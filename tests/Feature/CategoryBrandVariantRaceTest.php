<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.2: genuine parallel create race for Categories / Brands / Variants.
 *
 * Two independent OS child processes (proc_open) are released simultaneously by a
 * spinlock barrier file. Each worker runs the SAME guarded insert the controller
 * performs (a plain INSERT against the same file-backed SQLite DB that carries the
 * real per-store composite unique indexes added by the 2026_09_08_000001 migration).
 * The validation rule can pass in both processes concurrently, so exactly one insert
 * must win at the DB level and the loser must surface a clean "UNIQUE constraint
 * failed" — which the controller's try/catch translates into a user-facing
 * duplicate-name error instead of a raw 500.
 */
class CategoryBrandVariantRaceTest extends TestCase
{
    use RefreshDatabase;

    private function runParallelRace(string $table, string $column, string $indexName): void
    {
        $dbPath = sys_get_temp_dir() . '/cbt_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/cbt_race_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/cbt_race_worker_' . uniqid() . '.php';

        // Create the shared SQLite DB with the exact same schema the migration produces.
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE {$table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER NULL,
            {$column} VARCHAR(255) NULL,
            status INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");
        $pdo->exec("CREATE UNIQUE INDEX {$indexName} ON {$table} (store_id, {$column})");

        // Worker script built with single-quoted PHP string + addslashes at build
        // time (the exact pattern in SupplierRedesignAndRisksTest).
        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $table = "' . addslashes($table) . '";
        $column = "' . addslashes($column) . '";

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("INSERT INTO {$table} (store_id, {$column}, status, created_at, updated_at)
                        VALUES (1, \'RaceName\', 1, datetime(\'now\'), datetime(\'now\'))");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if (str_contains(strtolower($e->getMessage()), "unique")) {
                echo "RESULT:UNIQUE_FAIL\n";
            } else {
                echo "RESULT:OTHER_FAIL:" . $e->getMessage() . "\n";
            }
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes2);

        // Release the barrier so both workers race their inserts concurrently.
        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]);
        $out2 = stream_get_contents($pipes2[1]);
        fclose($pipes1[1]);
        fclose($pipes2[1]);
        proc_close($proc1);
        proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $combined = $out1 . $out2;

        $this->assertSame(1, substr_count($combined, 'RESULT:SUCCESS'), "Exactly one parallel insert must win.\nOut1: {$out1}\nOut2: {$out2}");
        $this->assertStringContainsString('RESULT:UNIQUE_FAIL', $combined, "The losing worker must fail on the per-store unique index.\nOut1: {$out1}\nOut2: {$out2}");

        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE store_id = 1 AND {$column} = 'RaceName'")->fetchColumn();
        $this->assertEquals(1, $count, "Exactly one {$table} row may exist for the same store + {$column} under genuine parallelism.");

        @unlink($dbPath);
    }

    public function test_genuine_parallel_category_create_exactly_one_succeeds(): void
    {
        $this->runParallelRace('db_category', 'category_name', 'db_category_store_category_name_unique');
    }

    public function test_genuine_parallel_brand_create_exactly_one_succeeds(): void
    {
        $this->runParallelRace('db_brands', 'brand_name', 'db_brands_store_brand_name_unique');
    }

    public function test_genuine_parallel_variant_create_exactly_one_succeeds(): void
    {
        $this->runParallelRace('db_variants', 'variant_name', 'db_variants_store_variant_name_unique');
    }
}
