<?php

use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSalesReturn;
use App\Models\DbStockAdjustment;
use App\Models\DbStockAdjustmentItems;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

function createStoreIsolationEnvironment() {
    $storeA = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Store Alpha',
        'store_code' => 'ST-A',
        'status'     => 1,
        'mobile'     => '01700000001',
    ]);

    $storeB = DbStore::firstOrCreate(['id' => 2], [
        'store_name' => 'Store Beta',
        'store_code' => 'ST-B',
        'status'     => 1,
        'mobile'     => '01700000002',
    ]);

    $roleA = DbRole::firstOrCreate(['id' => 1], [
        'store_id'  => $storeA->id,
        'role_name' => 'Admin A',
        'status'    => 1,
    ]);

    $roleB = DbRole::firstOrCreate(['id' => 2], [
        'store_id'  => $storeB->id,
        'role_name' => 'Admin B',
        'status'    => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $roleA->id], [
        'store_id'    => $storeA->id,
        'permissions' => ['dashboard_view', 'profit_report', 'sales_view', 'stock_adjustment_view', 'reports_view'],
    ]);

    DbPermission::firstOrCreate(['role_id' => $roleB->id], [
        'store_id'    => $storeB->id,
        'permissions' => ['dashboard_view', 'profit_report', 'sales_view', 'stock_adjustment_view', 'reports_view'],
    ]);

    $userA = User::factory()->create([
        'store_id'  => $storeA->id,
        'role_id'   => $roleA->id,
        'role_name' => 'Admin A',
    ]);

    $userB = User::factory()->create([
        'store_id'  => $storeB->id,
        'role_id'   => $roleB->id,
        'role_name' => 'Admin B',
    ]);

    $warehouseA = DbWarehouse::create([
        'store_id'       => $storeA->id,
        'warehouse_name' => 'Warehouse Alpha',
        'status'         => 1,
    ]);

    $warehouseB = DbWarehouse::create([
        'store_id'       => $storeB->id,
        'warehouse_name' => 'Warehouse Beta',
        'status'         => 1,
    ]);

    return compact('storeA', 'storeB', 'userA', 'userB', 'warehouseA', 'warehouseB');
}

test('Step 1a: Dashboard total outstanding due query reflects ONLY the acting store data, not both combined', function () {
    $env = createStoreIsolationEnvironment();
    Cache::flush();

    $customerA = DbCustomer::create([
        'store_id'      => $env['storeA']->id,
        'customer_name' => 'Customer Alpha',
        'mobile'        => '01711111111',
        'status'        => 1,
    ]);

    $customerB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Customer Beta',
        'mobile'        => '01722222222',
        'status'        => 1,
    ]);

    // Store A sale: 1000 grand total, 200 paid -> initial due 800
    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 200.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Store A return on saleA: 100 return, 0 refunded -> net return offsets 100 of due. Net due = (1000-100) - (200-0) = 700
    DbSalesReturn::create([
        'store_id'       => $env['storeA']->id,
        'sales_id'       => $saleA->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'return_code'    => 'RT-A-001',
        'return_date'    => Carbon::today()->format('Y-m-d'),
        'return_status'  => 'Completed',
        'subtotal'       => 100.00,
        'grand_total'    => 100.00,
        'paid_amount'    => 0.00,
        'payment_status' => 'Unpaid',
        'status'         => 1,
    ]);

    // Store B sale: 2000 grand total, 500 paid -> net due = 1500
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 2000.00,
        'grand_total'    => 2000.00,
        'paid_amount'    => 500.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Acting as Store A user
    $resA = $this->actingAs($env['userA'])->get(route('dashboard'));
    $resA->assertOk();
    $statsA = $resA->viewData('stats');
    expect((float) $statsA['total_outstanding_due'])->toEqual(700.00);

    // Acting as Store B user
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();
    $statsB = $resB->viewData('stats');
    expect((float) $statsB['total_outstanding_due'])->toEqual(1500.00);
});

