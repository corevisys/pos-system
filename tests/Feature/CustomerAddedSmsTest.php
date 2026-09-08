<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

if (!function_exists('smsEnv')) {
    function smsEnv(): array
    {
        if (!DbStore::where('id', 1)->exists()) {
            DbStore::create([
                'id' => 1,
                'store_name' => 'SMS Store',
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

if (!function_exists('smsBasicPayload')) {
    function smsBasicPayload(array $overrides = []): array
    {
        return array_merge([
            'current_step' => 'basic',
            'customer_type' => 'regular',
            'customer_name' => 'SMS Test Customer',
            'mobile' => '017' . random_int(10000000, 99999999),
            'credit_limit' => 0,
            'opening_balance' => 0,
            'price_level_type' => 'Increase',
            'price_level' => 0,
        ], $overrides);
    }
}

// ─── 4a. Create fires CustomerAdded exactly once ─────────────────────────

test('4a. CustomerAdded SMS fires exactly once on the first basic saveStep (create)', function () {
    $env = smsEnv();

    $spy = $this->spy(SmsTriggerService::class);

    $response = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), smsBasicPayload());
    $response->assertOk()->assertJson(['success' => true]);
    $id = $response->json('id');

    $customer = DbCustomer::find($id);
    expect($customer)->not->toBeNull();

    $spy->shouldHaveReceived('trigger')->once()->with('CustomerAdded', Mockery::on(fn ($m) => $m instanceof DbCustomer && $m->id === $id));
});

// ─── 4b. Subsequent wizard steps do NOT re-fire ──────────────────────────

test('4b. Subsequent wizard steps do not re-fire the CustomerAdded SMS', function () {
    $env = smsEnv();

    $spy = $this->spy(SmsTriggerService::class);

    // Create via basic
    $create = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), smsBasicPayload());
    $create->assertOk();
    $id = $create->json('id');

    // Subsequent steps: documents, guardian, guarantor, advanced
    foreach (['documents', 'guardian', 'guarantor', 'advanced'] as $step) {
        $res = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), [
            'current_step' => $step,
            'id' => $id,
            'customer_type' => 'regular',
        ]);
        $res->assertOk();
    }

    $spy->shouldHaveReceived('trigger')->once();
});

// ─── 4c. Editing an existing customer never fires ────────────────────────

test('4c. Editing an existing customer via saveStep does NOT fire the SMS at all', function () {
    $env = smsEnv();

    // Seed an existing customer directly via the model — avoids a prior real-service HTTP
    // request inside the same sqlite :memory: transaction, which desyncs transaction nesting.
    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Existing SMS Customer',
        'customer_type' => 'regular',
        'mobile' => '017' . random_int(10000000, 99999999),
        'customer_code' => 'SMS-' . strtoupper(uniqid()),
        'status' => 1,
    ]);

    $spy = $this->spy(SmsTriggerService::class);

    // Edit — only the basic step with an existing id.
    $edit = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), smsBasicPayload([
        'id' => $customer->id,
        'customer_name' => 'Edited SMS Customer',
    ]));
    $edit->assertOk();

    $spy->shouldNotHaveReceived('trigger');
});

// ─── 4d. SMS service failure does not roll back creation ─────────────────

test('4d. An SMS-service exception does not fail customer creation and is logged', function () {
    $env = smsEnv();

    Log::spy();

    // Bind a throwing subclass of the real service (avoids Mockery's exception handling
    // interfering with sqlite transaction bookkeeping inside the request).
    $throwingService = new class(app(\App\SMS\Services\SmsService::class)) extends SmsTriggerService {
        public function trigger(string $eventType, $model)
        {
            throw new \Exception('SMS gateway down');
        }
    };
    $this->app->instance(SmsTriggerService::class, $throwingService);

    $response = $this->actingAs($env['user'])->postJson(route('contacts.customers.save-step'), smsBasicPayload());
    $response->assertOk()->assertJson(['success' => true]);
    $id = $response->json('id');

    // Customer was still created despite the SMS failure.
    expect(DbCustomer::find($id))->not->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});
