<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbPurchase;
use Carbon\Carbon;

// -----------------------------------------------------------------------
// Helper: create an authenticated Super Admin user (mirrors SalesSummaryReportTest)
// -----------------------------------------------------------------------
function createDashboardSuperAdmin(): User
{
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Test Store',
        'status'     => 1,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id'  => 1,
        'role_name' => 'Super Admin',
        'status'    => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id'    => 1,
        'permissions' => ['sales_add', 'purchase_add', 'items_add', 'reports_view'],
    ]);

    return User::factory()->create([
        'store_id'  => 1,
        'role_id'   => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

// -----------------------------------------------------------------------
// TEST 1: Guest users are redirected to /login
// -----------------------------------------------------------------------
test('unauthenticated users cannot access the dashboard', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/login');
});

// -----------------------------------------------------------------------
// TEST 2: Authenticated users get HTTP 200 and the dashboard view
//         with all required view variables present
// -----------------------------------------------------------------------
test('authenticated user can access dashboard and receives all required view data', function () {
    $user = createDashboardSuperAdmin();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewIs('dashboard');

    // All key variables passed to the view
    $response->assertViewHas('stats');
    $response->assertViewHas('chartData');
    $response->assertViewHas('lowStockItems');
    $response->assertViewHas('topProducts');
    $response->assertViewHas('recentTransactions');
    $response->assertViewHas('paymentMethods');
    $response->assertViewHas('customersWithDue');

    // Verify stats array has all 8 expected KPI keys
    $stats = $response->viewData('stats');
    expect($stats)->toHaveKeys([
        'today_sales',
        'today_orders',
        'today_net_profit',
        'total_outstanding_due',
        'this_month_sales',
        'last_month_sales',
        'month_change_percent',
        'this_month_purchases',
    ]);

    // chartData must have labels + values arrays
    $chartData = $response->viewData('chartData');
    expect($chartData)->toHaveKeys(['labels', 'values']);
    expect($chartData['labels'])->toBeArray()->toHaveCount(7);
    expect($chartData['values'])->toBeArray()->toHaveCount(7);
});

// -----------------------------------------------------------------------
// TEST 3: dashboard.data AJAX endpoint returns correct JSON structure
//         and accurate aggregations for both 'last7' and 'last30' periods
// -----------------------------------------------------------------------
test('dashboard data endpoint returns valid chart JSON for last7 and last30 periods', function () {
    $user = createDashboardSuperAdmin();

    // --- last7 ---
    $res7 = $this->actingAs($user)->getJson(route('dashboard.data', ['period' => 'last7']));
    $res7->assertOk();
    $res7->assertJsonStructure(['labels', 'values']);

    $data7 = $res7->json();
    expect($data7['labels'])->toBeArray()->toHaveCount(7);
    expect($data7['values'])->toBeArray()->toHaveCount(7);

    // --- last30 ---
    $res30 = $this->actingAs($user)->getJson(route('dashboard.data', ['period' => 'last30']));
    $res30->assertOk();
    $res30->assertJsonStructure(['labels', 'values']);

    $data30 = $res30->json();
    expect($data30['labels'])->toBeArray()->toHaveCount(30);
    expect($data30['values'])->toBeArray()->toHaveCount(30);
});

// -----------------------------------------------------------------------
// TEST 4: KPI values are numerically correct when test sale data exists
// -----------------------------------------------------------------------
test('dashboard KPI values reflect real sale data accurately', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Dashboard Test Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Dashboard Test Customer',
        'customer_code' => 'CUST-DASH-001',
        'mobile'        => '01700000001',
        'status'        => 1,
    ]);

    $category = DbCategory::firstOrCreate(
        ['category_name' => 'Test Category'],
        ['status' => 1, 'store_id' => 1]
    );

    $item = DbItem::create([
        'store_id' => 1,
        'item_name'      => 'Dashboard Test Item',
        'item_code'      => 'DASH-ITEM-01',
        'category_id'    => $category->id,
        'purchase_price' => 60.00,
        'sales_price'    => 120.00,
        'stock'          => 50,
        'alert_qty'      => 5,
        'status'         => 1,
    ]);

    $today = Carbon::today()->format('Y-m-d');

    // Create today's sale: 2 × 120.00 = 240.00, paid 120.00, due 120.00
    $sale = DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-DASH-001',
        'sales_date'     => $today,
        'sales_status'   => 'Final',
        'subtotal'       => 240.00,
        'grand_total'    => 240.00,
        'paid_amount'    => 120.00,
        'payment_status' => 'Partial',
        'status'         => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id'       => $sale->id,
        'item_id'        => $item->id,
        'sales_qty'      => 2,
        'price_per_unit' => 120.00,
        'total_cost'     => 240.00,
        'purchase_price' => 60.00,
        'discount_amt'   => 0.00,
        'tax_amt'        => 0.00,
        'status'         => 1,
    ]);

    DbSalePayment::create([
        'store_id' => 1,
        'sales_id'     => $sale->id,
        'customer_id'  => $customer->id,
        'payment_date' => $today,
        'payment_type' => 'Cash',
        'payment'      => 120.00,
        'status'       => 1,
    ]);

    // Clear caches so fresh data is used
    \Illuminate\Support\Facades\Cache::flush();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();

    $stats = $response->viewData('stats');

    // today_sales should include our 240.00
    expect((float) $stats['today_sales'])->toBeGreaterThanOrEqual(240.0);

    // today_orders should be at least 1
    expect((int) $stats['today_orders'])->toBeGreaterThanOrEqual(1);

    // total_outstanding_due should be positive (at least our 120.00 partial)
    expect((float) $stats['total_outstanding_due'])->toBeGreaterThan(0);

    // recentTransactions should include our sale
    $txns = $response->viewData('recentTransactions');
    expect($txns->where('id', $sale->id)->count())->toBe(1);
});

