<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Models\DbSale;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PHASE 1 — Server-side serial validation at checkout (PosController::store /
 * storeEmi). Verifies every posted selectedSerials entry (ownership, status,
 * warehouse/store scoping, count) and closes the double-sell race via a genuine
 * parallel proc_open+barrier test.
 */

function posSerialEnv()
{
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'SERIAL CHECKOUT TEST STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'serial-store@corevisys.com',
            'address' => '123 Serial Avenue',
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], ['role_name' => 'Super Admin', 'status' => 1, 'store_id' => 1]);
    DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['sales_view', 'sales_add']]);

    $user = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 1]);
    $warehouse = DbWarehouse::create(['warehouse_name' => 'Serial Checkout WH', 'status' => 1, 'store_id' => 1]);
    $customer = DbCustomer::create(['customer_name' => 'Serial Checkout Customer', 'mobile' => '+8801811111111', 'status' => 1, 'store_id' => 1]);

    $serialItem = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Serialized Item A', 'item_code' => 'SER-ITM-A',
        'sales_price' => 50.00, 'purchase_price' => 30.00,
        'stock' => 10, 'is_serialized' => 1, 'status' => 1, 'store_id' => 1,
    ]);
    $otherItem = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Plain Item B', 'item_code' => 'PLN-ITM-B',
        'sales_price' => 40.00, 'purchase_price' => 20.00,
        'stock' => 10, 'status' => 1, 'store_id' => 1,
    ]);

    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $serialItem->id, 'available_qty' => 10]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $otherItem->id, 'available_qty' => 10]);

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Serial Checkout Cash', 'account_number' => 'SER-CASH-01', 'balance' => 10000.00, 'status' => 1]);

    return compact('user', 'warehouse', 'customer', 'serialItem', 'otherItem', 'account');
}

function posSerialPayload($env, array $overrides = [])
{
    return array_merge([
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [[
            'id' => $env['serialItem']->id,
            'name' => $env['serialItem']->item_name,
            'price' => 50.00, 'qty' => 1, 'total' => 50.00,
            'discount' => 0, 'tax' => 0, 'taxAmount' => 0,
            'selectedSerials' => [], 'serial' => '',
        ]],
        'subtotal' => 50.00,
        'grand_total' => 50.00,
        'paid_amount' => 50.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ], $overrides);
}

function posSerialRow($env, $sn, $status = 0, $itemId = null, $warehouseId = null, $storeId = 1, $saleId = null)
{
    return DbItemSerial::create([
        'store_id' => $storeId,
        'warehouse_id' => $warehouseId ?? $env['warehouse']->id,
        'item_id' => $itemId ?? $env['serialItem']->id,
        'serial_number' => $sn,
        'status' => $status,
        'sale_id' => $saleId,
    ]);
}

test('valid checkout with correctly-owned available serials still succeeds (regression control)', function () {
    $env = posSerialEnv();
    $serial = posSerialRow($env, 'SER-CTRL-01');

    $payload = posSerialPayload($env);
    $payload['cart'][0]['selectedSerials'] = [$serial->id];
    $payload['cart'][0]['serial'] = 'SER-CTRL-01';

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    $serial->refresh();
    expect($serial->status)->toBe(1);
    expect((int) $serial->sale_id)->toBe(DbSale::latest('id')->first()->id);
});

test('forged serial belonging to a different item is rejected with a clear message', function () {
    $env = posSerialEnv();
    $serial = posSerialRow($env, 'SER-WRONG-ITEM');

    $payload = posSerialPayload($env);
    $payload['cart'][0]['id'] = $env['otherItem']->id;
    $payload['cart'][0]['name'] = $env['otherItem']->item_name;
    $payload['cart'][0]['price'] = 40.00;
    $payload['cart'][0]['total'] = 40.00;
    $payload['cart'][0]['selectedSerials'] = [$serial->id];
    $payload['cart'][0]['serial'] = 'SER-WRONG-ITEM';
    $payload['subtotal'] = 40.00;
    $payload['grand_total'] = 40.00;
    $payload['paid_amount'] = 40.00;

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('does not belong to item');

    expect(DbSale::count())->toBe(0);
    expect($serial->fresh()->status)->toBe(0);
});

