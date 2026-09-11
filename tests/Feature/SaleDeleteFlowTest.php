<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbCategory;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbSalesItemReturn;
use App\Models\DbEmiSale;
use App\Models\DbEmiSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function getSaleDeleteTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Sale Delete Test Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

function makeDeleteSaleFixture(User $user): array {
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Delete Test Cash',
        'account_code' => 'ACC-DEL-001',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Delete WH',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Delete Customer',
        'customer_code' => 'CUST-DEL-001',
        'mobile' => '01799000001',
        'status' => 1,
    ]);

    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Delete Book',
        'item_code' => 'ITM-DEL-001',
        'category_id' => $category->id,
        'purchase_price' => 50.00,
        'sales_price' => 200.00,
        'stock' => 10,
        'status' => 1,
    ]);

    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'available_qty' => 10,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'sales_code' => 'SALE-DEL-001',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'customer_id' => $customer->id,
        'grand_total' => 400.00,
        'subtotal' => 400.00,
        'paid_amount' => 400.00,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
        'pos' => 0,
        'status' => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 4,
        'price' => 100.00,
        'total_cost' => 400.00,
        'status' => 1,
    ]);

    $payment = DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $customer->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 400.00,
        'account_id' => $account->id,
        'created_by' => $user->id,
        'status' => 1,
    ]);

    AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => Carbon::today()->format('Y-m-d'),
        'transaction_type' => 'SALES PAYMENT',
        'payment_code' => 'Cash',
        'credit_account_id' => $account->id,
        'debit_account_id' => null,
        'debit_amt' => 0,
        'credit_amt' => 400.00,
        'note' => 'Sales Payment: ' . $sale->sales_code,
        'ref_salespayments_id' => $payment->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    // Simulate the sale reducing stock on creation
    $item->decrement('stock', 4);
    DbWarehouseItem::where('warehouse_id', $warehouse->id)
        ->where('item_id', $item->id)
        ->decrement('available_qty', 4);
    $account->increment('balance', 400.00);

    return compact('sale', 'item', 'warehouse', 'account', 'customer');
}

test('1. Deleting a sale with an EMI record is blocked with a clear error', function () {
    $user = getSaleDeleteTestUser();
    $fix = makeDeleteSaleFixture($user);

    DbEmiSale::create([
        'store_id' => 1,
        'sale_id' => $fix['sale']->id,
        'customer_id' => $fix['customer']->id,
        'loan_amount' => 400.00,
        'total_payable' => 440.00,
        'duration_months' => 4,
        'monthly_installment' => 110.00,
        'start_date' => Carbon::today()->format('Y-m-d'),
        'status' => 'Active',
    ]);

    $response = $this->actingAs($user)->delete('/sales/delete/' . $fix['sale']->id);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'This sale has an EMI schedule and cannot be deleted.');

    // Sale must still exist
    expect(DbSale::find($fix['sale']->id))->not->toBeNull();
});

test('2. Deleting a partially-returned sale restores only the non-returned quantity', function () {
    $user = getSaleDeleteTestUser();
    $fix = makeDeleteSaleFixture($user);

    // 1 of 4 qty already returned (stock restored by the return flow)
    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $fix['sale']->id,
        'return_id' => 999,
        'item_id' => $fix['item']->id,
        'return_qty' => 1,
        'return_status' => 'Returned',
        'status' => 1,
    ]);
    $fix['item']->increment('stock', 1);
    DbWarehouseItem::where('warehouse_id', $fix['warehouse']->id)
        ->where('item_id', $fix['item']->id)
        ->increment('available_qty', 1);

    $itemBefore = $fix['item']->fresh();
    $whBefore = DbWarehouseItem::where('warehouse_id', $fix['warehouse']->id)
        ->where('item_id', $fix['item']->id)
        ->first();

    $response = $this->actingAs($user)->delete('/sales/delete/' . $fix['sale']->id);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Stock went 6 -> 6+3 = 9 (only remaining 3 of 4 restored, NOT +4 to 10)
    $itemAfter = $fix['item']->fresh();
    $whAfter = DbWarehouseItem::where('warehouse_id', $fix['warehouse']->id)
        ->where('item_id', $fix['item']->id)
        ->first();

    expect((float) $itemAfter->stock)->toBe((float) $itemBefore->stock + 3);
    expect((float) $whAfter->available_qty)->toBe((float) $whBefore->available_qty + 3);

    expect(DbSale::find($fix['sale']->id))->toBeNull();
});

test('3. Deleting a paid sale reverses account balance and removes ledger transactions', function () {
    $user = getSaleDeleteTestUser();
    $fix = makeDeleteSaleFixture($user);

    // After fixture: account balance = 1000 + 400 = 1400
    $accountBefore = $fix['account']->fresh();
    expect((float) $accountBefore->balance)->toBe(1400.0);

    $paymentId = DbSalePayment::where('sales_id', $fix['sale']->id)->value('id');
    $transaction = AcTransaction::where('ref_salespayments_id', $paymentId)->first();
    expect($transaction)->not->toBeNull();

    $response = $this->actingAs($user)->delete('/sales/delete/' . $fix['sale']->id);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Account balance reversed back to 1000
    $accountAfter = $fix['account']->fresh();
    expect((float) $accountAfter->balance)->toBe(1000.0);

    // Ledger transaction removed
    expect(AcTransaction::where('ref_salespayments_id', $paymentId)->count())->toBe(0);
    expect(DbSalePayment::where('sales_id', $fix['sale']->id)->count())->toBe(0);
});

test('4. Delete failure shows a generic error message and logs the real exception', function () {
    $user = getSaleDeleteTestUser();
    $fix = makeDeleteSaleFixture($user);

    // Force a deterministic exception inside the destroy transaction by registering
    // a deleting event on the sale model — the catch path must swallow it.
    DbSale::deleting(function () {
        throw new \Exception('Simulated delete failure');
    });

    $response = $this->actingAs($user)->delete('/sales/delete/' . $fix['sale']->id);

    $response->assertRedirect();
    // User must NOT see raw exception text
    $response->assertSessionHas('error', 'This sale could not be deleted. Please try again.');
    $session = session('error');
    expect($session)->not->toContain('SQLSTATE');
    expect($session)->not->toContain('Simulated delete failure');

    // Transaction must have been rolled back — the sale and stock remain intact
    expect(DbSale::find($fix['sale']->id))->not->toBeNull();
    expect((float) $fix['item']->fresh()->stock)->toBe(6.0);
});