// -----------------------------------------------------------------------
// TEST 5: Sale with returns correctly offsets due in Outstanding Due and Customers with Due
// -----------------------------------------------------------------------
test('sale with return offsets due in outstanding due and customers with due', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Return Test Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Return Test Customer',
        'customer_code' => 'CUST-RET-001',
        'mobile'        => '01700000002',
        'status'        => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name'      => 'Return Test Item',
        'item_code'      => 'RET-ITEM-01',
        'purchase_price' => 50.00,
        'sales_price'    => 100.00,
        'stock'          => 50,
        'status'         => 1,
    ]);

    $today = Carbon::today()->format('Y-m-d');

    // Sale: 500 total, 200 paid -> Raw Due = 300
    $sale = DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-RET-001',
        'sales_date'     => $today,
        'sales_status'   => 'Final',
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 200.00,
        'payment_status' => 'Partial',
        'status'         => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id'       => $sale->id,
        'item_id'        => $item->id,
        'sales_qty'      => 5,
        'price_per_unit' => 100.00,
        'total_cost'     => 500.00,
        'purchase_price' => 50.00,
        'discount_amt'   => 0.00,
        'tax_amt'        => 0.00,
        'status'         => 1,
    ]);

    // Return: 200 total, 0 refunded (offsets due) -> Net Due = (500-200)-(200-0) = 100
    \App\Models\DbSalesReturn::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_id'       => $sale->id,
        'return_code'    => 'SR-TEST-001',
        'return_date'    => $today,
        'subtotal'       => 200.00,
        'grand_total'    => 200.00,
        'paid_amount'    => 0.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    \Illuminate\Support\Facades\Cache::flush();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $stats = $response->viewData('stats');
    $customersWithDue = $response->viewData('customersWithDue');

    // Total outstanding due should reflect net due (100.00), not raw due (300.00)
    expect((float) $stats['total_outstanding_due'])->toEqual(100.0);

    // Customer with due should have total_due = 100.00
    $custEntry = collect($customersWithDue)->firstWhere('customer_id', $customer->id);
    expect($custEntry)->not->toBeNull();
    expect((float) $custEntry->total_due)->toEqual(100.0);
});

// -----------------------------------------------------------------------
// TEST 6: Invoice-level discount and coupon properly reduces today's net profit
// -----------------------------------------------------------------------
test('invoice level discount and coupon reduces today net profit', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Discount Test Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Discount Test Customer',
        'customer_code' => 'CUST-DISC-001',
        'mobile'        => '01700000003',
        'status'        => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name'      => 'Discount Test Item',
        'item_code'      => 'DISC-ITEM-01',
        'purchase_price' => 60.00,
        'sales_price'    => 100.00,
        'stock'          => 50,
        'status'         => 1,
    ]);

    $today = Carbon::today()->format('Y-m-d');

    // Sale: 1 item @ 100, purchase_price = 60. COGS = 60.
    // Invoice discount = 15, coupon = 5.
    // Total revenue = 100 - 20 = 80.
    // Gross Profit = 80 - 60 = 20. Net Profit = 20.
    $sale = DbSale::create([
        'store_id'                => 1,
        'warehouse_id'            => $warehouse->id,
        'customer_id'             => $customer->id,
        'sales_code'              => 'SA-DISC-001',
        'sales_date'              => $today,
        'sales_status'            => 'Final',
        'subtotal'                => 100.00,
        'tot_discount_to_all_amt' => 15.00,
        'coupon_amt'              => 5.00,
        'grand_total'             => 80.00,
        'paid_amount'             => 80.00,
        'payment_status'          => 'Paid',
        'status'                  => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id'       => $sale->id,
        'item_id'        => $item->id,
        'sales_qty'      => 1,
        'price_per_unit' => 100.00,
        'total_cost'     => 100.00,
        'purchase_price' => 60.00,
        'discount_amt'   => 0.00,
        'tax_amt'        => 0.00,
        'status'         => 1,
    ]);

    \Illuminate\Support\Facades\Cache::flush();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $stats = $response->viewData('stats');
    // Profit must be 20.00 (80 revenue - 60 COGS), not 40.00 (100 - 60)
    expect((float) $stats['today_net_profit'])->toEqual(20.0);
});

