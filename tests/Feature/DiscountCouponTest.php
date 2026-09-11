<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbSale;
use App\Models\DbCoupon;
use App\Models\DbCustomerCoupon;
use App\Models\DbItem;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'COREVISYS TEST STORE',
        'status' => 1,
        'mobile' => '+8801700000000',
        'email' => 'store@corevisys.com',
        'address' => '123 Test Avenue, Dhaka',
    ]);

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => 1,
        'permissions' => ['sales_view', 'sales_add', 'coupon_view', 'coupon_add'],
    ]);
});

function setupCouponTestEnvironment() {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Main Test Warehouse',
        'status' => 1,
        'store_id' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'VIP Customer',
        'mobile' => '+8801811111111',
        'status' => 1,
        'store_id' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Premium Headset',
        'item_code' => 'ITM-HD01',
        'sales_price' => 1000.00,
        'purchase_price' => 600.00,
        'stock' => 50,
        'status' => 1,
        'store_id' => 1,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer',
        'account_number' => 'CASH-001',
        'balance' => 10000.00,
        'status' => 1,
    ]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

test('validates active master percentage coupon successfully', function () {
    $env = setupCouponTestEnvironment();

    DbCoupon::create([
        'store_id' => 1,
        'code' => 'PROMO10',
        'name' => '10 Percent Off Promo',
        'value' => 10.00,
        'type' => 'Percentage',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'promo10', // test case-insensitivity
        'subtotal' => 1000,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 'PROMO10',
            'type' => 'Percentage',
            'value' => 10.00,
            'discount_amount' => 100.00,
        ]);
});

test('validates seeded ONEUSE20 for John Doe on an 8500 cart', function () {
    $env = setupCouponTestEnvironment();
    $john = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 1,
    ]);

    DbCustomerCoupon::create([
        'store_id' => 1,
        'customer_id' => $john->id,
        'code' => 'ONEUSE20',
        'name' => '20% Off Verification Voucher',
        'value' => 20.00,
        'type' => 'Percentage',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => ' oneuse20 ',
        'subtotal' => 8500.00,
        'customer_id' => $john->id,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'code' => 'ONEUSE20',
        'type' => 'Percentage',
        'value' => 20.00,
        'discount_amount' => 1700.00,
    ]);
});

test('POS exposes a functional percent discount binding and calculation branch', function () {
    $env = setupCouponTestEnvironment();

    $response = $this->actingAs($env['user'])->get(route('sales.pos'));

    $response->assertOk()
        ->assertSee('x-model="discountType"', false)
        ->assertSee("discountType === 'fixed'", false)
        ->assertSee("(this.subtotal * (parseFloat(this.discountOnAll || 0))) / 100", false);
});

test('validates active master fixed coupon successfully', function () {
    $env = setupCouponTestEnvironment();

    DbCoupon::create([
        'store_id' => 1,
        'code' => 'FLAT150',
        'name' => '150 Taka Flat Off',
        'value' => 150.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'FLAT150',
        'subtotal' => 1000,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 'FLAT150',
            'type' => 'Fixed',
            'value' => 150.00,
            'discount_amount' => 150.00,
        ]);
});

