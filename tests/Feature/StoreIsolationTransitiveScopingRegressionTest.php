<?php

use App\Models\AcAccount;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchaseItem;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function setupTransitiveScopingEnvironment() {
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

    $permissions = [
        'profit_report', 'sales_summary_report', 'cash_flow_report',
        'items_view', 'items_delete', 'services_view', 'services_delete',
        'accounts_view', 'accounts_delete',
        // Reports now have a single route-level gate.
        'reports_view',
    ];

    DbPermission::firstOrCreate(['role_id' => $roleA->id], [
        'store_id'    => $storeA->id,
        'permissions' => $permissions,
    ]);

    DbPermission::firstOrCreate(['role_id' => $roleB->id], [
        'store_id'    => $storeB->id,
        'permissions' => $permissions,
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

    $supplierA = DbSupplier::create([
        'store_id'      => $storeA->id,
        'supplier_name' => 'Supplier Alpha',
        'status'        => 1,
    ]);

    $supplierB = DbSupplier::create([
        'store_id'      => $storeB->id,
        'supplier_name' => 'Supplier Beta',
        'status'        => 1,
    ]);

    return compact('storeA', 'storeB', 'userA', 'userB', 'warehouseA', 'warehouseB', 'supplierA', 'supplierB');
}

test('Transitive scoping: ReportController P&L raw purchase items and sales items joins only aggregate acting store data', function () {
    $env = setupTransitiveScopingEnvironment();

    $itemA = DbItem::create([
        'store_id'       => $env['storeA']->id,
        'item_name'      => 'Item Alpha',
        'purchase_price' => 100.00,
        'sales_price'    => 150.00,
        'status'         => 1,
    ]);

    $itemB = DbItem::create([
        'store_id'       => $env['storeB']->id,
        'item_name'      => 'Item Beta',
        'purchase_price' => 200.00,
        'sales_price'    => 300.00,
        'status'         => 1,
    ]);

    // Store A Purchase with purchase item details: tax 100, discount 20
    $purchaseA = DbPurchase::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'supplier_id'    => $env['supplierA']->id,
        'purchase_code'  => 'PO-A-TRANS-1',
        'purchase_date'  => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1080.00,
        'paid_amount'    => 1080.00,
        'purchase_status'=> 'Received',
        'status'         => 1,
    ]);

    DB::table('db_purchaseitems')->insert([
        'store_id'        => $env['storeA']->id,
        'purchase_id'     => $purchaseA->id,
        'item_id'         => $itemA->id,
        'purchase_qty'    => 10,
        'price_per_unit'  => 100.00,
        'tax_amt'         => 100.00,
        'discount_amt'    => 20.00,
        'total_cost'      => 1080.00,
        'status'          => 1,
    ]);

    // Store B Purchase with purchase item details: tax 300, discount 60
    $purchaseB = DbPurchase::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'supplier_id'    => $env['supplierB']->id,
        'purchase_code'  => 'PO-B-TRANS-1',
        'purchase_date'  => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 2000.00,
        'grand_total'    => 2240.00,
        'paid_amount'    => 2240.00,
        'purchase_status'=> 'Received',
        'status'         => 1,
    ]);

    DB::table('db_purchaseitems')->insert([
        'store_id'        => $env['storeB']->id,
        'purchase_id'     => $purchaseB->id,
        'item_id'         => $itemB->id,
        'purchase_qty'    => 10,
        'price_per_unit'  => 200.00,
        'tax_amt'         => 300.00,
        'discount_amt'    => 60.00,
        'total_cost'      => 2240.00,
        'status'          => 1,
    ]);

    // Store A Sale with sales items details: tax 50, discount 10
    $customerA = DbCustomer::create([
        'store_id'      => $env['storeA']->id,
        'customer_name' => 'Cust A',
        'status'        => 1,
    ]);

    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-TRANS-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1500.00,
        'grand_total'    => 1540.00,
        'paid_amount'    => 1540.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DB::table('db_salesitems')->insert([
        'store_id'        => $env['storeA']->id,
        'sales_id'        => $saleA->id,
        'item_id'         => $itemA->id,
        'sales_qty'       => 10,
        'price_per_unit'  => 150.00,
        'tax_amt'         => 50.00,
        'discount_amt'    => 10.00,
        'total_cost'      => 1540.00,
        'status'          => 1,
    ]);

    // Store B Sale with sales items details: tax 150, discount 30
    $customerB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Cust B',
        'status'        => 1,
    ]);

    $saleB = DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-TRANS-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 3000.00,
        'grand_total'    => 3120.00,
        'paid_amount'    => 3120.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DB::table('db_salesitems')->insert([
        'store_id'        => $env['storeB']->id,
        'sales_id'        => $saleB->id,
        'item_id'         => $itemB->id,
        'sales_qty'       => 10,
        'price_per_unit'  => 300.00,
        'tax_amt'         => 150.00,
        'discount_amt'    => 30.00,
        'total_cost'      => 3120.00,
        'status'          => 1,
    ]);

    // Request P&L as Store A
    $resA = $this->actingAs($env['userA'])->getJson(route('reports.profit_loss_data'));
    $resA->assertOk();
    $dataA = $resA->json()['data'];

    expect((float) $dataA['purchases']['purchaseTax'])->toEqual(100.00);
    expect((float) $dataA['purchases']['discount'])->toEqual(20.00);
    expect((float) $dataA['sales']['salesTax'])->toEqual(50.00);
    expect((float) $dataA['sales']['discount'])->toEqual(10.00);

    // Request P&L as Store B
    $resB = $this->actingAs($env['userB'])->getJson(route('reports.profit_loss_data'));
    $resB->assertOk();
    $dataB = $resB->json()['data'];

    expect((float) $dataB['purchases']['purchaseTax'])->toEqual(300.00);
    expect((float) $dataB['purchases']['discount'])->toEqual(60.00);
    expect((float) $dataB['sales']['salesTax'])->toEqual(150.00);
    expect((float) $dataB['sales']['discount'])->toEqual(30.00);
});

