<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbUnit;
use App\Models\DbTax;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'COREVISYS TEST STORE',
        'status' => 1,
    ]);

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);
});

test('unauthenticated users are redirected to login when accessing print labels', function () {
    $response = $this->get(route('items.labels'));
    $response->assertRedirect(route('login'));
});

test('users without items_print_labels permission receive 403 forbidden', function () {
    $role = DbRole::create([
        'id' => 2,
        'role_name' => 'Cashier Without Print Labels',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::create([
        'role_id' => $role->id,
        'store_id' => 1,
        'permissions' => ['sales_add', 'items_view'],
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
        'role_name' => 'Cashier',
        'store_id' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('items.labels'));
    $response->assertStatus(403);
});

test('authorized users with items_print_labels can access the print labels page', function () {
    $role = DbRole::create([
        'id' => 3,
        'role_name' => 'Inventory Manager',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::create([
        'role_id' => $role->id,
        'store_id' => 1,
        'permissions' => ['items_print_labels'],
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
        'role_name' => 'Inventory Manager',
        'store_id' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('items.labels'));
    $response->assertStatus(200);
    $response->assertSee('Print Barcode Labels');
    $response->assertSee('COREVISYS TEST STORE');
});

test('super admin can access print labels page and pre-load items via query param', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $category = DbCategory::create(['category_name' => 'Electronics', 'status' => 1, 'store_id' => 1]);
    $brand = DbBrand::create(['brand_name' => 'Sony', 'status' => 1, 'store_id' => 1]);
    $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => 1]);
    $tax = DbTax::create(['tax_name' => 'GST 0%', 'tax' => 0, 'status' => 1, 'store_id' => 1]);

    $item1 = DbItem::create([
        'item_name' => 'Sony WH-1000XM5 Wireless Headphones',
        'item_code' => 'IT-00101',
        'custom_barcode' => 'SNY-WH1000-XM5',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'tax_id' => $tax->id,
        'sales_price' => 399.99,
        'purchase_price' => 280.00,
        'stock' => 15,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('items.labels', ['items' => $item1->id]));
    $response->assertStatus(200);
    $response->assertSee('Sony WH-1000XM5 Wireless Headphones');
    $response->assertSee('SNY-WH1000-XM5');
});

test('ajax search-items endpoint returns items with real vector Code128 barcode SVG', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $category = DbCategory::create(['category_name' => 'Audio', 'status' => 1, 'store_id' => 1]);
    $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => 1]);
    $tax = DbTax::create(['tax_name' => 'None', 'tax' => 0, 'status' => 1, 'store_id' => 1]);

    $item = DbItem::create([
        'item_name' => 'JBL Flip 6 Portable Bluetooth Speaker',
        'item_code' => 'IT-00202',
        'custom_barcode' => '890123456789',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'tax_id' => $tax->id,
        'sales_price' => 129.50,
        'purchase_price' => 90.00,
        'stock' => 25,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->getJson(route('items.search.items', ['query' => 'JBL Flip']));
    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveCount(1);
    expect($data[0]['item_name'])->toBe('JBL Flip 6 Portable Bluetooth Speaker');
    expect($data[0]['item_code'])->toBe('IT-00202');
    expect($data[0]['custom_barcode'])->toBe('890123456789');
    expect($data[0]['barcode_value'])->toBe('890123456789');
    expect($data[0]['sales_price'])->toBe(129.5);
    expect($data[0]['barcode_svg'])->toContain('<svg');
    expect($data[0]['barcode_svg'])->toContain('<rect');
});

test('batch-items endpoint loads category items with real barcode SVGs', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $category = DbCategory::create(['category_name' => 'Hardware', 'status' => 1, 'store_id' => 1]);
    $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => 1]);
    $tax = DbTax::create(['tax_name' => 'None', 'tax' => 0, 'status' => 1, 'store_id' => 1]);

    DbItem::create([
        'item_name' => 'SanDisk Extreme Pro 128GB',
        'item_code' => 'IT-00301',
        'custom_barcode' => 'SNDK-128GB-PRO',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'tax_id' => $tax->id,
        'sales_price' => 45.00,
        'stock' => 50,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->getJson(route('items.labels.batch', ['category_id' => $category->id]));
    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveCount(1);
    expect($data[0]['item_name'])->toBe('SanDisk Extreme Pro 128GB');
    expect($data[0]['barcode_value'])->toBe('SNDK-128GB-PRO');
    expect($data[0]['barcode_svg'])->toContain('<svg');
});
