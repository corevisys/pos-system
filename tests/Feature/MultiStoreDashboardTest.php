<?php

use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

// ============================================================================
// SETUP HELPERS
// ============================================================================

/**
 * Provision two stores, a Super-Admin user (role_id=1, has multi_store_dashboard_view),
 * and a regular store-user (role_id=2, no multi_store_dashboard_view).
 */
function setupMultiStoreDashboardEnv(): array
{
    Cache::flush();

    $storeA = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Alpha Store',
        'store_code' => 'ALPHA',
        'status'     => 1,
        'mobile'     => '01700000001',
    ]);

    $storeB = DbStore::firstOrCreate(['id' => 2], [
        'store_name' => 'Beta Store',
        'store_code' => 'BETA',
        'status'     => 1,
        'mobile'     => '01700000002',
    ]);

    // Super Admin role (id=1) — has all permissions including multi_store_dashboard_view
    $superAdminRole = DbRole::firstOrCreate(['id' => 1], [
        'store_id'  => $storeA->id,
        'role_name' => 'Super Admin',
        'status'    => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $superAdminRole->id], [
        'store_id'    => $storeA->id,
        'permissions' => [
            'dashboard_view_dashboard_data', 'multi_store_dashboard_view',
        ],
    ]);

    // Regular store user role (id=2) — does NOT have multi_store_dashboard_view
    $regularRole = DbRole::firstOrCreate(['id' => 2], [
        'store_id'  => $storeB->id,
        'role_name' => 'Store User',
        'status'    => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $regularRole->id], [
        'store_id'    => $storeB->id,
        'permissions' => ['dashboard_view_dashboard_data'],
    ]);

    // Super Admin user (role_id=1)
    $superAdmin = User::factory()->create([
        'store_id'  => $storeA->id,
        'role_id'   => $superAdminRole->id,
        'role_name' => 'Super Admin',
    ]);

    // Regular user in Store B (role_id=2)
    $regularUser = User::factory()->create([
        'store_id'  => $storeB->id,
        'role_id'   => $regularRole->id,
        'role_name' => 'Store User',
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

    return compact(
        'storeA', 'storeB',
        'superAdmin', 'regularUser',
        'warehouseA', 'warehouseB',
    );
}

// ============================================================================
// TEST: PERMISSION GATE
// ============================================================================

test('Multi-Store Dashboard: Super Admin can access the page (200)', function () {
    $env = setupMultiStoreDashboardEnv();

    $response = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));

    $response->assertOk();
});

test('Multi-Store Dashboard: Regular store-user is denied access (403)', function () {
    $env = setupMultiStoreDashboardEnv();

    $response = $this->actingAs($env['regularUser'])->get(route('multi-store-dashboard'));

    $response->assertForbidden();
});

test('Multi-Store Dashboard: Unauthenticated user is redirected to login', function () {
    $response = $this->get(route('multi-store-dashboard'));

    $response->assertRedirect(route('login'));
});

// ============================================================================
// TEST: NETWORK STATS ARE CORRECT SUM ACROSS STORES
// ============================================================================

test('Multi-Store Dashboard: networkStats today_sales is sum of all stores, not one store alone', function () {
    $env = setupMultiStoreDashboardEnv();
    $today = Carbon::today()->format('Y-m-d');

    // Store A: 1 sale today worth 1000
    DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'sales_code'     => 'MSD-A-001',
        'sales_date'     => $today,
        'subtotal'       => 1000.00,
        'grand_total'    => 1000.00,
        'paid_amount'    => 1000.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Store B: 1 sale today worth 600
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'sales_code'     => 'MSD-B-001',
        'sales_date'     => $today,
        'subtotal'       => 600.00,
        'grand_total'    => 600.00,
        'paid_amount'    => 600.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    $response = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));
    $response->assertOk();

    $networkStats = $response->viewData('networkStats');

    // Network today_sales must be 1000 + 600 = 1600
    expect((float) $networkStats['today_sales'])->toEqual(1600.00);

    // It is NOT just Store A's 1000 alone
    expect((float) $networkStats['today_sales'])->not->toEqual(1000.00);
});

// ============================================================================
// TEST: PER-STORE ISOLATION IN storeStats (Store B data never leaks into Store A)
// ============================================================================

