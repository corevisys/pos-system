<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbEmiSale;
use App\Models\DbEmiSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

if (!function_exists('customerGuardEnv')) {
    function customerGuardEnv(): array
    {
        if (!DbStore::where('id', 1)->exists()) {
            DbStore::create([
                'id' => 1,
                'store_name' => 'Customer Guard Store',
                'status' => 1,
                'mobile' => '+8801700000000',
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
            'store_id' => 1,
        ]);

        return compact('user');
    }
}

if (!function_exists('makeGuardCustomer')) {
    function makeGuardCustomer(array $env, string $type = 'regular'): DbCustomer
    {
        return DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Guard Customer ' . strtoupper(uniqid()),
            'customer_type' => $type,
            'mobile' => '017' . random_int(10000000, 99999999),
            'customer_code' => 'GU-' . strtoupper(uniqid()),
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }
}

if (!function_exists('makeGuardSale')) {
    function makeGuardSale(DbCustomer $customer, array $env): DbSale
    {
        return DbSale::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'sales_code' => 'GS-' . strtoupper(uniqid()),
            'sales_date' => Carbon::today()->format('Y-m-d'),
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 0,
            'payment_status' => 'Due',
            'created_by' => $env['user']->id,
            'status' => 1,
        ]);
    }
}

// ─── 1a. Customer with sales history ──────────────────────────────────────

test('1a. Deleting a customer with existing sales is BLOCKED and sales untouched', function () {
    $env = customerGuardEnv();
    $customer = makeGuardCustomer($env);
    $sale = makeGuardSale($customer, $env);

    $response = $this->actingAs($env['user'])
        ->from(route('contacts.customers.list'))
        ->delete(route('contacts.customers.delete', $customer->id));

    $response->assertSessionHas('error', 'This customer has sales/payment history and cannot be deleted. Deactivate the customer instead.');

    // Customer row still exists, delete_bit unchanged (0)
    $fresh = DbCustomer::find($customer->id);
    expect($fresh)->not->toBeNull();
    expect((int) $fresh->delete_bit)->toBe(0);

    // Sales untouched
    expect(DbSale::find($sale->id))->not->toBeNull();
    expect(DbSale::where('customer_id', $customer->id)->count())->toBe(1);
});

// ─── 1b. Customer with EMI history ────────────────────────────────────────

test('1b. Deleting a customer with EMI history is BLOCKED — EMI + schedule rows untouched', function () {
    $env = customerGuardEnv();
    $customer = makeGuardCustomer($env, 'emi');
    $sale = makeGuardSale($customer, $env);

    $emiSale = DbEmiSale::create([
        'sale_id' => $sale->id,
        'customer_id' => $customer->id,
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

    $response = $this->actingAs($env['user'])
        ->from(route('contacts.customers.list'))
        ->delete(route('contacts.customers.delete', $customer->id));

    $response->assertSessionHas('error');

    // Customer unchanged
    $fresh = DbCustomer::find($customer->id);
    expect($fresh)->not->toBeNull();
    expect((int) $fresh->delete_bit)->toBe(0);

    // EMI sale and its schedule rows must NOT have been cascade-deleted
    expect(DbEmiSale::find($emiSale->id))->not->toBeNull();
    expect(DbEmiSchedule::where('emi_sale_id', $emiSale->id)->count())->toBe(3);
});

// ─── 1c. Customer with payment history only ───────────────────────────────

test('1c. Deleting a customer with payment history only is BLOCKED — payment untouched', function () {
    $env = customerGuardEnv();
    $customer = makeGuardCustomer($env);

    // A salespayment row referencing the customer but no sale (sales_id nullable).
    $payment = DbSalePayment::create([
        'store_id' => 1,
        'customer_id' => $customer->id,
        'payment_date' => Carbon::today()->format('Y-m-d'),
        'payment_type' => 'Cash',
        'payment' => 50.00,
        'created_by' => $env['user']->id,
        'status' => 1,
    ]);

    $response = $this->actingAs($env['user'])
        ->from(route('contacts.customers.list'))
        ->delete(route('contacts.customers.delete', $customer->id));

    $response->assertSessionHas('error');

    $fresh = DbCustomer::find($customer->id);
    expect($fresh)->not->toBeNull();
    expect((int) $fresh->delete_bit)->toBe(0);
    expect(DbSalePayment::find($payment->id))->not->toBeNull();
});

// ─── 1d. Customer with zero history deletes successfully ─────────────────

test('1d. Deleting a customer with zero history SUCCEEDS and hides it from the list', function () {
    $env = customerGuardEnv();
    $customer = makeGuardCustomer($env);
    $name = $customer->customer_name;

    $response = $this->actingAs($env['user'])
        ->from(route('contacts.customers.list'))
        ->delete(route('contacts.customers.delete', $customer->id));

    $response->assertSessionHas('success', 'Customer deleted successfully.');

    $fresh = DbCustomer::find($customer->id);
    expect($fresh)->not->toBeNull();
    expect((int) $fresh->delete_bit)->toBe(1);

    // index() default query filters delete_bit = 0 — must not appear
    $listResponse = $this->actingAs($env['user'])->get(route('contacts.customers.list'));
    $listResponse->assertOk();
    $listResponse->assertDontSee($name);
});

// ─── 1e. withCount relations return exact counts ─────────────────────────

test('1e. withCount sales/emiSales/payments return exact real counts', function () {
    $env = customerGuardEnv();
    $customer = makeGuardCustomer($env, 'emi');

    // 2 sales
    $sale1 = makeGuardSale($customer, $env);
    $sale2 = makeGuardSale($customer, $env);

    // 1 EMI on sale1
    DbEmiSale::create([
        'sale_id' => $sale1->id,
        'customer_id' => $customer->id,
        'loan_amount' => 100.00,
        'total_payable' => 100.00,
        'duration_months' => 1,
        'monthly_installment' => 100.00,
        'processing_fee' => 0,
        'start_date' => Carbon::today()->format('Y-m-d'),
        'status' => 'Active',
    ]);

    // 3 payments (one tied to sale2, two with no sale — customer-level)
    DbSalePayment::create(['store_id' => 1, 'customer_id' => $customer->id, 'sales_id' => $sale2->id, 'payment' => 10.00, 'payment_date' => Carbon::today()->format('Y-m-d'), 'status' => 1]);
    DbSalePayment::create(['store_id' => 1, 'customer_id' => $customer->id, 'payment' => 20.00, 'payment_date' => Carbon::today()->format('Y-m-d'), 'status' => 1]);
    DbSalePayment::create(['store_id' => 1, 'customer_id' => $customer->id, 'payment' => 30.00, 'payment_date' => Carbon::today()->format('Y-m-d'), 'status' => 1]);

    $counted = DbCustomer::withCount(['sales', 'emiSales', 'payments'])->find($customer->id);

    expect((int) $counted->sales_count)->toBe(2);
    expect((int) $counted->emi_sales_count)->toBe(1);
    expect((int) $counted->payments_count)->toBe(3);

    // Sanity: a fresh customer counts all zero
    $empty = makeGuardCustomer($env);
    $emptyCounted = DbCustomer::withCount(['sales', 'emiSales', 'payments'])->find($empty->id);
    expect((int) $emptyCounted->sales_count)->toBe(0);
    expect((int) $emptyCounted->emi_sales_count)->toBe(0);
    expect((int) $emptyCounted->payments_count)->toBe(0);
});
