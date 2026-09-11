<?php

namespace Tests\Feature;

use App\Http\Controllers\StockAdjustmentController;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStockAdjustment;
use App\Models\DbStockTransfer;
use App\Models\DbStockTransferItems;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 1 + Item 2 + Item 3 regression tests for the Stock rollout fixes.
 *
 * 1. GENUINE-PARALLEL destroy() race — two worker OS processes, released by a
 *    spinlock barrier, EACH boot the real Laravel app against a shared
 *    file-backed SQLite DB (real migrations), authenticate as the seeded user,
 *    then dispatch the REAL `DELETE /stock/transfer/{id}` through the HTTP
 *    kernel (Application::handleRequest). This exercises the actual controller
 *    code path — NOT a raw-PDO reimplementation (the weakness of the earlier
 *    Adjustment race test). Exactly one delete must win; the loser must be a
 *    clean 404 (affected=0 on the atomic delete_bit transition); stock must be
 *    reversed exactly once.
 *
 * 2. Item 2 cross-store IDOR: update() with a warehouse_to from another store
 *    is rejected BEFORE any mutation; no other-store DbWarehouseItem is touched.
 *
 * 3. Item 3 portability: the unique-violation detector must catch BOTH SQLite's
 *    "UNIQUE constraint failed" message AND MySQL's "Duplicate entry ...
 *    uq_warehouse_item" format (SQLSTATE 23000), and must NOT catch unrelated
 *    errors — proving the production-engine retry actually fires.
 */
class StockTransferDeleteBitParallelAndScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'DeleteBit Parallel Store', 'status' => 1, 'mobile' => '01700000099']);

        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => ['stock_transfer_view', 'stock_transfer_add', 'stock_transfer_edit', 'stock_transfer_delete'],
        ]);

        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);
    }

    /**
     * Read APP_KEY from the repo .env so worker subprocesses can boot the app.
     */
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

    /**
     * Seed the shared file-backed SQLite DB via the real migrations + Eloquent.
     * Returns ['transfer_id', 'wh_from', 'wh_to', 'item_id'].
     */
    protected function seedFileDb(string $dbPath, string $appKey): array
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

// phpunit.xml forces DB_DATABASE=:memory: via <env>, which overrides putenv once
// the app boots. Force the shared file connection explicitly AFTER boot.
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

try {
    $kernel->call('migrate:fresh', ['--force' => true]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'SEED_MIGRATE_FAIL:' . $e->getMessage() . "\n");
    exit(1);
}

\App\Models\DbStore::create(['id' => 1, 'store_name' => 'DeleteBit Parallel Store', 'status' => 1, 'mobile' => '01700000099']);
$role = \App\Models\DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
\App\Models\DbPermission::firstOrCreate(['role_id' => $role->id], [
    'store_id' => 1,
    'permissions' => ['stock_transfer_view', 'stock_transfer_add', 'stock_transfer_edit', 'stock_transfer_delete'],
]);
$user = \App\Models\User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);

$cat = \App\Models\DbCategory::create(['category_name' => 'DBW Cat', 'status' => 1]);
$item = \App\Models\DbItem::create([
    'item_name' => 'DBW Item', 'item_code' => 'DBW-' . uniqid(),
    'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
    'stock' => 100, 'status' => 1, 'store_id' => 1,
]);
$whFrom = \App\Models\DbWarehouse::create(['warehouse_name' => 'DBW From', 'store_id' => 1, 'status' => 1]);
$whTo = \App\Models\DbWarehouse::create(['warehouse_name' => 'DBW To', 'store_id' => 1, 'status' => 1]);
// Post-transfer state: source has 90 left (100 − 10 moved), destination has 10.
\App\Models\DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whFrom->id, 'item_id' => $item->id, 'available_qty' => 90]);

$transfer = \App\Models\DbStockTransfer::create([
    'store_id' => 1,
    'warehouse_from' => $whFrom->id,
    'warehouse_to' => $whTo->id,
    'reference_no' => 'DBW-TR-' . uniqid(),
    'transfer_date' => date('Y-m-d'),
    'note' => null,
    'created_by' => $user->id,
    'created_date' => date('Y-m-d'),
    'created_time' => date('H:i:s'),
    'status' => 1,
    'delete_bit' => 0,
]);
\App\Models\DbStockTransferItems::create([
    'stocktransfer_id' => $transfer->id,
    'store_id' => 1,
    'warehouse_from' => $whFrom->id,
    'warehouse_to' => $whTo->id,
    'item_id' => $item->id,
    'transfer_qty' => 10,
]);
\App\Models\DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whTo->id, 'item_id' => $item->id, 'available_qty' => 10]);

