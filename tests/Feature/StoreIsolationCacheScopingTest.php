<?php

use App\Models\AcAccount;
use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\SmsAutoRule;
use App\Models\User;
use App\SMS\Services\RuleResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

function setupCacheScopingStores() {
    Cache::flush();

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
        'role_name' => 'Super Admin A',
        'status'    => 1,
    ]);

    $roleB = DbRole::firstOrCreate(['id' => 2], [
        'store_id'  => $storeB->id,
        'role_name' => 'Super Admin B',
        'status'    => 1,
    ]);

    $allPerms = [
        'dashboard_view', 'sales_view', 'sales_add', 'sales_edit',
        'profit_report', 'sales_summary_report', 'cash_flow_report', 'cash_reconciliation_report'
    ];

    DbPermission::firstOrCreate(['role_id' => $roleA->id], [
        'store_id'    => $storeA->id,
        'permissions' => $allPerms,
    ]);

    DbPermission::firstOrCreate(['role_id' => $roleB->id], [
        'store_id'    => $storeB->id,
        'permissions' => $allPerms,
    ]);

    $userA = User::factory()->create([
        'store_id'  => $storeA->id,
        'role_id'   => $roleA->id,
        'role_name' => 'Super Admin A',
    ]);

    $userB = User::factory()->create([
        'store_id'  => $storeB->id,
        'role_id'   => $roleB->id,
        'role_name' => 'Super Admin B',
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

test('Cache isolation: dashboard_outstanding_due is store-scoped and Store B does not see Store A cache', function () {
    $env = setupCacheScopingStores();

    $customerB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Customer B',
        'mobile'        => '01720000000',
        'status'        => 1,
    ]);

    // Store B has a real sale with due 250.00
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-CACHE-1',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 250.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Populate Store A's cache with poisoned dummy value
    Cache::put('dashboard_outstanding_due_s' . $env['storeA']->id, 99999.00, 300);

    // Request as Store B
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();
    $statsB = $resB->viewData('stats');

    expect((float) $statsB['total_outstanding_due'])->toEqual(250.00);
    expect((float) $statsB['total_outstanding_due'])->not->toEqual(99999.00);
});

test('Cache isolation: dashboard_customers_due is store-scoped and Store B does not see Store A cache', function () {
    $env = setupCacheScopingStores();

    $customerB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Legit Beta Customer',
        'mobile'        => '01720000001',
        'status'        => 1,
    ]);

    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'customer_id'    => $customerB->id,
        'sales_code'     => 'SL-B-CACHE-2',
        'sales_date'     => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 300.00,
        'grand_total'    => 300.00,
        'paid_amount'    => 100.00,
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Populate Store A's cache with poisoned dummy data
    Cache::put('dashboard_customers_due_s' . $env['storeA']->id, collect([
        (object) [
            'customer_id'   => 9999,
            'customer_name' => 'Ghost Store A Customer',
            'mobile'        => '0000000000',
            'orders_count'  => 1,
            'total_due'     => 99999.00,
        ]
    ]), 300);

    // Request as Store B
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();
    $customersB = collect($resB->viewData('customersWithDue'));

    expect($customersB->pluck('customer_name')->all())->toContain('Legit Beta Customer');
    expect($customersB->pluck('customer_name')->all())->not->toContain('Ghost Store A Customer');
});

test('Cache isolation: dashboard_month_sale_ids is store-scoped and Store B does not see Store A cache', function () {
    $env = setupCacheScopingStores();

    // Populate Store A's cache with dummy sale IDs that don't belong to Store B
    Cache::put('dashboard_month_sale_ids_s' . $env['storeA']->id, collect([888888, 999999]), 300);

    // Acting as Store B, dashboard computes Store B's own month sales ids
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();

    expect(Cache::has('dashboard_month_sale_ids_s' . $env['storeB']->id))->toBeTrue();
    $cachedB = Cache::get('dashboard_month_sale_ids_s' . $env['storeB']->id);
    expect($cachedB->all())->not->toContain(888888);
    expect($cachedB->all())->not->toContain(999999);
});