test('rejects expired coupon code', function () {
    $env = setupCouponTestEnvironment();

    DbCoupon::create([
        'store_id' => 1,
        'code' => 'EXPIRED20',
        'name' => 'Expired Promo',
        'value' => 20.00,
        'type' => 'Percentage',
        'expire_date' => date('Y-m-d', strtotime('-5 days')),
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'EXPIRED20',
        'subtotal' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('rejects inactive coupon code', function () {
    $env = setupCouponTestEnvironment();

    DbCoupon::create([
        'store_id' => 1,
        'code' => 'INACTIVE50',
        'name' => 'Inactive Promo',
        'value' => 50.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+10 days')),
        'status' => 0, // Inactive
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'INACTIVE50',
        'subtotal' => 1000,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('rejects non-existent coupon code', function () {
    $env = setupCouponTestEnvironment();

    $response = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'NONEXISTENT',
        'subtotal' => 1000,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
        ]);
});

test('validates customer-restricted coupon only for assigned customer', function () {
    $env = setupCouponTestEnvironment();

    $otherCustomer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Other Customer',
        'mobile' => '+8801999999999',
        'status' => 1,
        'store_id' => 1,
    ]);

    $customerCoupon = DbCustomerCoupon::create([
        'store_id' => 1,
        'customer_id' => $env['customer']->id,
        'code' => 'VIPGIFT',
        'name' => 'Exclusive VIP Gift',
        'value' => 200.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    // 1. Trying with matching customer -> should succeed
    $responseSuccess = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'VIPGIFT',
        'subtotal' => 1000,
        'customer_id' => $env['customer']->id,
    ]);

    $responseSuccess->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 'VIPGIFT',
            'discount_amount' => 200.00,
            'is_customer_coupon' => true,
        ]);

    // 2. Trying with other customer -> should fail
    $responseWrongCustomer = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'VIPGIFT',
        'subtotal' => 1000,
        'customer_id' => $otherCustomer->id,
    ]);

    $responseWrongCustomer->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);

    // 3. Trying with walk-in customer (null) -> should fail
    $responseWalkIn = $this->actingAs($env['user'])->postJson(route('sales.coupon.validate'), [
        'code' => 'VIPGIFT',
        'subtotal' => 1000,
        'customer_id' => null,
    ]);

    $responseWalkIn->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('server-side re-validation securely stores coupon and consumes customer coupon on checkout', function () {
    $env = setupCouponTestEnvironment();

    $customerCoupon = DbCustomerCoupon::create([
        'store_id' => 1,
        'customer_id' => $env['customer']->id,
        'code' => 'ONEUSE20',
        'name' => '20% Off Voucher',
        'value' => 20.00,
        'type' => 'Percentage',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    $cartPayload = [
        [
            'id' => $env['item']->id,
            'qty' => 2, // 2 x 1000 = 2000 subtotal
            'price' => 1000.00,
            'discount' => 0,
            'tax' => 0,
            'total' => 2000.00,
        ]
    ];

    // Attempt checkout: Subtotal = 2000, Global Disc = 10% (200), Coupon = 20% (400)
    // Tampered payload from client claiming coupon discount is 9999
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), [
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => $cartPayload,
        'subtotal' => 2000.00,
        'discount_on_all' => 10.00,
        'discount_type' => 'percent',
        'coupon_code' => 'ONEUSE20',
        'customer_coupon_id' => $customerCoupon->id,
        'coupon_amt' => 9999.00, // Tampered frontend amount
        'grand_total' => 1400.00, // 2000 - 200 (disc) - 400 (coupon) = 1400
        'account_id' => $env['account']->id,
        'paid_amount' => 1400.00,
        'payment_type' => 'Cash',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $sale = DbSale::latest('id')->first();
    expect($sale)->not->toBeNull();
    expect((float)$sale->subtotal)->toBe(2000.00);
    expect((float)$sale->tot_discount_to_all_amt)->toBe(200.00); // 10% of 2000
    expect((float)$sale->coupon_amt)->toBe(400.00); // Server-side re-calculated 20% of 2000, NOT 9999
    expect((float)$sale->grand_total)->toBe(1400.00);

    // Verify customer coupon status flipped to 0 (consumed)
    $customerCoupon->refresh();
    expect($customerCoupon->status)->toBe(0);
});

test('master coupon can be reused across sales while remaining active', function () {
    $env = setupCouponTestEnvironment();

    $masterCoupon = DbCoupon::create([
        'store_id' => 1,
        'code' => 'SPRING50',
        'name' => 'Spring 50 Taka Promo',
        'value' => 50.00,
        'type' => 'Fixed',
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 1,
    ]);

    $cartPayload = [
        [
            'id' => $env['item']->id,
            'qty' => 1,
            'price' => 1000.00,
            'total' => 1000.00,
        ]
    ];

    // First Sale
    $res1 = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), [
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => $cartPayload,
        'subtotal' => 1000.00,
        'coupon_id' => $masterCoupon->id,
        'coupon_code' => 'SPRING50',
        'grand_total' => 950.00,
        'account_id' => $env['account']->id,
        'paid_amount' => 950.00,
    ]);
    $res1->assertStatus(200);

    // Verify master coupon is still active (status = 1)
    $masterCoupon->refresh();
    expect($masterCoupon->status)->toBe(1);

    // Second Sale by same or different customer
    $res2 = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), [
        'customer_id' => null, // Walk-in customer
        'warehouse_id' => $env['warehouse']->id,
        'cart' => $cartPayload,
        'subtotal' => 1000.00,
        'coupon_id' => $masterCoupon->id,
        'coupon_code' => 'SPRING50',
        'grand_total' => 950.00,
        'account_id' => $env['account']->id,
        'paid_amount' => 950.00,
    ]);
    $res2->assertStatus(200);

    $masterCoupon->refresh();
    expect($masterCoupon->status)->toBe(1);
});
