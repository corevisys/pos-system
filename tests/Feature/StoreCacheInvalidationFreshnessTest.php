<?php

use App\Http\Controllers\DashboardController;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchaseItem;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\SmsAutoRule;
use App\Models\User;
use App\SMS\Services\RuleResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

function setupFreshnessTestEnv(int $storeId = 1)
{
    Cache::flush();

    $store = DbStore::firstOrCreate(['id' => $storeId], [
        'store_name' => 'Store Freshness ' . $storeId,
        'store_code' => 'ST-FR-' . $storeId,
        'status'     => 1,
        'mobile'     => '0171111111' . $storeId,
    ]);

    $role = DbRole::firstOrCreate(['id' => $storeId + 100], [
        'store_id'  => $store->id,
        'role_name' => 'Super Admin ' . $storeId,
        'status'    => 1,
    ]);

    $allPerms = [
        'dashboard_view', 'sales_view', 'sales_add', 'sales_edit', 'sales_delete',
        'sales_payment_add', 'sales_payment_delete', 'sales_return_add', 'sales_return_view',
        'purchase_view', 'purchase_add', 'purchase_edit', 'purchase_delete',
        'profit_report', 'sales_summary_report', 'cash_flow_report',
    ];

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id'    => $store->id,
        'permissions' => $allPerms,
    ]);

    $user = User::factory()->create([
        'store_id'  => $store->id,
        'role_id'   => $role->id,
        'role_name' => 'Super Admin ' . $storeId,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id'       => $store->id,
        'warehouse_name' => 'Warehouse Freshness ' . $storeId,
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id'      => $store->id,
        'customer_name' => 'Freshness Customer ' . $storeId,
        'mobile'        => '0182222222' . $storeId,
        'status'        => 1,
    ]);

    $supplier = DbSupplier::create([
        'store_id'      => $store->id,
        'supplier_name' => 'Freshness Supplier ' . $storeId,
        'supplier_code' => 'SUP-FR-' . $storeId,
        'mobile'        => '0193333333' . $storeId,
        'status'        => 1,
    ]);

    $item = DbItem::create([
        'store_id'       => $store->id,
        'item_name'      => 'Freshness Item ' . $storeId,
        'item_code'      => 'ITM-FR-' . $storeId,
        'sales_price'    => 500.00,
        'purchase_price' => 300.00,
        'stock'          => 100,
        'status'         => 1,
        'is_serialized'  => 0,
    ]);

    DbWarehouseItem::create([
        'store_id'       => $store->id,
        'warehouse_id'   => $warehouse->id,
        'item_id'        => $item->id,
        'available_qty'  => 100,
    ]);

    $account = AcAccount::create([
        'store_id'       => $store->id,
        'account_name'   => 'Cash Counter ' . $storeId,
        'account_number' => 'ACC-FR-' . $storeId,
        'balance'        => 10000.00,
        'status'         => 1,
    ]);

    DbPaymentType::firstOrCreate(
        ['store_id' => $store->id, 'payment_type' => 'Cash'],
        ['status' => 1]
    );

    return compact('store', 'user', 'warehouse', 'customer', 'supplier', 'item', 'account');
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. DashboardController::clearDashboardCache
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.1: DashboardController::clearDashboardCache purges all store cache keys and subsequent read refreshes live data', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    // Create a real sale with 200.00 due
    DbSale::create([
        'store_id'       => $sid,
        'warehouse_id'   => $env['warehouse']->id,
        'customer_id'    => $env['customer']->id,
        'sales_code'     => 'SL-DASH-CLEAR-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 300.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Poison all 8 store-scoped dashboard keys with stale sentinel data
    Cache::put('dashboard_outstanding_due_s' . $sid, 99999.00, 300);
    Cache::put('dashboard_month_sale_ids_s' . $sid, collect([888888]), 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect([(object)['total_due' => 99999]]), 300);
    Cache::put('dashboard_month_purchases_s' . $sid, 77777.00, 300);
    Cache::put('dashboard_chart_last7_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_last30_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_weekly_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_monthly_s' . $sid, ['stale' => true], 300);

    // Call clearDashboardCache for this store
    DashboardController::clearDashboardCache($sid);

    // Assert all 8 keys are forgotten
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_purchases_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last7_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last30_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_weekly_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_monthly_s' . $sid))->toBeFalse();

    // Read dashboard as acting user; must compute live values, NOT stale sentinel
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');

    expect((float) $stats['total_outstanding_due'])->toEqual(200.00);
    expect((float) $stats['total_outstanding_due'])->not->toEqual(99999.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. PosController::store (PosController.php:609-615)
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.2: PosController regular sale checkout invalidates dashboard caches and refreshes own live figures', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    // Poison all 7 affected cache keys
    Cache::put('dashboard_outstanding_due_s' . $sid, 88888.00, 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['stale']), 300);
    Cache::put('dashboard_month_sale_ids_s' . $sid, collect([77777]), 300);
    Cache::put('dashboard_chart_last7_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_last30_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_weekly_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_monthly_s' . $sid, ['stale' => true], 300);

    // Perform POS sale with partial payment (500 total, 200 paid => 300 due)
    $payload = [
        'customer_id'  => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart'         => [[
            'id'    => $env['item']->id,
            'name'  => $env['item']->item_name,
            'price' => 500.00,
            'qty'   => 1,
            'total' => 500.00,
        ]],
        'subtotal'     => 500.00,
        'grand_total'  => 500.00,
        'paid_amount'  => 200.00,
        'payment_type' => 'Cash',
        'account_id'   => $env['account']->id,
    ];

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert all 7 cache keys were purged
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last7_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last30_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_weekly_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_monthly_s' . $sid))->toBeFalse();

    // Verify next dashboard read reflects the new 300.00 due
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(300.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. PosController::storeEmi (PosController.php:1027-1033)
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.3: PosController EMI sale checkout invalidates dashboard caches and refreshes own live figures', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;
    $env['customer']->update(['customer_type' => 'emi']);

    // Poison all 7 affected cache keys
    Cache::put('dashboard_outstanding_due_s' . $sid, 66666.00, 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['stale']), 300);
    Cache::put('dashboard_month_sale_ids_s' . $sid, collect([55555]), 300);
    Cache::put('dashboard_chart_last7_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_last30_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_weekly_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_monthly_s' . $sid, ['stale' => true], 300);

    // Perform POS EMI sale checkout
    $payload = [
        'customer_id'    => $env['customer']->id,
        'warehouse_id'   => $env['warehouse']->id,
        'cart'           => [[
            'id'    => $env['item']->id,
            'name'  => $env['item']->item_name,
            'price' => 500.00,
            'qty'   => 1,
            'total' => 500.00,
        ]],
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'initial_pay'    => 50.00,
        'duration'       => 3,
        'processing_fee' => 0,
        'start_date'     => date('Y-m-d'),
        'account_id'     => $env['account']->id,
    ];

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert all 7 cache keys were purged
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last7_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last30_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_weekly_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_monthly_s' . $sid))->toBeFalse();

    // Verify next dashboard read reflects new sales without stale cache
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->not->toEqual(66666.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. PurchaseController (PurchaseController.php:471, 918, 1327)
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.4a: PurchaseController::store invalidates dashboard_month_purchases and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    // Poison purchase cache
    Cache::put('dashboard_month_purchases_s' . $sid, 99999.00, 300);

    // Create purchase of 600.00 (2 items @ 300)
    $payload = [
        'supplier_id'    => $env['supplier']->id,
        'warehouse_id'   => $env['warehouse']->id,
        'purchase_date'  => Carbon::today()->format('Y-m-d'),
        'amount_paid'    => 600.00,
        'payment_type'   => 'Cash',
        'account_id'     => $env['account']->id,
        'cart'           => [[
            'item_id'        => $env['item']->id,
            'qty'            => 2,
            'price'          => 300.00,
            'discount'       => 0,
            'tax_type'       => 'Inclusive',
        ]],
    ];

    $response = $this->actingAs($env['user'])->postJson(route('purchase.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert cache forgotten
    expect(Cache::has('dashboard_month_purchases_s' . $sid))->toBeFalse();

    // Next dashboard read reflects the live 600.00
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['this_month_purchases'])->toEqual(600.00);
});

