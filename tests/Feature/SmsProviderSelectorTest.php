<?php

use App\Models\DbStore;
use App\Models\User;
use App\SMS\Services\SmsService;
use App\SMS\Providers\HttpSmsProvider;
use App\SMS\Providers\AlphaSMSProvider;
use App\SMS\Providers\BulkSmsBdProvider;
use App\SMS\Providers\FiveMojoSMSProvider;
use App\SMS\Providers\SslWirelessProvider;
use App\SMS\Providers\SandboxSMSProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Corevisys POS',
        'store_code' => 'ST001',
        'sms_status' => 0,
    ]);
    $this->store->update(['sms_status' => 0]);
});

// --- 1. Page loads with correct active provider -------------------------------

it('sms settings page loads successfully with all provider cards', function () {
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('provider-card-0', false);
    $response->assertSee('provider-card-1', false);
    $response->assertSee('provider-card-2', false);
    $response->assertSee('provider-card-3', false);
    $response->assertSee('provider-card-4', false);
    $response->assertSee('provider-card-5', false);
    $response->assertSee('provider-card-sandbox', false);
});

it('sms settings page initialises activeProvider to 0 when disabled', function () {
    $this->store->update(['sms_status' => 0]);
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('activeProvider: 0', false);
});

it('sms settings page shows alpha sms as active tab when sms_status is 2', function () {
    $this->store->update(['sms_status' => 2]);
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('activeProvider: 2', false);
    $response->assertSee("activeTab: 'alpha'", false);
});

it('sms settings page shows ssl tab when sms_status is 5', function () {
    $this->store->update(['sms_status' => 5]);
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('activeProvider: 5', false);
    $response->assertSee("activeTab: 'ssl'", false);
});

it('sandbox card is a non-interactive div with cursor-not-allowed and no setProvider handler', function () {
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('id="provider-card-sandbox"', false);
    $response->assertSee('cursor-not-allowed', false);
    $response->assertSee('Testing Only', false);
    $response->assertSee('SMS_SANDBOX', false);
    $body = $response->getContent();
    $sandboxPos = strpos($body, 'provider-card-sandbox');
    $segment = substr($body, $sandboxPos, 400);
    expect($segment)->not->toContain('setProvider');
});

// --- 2. Saving sms_status correctly switches providers -----------------------

it('posting sms_status=1 persists http as active provider', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 1])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(1);
});

it('posting sms_status=2 persists alpha sms as active provider', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 2])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(2);
});

it('posting sms_status=3 persists bulksms bd as active provider', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 3])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(3);
});

it('posting sms_status=4 persists fivemojo as active provider', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 4])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(4);
});

it('posting sms_status=5 persists ssl wireless as active provider', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 5])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(5);
});

it('posting sms_status=0 disables sms', function () {
    $this->store->update(['sms_status' => 3]);
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 0])->assertRedirect();
    expect((int) $this->store->fresh()->sms_status)->toBe(0);
});

it('provider switch is persisted and reflected on page reload', function () {
    $this->actingAs($this->user)->post('/sms/settings/update', ['sms_status' => 5]);
    expect((int) $this->store->fresh()->sms_status)->toBe(5);
    $response = $this->actingAs($this->user)->get('/sms/settings');
    $response->assertOk();
    $response->assertSee('activeProvider: 5', false);
    $response->assertSee("activeTab: 'ssl'", false);
});

// --- 3. SmsService::getProvider() returns correct class per status ------------

it('SmsService returns null when sms_status is 0', function () {
    $this->store->update(['sms_status' => 0]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeNull();
});

it('SmsService returns HttpSmsProvider when sms_status is 1', function () {
    $this->store->update(['sms_status' => 1]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(HttpSmsProvider::class);
});

it('SmsService returns AlphaSMSProvider when sms_status is 2', function () {
    $this->store->update(['sms_status' => 2]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(AlphaSMSProvider::class);
});

it('SmsService returns BulkSmsBdProvider when sms_status is 3', function () {
    $this->store->update(['sms_status' => 3]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(BulkSmsBdProvider::class);
});

it('SmsService returns FiveMojoSMSProvider when sms_status is 4', function () {
    $this->store->update(['sms_status' => 4]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(FiveMojoSMSProvider::class);
});

it('SmsService returns SslWirelessProvider when sms_status is 5', function () {
    $this->store->update(['sms_status' => 5]);
    config(['sms.sandbox' => false]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(SslWirelessProvider::class);
});

it('SmsService returns SandboxSMSProvider when sms.sandbox config is true, regardless of sms_status', function () {
    $this->store->update(['sms_status' => 2]);
    config(['sms.sandbox' => true]);
    expect(app(SmsService::class)->getProvider($this->store->id))->toBeInstanceOf(SandboxSMSProvider::class);
    config(['sms.sandbox' => false]);
});

it('SmsService correctly switches provider class when sms_status changes in database', function () {
    config(['sms.sandbox' => false]);
    $service = app(SmsService::class);

    $this->store->update(['sms_status' => 1]);
    expect($service->getProvider($this->store->id))->toBeInstanceOf(HttpSmsProvider::class);

    $this->store->update(['sms_status' => 2]);
    expect($service->getProvider($this->store->id))->toBeInstanceOf(AlphaSMSProvider::class);

    $this->store->update(['sms_status' => 5]);
    expect($service->getProvider($this->store->id))->toBeInstanceOf(SslWirelessProvider::class);

    $this->store->update(['sms_status' => 0]);
    expect($service->getProvider($this->store->id))->toBeNull();
});
