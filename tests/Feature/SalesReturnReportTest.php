<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbSalesReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Test Store',
        'status' => 1,
        'mobile' => '+8801700000000',
        'email' => 'store@corevisys.com',
        'address' => 'Dhaka, Bangladesh',
    ]);

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => 1,
        'permissions' => ['reports_sales_return_view', 'sales_return_view'],
    ]);
});

test('sales return report page loads with warehouses from DbWarehouse', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $wh1 = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Central Hub Warehouse',
        'status' => 1,
        'store_id' => 1,
    ]);

    $wh2 = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'North Depot Warehouse',
        'status' => 1,
        'store_id' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('reports.sales_return'));
    $response->assertStatus(200);
    $response->assertViewIs('module.reports.sales_return');
    $response->assertViewHas('warehouses', function ($warehouses) use ($wh1, $wh2) {
        return $warehouses->contains('id', $wh1->id) && $warehouses->contains('id', $wh2->id);
    });
    $response->assertSee('Central Hub Warehouse');
    $response->assertSee('North Depot Warehouse');
});

test('sales return report data endpoint filters by warehouse_id correctly', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $whA = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Warehouse Alpha',
        'status' => 1,
        'store_id' => 1,
    ]);

    $whB = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Warehouse Beta',
        'status' => 1,
        'store_id' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Retail Customer A',
        'mobile' => '+8801800000000',
        'status' => 1,
        'store_id' => 1,
    ]);

    $saleA = DbSale::create([
        'store_id' => 1,
        'sales_code' => 'SA-001',
        'sales_date' => now()->toDateString(),
        'store_id' => 1,
        'warehouse_id' => $whA->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'subtotal' => 500,
        'grand_total' => 500,
        'paid_amount' => 500,
        'payment_status' => 'Paid',
    ]);

    $saleB = DbSale::create([
        'store_id' => 1,
        'sales_code' => 'SA-002',
        'sales_date' => now()->toDateString(),
        'store_id' => 1,
        'warehouse_id' => $whB->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'subtotal' => 800,
        'grand_total' => 800,
        'paid_amount' => 800,
        'payment_status' => 'Paid',
    ]);

    $returnA = DbSalesReturn::create([
        'store_id' => 1,
        'return_code' => 'SR-001',
        'return_date' => now()->toDateString(),
        'sales_id' => $saleA->id,
        'warehouse_id' => $whA->id,
        'customer_id' => $customer->id,
        'store_id' => 1,
        'subtotal' => 200,
        'grand_total' => 200,
        'paid_amount' => 200,
        'payment_status' => 'Paid',
    ]);

    $returnB = DbSalesReturn::create([
        'store_id' => 1,
        'return_code' => 'SR-002',
        'return_date' => now()->toDateString(),
        'sales_id' => $saleB->id,
        'warehouse_id' => $whB->id,
        'customer_id' => $customer->id,
        'store_id' => 1,
        'subtotal' => 300,
        'grand_total' => 300,
        'paid_amount' => 300,
        'payment_status' => 'Paid',
    ]);

    // Query for Warehouse Alpha only
    $response = $this->actingAs($user)->getJson(route('reports.sales_return_data', [
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'warehouse_id' => $whA->id,
    ]));

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'success',
    ]);

    $records = $response->json('records');
    expect($records)->toHaveCount(1);
    expect($records[0]['invoice'])->toBe('SR-001');
    expect($records[0]['warehouse'])->toBe('Warehouse Alpha');
});