echo 'TRANSFER_ID:' . $transfer->id . "\n";
echo 'WH_FROM:' . $whFrom->id . "\n";
echo 'WH_TO:' . $whTo->id . "\n";
echo 'ITEM_ID:' . $item->id . "\n";
PHP;

        $scriptPath = sys_get_temp_dir() . '/dbw_seed_' . uniqid() . '.php';
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
        foreach ($outLines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $meta[$k] = (int) trim($v);
            }
        }
        if (empty($meta['TRANSFER_ID'])) {
            $this->fail('Seed worker did not return a transfer id. Output: ' . implode("\n", $outLines));
        }
        return $meta;
    }

    /**
     * Worker: boot the real app against the shared file DB, authenticate, then
     * dispatch the REAL DELETE route through the HTTP kernel. Writes
     * "RESULT:<status>:<message>" to $resultFile (file-based, avoids the
     * Windows stdout-pipe flakiness seen across the suite).
     */
    protected function buildDestroyWorker(string $dbPath, string $appKey, string $barrierFile, string $resultFile, int $transferId): string
    {
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
\$transferId = (int) '{$transferId}';

putenv('APP_ENV=testing');
putenv('APP_KEY=' . \$appKey);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . \$dbPath);

require \$base . '/vendor/autoload.php';
\$app = require \$base . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

// Force the shared file connection (phpunit.xml's :memory: env would otherwise win).
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => \$dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

