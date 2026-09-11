<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbSale;
use App\Models\DbSalesReturn;
use App\Models\DbSmsTemplate;
use App\Models\SmsAutoRule;
use App\Models\SmsLog;
use App\SMS\Services\SmsTriggerService;
use Carbon\Carbon;

function getSmsTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Corevisys Store',
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
        'permissions' => ['sms_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('SalesReturnConfirmation SMS trigger maps grand_total to amount variable correctly', function () {
    config(['sms.sandbox' => true]);
    $user = getSmsTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Jane Smith',
        'customer_code' => 'CUST-SMS-001',
        'mobile' => '01888000001',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Main Warehouse',
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-SMS-101',
        'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 500.00,
        'grand_total' => 500.00,
        'paid_amount' => 500.00,
        'payment_status' => 'Paid',
        'status' => 1,
    ]);

    $salesReturn = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'RTN-SMS-101',
        'return_date' => Carbon::today()->format('Y-m-d'),
        'return_status' => 'Completed',
        'subtotal' => 250.00,
        'grand_total' => 250.00,
        'paid_amount' => 250.00,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    $template = DbSmsTemplate::create([
        'store_id' => 1,
        'template_name' => 'Sales Return Confirmation Test',
        'category' => 'Transactional',
        'message_type' => 'transactional',
        'content' => 'Dear {customer_name}, return for {invoice_no} processed. Amount: {amount}. Thank you.',
        'variables_used' => ['customer_name', 'invoice_no', 'amount'],
        'status' => 1,
    ]);

    $rule = SmsAutoRule::create([
        'rule_name' => 'Sales Return Rule Test',
        'event_type' => 'SalesReturnConfirmation',
        'event_source' => 'invoice',
        'template_id' => $template->id,
        'trigger_time' => 'immediate',
        'days_offset' => 0,
        'is_active' => true,
    ]);

    $triggerService = app(SmsTriggerService::class);
    $triggerService->trigger('SalesReturnConfirmation', $salesReturn);

    $log = SmsLog::where('phone', '01888000001')->latest()->first();

    expect($log)->not->toBeNull();
    expect($log->message)->toContain('Amount: 250.00');
    expect($log->message)->toContain('Dear Jane Smith');
    expect($log->message)->toContain('return for SA-SMS-101 processed');
});
