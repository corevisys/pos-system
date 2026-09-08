<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

if (!function_exists('saveStepEnv')) {
    function saveStepEnv(): array
    {
        if (!DbStore::where('id', 1)->exists()) {
            DbStore::create([
                'id' => 1,
                'store_name' => 'SaveStep Store',
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

if (!function_exists('saveStepEmiBasicPayload')) {
    /**
     * The full Basic-tab payload a completed EMI customer carries.
     */
    function saveStepEmiBasicPayload(array $overrides = []): array
    {
        return array_merge([
            'current_step' => 'basic',
            'customer_type' => 'emi',
            'customer_name' => 'EMI Wizard Customer',
            'mobile' => '017' . random_int(10000000, 99999999),
            'email' => 'wizard_' . random_int(1000, 9999) . '@example.com',
            'credit_limit' => 5000,
            'opening_balance' => 0,
            'price_level_type' => 'Increase',
            'price_level' => 0,
            'phone' => '',
            'customer_id_card' => 'NID-123456',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'dob' => '1990-05-15',
            'mobile_secondary' => '018' . random_int(10000000, 99999999),
            'present_address' => '123 Present Street, Dhaka',
            'permanent_address' => '456 Permanent Road, Khulna',
            'occupation' => 'Engineer',
            'monthly_income' => 45000,
            'workplace_name' => 'Acme Corp',
            'workplace_address' => '789 Work Ave, Dhaka',
        ], $overrides);
    }
}

// ─── 2a/2b/2c. Editing only customer_name preserves previously-set EMI KYC fields ──

test('2. Editing only customer_name via saveStep(basic) preserves stored EMI KYC fields', function () {
    $env = saveStepEnv();

    // Create a complete EMI customer through the wizard's create branch
    $create = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), saveStepEmiBasicPayload());
    $create->assertOk()->assertJson(['success' => true]);
    $id = $create->json('id');
    expect($id)->not->toBeNull();

    $before = DbCustomer::find($id);
    // Cast to float — sqlite NUMERIC affinity returns integral decimals as int, not float.
    expect((float) $before->monthly_income)->toBe(45000.0);
    expect($before->dob)->toBe('1990-05-15');
    expect($before->present_address)->toBe('123 Present Street, Dhaka');
    expect($before->occupation)->toBe('Engineer');
    expect($before->customer_id_card)->toBe('NID-123456');

    // Simulate the real wizard partial-payload shape: editing ONLY customer_name on the
    // basic step. The other Basic-tab fields are NOT resent by the client in this scenario.
    $payload = saveStepEmiBasicPayload([
        'customer_name' => 'Renamed EMI Customer',
    ]);

    // Drop fields to emulate what the wizard genuinely omits (not simply empty-string posts).
    unset($payload['email'], $payload['dob'], $payload['present_address'], $payload['permanent_address'],
           $payload['occupation'], $payload['monthly_income'], $payload['workplace_name'],
           $payload['workplace_address'], $payload['customer_id_card'], $payload['father_name'],
           $payload['mother_name'], $payload['mobile_secondary'], $payload['phone']);
    $payload['id'] = $id;

    $edit = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), $payload);
    $edit->assertOk()->assertJson(['success' => true]);

    $after = DbCustomer::find($id);
    expect($after->customer_name)->toBe('Renamed EMI Customer');
    // NOT reset to $dbDefaults:
    expect((float) $after->monthly_income)->toBe(45000.0);
    expect($after->dob)->toBe('1990-05-15');
    expect($after->present_address)->toBe('123 Present Street, Dhaka');
    expect($after->permanent_address)->toBe('456 Permanent Road, Khulna');
    expect($after->occupation)->toBe('Engineer');
    expect($after->customer_id_card)->toBe('NID-123456');
    expect($after->workplace_name)->toBe('Acme Corp');
    expect($after->mobile_secondary)->toBe($before->mobile_secondary);
});

// ─── 2d. Intentional clear of an optional field still works ────────────────

test('2d. Explicitly clearing an optional field (email) still works on edit', function () {
    $env = saveStepEnv();

    $create = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), saveStepEmiBasicPayload());
    $create->assertOk();
    $id = $create->json('id');

    // Email is explicitly present AND empty → must clear (optional clearable field).
    $payload = saveStepEmiBasicPayload([
        'customer_name' => 'Clear Email Tester',
    ]);
    $payload['id'] = $id;
    $payload['email'] = '';

    $edit = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), $payload);
    $edit->assertOk()->assertJson(['success' => true]);

    $after = DbCustomer::find($id);
    expect($after->email)->toBe('');
    // Other non-optional stored fields still preserved
    expect($after->customer_name)->toBe('Clear Email Tester');
    expect((float) $after->monthly_income)->toBe(45000.0);
});

// ─── 2e. CREATE path still applies schema-safe defaults ───────────────────

test('2e. Create path (no id) still applies $dbDefaults for unset optional fields', function () {
    $env = saveStepEnv();

    // Minimal regular-customer basic payload — many optional fields are absent entirely.
    $payload = [
        'current_step' => 'basic',
        'customer_type' => 'regular',
        'customer_name' => 'Minimal Regular',
        'mobile' => '016' . random_int(10000000, 99999999),
        'credit_limit' => 0,
        'opening_balance' => 0,
        'price_level_type' => 'Increase',
        'price_level' => 0,
    ];

    $response = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), $payload);
    $response->assertOk()->assertJson(['success' => true]);
    $id = $response->json('id');

    $customer = DbCustomer::find($id);
    // Schema-safe defaults applied so the DB never gets NULL in NOT-NULL/EMI columns:
    expect((float) $customer->monthly_income)->toBe(0.0);
    expect($customer->dob)->toBe('2000-01-01');
    expect($customer->present_address)->toBe('');
    expect($customer->occupation)->toBe('');
    expect($customer->customer_id_card)->toBe('');
});

// ─── 2f. Guard: an absent required EMI column on edit also preserves, not NULLs ──

test('2f. saveStep edit does not write NULL/empty into required EMI columns omitted from the request', function () {
    $env = saveStepEnv();

    $create = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), saveStepEmiBasicPayload());
    $create->assertOk();
    $id = $create->json('id');

    // Simulate advanced-step-style partial where father_name etc. are omitted.
    $payload = [
        'current_step' => 'basic',
        'id' => $id,
        'customer_type' => 'emi',
        'customer_name' => 'EMI Partial Edit',
        'mobile' => '017' . random_int(10000000, 99999999),
        'credit_limit' => 5000,
        'opening_balance' => 0,
        'price_level_type' => 'Increase',
        'price_level' => 0,
    ];

    $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), $payload)->assertOk();

    $after = DbCustomer::find($id);
    // The payload omitted father_name / mobile_secondary — those must NOT be wiped.
    expect($after->father_name)->toBe('Father Name');
    expect($after->mobile_secondary)->not->toBeEmpty();
    expect((float) $after->monthly_income)->toBe(45000.0);
    expect($after->dob)->toBe('1990-05-15');
});
