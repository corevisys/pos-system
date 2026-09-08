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
use App\Models\DbCurrency;
use App\Providers\AppServiceProvider;
use App\Http\Controllers\DashboardController;
use Carbon\Carbon;

function getStoreTimezoneTestUser(): User {
    store_settings(true);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Timezone Test Store',
        'status' => 1,
        'mobile' => '01700000000',
        'timezone' => 'Asia/Dhaka',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::updateOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['store_settings_edit', 'store_settings_view', 'reports_view', 'sales_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. AppServiceProvider::configureStoreTimezone falls back to Asia/Dhaka when timezone is null or invalid', function () {
    $store = DbStore::firstOrCreate(['id' => 1]);
    
    // Set invalid timezone
    $store->update(['timezone' => 'Invalid/NonExistent_Zone']);
    store_settings(true);
    
    $resolved = AppServiceProvider::configureStoreTimezone(true);
    expect($resolved)->toBe('Asia/Dhaka');
    expect(config('app.timezone'))->toBe('Asia/Dhaka');
    expect(date_default_timezone_get())->toBe('Asia/Dhaka');

    // Set null/empty timezone
    $store->update(['timezone' => null]);
    store_settings(true);
    
    $resolvedNull = AppServiceProvider::configureStoreTimezone(true);
    expect($resolvedNull)->toBe('Asia/Dhaka');
    expect(config('app.timezone'))->toBe('Asia/Dhaka');
    expect(date_default_timezone_get())->toBe('Asia/Dhaka');
});

test('2. AppServiceProvider::configureStoreTimezone dynamically applies configured valid timezone', function () {
    $store = DbStore::firstOrCreate(['id' => 1]);
    
    $store->update(['timezone' => 'America/New_York']);
    store_settings(true);
    
    $resolved = AppServiceProvider::configureStoreTimezone(true);
    expect($resolved)->toBe('America/New_York');
    expect(config('app.timezone'))->toBe('America/New_York');
    expect(date_default_timezone_get())->toBe('America/New_York');

    // Switch to Asia/Tokyo
    $store->update(['timezone' => 'Asia/Tokyo']);
    store_settings(true);
    
    $resolvedTokyo = AppServiceProvider::configureStoreTimezone(true);
    expect($resolvedTokyo)->toBe('Asia/Tokyo');
    expect(config('app.timezone'))->toBe('Asia/Tokyo');
    expect(date_default_timezone_get())->toBe('Asia/Tokyo');

    // Reset back to Asia/Dhaka
    $store->update(['timezone' => 'Asia/Dhaka']);
    store_settings(true);
    AppServiceProvider::configureStoreTimezone(true);
});

test('3. Store Settings controller update validates timezone against valid PHP identifiers', function () {
    $user = getStoreTimezoneTestUser();
    $currency = DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT',
        'code' => 'BDT',
        'symbol' => '৳',
        'status' => 1,
    ]);

    // Attempting invalid timezone should fail validation
    $response = $this->actingAs($user)->post(route('settings.store.update'), [
        'store_name' => 'Timezone Validated Store',
        'mobile' => '01711223344',
        'email' => 'admin@test.com',
        'city' => 'Dhaka',
        'currency_id' => $currency->id,
        'timezone' => 'Invalid/Fake_Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');

    // Valid timezone passes
    $validResponse = $this->actingAs($user)->post(route('settings.store.update'), [
        'store_name' => 'Timezone Validated Store',
        'mobile' => '01711223344',
        'email' => 'admin@test.com',
        'city' => 'Dhaka',
        'currency_id' => $currency->id,
        'timezone' => 'Asia/Dhaka',
    ]);

    $validResponse->assertRedirect(route('settings.store'));
    $validResponse->assertSessionHas('success');

    $store = DbStore::first();
    expect($store->timezone)->toBe('Asia/Dhaka');
    expect(config('app.timezone'))->toBe('Asia/Dhaka');
});

test('4. Changing timezone dynamically shifts Carbon::today and Dashboard Today Profit boundary', function () {
    $user = getStoreTimezoneTestUser();
    $store = DbStore::first();

    // Set to Asia/Dhaka (+06:00)
    $store->update(['timezone' => 'Asia/Dhaka']);
    store_settings(true);
    AppServiceProvider::configureStoreTimezone(true);

    $dhakaToday = Carbon::today()->format('Y-m-d');
    expect(config('app.timezone'))->toBe('Asia/Dhaka');

    // Set to Pacific/Honolulu (-10:00) where date might differ depending on time of day
    $store->update(['timezone' => 'Pacific/Honolulu']);
    store_settings(true);
    AppServiceProvider::configureStoreTimezone(true);

    $honoluluToday = Carbon::today()->format('Y-m-d');
    expect(config('app.timezone'))->toBe('Pacific/Honolulu');

    // Restore to Asia/Dhaka
    $store->update(['timezone' => 'Asia/Dhaka']);
    store_settings(true);
    AppServiceProvider::configureStoreTimezone(true);
});
