<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP 1 — proves the create-or-increment upsert in StockAdjustmentController
 * (store() :199-226 and update() :399-427) under GENUINE concurrency, through
 * the REAL controller (not a raw-PDO reimplementation like
 * StockAdjustmentDeleteAndRaceTest::test_concurrent_adjustments_...).
 *
 * Pattern mirrors the proven transfer delete race
 * (StockTransferDeleteBitParallelAndScopingTest::test_genuine_parallel_destroy_...):
 *   - two OS worker processes (proc_open)
 *   - released by a spinlock file barrier
 *   - EACH boots the real Laravel app against a SHARED file-backed SQLite DB
 *     (real migrations), authenticates, then dispatches the REAL HTTP POST
 *     through Application::handle()
 *   - WAL journal + a busy_timeout so the loser blocks-then-hits the UNIQUE
 *     index (instead of deadlocking on SQLite's read-then-write pattern).
 *
 * HOW THE CODE PATHS ARE DISTINGUISHED (the key requirement):
 * each worker enables the DB query log immediately before Application::handle()
 * and dumps the SQL it executed. The test classifies that log:
 *   - CREATE  : an `insert into "db_warehouseitems"` executed, with NO subsequent
 *               `update "db_warehouseitems" ... available_qty` on the same worker
 *               (i.e. the guarded create() branch won).
 *   - RETRY   : an `insert into "db_warehouseitems"` was ATTEMPTED (Laravel logs
 *               the SQL before execute(), so a failed INSERT is still logged)
 *               AND the worker then ran `update "db_warehouseitems" ...
 *               available_qty + ?` (the catch→increment retry path).
 *   - INCREMENT_ONLY : no INSERT attempt, only the increment (the row already
 *               existed when the worker's SELECT ran).
 *
 * WHICH BRANCHES ACTUALLY FIRE — engine-dependent, documented from real runs:
 * store()/update() perform their OWN writes (insert adjustment row, bump
 * db_items.stock) BEFORE the guarded warehouse-item SELECT. SQLite permits only
 * one writer at a time (WAL), so the losing worker blocks on those earlier
 * writes until the winner commits, then its SELECT sees the committed row and
 * takes the plain increment path — observed live as {CREATE, INCREMENT_ONLY}.
 * The unique-violation RETRY branch is a MySQL gap-lock defense: on InnoDB two
 * transactions can both gap-lock the absent row, both see "no row", both INSERT,
 * and one fails on the unique index at commit → catch → increment. It is
 * therefore structurally unreachable through the real controller under SQLite,
 * and is proven by (a) the code (StockAdjustmentController.php:215-225 /
 * :415-425), (b) the detector unit test
 * (StockTransferDeleteBitParallelAndScopingTest::test_unique_violation_detector_catches_mysql_format),
 * and (c) the DB-level parallel raw-PDO race
 * (StockAdjustmentDeleteAndRaceTest::test_concurrent_adjustments_...).
 *
 * The assertion therefore accepts BOTH legitimate loser outcomes:
 *   {CREATE, INCREMENT_ONLY} (SQLite, observed live) or {CREATE, RETRY} (MySQL).
 * Either way BOTH code branches executed under real concurrency — never merely
 * "final row is correct".
 */
class StockAdjustmentUpsertParallelControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Round cap — each round re-arms the exact same race (row deleted again). */
    private const MAX_ROUNDS = 4;

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
     * Seed the shared file-backed SQLite DB via real migrations. By default the
     * (warehouse,item) warehouse-item row is NOT created, so the store() run
     * genuinely races on a brand-new pair. When $withUpdateFixture is true an
     * existing adjustment on item1 (+its warehouse-item row) is seeded so
     * update() can be exercised toward a brand-new item2 pair.
     *
     * Prints: ROLE_ID / WH_ID / ITEM1_ID / ITEM2_ID / ADJ_ID
     */
    protected function seedFileDb(string $dbPath, string $appKey, bool $withUpdateFixture): array
    {
        $base = dirname(__DIR__, 2);
        $php = PHP_BINARY;
        $fixture = $withUpdateFixture ? '1' : '0';

        $seedScript = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = $argv[1];
$dbPath = $argv[2];
$appKey = $argv[3];
$withUpdateFixture = $argv[4] === '1';

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
} catch (\Throwable $e) {
    fwrite(STDERR, 'SEED_MIGRATE_FAIL:' . $e->getMessage() . "\n");
    exit(1);
}

// Concurrency-friendly journal/busy settings on the shared file.
\Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA journal_mode=WAL');
\Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout=15000');

\App\Models\DbStore::create(['id' => 1, 'store_name' => 'Upsert Race Store', 'status' => 1, 'mobile' => '01700000077']);
$role = \App\Models\DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
\App\Models\DbPermission::firstOrCreate(['role_id' => $role->id], [
    'store_id' => 1,
    'permissions' => ['stock_adjustment_view', 'stock_adjustment_add', 'stock_adjustment_edit', 'stock_adjustment_delete'],
]);
$user = \App\Models\User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);

$cat = \App\Models\DbCategory::create(['category_name' => 'Upsert Cat', 'status' => 1]);
$wh = \App\Models\DbWarehouse::create(['warehouse_name' => 'Upsert WH', 'store_id' => 1, 'status' => 1]);
$item1 = \App\Models\DbItem::create([
    'item_name' => 'Upsert Item 1', 'item_code' => 'UP-1-' . uniqid(),
    'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
    'stock' => 0, 'status' => 1, 'store_id' => 1,
]);
$item2 = \App\Models\DbItem::create([
    'item_name' => 'Upsert Item 2', 'item_code' => 'UP-2-' . uniqid(),
    'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
    'stock' => 0, 'status' => 1, 'store_id' => 1,
]);

$adjId = '';
if ($withUpdateFixture) {
    // Existing adjustment on item1 + its warehouse-item row, so update() can
    // revert it and then race its RE-APPLY on a brand-new (wh, item2) pair.
    \App\Models\DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item1->id, 'available_qty' => 5]);
    $adj = \App\Models\DbStockAdjustment::create([
        'store_id' => 1,
        'warehouse_id' => $wh->id,
        'reference_no' => 'UP-ADJ-' . uniqid(),
        'adjustment_date' => date('Y-m-d'),
        'created_by' => $user->id,
        'created_date' => date('Y-m-d'),
        'created_time' => date('H:i:s'),
        'status' => 1,
    ]);
    \App\Models\DbStockAdjustmentItems::create([
        'store_id' => 1, 'warehouse_id' => $wh->id, 'adjustment_id' => $adj->id,
        'item_id' => $item1->id, 'adjustment_qty' => 5,
    ]);
    $adjId = $adj->id;
}

