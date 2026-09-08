<?php

use App\Models\User;
use App\Models\DbStore;
use Database\Seeders\StoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\WarehouseSeeder;
use Database\Seeders\LanguageSeeder;

beforeEach(function () {
    $this->seed([
        CurrencySeeder::class,
        LanguageSeeder::class,
        StoreSeeder::class,
        RolePermissionSeeder::class,
        WarehouseSeeder::class,
    ]);

    $this->user = User::factory()->create([
        'store_id' => 1,
        'role_id' => 1,
        'role_name' => 'Super Admin',
    ]);
});

test('authenticated pages render browser tab title with {Page Name} - {Store Name} format', function (string $url, string $expectedTitle) {
    $store = DbStore::first();
    $storeName = $store->store_name;

    $response = $this->actingAs($this->user)->get($url);

    $response->assertOk();
    $expectedFull = e("{$expectedTitle} - {$storeName}");
    $response->assertSee("<title>{$expectedFull}</title>", false);
})->with([
    ['/dashboard', 'Dashboard'],
    ['/sales/pos', 'POS'],
    ['/sales/add', 'Add Sale'],
    ['/sales/list', 'Sales List'],
    ['/sales/payments', 'Sales Payments'],
    ['/sales/returns', 'Sales Returns List'],
    ['/purchase/new', 'New Purchase'],
    ['/purchase/list', 'Purchase List'],
    ['/contacts/customers', 'Customers List'],
    ['/contacts/suppliers', 'Suppliers List'],
    ['/items/list', 'Items List'],
    ['/items/categories', 'Categories List'],
    ['/stock/transfer', 'Stock Transfer List'],
    ['/stock/adjustment', 'Stock Adjustment List'],
    ['/expenses/list', 'Expenses List'],
    ['/accounts/list', 'Accounts List'],
    ['/reports/sales', 'Sales Report'],
    ['/reports/profit-loss', 'Profit & Loss Report'],
    ['/settings/store', 'Store Settings'],
    ['/settings/currency', 'Currency List'],
    ['/users/list', 'Users List'],
    ['/users/roles', 'Roles List'],
    ['/warehouse/list', 'Warehouse List'],
    ['/sms/send', 'Send SMS'],
    ['/coupons/master', 'Coupons Master'],
    ['/advance/list', 'Advance Payments List'],
    ['/quotation/list', 'Quotation List'],
]);

test('updating store name dynamically reflects in browser tab title', function () {
    $store = DbStore::first();
    $response = $this->actingAs($this->user)->get('/dashboard');
    $response->assertSee("<title>Dashboard - {$store->store_name}</title>", false);

    // Update store name
    $newStoreName = 'Alpha Enterprise POS';
    $store->update(['store_name' => $newStoreName]);
    store_settings(true); // bust memoized cache

    $response2 = $this->actingAs($this->user)->get('/dashboard');
    $response2->assertSee("<title>Dashboard - {$newStoreName}</title>", false);

    $response3 = $this->actingAs($this->user)->get('/sales/pos');
    $response3->assertSee("<title>POS - {$newStoreName}</title>", false);
});
