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
use App\Models\AcTransaction;
use App\Models\DbPaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

function getPaymentDeleteTestUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'currency_code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Payment Delete Test Store',
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
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_include_pos_sales_payments_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

function makePaymentDeleteFixture(User $user): array {
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Delete Payment Cash',
        'account_code' => 'ACC-DELPAY-001',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Delete Payment WH',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Payment Delete Customer',
        'customer_code' => 'CUST-DELPAY-001',
        'mobile' => '01799000001',
        'status' => 1,
    ]);

    DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash'], ['status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-DELPAY-001',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_amount' => 800.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    // Two payments: 500 + 300 = 800
    $payment500 = DbSalePayment::create([
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

    $payment300 = DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $customer->id,
        'account_id' => $account->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 300.00,
        'created_by' => $user->id,
        'status' => 1,
    ]);

    // Matching ledger entries
    foreach ([$payment500, $payment300] as $p) {
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => $p->payment_date,
            'transaction_type' => 'SALES PAYMENT',
            'payment_code' => $p->payment_type,
            'credit_account_id' => $p->account_id,
            'debit_account_id' => null,
            'debit_amt' => 0,
            'credit_amt' => $p->payment,
            'note' => 'Sales Payment: ' . $sale->sales_code,
            'ref_salespayments_id' => $p->id,
            'customer_id' => $sale->customer_id,
            'created_by' => $user->id,
            'created_date' => date('Y-m-d'),
        ]);
    }

    // Account balance already reflects both payments: 1000 + 800 = 1800
    $account->refresh();
    $account->increment('balance', 800.00);

    return [
        'user' => $user,
        'account' => $account,
        'sale' => $sale,
        'payment500' => $payment500,
        'payment300' => $payment300,
    ];
}

test('1. Deleting a payment reverses account balance, removes ledger row, and decrements paid_amount (Partial stays Partial)', function () {
    $fx = makePaymentDeleteFixture(getPaymentDeleteTestUser());

    $response = $this->actingAs($fx['user'])
        ->from(route('sales.payments'))
        ->delete(route('sales.payments.destroy', $fx['payment500']->id));

    $response->assertRedirect(route('sales.payments'));
    $response->assertSessionHas('success');

    // 1. Payment row gone
    expect(DbSalePayment::find($fx['payment500']->id))->toBeNull();

    // 2. Ledger row gone
    expect(AcTransaction::where('ref_salespayments_id', $fx['payment500']->id)->count())->toBe(0);

    // 3. Account balance decreased by exactly 500: 1800 - 500 = 1300
    $fx['account']->refresh();
    expect((float)$fx['account']->balance)->toBe(1300.00);

    // 4. Sale paid_amount 800 - 500 = 300, status still Partial
    $fx['sale']->refresh();
    expect((float)$fx['sale']->paid_amount)->toBe(300.00);
    expect($fx['sale']->payment_status)->toBe('Partial');

    // 5. Remaining payment still intact with its ledger row
    expect(DbSalePayment::find($fx['payment300']->id))->not->toBeNull();
    expect(AcTransaction::where('ref_salespayments_id', $fx['payment300']->id)->count())->toBe(1);
});

test('2. Deleting the final payment flips sale status from Paid to Partial', function () {
    $fx = makePaymentDeleteFixture(getPaymentDeleteTestUser());

    // First fully settle the sale by adding a 200 payment (800 + 200 = 1000 = grand_total)
    $account = $fx['account'];
    $sale = $fx['sale'];

    $finalPayment = DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $sale->customer_id,
        'account_id' => $account->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 200.00,
        'created_by' => $fx['user']->id,
        'status' => 1,
    ]);

    AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => $finalPayment->payment_date,
        'transaction_type' => 'SALES PAYMENT',
        'payment_code' => $finalPayment->payment_type,
        'credit_account_id' => $finalPayment->account_id,
        'debit_account_id' => null,
        'debit_amt' => 0,
        'credit_amt' => $finalPayment->payment,
        'note' => 'Sales Payment: ' . $sale->sales_code,
        'ref_salespayments_id' => $finalPayment->id,
        'customer_id' => $sale->customer_id,
        'created_by' => $fx['user']->id,
        'created_date' => date('Y-m-d'),
    ]);

    $account->increment('balance', 200.00);
    $sale->update(['paid_amount' => 1000.00, 'payment_status' => 'Paid']);

    // Now delete the final payment
    $response = $this->actingAs($fx['user'])
        ->from(route('sales.payments'))
        ->delete(route('sales.payments.destroy', $finalPayment->id));

    $response->assertRedirect(route('sales.payments'));
    $response->assertSessionHas('success');

    $sale->refresh();
    $account->refresh();

    expect((float)$sale->paid_amount)->toBe(800.00);
    expect($sale->payment_status)->toBe('Partial'); // Paid -> Partial
    expect((float)$account->balance)->toBe(1800.00); // 1800 + 200 - 200
    expect(AcTransaction::where('ref_salespayments_id', $finalPayment->id)->count())->toBe(0);
});