test('Transitive scoping: ReportController Sales Summary raw query only aggregates acting store sales', function () {
    $env = setupTransitiveScopingEnvironment();

    $customerA = DbCustomer::create(['store_id' => $env['storeA']->id, 'customer_name' => 'Cust A', 'status' => 1]);
    $customerB = DbCustomer::create(['store_id' => $env['storeB']->id, 'customer_name' => 'Cust B', 'status' => 1]);

    DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-SUM-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 1000.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-SUM-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 2500.00,
        'grand_total'    => 2500.00,
        'paid_amount'    => 2500.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    $resA = $this->actingAs($env['userA'])->getJson(route('reports.sales_summary_data'));
    $resA->assertOk();
    $dataA = $resA->json();
    expect((float) $dataA['summary']['total_sales'])->toEqual(1000.00);

    $resB = $this->actingAs($env['userB'])->getJson(route('reports.sales_summary_data'));
    $resB->assertOk();
    $dataB = $resB->json();
    expect((float) $dataB['summary']['total_sales'])->toEqual(2500.00);
});

test('Transitive scoping: ServiceController destroy history guard isolates services per store and blocks IDOR', function () {
    $env = setupTransitiveScopingEnvironment();

    $serviceA = DbItem::create([
        'store_id'       => $env['storeA']->id,
        'item_name'      => 'Repair Service Alpha',
        'service_bit'    => 1,
        'sales_price'    => 100.00,
        'status'         => 1,
    ]);

    $serviceB = DbItem::create([
        'store_id'       => $env['storeB']->id,
        'item_name'      => 'Cleaning Service Beta',
        'service_bit'    => 1,
        'sales_price'    => 80.00,
        'status'         => 1,
    ]);

    $customerA = DbCustomer::create(['store_id' => $env['storeA']->id, 'customer_name' => 'Cust A', 'status' => 1]);
    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-SRV',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 100.00,
        'grand_total'    => 100.00,
        'paid_amount'    => 100.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Add sales item history to Service A in Store A
    DB::table('db_salesitems')->insert([
        'store_id'       => $env['storeA']->id,
        'sales_id'       => $saleA->id,
        'item_id'        => $serviceA->id,
        'sales_qty'      => 1,
        'price_per_unit' => 100.00,
        'total_cost'     => 100.00,
        'status'         => 1,
    ]);

    // Store A user tries to delete Service A (has history) -> 422
    $resDelA = $this->actingAs($env['userA'])->deleteJson(route('items.service.delete', ['id' => $serviceA->id]));
    $resDelA->assertStatus(422);
    $resDelA->assertJson(['success' => false]);
    expect($resDelA->json()['message'])->toContain('existing sales/order history');

    // Store B user deletes Service B (no history) -> 200
    $resDelB = $this->actingAs($env['userB'])->deleteJson(route('items.service.delete', ['id' => $serviceB->id]));
    $resDelB->assertOk();
    $resDelB->assertJson(['success' => true]);

    // Store B user tries to delete Service A -> 404 (IDOR guard)
    $resIdor = $this->actingAs($env['userB'])->deleteJson(route('items.service.delete', ['id' => $serviceA->id]));
    $resIdor->assertStatus(404);
});

