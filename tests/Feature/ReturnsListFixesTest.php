<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalesReturn;
use App\Models\AcAccount;
use Carbon\Carbon;

function getReturnListFixesUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1,
    ]);

    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Returns List Fix Store',
        'status' => 1, 'mobile' => '01700000000',
        'currency_id' => $currency->id, 'decimals' => 2,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin',
    ]);
}

function makeReturnListFixSale(User $user): array {
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'RL Fix WH', 'status' => 1]);
    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'RL Fix Customer', 'customer_code' => 'CUST-RLF-01', 'mobile' => '01793000001', 'status' => 1,
    ]);
    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'RL Fix Item', 'item_code' => 'ITM-RLF-01', 'category_id' => $category->id,
        'purchase_price' => 100, 'sales_price' => 500, 'stock' => 10, 'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id,
        'sales_code' => 'SA-RLF-01', 'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000, 'grand_total' => 1000, 'paid_amount' => 1000,
        'payment_status' => 'Paid', 'status' => 1, 'return_bit' => 0,
    ]);

    DbSaleItem::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'item_id' => $item->id,
        'sales_qty' => 2, 'price_per_unit' => 500, 'total_cost' => 1000,
    ]);

    return ['warehouse' => $warehouse, 'sale' => $sale, 'item' => $item, 'customer' => $customer];
}

test('1. "New Return" button opens a sale-picker with returnable sales', function () {
    $user = getReturnListFixesUser();
    makeReturnListFixSale($user);

    $response = $this->actingAs($user)->get(route('sales.returns'));
    $response->assertOk();
    // Button now dispatches the picker (rendered button)
    $response->assertSee('New Return');
    // The picker lists the returnable sale and links to return.create
    $response->assertSee('SA-RLF-01');
    $response->assertSee(route('sales.return.create', 1));
});

test('2. Per-page limit param is honored and preserved', function () {
    $user = getReturnListFixesUser();
    makeReturnListFixSale($user);

    $response = $this->actingAs($user)->get(route('sales.returns', ['limit' => 25]));
    $response->assertOk();
    $response->assertSee('25');
    // The select renders the selected state (HTML <option ... selected>)
    $response->assertSee('value="25" selected', false);
});

test('3. CSV export of the filtered returns list downloads matching rows', function () {
    $user = getReturnListFixesUser();
    $fx = makeReturnListFixSale($user);

    // Create a return so the export has a row
    $acct = AcAccount::create([
        'store_id' => 1, 'account_name' => 'RLF Acct', 'account_code' => 'ACC-RLF-01',
        'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0,
    ]);
    DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $fx['sale']->id, 'warehouse_id' => $fx['warehouse']->id,
        'customer_id' => $fx['customer']->id, 'return_code' => 'SR-RLF-01',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('sales.returns', ['export' => 'csv']));
    $response->assertOk();
    $response->assertHeader('Content-type', 'text/csv; charset=utf-8');
    $content = $response->streamedContent();
    expect($content)->toContain('SR-RLF-01');
    expect($content)->toContain('SA-RLF-01');
});

test('4. Print export renders the print-friendly returns list view', function () {
    $user = getReturnListFixesUser();
    $fx = makeReturnListFixSale($user);

    DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $fx['sale']->id, 'warehouse_id' => $fx['warehouse']->id,
        'customer_id' => $fx['customer']->id, 'return_code' => 'SR-RLF-02',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('sales.returns', ['export' => 'print']));
    $response->assertOk();
    $response->assertSee('Sales Returns List');
    $response->assertSee('SR-RLF-02');
});

test('5. Store A user only sees store-A returns and store-A stats', function () {
    $user = getReturnListFixesUser();
    $fx = makeReturnListFixSale($user);

    DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $fx['sale']->id, 'warehouse_id' => $fx['warehouse']->id,
        'customer_id' => $fx['customer']->id, 'return_code' => 'SR-RLF-A',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 300, 'grand_total' => 300, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    // Store-2 return (different store)
    $wh2 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'RLF WH2', 'status' => 1]);
    $cust2 = DbCustomer::create(['store_id' => 1, 'customer_name' => 'RLF C2', 'customer_code' => 'CUST-RLF-2', 'mobile' => '01794000001', 'status' => 1]);
    $sale2 = DbSale::create([
        'store_id' => 2, 'warehouse_id' => $wh2->id, 'customer_id' => $cust2->id,
        'sales_code' => 'SA-RLF-2', 'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 100, 'grand_total' => 100, 'paid_amount' => 100,
        'payment_status' => 'Paid', 'status' => 1, 'return_bit' => 1,
    ]);
    DbSalesReturn::create([
        'store_id' => 2, 'sales_id' => $sale2->id, 'warehouse_id' => $wh2->id,
        'customer_id' => $cust2->id, 'return_code' => 'SR-RLF-B',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 50, 'grand_total' => 50, 'paid_amount' => 50,
        'payment_status' => 'Paid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('sales.returns'));
    $response->assertOk();
    $response->assertSee('SR-RLF-A');
    $response->assertDontSee('SR-RLF-B');
    // Stats: only store-1's 300 shows as total (rendered through <x-money>)
    assert_compact_amount($response, 300);
    assert_compact_amount_absent($response, 50);
});

test('6. Stat cards reflect the applied warehouse filter', function () {
    $user = getReturnListFixesUser();
    $fx = makeReturnListFixSale($user);

    $wh2 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'RLF WH3', 'status' => 1]);
    $sale2 = DbSale::create([
        'store_id' => 1, 'warehouse_id' => $wh2->id, 'customer_id' => $fx['customer']->id,
        'sales_code' => 'SA-RLF-3', 'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 100, 'grand_total' => 100, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'status' => 1, 'return_bit' => 1,
    ]);

    // Return in WH1 = 500, return in WH3 = 100
    DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $fx['sale']->id, 'warehouse_id' => $fx['warehouse']->id,
        'customer_id' => $fx['customer']->id, 'return_code' => 'SR-RLF-W1',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);
    DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $sale2->id, 'warehouse_id' => $wh2->id,
        'customer_id' => $fx['customer']->id, 'return_code' => 'SR-RLF-W2',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 100, 'grand_total' => 100, 'paid_amount' => 0,
        'payment_status' => 'Unpaid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    // Unfiltered: total = 600
    $respAll = $this->actingAs($user)->get(route('sales.returns'));
    assert_compact_amount($respAll, 600);

    // Filtered to WH1: total = 500
    $respWh = $this->actingAs($user)->get(route('sales.returns', ['warehouse_id' => $fx['warehouse']->id]));
    assert_compact_amount($respWh, 500);
    assert_compact_amount_absent($respWh, 100);
});