// -----------------------------------------------------------------------
// TEST 7: Inactive (status=0) or Quotation sales are excluded from all KPIs
// -----------------------------------------------------------------------
test('inactive and quotation sales are excluded from dashboard KPIs', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Status Test Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Status Test Customer',
        'customer_code' => 'CUST-STAT-001',
        'mobile'        => '01700000004',
        'status'        => 1,
    ]);

    $today = Carbon::today()->format('Y-m-d');

    // Quotation sale (should be ignored)
    DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'QT-001',
        'sales_date'     => $today,
        'sales_status'   => 'Quotation',
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 0.00,
        'payment_status' => 'Unpaid',
        'status'         => 1,
    ]);

    // Inactive sale (status=0)
    DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-DEL-001',
        'sales_date'     => $today,
        'sales_status'   => 'Final',
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 0.00,
        'payment_status' => 'Unpaid',
        'status'         => 0,
    ]);

    \Illuminate\Support\Facades\Cache::flush();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $stats = $response->viewData('stats');
    $recentTransactions = $response->viewData('recentTransactions');

    expect((float) $stats['today_sales'])->toEqual(0.0);
    expect((int) $stats['today_orders'])->toEqual(0);
    expect((float) $stats['total_outstanding_due'])->toEqual(0.0);
    expect($recentTransactions->count())->toEqual(0);
});

// -----------------------------------------------------------------------
// TEST 8: Trend chart query executes single grouped query and matches direct sum
// -----------------------------------------------------------------------
test('trend chart endpoint uses single grouped query and returns accurate period values', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Trend Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Trend Customer',
        'customer_code' => 'CUST-TRN-001',
        'mobile'        => '01700000005',
        'status'        => 1,
    ]);

    // Create sales across multiple dates
    $d1 = Carbon::today()->subDays(2)->format('Y-m-d');
    $d2 = Carbon::today()->subDays(5)->format('Y-m-d');

    DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-TRN-001',
        'sales_date'     => $d1,
        'sales_status'   => 'Final',
        'subtotal'       => 300.00,
        'grand_total'    => 300.00,
        'paid_amount'    => 300.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-TRN-002',
        'sales_date'     => $d2,
        'sales_status'   => 'Final',
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 500.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    \Illuminate\Support\Facades\Cache::flush();
    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $res = $this->actingAs($user)->getJson(route('dashboard.data', ['period' => 'last7']));
    $res->assertOk();

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    // Verify only 1 sales query was fired instead of 7
    $salesQueries = collect($queries)->filter(fn($q) => str_contains($q['query'], 'db_sales'));
    expect($salesQueries->count())->toEqual(1);

    $data = $res->json();
    expect(array_sum($data['values']))->toEqual(800.0);
});

// -----------------------------------------------------------------------
// TEST 9: Payment receipt invalidates dashboard due cache
// -----------------------------------------------------------------------
test('receiving payment invalidates dashboard outstanding due and customers due cache', function () {
    $user = createDashboardSuperAdmin();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Cache Test Warehouse',
        'status'         => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Cache Test Customer',
        'customer_code' => 'CUST-CCH-001',
        'mobile'        => '01700000006',
        'status'        => 1,
    ]);

    $account = \App\Models\AcAccount::create([
        'store_id'     => 1,
        'account_code' => 'ACC-CCH-01',
        'account_name' => 'Cash Account',
        'balance'      => 0,
        'status'       => 1,
    ]);

    $sale = DbSale::create([
        'store_id'       => 1,
        'warehouse_id'   => $warehouse->id,
        'customer_id'    => $customer->id,
        'sales_code'     => 'SA-CCH-001',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'sales_status'   => 'Final',
        'subtotal'       => 400.00,
        'grand_total'    => 400.00,
        'paid_amount'    => 100.00,
        'payment_status' => 'Partial',
        'status'         => 1,
    ]);

    // Prime the cache
    \Illuminate\Support\Facades\Cache::put('dashboard_outstanding_due', 300.0, 300);
    \Illuminate\Support\Facades\Cache::put('dashboard_customers_due', ['dummy'], 300);

    expect(\Illuminate\Support\Facades\Cache::has('dashboard_outstanding_due'))->toBeTrue();

    // Store payment
    $response = $this->actingAs($user)->post(route('sales.payments.store'), [
        'sales_id'     => $sale->id,
        'amount'       => 300.00,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'account_id'   => $account->id,
    ]);

    $response->assertRedirect();

    // Assert cache keys were invalidated
    expect(\Illuminate\Support\Facades\Cache::has('dashboard_outstanding_due'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Cache::has('dashboard_customers_due'))->toBeFalse();

    // On next dashboard load, fresh outstanding due reflects full settlement (0.00)
    $dashRes = $this->actingAs($user)->get(route('dashboard'));
    $dashRes->assertOk();
    $stats = $dashRes->viewData('stats');
    expect((float) $stats['total_outstanding_due'])->toEqual(0.0);
});
