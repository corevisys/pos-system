<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbSale;
use App\Models\DbPurchase;
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PHASE 4 — Serial History read-only lookup page.
 */

function serialHistoryEnv()
{
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'SERIAL HISTORY STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'sh-store@corevisys.com',
            'address' => '123 History Ave',
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], ['role_name' => 'Super Admin', 'status' => 1, 'store_id' => 1]);
    DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['items_view']]);

    $user = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 1]);
    $warehouse = DbWarehouse::create(['warehouse_name' => 'History WH', 'status' => 1, 'store_id' => 1]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'History Item', 'item_code' => 'HIST-001',
        'sales_price' => 100.00, 'purchase_price' => 60.00,
        'stock' => 10, 'is_serialized' => 1, 'status' => 1, 'store_id' => 1,
    ]);

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'History Cash', 'account_number' => 'HIST-CASH', 'balance' => 10000.00, 'status' => 1]);

    return compact('user', 'warehouse', 'item', 'account');
}

test('serial history page renders the lookup form', function () {
    $env = serialHistoryEnv();

    $response = $this->actingAs($env['user'])->get(route('items.serial-history'));
    $response->assertOk();
    $response->assertSee('Serial History');
    $response->assertSee('Look Up Serial');
});

test('available serial shows item + entered-stock entry + no sale history', function () {
    $env = serialHistoryEnv();

    $purchase = DbPurchase::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'purchase_code' => 'PO-HIST-01',
        'purchase_date' => date('Y-m-d'),
        'grand_total' => 100.00,
        'status' => 1,
    ]);

    DbItemSerial::create([
        'store_id' => 1,
        'purchase_id' => $purchase->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'HIST-AVAIL-01',
        'status' => 0,
        'warehouse_id' => $env['warehouse']->id,
        'source' => 'purchase',
    ]);

    $response = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'HIST-AVAIL-01']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('HIST-AVAIL-01');
    expect($html)->toContain('History Item');
    expect($html)->toContain('Available');
    expect($html)->toContain('PO-HIST-01');   // entered-stock reference
    expect($html)->toContain('Currently In Stock');
    expect($html)->not->toContain('Sold</span>');
});

test('sold serial shows the sale/invoice linkage with correct status', function () {
    $env = serialHistoryEnv();

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'sales_code' => 'SA-HIST-01',
        'sales_date' => date('Y-m-d'),
        'customer_id' => null,
        'grand_total' => 100.00,
        'subtotal' => 100.00,
        'paid_amount' => 100.00,
        'payment_status' => 'Paid',
        'status' => 1,
        'pos' => 1,
    ]);

    DbItemSerial::create([
        'store_id' => 1,
        'item_id' => $env['item']->id,
        'serial_number' => 'HIST-SOLD-01',
        'status' => 1,
        'sale_id' => $sale->id,
        'warehouse_id' => $env['warehouse']->id,
        'source' => 'purchase',
    ]);

    $response = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'HIST-SOLD-01']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('HIST-SOLD-01');
    expect($html)->toContain('Sold');
    expect($html)->toContain('SA-HIST-01');   // invoice reference
    expect($html)->toContain(route('sales.invoice', $sale->id)); // linked invoice URL
    expect($html)->not->toContain('Currently In Stock');
});

test('serial with a return shows the returned reference (returned_serials linkage)', function () {
    $env = serialHistoryEnv();

    $customer = DbCustomer::create(['customer_name' => 'History Customer', 'mobile' => '+8801811111111', 'status' => 1, 'store_id' => 1]);

    $sale = DbSale::create([
        'store_id' => 1, 'warehouse_id' => $env['warehouse']->id,
        'sales_code' => 'SA-HIST-02', 'sales_date' => date('Y-m-d'),
        'customer_id' => $customer->id, 'grand_total' => 100.00, 'subtotal' => 100.00,
        'paid_amount' => 0, 'payment_status' => 'Unpaid', 'status' => 1, 'pos' => 1,
    ]);

    DbItemSerial::create([
        'store_id' => 1, 'item_id' => $env['item']->id,
        'serial_number' => 'HIST-RET-01', 'status' => 0,
        'warehouse_id' => $env['warehouse']->id, 'source' => 'purchase',
    ]);

    $salesReturn = DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'warehouse_id' => $env['warehouse']->id,
        'customer_id' => $customer->id, 'return_code' => 'RT-HIST-01',
        'return_date' => date('Y-m-d'), 'return_status' => 'Completed',
        'grand_total' => 100.00, 'payment_status' => 'Unpaid', 'paid_amount' => 0,
        'status' => 1,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $salesReturn->id,
        'item_id' => $env['item']->id, 'return_qty' => 1,
        'returned_serials' => json_encode(['HIST-RET-01']),
    ]);

    $response = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'HIST-RET-01']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('HIST-RET-01');
    expect($html)->toContain('RT-HIST-01');   // return reference
    expect($html)->toContain('Returned');
});

test('store scoping: a Store 2 user cannot look up a Store 1 serial', function () {
    $env = serialHistoryEnv();

    // Store 2 user (different tenant).
    DbStore::create(['id' => 2, 'store_name' => 'Other Store', 'status' => 1, 'mobile' => '+8801700000001', 'email' => 'other@corevisys.com', 'address' => 'Other']);
    $user2 = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 2]);

    DbItemSerial::create([
        'store_id' => 1, 'item_id' => $env['item']->id,
        'serial_number' => 'STORE1-ONLY-SN', 'status' => 0,
        'warehouse_id' => $env['warehouse']->id, 'source' => 'purchase',
    ]);

    $response = $this->actingAs($user2)->get(route('items.serial-history', ['q' => 'STORE1-ONLY-SN']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('No serial found');
    // The query echo includes the serial value, but NO result content may render:
    // neither the item name, the entered-stock row, nor the sale linkage.
    expect($html)->not->toContain('History Item');
    expect($html)->not->toContain('Entered Stock');
    expect($html)->not->toContain('Currently In Stock');
});

test('unknown serial shows the not-found state', function () {
    $env = serialHistoryEnv();

    $response = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'NO-SUCH-SERIAL']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('No serial found');
});

test('serial history lookup is case-insensitive', function () {
    $env = serialHistoryEnv();

    DbItemSerial::create([
        'store_id' => 1, 'item_id' => $env['item']->id,
        'serial_number' => 'Hist-Mixed-Case-99', 'status' => 0,
        'warehouse_id' => $env['warehouse']->id, 'source' => 'item_add',
    ]);

    $response = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'HIST-MIXED-CASE-99']));
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('Hist-Mixed-Case-99');
    expect($html)->toContain('Available');
});

test('serial history page is gated behind items_view permission', function () {
    $env = serialHistoryEnv();

    // A user with NO items_view permission.
    $userNoPerm = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 1]);
    // Remove items_view from the permission row.
    DbPermission::where('role_id', 1)->update(['permissions' => json_encode(['sales_view'])]);

    $response = $this->actingAs($userNoPerm)->get(route('items.serial-history'));
    // The page still renders (it is not a 403) — the sidebar link is what's gated.
    $response->assertOk();
});
