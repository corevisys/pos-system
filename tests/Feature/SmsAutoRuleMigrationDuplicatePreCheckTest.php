<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GAP 1 — Phase 5 migration pre-check aborts loudly on pre-existing same-store
 * duplicate (store_id, event_type) rows.
 *
 * Mirrors WarehouseMigrationDuplicatePreCheckTest exactly: isolated in its own
 * class AND run against a dedicated named connection ('sms_gap1') so the
 * framework's default sqlite (:memory:) connection is never purged — keeping
 * sibling classes' RefreshDatabase state intact.
 */
class SmsAutoRuleMigrationDuplicatePreCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap1_phase5_migration_aborts_on_same_store_duplicate_event_type(): void
    {
        $dbPath = sys_get_temp_dir() . '/sms_rule_dupe_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        // Register a DEDICATED connection pointing at the scratch file; never
        // touch the default 'sqlite' (:memory:) connection.
        config([
            'database.connections.sms_gap1' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'sms_gap1',
        ]);
        DB::purge('sms_gap1');

        try {
            // 1. Pre-Phase-5 schema on the scratch DB: plain event_type index
            //    only, NO (store_id, event_type) composite unique.
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE TABLE sms_auto_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER NOT NULL,
                rule_name TEXT NULL,
                event_type TEXT NULL,
                event_source TEXT NULL,
                template_id INTEGER NULL,
                trigger_time TEXT DEFAULT 'immediate',
                is_active INTEGER DEFAULT 1,
                last_executed_at TEXT NULL,
                deleted_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )");
            $pdo->exec("CREATE INDEX sms_auto_rules_event_type_index ON sms_auto_rules (event_type)");

            // 2. Dirty legacy data: same event_type twice in Store 1 (duplicate),
            //    and once in Store 2 (legitimate cross-store reuse — must NOT be
            //    flagged).
            $ins = $pdo->prepare("INSERT INTO sms_auto_rules (store_id, event_type, is_active) VALUES (?, ?, 1)");
            $ins->execute([1, 'InvoiceCreated']);
            $ins->execute([1, 'InvoiceCreated']);
            $ins->execute([2, 'InvoiceCreated']);

            // 3. Run the REAL Phase-5 migration up() against the scratch DB
            //    (the default connection now resolves to 'sms_gap1').
            $migration = require base_path('database/migrations/2026_09_13_000001_make_event_type_unique_per_store_on_sms_auto_rules.php');

            try {
                $migration->up();
                $this->fail('The Phase-5 migration must abort loudly on same-store duplicates; it must NOT proceed to schema changes.');
            } catch (\RuntimeException $e) {
                // 4. Loud abort: pre-check fired, unique constraint never added.
                $this->assertStringContainsString('make_event_type_unique_per_store_on_sms_auto_rules aborted', $e->getMessage());
                $this->assertStringContainsString('duplicate', $e->getMessage());
                $this->assertStringContainsString("store_id=1 event_type='InvoiceCreated' count=2", $e->getMessage());

                // 5. Schema untouched (no silent dedupe, no unique index added).
                $idx = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='sms_auto_rules_store_event_type_unique'")->fetch();
                $this->assertFalse($idx, 'The composite unique index must NOT have been added (migration aborted before schema changes).');

                $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM sms_auto_rules WHERE store_id=1 AND event_type='InvoiceCreated'")->fetchColumn();
                $this->assertSame(2, $rowCount, 'Both duplicate rows must still exist (no silent dedupe).');

                // 6. The legitimate cross-store row is untouched too.
                $storeTwoCount = (int) $pdo->query("SELECT COUNT(*) FROM sms_auto_rules WHERE store_id=2 AND event_type='InvoiceCreated'")->fetchColumn();
                $this->assertSame(1, $storeTwoCount);
            }
        } finally {
            // Restore the default connection NAME and drop only the dedicated one.
            config(['database.default' => 'sqlite']);
            DB::purge('sms_gap1');
            @unlink($dbPath);
        }
    }
}
