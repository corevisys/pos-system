<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GAP 3 — Phase 5 migration pre-check aborts loudly on pre-existing same-store
 * duplicate warehouse_name rows.
 *
 * Isolated in its own class AND run against a dedicated named connection
 * ('wh_gap3') so the framework's default sqlite (:memory:) connection is never
 * purged — keeping sibling classes' RefreshDatabase state intact.
 */
class WarehouseMigrationDuplicatePreCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap3_phase5_migration_assert_no_duplicates_aborts_on_same_store_duplicate(): void
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/wh_dupe_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        // Register a DEDICATED connection pointing at the scratch file; never
        // touch the default 'sqlite' (:memory:) connection.
        config([
            'database.connections.wh_gap3' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'wh_gap3',
        ]);
        DB::purge('wh_gap3');

        try {
            // 1. Pre-Phase-5 schema on the scratch DB: plain indexes only, NO
            //    (store_id, warehouse_name) unique.
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE TABLE db_warehouse (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER NULL,
                warehouse_type TEXT NULL,
                warehouse_name TEXT NULL,
                mobile TEXT NULL,
                email TEXT NULL,
                status INTEGER DEFAULT 1,
                created_date TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                delete_bit INTEGER DEFAULT 0
            )");
            $pdo->exec("CREATE INDEX db_warehouse_store_id_index ON db_warehouse (store_id)");
            $pdo->exec("CREATE INDEX db_warehouse_warehouse_name_index ON db_warehouse (warehouse_name)");

            // 2. Dirty legacy data: same name twice in Store 1 (duplicate), same
            //    name once in Store 2 (legitimate cross-store reuse).
            $ins = $pdo->prepare("INSERT INTO db_warehouse (store_id, warehouse_name, status, delete_bit) VALUES (?, ?, 1, 0)");
            $ins->execute([1, 'DupName']);
            $ins->execute([1, 'DupName']);
            $ins->execute([2, 'DupName']);

            // 3. Run the REAL Phase-5 migration up() against the scratch DB
            //    (the default connection now resolves to 'wh_gap3').
            $migration = require base_path('database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php');

            try {
                $migration->up();
                $this->fail('The Phase-5 migration must abort loudly on same-store duplicates; it must NOT proceed to schema changes.');
            } catch (\RuntimeException $e) {
                // 4. Loud abort: pre-check fired, unique constraint never added.
                $this->assertStringContainsString('Pre-existing duplicate db_warehouse.warehouse_name', $e->getMessage());
                $this->assertStringContainsString('db_warehouse_store_warehouse_name_unique', $e->getMessage());

                // 5. Schema untouched (no silent dedupe, no unique index added).
                $idx = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='db_warehouse_store_warehouse_name_unique'")->fetch();
                $this->assertFalse($idx, 'The unique index must NOT have been added (migration aborted before schema changes).');
                $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM db_warehouse WHERE store_id=1 AND warehouse_name='DupName'")->fetchColumn();
                $this->assertSame(2, $rowCount, 'Both duplicate rows must still exist (no silent dedupe).');
            }
        } finally {
            // Restore the default connection NAME and drop only the dedicated one.
            config(['database.default' => 'sqlite']);
            DB::purge('wh_gap3');
            @unlink($dbPath);
        }
    }
}
