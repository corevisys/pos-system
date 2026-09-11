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
 * PHASE 2 + 3 — Serial-number scan resolution (search fallback + client
 * pre-attach contract) and the same-serial duplicate guard.
 */

function posScanEnv()
{
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'SCAN TEST STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'scan-store@corevisys.com',
            'address' => '123 Scan Avenue',
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], ['role_name' => 'Super Admin', 'status' => 1, 'store_id' => 1]);
    DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['sales_view', 'sales_add']]);

    $user = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 1]);
    $warehouse = DbWarehouse::create(['warehouse_name' => 'Scan WH', 'status' => 1, 'store_id' => 1]);
    $customer = DbCustomer::create(['customer_name' => 'Scan Customer', 'mobile' => '+8801811111111', 'status' => 1, 'store_id' => 1]);

    // IT-00048 with a barcode, an item code, and a serial MS26A8dg (per the spec).
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'IT-00048 Product', 'item_code' => 'IT-00048',
        'custom_barcode' => 'BC-00048', 'sales_price' => 100.00, 'purchase_price' => 60.00,
        'stock' => 10, 'is_serialized' => 1, 'status' => 1, 'store_id' => 1,
    ]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 10]);

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Scan Cash', 'account_number' => 'SCAN-CASH', 'balance' => 10000.00, 'status' => 1]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

test('search by item code still returns the item exactly as before (control test)', function () {
    $env = posScanEnv();

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'IT-00048',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['item_code'])->toBe('IT-00048');
    // The control path must NOT carry serial-match markers.
    expect($data[0])->not->toHaveKey('matched_serial_id');
});

test('search by custom barcode still returns the item (control test)', function () {
    $env = posScanEnv();

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'BC-00048',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['custom_barcode'])->toBe('BC-00048');
    expect($data[0])->not->toHaveKey('matched_serial_id');
});

test('search by item name still returns the item (control test)', function () {
    $env = posScanEnv();

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'IT-00048 Product',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['item_name'])->toBe('IT-00048 Product');
    expect($data[0])->not->toHaveKey('matched_serial_id');
});

test('scanning an available serial resolves to its parent item with the serial pre-attached', function () {
    $env = posScanEnv();

    $serial = DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'MS26A8dg',
        'status' => 0,
    ]);

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'MS26A8dg',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['item_code'])->toBe('IT-00048');
    // Serial-match marker present with the exact serial id/value.
    expect((int) $data[0]['matched_serial_id'])->toBe($serial->id);
    expect($data[0]['matched_serial'])->toBe('MS26A8dg');
});

test('serial scan is case-insensitive (serial stored as typed, scan uppercased)', function () {
    $env = posScanEnv();

    DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'MS26A8dg',
        'status' => 0,
    ]);

    // Scanner sends uppercase.
    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'MS26A8DG',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['item_code'])->toBe('IT-00048');
    expect($data[0]['matched_serial'])->toBe('MS26A8dg');
});

test('sold serial does NOT resolve via scan (status must be 0)', function () {
    $env = posScanEnv();

    DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'SOLD-SN-01',
        'status' => 1,
        'sale_id' => 111,
    ]);

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'SOLD-SN-01',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    expect($response->json())->toBe([]);
});

test('serial from a different store does NOT resolve via scan (store scoping)', function () {
    $env = posScanEnv();

    DbStore::create(['id' => 2, 'store_name' => 'Other Store', 'status' => 1, 'mobile' => '+8801700000001', 'email' => 'other@corevisys.com', 'address' => 'Other']);

    // Serial exists in store 2, warehouse 1 (cross-tenant).
    DbItemSerial::create([
        'store_id' => 2,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'OTHER-STORE-SN',
        'status' => 0,
    ]);

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'OTHER-STORE-SN',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    expect($response->json())->toBe([]);
});

test('unrecognized code matches neither the three fields nor any serial — empty result as before', function () {
    $env = posScanEnv();

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'ZZZ-NO-MATCH-999',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    expect($response->json())->toBe([]);
});

test('search is store-scoped to the current store (cross-store items are not returned)', function () {
    $env = posScanEnv();

    DbStore::create(['id' => 2, 'store_name' => 'Other Store', 'status' => 1, 'mobile' => '+8801700000001', 'email' => 'other@corevisys.com', 'address' => 'Other']);
    // Another store's item — item_code is globally UNIQUE (db_items), so use a
    // distinct code but the SAME barcode (barcodes are not unique).
    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Store 2 Item', 'item_code' => 'IT-00048-S2',
        'custom_barcode' => 'BC-00048', 'sales_price' => 5.00, 'purchase_price' => 2.00,
        'stock' => 5, 'status' => 1, 'store_id' => 2,
    ]);

    $response = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'IT-00048',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $response->assertStatus(200);
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect((int) $data[0]['id'])->toBe($env['item']->id);
});

test('POS page ships the scan-by-serial pre-attach contract (view-render guard)', function () {
    $env = posScanEnv();

    $response = $this->actingAs($env['user'])->get(route('sales.pos'));
    $response->assertOk();
    $html = $response->getContent();

    // The client must recognize serial-fallback hits and call the pre-attach helper.
    expect($html)->toContain('matched_serial_id');
    expect($html)->toContain('addToCartBySerial(found, found.matched_serial_id');
    // The pre-attach helper must exist and populate the exact serial.
    expect($html)->toContain('addToCartBySerial(p, serialId, serialValue)');
    expect($html)->toContain('selectedSerials: [serialId]');
    // The regular typed / barcode-scan serialized flow must remain (modal intact).
    expect($html)->toContain('openSerialModal(p)');
    // Phase 3 client guard message.
    expect($html)->toContain('This serial is already in your cart.');
});

test('duplicate serial posted twice within one checkout payload is rejected server-side', function () {
    $env = posScanEnv();

    $serial = DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'DUP-SN-01',
        'status' => 0,
    ]);

    // Two cart lines both claiming the same serial id.
    $payload = [
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [
            [
                'id' => $env['item']->id, 'name' => $env['item']->item_name,
                'price' => 100.00, 'qty' => 1, 'total' => 100.00,
                'discount' => 0, 'tax' => 0, 'taxAmount' => 0,
                'selectedSerials' => [$serial->id], 'serial' => 'DUP-SN-01',
            ],
            [
                'id' => $env['item']->id, 'name' => $env['item']->item_name,
                'price' => 100.00, 'qty' => 1, 'total' => 100.00,
                'discount' => 0, 'tax' => 0, 'taxAmount' => 0,
                'selectedSerials' => [$serial->id], 'serial' => 'DUP-SN-01',
            ],
        ],
        'subtotal' => 200.00,
        'grand_total' => 200.00,
        'paid_amount' => 200.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ];

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);
    expect($response->json('message'))->toContain('already in this cart');

    expect(DbSale::count())->toBe(0);
    expect($serial->fresh()->status)->toBe(0);
});