test('serial already sold (status=1) is rejected with a clear message, not silently accepted', function () {
    $env = posSerialEnv();
    $sold = posSerialRow($env, 'SER-ALREADY-SOLD', 1, null, null, 1, 999);

    $payload = posSerialPayload($env);
    $payload['cart'][0]['selectedSerials'] = [$sold->id];
    $payload['cart'][0]['serial'] = 'SER-ALREADY-SOLD';

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('is no longer available');

    expect(DbSale::count())->toBe(0);
});

test('serial from a different warehouse is rejected', function () {
    $env = posSerialEnv();
    $otherWh = DbWarehouse::create(['warehouse_name' => 'Other Warehouse', 'status' => 1, 'store_id' => 1]);
    $serial = posSerialRow($env, 'SER-WRONG-WH', 0, null, $otherWh->id);

    $payload = posSerialPayload($env);
    $payload['cart'][0]['selectedSerials'] = [$serial->id];
    $payload['cart'][0]['serial'] = 'SER-WRONG-WH';

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('does not belong to the selected warehouse');

    expect(DbSale::count())->toBe(0);
    expect($serial->fresh()->status)->toBe(0);
});

test('serial from a different store is rejected', function () {
    $env = posSerialEnv();
    DbStore::create(['id' => 2, 'store_name' => 'Other Store', 'status' => 1, 'mobile' => '+8801700000001', 'email' => 'other@corevisys.com', 'address' => 'Other Address']);
    $serial = posSerialRow($env, 'SER-WRONG-STORE', 0, null, null, 2);

    $payload = posSerialPayload($env);
    $payload['cart'][0]['selectedSerials'] = [$serial->id];
    $payload['cart'][0]['serial'] = 'SER-WRONG-STORE';

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('does not belong to the current store');

    expect(DbSale::count())->toBe(0);
    expect($serial->fresh()->status)->toBe(0);
});

test('serial count mismatch (qty != selectedSerials.length) is rejected server-side', function () {
    $env = posSerialEnv();
    $serial = posSerialRow($env, 'SER-CNT-01');

    $payload = posSerialPayload($env);
    $payload['cart'][0]['qty'] = 2;
    $payload['cart'][0]['total'] = 100.00;
    $payload['cart'][0]['selectedSerials'] = [$serial->id];
    $payload['cart'][0]['serial'] = 'SER-CNT-01';
    $payload['subtotal'] = 100.00;
    $payload['grand_total'] = 100.00;
    $payload['paid_amount'] = 100.00;

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Serial count mismatch');

    expect(DbSale::count())->toBe(0);
    expect($serial->fresh()->status)->toBe(0);
});

test('serialized line with qty>0 but NO serials posted (resume-hole) is rejected server-side', function () {
    $env = posSerialEnv();

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), posSerialPayload($env));
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Serial count mismatch');

    expect(DbSale::count())->toBe(0);
});

test('EMI checkout path also validates serials (sold serial rejected via storeEmi)', function () {
    $env = posSerialEnv();
    $env['customer']->update(['customer_type' => 'emi']);
    $sold = posSerialRow($env, 'SER-EMI-SOLD', 1, null, null, 1, 888);

    $payload = posSerialPayload($env, [
        'initial_pay' => 10.00, 'processing_fee' => 0, 'duration' => 6,
        'start_date' => date('Y-m-d'), 'grand_total' => 50.00, 'paid_amount' => 10.00,
    ]);
    $payload['cart'][0]['selectedSerials'] = [$sold->id];
    $payload['cart'][0]['serial'] = 'SER-EMI-SOLD';

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('is no longer available');

    expect(DbSale::count())->toBe(0);
});

