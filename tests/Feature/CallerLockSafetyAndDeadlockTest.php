<?php

namespace Tests\Feature;

use App\Models\DbItem;
use App\Models\DbQuotation;
use App\Models\DbQuotationItem;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 verification — tests caller safety, no deadlocks under the new locking,
 * and concurrent item creation retry.
 *
 * 1. Concurrent Quotation-to-Sale conversion: Two real OS worker processes
 *    concurrently convert two distinct quotations in the same store. Proves
 *    the locking order across DbWarehouseItem, DbStore, and DbSale does NOT
 *    deadlock, and both quotations convert with distinct sales codes.
 * 2. Concurrent Item Creation: Two real OS worker processes concurrently create
 *    items in the same store. Proves both succeed with distinct item codes
 *    without throwing unhandled SQL 23000 errors.
 */
class CallerLockSafetyAndDeadlockTest extends TestCase
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

    protected function seedQuotationDb(string $dbPath, string $appKey): array
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

$store = \App\Models\DbStore::create(['id' => 1, 'store_name' => 'Deadlock Test Store', 'status' => 1, 'sales_init' => 'SA', 'item_init' => 'IT']);
$role = \App\Models\DbRole::create(['id' => 1, 'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
\App\Models\DbPermission::create(['role_id' => 1, 'store_id' => 1, 'permissions' => []]);
$user = \App\Models\User::factory()->create(['id' => 1, 'store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin']);

$wh = \App\Models\DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main WH', 'status' => 1]);
$tax = \App\Models\DbTax::create(['store_id' => 1, 'tax_name' => 'No Tax', 'tax' => 0, 'status' => 1]);
$unit = \App\Models\DbUnit::create(['store_id' => 1, 'unit_name' => 'Piece', 'status' => 1]);
$cat = \App\Models\DbCategory::create(['store_id' => 1, 'category_name' => 'General', 'status' => 1]);

$item = \App\Models\DbItem::create([
    'store_id' => 1,
    'item_code' => 'IT-00001',
    'item_name' => 'Test Item',
    'category_id' => $cat->id,
    'unit_id' => $unit->id,
    'tax_id' => $tax->id,
    'sales_price' => 50,
    'purchase_price' => 30,
    'stock' => 100,
    'status' => 1,
]);

\App\Models\DbWarehouseItem::create([
    'store_id' => 1,
    'warehouse_id' => $wh->id,
    'item_id' => $item->id,
    'available_qty' => 100,
]);

$q1 = \App\Models\DbQuotation::create([
    'store_id' => 1,
    'warehouse_id' => $wh->id,
    'quotation_code' => 'QU-00001',
    'quotation_date' => date('Y-m-d'),
    'quotation_status' => 'Quoted',
    'subtotal' => 50,
    'grand_total' => 50,
    'status' => 1,
]);
\App\Models\DbQuotationItem::create([
    'store_id' => 1,
    'quotation_id' => $q1->id,
    'item_id' => $item->id,
    'quotation_qty' => 1,
    'price_per_unit' => 50,
    'total_cost' => 50,
]);

$q2 = \App\Models\DbQuotation::create([
    'store_id' => 1,
    'warehouse_id' => $wh->id,
    'quotation_code' => 'QU-00002',
    'quotation_date' => date('Y-m-d'),
    'quotation_status' => 'Quoted',
    'subtotal' => 50,
    'grand_total' => 50,
    'status' => 1,
]);
\App\Models\DbQuotationItem::create([
    'store_id' => 1,
    'quotation_id' => $q2->id,
    'item_id' => $item->id,
    'quotation_qty' => 1,
    'price_per_unit' => 50,
    'total_cost' => 50,
]);

echo "META_Q1:{$q1->id}\n";
echo "META_Q2:{$q2->id}\n";
echo "META_ITEM_ID:{$item->id}\n";
echo "META_CAT_ID:{$cat->id}\n";
echo "META_UNIT_ID:{$unit->id}\n";
echo "META_TAX_ID:{$tax->id}\n";
echo "META_WH_ID:{$wh->id}\n";
PHP;

        $scriptPath = sys_get_temp_dir() . '/dl_seed_' . uniqid() . '.php';
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

        $meta = [];
        foreach ($outLines as $l) {
            $trimmed = trim($l);
            if (str_starts_with($trimmed, 'META_') && str_contains($trimmed, ':')) {
                [$k, $v] = explode(':', $trimmed, 2);
                $meta[substr($k, 5)] = (int) trim($v);
            }
        }

        if (empty($meta['Q1']) || empty($meta['CAT_ID'])) {
            $this->fail("Seed worker did not output expected metadata. Output:\n" . implode("\n", $outLines));
        }

        return $meta;
    }

    protected function executeParallel(string $php, string $s1, string $s2, string $barrierFile, string $res1, string $res2): array
    {
        $log1 = sys_get_temp_dir() . '/cw1_' . uniqid() . '.log';
        $log2 = sys_get_temp_dir() . '/cw2_' . uniqid() . '.log';
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

    public function test_concurrent_quotation_to_sale_conversion_has_no_deadlock(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/q2s_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/q2s_bar_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/q2s_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/q2s_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/q2s_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/q2s_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedQuotationDb($dbPath, $appKey);
            $q1Id = $meta['Q1'];
            $q2Id = $meta['Q2'];

            $buildWorker = function (int $quotationId, string $resFile) use ($dbPath, $appKey, $barrierFile) {
                $base = dirname(__DIR__, 2);
                return <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

\$base = '{$base}';
\$dbPath = '{$dbPath}';
\$appKey = '{$appKey}';
\$barrier = '{$barrierFile}';
\$resultFile = '{$resFile}';
\$quotationId = (int) '{$quotationId}';

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

\Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000;');

\$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login(\$user);

while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    \$request = \Illuminate\Http\Request::create('/quotation/' . \$quotationId . '/convert', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    \$controller = \$app->make(\App\Http\Controllers\QuotationController::class);
    \$response = \$controller->convertToSale(\$request, \$quotationId);
    \$data = json_decode(\$response->getContent(), true);

    if (isset(\$data['success']) && \$data['success']) {
        file_put_contents(\$resultFile, 'SUCCESS:SALE_ID:' . (\$data['sale_id'] ?? 0));
    } else {
        file_put_contents(\$resultFile, 'FAIL:' . (\$data['message'] ?? 'unknown'));
    }
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, 'ERROR:' . \$e->getMessage());
}
PHP;
            };

            file_put_contents($s1, $buildWorker($q1Id, $res1));
            file_put_contents($s2, $buildWorker($q2Id, $res2));

            [$out1, $out2] = $this->executeParallel($php, $s1, $s2, $barrierFile, $res1, $res2);

            $this->assertStringStartsWith('SUCCESS:SALE_ID:', $out1, "Worker 1 failed: {$out1}");
            $this->assertStringStartsWith('SUCCESS:SALE_ID:', $out2, "Worker 2 failed: {$out2}");

            // Verify both sales exist with distinct sales_code
            $pdo = new \PDO('sqlite:' . $dbPath);
            $codes = $pdo->query("SELECT sales_code FROM db_sales WHERE store_id = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertCount(2, $codes);
            $this->assertNotEquals($codes[0], $codes[1], "Both sales must have distinct sales_code.");
            $this->assertContains('SA-00001', $codes);
            $this->assertContains('SA-00002', $codes);

            // Verify both quotations transitioned to 'Converted'
            $st1 = $pdo->query("SELECT quotation_status FROM db_quotation WHERE id = {$q1Id}")->fetchColumn();
            $st2 = $pdo->query("SELECT quotation_status FROM db_quotation WHERE id = {$q2Id}")->fetchColumn();
            $this->assertEquals('Converted', $st1);
            $this->assertEquals('Converted', $st2);
        } finally {
            @unlink($dbPath);
            @unlink($barrierFile);
            @unlink($res1);
            @unlink($res2);
            @unlink($s1);
            @unlink($s2);
        }
    }

    public function test_concurrent_item_creation_succeeds_with_distinct_item_codes(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/item_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/item_bar_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/item_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/item_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/item_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/item_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedQuotationDb($dbPath, $appKey);
            $catId = $meta['CAT_ID'];
            $unitId = $meta['UNIT_ID'];
            $taxId = $meta['TAX_ID'];
            $whId = $meta['WH_ID'];

            $buildWorker = function (string $name, string $resFile) use ($dbPath, $appKey, $barrierFile, $catId, $unitId, $taxId, $whId) {
                $base = dirname(__DIR__, 2);
                return <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

\$base = '{$base}';
\$dbPath = '{$dbPath}';
\$appKey = '{$appKey}';
\$barrier = '{$barrierFile}';
\$resultFile = '{$resFile}';
\$name = '{$name}';

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

\Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode=WAL;');
\Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000;');

\$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login(\$user);

while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    \$service = \$app->make(\App\Services\ItemCreationService::class);
    \$request = \Illuminate\Http\Request::create('/items', 'POST', [
        'warehouse_id' => {$whId},
        'price' => 100,
        'opening_stock' => 5,
    ], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

    \$validated = [
        'item_name' => \$name,
        'category_id' => {$catId},
        'unit_id' => {$unitId},
        'tax_id' => {$taxId},
        'tax_type' => 'Exclusive',
        'sales_price' => 150,
        'opening_stock' => 5,
    ];

    \$item = \$service->createSingleItem(\$validated, \$request);
    file_put_contents(\$resultFile, 'CODE:' . \$item->item_code);
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, 'ERROR:' . \$e->getMessage());
}
PHP;
            };

            file_put_contents($s1, $buildWorker('Parallel Item 1', $res1));
            file_put_contents($s2, $buildWorker('Parallel Item 2', $res2));

            [$out1, $out2] = $this->executeParallel($php, $s1, $s2, $barrierFile, $res1, $res2);

            $this->assertStringStartsWith('CODE:', $out1, "Worker 1 failed: {$out1}");
            $this->assertStringStartsWith('CODE:', $out2, "Worker 2 failed: {$out2}");

            $code1 = substr($out1, strlen('CODE:'));
            $code2 = substr($out2, strlen('CODE:'));

            $this->assertNotEquals($code1, $code2, "Two concurrent items must receive distinct codes.");
            $this->assertContains($code1, ['IT-00002', 'IT-00003']);
            $this->assertContains($code2, ['IT-00002', 'IT-00003']);

            $pdo = new \PDO('sqlite:' . $dbPath);
            $count = (int) $pdo->query("SELECT COUNT(DISTINCT item_code) FROM db_items WHERE store_id = 1")->fetchColumn();
            // Seeded IT-00001 plus the two new items = 3 distinct codes
            $this->assertSame(3, $count, "Exactly 3 distinct item codes must exist in DB.");
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
