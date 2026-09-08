<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbTax;
use App\Models\DbPaymentType;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (!function_exists('quickAddEmiEnv')) {
    function quickAddEmiEnv(): array
    {
        if (!DbStore::where('id', 1)->exists()) {
            DbStore::create([
                'id' => 1,
                'store_name' => 'QuickAdd Store',
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

if (!function_exists('posRenderEnv')) {
    /**
     * Seed the data the POS index() view needs so the page renders for a view assertion.
     */
    function posRenderEnv(array $env): void
    {
        DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Render WH', 'status' => 1]);
        DbCategory::create(['store_id' => 1, 'category_name' => 'Render Cat', 'status' => 1]);
        DbBrand::firstOrCreate(['store_id' => 1, 'brand_name' => 'Render Brand', 'status' => 1]);
        DbTax::firstOrCreate(['store_id' => 1, 'tax_name' => 'No Tax', 'tax' => 0, 'status' => 1]);
        DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash', 'status' => 1]);
        AcAccount::firstOrCreate(['store_id' => 1, 'account_name' => 'Render Cash', 'status' => 1]);
    }
}

// ─── 3a. quickStore always stores customer_type = regular ────────────────

test('3a. quickStore REJECTS an emi customer_type attempt and creates no row', function () {
    $env = quickAddEmiEnv();

    $mobile = '019' . random_int(10000000, 99999999);

    // Attempt to create an EMI customer through quick-add. The endpoint's validation
    // (in:regular) rejects it outright — no KYC-less EMI row may ever be created.
    $response = $this->actingAs($env['user'])->postJson(route('contacts.customers.quick-store'), [
        'customer_name' => 'Blocked EMI QuickAdd',
        'mobile' => $mobile,
        'customer_type' => 'emi',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['customer_type']);

    // No row was created with that mobile.
    expect(DbCustomer::where('mobile', $mobile)->exists())->toBeFalse();
    expect(DbCustomer::where('customer_name', 'Blocked EMI QuickAdd')->exists())->toBeFalse();
});

// ─── 3b. quick-add modal no longer renders an EMI option ──────────────────

test('3b. POS quick-add customer modal no longer offers an EMI option', function () {
    $env = quickAddEmiEnv();
    posRenderEnv($env);

    $response = $this->actingAs($env['user'])->get(route('sales.pos'));
    $response->assertOk();

    $html = $response->getContent();

    // The quick-add Category select (x-model="newCustomer.customer_type") must contain
    // only the regular option — there must be no option with value="emi" inside it.
    // Extract the select's inner HTML and assert it does not contain value="emi".
    if (preg_match('/<select x-model="newCustomer\.customer_type".*?>(.*?)<\/select>/s', $html, $m)) {
        $selectHtml = $m[1];
        expect($selectHtml)->not->toContain('value="emi"');
        expect($selectHtml)->toContain('value="regular"');
        // The guidance note is also present.
        expect($html)->toContain('EMI customers must be created via Customers');
    } else {
        $this->fail('Could not locate the newCustomer.customer_type select in the rendered POS view.');
    }
});

// ─── 3c. Re-run existing regular quick-store path ─────────────────────────

test('3c. quickStore with customer_type=regular still works exactly as before', function () {
    $env = quickAddEmiEnv();

    $mobile = '015' . random_int(10000000, 99999999);

    $response = $this->actingAs($env['user'])->postJson(route('contacts.customers.quick-store'), [
        'customer_name' => 'Regular QuickAdd',
        'mobile' => $mobile,
        'customer_type' => 'regular',
        'email' => 'regular_' . random_int(100, 999) . '@example.com',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);
    $customer = DbCustomer::find($response->json('customer.id'));
    expect($customer)->not->toBeNull();
    expect($customer->customer_name)->toBe('Regular QuickAdd');
    expect($customer->mobile)->toBe($mobile);
    expect($customer->customer_type)->toBe('regular');
});
