<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbEmiSale;
use App\Models\DbEmiSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

/**
 * Functional regression tests for the EMI remediation pass (Part A).
 * Covers: A1 paid_amount sync + overpay/account guards, A2 generic-payment
 * EMI guard, A3 EMI-aware payment deletion, A4 POS edit EMI guard, A5 overdue
 * rendering data, A6 store scoping, A7 filter-preserving pagination.
 */

function emiFixEnv(int $storeId = 1)
{
    if (!DbStore::where('id', $storeId)->exists()) {
        DbStore::create([
            'id' => $storeId,
            'store_name' => 'EMI Fix Store ' . $storeId,
            'status' => 1,
            'mobile' => '+880170000000' . $storeId,
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => 1,
        'permissions' => ['sales_view', 'sales_add', 'pos', 'accounts_view'],
    ]);

    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => $storeId,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'EMI Fix WH ' . $storeId,
        'status' => 1,
        'store_id' => $storeId,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'EMI Fix Customer ' . $storeId,
        'mobile' => '+88018112233' . $storeId,
        'customer_type' => 'emi',
        'status' => 1,
        'store_id' => $storeId,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'EMI Fix Item',
        'item_code' => 'EMI-FIX-' . $storeId,
        'sales_price' => 1000.00,
        'purchase_price' => 600.00,
        'stock' => 10,
        'status' => 1,
        'store_id' => $storeId,
    ]);

    $account = AcAccount::create([
        'store_id' => $storeId,
        'account_name' => 'EMI Fix Cash ' . $storeId,
        'account_code' => 'ACC-EMI-' . $storeId,
        'balance' => 10000.00,
        'status' => 1,
    ]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

/**
 * Create an EMI sale directly (bypassing the full POS EMI payload) so tests
 * are focused on the pay/delete/edit flows rather than sale creation.
 */
function makeEmiSale(array $env, array $overrides = []): array
{
    $sale = DbSale::create(array_merge([
        'store_id' => $env['user']->store_id,
        'warehouse_id' => $env['warehouse']->id,
        'customer_id' => $env['customer']->id,
        'sales_code' => 'EMI-SALE-' . strtoupper(uniqid()),
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_amount' => 100.00, // initial pay
        'payment_status' => 'Partial',
        'created_by' => $env['user']->id,
        'status' => 1,
    ], $overrides));

    $emiSale = DbEmiSale::create([
        'sale_id' => $sale->id,
        'customer_id' => $env['customer']->id,
        'loan_amount' => 900.00,
        'total_payable' => 900.00,
        'duration_months' => 3,
        'monthly_installment' => 300.00,
        'processing_fee' => 0,
        'start_date' => Carbon::today()->format('Y-m-d'),
        'status' => 'Active',
    ]);

    $schedules = [];
    for ($i = 1; $i <= 3; $i++) {
        $schedules[] = DbEmiSchedule::create([
            'emi_sale_id' => $emiSale->id,
            'installment_no' => $i,
            'due_date' => Carbon::today()->addMonths($i)->format('Y-m-d'),
            'amount' => 300.00,
            'paid_amount' => 0,
            'status' => 'Pending',
        ]);
    }

    return compact('sale', 'emiSale', 'schedules');
}

// ─── A1: paid_amount sync ───────────────────────────────────────────────

test('A1. Paying an installment syncs db_sales.paid_amount and flips status', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    $response = $this->actingAs($env['user'])->post(route('sales.emi.pay'), [
        'schedule_id' => $fx['schedules'][0]->id,
        'amount' => 300.00,
        'account_id' => $env['account']->id,
    ]);

    $response->assertSessionHas('success');

    $fx['sale']->refresh();
    // 100 initial + 300 installment = 400
    expect((float) $fx['sale']->paid_amount)->toBe(400.00);
    expect($fx['sale']->payment_status)->toBe('Partial');

    $schedule = $fx['schedules'][0]->refresh();
    expect((float) $schedule->paid_amount)->toBe(300.00);
    expect($schedule->status)->toBe('Paid');

    // Ledger + account balance written
    $payment = DbSalePayment::where('sales_id', $fx['sale']->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->emi_schedule_id)->toBe($schedule->id);
    expect(AcTransaction::where('ref_salespayments_id', $payment->id)->count())->toBe(1);
    $env['account']->refresh();
    expect((float) $env['account']->balance)->toBe(10300.00);
});