test('GAP 2.4b: PurchaseController::update invalidates dashboard_month_purchases and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    // Create a purchase first
    $purchase = DbPurchase::create([
        'store_id'        => $sid,
        'warehouse_id'    => $env['warehouse']->id,
        'supplier_id'     => $env['supplier']->id,
        'purchase_code'   => 'PO-UP-001',
        'purchase_date'   => Carbon::today()->format('Y-m-d'),
        'subtotal'        => 300.00,
        'grand_total'     => 300.00,
        'paid_amount'     => 300.00,
        'purchase_status' => 'Received',
        'status'          => 1,
    ]);

    DbPurchaseItem::create([
        'store_id'       => $sid,
        'purchase_id'    => $purchase->id,
        'item_id'        => $env['item']->id,
        'purchase_qty'   => 1,
        'price_per_unit' => 300.00,
        'tax_amt'        => 0,
        'tax_type'       => 'Inclusive',
        'discount_amt'   => 0,
        'total_cost'     => 300.00,
    ]);

    // Poison purchase cache with stale figure
    Cache::put('dashboard_month_purchases_s' . $sid, 88888.00, 300);

    // Update purchase to 900.00 (3 items @ 300)
    $payload = [
        'supplier_id'    => $env['supplier']->id,
        'warehouse_id'   => $env['warehouse']->id,
        'purchase_date'  => Carbon::today()->format('Y-m-d'),
        'amount_paid'    => 900.00,
        'payment_type'   => 'Cash',
        'account_id'     => $env['account']->id,
        'items'          => [[
            'item_id'        => $env['item']->id,
            'quantity'       => 3,
            'purchase_price' => 300.00,
            'discount'       => 0,
            'tax_type'       => 'Inclusive',
        ]],
    ];

    $response = $this->actingAs($env['user'])->postJson(route('purchase.update', $purchase->id), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert cache forgotten
    expect(Cache::has('dashboard_month_purchases_s' . $sid))->toBeFalse();

    // Next dashboard read reflects the updated 900.00
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['this_month_purchases'])->toEqual(900.00);
});

