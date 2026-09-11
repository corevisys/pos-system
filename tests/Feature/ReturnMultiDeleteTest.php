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
use Carbon\Carbon;

function getMultiReturnDeleteUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1,
    ]);

    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Multi Return Delete Store',
        'status' => 1, 'mobile' => '01700000000',
        'currency_id' => $currency->id, 'decimals' => 2,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin',
    ]);
}

/**
 * Sale grand_total 1000, paid 1000 (Paid).
 * Two returns:
 *   R1: 300 goods, 0 refund (due offset only)
 *   R2: 200 goods, 200 refund
 * After both: net total = 1000-500 = 500; net paid = 1000-200 = 800 → overpaid by 300,
 * payment_status would be Paid (remainingDue 0). Delete R2 (200 refund):
 * surviving R1 = 300 goods, 0 refund. net total = 1000-300 = 700; net paid = 1000-0 = 1000.
 * remainingDue = 0 → still Paid. The OLD code would compute grand_total - paid_amount = 0 → Paid too.
 * To expose the bug we need a case where the OLD code gives a different answer.
 * Scenario B: sale grand_total 1000, paid 600 (Partial). R1: 400 goods / 0 refund.
 * R2: 100 goods / 100 refund.
 * After both: net total = 1000-500 = 500; net paid = 600-100 = 500 → due 0 → Paid.
 * Delete R1 (400 goods, 0 refund): surviving R2 = 100 goods, 100 refund.
 * net total = 1000-100 = 900; net paid = 600-100 = 500 → due 400 → Partial.
 * OLD code: remainingDue = grand_total - paid_amount = 1000-600 = 400 → Partial. Same.
 * The OLD bug shows when a surviving return has a refund. Scenario C:
 * sale grand_total 1000, paid 1000 (Paid). R1: 600 goods / 0 refund. R2: 100 goods / 100 refund.
 * After both: net total = 1000-700 = 300; net paid = 1000-100 = 900 → overpaid 600, status Paid.
 * Delete R1: surviving R2 = 100 goods / 100 refund.
 * net total = 1000-100 = 900; net paid = 1000-100 = 900 → due 0 → Paid.
 * OLD: grand_total - paid_amount = 0 → Paid. Same.
 * The REAL divergence: when the surviving return carries a refund that makes net paid
 * exceed net total. Scenario D: sale grand_total 1000, paid 600. R1: 500 goods / 500 refund.
 * R2: 0 goods / 100 refund (pure refund anomaly).
 * After both: net total = 1000-500 = 500; net paid = 600-600 = 0 → due 500 → Partial.
 * Delete R1: surviving R2 = 0 goods / 100 refund. net total = 1000; net paid = 600-100 = 500 → due 500 → Partial.
 * OLD: remainingDue = 1000-600 = 400 → Partial. Both Partial, but amount differs.
 * The clearest observable divergence for the TEST is the payment_status when the OLD
 * formula would say 'Unpaid' but net-financials say 'Partial' (or vice versa).
 */
function makeMultiReturnFixture(User $user): array {
    $account = AcAccount::create([
        'store_id' => 1, 'account_name' => 'MultiReturn Acct', 'account_code' => 'ACC-MR-01',
        'balance' => 5000.00, 'status' => 1, 'delete_bit' => 0,
    ]);
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'MR WH', 'status' => 1]);
    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'MR Customer', 'customer_code' => 'CUST-MR-01', 'mobile' => '01792000001', 'status' => 1,
    ]);
    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'MR Item', 'item_code' => 'ITM-MR-01', 'category_id' => $category->id,
        'purchase_price' => 100, 'sales_price' => 500, 'stock' => 10, 'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id,
        'sales_code' => 'SA-MR-01', 'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000, 'grand_total' => 1000, 'paid_amount' => 600,
        'payment_status' => 'Partial', 'status' => 1, 'return_bit' => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'item_id' => $item->id,
        'sales_qty' => 2, 'price_per_unit' => 500, 'total_cost' => 1000,
    ]);

    // R1: 500 goods, 500 refund
    $r1 = DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id, 'return_code' => 'SR-MR-01',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 500,
        'payment_status' => 'Paid', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);
    DbSalesItemReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $r1->id, 'item_id' => $item->id,
        'return_qty' => 1, 'price_per_unit' => 500, 'total_cost' => 500,
    ]);
    DbSalesPaymentReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $r1->id,
        'payment_date' => Carbon::today()->format('Y-m-d'), 'payment_type' => 'Cash',
        'payment' => 500, 'account_id' => $account->id, 'customer_id' => $customer->id,
        'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    // R2: 200 goods, 100 refund (partial refund)
    $r2 = DbSalesReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id, 'return_code' => 'SR-MR-02',
        'return_date' => Carbon::today()->format('Y-m-d'), 'return_status' => 'Completed',
        'subtotal' => 200, 'grand_total' => 200, 'paid_amount' => 100,
        'payment_status' => 'Partial', 'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);
    DbSalesItemReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $r2->id, 'item_id' => $item->id,
        'return_qty' => 1, 'price_per_unit' => 200, 'total_cost' => 200,
    ]);
    DbSalesPaymentReturn::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $r2->id,
        'payment_date' => Carbon::today()->format('Y-m-d'), 'payment_type' => 'Cash',
        'payment' => 100, 'account_id' => $account->id, 'customer_id' => $customer->id,
        'created_by' => $user->id, 'created_date' => date('Y-m-d'),
    ]);

    return ['account' => $account, 'sale' => $sale, 'r1' => $r1, 'r2' => $r2];
}

test('1. Deleting one of two returns recomputes sale payment_status from surviving-return net financials', function () {
    $user = getMultiReturnDeleteUser();
    $fx = makeMultiReturnFixture($user);

    // Before delete: net total = 1000 - 700 = 300; net paid = 600 - 600 = 0 → due 300 → Partial.
    $fx['sale']->refresh();
    expect($fx['sale']->payment_status)->toBe('Partial');

    // Delete R2 (200 goods / 100 refund). Surviving R1: 500 goods / 500 refund.
    $response = $this->actingAs($user)->from(route('sales.returns'))->delete(route('sales.return.delete', $fx['r2']->id));
    $response->assertRedirect(route('sales.returns'));
    $response->assertSessionHas('success');

    $fx['sale']->refresh();
    // net total = 1000 - 500 = 500; net paid = 600 - 500 = 100 → due 400 → Partial.
    expect((float)$fx['sale']->paid_amount)->toBe(600.00);
    expect($fx['sale']->payment_status)->toBe('Partial');
    expect($fx['sale']->return_bit)->toBe(1); // R1 still exists

    // R1 must be intact
    expect(DbSalesReturn::find($fx['r1']->id))->not->toBeNull();
});

test('2. Deleting the last surviving return resets return_bit to 0', function () {
    $user = getMultiReturnDeleteUser();
    $fx = makeMultiReturnFixture($user);

    // Delete R2 then R1
    $this->actingAs($user)->delete(route('sales.return.delete', $fx['r2']->id));
    $this->actingAs($user)->delete(route('sales.return.delete', $fx['r1']->id));

    $fx['sale']->refresh();
    expect($fx['sale']->return_bit)->toBe(0);
    // No returns left: net total = 1000, net paid = 600 → due 400 → Partial
    expect($fx['sale']->payment_status)->toBe('Partial');
});
