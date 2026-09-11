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
use App\Models\DbSalePayment;
use App\Models\AcAccount;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

function getSyncTestUser(): User {
    store_settings(true);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Sync Test Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::updateOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['reports_view', 'sales_view', 'pos', 'accounts_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. Dashboard and P&L Report both calculate exact matching net profit with invoice discounts and coupons', function () {
    $user = getSyncTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Discount Tester',
        'customer_code' => 'CUST-DSC-001',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Sync WH',
        'status' => 1,
    ]);

    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Electronics', 'status' => 1]);

    // Item: Purchase Price (COGS) = 65,000, Sales Price = 72,000
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Flagship Smartphone',
        'item_code' => 'ITM-PHN-01',
        'sku' => 'SKU-PHN-01',
        'category_id' => $category->id,
        'purchase_price' => 65000.00,
        'sales_price' => 72000.00,
        'stock' => 10,
        'status' => 1,
    ]);

    // Sale: 72,000 subtotal, 7,200 invoice discount (10%), 14,400 coupon discount (20%) -> Grand Total = 50,400
    $sale = DbSale::create([
        'store_id' => 1,
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_code' => 'SA-SYNC-001',
        'sales_date' => $today,
        'subtotal' => 72000.00,
        'discount_to_all_input' => 10,
        'discount_to_all_type' => 'percent',
        'tot_discount_to_all_amt' => 7200.00,
        'coupon_amt' => 14400.00,
        'round_off' => 0.00,
        'grand_total' => 50400.00,
        'paid_amount' => 50400.00,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
        'status' => 1,
        'sales_status' => 'Final',
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 1,
        'price_per_unit' => 72000.00,
        'total_cost' => 72000.00,
        'purchase_price' => 65000.00,
        'discount_amt' => 0.00,
        'tax_amt' => 0.00,
        'status' => 1,
    ]);

    // Clear dashboard cache
    DashboardController::clearDashboardCache();

    // 1. Check Dashboard Controller
    $dashCtrl = new DashboardController();
    $dashView = $dashCtrl->index();
    $dashStats = $dashView->getData()['stats'];

    // Expected profit: Revenue (50,400) - COGS (65,000) = -14,600.00
    expect((float)$dashStats['today_sales'])->toBe(50400.00);
    expect((float)$dashStats['today_net_profit'])->toBe(-14600.00);

    // 2. Check P&L Report Controller
    $repCtrl = new ReportController();
    $req = new Request(['start_date' => $today, 'end_date' => $today]);
    $plRes = $repCtrl->getProfitLossData($req);
    $plData = $plRes->getData(true)['data'];

    $cleanFloat = fn($val) => (float) str_replace(',', '', (string)$val);

    expect($cleanFloat($plData['sales']['grandTotal']))->toBe(50400.00);
    expect($cleanFloat($plData['summary']['grossProfit']))->toBe(-14600.00);
    expect($cleanFloat($plData['summary']['netProfit']))->toBe(-14600.00);

    // Exact equality check between Dashboard and P&L Report
    expect((float)$dashStats['today_net_profit'])->toBe($cleanFloat($plData['summary']['netProfit']));
});

test('2. Quick Add Customer endpoint successfully stores customer and returns valid JSON for POS', function () {
    $user = getSyncTestUser();

    $mobile = '017' . rand(10000000, 99999999);
    $response = $this->actingAs($user)->postJson(route('contacts.customers.quick-store'), [
        'customer_name' => 'Quick Registered POS Customer',
        'mobile' => $mobile,
        'customer_type' => 'regular',
        'email' => 'pos_quick_' . rand(100, 999) . '@example.com',
    ]);

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['success'])->toBeTrue();
    expect($data['customer']['customer_name'])->toBe('Quick Registered POS Customer');
    expect($data['customer']['mobile'])->toBe($mobile);
});

test('3. POS page renders without syntax errors and contains complete customer and item state', function () {
    $user = getSyncTestUser();

    $response = $this->actingAs($user)->get(route('sales.pos'));
    $response->assertStatus(200);

    // Check critical Alpine elements and customer components
    $response->assertSee('filteredCustomers()', false);
    $response->assertSee('selectCustomer(customer)', false);
    $response->assertSee('customerModalOpen', false);
    $response->assertSee('submitQuickCustomer()', false);
    $response->assertSee('Create New Customer', false);
});

test('4. End-to-end POS checkout flow with discounts and immediate dashboard cache reflection', function () {
    $user = getSyncTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main POS Drawer',
        'account_code' => 'ACC-POS-01',
        'balance' => 5000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Central Retail Store',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'POS Shopper',
        'customer_code' => 'CUST-POS-01',
        'mobile' => '01799887766',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Gaming Mouse',
        'item_code' => 'ITM-MSE-01',
        'sku' => 'SKU-MSE-01',
        'purchase_price' => 1200.00,
        'sales_price' => 2000.00,
        'stock' => 50,
        'status' => 1,
    ]);

    // WELCOME500 coupon referenced by the checkout payload below — must exist
    // server-side so resolveCoupon() can validate it (the server now rejects
    // sales whose coupon cannot be server-verified).
    \App\Models\DbCoupon::create([
        'store_id' => 1,
        'code' => 'WELCOME500',
        'name' => 'Welcome 500 Taka Coupon',
        'value' => 500.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    // Pre-populate dashboard cache with old data
    Cache::put('dashboard_outstanding_due_s' . $user->store_id, 9999.00, 300);
    Cache::put('dashboard_month_sale_ids_s' . $user->store_id, collect([99999]), 300);

    // Perform POS Checkout via API:
    // Buy 2 mice @ 2000 = 4000 subtotal
    // 10% invoice discount = 400
    // 500 coupon discount = 500
    // Net Payable = 3100.00
    $response = $this->actingAs($user)->postJson(route('sales.pos.store'), [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'cart' => [
            [
                'id' => $item->id,
                'name' => $item->item_name,
                'price' => 2000.00,
                'qty' => 2,
                'total' => 4000.00,
                'discount' => 0,
                'taxAmount' => 0,
            ]
        ],
        'subtotal' => 4000.00,
        'discount_on_all' => 10,
        'discount_type' => 'percent',
        'coupon_amt' => 500.00,
        'coupon_code' => 'WELCOME500',
        'grand_total' => 3100.00,
        'paid_amount' => 3100.00,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
    ]);

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['sale_id'])->toBeGreaterThan(0);

    // 1. Verify item stock decremented by 2
    $item->refresh();
    expect((float)$item->stock)->toBe(48.00);

    // 2. Verify account balance incremented by 3100
    $account->refresh();
    expect((float)$account->balance)->toBe(8100.00); // 5000 + 3100

    // 3. Verify Dashboard Cache was immediately invalidated
    expect(Cache::has('dashboard_outstanding_due_s' . $user->store_id))->toBeFalse();
    expect(Cache::has('dashboard_month_sale_ids_s' . $user->store_id))->toBeFalse();

    // 4. Verify Dashboard query produces new figures immediately without stale cache
    $dashCtrl = new DashboardController();
    $stats = $dashCtrl->index()->getData()['stats'];
    expect((float)$stats['today_sales'])->toBeGreaterThanOrEqual(3100.00);
});