test('GAP 2.4c: PurchaseController::destroy invalidates dashboard_month_purchases and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $purchase = DbPurchase::create([
        'store_id'        => $sid,
        'warehouse_id'    => $env['warehouse']->id,
        'supplier_id'     => $env['supplier']->id,
        'purchase_code'   => 'PO-DEL-001',
        'purchase_date'   => Carbon::today()->format('Y-m-d'),
        'subtotal'        => 500.00,
        'grand_total'     => 500.00,
        'paid_amount'     => 500.00,
        'purchase_status' => 'Received',
        'status'          => 1,
    ]);

    DbPurchaseItem::create([
        'store_id'       => $sid,
        'purchase_id'    => $purchase->id,
        'item_id'        => $env['item']->id,
        'purchase_qty'   => 1,
        'price_per_unit' => 500.00,
        'tax_amt'        => 0,
        'tax_type'       => 'Inclusive',
        'discount_amt'   => 0,
        'total_cost'     => 500.00,
    ]);

    // Poison cache
    Cache::put('dashboard_month_purchases_s' . $sid, 77777.00, 300);

    // Delete purchase
    $response = $this->actingAs($env['user'])->deleteJson(route('purchase.delete', $purchase->id));
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert cache forgotten
    expect(Cache::has('dashboard_month_purchases_s' . $sid))->toBeFalse();

    // Next dashboard read reflects 0.00 purchases (clean deletion)
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['this_month_purchases'])->toEqual(0.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. SaleController (SaleController.php:362, 784-785, 862-863)
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.5a: SaleController::deleteSale invalidates full dashboard cache and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $sale = DbSale::create([
        'store_id'       => $sid,
        'warehouse_id'   => $env['warehouse']->id,
        'customer_id'    => $env['customer']->id,
        'sales_code'     => 'SL-DEL-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 400.00,
        'grand_total'    => 400.00,
        'paid_amount'    => 100.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbSaleItem::create([
        'store_id'       => $sid,
        'sales_id'       => $sale->id,
        'item_id'        => $env['item']->id,
        'sales_qty'      => 1,
        'price_per_unit' => 400.00,
        'tax_amt'        => 0,
        'tax_type'       => 'Inclusive',
        'discount_amt'   => 0,
        'total_cost'     => 400.00,
    ]);

    // Poison dashboard keys
    Cache::put('dashboard_outstanding_due_s' . $sid, 99999.00, 300);
    Cache::put('dashboard_month_sale_ids_s' . $sid, collect([$sale->id]), 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['stale']), 300);

    // Delete sale
    $response = $this->actingAs($env['user'])->delete(route('sales.delete', $sale->id));
    $response->assertRedirect();

    // Assert cache forgotten
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();

    // Read dashboard: outstanding due must be 0.00
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(0.00);
});

