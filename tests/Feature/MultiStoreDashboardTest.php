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