test('Step 1b: Dashboard customers with due query reflects ONLY the acting store customers, not both combined', function () {
    $env = createStoreIsolationEnvironment();
    Cache::flush();

    $customerA = DbCustomer::create([
        'store_id'      => $env['storeA']->id,
        'customer_name' => 'Alpha Due Customer',
        'mobile'        => '01711999999',
        'status'        => 1,
    ]);

    $customerB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Beta Due Customer',
        'mobile'        => '01722999999',
        'status'        => 1,
    ]);

    // Store A sale with due
    DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-DUE',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 800.00,
        'grand_total'    => 800.00,
        'paid_amount'    => 300.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Store B sale with due
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-DUE',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1200.00,
        'grand_total'    => 1200.00,
        'paid_amount'    => 200.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Acting as Store A user
    $resA = $this->actingAs($env['userA'])->get(route('dashboard'));
    $resA->assertOk();
    $customersWithDueA = collect($resA->viewData('customersWithDue'));
    expect($customersWithDueA->pluck('customer_name')->all())->toContain('Alpha Due Customer');
    expect($customersWithDueA->pluck('customer_name')->all())->not->toContain('Beta Due Customer');
    $custRowA = $customersWithDueA->firstWhere('customer_name', 'Alpha Due Customer');
    expect((float) $custRowA->total_due)->toEqual(500.00);

    // Acting as Store B user
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();
    $customersWithDueB = collect($resB->viewData('customersWithDue'));
    expect($customersWithDueB->pluck('customer_name')->all())->toContain('Beta Due Customer');
    expect($customersWithDueB->pluck('customer_name')->all())->not->toContain('Alpha Due Customer');
    $custRowB = $customersWithDueB->firstWhere('customer_name', 'Beta Due Customer');
    expect((float) $custRowB->total_due)->toEqual(1000.00);
});

test('Step 1c: ReportController opening stock calculation reflects ONLY the acting store data, not both combined', function () {
    $env = createStoreIsolationEnvironment();

    $itemA = DbItem::create([
        'store_id'       => $env['storeA']->id,
        'item_name'      => 'Item Alpha',
        'item_code'      => 'ITM-A-01',
        'purchase_price' => 50.00,
        'sales_price'    => 75.00,
        'stock'          => 10,
        'status'         => 1,
    ]);

    $itemB = DbItem::create([
        'store_id'       => $env['storeB']->id,
        'item_name'      => 'Item Beta',
        'item_code'      => 'ITM-B-01',
        'purchase_price' => 80.00,
        'sales_price'    => 120.00,
        'stock'          => 5,
        'status'         => 1,
    ]);

    // Adjustment A in Store A: 10 qty * 50 price = 500 value
    $adjA = DbStockAdjustment::create([
        'store_id'          => $env['storeA']->id,
        'warehouse_id'      => $env['warehouseA']->id,
        'adjustment_date'   => Carbon::today()->format('Y-m-d'),
        'reference_no'      => 'ADJ-A-001',
        'status'            => 1,
    ]);

    DbStockAdjustmentItems::create([
        'store_id'        => $env['storeA']->id,
        'warehouse_id'    => $env['warehouseA']->id,
        'adjustment_id'   => $adjA->id,
        'item_id'         => $itemA->id,
        'adjustment_qty'  => 10,
        'status'          => 1,
    ]);

    // Adjustment B in Store B: 5 qty * 80 price = 400 value
    $adjB = DbStockAdjustment::create([
        'store_id'          => $env['storeB']->id,
        'warehouse_id'      => $env['warehouseB']->id,
        'adjustment_date'   => Carbon::today()->format('Y-m-d'),
        'reference_no'      => 'ADJ-B-001',
        'status'            => 1,
    ]);

    DbStockAdjustmentItems::create([
        'store_id'        => $env['storeB']->id,
        'warehouse_id'    => $env['warehouseB']->id,
        'adjustment_id'   => $adjB->id,
        'item_id'         => $itemB->id,
        'adjustment_qty'  => 5,
        'status'          => 1,
    ]);

    // Query P&L as Store A
    $resA = $this->actingAs($env['userA'])->getJson(route('reports.profit_loss_data'));
    $resA->assertOk();
    $jsonA = $resA->json();
    expect((float) $jsonA['data']['inventory']['openingStock'])->toEqual(500.00);

    // Query P&L as Store B
    $resB = $this->actingAs($env['userB'])->getJson(route('reports.profit_loss_data'));
    $resB->assertOk();
    $jsonB = $resB->json();
    expect((float) $jsonB['data']['inventory']['openingStock'])->toEqual(400.00);
});