test('Multi-Store Dashboard: per-store stats are correctly isolated — Store A only sees its own sales', function () {
    $env = setupMultiStoreDashboardEnv();
    $today = Carbon::today()->format('Y-m-d');

    // Store A: 800
    DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'sales_code'     => 'ISO-A-001',
        'sales_date'     => $today,
        'subtotal'       => 800.00,
        'grand_total'    => 800.00,
        'paid_amount'    => 800.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // Store B: 500 (must NOT appear in Store A's stats)
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'sales_code'     => 'ISO-B-001',
        'sales_date'     => $today,
        'subtotal'       => 500.00,
        'grand_total'    => 500.00,
        'paid_amount'    => 500.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    $response = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));
    $response->assertOk();

    $storeStats = $response->viewData('storeStats');

    // Find Store A's entry
    $storeAEntry = $storeStats->first(fn($e) => $e['store']->id === $env['storeA']->id);
    // Find Store B's entry
    $storeBEntry = $storeStats->first(fn($e) => $e['store']->id === $env['storeB']->id);

    expect($storeAEntry)->not->toBeNull();
    expect($storeBEntry)->not->toBeNull();

    // Store A's today_sales = 800, NOT 1300 (would be combined if scoping leaked)
    expect((float) $storeAEntry['stats']['stats']['today_sales'])->toEqual(800.00);

    // Store B's today_sales = 500
    expect((float) $storeBEntry['stats']['stats']['today_sales'])->toEqual(500.00);
});

// ============================================================================
// TEST: VIEW DATA SHAPE
// ============================================================================

test('Multi-Store Dashboard: view receives storeStats and networkStats variables', function () {
    $env = setupMultiStoreDashboardEnv();

    $response = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));
    $response->assertOk();

    // Both variables must be present
    $storeStats   = $response->viewData('storeStats');
    $networkStats = $response->viewData('networkStats');

    expect($storeStats)->not->toBeNull();
    expect($networkStats)->not->toBeNull();

    // networkStats has the expected keys
    expect($networkStats)->toHaveKeys([
        'today_sales', 'today_orders', 'this_month_sales',
        'this_month_purchases', 'total_outstanding_due', 'store_count',
    ]);

    // store_count matches DB store count
    expect($networkStats['store_count'])->toBe(DbStore::count());
});

// ============================================================================
// TEST: MONTH PURCHASES AGGREGATION
// ============================================================================

test('Multi-Store Dashboard: network month purchases aggregates both stores', function () {
    $env = setupMultiStoreDashboardEnv();
    $today = Carbon::today()->format('Y-m-d');

    DbPurchase::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'purchase_code'  => 'PUR-MSD-A-001',
        'purchase_date'  => $today,
        'subtotal'       => 2000.00,
        'grand_total'    => 2000.00,
        'paid_amount'    => 2000.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    DbPurchase::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'purchase_code'  => 'PUR-MSD-B-001',
        'purchase_date'  => $today,
        'subtotal'       => 1500.00,
        'grand_total'    => 1500.00,
        'paid_amount'    => 1500.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    $response = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));
    $response->assertOk();

    $networkStats = $response->viewData('networkStats');

    // 2000 + 1500 = 3500 combined
    expect((float) $networkStats['this_month_purchases'])->toEqual(3500.00);
});

// ============================================================================
// GAP 1: ADMIN ROLE (NON-SUPER-ADMIN) IS DENIED ACCESS (403) VIA isSuperAdmin()
// ============================================================================

test('Multi-Store Dashboard: Admin role user with broad permissions (and even with permission slug) is denied access (403) because isSuperAdmin is false', function () {
    $env = setupMultiStoreDashboardEnv();

    // Create an Admin role (id=3, role_name = "Admin")
    $adminRole = DbRole::firstOrCreate(['id' => 3], [
        'store_id'  => $env['storeA']->id,
        'role_name' => 'Admin',
        'status'    => 1,
    ]);

    // Give this Admin all standard permissions AND explicitly assign the slug
    DbPermission::updateOrCreate(['role_id' => $adminRole->id], [
        'store_id'    => $env['storeA']->id,
        'permissions' => [
            'dashboard_view', 'dashboard_view_dashboard_data',
            'items_view', 'sales_view', 'purchase_view', 'store_settings_view',
            'multi_store_dashboard_view', // Even if this slug is maliciously/mistakenly present!
        ],
    ]);

    $adminUser = User::factory()->create([
        'store_id'  => $env['storeA']->id,
        'role_id'   => $adminRole->id,
        'role_name' => 'Admin',
    ]);

    expect($adminUser->isSuperAdmin())->toBeFalse();
    expect($adminUser->hasPermission('multi_store_dashboard_view'))->toBeTrue();

    // Must still receive 403 Forbidden because of isSuperAdmin() defense-in-depth gate
    $response = $this->actingAs($adminUser)->get(route('multi-store-dashboard'));
    $response->assertForbidden();
});

// ============================================================================
// GAP 4: STORE PARITY (SAME FIGURES ON STORE DASHBOARD AND MULTI-STORE DASHBOARD)
// ============================================================================

