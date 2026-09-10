<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DbRole;
use App\Models\DbPermission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensurse a Super Admin role exists
        $role = DbRole::firstOrCreate(
            ['role_name' => 'Super Admin'],
            [
                'description' => 'Super Admin Role with full permissions',
                'status' => 1,
                'store_id' => 1, // Assuming default store ID
            ]
        );

        $permissions = [
            'items_add', 'items_edit', 'items_delete', 'items_view',
            'items_import_items', 'brand_add', 'brand_edit', 'brand_delete', 'brand_view',
            'customers_add', 'customers_edit', 'customers_delete', 'customers_view',
            'sales_add', 'sales_edit', 'sales_delete', 'sales_view',
            'sales_payment_view', 'sales_payment_add', 'sales_payment_delete',
            'sales_report', 'sales_payments_report',
            'items_category_add', 'items_category_edit', 'items_category_delete', 'items_category_view',
            'print_labels', 'dashboard_view',
            'dashboard_info_box_1', 'dashboard_info_box_2', 'dashboard_pur_sal_chart',
            'dashboard_recent_items', 'dashboard_stock_alert', 'dashboard_trending_items_chart',
            'sales_return_add', 'sales_return_edit', 'sales_return_delete', 'sales_return_view',
            'sales_return_report', 'sales_return_payment_view', 'sales_return_payment_add', 'sales_return_payment_delete',
            'payment_types_add', 'payment_types_edit', 'payment_types_delete', 'payment_types_view',
            'language_view', 'country_view', 'state_view', 'currency_view',
            'import_customers', 'stock_transfer_add', 'stock_transfer_edit', 'stock_transfer_delete', 'stock_transfer_view',
            'seller_points_report', 'services_add', 'services_edit', 'services_delete', 'services_view',
            // Reserved: unused since the Import Services page/route was removed; kept for
            // a potential future re-implementation of bulk service import.
            'import_services', 'stock_adjustment_add', 'stock_adjustment_edit', 'stock_adjustment_delete', 'stock_adjustment_view',
            'variant_add', 'variant_edit', 'variant_delete', 'variant_view',
            'accounts_add', 'accounts_edit', 'accounts_delete', 'accounts_view',
            'money_transfer_add', 'money_transfer_edit', 'money_transfer_delete', 'money_transfer_view',
            'money_deposit_add', 'money_deposit_edit', 'money_deposit_delete', 'money_deposit_view',
            'sales_tax_report', 'tax_add', 'tax_edit', 'tax_delete', 'tax_view',
            'units_add', 'units_edit', 'units_delete', 'units_view',
            'suppliers_add', 'suppliers_edit', 'suppliers_delete', 'suppliers_view',
            'purchase_add', 'purchase_edit', 'purchase_delete', 'purchase_view',
            'purchase_report', 'purchase_payments_report',
            'purchase_return_add', 'purchase_return_edit', 'purchase_return_delete', 'purchase_return_view',
            'purchase_return_report', 'purchase_return_payment_view', 'purchase_return_payment_add', 'purchase_return_payment_delete',
            'purchase_payment_view', 'purchase_payment_add', 'purchase_payment_delete',
            'import_suppliers', 'warehouse_add', 'warehouse_edit', 'warehouse_delete', 'warehouse_view',
            'purchase_tax_report', 'users_add', 'users_edit', 'users_delete', 'users_view',
            'store_edit', 'store_settings_view', 'store_settings_edit', 'roles_add', 'roles_edit', 'roles_delete', 'roles_view',
            'expense_add', 'expense_edit', 'expense_delete', 'expense_view',
            'expense_report', 'profit_report', 'stock_report', 'item_sales_report',
            'expense_category_add', 'expense_category_edit', 'expense_category_delete', 'expense_category_view',
            'send_sms', 'sms_template_edit', 'sms_template_view', 'sms_api_view', 'sms_api_edit',
            'supplier_items_report', 'quotation_add', 'quotation_edit', 'quotation_delete', 'quotation_view',
            'cash_transactions', 'show_all_users_sales_invoices', 'show_all_users_sales_return_invoices',
            'show_all_users_purchase_invoices', 'show_all_users_purchase_return_invoices',
            'show_all_users_expenses', 'show_all_users_quotations', 'subscription',
            'smtp_settings_view', 'send_email', 'sms_settings', 'email_template_edit', 'email_template_view',
            'cust_adv_payments_add', 'cust_adv_payments_edit', 'cust_adv_payments_delete', 'cust_adv_payments_view',
            'gstr_1_report', 'gstr_2_report', 'delivery_sheet_report', 'load_sheet_report',
            'show_purchase_price', 'customer_orders_report',
            'discountCouponAdd', 'discountCouponEdit', 'discountCouponDelete', 'discountCouponView',
            'sales_gst_report', 'purchase_gst_report',
            'customerCouponAdd', 'customerCouponEdit', 'customerCouponDelete', 'customerCouponView',
            'return_items_report', 'help_link', 'recent_sales_invoice_list',
            'cash_reconciliation_view', 'cash_reconciliation_add', 'cash_reconciliation_adjust', 'cash_reconciliation_delete', 'cash_reconciliation_report'
        ];

        DbPermission::updateOrCreate(
            ['role_id' => $role->id],
            [
                'store_id' => $role->store_id,
                'permissions' => $permissions,
            ]
        );
    }
}
