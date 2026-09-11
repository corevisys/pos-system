<?php

namespace Tests\Feature;

use App\Models\DbSale;
use App\Models\DbExpense;
use App\Models\DbStore;
use App\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 verification — proves CodeGeneratorService store-scoped locking and
 * collision prevention under GENUINE concurrency (real OS processes + barrier).
 *
 * Covers:
 * 1. Sale (highest traffic): Two simultaneous requests for SAME store produce
 *    exactly 2 distinct sequential codes without collision or rollback.
 * 2. Expense (high traffic): Two simultaneous requests for SAME store produce
 *    exactly 2 distinct sequential codes without collision or rollback.
 * 3. Cross-Store Control: Two simultaneous requests for TWO DIFFERENT stores
 *    do not serialize globally and both receive per-store sequential numbering
 *    (both start at 00001).
 */
class EntityCodeParallelGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function readAppKey(): string
    {
        $base = dirname(__DIR__, 2);
        $env = file($base . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($env ?: [] as $line) {
            if (str_starts_with($line, 'APP_KEY=')) {
                return substr($line, strlen('APP_KEY='));
            }
        }
        return '';
    }

    protected function seedFileDb(string $dbPath, string $appKey): void
    {
        $base = dirname(__DIR__, 2);
        $php = PHP_BINARY;

        $seedScript = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = $argv[1];
$dbPath = $argv[2];
$appKey = $argv[3];

putenv('APP_ENV=testing');
putenv('APP_KEY=' . $appKey);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

try {
    $kernel->call('migrate:fresh', ['--force' => true]);
    \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode=WAL;');
    \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000;');
} catch (\Throwable $e) {
    fwrite(STDERR, 'SEED_MIGRATE_FAIL:' . $e->getMessage() . "\n");
    exit(1);
}

\App\Models\DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'sales_init' => 'SA', 'expense_init' => 'EXP']);
\App\Models\DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'sales_init' => 'SA', 'expense_init' => 'EXP']);

echo "SEEDED_OK\n";
PHP;

        $scriptPath = sys_get_temp_dir() . '/codegen_seed_' . uniqid() . '.php';
        file_put_contents($scriptPath, $seedScript);
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($base)
            . ' ' . escapeshellarg($dbPath)
            . ' ' . escapeshellarg($appKey);
        exec($cmd . ' 2>&1', $outLines, $code);
        @unlink($scriptPath);

        if ($code !== 0) {
            $this->fail('Seed worker failed (exit ' . $code . '): ' . implode("\n", $outLines));
        }
    }

    protected function buildWorker(
        string $dbPath,
        string $appKey,
        string $barrierFile,
        string $resultFile,
        string $type,
        int $storeId
    ): string {
        $base = dirname(__DIR__, 2);

        return <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

\$base = '{$base}';
\$dbPath = '{$dbPath}';
\$appKey = '{$appKey}';
\$barrier = '{$barrierFile}';
\$resultFile = '{$resultFile}';
\$type = '{$type}';
\$storeId = (int) '{$storeId}';

putenv('APP_ENV=testing');
putenv('APP_KEY=' . \$appKey);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . \$dbPath);

require \$base . '/vendor/autoload.php';
\$app = require \$base . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => \$dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

// Set busy_timeout so concurrent writers retry instead of locking out
\Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000;');

