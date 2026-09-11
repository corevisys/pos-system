<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbSalesReturn;
use App\Models\DbSalesPaymentReturn;
use Carbon\Carbon;

function getAccountingTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Accounting Test Store',
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
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. POS sale with payment creates matching ac_transactions credit entry and increments account balance', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer',
        'account_code' => 'ACC-CASH-001',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Main WH',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Alice Accounting',
        'customer_code' => 'CUST-ACC-001',
        'mobile' => '01799000001',
        'status' => 1,
    ]);

    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Book',
        'item_code' => 'ITM-BK-001',
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

    $response = $this->actingAs($user)->postJson('/sales/pos/store', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'account_id' => $account->id,
        'cart' => [
            ['id' => $item->id, 'qty' => 1, 'price' => 200.00, 'total' => 200.00]
        ],
        'subtotal' => 200.00,
        'grand_total' => 200.00,
        'paid_amount' => 200.00,
        'payment_type' => 'Cash',
        'payments' => [
            ['amount' => 200.00, 'type' => 'Cash', 'account' => $account->id]
        ]
    ]);

    $response->assertOk();

    // Check payment record
    $payment = DbSalePayment::where('account_id', $account->id)->latest()->first();
    expect($payment)->not->toBeNull();
    expect((float)$payment->payment)->toBe(200.0);

    // Check AcTransaction entry
    $transaction = AcTransaction::where('ref_salespayments_id', $payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_type)->toBe('SALES PAYMENT');
    expect($transaction->credit_account_id)->toBe($account->id);
    expect((float)$transaction->credit_amt)->toBe(200.0);
    expect((float)$transaction->debit_amt)->toBe(0.0);

    // Check Account Balance: 1000 + 200 = 1200
    $account->refresh();
    expect((float)$account->balance)->toBe(1200.0);
});

test('2. Sales return with refund creates matching ac_transactions debit entry and decrements account balance', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer 2',
        'account_code' => 'ACC-CASH-002',
        'balance' => 1200.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main WH 2', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Alice 2', 'customer_code' => 'CUST-ACC-002', 'mobile' => '01799000002', 'status' => 1]);
    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create(['store_id' => 1, 'item_name' => 'Book 2', 'item_code' => 'ITM-BK-002', 'category_id' => $category->id, 'sales_price' => 200.00, 'stock' => 10, 'status' => 1]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 10]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-ACC-002',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 200.00,
        'grand_total' => 200.00,
        'paid_amount' => 200.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 50.00,
        'grand_total' => 50.00,
        'paid_amount' => 50.00,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'items' => [
            ['item_id' => $item->id, 'return_qty' => 1, 'price_per_unit' => 50.00, 'total_cost' => 50.00]
        ]
    ]);

    $response->assertOk();

    // Check payment return record
    $paymentReturn = DbSalesPaymentReturn::where('sales_id', $sale->id)->latest()->first();
    expect($paymentReturn)->not->toBeNull();
    expect((float)$paymentReturn->payment)->toBe(50.0);

    // Check AcTransaction debit entry
    $transaction = AcTransaction::where('ref_salespaymentsreturn_id', $paymentReturn->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_type)->toBe('SALES RETURN REFUND');
    expect($transaction->debit_account_id)->toBe($account->id);
    expect((float)$transaction->debit_amt)->toBe(50.0);
    expect((float)$transaction->credit_amt)->toBe(0.0);

    // Check Account Balance: 1200 - 50 = 1150
    $account->refresh();
    expect((float)$account->balance)->toBe(1150.0);
});

test('3. Subsequent payment via SaleController creates matching transaction and increments account balance', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Bank Account 3',
        'account_code' => 'ACC-BNK-003',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH 3', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Charlie', 'customer_code' => 'CUST-ACC-003', 'mobile' => '01799000003', 'status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-ACC-003',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 300.00,
        'grand_total' => 300.00,
        'paid_amount' => 100.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('sales.payments.store'), [
        'sales_id' => $sale->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'amount' => 100.00,
        'payment_type' => 'Bank',
        'account_id' => $account->id,
        'payment_note' => 'Second installment',
    ]);

    $response->assertRedirect(route('sales.list'));

    $payment = DbSalePayment::where('sales_id', $sale->id)->where('account_id', $account->id)->latest()->first();
    expect($payment)->not->toBeNull();

    $transaction = AcTransaction::where('ref_salespayments_id', $payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->credit_account_id)->toBe($account->id);
    expect((float)$transaction->credit_amt)->toBe(100.0);

    $account->refresh();
    expect((float)$account->balance)->toBe(600.0);
});

