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
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use App\Models\DbSalesPaymentReturn;
use App\Models\AcAccount;
use App\Models\DbPaymentType;
use Carbon\Carbon;

function getReturnCreditTestUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'currency_code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Return Credit Test Store',
        'status' => 1,
        'mobile' => '01700000000',
        'currency_id' => $currency->id,
        'decimals' => 2,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

/**
 * Builds a sale where a cash refund on a return makes the customer overpaid.
 * Sale: grand_total 1000, paid 1000. Return 600 with a 600 cash refund.
 * Post-return raw due = (1000-600) - (1000-600) = 0 (not overpaid).
 * To trigger overpaid, we instead return 800 with an 800 refund:
 * raw due = (1000-800) - (1000-800) = 0 still. So to overpay, the refund
 * must exceed what the sale had net of returns — which the store() guard
 * forbids. Therefore we construct the overpaid state directly in the DB
 * to verify the *indicator* surfaces it (option i is display-only).
 */
function makeOverpaidReturnFixture(User $user): array {
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Credit Indicator Cash',
        'account_code' => 'ACC-CREDIT-01',
        'balance' => 5000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Credit WH', 'status' => 1]);
    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Credit Customer',
        'customer_code' => 'CUST-CREDIT-01',
        'mobile' => '01791000001',
        'status' => 1,
    ]);

    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Credit Item',
        'item_code' => 'ITM-CREDIT-01',
        'category_id' => $category->id,
        'purchase_price' => 100,
        'sales_price' => 500,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-CREDIT-01',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000,
        'grand_total' => 1000,
        // Overpaid state: customer paid 1100 on a 1000 invoice (e.g. double payment).
        'paid_amount' => 1100,
        'payment_status' => 'Paid',
        'status' => 1,
        'return_bit' => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 2,
        'price_per_unit' => 500,
        'total_cost' => 1000,
    ]);

    // Then a return of 900 in goods with a 900 cash refund.
    // raw due = (1000 - 900) - (1100 - 900) = 100 - 200 = -100  → overpaid by 100.
    $return = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-CREDIT-01',
        'return_date' => Carbon::today()->format('Y-m-d'),
        'return_status' => 'Completed',
        'subtotal' => 900,
        'grand_total' => 900,
        'paid_amount' => 900,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
        'created_date' => date('Y-m-d'),
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'item_id' => $item->id,
        'return_qty' => 1,
        'price_per_unit' => 900,
        'total_cost' => 900,
    ]);

    DbSalesPaymentReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 900,
        'account_id' => $account->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'created_date' => date('Y-m-d'),
    ]);

    return ['account' => $account, 'sale' => $sale, 'return' => $return, 'item' => $item, 'warehouse' => $warehouse, 'customer' => $customer];
}

test('1. Sale show page surfaces "Credit Balance (Overpaid)" instead of clipping due to zero', function () {
    $user = getReturnCreditTestUser();
    $fx = makeOverpaidReturnFixture($user);
    // Fixture: sale paid 1100, returned 900 goods, refunded 900.
    // raw due = (1000 - 900) - (1100 - 900) = 100 - 200 = -100 → overpaid by 100.

    $response = $this->actingAs($user)->get(route('sales.show', $fx['sale']->id));
    $response->assertOk();
    $response->assertSee('Credit Balance (Overpaid)');
    // Credit renders through <x-money>; assert its runtime formatter call.
    assert_compact_amount($response, 100);
});

test('2. Sales List shows a "Credit" badge for an overpaid sale', function () {
    $user = getReturnCreditTestUser();
    $fx = makeOverpaidReturnFixture($user);

    $response = $this->actingAs($user)->get(route('sales.list'));
    $response->assertOk();
    $response->assertSee('SA-CREDIT-01');
    $response->assertSee('Credit');
    assert_compact_amount($response, 100);
});

test('3. Return Create page shows credit balance for an overpaid sale', function () {
    $user = getReturnCreditTestUser();
    $fx = makeOverpaidReturnFixture($user);

    $response = $this->actingAs($user)->get(route('sales.return.create', $fx['sale']->id));
    $response->assertOk();
    $response->assertSee('Credit Balance (Overpaid)');
    assert_compact_amount($response, 100);
});

test('4. Returns List shows a credit badge on the originating sale for an overpaid sale', function () {
    $user = getReturnCreditTestUser();
    $fx = makeOverpaidReturnFixture($user);

    $response = $this->actingAs($user)->get(route('sales.returns'));
    $response->assertOk();
    $response->assertSee('SR-CREDIT-01');
    $response->assertSee('Credit');
    assert_compact_amount($response, 100);
});
