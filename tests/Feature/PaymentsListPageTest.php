<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\AcAccount;
use App\Models\DbPaymentType;
use Carbon\Carbon;

function getPaymentsListTestUser(int $storeId = 1): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'currency_code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => $storeId], [
        'store_name' => 'Payments List Store ' . $storeId,
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
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_include_pos_sales_payments_view'],
    ]);

    return User::factory()->create([
        'store_id' => $store->id,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

function makePaymentsListFixture(int $storeId = 1): array {
    $account = AcAccount::create([
        'store_id' => $storeId,
        'account_name' => 'Payments List Account',
        'account_code' => 'ACC-PAYLIST-' . $storeId,
        'balance' => 0.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['warehouse_name' => 'PayList WH ' . $storeId, 'status' => 1]);
    $customer = DbCustomer::create([
        'customer_name' => 'PayList Customer ' . $storeId,
        'customer_code' => 'CUST-PAYLIST-' . $storeId,
        'mobile' => '0179000000' . $storeId,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => $storeId,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-PAYLIST-' . $storeId,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_amount' => 1000.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);

    $payment = DbSalePayment::create([
        'store_id' => $storeId,
        'sales_id' => $sale->id,
        'customer_id' => $customer->id,
        'account_id' => $account->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 1000.00,
        'created_by' => 1,
        'status' => 1,
    ]);

    return ['account' => $account, 'sale' => $sale, 'payment' => $payment, 'customer' => $customer];
}

test('1. Payments list is scoped to the current store only', function () {
    $user = getPaymentsListTestUser(1);
    $fxA = makePaymentsListFixture(1);

    // Another store's payment must NOT appear
    $fxB = makePaymentsListFixture(2);

    $response = $this->actingAs($user)->get(route('sales.payments'));

    $response->assertOk();
    $response->assertSee('SA-PAYLIST-1');
    $response->assertDontSee('SA-PAYLIST-2');
});

test('2. Store A user sees store-A-only totals in the four stat cards', function () {
    $user = getPaymentsListTestUser(1);
    $fxA = makePaymentsListFixture(1);

    // A store-2 payment of a much larger amount should not leak into store-1 stats
    $fxB = makePaymentsListFixture(2);
    $fxB['payment']->update(['payment' => 99999.00]);

    $response = $this->actingAs($user)->get(route('sales.payments'));

    $response->assertOk();
    // Store-1 only has the 1000.00 payment
    $response->assertSee(format_currency(1000.00));
    $response->assertDontSee(format_currency(99999.00));
});

test('3. Stat cards reflect the applied payment_type filter', function () {
    $user = getPaymentsListTestUser(1);
    $fx = makePaymentsListFixture(1);

    // Add a second payment of a different method
    DbPaymentType::firstOrCreate(['payment_type' => 'Bank Transfer'], ['status' => 1]);

    $sale = $fx['sale'];
    $sale->update(['paid_amount' => 500.00, 'payment_status' => 'Partial']);

    $second = DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $fx['customer']->id,
        'account_id' => $fx['account']->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Bank Transfer',
        'payment' => 500.00,
        'created_by' => $user->id,
        'status' => 1,
    ]);

    // Without filter: total = 1500
    $response = $this->actingAs($user)->get(route('sales.payments'));
    $response->assertOk();
    $response->assertSee(format_currency(1500.00));

    // With payment_type=Bank Transfer: total = 500, cash = 0
    $response = $this->actingAs($user)->get(route('sales.payments', ['payment_type' => 'Bank Transfer']));
    $response->assertOk();
    $response->assertSee(format_currency(500.00));
});

test('4. Stat cards reflect the applied search filter', function () {
    $user = getPaymentsListTestUser(1);
    $fx = makePaymentsListFixture(1);

    // Another sale+payment for the same store
    $customer2 = DbCustomer::create([
        'customer_name' => 'Other Customer',
        'customer_code' => 'CUST-OTHER',
        'mobile' => '01791111111',
        'status' => 1,
    ]);
    $sale2 = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $fx['sale']->warehouse_id,
        'customer_id' => $customer2->id,
        'sales_code' => 'SA-SEARCH-MATCH',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 200.00,
        'grand_total' => 200.00,
        'paid_amount' => 200.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);
    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale2->id,
        'customer_id' => $customer2->id,
        'account_id' => $fx['account']->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 200.00,
        'created_by' => $user->id,
        'status' => 1,
    ]);

    // Unfiltered: total = 1200
    $response = $this->actingAs($user)->get(route('sales.payments'));
    $response->assertSee(format_currency(1200.00));

    // Search for SA-PAYLIST-1 only: total = 1000
    $response = $this->actingAs($user)->get(route('sales.payments', ['search' => 'SA-PAYLIST-1']));
    $response->assertSee(format_currency(1000.00));
    $response->assertDontSee('SA-SEARCH-MATCH');
});

test('5. Custom payment types appear as filter options', function () {
    $user = getPaymentsListTestUser(1);
    makePaymentsListFixture(1);

    DbPaymentType::firstOrCreate(['payment_type' => 'Rocket'], ['status' => 1]);

    $response = $this->actingAs($user)->get(route('sales.payments'));

    $response->assertOk();
    $response->assertSee('Rocket');
});