test('Cache isolation: dashboard_month_purchases is store-scoped and Store B does not see Store A cache', function () {
    $env = setupCacheScopingStores();

    DbPurchase::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'supplier_id'    => 1,
        'purchase_code'  => 'PO-B-CACHE-1',
        'purchase_date'  => Carbon::today()->format('Y-m-d'),
        'subtotal'       => 450.00,
        'grand_total'    => 450.00,
        'paid_amount'    => 450.00,
        'purchase_status'=> 'Received',
        'status'         => 1,
    ]);

    // Populate Store A's cache with dummy purchase total
    Cache::put('dashboard_month_purchases_s' . $env['storeA']->id, 77777.00, 300);

    // Request as Store B
    $resB = $this->actingAs($env['userB'])->get(route('dashboard'));
    $resB->assertOk();
    $statsB = $resB->viewData('stats');

    expect((float) $statsB['this_month_purchases'])->toEqual(450.00);
    expect((float) $statsB['this_month_purchases'])->not->toEqual(77777.00);
});

test('Cache isolation: dashboard_chart_<period> is store-scoped and Store B does not see Store A cache', function () {
    $env = setupCacheScopingStores();

    // Populate Store A's cache with poisoned chart data
    Cache::put('dashboard_chart_last7_s' . $env['storeA']->id, [
        'labels' => ['Fake Date A'],
        'values' => [99999.00],
    ], 300);

    // Request chart as Store B
    $resB = $this->actingAs($env['userB'])->getJson(route('dashboard.data', ['period' => 'last7']));
    $resB->assertOk();
    $dataB = $resB->json();

    expect($dataB['labels'])->not->toContain('Fake Date A');
    expect($dataB['values'])->not->toContain(99999.00);
});

test('Cache isolation: db_categories_list is store-scoped across SaleController and ReportController', function () {
    $env = setupCacheScopingStores();

    $catB = DbCategory::create([
        'store_id'      => $env['storeB']->id,
        'category_name' => 'Beta Electronics Category',
        'category_code' => 'CAT-B-01',
        'status'        => 1,
    ]);

    // Populate Store A's cache with dummy category
    Cache::put('db_categories_list_s' . $env['storeA']->id, collect([
        (object) ['id' => 9999, 'category_name' => 'Ghost Category A']
    ]), 3600);

    // Test in SaleController (sales/add)
    $resSaleB = $this->actingAs($env['userB'])->get(route('sales.add'));
    $resSaleB->assertOk();
    $categoriesSaleB = collect($resSaleB->viewData('categories'));
    expect($categoriesSaleB->pluck('category_name')->all())->toContain('Beta Electronics Category');
    expect($categoriesSaleB->pluck('category_name')->all())->not->toContain('Ghost Category A');

    // Test in ReportController (reports/sales-summary)
    $resReportB = $this->actingAs($env['userB'])->get(route('reports.sales_summary'));
    $resReportB->assertOk();
    $categoriesReportB = collect($resReportB->viewData('categories'));
    expect($categoriesReportB->pluck('category_name')->all())->toContain('Beta Electronics Category');
    expect($categoriesReportB->pluck('category_name')->all())->not->toContain('Ghost Category A');
});

test('Cache isolation: db_brands_list is store-scoped in SaleController', function () {
    $env = setupCacheScopingStores();

    $brandB = DbBrand::create([
        'store_id'   => $env['storeB']->id,
        'brand_name' => 'Beta HighTech Brand',
        'brand_code' => 'BRD-B-01',
        'status'     => 1,
    ]);

    // Populate Store A's cache with dummy brand
    Cache::put('db_brands_list_s' . $env['storeA']->id, collect([
        (object) ['id' => 8888, 'brand_name' => 'Ghost Brand A']
    ]), 3600);

    // Test in SaleController
    $resB = $this->actingAs($env['userB'])->get(route('sales.add'));
    $resB->assertOk();
    $brandsB = collect($resB->viewData('brands'));

    expect($brandsB->pluck('brand_name')->all())->toContain('Beta HighTech Brand');
    expect($brandsB->pluck('brand_name')->all())->not->toContain('Ghost Brand A');
});

