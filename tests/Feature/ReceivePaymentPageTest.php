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
use App\Models\DbSalePayment;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbPaymentType;
use Carbon\Carbon;

function getReceivePaymentTestUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'currency_code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Receive Payment Test Store',
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
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. Unauthenticated users cannot view receive payment page', function () {
    $response = $this->get('/sales/payments/receive/1');
    $response->assertRedirect('/login');
});

test('2. Receive payment page renders with correct sale details, customer, and remaining due', function () {
    $user = getReceivePaymentTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Rahim Uddin',
        'customer_code' => 'CU-000101',
        'mobile' => '01811223344',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Dhanmondi Branch',
        'status' => 1,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Store Cash Counter',
        'account_code' => 'ACC-CSH-101',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $paymentType = DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash'], ['status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-2026-001',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1500.00,
        'grand_total' => 1500.00,
        'paid_amount' => 500.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    // Initial partial payment record
    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $customer->id,
        'account_id' => $account->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 500.00,
        'created_by' => $user->id,
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('sales.payments.receive', $sale->id));
    
    $response->assertOk();
    $response->assertSee('Receive Payment');
    $response->assertSee('SA-2026-001');
    $response->assertSee('Rahim Uddin');
    $response->assertSee('01811223344');
    $response->assertSee('Store Cash Counter');
    $response->assertSee(format_currency(1500.00));
    $response->assertSee(format_currency(500.00));
    $response->assertSee(format_currency(1000.00)); // remaining balance
    $response->assertSee('Record Payment');
});

test('3. Submitting a partial payment creates DbSalePayment, AcTransaction, and increments account balance', function () {
    $user = getReceivePaymentTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Karim Khan',
        'customer_code' => 'CU-000102',
        'mobile' => '01899887766',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Gulshan Branch',
        'status' => 1,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'BRAC Bank Merchant',
        'account_code' => 'ACC-BNK-102',
        'balance' => 2000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Bank Transfer'], ['status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-2026-002',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 3000.00,
        'grand_total' => 3000.00,
        'paid_amount' => 1000.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    $postData = [
        'sales_id' => $sale->id,
        'amount' => 1000.00,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Bank Transfer',
        'account_id' => $account->id,
        'payment_note' => 'Partial payment batch 2 - TxnRef #BRAC998',
    ];

    $response = $this->actingAs($user)->post(route('sales.payments.store'), $postData);

    $response->assertRedirect(route('sales.list'));
    $response->assertSessionHas('success');

    // 1. Verify DbSalePayment
    $payment = DbSalePayment::where('sales_id', $sale->id)->where('payment', 1000.00)->latest()->first();
    expect($payment)->not->toBeNull();
    expect($payment->payment_type)->toBe('Bank Transfer');
    expect($payment->payment_note)->toBe('Partial payment batch 2 - TxnRef #BRAC998');
    expect($payment->account_id)->toBe($account->id);

    // 2. Verify AcTransaction credit entry
    $transaction = AcTransaction::where('ref_salespayments_id', $payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_type)->toBe('SALES PAYMENT');
    expect($transaction->credit_account_id)->toBe($account->id);
    expect((float)$transaction->credit_amt)->toBe(1000.00);
    expect((float)$transaction->debit_amt)->toBe(0.00);
    expect($transaction->note)->toContain('SA-2026-002');

    // 3. Verify Account balance increment: 2000 + 1000 = 3000
    $account->refresh();
    expect((float)$account->balance)->toBe(3000.00);

    // 4. Verify DbSale paid_amount and payment_status
    $sale->refresh();
    expect((float)$sale->paid_amount)->toBe(2000.00);
    expect($sale->payment_status)->toBe('Partial');
});

test('4. Full settlement payment transitions sale status from Partial to Paid', function () {
    $user = getReceivePaymentTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Sadia Islam',
        'customer_code' => 'CU-000103',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main WH', 'status' => 1]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'City Bank Account',
        'account_code' => 'ACC-CITY-103',
        'balance' => 100.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash'], ['status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-2026-003',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 800.00,
        'grand_total' => 800.00,
        'paid_amount' => 500.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    // Pay exact remaining due: 300.00
    $response = $this->actingAs($user)->post(route('sales.payments.store'), [
        'sales_id' => $sale->id,
        'amount' => 300.00,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'payment_note' => 'Final settlement',
    ]);

    $response->assertRedirect(route('sales.list'));
    $response->assertSessionHas('success');

    $sale->refresh();
    expect((float)$sale->paid_amount)->toBe(800.00);
    expect($sale->payment_status)->toBe('Paid');

    $account->refresh();
    expect((float)$account->balance)->toBe(400.00); // 100 + 300
});

test('5. Overpayment attempt exceeding balance due is rejected', function () {
    $user = getReceivePaymentTestUser();

    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Tariq Hasan', 'status' => 1]);
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main WH', 'status' => 1]);
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Counter',
        'balance' => 500.00,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-2026-004',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_amount' => 700.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    // Remaining due is 300.00, try paying 500.00
    $response = $this->actingAs($user)->from(route('sales.payments.receive', $sale->id))->post(route('sales.payments.store'), [
        'sales_id' => $sale->id,
        'amount' => 500.00,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'account_id' => $account->id,
    ]);

    $response->assertRedirect(route('sales.payments.receive', $sale->id));
    $response->assertSessionHas('error');

    // Confirm no payment record created
    expect(DbSalePayment::where('sales_id', $sale->id)->count())->toBe(0);

    // Confirm balance untouched
    $account->refresh();
    expect((float)$account->balance)->toBe(500.00);
});

test('6. Fully settled sale page renders fully paid guard notice', function () {
    $user = getReceivePaymentTestUser();

    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Farhana', 'status' => 1]);
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main WH', 'status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-2026-005',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 500.00,
        'grand_total' => 500.00,
        'paid_amount' => 500.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('sales.payments.receive', $sale->id));
    
    $response->assertOk();
    $response->assertSee('Invoice Fully Settled');
    $response->assertSee('No additional payment collection is required');
    $response->assertDontSee('name="amount"', false);
});
