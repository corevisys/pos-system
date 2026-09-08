<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbSale;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function getSalesListTestUser(int $storeId = 1): User
{
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'currency_code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => $storeId], [
        'store_name' => 'Sales List Test Store ' . $storeId,
        'status' => 1,
        'mobile' => '0170000000' . $storeId,
        'currency_id' => $currency->id,
        'decimals' => 2,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => $store->id,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => $store->id,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view'],
    ]);

    return User::factory()->create([
        'store_id' => $store->id,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

function makeSalesListSale(User $user, int $storeId, string $code, float $total, ?int $warehouseId = null): DbSale
{
    $customer = DbCustomer::create([
        'customer_name' => 'Generic Walk-in',
        'customer_code' => 'CUST-GEN',
        'mobile' => '01790000000',
        'status' => 1,
    ]);

    return DbSale::create([
        'store_id' => $storeId,
        'warehouse_id' => $warehouseId,
        'sales_code' => $code,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'customer_id' => $customer->id,
        'grand_total' => $total,
        'subtotal' => $total,
        'paid_amount' => $total,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
        'pos' => 0,
        'status' => 1,
    ]);
}

test('Sales List page renders OK for a store-scoped user', function () {
    $user = getSalesListTestUser(1);
    makeSalesListSale($user, 1, 'SALE-A-001', 100.00);

    $response = $this->actingAs($user)->get('/sales/list');

    $response->assertOk();
    $response->assertSee('Sales List');
    $response->assertSee('SALE-A-001');
});

test('Sales List shows only the current store sales (no cross-store leak)', function () {
    $userA = getSalesListTestUser(1);
    $userB = getSalesListTestUser(2);

    makeSalesListSale($userA, 1, 'SALE-STORE1', 100.00);
    makeSalesListSale($userB, 2, 'SALE-STORE2', 500.00);

    // User A only sees Store 1's sale
    $responseA = $this->actingAs($userA)->get('/sales/list');
    $responseA->assertOk();
    $responseA->assertSee('SALE-STORE1');
    $responseA->assertDontSee('SALE-STORE2');

    // User B only sees Store 2's sale
    $responseB = $this->actingAs($userB)->get('/sales/list');
    $responseB->assertOk();
    $responseB->assertSee('SALE-STORE2');
    $responseB->assertDontSee('SALE-STORE1');
});

test('Sales List stat cards are scoped to the current store only', function () {
    $userA = getSalesListTestUser(1);
    $userB = getSalesListTestUser(2);

    makeSalesListSale($userA, 1, 'SALE-STAT1', 100.00);
    makeSalesListSale($userB, 2, 'SALE-STAT2', 500.00);

    $response = $this->actingAs($userA)->get('/sales/list');

    // Store 1 only: total amount card shows 100, not 600
    $response->assertSee('100.00');
    $response->assertDontSee('500.00');
});

test('Search submit preserves active warehouse filter in the query string', function () {
    $user = getSalesListTestUser(1);

    $warehouse = DbWarehouse::create(['warehouse_name' => 'Filter WH', 'status' => 1]);
    makeSalesListSale($user, 1, 'SALE-FILTER-1', 100.00, $warehouse->id);
    makeSalesListSale($user, 1, 'SALE-FILTER-2', 200.00);

    // Apply a warehouse filter + search together — both must survive in the URL
    $response = $this->actingAs($user)->get('/sales/list?warehouse_id=' . $warehouse->id . '&search=SALE-FILTER-1');

    $response->assertOk();
    $response->assertSee('SALE-FILTER-1');
    $response->assertDontSee('SALE-FILTER-2');
});

test('Per-page limit param is honored by the list controller', function () {
    $user = getSalesListTestUser(1);

    for ($i = 1; $i <= 12; $i++) {
        makeSalesListSale($user, 1, 'SALE-PAGE-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 10.00);
    }

    // Default = 10 per page, ordered id desc => newest (SALE-PAGE-012) first, oldest (001) on page 2
    $default = $this->actingAs($user)->get('/sales/list');
    $default->assertOk();
    $default->assertSee('SALE-PAGE-012');
    $default->assertDontSee('SALE-PAGE-001');

    // limit=25 shows all 12 on one page
    $limited = $this->actingAs($user)->get('/sales/list?limit=25');
    $limited->assertOk();
    $limited->assertSee('SALE-PAGE-001');
    $limited->assertSee('SALE-PAGE-012');
});

test('CSV export of the filtered list only contains matching sales', function () {
    $user = getSalesListTestUser(1);

    makeSalesListSale($user, 1, 'SALE-EXP-1', 100.00);
    makeSalesListSale($user, 1, 'SALE-EXP-2', 200.00);

    $response = $this->actingAs($user)->get('/sales/list?search=SALE-EXP-1&export=csv');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('SALE-EXP-1');
    expect($response->streamedContent())->not->toContain('SALE-EXP-2');
});

test('Print export renders the print-friendly list view', function () {
    $user = getSalesListTestUser(1);

    makeSalesListSale($user, 1, 'SALE-PRINT-1', 100.00);

    $response = $this->actingAs($user)->get('/sales/list?export=print');

    $response->assertOk();
    $response->assertSee('Print / Save as PDF');
    $response->assertSee('SALE-PRINT-1');
});