test('GAP 2.5b: SaleController::receivePayment invalidates outstanding & customer due caches and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $sale = DbSale::create([
        'store_id'       => $sid,
        'warehouse_id'   => $env['warehouse']->id,
        'customer_id'    => $env['customer']->id,
        'sales_code'     => 'SL-PAY-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 200.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Poison keys
    Cache::put('dashboard_outstanding_due_s' . $sid, 800.00, 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['old_due' => 800.00]), 300);

    // Record additional payment of 300.00 (remaining due will be 500.00)
    $response = $this->actingAs($env['user'])->post(route('sales.payments.store'), [
        'sales_id'     => $sale->id,
        'amount'       => 300.00,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'account_id'   => $env['account']->id,
    ]);

    $response->assertRedirect(route('sales.list'));

    // Assert cache forgotten
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();

    // Verify next dashboard read reflects reduced due of 500.00
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(500.00);
});

test('GAP 2.5c: SaleController::destroyPayment invalidates outstanding & customer due caches and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $sale = DbSale::create([
        'store_id'       => $sid,
        'warehouse_id'   => $env['warehouse']->id,
        'customer_id'    => $env['customer']->id,
        'sales_code'     => 'SL-REVPAY-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 600.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    $payment = DbSalePayment::create([
        'store_id'     => $sid,
        'sales_id'     => $sale->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment'      => 400.00,
        'account_id'   => $env['account']->id,
        'status'       => 1,
    ]);

    AcTransaction::create([
        'store_id'             => $sid,
        'transaction_date'     => Carbon::today()->format('Y-m-d'),
        'transaction_type'     => 'SALES PAYMENT',
        'credit_account_id'    => $env['account']->id,
        'credit_amt'           => 400.00,
        'debit_amt'            => 0.00,
        'ref_salespayments_id' => $payment->id,
        'status'               => 1,
    ]);

    // Due before reversal is 400.00 (1000 - 600).
    // Poison keys with stale due
    Cache::put('dashboard_outstanding_due_s' . $sid, 400.00, 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['old_due' => 400.00]), 300);

    // Delete payment: reversing 400.00 payment => paid becomes 200.00 => due becomes 800.00
    $response = $this->actingAs($env['user'])->delete(route('sales.payments.destroy', $payment->id));
    $response->assertRedirect();

    // Assert cache forgotten
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();

    // Next dashboard read reflects increased due of 800.00
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(800.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. SalesReturnController::store (SalesReturnController.php:483-489)
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.6: SalesReturnController::store invalidates dashboard caches and refreshes live stats', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $sale = DbSale::create([
        'store_id'       => $sid,
        'warehouse_id'   => $env['warehouse']->id,
        'customer_id'    => $env['customer']->id,
        'sales_code'     => 'SL-RET-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 400.00, // 600 due
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbSaleItem::create([
        'store_id'       => $sid,
        'sales_id'       => $sale->id,
        'item_id'        => $env['item']->id,
        'sales_qty'      => 2,
        'price_per_unit' => 500.00,
        'tax_amt'        => 0,
        'tax_type'       => 'Inclusive',
        'discount_amt'   => 0,
        'total_cost'     => 1000.00,
    ]);

    // Poison all 7 affected cache keys
    Cache::put('dashboard_outstanding_due_s' . $sid, 600.00, 300);
    Cache::put('dashboard_customers_due_s' . $sid, collect(['old_due' => 600.00]), 300);
    Cache::put('dashboard_month_sale_ids_s' . $sid, collect([$sale->id]), 300);
    Cache::put('dashboard_chart_last7_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_last30_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_weekly_s' . $sid, ['stale' => true], 300);
    Cache::put('dashboard_chart_monthly_s' . $sid, ['stale' => true], 300);

    // Return 1 item (500.00 value offsets 500 of the 600 due => new due is 100.00)
    $payload = [
        'sales_id'     => $sale->id,
        'return_date'  => Carbon::today()->format('Y-m-d'),
        'paid_amount'  => 0.00, // zero cash refund; entire return offsets due
        'items'        => [[
            'item_id'        => $env['item']->id,
            'return_qty'     => 1,
            'price_per_unit' => 500.00,
            'tax_amt'        => 0,
            'discount_amt'   => 0,
            'total_cost'     => 500.00,
        ]],
    ];

    $response = $this->actingAs($env['user'])->postJson(route('sales.return.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    // Assert all 7 cache keys were forgotten
    expect(Cache::has('dashboard_outstanding_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_customers_due_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last7_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_last30_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_weekly_s' . $sid))->toBeFalse();
    expect(Cache::has('dashboard_chart_monthly_s' . $sid))->toBeFalse();

    // Verify next dashboard read reflects reduced due of 100.00 (1000 - 500 return - 400 paid)
    $res = $this->actingAs($env['user'])->get(route('dashboard'));
    $res->assertOk();
    $stats = $res->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(100.00);
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. RuleResolverService::clearCache
// ─────────────────────────────────────────────────────────────────────────────
test('GAP 2.7: RuleResolverService::clearCache purges store-scoped rule cache and next resolve reflects DB changes', function () {
    $env = setupFreshnessTestEnv(1);
    $sid = $env['store']->id;

    $template = DbSmsTemplate::create([
        'store_id'        => $sid,
        'template_name'   => 'Template Freshness',
        'category'        => 'Transactional',
        'message_type'    => 'transactional',
        'content'         => 'Hello {customer_name}',
        'variables_used'  => ['customer_name'],
        'status'          => 1,
    ]);

    $rule1 = SmsAutoRule::create([
        'store_id'     => $sid,
        'rule_name'    => 'Rule Initial',
        'event_type'   => 'InvoiceCreated',
        'event_source' => 'invoice',
        'template_id'  => $template->id,
        'trigger_time' => 'immediate',
        'is_active'    => true,
    ]);

    // Resolve as acting user; primes cache
    $this->actingAs($env['user']);
    $initial = RuleResolverService::resolve('InvoiceCreated');
    expect($initial->pluck('rule_name')->all())->toContain('Rule Initial');
    expect(Cache::has("sms_rules_InvoiceCreated_s{$sid}"))->toBeTrue();

    // Create a new rule directly in DB
    $rule2 = SmsAutoRule::create([
        'store_id'     => $sid,
        'rule_name'    => 'Rule Added Later',
        'event_type'   => 'InvoiceCreated',
        'event_source' => 'invoice',
        'template_id'  => $template->id,
        'trigger_time' => 'immediate',
        'is_active'    => true,
    ]);

    // Without clearing cache, resolve would return old cached collection
    $stillCached = RuleResolverService::resolve('InvoiceCreated');
    expect($stillCached->pluck('rule_name')->all())->not->toContain('Rule Added Later');

    // Call clearCache
    RuleResolverService::clearCache('InvoiceCreated', $sid);
    expect(Cache::has("sms_rules_InvoiceCreated_s{$sid}"))->toBeFalse();

    // Next resolve fetches fresh rules from DB
    $fresh = RuleResolverService::resolve('InvoiceCreated');
    expect($fresh->pluck('rule_name')->all())->toContain('Rule Initial');
    expect($fresh->pluck('rule_name')->all())->toContain('Rule Added Later');
    expect(Cache::has("sms_rules_InvoiceCreated_s{$sid}"))->toBeTrue();
});