test('3. Deleting a payment that would make paid_amount negative is blocked', function () {
    $fx = makePaymentDeleteFixture(getPaymentDeleteTestUser());

    // Reduce paid_amount artificially below the payment being deleted
    // (e.g. data inconsistency where payment sum > paid_amount)
    $fx['sale']->update(['paid_amount' => 100.00]);

    $response = $this->actingAs($fx['user'])
        ->from(route('sales.payments'))
        ->delete(route('sales.payments.destroy', $fx['payment500']->id));

    $response->assertRedirect(route('sales.payments'));
    $response->assertSessionHas('error');

    // Nothing was changed
    expect(DbSalePayment::find($fx['payment500']->id))->not->toBeNull();
    expect(AcTransaction::where('ref_salespayments_id', $fx['payment500']->id)->count())->toBe(1);
    $fx['account']->refresh();
    expect((float)$fx['account']->balance)->toBe(1800.00);
    $fx['sale']->refresh();
    expect((float)$fx['sale']->paid_amount)->toBe(100.00);
    expect($fx['sale']->payment_status)->toBe('Partial');
});

test('4. Unauthenticated users cannot delete a payment', function () {
    $response = $this->delete('/sales/payments/1');
    $response->assertRedirect('/login');
});

test('5. Payment recording failure shows generic message and logs the real error', function () {
    $user = getPaymentDeleteTestUser();

    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Failure Customer', 'status' => 1]);
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Failure WH', 'status' => 1]);
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Failure Account',
        'balance' => 100.00,
        'status' => 1,
    ]);

    DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash'], ['status' => 1]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-FAIL-001',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 500.00,
        'grand_total' => 500.00,
        'paid_amount' => 0.00,
        'payment_status' => 'Unpaid',
        'status' => 1,
    ]);

    // Force the SMS trigger to throw after the payment + ledger rows are created,
    // so the transaction rolls back and the generic catch path runs.
    $mockSms = Mockery::mock(\App\SMS\Services\SmsTriggerService::class);
    $mockSms->shouldReceive('trigger')->once()->andThrow(new \Exception('SMS gateway down'));

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message, $context) => str_contains($message, 'Payment recording failed'));

    $this->app->instance(\App\SMS\Services\SmsTriggerService::class, $mockSms);

    $response = $this->actingAs($user)
        ->from(route('sales.payments.receive', $sale->id))
        ->post(route('sales.payments.store'), [
            'sales_id' => $sale->id,
            'amount' => 200.00,
            'payment_date' => Carbon::today()->format('Y-m-d'),
            'payment_type' => 'Cash',
            'account_id' => $account->id,
            'payment_note' => 'Should roll back',
        ]);

    $response->assertRedirect(route('sales.payments.receive', $sale->id));
    $response->assertSessionHas('error');
    // Generic message, no exception text
    $response->assertSessionHas('error', 'This payment could not be recorded. Please try again.');

    // Transaction rolled back cleanly
    expect(DbSalePayment::where('sales_id', $sale->id)->count())->toBe(0);
    expect(AcTransaction::where('ref_salespayments_id', '!=', null)->count())->toBe(0);
    $account->refresh();
    expect((float)$account->balance)->toBe(100.00);
    $sale->refresh();
    expect((float)$sale->paid_amount)->toBe(0.00);
    expect($sale->payment_status)->toBe('Unpaid');
});