test('A1. Paying all installments completes the EMI sale with paid_amount == collected', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    foreach ($fx['schedules'] as $s) {
        $this->actingAs($env['user'])->post(route('sales.emi.pay'), [
            'schedule_id' => $s->id,
            'amount' => 300.00,
            'account_id' => $env['account']->id,
        ]);
    }

    $fx['sale']->refresh();
    // 100 initial + 900 installments = 1000 = grand_total
    expect((float) $fx['sale']->paid_amount)->toBe(1000.00);
    expect($fx['sale']->payment_status)->toBe('Paid');

    $fx['emiSale']->refresh();
    expect($fx['emiSale']->status)->toBe('Completed');

    // Σ db_emi_schedule.paid_amount == db_sales.paid_amount - initial pay
    $scheduleSum = (float) DbEmiSchedule::where('emi_sale_id', $fx['emiSale']->id)->sum('paid_amount');
    expect($scheduleSum)->toBe(900.00);
});

test('A1. Overpaying a single installment is rejected', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    $response = $this->actingAs($env['user'])->from(route('sales.emi.show', $fx['emiSale']->id))
        ->post(route('sales.emi.pay'), [
            'schedule_id' => $fx['schedules'][0]->id,
            'amount' => 301.00, // exceeds remaining 300
            'account_id' => $env['account']->id,
        ]);

    $response->assertSessionHas('error');

    $fx['schedules'][0]->refresh();
    expect((float) $fx['schedules'][0]->paid_amount)->toBe(0.00);
    expect($fx['schedules'][0]->status)->toBe('Pending');

    $fx['sale']->refresh();
    expect((float) $fx['sale']->paid_amount)->toBe(100.00);
});

test('A1. Payment without an account_id is rejected', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    $response = $this->actingAs($env['user'])->post(route('sales.emi.pay'), [
        'schedule_id' => $fx['schedules'][0]->id,
        'amount' => 300.00,
        'account_id' => null,
    ]);

    $response->assertSessionHas('error');
    expect(DbSalePayment::count())->toBe(0);
});

// ─── A2: generic payment EMI guard ──────────────────────────────────────

test('A2. Generic storePayment is blocked for EMI sales', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    $response = $this->actingAs($env['user'])->from(route('sales.payments.receive', $fx['sale']->id))
        ->post(route('sales.payments.store'), [
            'sales_id' => $fx['sale']->id,
            'amount' => 100.00,
            'payment_date' => Carbon::today()->format('Y-m-d'),
            'payment_type' => 'Cash',
            'account_id' => $env['account']->id,
        ]);

    $response->assertSessionHas('error');
    $response->assertSessionHas('error', function ($msg) {
        return str_contains($msg, 'EMI');
    });

    $fx['sale']->refresh();
    expect((float) $fx['sale']->paid_amount)->toBe(100.00);
    expect(DbSalePayment::count())->toBe(0);
});

// ─── A3: EMI-aware payment deletion ─────────────────────────────────────

test('A3. Deleting an EMI installment payment reverts the schedule row', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    // Pay installment 1 fully, then delete that payment.
    $this->actingAs($env['user'])->post(route('sales.emi.pay'), [
        'schedule_id' => $fx['schedules'][0]->id,
        'amount' => 300.00,
        'account_id' => $env['account']->id,
    ]);

    $payment = DbSalePayment::where('sales_id', $fx['sale']->id)->first();
    $env['account']->refresh();
    $balanceAfterPay = (float) $env['account']->balance;

    $response = $this->actingAs($env['user'])->from(route('sales.payments'))
        ->delete(route('sales.payments.destroy', $payment->id));

    $response->assertSessionHas('success');

    // Schedule reverted
    $fx['schedules'][0]->refresh();
    expect((float) $fx['schedules'][0]->paid_amount)->toBe(0.00);
    expect($fx['schedules'][0]->status)->toBe('Pending');

    // Sale paid_amount back to initial pay
    $fx['sale']->refresh();
    expect((float) $fx['sale']->paid_amount)->toBe(100.00);

    // Ledger + account reversed
    expect(AcTransaction::where('ref_salespayments_id', $payment->id)->count())->toBe(0);
    $env['account']->refresh();
    expect((float) $env['account']->balance)->toBe($balanceAfterPay - 300.00);
});