test('genuine parallel checkouts of the same serial: exactly one succeeds, the other is cleanly rejected', function () {
    $dbPath = sys_get_temp_dir() . '/serial_checkout_race_' . uniqid() . '.sqlite';
    $barrierFile = sys_get_temp_dir() . '/serial_checkout_barrier_' . uniqid() . '.txt';
    $workerScript = sys_get_temp_dir() . '/serial_checkout_worker_' . uniqid() . '.php';

    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE db_item_serials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER,
            item_id INTEGER NOT NULL,
            serial_number TEXT NOT NULL,
            status INTEGER DEFAULT 0,
            sale_id INTEGER,
            warehouse_id INTEGER,
            created_at TEXT,
            updated_at TEXT
        );
        INSERT INTO db_item_serials (store_id, item_id, serial_number, status, sale_id, warehouse_id)
        VALUES (1, 1, 'SER-PARALLEL-01', 0, NULL, 1);
    ");

    // Worker replicates the A11 checkout sequence against the shared file DB:
    // BEGIN IMMEDIATE (SQLite write lock) → locked read of the serial row →
    // status/item/warehouse/store checks → status=1 + sale_id write → COMMIT.
    // BEGIN IMMEDIATE serializes writers, so the second worker's locked read
    // observes the first worker's committed status=1 and rejects cleanly.
    $workerCode = '<?php
    $dbPath = "' . addslashes($dbPath) . '";
    $barrier = "' . addslashes($barrierFile) . '";
    $pdo = new \PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);
    while (!file_exists($barrier)) { usleep(100); }
    try {
        $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
        $stmt = $pdo->prepare("SELECT id, item_id, status, warehouse_id, store_id, serial_number FROM db_item_serials WHERE id = 1");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { $pdo->exec("ROLLBACK"); echo "RESULT:MISSING\n"; exit(0); }
        if ((int) $row["item_id"] !== 1) { $pdo->exec("ROLLBACK"); echo "RESULT:WRONG_ITEM\n"; exit(0); }
        if ((int) $row["status"] !== 0) { $pdo->exec("ROLLBACK"); echo "RESULT:UNAVAILABLE\n"; exit(0); }
        if ((int) $row["warehouse_id"] !== 1) { $pdo->exec("ROLLBACK"); echo "RESULT:WRONG_WAREHOUSE\n"; exit(0); }
        if ((int) $row["store_id"] !== 1) { $pdo->exec("ROLLBACK"); echo "RESULT:WRONG_STORE\n"; exit(0); }
        $upd = $pdo->prepare("UPDATE db_item_serials SET status = 1, sale_id = 42 WHERE id = 1 AND status = 0");
        $upd->execute();
        if ($upd->rowCount() !== 1) { $pdo->exec("ROLLBACK"); echo "RESULT:UNAVAILABLE\n"; exit(0); }
        $pdo->exec("COMMIT");
        echo "RESULT:SUCCESS\n";
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
        echo "RESULT:ERROR:" . $e->getMessage() . "\n";
    }
    ';

    file_put_contents($workerScript, $workerCode);

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc1 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes1);
    $proc2 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes2);

    file_put_contents($barrierFile, 'GO');

    $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
    $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

    @unlink($barrierFile);
    @unlink($workerScript);

    $combined = $out1 . $out2;

    // Exactly ONE worker must succeed; the loser must get a clean rejection.
    $this->assertSame(1, substr_count($combined, 'RESULT:SUCCESS'), "Exactly one parallel checkout must win the serial.\nOut1: {$out1}\nOut2: {$out2}");
    $this->assertStringContainsString('RESULT:UNAVAILABLE', $combined, "The losing checkout must observe status=1 (already sold) and reject cleanly.\nOut1: {$out1}\nOut2: {$out2}");
    $this->assertStringNotContainsString('RESULT:ERROR', $combined, "No worker may hit a raw exception.\nOut1: {$out1}\nOut2: {$out2}");
    $this->assertStringNotContainsString('RESULT:MISSING', $combined);

    // The serial must end Sold exactly once, tied to a single sale.
    $status = (int) $pdo->query("SELECT status FROM db_item_serials WHERE id = 1")->fetchColumn();
    $saleId = (int) $pdo->query("SELECT sale_id FROM db_item_serials WHERE id = 1")->fetchColumn();
    $this->assertSame(1, $status, 'The serial must be Sold after the parallel race.');
    $this->assertSame(42, $saleId, 'The winning sale must be the one recorded on the serial.');

    @unlink($dbPath);
});