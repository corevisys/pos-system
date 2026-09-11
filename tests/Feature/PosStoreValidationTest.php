<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbCoupon;
use App\Models\DbCustomerCoupon;
use App\Models\DbSale;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 1 regression tests for the shared PosController@store / storeEmi validation
 * (POS and Add Sale both hit these endpoints).
 */

function posStoreTestEnv()
{
    // Create the store ONLY if it doesn't already exist — do NOT overwrite the name,
    // otherwise tests that run after this one in the same process (e.g. PageTitleTest)
    // see a mutated store name. This mirrors the other test suites' isolation pattern.
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'VALIDATION TEST STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'store@corevisys.com',
            'address' => '123 Test Avenue',
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => 1,
        'permissions' => ['sales_view', 'sales_add'],
    ]);

    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Validation Warehouse',
        'status' => 1,
        'store_id' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Validation Customer',
        'mobile' => '+8801811111111',
        'status' => 1,
        'store_id' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Validation Item',
        'item_code' => 'VAL-001',
        'sales_price' => 100.00,
        'purchase_price' => 60.00,
        'stock' => 10,
        'status' => 1,
        'store_id' => 1,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Validation Cash Drawer',
        'account_number' => 'VAL-CASH-01',
        'balance' => 1000.00,
        'status' => 1,
    ]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

function posStoreBasePayload($env, array $overrides = [])
{
    return array_merge([
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [
            [
                'id' => $env['item']->id,
                'name' => $env['item']->item_name,
                'price' => 100.00,
                'qty' => 1,
                'total' => 100.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]
        ],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ], $overrides);
}

test('valid POS sale succeeds with correct totals', function () {
    $env = posStoreTestEnv();

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), posStoreBasePayload($env));
    $response->assertStatus(200)->assertJson(['success' => true]);

    $sale = DbSale::latest('id')->first();
    expect((float) $sale->grand_total)->toBe(100.00);
});

test('valid Add Sale succeeds with tax and other charges included', function () {
    $env = posStoreTestEnv();

    // Add Sale client includes tax + other_charges in grand_total.
    $payload = posStoreBasePayload($env, [
        'cart' => [
            [
                'id' => $env['item']->id,
                'name' => $env['item']->item_name,
                'price' => 100.00,
                'qty' => 2,
                'total' => 230.00,
                'discount' => 0,
                'tax' => 15,
                'taxAmount' => 30.00,
            ]
        ],
        'subtotal' => 200.00,
        'other_charges' => 10.00,
        'grand_total' => 240.00, // 200 + 30 tax + 10 other = 240
        'paid_amount' => 240.00,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    $sale = DbSale::latest('id')->first();
    expect((float) $sale->grand_total)->toBe(240.00);
});

test('tampered grand_total is rejected with 422 on both POS and Add Sale', function () {
    $env = posStoreTestEnv();

    // Client claims a fake grand_total that doesn't match server recompute.
    $tampered = posStoreBasePayload($env, ['grand_total' => 50.00]);

    $posResp = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $tampered);
    $posResp->assertStatus(422)->assertJson(['success' => false]);
    $posResp->assertJsonPath('message', fn ($m) => str_contains($m, 'Total mismatch'));

    $addResp = $this->actingAs($env['user'])->postJson(route('sales.store'), $tampered);
    $addResp->assertStatus(422)->assertJson(['success' => false]);

    // No sale should have been persisted by either attempt.
    expect(DbSale::count())->toBe(0);
});

test('insufficient stock is rejected with 422', function () {
    $env = posStoreTestEnv();

    // Only 1 item in warehouse stock; request 5.
    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'available_qty' => 1,
    ]);

    $payload = posStoreBasePayload($env, [
        'cart' => [
            [
                'id' => $env['item']->id,
                'name' => $env['item']->item_name,
                'price' => 100.00,
                'qty' => 5,
                'total' => 500.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]
        ],
        'subtotal' => 500.00,
        'grand_total' => 500.00,
        'paid_amount' => 500.00,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'Insufficient stock'));
});

test('empty cart is rejected with 422', function () {
    $env = posStoreTestEnv();

    $payload = posStoreBasePayload($env, ['cart' => []]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'Cart cannot be empty'));
});