test('Multi-Store Dashboard: store parity — single-store DashboardController stats exactly match the store card stats in MultiStoreDashboardController', function () {
    $env = setupMultiStoreDashboardEnv();
    $today = Carbon::today()->format('Y-m-d');

    // Create realistic sales and purchases for Store A
    DbSale::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'sales_code'     => 'PARITY-SALE-001',
        'sales_date'     => $today,
        'subtotal'       => 1250.00,
        'grand_total'    => 1250.00,
        'paid_amount'    => 1000.00, // 250 due
        'payment_status' => 'Partial',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    DbPurchase::create([
        'store_id'       => $env['storeA']->id,
        'warehouse_id'   => $env['warehouseA']->id,
        'purchase_code'  => 'PARITY-PUR-001',
        'purchase_date'  => $today,
        'subtotal'       => 750.00,
        'grand_total'    => 750.00,
        'paid_amount'    => 750.00,
        'payment_status' => 'Paid',
        'status'         => 1,
    ]);

    // Also create some Store B activity so we prove they don't contaminate Store A
    DbSale::create([
        'store_id'       => $env['storeB']->id,
        'warehouse_id'   => $env['warehouseB']->id,
        'sales_code'     => 'PARITY-B-001',
        'sales_date'     => $today,
        'subtotal'       => 999.00,
        'grand_total'    => 999.00,
        'paid_amount'    => 999.00,
        'payment_status' => 'Paid',
        'sales_status'   => 'Final',
        'status'         => 1,
    ]);

    // 1. Regular store user visits their own store dashboard (/dashboard)
    $storeAUser = User::factory()->create([
        'store_id'  => $env['storeA']->id,
        'role_id'   => $env['regularUser']->role_id,
        'role_name' => 'Store User',
    ]);

    $singleResponse = $this->actingAs($storeAUser)->get(route('dashboard'));
    $singleResponse->assertOk();
    $singleStats = $singleResponse->viewData('stats');

    // 2. Super Admin visits Multi-Store dashboard (/multi-store-dashboard)
    $multiResponse = $this->actingAs($env['superAdmin'])->get(route('multi-store-dashboard'));
    $multiResponse->assertOk();
    $multiStoreStats = $multiResponse->viewData('storeStats');
    $storeAEntry = $multiStoreStats->first(fn($e) => $e['store']->id === $env['storeA']->id);

    expect($storeAEntry)->not->toBeNull();
    $multiStats = $storeAEntry['stats']['stats'];

    // 3. Exact parity assertions across all key metric figures
    expect((float) $multiStats['today_sales'])->toEqual((float) $singleStats['today_sales']);
    expect((int) $multiStats['today_orders'])->toEqual((int) $singleStats['today_orders']);
    expect((float) $multiStats['this_month_sales'])->toEqual((float) $singleStats['this_month_sales']);
    expect((float) $multiStats['this_month_purchases'])->toEqual((float) $singleStats['this_month_purchases']);
    expect((float) $multiStats['total_outstanding_due'])->toEqual((float) $singleStats['total_outstanding_due']);
    expect($multiStats['month_change_percent'])->toEqual($singleStats['month_change_percent']);
});

// ============================================================================
// TEST: NAV LINK VISIBILITY IS RESTRICTED TO SUPER ADMIN
// ============================================================================

test('Multi-Store Dashboard: sidebar nav link is visible ONLY to Super Admin', function () {
    $env = setupMultiStoreDashboardEnv();

    // 1. Super Admin sees the link
    $response = $this->actingAs($env['superAdmin'])->get(route('dashboard'));
    $response->assertSee(route('multi-store-dashboard'));
    $response->assertSee('Multi-Store');

    // 2. Regular user does NOT see the link
    $responseRegular = $this->actingAs($env['regularUser'])->get(route('dashboard'));
    $responseRegular->assertDontSee(route('multi-store-dashboard'));

    // 3. Admin user (non-super-admin, even with permission) does NOT see the link
    $adminRole = DbRole::firstOrCreate(['id' => 4], [
        'store_id'  => $env['storeA']->id,
        'role_name' => 'Store Admin',
        'status'    => 1,
    ]);
    DbPermission::updateOrCreate(['role_id' => $adminRole->id], [
        'store_id'    => $env['storeA']->id,
        'permissions' => ['dashboard_view', 'dashboard_view_dashboard_data', 'multi_store_dashboard_view'],
    ]);
    $adminUser = User::factory()->create([
        'store_id'  => $env['storeA']->id,
        'role_id'   => $adminRole->id,
        'role_name' => 'Store Admin',
    ]);

    $responseAdmin = $this->actingAs($adminUser)->get(route('dashboard'));
    $responseAdmin->assertDontSee(route('multi-store-dashboard'));
});
