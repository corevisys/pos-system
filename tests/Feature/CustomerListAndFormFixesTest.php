<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (!function_exists('listFixEnv')) {
    function listFixEnv(): array
    {
        if (!DbStore::where('id', 1)->exists()) {
            DbStore::create([
                'id' => 1,
                'store_name' => 'List Fix Store',
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

if (!function_exists('makeListCustomer')) {
    function makeListCustomer(string $name, string $mobile): DbCustomer
    {
        return DbCustomer::create([
            'store_id' => 1,
            'customer_name' => $name,
            'customer_type' => 'regular',
            'mobile' => $mobile,
            'customer_code' => 'LF-' . strtoupper(uniqid()),
            'opening_balance' => 0,
            'sales_return_due' => 0,
            'credit_limit' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }
}

// ─── 5b. Show Entries limit ───────────────────────────────────────────────

test('5b. index() honours a valid limit and clamps an out-of-range limit to 10', function () {
    $env = listFixEnv();

    // 12 customers → page of 25 shows all 12; page of 10 shows 10.
    for ($i = 1; $i <= 12; $i++) {
        makeListCustomer('Limit Customer ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), '017' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT));
    }

    $r25 = $this->actingAs($env['user'])->get(route('contacts.customers.list', ['limit' => 25]));
    $r25->assertOk();
    expect($r25->viewData('customers')->perPage())->toBe(25);
    expect($r25->viewData('customers')->total())->toBe(12);

    $r10 = $this->actingAs($env['user'])->get(route('contacts.customers.list', ['limit' => 10]));
    $r10->assertOk();
    expect($r10->viewData('customers')->perPage())->toBe(10);
    expect($r10->viewData('customers')->total())->toBe(12);
    expect(count($r10->viewData('customers')->items()))->toBe(10);

    // Out-of-range / invalid limit is clamped to the default of 10 — never passed raw.
    $rBad = $this->actingAs($env['user'])->get(route('contacts.customers.list', ['limit' => 999]));
    $rBad->assertOk();
    expect($rBad->viewData('customers')->perPage())->toBe(10);
});

// ─── 5c. Exports ──────────────────────────────────────────────────────────

test('5c1. export=csv returns a CSV stream of the filtered result set', function () {
    $env = listFixEnv();

    makeListCustomer('CSV Export Alpha', '01710000001');
    makeListCustomer('CSV Export Beta', '01710000002');
    // A third matching the search term to prove filtering is respected
    makeListCustomer('CSV Export Gamma Target', '01710000003');

    $response = $this->actingAs($env['user'])->get(route('contacts.customers.list', ['export' => 'csv', 'search' => 'Target']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    // fputcsv auto-quotes fields containing spaces — assert individual header tokens.
    expect($csv)->toContain('"Customer Code"');
    expect($csv)->toContain('"Customer Name"');
    expect($csv)->toContain('Mobile');
    expect($csv)->toContain('CSV Export Gamma Target');
    // Filtered out rows must not appear
    expect($csv)->not->toContain('CSV Export Alpha');
    expect($csv)->not->toContain('CSV Export Beta');
});

test('5c2. export=print renders the customers_list_print view without error', function () {
    $env = listFixEnv();
    makeListCustomer('Print Export Customer', '01710000004');

    $response = $this->actingAs($env['user'])->get(route('contacts.customers.list', ['export' => 'print']));

    $response->assertOk();
    $response->assertViewIs('module.contacts.customers_list_print');
    $response->assertSee('Customers List');
    $response->assertSee('Print Export Customer');
});

// ─── 6. tfoot label honesty ───────────────────────────────────────────────

test('6. Customers list shows "This Page Total" and no longer shows "Total Summary"', function () {
    $env = listFixEnv();
    makeListCustomer('Label Customer', '01710000005');

    $response = $this->actingAs($env['user'])->get(route('contacts.customers.list'));
    $response->assertOk();
    $response->assertSee('This Page Total', false);
    $response->assertDontSee('Total Summary', false);
});

// ─── 7. GST / Tax fields persist through saveStep(advanced) ──────────────

test('7. gstin and tax_number persist via saveStep(advanced) on create and edit', function () {
    $env = listFixEnv();

    // Create via basic
    $basic = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), [
        'current_step' => 'basic',
        'customer_type' => 'regular',
        'customer_name' => 'GST Customer',
        'mobile' => '017' . random_int(10000000, 99999999),
        'credit_limit' => 0,
        'opening_balance' => 0,
        'price_level_type' => 'Increase',
        'price_level' => 0,
    ]);
    $basic->assertOk();
    $id = $basic->json('id');

    // Advanced step carries gstin/tax_number
    $adv = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), [
        'current_step' => 'advanced',
        'id' => $id,
        'customer_type' => 'regular',
        'customer_name' => 'GST Customer',
        'gstin' => 'GSTIN-ABCDE1234F',
        'tax_number' => 'TAX-987654321',
        'vatin' => 'VAT-111',
        'phone' => '',
        'credit_limit' => 0,
        'opening_balance' => 0,
        'price_level_type' => 'Increase',
        'price_level' => 0,
    ]);
    $adv->assertOk();

    $stored = DbCustomer::find($id);
    expect($stored->gstin)->toBe('GSTIN-ABCDE1234F');
    expect($stored->tax_number)->toBe('TAX-987654321');
    expect($stored->vatin)->toBe('VAT-111');

    // Re-edit: reload the edit page (view re-population) then save again → still persisted
    $editPage = $this->actingAs($env['user'])->get(route('contacts.customers.edit', $id));
    $editPage->assertOk();
    $editPage->assertSee('GSTIN-ABCDE1234F', false);
    $editPage->assertSee('TAX-987654321', false);

    $adv2 = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), [
        'current_step' => 'advanced',
        'id' => $id,
        'customer_type' => 'regular',
        'customer_name' => 'GST Customer Renamed',
        'gstin' => 'GSTIN-ABCDE1234F',
        'tax_number' => 'TAX-987654321',
        'vatin' => 'VAT-111',
        'phone' => '',
        'credit_limit' => 0,
        'opening_balance' => 0,
        'price_level_type' => 'Increase',
        'price_level' => 0,
    ]);
    $adv2->assertOk();

    $final = DbCustomer::find($id);
    expect($final->customer_name)->toBe('GST Customer Renamed');
    expect($final->gstin)->toBe('GSTIN-ABCDE1234F');
    expect($final->tax_number)->toBe('TAX-987654321');
});

// ─── 8. Dead "Opening Balance Payments" table removed ─────────────────────

test('8. add_customer view no longer renders the dead Opening Balance Payments table', function () {
    $env = listFixEnv();

    $response = $this->actingAs($env['user'])->get(route('contacts.customers.add'));
    $response->assertOk();
    $response->assertDontSee('Opening Balance Payments', false);
    $response->assertDontSee('No Previous Stock Entry Found', false);
});