test('4. POS sale edit cleanly reverts old transactions and updates account balance correctly', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 4',
        'account_code' => 'ACC-CASH-004',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH 4', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Dave', 'customer_code' => 'CUST-ACC-004', 'mobile' => '01799000004', 'status' => 1]);
    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create(['store_id' => 1, 'item_name' => 'Item 4', 'item_code' => 'ITM-4', 'category_id' => $category->id, 'sales_price' => 100.00, 'stock' => 10, 'status' => 1]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 10]);

    // Initial sale of $100
    $storeResponse = $this->actingAs($user)->postJson('/sales/pos/store', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'cart' => [
            ['id' => $item->id, 'qty' => 1, 'price' => 100.00, 'total' => 100.00]
        ],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
        'payments' => [
            ['amount' => 100.00, 'type' => 'Cash', 'account' => $account->id]
        ]
    ]);
    $storeResponse->assertOk();

    $account->refresh();
    expect((float)$account->balance)->toBe(1100.0); // 1000 + 100

    $sale = DbSale::where('customer_id', $customer->id)->first();

    // Now EDIT sale to $250 (2.5 qty) with $250 payment
    $editResponse = $this->actingAs($user)->postJson('/sales/pos/store', [
        'sale_id' => $sale->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'cart' => [
            ['id' => $item->id, 'qty' => 2, 'price' => 125.00, 'total' => 250.00]
        ],
        'subtotal' => 250.00,
        'grand_total' => 250.00,
        'paid_amount' => 250.00,
        'payments' => [
            ['amount' => 250.00, 'type' => 'Cash', 'account' => $account->id]
        ]
    ]);
    $editResponse->assertOk();

    // Balance should be: 1000 (initial) + 250 (new) = 1250.00 (not 1100 + 250 = 1350)
    $account->refresh();
    expect((float)$account->balance)->toBe(1250.0);

    // Old transaction deleted, only 1 transaction remains for this sale
    $transactions = AcTransaction::where('customer_id', $customer->id)->get();
    expect($transactions->count())->toBe(1);
    expect((float)$transactions->first()->credit_amt)->toBe(250.0);
});

test('5. Sales return deletion restores refunded money back to account balance', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 5',
        'account_code' => 'ACC-CASH-005',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH 5', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Eve', 'customer_code' => 'CUST-ACC-005', 'mobile' => '01799000005', 'status' => 1]);
    $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create(['store_id' => 1, 'item_name' => 'Item 5', 'item_code' => 'ITM-5', 'category_id' => $category->id, 'sales_price' => 100.00, 'stock' => 10, 'status' => 1]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 10]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-ACC-005',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);

    // Create return with $60 refund
    $retResponse = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 60.00,
        'grand_total' => 60.00,
        'paid_amount' => 60.00,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'items' => [
            ['item_id' => $item->id, 'return_qty' => 1, 'price_per_unit' => 60.00, 'total_cost' => 60.00]
        ]
    ]);
    $retResponse->assertOk();

    $account->refresh();
    expect((float)$account->balance)->toBe(940.0); // 1000 - 60

    $return = DbSalesReturn::where('sales_id', $sale->id)->first();

    // Now DELETE the return
    $delResponse = $this->actingAs($user)->delete(route('sales.return.delete', ['id' => $return->id]));
    $delResponse->assertRedirect();

    // Account balance should be restored to 1000.00
    $account->refresh();
    expect((float)$account->balance)->toBe(1000.0);

    // AcTransaction should be deleted
    expect(AcTransaction::where('ref_salespaymentsreturn_id', $return->id)->first())->toBeNull();
});

test('6. Cash transactions ledger displays sales payments and return refunds with account names', function () {
    $user = getAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Ledger Test Account',
        'account_code' => 'ACC-LEDGER-001',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $transaction1 = AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => Carbon::today()->format('Y-m-d'),
        'transaction_type' => 'SALES PAYMENT',
        'payment_code' => 'Cash',
        'credit_account_id' => $account->id,
        'debit_amt' => 0,
        'credit_amt' => 350.00,
        'note' => 'POS Sale Payment: SA-LEDGER-1',
        'created_by' => $user->id,
        'created_date' => Carbon::today()->format('Y-m-d'),
    ]);

    $transaction2 = AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => Carbon::today()->format('Y-m-d'),
        'transaction_type' => 'SALES RETURN REFUND',
        'payment_code' => 'Cash',
        'debit_account_id' => $account->id,
        'debit_amt' => 50.00,
        'credit_amt' => 0,
        'note' => 'Refund for Return RTN-LEDGER-1',
        'created_by' => $user->id,
        'created_date' => Carbon::today()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)->get(route('accounts.transactions'));
    $response->assertOk();
    $response->assertSee('SALES PAYMENT');
    $response->assertSee('SALES RETURN REFUND');
    $response->assertSee('Ledger Test Account');
    $response->assertSee('350.00');
    $response->assertSee('50.00');
});
