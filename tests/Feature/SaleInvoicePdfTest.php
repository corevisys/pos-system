<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbItem;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbSalePayment;
use App\Models\DbEmiSale;
use App\Models\DbEmiSchedule;
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
        'permissions' => ['sales_view', 'sales_add'],
    ]);
});

function createDummySale($itemCount = 1, $withEmi = false) {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'warehouse_name' => 'Main Test Warehouse',
        'status' => 1,
        'store_id' => 1,
    ]);

    $customer = DbCustomer::create([
        'customer_name' => 'John Doe Corp',
        'mobile' => '+8801811111111',
        'address' => '456 Client Road, Dhaka',
        'status' => 1,
        'store_id' => 1,
    ]);

    $sale = DbSale::create([
        'sales_code' => 'SA-TEST-' . rand(1000, 9999),
        'sales_date' => now()->toDateString(),
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'subtotal' => 1000 * $itemCount,
        'tax_amt' => 50,
        'discount_amt' => 20,
        'grand_total' => (1000 * $itemCount) + 30,
        'paid_amount' => (1000 * $itemCount) + 30,
        'payment_status' => 'Paid',
    ]);

    for ($i = 1; $i <= $itemCount; $i++) {
        $item = DbItem::create([
            'item_name' => "Product Item #{$i}",
            'item_code' => "SKU-00{$i}",
            'sales_price' => 1000,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbSaleItem::create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'sales_qty' => 1,
            'price_per_unit' => 1000,
            'total_cost' => 1000,
            'store_id' => 1,
        ]);
    }

    DbSalePayment::create([
        'sales_id' => $sale->id,
        'payment_type' => 'Cash',
        'payment' => $sale->grand_total,
        'payment_date' => now()->toDateString(),
        'store_id' => 1,
    ]);

    if ($withEmi) {
        $emi = DbEmiSale::create([
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'loan_amount' => $sale->grand_total,
            'total_payable' => $sale->grand_total,
            'duration_months' => 6,
            'monthly_installment' => $sale->grand_total / 6,
            'processing_fee' => 150,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        for ($m = 1; $m <= 6; $m++) {
            DbEmiSchedule::create([
                'emi_sale_id' => $emi->id,
                'installment_no' => $m,
                'due_date' => now()->addMonths($m)->toDateString(),
                'amount' => $sale->grand_total / 6,
                'paid_amount' => $m == 1 ? $sale->grand_total / 6 : 0,
                'status' => $m == 1 ? 'paid' : 'pending',
            ]);
        }
    }

    return [$user, $sale];
}

test('unauthenticated users are redirected to login when viewing invoice', function () {
    [$user, $sale] = createDummySale(1);
    $response = $this->get(route('sales.invoice', ['id' => $sale->id]));
    $response->assertRedirect(route('login'));
});

test('invoice view renders on-screen with selected paper size', function () {
    [$user, $sale] = createDummySale(2);

    $response = $this->actingAs($user)->get(route('sales.invoice', ['id' => $sale->id, 'paper_size' => 'letter']));
    $response->assertStatus(200);
    $response->assertViewIs('module.sales.invoice.view');
    $response->assertSeeText($sale->sales_code);
    $response->assertSeeText('COREVISYS TEST STORE');
    $response->assertSeeText('John Doe Corp');
});

test('invoice pdf stream mode returns inline disposition for A4 and Letter', function () {
    [$user, $sale] = createDummySale(3);

    // 1. A4 Stream
    $responseA4 = $this->actingAs($user)->get(route('sales.invoice', [
        'id' => $sale->id,
        'mode' => 'stream',
        'paper_size' => 'a4'
    ]));

    $responseA4->assertStatus(200);
    expect($responseA4->headers->get('Content-Type'))->toBe('application/pdf');
    expect($responseA4->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($responseA4->headers->get('Content-Disposition'))->toContain('A4');
    expect(strlen($responseA4->getContent()))->toBeGreaterThan(2000);

    // 2. Letter Stream
    $responseLetter = $this->actingAs($user)->get(route('sales.invoice', [
        'id' => $sale->id,
        'mode' => 'stream',
        'paper_size' => 'letter'
    ]));

    $responseLetter->assertStatus(200);
    expect($responseLetter->headers->get('Content-Type'))->toBe('application/pdf');
    expect($responseLetter->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($responseLetter->headers->get('Content-Disposition'))->toContain('LETTER');
    expect(strlen($responseLetter->getContent()))->toBeGreaterThan(2000);
});

test('invoice pdf download mode returns attachment disposition', function () {
    [$user, $sale] = createDummySale(2);

    $response = $this->actingAs($user)->get(route('sales.invoice', [
        'id' => $sale->id,
        'mode' => 'download',
        'paper_size' => 'a4'
    ]));

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('long invoice with 15 items and EMI schedule renders without 500 error', function () {
    [$user, $sale] = createDummySale(15, true);

    $response = $this->actingAs($user)->get(route('sales.invoice', [
        'id' => $sale->id,
        'mode' => 'download',
        'paper_size' => 'letter'
    ]));

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(3000);
});

test('invoice view renders store website when configured in store_website column', function () {
    $store = DbStore::first();
    $store->update([
        'store_website' => 'https://pos.corevisys.com'
    ]);

    [$user, $sale] = createDummySale(1);

    $response = $this->actingAs($user)->get(route('sales.invoice', ['id' => $sale->id]));
    $response->assertStatus(200);
    $response->assertSeeText('pos.corevisys.com');
});