test('A3. Deleting the final payment un-completes the EMI sale', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    // Fully settle every installment.
    foreach ($fx['schedules'] as $s) {
        $this->actingAs($env['user'])->post(route('sales.emi.pay'), [
            'schedule_id' => $s->id,
            'amount' => 300.00,
            'account_id' => $env['account']->id,
        ]);
    }

    $fx['emiSale']->refresh();
    expect($fx['emiSale']->status)->toBe('Completed');

    $lastPayment = DbSalePayment::where('sales_id', $fx['sale']->id)->orderBy('id', 'desc')->first();

    $this->actingAs($env['user'])->delete(route('sales.payments.destroy', $lastPayment->id));

    $fx['emiSale']->refresh();
    expect($fx['emiSale']->status)->toBe('Active');

    $lastSchedule = DbEmiSchedule::find($lastPayment->emi_schedule_id);
    expect($lastSchedule)->not->toBeNull();
    expect((float) $lastSchedule->paid_amount)->toBe(0.00);
    expect($lastSchedule->status)->toBe('Pending');
});

// ─── A4: POS edit EMI guard ─────────────────────────────────────────────

test('A4. POS edit-save is blocked for EMI sales without touching rows', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    // Create a normal payment + ledger row that must NOT be wiped.
    $payment = DbSalePayment::create([
        'store_id' => $env['user']->store_id,
        'sales_id' => $fx['sale']->id,
        'customer_id' => $env['customer']->id,
        'account_id' => $env['account']->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 100.00,
        'created_by' => $env['user']->id,
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), [
        'sale_id' => $fx['sale']->id,
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [
            [
                'id' => $env['item']->id,
                'name' => $env['item']->item_name,
                'price' => 1000.00,
                'qty' => 1,
                'total' => 1000.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ],
        ],
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'paid_amount' => 1000.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', fn ($m) => str_contains($m, 'EMI'));

    // Nothing was altered.
    expect(DbSalePayment::find($payment->id))->not->toBeNull();
    expect(DbEmiSchedule::count())->toBe(3);
    $fx['sale']->refresh();
    expect((float) $fx['sale']->paid_amount)->toBe(100.00);
});

// ─── A6: store scoping ──────────────────────────────────────────────────

test('A6. EMI list only shows the current store\'s sales', function () {
    $env1 = emiFixEnv(1);
    $env2 = emiFixEnv(2);

    $fx1 = makeEmiSale($env1);
    $fx2 = makeEmiSale($env2);

    $response = $this->actingAs($env1['user'])->get(route('sales.emi.list'));
    $response->assertOk();
    $response->assertSee($fx1['sale']->sales_code);
    $response->assertDontSee($fx2['sale']->sales_code);
});

test('A6. Paying another store\'s installment is rejected', function () {
    $env1 = emiFixEnv(1);
    $env2 = emiFixEnv(2);
    $fx2 = makeEmiSale($env2);

    // User from store 1 tries to pay store 2's schedule.
    $response = $this->actingAs($env1['user'])->post(route('sales.emi.pay'), [
        'schedule_id' => $fx2['schedules'][0]->id,
        'amount' => 300.00,
        'account_id' => $env1['account']->id,
    ]);

    $response->assertSessionHas('error');
    expect(DbSalePayment::count())->toBe(0);
});

// ─── A7: pagination appends ─────────────────────────────────────────────

test('A7. EMI list pagination preserves active filters in URLs', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    // Create enough EMI sales to force page 2 (limit 1 → 2 pages).
    for ($i = 0; $i < 2; $i++) {
        makeEmiSale($env);
    }

    $response = $this->actingAs($env['user'])->get(route('sales.emi.list', ['limit' => 1, 'customer_id' => $env['customer']->id]));
    $response->assertOk();

    // The pagination links must carry the filter query string.
    $response->assertSee('customer_id=' . $env['customer']->id, false);
    $response->assertSee('limit=1', false);
    $response->assertSee('page=2', false);
});

// ─── A5: overdue data surfaced on details page ──────────────────────────

test('A5. Overdue installments are passed to the details view', function () {
    $env = emiFixEnv();
    $fx = makeEmiSale($env);

    // Backdate installment 1 to yesterday.
    $fx['schedules'][0]->update(['due_date' => Carbon::yesterday()->format('Y-m-d')]);

    $response = $this->actingAs($env['user'])->get(route('sales.emi.show', $fx['emiSale']->id));
    $response->assertOk();

    // The view renders an Overdue badge for the backdated, unpaid installment.
    $response->assertSee('Overdue', false);
});