test('Cache isolation: db_customers_summary_list is store-scoped in ReportController', function () {
    $env = setupCacheScopingStores();

    $custB = DbCustomer::create([
        'store_id'      => $env['storeB']->id,
        'customer_name' => 'Beta Summary Customer',
        'customer_code' => 'CUST-B-99',
        'mobile'        => '01799000000',
        'status'        => 1,
    ]);

    // Populate Store A's cache with dummy customer
    Cache::put('db_customers_summary_list_s' . $env['storeA']->id, collect([
        (object) ['id' => 7777, 'customer_name' => 'Ghost Customer A', 'customer_code' => 'CUST-A-00']
    ]), 3600);

    $resB = $this->actingAs($env['userB'])->get(route('reports.sales_summary'));
    $resB->assertOk();
    $customersB = collect($resB->viewData('customers'));

    expect($customersB->pluck('customer_name')->all())->toContain('Beta Summary Customer');
    expect($customersB->pluck('customer_name')->all())->not->toContain('Ghost Customer A');
});

test('Cache isolation: db_accounts_list is store-scoped in ReportController', function () {
    $env = setupCacheScopingStores();

    $accB = AcAccount::create([
        'store_id'     => $env['storeB']->id,
        'account_name' => 'Beta Petty Cash',
        'account_code' => 'ACC-B-01',
        'status'       => 1,
    ]);

    // Populate Store A's cache with dummy account
    Cache::put('db_accounts_list_s' . $env['storeA']->id, collect([
        (object) ['id' => 6666, 'account_name' => 'Ghost Account A', 'account_code' => 'ACC-A-00']
    ]), 3600);

    $resB = $this->actingAs($env['userB'])->get(route('reports.cash_flow'));
    $resB->assertOk();
    $accountsB = collect($resB->viewData('accounts'));

    expect($accountsB->pluck('account_name')->all())->toContain('Beta Petty Cash');
    expect($accountsB->pluck('account_name')->all())->not->toContain('Ghost Account A');
});

test('Cache isolation: sms_rules_{eventType} is store-scoped in RuleResolverService', function () {
    $env = setupCacheScopingStores();

    $templateB = DbSmsTemplate::create([
        'store_id'        => $env['storeB']->id,
        'template_name'   => 'Template Beta',
        'category'        => 'Transactional',
        'message_type'    => 'transactional',
        'content'         => 'Hello Beta {customer_name}',
        'variables_used'  => ['customer_name'],
        'status'          => 1,
    ]);

    $ruleB = SmsAutoRule::create([
        'rule_name'   => 'Invoice Rule Beta',
        'event_type'  => 'InvoiceCreated',
        'event_source'=> 'invoice',
        'template_id' => $templateB->id,
        'trigger_time'=> 'immediate',
        'is_active'   => true,
    ]);

    // Populate Store A's cache with dummy rule
    Cache::put('sms_rules_InvoiceCreated_s' . $env['storeA']->id, collect([
        (object) ['id' => 5555, 'rule_name' => 'Ghost SMS Rule A']
    ]), 3600);

    // Resolve rules acting as Store B
    $this->actingAs($env['userB']);
    $resolvedB = RuleResolverService::resolve('InvoiceCreated');

    expect($resolvedB->pluck('rule_name')->all())->toContain('Invoice Rule Beta');
    expect($resolvedB->pluck('rule_name')->all())->not->toContain('Ghost SMS Rule A');
});