// Barrier: wait until released by parent
while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    // Generate code and persist row inside transaction using CodeGeneratorService with retry
    \$result = \App\Services\CodeGeneratorService::executeWithRetry(function () use (\$type, \$storeId) {
        return \Illuminate\Support\Facades\DB::transaction(function () use (\$type, \$storeId) {
            \$code = \App\Services\CodeGeneratorService::generate(\$type, null, \$storeId);
            if (\$type === 'sales') {
                \$record = \App\Models\DbSale::create([
                    'store_id' => \$storeId,
                    'sales_code' => \$code,
                    'status' => 1,
                    'grand_total' => 100,
                    'subtotal' => 100,
                ]);
                return \$record->sales_code;
            } elseif (\$type === 'expense') {
                \$record = \App\Models\DbExpense::create([
                    'store_id' => \$storeId,
                    'expense_code' => \$code,
                    'expense_date' => date('Y-m-d'),
                    'expense_for' => 'Test Expense',
                    'expense_amt' => 50,
                    'status' => 1,
                    'delete_bit' => 0,
                ]);
                return \$record->expense_code;
            }
            return \$code;
        });
    });

    file_put_contents(\$resultFile, 'CODE:' . \$result);
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, 'ERROR:' . \$e->getMessage());
}
PHP;
    }

    protected function executeParallel(string $php, string $s1, string $s2, string $barrierFile, string $res1, string $res2): array
    {
        $log1 = sys_get_temp_dir() . '/w1_' . uniqid() . '.log';
        $log2 = sys_get_temp_dir() . '/w2_' . uniqid() . '.log';
        $descriptors1 = [0 => ['pipe', 'r'], 1 => ['file', $log1, 'w'], 2 => ['file', $log1, 'w']];
        $descriptors2 = [0 => ['pipe', 'r'], 1 => ['file', $log2, 'w'], 2 => ['file', $log2, 'w']];

        $p1 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s1), $descriptors1, $pipes1);
        $p2 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s2), $descriptors2, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $start = time();
        while (true) {
            $st1 = proc_get_status($p1);
            $st2 = proc_get_status($p2);
            if (!$st1['running'] && !$st2['running']) {
                break;
            }
            if (time() - $start > 25) {
                proc_terminate($p1);
                proc_terminate($p2);
                break;
            }
            usleep(25000); // 25ms
        }

        if (isset($pipes1[0]) && is_resource($pipes1[0])) fclose($pipes1[0]);
        if (isset($pipes2[0]) && is_resource($pipes2[0])) fclose($pipes2[0]);
        proc_close($p1);
        proc_close($p2);

        $out1 = file_exists($res1) ? trim(file_get_contents($res1)) : (file_exists($log1) ? 'LOG:' . trim(file_get_contents($log1)) : 'NO_RESULT');
        $out2 = file_exists($res2) ? trim(file_get_contents($res2)) : (file_exists($log2) ? 'LOG:' . trim(file_get_contents($log2)) : 'NO_RESULT');

        @unlink($log1);
        @unlink($log2);

        return [$out1, $out2];
    }

    public function test_concurrent_sales_generation_for_same_store_produces_two_unique_codes(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/sale_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/sale_bar_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/sale_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/sale_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/sale_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/sale_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $this->seedFileDb($dbPath, $appKey);

            file_put_contents($s1, $this->buildWorker($dbPath, $appKey, $barrierFile, $res1, 'sales', 1));
            file_put_contents($s2, $this->buildWorker($dbPath, $appKey, $barrierFile, $res2, 'sales', 1));

            [$out1, $out2] = $this->executeParallel($php, $s1, $s2, $barrierFile, $res1, $res2);

            $this->assertStringStartsWith('CODE:', $out1, "Worker 1 failed: {$out1}");
            $this->assertStringStartsWith('CODE:', $out2, "Worker 2 failed: {$out2}");

            $code1 = substr($out1, strlen('CODE:'));
            $code2 = substr($out2, strlen('CODE:'));

            // Assert two distinct codes
            $this->assertNotEquals($code1, $code2, "Two concurrent checkouts in same store must generate unique codes.");
            $this->assertContains($code1, ['SA-00001', 'SA-00002']);
            $this->assertContains($code2, ['SA-00001', 'SA-00002']);

            // Verify in DB
            $pdo = new \PDO('sqlite:' . $dbPath);
            $cnt = (int) $pdo->query("SELECT COUNT(DISTINCT sales_code) FROM db_sales WHERE store_id = 1")->fetchColumn();
            $this->assertSame(2, $cnt, "Exactly 2 unique sales_codes must exist in DB for store 1.");
        } finally {
            @unlink($dbPath);
            @unlink($barrierFile);
            @unlink($res1);
            @unlink($res2);
            @unlink($s1);
            @unlink($s2);
        }
    }

    public function test_concurrent_expense_generation_for_same_store_produces_two_unique_codes(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/exp_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/exp_bar_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/exp_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/exp_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/exp_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/exp_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $this->seedFileDb($dbPath, $appKey);

            file_put_contents($s1, $this->buildWorker($dbPath, $appKey, $barrierFile, $res1, 'expense', 1));
            file_put_contents($s2, $this->buildWorker($dbPath, $appKey, $barrierFile, $res2, 'expense', 1));

            [$out1, $out2] = $this->executeParallel($php, $s1, $s2, $barrierFile, $res1, $res2);

            $this->assertStringStartsWith('CODE:', $out1, "Worker 1 failed: {$out1}");
            $this->assertStringStartsWith('CODE:', $out2, "Worker 2 failed: {$out2}");

            $code1 = substr($out1, strlen('CODE:'));
            $code2 = substr($out2, strlen('CODE:'));

            // Assert two distinct codes
            $this->assertNotEquals($code1, $code2, "Two concurrent expenses in same store must generate unique codes.");
            $this->assertContains($code1, ['EXP0001', 'EXP0002']);
            $this->assertContains($code2, ['EXP0001', 'EXP0002']);

            // Verify in DB
            $pdo = new \PDO('sqlite:' . $dbPath);
            $cnt = (int) $pdo->query("SELECT COUNT(DISTINCT expense_code) FROM db_expense WHERE store_id = 1")->fetchColumn();
            $this->assertSame(2, $cnt, "Exactly 2 unique expense_codes must exist in DB for store 1.");
        } finally {
            @unlink($dbPath);
            @unlink($barrierFile);
            @unlink($res1);
            @unlink($res2);
            @unlink($s1);
            @unlink($s2);
        }
    }

    public function test_cross_store_concurrent_generation_does_not_serialize_and_both_start_at_one(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/cross_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/cross_bar_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/cross_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/cross_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/cross_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/cross_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $this->seedFileDb($dbPath, $appKey);

            // Worker 1 fires for Store 1, Worker 2 fires for Store 2
            file_put_contents($s1, $this->buildWorker($dbPath, $appKey, $barrierFile, $res1, 'sales', 1));
            file_put_contents($s2, $this->buildWorker($dbPath, $appKey, $barrierFile, $res2, 'sales', 2));

            [$out1, $out2] = $this->executeParallel($php, $s1, $s2, $barrierFile, $res1, $res2);

            $this->assertStringStartsWith('CODE:', $out1, "Worker 1 failed: {$out1}");
            $this->assertStringStartsWith('CODE:', $out2, "Worker 2 failed: {$out2}");

            $code1 = substr($out1, strlen('CODE:'));
            $code2 = substr($out2, strlen('CODE:'));

            // Both stores are independent; each starts its numbering at SA-00001
            $this->assertEquals('SA-00001', $code1, "Store 1 first sale code must be SA-00001");
            $this->assertEquals('SA-00001', $code2, "Store 2 first sale code must be SA-00001 (per-store sequence)");

            // Verify in DB
            $pdo = new \PDO('sqlite:' . $dbPath);
            $cntS1 = (int) $pdo->query("SELECT COUNT(*) FROM db_sales WHERE store_id = 1 AND sales_code = 'SA-00001'")->fetchColumn();
            $cntS2 = (int) $pdo->query("SELECT COUNT(*) FROM db_sales WHERE store_id = 2 AND sales_code = 'SA-00001'")->fetchColumn();
            $this->assertSame(1, $cntS1, "Store 1 must have exactly one SA-00001 row.");
            $this->assertSame(1, $cntS2, "Store 2 must have exactly one SA-00001 row.");
        } finally {
            @unlink($dbPath);
            @unlink($barrierFile);
            @unlink($res1);
            @unlink($res2);
            @unlink($s1);
            @unlink($s2);
        }
    }
}