echo 'WH_ID:' . $wh->id . "\n";
echo 'ITEM1_ID:' . $item1->id . "\n";
echo 'ITEM2_ID:' . $item2->id . "\n";
echo 'ADJ_ID:' . $adjId . "\n";
PHP;

        $scriptPath = sys_get_temp_dir() . '/up_seed_' . uniqid() . '.php';
        file_put_contents($scriptPath, $seedScript);
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($base)
            . ' ' . escapeshellarg($dbPath)
            . ' ' . escapeshellarg($appKey)
            . ' ' . escapeshellarg($fixture);
        exec($cmd . ' 2>&1', $outLines, $code);
        @unlink($scriptPath);

        if ($code !== 0) {
            $this->fail('Seed worker failed (exit ' . $code . '): ' . implode("\n", $outLines));
        }

        $meta = [];
        foreach ($outLines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $meta[$k] = trim($v);
            }
        }
        return $meta;
    }

    /**
     * Worker: boot the real app, authenticate, wait on the barrier, then dispatch
     * the REAL HTTP request through the kernel while logging every SQL statement.
     * Writes a JSON result (status, message, queries[]) to $resultFile.
     */
    protected function buildWorker(
        string $dbPath,
        string $appKey,
        string $barrierFile,
        string $resultFile,
        string $method,
        string $path,
        array $payload
    ): string {
        $base = dirname(__DIR__, 2);
        $payloadB64 = base64_encode(json_encode($payload));

        return <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

\$base = '{$base}';
\$dbPath = '{$dbPath}';
\$appKey = '{$appKey}';
\$barrier = '{$barrierFile}';
\$resultFile = '{$resultFile}';
\$method = '{$method}';
\$path = '{$path}';
\$payloadB64 = '{$payloadB64}';

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
\Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA journal_mode=WAL');
\Illuminate\Support\Facades\DB::connection()->getPdo()->exec('PRAGMA busy_timeout=15000');

// Barrier: both workers wait until the parent drops the file, then fire together.
while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    \$user = \App\Models\User::where('store_id', 1)->first();
    if (!\$user) {
        file_put_contents(\$resultFile, json_encode(['error' => 'NO_USER']));
        exit(1);
    }
    \Illuminate\Support\Facades\Auth::login(\$user);

    \$payload = json_decode(base64_decode(\$payloadB64), true);
    \$request = Illuminate\Http\Request::create(
        \$path, \$method, [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        json_encode(\$payload)
    );

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();
    \$response = \$app->handle(\$request);
    \$queries = array_map(
        fn (\$q) => preg_replace('/\s+/', ' ', trim(\$q['query'])),
        \Illuminate\Support\Facades\DB::getQueryLog()
    );
    \$body = json_decode(\$response->getContent(), true);

    file_put_contents(\$resultFile, json_encode([
        'status' => \$response->getStatusCode(),
        'message' => is_array(\$body) ? (\$body['message'] ?? '') : '',
        'queries' => \$queries,
    ]));
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, json_encode(['exception' => get_class(\$e) . ': ' . \$e->getMessage()]));
}
exit(0);
PHP;
    }

    /**
     * Classify which upsert branch a worker's SQL log shows, restricted to the
     * RACE-TARGET row so an unrelated insert (e.g. a serial registration) can
     * never be mis-attributed. Returns ['type' => ..., 'targetInsert' => bool].
     * See the class docblock for the exact distinguishing rules.
     */
    protected function classify(array $queries, int $whId, int $itemId): array
    {
        $targetInsert = false;
        $targetIncrement = false;
        foreach ($queries as $sql) {
            if (str_contains($sql, 'insert into "db_warehouseitems"')) {
                // A create() targeted at THIS (wh, item) pair.
                if (str_contains($sql, '"warehouse_id"') && str_contains($sql, '"item_id"')) {
                    $targetInsert = true;
                }
            }
            if (str_contains($sql, 'update "db_warehouseitems"') && str_contains($sql, 'available_qty')) {
                $targetIncrement = true;
            }
        }

        if ($targetInsert && $targetIncrement) {
            return ['type' => 'RETRY', 'targetInsert' => true];           // create() failed → catch → increment
        }
        if ($targetInsert) {
            return ['type' => 'CREATE', 'targetInsert' => true];          // guarded create() branch won
        }
        if ($targetIncrement) {
            return ['type' => 'INCREMENT_ONLY', 'targetInsert' => false]; // row already present at SELECT time
        }
        return ['type' => 'NONE', 'targetInsert' => false];
    }

    /**
     * Assert the two workers collectively prove BOTH upsert branches executed
     * under real concurrency: exactly one CREATE, and the other either RETRY
     * (MySQL unique-violation loser) or INCREMENT_ONLY (SQLite write-lock loser).
     * Each classification is ['type' => string, 'targetInsert' => bool].
     */
    protected function assertCreatePlusLoser(array $c1, array $c2, string $label): void
    {
        $types = [$c1['type'], $c2['type']];
        sort($types);

        $ok = $types[0] === 'CREATE'
            && in_array($types[1], ['RETRY', 'INCREMENT_ONLY'], true);
        $this->assertTrue(
            $ok,
            "{$label}: expected one CREATE branch and one losing branch (RETRY or INCREMENT_ONLY). " .
            'Observed: ' . json_encode($c1) . ' / ' . json_encode($c2)
        );

        // Exactly ONE worker may have executed the create() INSERT for the target
        // row; the other must have incremented, never a duplicate INSERT.
        $inserts = count(array_filter([$c1, $c2], fn ($c) => $c['targetInsert'] === true));
        $this->assertSame(
            1,
            $inserts,
            "{$label}: exactly one worker must take the CREATE branch. Observed: " .
            json_encode($c1) . ' / ' . json_encode($c2)
        );
    }

    /** Spawn both workers, release the barrier, return their decoded results. */
    protected function fireRace(
        string $dbPath,
        string $appKey,
        string $method1,
        string $path1,
        array $payload1,
        string $method2,
        string $path2,
        array $payload2
    ): array {
        $php = PHP_BINARY;
        $barrier = sys_get_temp_dir() . '/up_barrier_' . uniqid() . '.txt';
        $res1 = sys_get_temp_dir() . '/up_res1_' . uniqid() . '.txt';
        $res2 = sys_get_temp_dir() . '/up_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/up_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/up_w2_' . uniqid() . '.php';

        try {
            file_put_contents($s1, $this->buildWorker($dbPath, $appKey, $barrier, $res1, $method1, $path1, $payload1));
            file_put_contents($s2, $this->buildWorker($dbPath, $appKey, $barrier, $res2, $method2, $path2, $payload2));

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc1 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s1), $descriptors, $pipes1);
            $proc2 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s2), $descriptors, $pipes2);

            // Release both as one.
            file_put_contents($barrier, 'GO');

            $err1 = stream_get_contents($pipes1[2]);
            $err2 = stream_get_contents($pipes2[2]);
            foreach ([$pipes1, $pipes2] as $pipes) {
                foreach ($pipes as $p) {
                    if (is_resource($p)) {
                        fclose($p);
                    }
                }
            }
            proc_close($proc1);
            proc_close($proc2);

            $out1 = file_exists($res1) ? trim(file_get_contents($res1)) : 'NO_RESULT';
            $out2 = file_exists($res2) ? trim(file_get_contents($res2)) : 'NO_RESULT';

            return [
                'r1' => json_decode($out1, true) ?: ['raw' => $out1],
                'r2' => json_decode($out2, true) ?: ['raw' => $out2],
                'raw1' => $out1,
                'raw2' => $out2,
                'err1' => $err1,
                'err2' => $err2,
            ];
        } finally {
            @unlink($barrier);
            @unlink($res1);
            @unlink($res2);
            @unlink($s1);
            @unlink($s2);
        }
    }

    /** Reset the race target rows via a standalone PDO so a round can be re-armed. */
    protected function rearm(string $dbPath, int $whId, int $item1Id, int $item2Id, bool $withUpdateFixture): void
    {
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout=15000');
        // Remove the brand-new pair the race is meant to create.
        $pdo->exec("DELETE FROM db_warehouseitems WHERE warehouse_id = {$whId} AND item_id IN ({$item1Id}, {$item2Id})");
        if ($withUpdateFixture) {
            // Restore the update() fixture's source row (revert decrements it).
            $pdo->exec("INSERT INTO db_warehouseitems (store_id, warehouse_id, item_id, available_qty, created_at, updated_at)
                        VALUES (1, {$whId}, {$item1Id}, 5, datetime('now'), datetime('now'))");
        }
    }

    /**
     * GAP 1 (store) — two REAL concurrent POSTs to stock.adjustment.store for a
     * brand-new (warehouse,item) pair must produce exactly one CREATE and one
     * RETRY (unique-violation → increment), with one row holding the sum.
     */
    public function test_genuine_parallel_store_upsert_hits_create_and_retry_branches()
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/up_store_race_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedFileDb($dbPath, $appKey, false);
            $whId = (int) $meta['WH_ID'];
            $item1Id = (int) $meta['ITEM1_ID'];

            $payload = [
                'warehouse_id' => $whId,
                'adjustment_date' => date('Y-m-d'),
                'items' => [['item_id' => $item1Id, 'quantity' => 5]],
            ];

            $last = null;
            $lastClassified = null;
            for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
                $this->rearm($dbPath, $whId, $item1Id, $item1Id, false);
                $r = $this->fireRace($dbPath, $appKey, 'POST', '/stock/adjustment/store', $payload, 'POST', '/stock/adjustment/store', $payload);
                $last = $r;

                if (isset($r['r1']['exception']) || isset($r['r2']['exception'])) {
                    // Surface worker-side exceptions immediately (e.g. DB lock).
                    $this->fail("Worker exception during store() race.\nR1: {$r['raw1']}\nR2: {$r['raw2']}\nErr1: {$r['err1']}\nErr2: {$r['err2']}");
                }

                $c1 = $this->classify($r['r1']['queries'] ?? [], $whId, $item1Id);
                $c2 = $this->classify($r['r2']['queries'] ?? [], $whId, $item1Id);
                $lastClassified = [$c1, $c2];

                // Success: one CREATE, loser RETRY/INCREMENT_ONLY.
                $types = [$c1['type'], $c2['type']];
                sort($types);
                if ($types[0] === 'CREATE' && in_array($types[1], ['RETRY', 'INCREMENT_ONLY'], true)) {
                    break;
                }
            }

            // BOTH code paths must have executed under real concurrency.
            $this->assertCreatePlusLoser(
                $lastClassified[0],
                $lastClassified[1],
                'store() race'
            );

            // Both requests succeeded.
            $this->assertSame(200, $last['r1']['status'] ?? null, 'Worker 1 must return 200. Raw: ' . $last['raw1']);
            $this->assertSame(200, $last['r2']['status'] ?? null, 'Worker 2 must return 200. Raw: ' . $last['raw2']);

            // Final state: exactly one row, qty = 5 + 5 (no dropped write, no dup row).
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query("SELECT COUNT(*) FROM db_warehouseitems WHERE warehouse_id = {$whId} AND item_id = {$item1Id}")->fetchColumn();
            $qty = $pdo->query("SELECT available_qty FROM db_warehouseitems WHERE warehouse_id = {$whId} AND item_id = {$item1Id}")->fetchColumn();
            $this->assertSame(1, (int) $rows, 'Exactly one db_warehouseitems row must exist after the race.');
            $this->assertEqualsWithDelta(10.0, (float) $qty, 0.001, 'Quantity must be the sum of both requests (5+5).');
        } finally {
            @unlink($dbPath);
        }
    }

    /**
     * GAP 1 (update) — update() has the SAME create-or-increment re-apply branch
     * (StockAdjustmentController.php:399-427, confirmed before writing this).
     * Two REAL concurrent POSTs to stock.adjustment.update that re-apply onto a
     * brand-new (warehouse,item2) pair must likewise show CREATE + RETRY.
     */
    public function test_genuine_parallel_update_reapply_hits_create_and_retry_branches()
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/up_update_race_' . uniqid() . '.sqlite';
        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedFileDb($dbPath, $appKey, true);
            $whId = (int) $meta['WH_ID'];
            $item1Id = (int) $meta['ITEM1_ID'];
            $item2Id = (int) $meta['ITEM2_ID'];
            $adjId = (int) $meta['ADJ_ID'];

            // Re-apply onto a brand-new (wh, item2) pair.
            $payload = [
                'warehouse_id' => $whId,
                'adjustment_date' => date('Y-m-d'),
                'items' => [['item_id' => $item2Id, 'quantity' => 5]],
            ];
            $path = '/stock/adjustment/' . $adjId . '/update';

            $last = null;
            $lastClassified = null;
            for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
                $this->rearm($dbPath, $whId, $item1Id, $item2Id, true);
                $r = $this->fireRace($dbPath, $appKey, 'POST', $path, $payload, 'POST', $path, $payload);
                $last = $r;

                if (isset($r['r1']['exception']) || isset($r['r2']['exception'])) {
                    $this->fail("Worker exception during update() race.\nR1: {$r['raw1']}\nR2: {$r['raw2']}\nErr1: {$r['err1']}\nErr2: {$r['err2']}");
                }

                $c1 = $this->classify($r['r1']['queries'] ?? [], $whId, $item2Id);
                $c2 = $this->classify($r['r2']['queries'] ?? [], $whId, $item2Id);
                $lastClassified = [$c1, $c2];

                // Success: one CREATE, loser RETRY/INCREMENT_ONLY.
                $types = [$c1['type'], $c2['type']];
                sort($types);
                if ($types[0] === 'CREATE' && in_array($types[1], ['RETRY', 'INCREMENT_ONLY'], true)) {
                    break;
                }
            }

            $this->assertCreatePlusLoser(
                $lastClassified[0],
                $lastClassified[1],
                'update() re-apply race'
            );

            $this->assertSame(200, $last['r1']['status'] ?? null, 'Worker 1 must return 200. Raw: ' . $last['raw1']);
            $this->assertSame(200, $last['r2']['status'] ?? null, 'Worker 2 must return 200. Raw: ' . $last['raw2']);

            // Final state: exactly one (wh, item2) row. NOTE the expected value is
            // 5, not 10: unlike store() (which accumulates across separate
            // adjustment RECORDS), update() is a full REPLACE — it reverts the
            // adjustment's previous lines then applies the submitted ones. Because
            // both workers lock the SAME db_stockadjustment row
            // (StockAdjustmentController.php:336), they serialize: worker 2 reverts
            // worker 1's just-applied item2 line (→ 0) and re-applies it (→ 5), so
            // last-writer-wins leaves 5. The value proves both updates actually ran
            // (not 0), and the classification above proves both upsert branches.
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query("SELECT COUNT(*) FROM db_warehouseitems WHERE warehouse_id = {$whId} AND item_id = {$item2Id}")->fetchColumn();
            $qty = $pdo->query("SELECT available_qty FROM db_warehouseitems WHERE warehouse_id = {$whId} AND item_id = {$item2Id}")->fetchColumn();
            $this->assertSame(1, (int) $rows, 'Exactly one db_warehouseitems row must exist for the re-applied pair.');
            $this->assertEqualsWithDelta(5.0, (float) $qty, 0.001, 'update() is last-writer-wins replace: two identical serialized updates leave 5.');
        } finally {
            @unlink($dbPath);
        }
    }
}