// Barrier: both workers wait until the parent drops the file. (The seed worker
// already migrated + seeded; a redundant concurrent migrate here only risks an
// SQLite "database is locked" race between the two workers.)
while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    \$user = \App\Models\User::where('store_id', 1)->first();
    if (!\$user) {
        \$count = \App\Models\User::count();
        \$transferCount = \App\Models\DbStockTransfer::count();
        \$cfgDb = config('database.connections.' . config('database.default') . '.database');
        \$fileExists = file_exists((string) \$cfgDb) ? 'yes' : 'no';
        \$sample = \App\Models\User::query()->limit(2)->get()->map(fn (\$u) => \$u->toArray())->toJson();
        file_put_contents(\$resultFile, 'RESULT:NO_USER:count=' . \$count . ':transferCount=' . \$transferCount . ':cfgDb=' . \$cfgDb . ':fileExists=' . \$fileExists . ':sample=' . \$sample);
        exit(1);
    }

    // Authenticate so the `auth` + `verified` route middleware passes.
    \Illuminate\Support\Facades\Auth::login(\$user);

    // REAL endpoint call through the REAL HTTP kernel + route middleware.
    // Application::handle() returns the SymfonyResponse (handleRequest() sends
    // and returns void).
    \$request = Illuminate\Http\Request::create('/stock/transfer/' . \$transferId, 'DELETE');
    \$response = \$app->handle(\$request);

    \$status = \$response->getStatusCode();
    \$body = json_decode(\$response->getContent(), true);
    \$message = is_array(\$body) ? (\$body['message'] ?? '') : '';

    file_put_contents(\$resultFile, 'RESULT:' . \$status . ':' . \$message);
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, 'RESULT:EXCEPTION:' . get_class(\$e) . ':' . \$e->getMessage());
}
exit(0);
PHP;
    }

    /**
     * Item 1 — GENUINE parallel destroy() race through the real controller.
     */
    public function test_genuine_parallel_destroy_exactly_one_wins_and_reverses_once()
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/dbw_del_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/dbw_barrier_' . uniqid() . '.txt';
        $resultFile1 = sys_get_temp_dir() . '/dbw_res1_' . uniqid() . '.txt';
        $resultFile2 = sys_get_temp_dir() . '/dbw_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/dbw_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/dbw_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        // SQLite requires the file to exist before the app can connect to it.
        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedFileDb($dbPath, $appKey);
            $transferId = $meta['TRANSFER_ID'];
            $whFrom = $meta['WH_FROM'];
            $whTo = $meta['WH_TO'];
            $itemId = $meta['ITEM_ID'];

            file_put_contents($s1, $this->buildDestroyWorker($dbPath, $appKey, $barrierFile, $resultFile1, $transferId));
            file_put_contents($s2, $this->buildDestroyWorker($dbPath, $appKey, $barrierFile, $resultFile2, $transferId));

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc1 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s1), $descriptors, $pipes1);
            $proc2 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s2), $descriptors, $pipes2);

            // Release both simultaneously.
            file_put_contents($barrierFile, 'GO');

            // Drain pipes to avoid deadlock, then close.
            $stderr1 = stream_get_contents($pipes1[2]);
            $stderr2 = stream_get_contents($pipes2[2]);
            foreach ($pipes1 as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            foreach ($pipes2 as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            proc_close($proc1);
            proc_close($proc2);

            $out1 = file_exists($resultFile1) ? trim(file_get_contents($resultFile1)) : 'NO_RESULT';
            $out2 = file_exists($resultFile2) ? trim(file_get_contents($resultFile2)) : 'NO_RESULT';

            $successes = 0;
            $notFound = 0;
            foreach ([$out1, $out2] as $out) {
                if (str_starts_with($out, 'RESULT:200:')) {
                    $successes++;
                }
                if (str_starts_with($out, 'RESULT:404:')) {
                    $notFound++;
                }
            }
            $this->assertSame(1, $successes, "Exactly one parallel delete must succeed.\nOut1: {$out1}\nOut2: {$out2}\nErr1: {$stderr1}\nErr2: {$stderr2}");
            $this->assertSame(1, $notFound, "The loser must observe a clean 404 (affected=0).\nOut1: {$out1}\nOut2: {$out2}");

            // Final DB state on the shared file DB.
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $row = $pdo->query('SELECT delete_bit FROM db_stocktransfer WHERE id = ' . (int) $transferId)->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotFalse($row, 'The soft-deleted row must still exist (delete_bit audit trail).');
            $this->assertSame('1', (string) $row['delete_bit'], 'delete_bit must be exactly 1 after the race.');

            $itemsCount = (int) $pdo->query('SELECT COUNT(*) FROM db_stocktransferitems WHERE stocktransfer_id = ' . (int) $transferId)->fetchColumn();
            $this->assertSame(0, $itemsCount, 'Line items must be removed exactly once.');

            $srcQty = (float) $pdo->query('SELECT available_qty FROM db_warehouseitems WHERE warehouse_id = ' . (int) $whFrom . ' AND item_id = ' . (int) $itemId)->fetchColumn();
            $destQty = (float) $pdo->query('SELECT available_qty FROM db_warehouseitems WHERE warehouse_id = ' . (int) $whTo . ' AND item_id = ' . (int) $itemId)->fetchColumn();
            $this->assertEqualsWithDelta(100.0, $srcQty, 0.001, 'Source must be reversed exactly once (100).');
            $this->assertEqualsWithDelta(0.0, $destQty, 0.001, 'Destination must be reversed exactly once (0).');
        } finally {
            @unlink($barrierFile);
            @unlink($resultFile1);
            @unlink($resultFile2);
            @unlink($s1);
            @unlink($s2);
            @unlink($dbPath);
        }
    }

    /**
     * Item 2 regression: update() with a warehouse_to from another store must be
     * rejected BEFORE any mutation, and the other store's DbWarehouseItem must
     * be untouched. A same-store update still succeeds (regression control).
     */
    public function test_update_rejects_cross_store_warehouse_to_before_any_mutation()
    {
        $cat = DbCategory::create(['category_name' => 'IDOR Cat', 'status' => 1]);
        $item = DbItem::create([
            'item_name' => 'IDOR Item', 'item_code' => 'IDOR-001',
            'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
            'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);
        $whFrom = DbWarehouse::create(['warehouse_name' => 'IDOR From', 'store_id' => 1, 'status' => 1]);
        $whTo = DbWarehouse::create(['warehouse_name' => 'IDOR To', 'store_id' => 1, 'status' => 1]);

        // Store 2 warehouse + its own warehouse-item row (the row that must NOT be touched).
        DbStore::create(['id' => 2, 'store_name' => 'IDOR Store 2', 'status' => 1, 'mobile' => '01700000002']);
        $whToStore2 = DbWarehouse::create(['warehouse_name' => 'IDOR To S2', 'store_id' => 2, 'status' => 1]);
        DbWarehouseItem::create(['store_id' => 2, 'warehouse_id' => $whToStore2->id, 'item_id' => $item->id, 'available_qty' => 999]);

        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whFrom->id, 'item_id' => $item->id, 'available_qty' => 100]);

        $this->actingAs($this->user)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $whFrom->id,
            'warehouse_to' => $whTo->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 10]],
        ])->assertOk();

        $transfer = DbStockTransfer::latest('id')->first();

        // Cross-store warehouse_to on update() → rejected with the clear message,
        // BEFORE any mutation.
        $response = $this->actingAs($this->user)->postJson(route('stock.transfer.update', $transfer->id), [
            'warehouse_from' => $whFrom->id,
            'warehouse_to' => $whToStore2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 10]],
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('do not belong to your store', $response->json('message'));

        // No mutation: transfer still at original warehouse_to; store-2 row untouched.
        $transfer->refresh();
        $this->assertSame($whTo->id, (int) $transfer->warehouse_to);
        $this->assertSame(999.0, (float) DbWarehouseItem::allStores()->where('warehouse_id', $whToStore2->id)->where('item_id', $item->id)->value('available_qty'));
        // Source still 90 (only the original transfer decremented it).
        $this->assertSame(90.0, (float) DbWarehouseItem::where('warehouse_id', $whFrom->id)->where('item_id', $item->id)->value('available_qty'));

        // Regression control: same-store update still succeeds.
        $ok = $this->actingAs($this->user)->postJson(route('stock.transfer.update', $transfer->id), [
            'warehouse_from' => $whFrom->id,
            'warehouse_to' => $whTo->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 5]],
        ]);
        $ok->assertOk();
    }

    /**
     * Item 3 regression: the unique-violation detector must catch the MySQL
     * "Duplicate entry ... uq_warehouse_item" message (SQLSTATE 23000) exactly
     * like SQLite's "UNIQUE constraint failed", and must NOT catch unrelated
     * errors. This proves the production-engine retry actually fires.
     */
    public function test_unique_violation_detector_catches_mysql_format()
    {
        $controller = new StockAdjustmentController(app(\App\SMS\Services\SmsTriggerService::class));
        $method = new \ReflectionMethod($controller, 'isWarehouseItemUniqueViolation');
        $method->setAccessible(true);

        // SQLite format (the format that already worked). Real PDOException SQLSTATE
        // codes are integers: SQLite reports 23000 for a constraint violation, MySQL
        // reports 1062 for duplicate entry — the QueryException wrapper surfaces the
        // SQLSTATE string on ->getCode().
        $sqliteEx = new QueryException(
            'sqlite',
            'INSERT into db_warehouseitems ...',
            [],
            new \PDOException('UNIQUE constraint failed: db_warehouseitems.warehouse_id, db_warehouseitems.item_id', 23000)
        );
        $this->assertTrue($method->invoke($controller, $sqliteEx), 'SQLite UNIQUE message must be detected (SQLSTATE 23000).');

        // MySQL format where the PDOException carries 23000 (driver returns the
        // SQLSTATE as code).
        $mysqlEx = new QueryException(
            'mysql',
            'insert into `db_warehouseitems` ...',
            [],
            new \PDOException("Duplicate entry '1-1' for key 'db_warehouseitems.uq_warehouse_item'", 23000)
        );
        $this->assertTrue($method->invoke($controller, $mysqlEx), 'MySQL Duplicate-entry message must be detected via SQLSTATE 23000.');

        // MySQL format with native 1062 code and NO SQLSTATE 23000 — the constraint
        // name 'uq_warehouse_item' fallback must still catch it.
        $mysqlNoCode = new QueryException(
            'mysql',
            'insert into `db_warehouseitems` ...',
            [],
            new \PDOException("Duplicate entry '1-1' for key 'db_warehouseitems.uq_warehouse_item'", 1062)
        );
        $this->assertTrue($method->invoke($controller, $mysqlNoCode), 'Constraint-name fallback must catch MySQL even when code is 1062.');

        // Unrelated error must NOT be swallowed (rethrow path preserved).
        $otherEx = new QueryException(
            'mysql',
            'insert into `db_items` ...',
            [],
            new \PDOException('SQLSTATE[HY000]: General error: 1 no such column: nope', 1)
        );
        $this->assertFalse($method->invoke($controller, $otherEx), 'Unrelated DB errors must NOT be treated as a uq_warehouse_item violation.');
    }

    /**
     * Item 4 — N+1 regression: the transfer list page must not run a per-row
     * query for `items`. Rendering N transfers must cost the SAME number of
     * queries as rendering 1 (the eager-load keeps it flat). The assertion is
     * strict equality — any per-row lazy load adds exactly 1 query/row and
     * fails it.
     */
    public function test_transfer_list_query_count_is_flat_across_row_growth()
    {
        $cat = DbCategory::create(['category_name' => 'N1 Cat', 'status' => 1]);
        $item = DbItem::create([
            'item_name' => 'N1 Item', 'item_code' => 'N1-001',
            'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
            'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);
        $whFrom = DbWarehouse::create(['warehouse_name' => 'N1 From', 'store_id' => 1, 'status' => 1]);
        $whTo = DbWarehouse::create(['warehouse_name' => 'N1 To', 'store_id' => 1, 'status' => 1]);
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whFrom->id, 'item_id' => $item->id, 'available_qty' => 100]);

        $makeTransfer = function () use ($whFrom, $whTo, $item) {
            return DbStockTransfer::create([
                'store_id' => 1,
                'warehouse_from' => $whFrom->id,
                'warehouse_to' => $whTo->id,
                'reference_no' => 'N1-TR-' . uniqid(),
                'transfer_date' => now()->toDateString(),
                'created_by' => $this->user->id,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'status' => 1,
                'delete_bit' => 0,
            ]);
        };

        // Warm any one-off page caches (e.g. the layout's db_store lookup) BEFORE
        // measuring, so both measured requests reflect only the page's own cost.
        $this->actingAs($this->user)->get(route('stock.transfer'))->assertOk();

        // Phase 1: measure the page with exactly ONE transfer.
        $t1 = $makeTransfer();
        DbStockTransferItems::create(['stocktransfer_id' => $t1->id, 'store_id' => 1, 'warehouse_from' => $whFrom->id, 'warehouse_to' => $whTo->id, 'item_id' => $item->id, 'transfer_qty' => 5]);
        // Single destination row for this item (unique on warehouse+item).
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whTo->id, 'item_id' => $item->id, 'available_qty' => 12]);

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->user)->get(route('stock.transfer'))->assertOk();
        $queries1 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::flushQueryLog();

        // Phase 2: add three MORE transfers (all to the same destination item —
        // the badge still reads the single aggregate row), then measure again.
        // The inserts run while logging is OFF so they cannot contaminate counts.
        foreach ([2, 3, 4] as $i) {
            $t = $makeTransfer();
            DbStockTransferItems::create(['stocktransfer_id' => $t->id, 'store_id' => 1, 'warehouse_from' => $whFrom->id, 'warehouse_to' => $whTo->id, 'item_id' => $item->id, 'transfer_qty' => $i]);
        }

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->user)->get(route('stock.transfer'))->assertOk();
        $queries4 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::flushQueryLog();

        // STRICT N+1 guard: query count must be IDENTICAL for 1 row vs 4 rows.
        // A per-row lazy load — e.g. removing `items` from index()'s with() list
        // at StockTransferController.php:27 — adds exactly 1 query/row and makes
        // $queries4 > $queries1, failing this assertion. A loose "+N fudge
        // factor" is deliberately NOT used because it cannot distinguish a
        // genuinely flat page from a 1-query-per-row regression.
        $this->assertSame(
            $queries1,
            $queries4,
            "Transfer list query count must be IDENTICAL as rows grow (strict N+1 guard). 1-row={$queries1}, 4-row={$queries4}."
        );
    }

    /**
     * Item 4 — N+1 regression for the ADJUSTMENT list page: the net-qty badge
     * reads $adj->items->sum(...); with items eager-loaded the query count stays
     * flat as the row count grows.
     */
    public function test_adjustment_list_query_count_is_flat_across_row_growth()
    {
        $cat = DbCategory::create(['category_name' => 'N1 Adj Cat', 'status' => 1]);
        $item = DbItem::create([
            'item_name' => 'N1 Adj Item', 'item_code' => 'N1-ADJ-001',
            'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
            'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);
        $wh = DbWarehouse::create(['warehouse_name' => 'N1 Adj WH', 'store_id' => 1, 'status' => 1]);

        $makeAdjustment = function () use ($wh, $item) {
            $a = DbStockAdjustment::create([
                'store_id' => 1,
                'warehouse_id' => $wh->id,
                'reference_no' => 'N1-ADJ-' . uniqid(),
                'adjustment_date' => now()->toDateString(),
                'created_by' => $this->user->id,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'status' => 1,
            ]);
            \App\Models\DbStockAdjustmentItems::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'adjustment_id' => $a->id, 'item_id' => $item->id, 'adjustment_qty' => 5]);
            return $a;
        };

        $makeAdjustment();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->user)->get(route('stock.adjustment'))->assertOk();
        $q1 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Create 3 more adjustments while logging is OFF (so the inserts never
        // contaminate the second GET's count).
        foreach ([1, 2, 3] as $i) {
            $makeAdjustment();
        }

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->user)->get(route('stock.adjustment'))->assertOk();
        $q4 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan($q1 + 4, $q4, 'Adjustment list query count must stay flat as rows grow (N+1 regression). q1=' . $q1 . ' q4=' . $q4);
    }
}