test('Transitive scoping: ItemController destroy history guard isolates items per store and blocks IDOR', function () {
    $env = setupTransitiveScopingEnvironment();

    $itemA = DbItem::create([
        'store_id'       => $env['storeA']->id,
        'item_name'      => 'Item Alpha With History',
        'sales_price'    => 100.00,
        'status'         => 1,
    ]);

    $itemB = DbItem::create([
        'store_id'       => $env['storeB']->id,
        'item_name'      => 'Item Beta Without History',
        'sales_price'    => 80.00,
        'status'         => 1,
    ]);

    $customerA = DbCustomer::create(['store_id' => $env['storeA']->id, 'customer_name' => 'Cust A', 'status' => 1]);
    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-ITM',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 100.00,
        'grand_total'    => 100.00,
        'paid_amount'    => 100.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Add history for Item A
    DB::table('db_salesitems')->insert([
        'store_id'       => $env['storeA']->id,
        'sales_id'       => $saleA->id,
        'item_id'        => $itemA->id,
        'sales_qty'      => 1,
        'price_per_unit' => 100.00,
        'total_cost'     => 100.00,
        'status'         => 1,
    ]);

    // Store A user tries to delete Item A -> 422
    $resDelA = $this->actingAs($env['userA'])->deleteJson(route('items.delete', ['id' => $itemA->id]));
    $resDelA->assertStatus(422);
    $resDelA->assertJson(['success' => false]);

    // Store B user deletes Item B -> 200
    $resDelB = $this->actingAs($env['userB'])->deleteJson(route('items.delete', ['id' => $itemB->id]));
    $resDelB->assertOk();
    $resDelB->assertJson(['success' => true]);

    // Store B user tries to delete Item A -> 404 (IDOR guard)
    $resIdor = $this->actingAs($env['userB'])->deleteJson(route('items.delete', ['id' => $itemA->id]));
    $resIdor->assertStatus(404);
});

test('Transitive scoping: AccountController dependency checks block deletion only for accounts with records', function () {
    $env = setupTransitiveScopingEnvironment();

    $accA = AcAccount::create([
        'store_id'     => $env['storeA']->id,
        'account_name' => 'Account Alpha With Txns',
        'account_code' => 'ACC-A-TXN',
        'status'       => 1,
    ]);

    $accB = AcAccount::create([
        'store_id'     => $env['storeB']->id,
        'account_name' => 'Account Beta Clean',
        'account_code' => 'ACC-B-CLN',
        'status'       => 1,
    ]);

    $customerA = DbCustomer::create(['store_id' => $env['storeA']->id, 'customer_name' => 'Cust A', 'status' => 1]);
    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-ACC',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 500.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Add sales payment referencing Account A
    DB::table('db_salespayments')->insert([
        'store_id'     => $env['storeA']->id,
        'sales_id'     => $saleA->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment'      => 500.00,
        'account_id'   => $accA->id,
        'status'       => 1,
    ]);

    // Store A user tries to delete Account A -> blocked with warning/error
    $resDelA = $this->actingAs($env['userA'])->delete(route('accounts.delete', ['id' => $accA->id]));
    $resDelA->assertSessionHas('error');
    expect(AcAccount::where('id', $accA->id)->where('delete_bit', 0)->exists())->toBeTrue();

    // Store B user deletes Account B -> succeeds
    $resDelB = $this->actingAs($env['userB'])->delete(route('accounts.delete', ['id' => $accB->id]));
    $resDelB->assertSessionHas('success');
    expect(AcAccount::where('id', $accB->id)->where('delete_bit', 0)->exists())->toBeFalse();

    // Store B user tries to delete Account A -> redirected with 'Account not found.'
    $resIdor = $this->actingAs($env['userB'])->delete(route('accounts.delete', ['id' => $accA->id]));
    $resIdor->assertRedirect(route('accounts.list'));
    $resIdor->assertSessionHas('error', 'Account not found.');
    expect(AcAccount::withoutGlobalScopes()->where('id', $accA->id)->where('delete_bit', 0)->exists())->toBeTrue();
});

