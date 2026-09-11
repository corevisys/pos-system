<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbSmsTemplate;
use App\Models\SmsAutoRule;
use App\Models\SmsLog;
use App\Observers\SMSObserver;
use App\Models\DbSale;
use Carbon\Carbon;

function getPosSmsTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'POS Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('pos checkout creates only one SMS log entry for invoice created', function () {
    config(['sms.sandbox' => true]);
    config(['queue.default' => 'sync']);

    // Ensure SMSObserver is registered
    DbSale::observe(SMSObserver::class);

    $user = getPosSmsTestUser();

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'POS Warehouse',
        'status' => 1,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Bob POS',
        'customer_code' => 'CUST-POS-001',
        'mobile' => '01777000002',
        'status' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'Retail',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'POS Test Item',
        'item_code' => 'ITM-POS-001',
        'category_id' => $category->id,
        'purchase_price' => 50.00,
        'sales_price' => 100.00,
        'stock' => 50,
        'status' => 1,
    ]);

    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'available_qty' => 50,
    ]);

    $template = DbSmsTemplate::create([
        'store_id' => 1,
        'template_name' => 'Invoice Created',
        'category' => 'Transactional',
        'message_type' => 'transactional',
        'content' => 'Dear {customer_name}, invoice #{invoice_no} of total {total_amount} created. Due: {due_amount}.',
        'variables_used' => ['customer_name', 'invoice_no', 'total_amount', 'due_amount'],
        'status' => 1,
    ]);

    $rule = SmsAutoRule::create([
        'rule_name' => 'Invoice Created Rule',
        'event_type' => 'InvoiceCreated',
        'event_source' => 'invoice',
        'template_id' => $template->id,
        'trigger_time' => 'immediate',
        'days_offset' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson('/sales/pos/store', [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'cart' => [
            [
                'id' => $item->id,
                'qty' => 1,
                'price' => 100.00,
                'total' => 100.00,
            ]
        ],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'other_charges' => 0.00,
        'discount_on_all' => 0.00,
        'paid_amount' => 0.00,
    ]);

    $response->assertOk();

    $logs = SmsLog::where('phone', '01777000002')->get();

    // Assert that exactly ONE SMS log was created for this sale
    expect($logs->count())->toBe(1);
});
