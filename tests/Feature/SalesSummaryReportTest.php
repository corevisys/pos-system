<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbCategory;
use Carbon\Carbon;

function createTestSuperAdmin(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Test Store',
        'status' => 1,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['reports_view', 'sales_report'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('unauthenticated users cannot access sales summary report', function () {
    $response = $this->get(route('reports.sales_summary'));
    $response->assertRedirect('/login');
});

test('authenticated user can view the sales summary report page', function () {
    $user = createTestSuperAdmin();

    $response = $this
        ->actingAs($user)
        ->get(route('reports.sales_summary'));

    $response->assertOk();
    $response->assertViewIs('module.reports.sales_summary');
    $response->assertViewHas(['warehouses', 'categories', 'customers']);
});

test('sales summary data endpoint returns accurate metrics, breakdowns, and chart data', function () {
    $user = createTestSuperAdmin();

    // Create warehouse, customer, category, item
    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Main Test Warehouse',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'John Doe',
        'customer_code' => 'CUST-TEST-001',
        'mobile' => '01711000000',
        'status' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'Electronics',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Wireless Mouse',
        'item_code' => 'ITM-MOUSE-01',
        'category_id' => $category->id,
        'purchase_price' => 50.00,
        'sales_price' => 100.00,
        'stock' => 50,
        'status' => 1,
    ]);

    $today = Carbon::today()->format('Y-m-d');

    // Create a sale for 200.00 (2 qty of 100.00), paid 150.00, due 50.00
    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-TEST-999',
        'sales_date' => $today,
        'sales_status' => 'Final',
        'subtotal' => 200.00,
        'grand_total' => 200.00,
        'paid_amount' => 150.00,
        'payment_status' => 'Partial',
        'status' => 1,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 2,
        'price_per_unit' => 100.00,
        'total_cost' => 200.00,
        'purchase_price' => 50.00,
        'discount_amt' => 0.00,
        'tax_amt' => 0.00,
        'status' => 1,
    ]);

    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'customer_id' => $customer->id,
        'payment_date' => $today,
        'payment_type' => 'Cash',
        'payment' => 150.00,
        'status' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('reports.sales_summary_data', [
            'start_date' => $today,
            'end_date' => $today,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
        ]));

    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'summary' => [
            'total_sales',
            'total_orders',
            'total_paid',
            'total_due',
            'total_discount',
            'total_tax',
            'total_cost',
            'total_profit',
            'avg_order_value',
        ],
        'breakdowns' => [
            'payment_status',
            'payment_methods',
            'warehouse_wise',
            'top_products',
            'category_wise',
            'top_customers',
            'customers_with_due',
        ],
        'charts' => [
            'daily_trend',
            'payment_methods',
            'category_wise',
        ],
        'data',
    ]);

    $data = $response->json();

    expect($data['status'])->toBe('success')
        ->and((float)$data['summary']['total_sales'])->toBe(200.0)
        ->and((float)$data['summary']['total_paid'])->toBe(150.0)
        ->and((float)$data['summary']['total_due'])->toBe(50.0)
        ->and((int)$data['summary']['total_orders'])->toBe(1)
        ->and((float)$data['summary']['total_cost'])->toBe(100.0)
        ->and((float)$data['summary']['total_profit'])->toBe(100.0);
});