test('customer coupon is NOT consumed when the sale fails total validation', function () {
    $env = posStoreTestEnv();

    $coupon = DbCustomerCoupon::create([
        'store_id' => 1,
        'customer_id' => $env['customer']->id,
        'code' => 'ONETIME10',
        'name' => '10 Taka Off',
        'value' => 10.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    // Client claims a tampered grand_total that will fail the mismatch check
    // AFTER resolveCoupon() runs (which previously burned the coupon).
    $payload = posStoreBasePayload($env, [
        'grand_total' => 1.00,
        'coupon_code' => 'ONETIME10',
        'customer_coupon_id' => $coupon->id,
        'coupon_amt' => 10.00,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(422);

    // Coupon must still be active (status = 1) — not burned by the failed attempt.
    $coupon->refresh();
    expect($coupon->status)->toBe(1);
});

test('customer coupon IS consumed when the sale succeeds', function () {
    $env = posStoreTestEnv();

    $coupon = DbCustomerCoupon::create([
        'store_id' => 1,
        'customer_id' => $env['customer']->id,
        'code' => 'ONETIME20',
        'name' => '20 Taka Off',
        'value' => 20.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    // 100 subtotal, 20 coupon → grand_total 80.
    $payload = posStoreBasePayload($env, [
        'grand_total' => 80.00,
        'coupon_code' => 'ONETIME20',
        'customer_coupon_id' => $coupon->id,
        'coupon_amt' => 20.00,
        'paid_amount' => 80.00,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    $coupon->refresh();
    expect($coupon->status)->toBe(0);
});

/**
 * EMI-eligible customer for the shared storeEmi flow. The shared validation env
 * only creates a 'regular' customer (default), so EMI-specific tests need their
 * own customer whose db_customers.customer_type === 'emi'.
 */
function posStoreEmiEnv($env, string $customerType = 'emi')
{
    $emiCustomer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'EMI Eligible Customer',
        'mobile' => '+8801811223344',
        'customer_type' => $customerType,
        'status' => 1,
        'store_id' => 1,
    ]);

    $env['emi_customer'] = $emiCustomer;
    return $env;
}

test('EMI store succeeds for an EMI-eligible customer', function () {
    $env = posStoreEmiEnv(posStoreTestEnv());

    $payload = posStoreBasePayload($env, [
        'customer_id' => $env['emi_customer']->id,
        'grand_total' => 100.00,
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 500,
        'start_date' => date('Y-m-d'),
        'notes' => 'Test EMI',
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.emi'), $payload);
    $response->assertStatus(200)->assertJson(['success' => true]);

    expect(\App\Models\DbEmiSale::count())->toBe(1);
});

test('EMI store rejects tampered grand_total for an EMI-eligible customer', function () {
    $env = posStoreEmiEnv(posStoreTestEnv());

    $payload = posStoreBasePayload($env, [
        'customer_id' => $env['emi_customer']->id,
        'grand_total' => 50.00, // tampered (real total is 100)
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 500,
        'start_date' => date('Y-m-d'),
        'notes' => 'Test EMI',
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.emi'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'Total mismatch'));
    expect(DbSale::count())->toBe(0);
});

test('EMI store rejects a non-EMI customer on the POS route (server-side eligibility)', function () {
    $env = posStoreTestEnv(); // default customer_type = regular

    $payload = posStoreBasePayload($env, [
        'grand_total' => 100.00,
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 500,
        'start_date' => date('Y-m-d'),
        'notes' => 'Test EMI',
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'EMI'));
    expect(DbSale::count())->toBe(0);
    expect(\App\Models\DbEmiSale::count())->toBe(0);
});

test('EMI store rejects a non-EMI customer on the Add Sale route (server-side eligibility)', function () {
    $env = posStoreTestEnv(); // default customer_type = regular

    $payload = posStoreBasePayload($env, [
        'grand_total' => 100.00,
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 500,
        'start_date' => date('Y-m-d'),
        'notes' => 'Test EMI',
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.emi'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'EMI'));
    expect(DbSale::count())->toBe(0);
    expect(\App\Models\DbEmiSale::count())->toBe(0);
});

test('EMI store rejects a Walk-in customer (no EMI eligibility)', function () {
    $env = posStoreTestEnv();

    $payload = posStoreBasePayload($env, [
        'customer_id' => 'Walk-in customer',
        'grand_total' => 100.00,
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 500,
        'start_date' => date('Y-m-d'),
        'notes' => 'Test EMI',
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.emi'), $payload);
    $response->assertStatus(422)->assertJson(['success' => false]);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'EMI'));
    expect(DbSale::count())->toBe(0);
});
