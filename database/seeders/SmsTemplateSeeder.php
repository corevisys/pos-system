<?php

namespace Database\Seeders;

use App\Models\DbSmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'template_name' => 'Invoice Created',
                'category' => 'Transactional',
                'message_type' => 'transactional',
                'content' => 'Dear {customer_name}, your Invoice #{invoice_no} has been created. Total: {total_amount}. Due: {due_amount}. Thank you for shopping with {store_name}.',
                'variables_used' => ['customer_name', 'invoice_no', 'total_amount', 'due_amount', 'store_name'],
            ],
            [
                'template_name' => 'Payment Received',
                'category' => 'Transactional',
                'message_type' => 'transactional',
                'content' => 'Dear {customer_name}, we received your payment of {paid_amount} for Invoice #{invoice_no}. Remaining balance: {due_amount}. Thank you.',
                'variables_used' => ['customer_name', 'paid_amount', 'invoice_no', 'due_amount'],
            ],
            [
                'template_name' => 'Sales Return Confirmation',
                'category' => 'Transactional',
                'message_type' => 'transactional',
                'content' => 'Dear {customer_name}, your return for Invoice #{invoice_no} has been processed. Refund/Credit: {amount}. Thank you.',
                'variables_used' => ['customer_name', 'invoice_no', 'amount'],
            ],
            [
                'template_name' => 'Purchase Created (Supplier)',
                'category' => 'Transactional',
                'message_type' => 'transactional',
                'content' => 'Dear {supplier_name}, Purchase Order #{purchase_no} created. Total amount: {total}. Please confirm delivery schedule.',
                'variables_used' => ['supplier_name', 'purchase_no', 'total'],
            ],
            [
                'template_name' => 'EMI Due (Reminder)',
                'category' => 'EMI',
                'message_type' => 'transactional',
                'content' => 'Reminder: EMI of {emi_amount} for Invoice #{invoice_no} is due on {due_date}. Please pay on time to avoid late fees.',
                'variables_used' => ['emi_amount', 'invoice_no', 'due_date'],
            ],
            [
                'template_name' => 'EMI Overdue',
                'category' => 'EMI',
                'message_type' => 'transactional',
                'content' => 'Urgent: EMI of {emi_amount} for Invoice #{invoice_no} was due on {due_date}. Please clear immediately to avoid penalty.',
                'variables_used' => ['emi_amount', 'invoice_no', 'due_date'],
            ],
            [
                'template_name' => 'EMI Payment Confirmation',
                'category' => 'EMI',
                'message_type' => 'transactional',
                'content' => 'Thank you! EMI payment of {paid_amount} for Invoice #{invoice_no} received successfully. Next due date: {next_due_date}.',
                'variables_used' => ['paid_amount', 'invoice_no', 'next_due_date'],
            ],
            [
                'template_name' => 'EMI Completion',
                'category' => 'EMI',
                'message_type' => 'transactional',
                'content' => 'Congratulations! Your EMI for Invoice #{invoice_no} is fully completed. Thank you for your trust in {store_name}.',
                'variables_used' => ['invoice_no', 'store_name'],
            ],
            [
                'template_name' => 'Service Due Reminder',
                'category' => 'Service',
                'message_type' => 'transactional',
                'content' => 'Service Reminder: Your product from Invoice #{invoice_no} requires service on {service_date}. Please visit {store_name}.',
                'variables_used' => ['invoice_no', 'service_date', 'store_name'],
            ],
            [
                'template_name' => 'Low Stock (Admin)',
                'category' => 'Inventory',
                'message_type' => 'transactional',
                'content' => 'Stock Alert: {item_name} is low. Only {current_qty} left in stock. Please restock soon.',
                'variables_used' => ['item_name', 'current_qty'],
            ],
            [
                'template_name' => 'Warehouse Low Stock',
                'category' => 'Inventory',
                'message_type' => 'transactional',
                'content' => 'Warehouse Alert: {item_name} in {warehouse_name} has only {current_qty} units left.',
                'variables_used' => ['item_name', 'warehouse_name', 'current_qty'],
            ],
            [
                'template_name' => 'Stock Adjustment Alert',
                'category' => 'Inventory',
                'message_type' => 'transactional',
                'content' => 'Stock adjusted for {item_name}. Adjustment Qty: {adjusted_qty}. Please verify records.',
                'variables_used' => ['item_name', 'adjusted_qty'],
            ],
            [
                'template_name' => 'Birthday',
                'category' => 'CRM',
                'message_type' => 'promo',
                'content' => 'Happy Birthday {customer_name}! Enjoy a special offer from {store_name}. Visit us soon 🎉',
                'variables_used' => ['customer_name', 'store_name'],
            ],
            [
                'template_name' => 'Festival Campaign',
                'category' => 'CRM',
                'message_type' => 'promo',
                'content' => '{festival_name} Special Offer! Get exciting discounts at {store_name}. Offer valid till {offer_date}. Visit today!',
                'variables_used' => ['festival_name', 'store_name', 'offer_date'],
            ],
            [
                'template_name' => 'Coupon Expiry',
                'category' => 'CRM',
                'message_type' => 'promo',
                'content' => 'Dear {customer_name}, your coupon {coupon_code} expires on {expiry_date}. Don’t miss this offer!',
                'variables_used' => ['customer_name', 'coupon_code', 'expiry_date'],
            ],
            [
                'template_name' => 'Win-back Message',
                'category' => 'CRM',
                'message_type' => 'promo',
                'content' => 'We miss you, {customer_name}! Visit {store_name} this week and enjoy a special surprise on your purchase.',
                'variables_used' => ['customer_name', 'store_name'],
            ],
            [
                'template_name' => 'EOD Summary',
                'category' => 'Admin Intelligence',
                'message_type' => 'transactional',
                'content' => 'EOD Report ({date}): Sales {total_sales}, Cash {cash_sales}, EMI {emi_collected}, Expenses {expenses}. Net: {net_amount}.',
                'variables_used' => ['date', 'total_sales', 'cash_sales', 'emi_collected', 'expenses', 'net_amount'],
            ],
            [
                'template_name' => 'Large Transaction Alert',
                'category' => 'Admin Intelligence',
                'message_type' => 'transactional',
                'content' => 'Alert: High-value transaction detected. Amount: {amount}. Invoice #{invoice_no}.',
                'variables_used' => ['amount', 'invoice_no'],
            ],
            [
                'template_name' => 'Backup Completed Alert',
                'category' => 'Admin Intelligence',
                'message_type' => 'transactional',
                'content' => 'Database Backup completed successfully on {date_time}. System secure.',
                'variables_used' => ['date_time'],
            ],
            [
                'template_name' => 'Customer Registration',
                'category' => 'CRM',
                'message_type' => 'transactional',
                'content' => 'Welcome {customer_name}! Thank you for registering at {store_name}. We are happy to have you with us.',
                'variables_used' => ['customer_name', 'store_name'],
            ],
        ];

        foreach ($templates as $template) {
            DbSmsTemplate::updateOrCreate(
                ['template_name' => $template['template_name']],
                [
                    'category' => $template['category'],
                    'message_type' => $template['message_type'],
                    'content' => $template['content'],
                    'variables_used' => $template['variables_used'],
                    'status' => 1,
                    'store_id' => 1, // Default store or null if per-store not needed yet
                    'language' => 'en',
                ]
            );
        }
    }
}