test('Transitive scoping: ReportController Cash Flow report only reflects acting store account transactions', function () {
    $env = setupTransitiveScopingEnvironment();

    $accA = AcAccount::create([
        'store_id'     => $env['storeA']->id,
        'account_name' => 'Cash A',
        'account_code' => 'CSH-A',
        'status'       => 1,
    ]);

    $accB = AcAccount::create([
        'store_id'     => $env['storeB']->id,
        'account_name' => 'Cash B',
        'account_code' => 'CSH-B',
        'status'       => 1,
    ]);

    $customerA = DbCustomer::create(['store_id' => $env['storeA']->id, 'customer_name' => 'Cust A', 'status' => 1]);
    $saleA = DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'customer_id'    => $customerA->id,
        'sales_code'     => 'SL-A-CF',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 750.00,
        'grand_total'    => 750.00,
        'paid_amount'    => 750.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbSalePayment::create([
        'store_id'     => $env['storeA']->id,
        'sales_id'     => $saleA->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment'      => 750.00,
        'account_id'   => $accA->id,
        'status'       => 1,
    ]);

    \App\Models\AcTransaction::create([
        'store_id'          => $env['storeA']->id,
        'transaction_date'  => Carbon::today()->format('Y-m-d'),
        'transaction_type'  => 'SALES PAYMENT',
        'credit_account_id' => $accA->id,
        'credit_amt'        => 750.00,
        'debit_amt'         => 0.00,
        'status'            => 1,
    ]);

    $customerB = DbCustomer::create(['store_id' => $env['storeB']->id, 'customer_name' => 'Cust B', 'status' => 1]);
    $saleB = DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-CF',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1800.00,
        'grand_total'    => 1800.00,
        'paid_amount'    => 1800.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbSalePayment::create([
        'store_id'     => $env['storeB']->id,
        'sales_id'     => $saleB->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment'      => 1800.00,
        'account_id'   => $accB->id,
        'status'       => 1,
    ]);

    \App\Models\AcTransaction::create([
        'store_id'          => $env['storeB']->id,
        'transaction_date'  => Carbon::today()->format('Y-m-d'),
        'transaction_type'  => 'SALES PAYMENT',
        'credit_account_id' => $accB->id,
        'credit_amt'        => 1800.00,
        'debit_amt'         => 0.00,
        'status'            => 1,
    ]);

    // Request Cash Flow as Store A
    $resA = $this->actingAs($env['userA'])->getJson(route('reports.cash_flow_data', [
        'start_date' => Carbon::today()->format('Y-m-d'),
        'end_date'   => Carbon::today()->format('Y-m-d'),
    ]));
    $resA->assertOk();
    $dataA = $resA->json();
    expect((float) $dataA['operating_activities']['sales_payments'])->toEqual(750.00);

    // Request Cash Flow as Store B
    $resB = $this->actingAs($env['userB'])->getJson(route('reports.cash_flow_data', [
        'start_date' => Carbon::today()->format('Y-m-d'),
        'end_date'   => Carbon::today()->format('Y-m-d'),
    ]));
    $resB->assertOk();
    $dataB = $resB->json();
    expect((float) $dataB['operating_activities']['sales_payments'])->toEqual(1800.00);
});
