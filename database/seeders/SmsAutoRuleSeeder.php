<?php

namespace Database\Seeders;

use App\Models\SmsAutoRule;
use App\Models\DbSmsTemplate;
use Illuminate\Database\Seeder;

class SmsAutoRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'rule_name' => 'Invoice Created Notification',
                'event_type' => 'InvoiceCreated',
                'event_source' => 'invoice',
                'template_name' => 'Invoice Created',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Payment Received Notification',
                'event_type' => 'PaymentReceived',
                'event_source' => 'invoice',
                'template_name' => 'Payment Received',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Sales Return Confirmation',
                'event_type' => 'SalesReturnConfirmation',
                'event_source' => 'invoice',
                'template_name' => 'Sales Return Confirmation',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Purchase Created (Supplier)',
                'event_type' => 'PurchaseCreated',
                'event_source' => 'purchase',
                'template_name' => 'Purchase Created (Supplier)',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'EMI Due Reminder',
                'event_type' => 'EmiDue',
                'event_source' => 'emi',
                'template_name' => 'EMI Due (Reminder)',
                'trigger_time' => 'before_due',
                'days_offset' => 1,
            ],
            [
                'rule_name' => 'EMI Overdue Alert',
                'event_type' => 'EmiOverdue',
                'event_source' => 'emi',
                'template_name' => 'EMI Overdue',
                'trigger_time' => 'after_due',
                'days_offset' => 1,
            ],
            [
                'rule_name' => 'EMI Payment Confirmation',
                'event_type' => 'EmiPaymentConfirmation',
                'event_source' => 'emi',
                'template_name' => 'EMI Payment Confirmation',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'EMI Completion Notification',
                'event_type' => 'EmiCompletion',
                'event_source' => 'emi',
                'template_name' => 'EMI Completion',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Service Due Reminder',
                'event_type' => 'ServiceDueReminder',
                'event_source' => 'invoice',
                'template_name' => 'Service Due Reminder',
                'trigger_time' => 'before_due',
                'days_offset' => 1,
            ],
            [
                'rule_name' => 'Low Stock Alert (Admin)',
                'event_type' => 'LowStock',
                'event_source' => 'manual',
                'template_name' => 'Low Stock (Admin)',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Warehouse Low Stock',
                'event_type' => 'WarehouseLowStock',
                'event_source' => 'manual',
                'template_name' => 'Warehouse Low Stock',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Stock Adjustment Alert',
                'event_type' => 'StockAdjustmentAlert',
                'event_source' => 'manual',
                'template_name' => 'Stock Adjustment Alert',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Customer Birthday Wish',
                'event_type' => 'CustomerBirthday',
                'event_source' => 'birthday',
                'template_name' => 'Birthday',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Festival Campaign',
                'event_type' => 'FestivalCampaign',
                'event_source' => 'manual',
                'template_name' => 'Festival Campaign',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Coupon Expiry Reminder',
                'event_type' => 'CouponExpiry',
                'event_source' => 'manual',
                'template_name' => 'Coupon Expiry',
                'trigger_time' => 'before_due',
                'days_offset' => 1,
            ],
            [
                'rule_name' => 'Win-back Message',
                'event_type' => 'WinbackMessage',
                'event_source' => 'manual',
                'template_name' => 'Win-back Message',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'EOD Summary Report',
                'event_type' => 'EodSummary',
                'event_source' => 'manual',
                'template_name' => 'EOD Summary',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Large Transaction Alert',
                'event_type' => 'LargeTransactionAlert',
                'event_source' => 'manual',
                'template_name' => 'Large Transaction Alert',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'Database Backup Alert',
                'event_type' => 'BackupCompletedAlert',
                'event_source' => 'manual',
                'template_name' => 'Backup Completed Alert',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
            [
                'rule_name' => 'New Customer Welcome',
                'event_type' => 'CustomerAdded',
                'event_source' => 'marketing',
                'template_name' => 'Customer Registration',
                'trigger_time' => 'immediate',
                'days_offset' => 0,
            ],
        ];

        foreach ($rules as $rule) {
            $template = DbSmsTemplate::where('template_name', $rule['template_name'])->first();
            
            if ($template) {
                SmsAutoRule::updateOrCreate(
                    ['event_type' => $rule['event_type']],
                    [
                        'rule_name' => $rule['rule_name'],
                        'event_source' => $rule['event_source'],
                        'template_id' => $template->id,
                        'trigger_time' => $rule['trigger_time'],
                        'days_offset' => $rule['days_offset'],
                        'cooldown_days' => 0,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
