<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 verification — entity code composite unique constraint migration.
 *
 * Verifies:
 * 1. assertNoDuplicates pre-check aborts loudly when pre-existing same-store
 *    duplicates exist, without applying schema changes.
 * 2. Migration succeeds cleanly on clean non-duplicate data across all tables.
 * 3. Two different stores CAN share the same code value (per-store unique, not global).
 * 4. Attempting to insert a duplicate code in the SAME store violates the composite unique constraint.
 */
class EntityCodeMigrationDuplicatePreCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_aborts_loudly_on_same_store_duplicate(): void
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/code_dupe_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        config([
            'database.connections.code_precheck' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'code_precheck',
        ]);
        DB::purge('code_precheck');

        try {
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Create all target tables with plain indexes and no composite unique
            $tables = [
                'db_sales' => 'sales_code',
                'db_purchase' => 'purchase_code',
                'db_quotation' => 'quotation_code',
                'db_customers' => 'customer_code',
                'db_suppliers' => 'supplier_code',
                'db_expense' => 'expense_code',
                'db_custadvance' => 'payment_code',
                'db_salesreturn' => 'return_code',
                'db_purchasereturn' => 'return_code',
            ];

            foreach ($tables as $table => $col) {
                $pdo->exec("CREATE TABLE {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    store_id INTEGER NULL,
                    {$col} TEXT NULL
                )");
                $pdo->exec("CREATE INDEX {$table}_{$col}_index ON {$table} ({$col})");
            }

            // Seed duplicates in Store 1 for db_sales (representative table)
            // and legitimate cross-store code in Store 2
            $ins = $pdo->prepare("INSERT INTO db_sales (store_id, sales_code) VALUES (?, ?)");
            $ins->execute([1, 'SA-00001']);
            $ins->execute([1, 'SA-00001']); // duplicate in store 1
            $ins->execute([2, 'SA-00001']); // same code in store 2 (legitimate cross-store)

            $migration = require base_path('database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php');

            try {
                $migration->up();
                $this->fail('The migration must abort loudly on same-store duplicates; it must NOT proceed to schema changes.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Pre-existing duplicate db_sales.sales_code detected per store', $e->getMessage());
                $this->assertStringContainsString('db_sales_store_sales_code_unique', $e->getMessage());

                // Assert unique index was NOT created
                $idx = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='db_sales_store_sales_code_unique'")->fetch();
                $this->assertFalse($idx, 'Unique index must not be created when pre-check fails.');

                // Assert both duplicate rows remain untouched
                $cnt = (int) $pdo->query("SELECT COUNT(*) FROM db_sales WHERE store_id=1 AND sales_code='SA-00001'")->fetchColumn();
                $this->assertSame(2, $cnt);
            }
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('code_precheck');
            @unlink($dbPath);
        }
    }

    public function test_migration_succeeds_and_enforces_per_store_uniqueness(): void
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/code_clean_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        config([
            'database.connections.code_clean' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'code_clean',
        ]);
        DB::purge('code_clean');

        try {
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $tables = [
                'db_sales' => 'sales_code',
                'db_purchase' => 'purchase_code',
                'db_quotation' => 'quotation_code',
                'db_customers' => 'customer_code',
                'db_suppliers' => 'supplier_code',
                'db_expense' => 'expense_code',
                'db_custadvance' => 'payment_code',
                'db_salesreturn' => 'return_code',
                'db_purchasereturn' => 'return_code',
            ];

            foreach ($tables as $table => $col) {
                $pdo->exec("CREATE TABLE {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    store_id INTEGER NULL,
                    {$col} TEXT NULL
                )");
                $pdo->exec("CREATE INDEX {$table}_{$col}_index ON {$table} ({$col})");
            }

            // Seed clean data: Store 1 has 'CODE-1', Store 2 ALSO has 'CODE-1' (cross-store reuse)
            foreach ($tables as $table => $col) {
                $ins = $pdo->prepare("INSERT INTO {$table} (store_id, {$col}) VALUES (?, ?)");
                $ins->execute([1, 'CODE-001']);
                $ins->execute([2, 'CODE-001']); // Allowed cross-store
            }

            $migration = require base_path('database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php');
            $migration->up();

            // Verify unique index exists on all tables
            foreach ($tables as $table => $col) {
                $uniqueName = "{$table}_store_{$col}_unique";
                $idx = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='{$uniqueName}'")->fetch();
                $this->assertNotEmpty($idx, "Composite unique index {$uniqueName} must exist on {$table}.");

                // Inserting another identical code in Store 1 must fail with UNIQUE constraint violation
                try {
                    $pdo->prepare("INSERT INTO {$table} (store_id, {$col}) VALUES (?, ?)")->execute([1, 'CODE-001']);
                    $this->fail("Inserting duplicate {$col} in same store on {$table} must violate composite unique.");
                } catch (\PDOException $e) {
                    $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
                }

                // Inserting same code in Store 3 must succeed (per-store unique, not global)
                $pdo->prepare("INSERT INTO {$table} (store_id, {$col}) VALUES (?, ?)")->execute([3, 'CODE-001']);
                $cntStore3 = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE store_id=3 AND {$col}='CODE-001'")->fetchColumn();
                $this->assertSame(1, $cntStore3, "Store 3 must be able to use CODE-001 independently.");
            }
        } finally {
            config(['database.default' => 'sqlite']);
            DB::purge('code_clean');
            @unlink($dbPath);
        }
    }
}
