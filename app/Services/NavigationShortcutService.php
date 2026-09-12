<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class NavigationShortcutService
{
    /**
     * Complete catalogue of sidebar pages and module shortcuts.
     * All entries map to their exact module key, page key, route name, and permission slug.
     */
    public const SHORTCUTS = [
        'Dashboard' => [
            'module_key' => 'D',
            'module' => 'Dashboard',
            'items' => [
                ['label' => 'Dashboard', 'page_key' => 'D', 'route' => 'dashboard', 'permission' => 'dashboard_view_dashboard_data'],
            ]
        ],
        'Users' => [
            'module_key' => 'U',
            'module' => 'Users',
            'items' => [
                ['label' => 'Users List', 'page_key' => 'U', 'route' => 'users.list', 'permission' => 'users_view'],
                ['label' => 'Roles List', 'page_key' => 'R', 'route' => 'users.roles', 'permission' => 'roles_view'],
            ]
        ],
        'Sales' => [
            'module_key' => 'S',
            'module' => 'Sales',
            'items' => [
                ['label' => 'POS', 'page_key' => 'P', 'route' => 'sales.pos', 'permission' => 'sales_add'],
                ['label' => 'Add Sale', 'page_key' => 'A', 'route' => 'sales.add', 'permission' => 'sales_add'],
                ['label' => 'Sales List', 'page_key' => 'L', 'route' => 'sales.list', 'permission' => 'sales_view'],
                ['label' => 'Sales Payments', 'page_key' => 'Y', 'route' => 'sales.payments', 'permission' => 'sales_payment_view'],
                ['label' => 'Sales Returns List', 'page_key' => 'R', 'route' => 'sales.returns', 'permission' => 'sales_return_view'],
                ['label' => 'EMI Sale List', 'page_key' => 'E', 'route' => 'sales.emi.list', 'permission' => 'sales_view'],
                ['label' => 'Hold Sales List', 'page_key' => 'H', 'route' => 'sales.hold.list', 'permission' => 'sales_view'],
            ]
        ],
        'Contacts' => [
            'module_key' => 'C',
            'module' => 'Contacts',
            'items' => [
                ['label' => 'Add Customer', 'page_key' => 'C', 'route' => 'contacts.customers.add', 'permission' => 'customers_add'],
                ['label' => 'Customers List', 'page_key' => 'L', 'route' => 'contacts.customers.list', 'permission' => 'customers_view'],
                ['label' => 'Add Supplier', 'page_key' => 'S', 'route' => 'contacts.suppliers.add', 'permission' => 'suppliers_add'],
                ['label' => 'Suppliers List', 'page_key' => 'U', 'route' => 'contacts.suppliers.list', 'permission' => 'suppliers_view'],
                ['label' => 'Import Customers', 'page_key' => 'I', 'route' => 'contacts.customers.import', 'permission' => 'customers_import_customers'],
                ['label' => 'Import Suppliers', 'page_key' => 'M', 'route' => 'contacts.suppliers.import', 'permission' => 'suppliers_import_suppliers'],
            ]
        ],
        'Advance' => [
            'module_key' => 'V',
            'module' => 'Advance',
            'items' => [
                ['label' => 'Add Advance', 'page_key' => 'A', 'route' => 'advance.add', 'permission' => 'customers_advance_payments_add'],
                ['label' => 'Advance List', 'page_key' => 'L', 'route' => 'advance.list', 'permission' => 'customers_advance_payments_view'],
            ]
        ],
        'Coupons' => [
            'module_key' => 'O',
            'module' => 'Coupons',
            'items' => [
                ['label' => 'Create Customer Coupon', 'page_key' => 'C', 'route' => 'coupons.customer.create', 'permission' => 'customer_coupon_add'],
                ['label' => 'Customer Coupons List', 'page_key' => 'L', 'route' => 'coupons.customer.list', 'permission' => 'customer_coupon_view'],
                ['label' => 'Create Coupon', 'page_key' => 'A', 'route' => 'coupons.create', 'permission' => 'discount_coupon_add'],
                ['label' => 'Coupons Master', 'page_key' => 'M', 'route' => 'coupons.master', 'permission' => 'discount_coupon_view'],
            ]
        ],
        'Quotation' => [
            'module_key' => 'Q',
            'module' => 'Quotation',
            'items' => [
                ['label' => 'New Quotation', 'page_key' => 'N', 'route' => 'quotation.new', 'permission' => 'quotation_add'],
                ['label' => 'Quotation List', 'page_key' => 'L', 'route' => 'quotation.list', 'permission' => 'quotation_view'],
            ]
        ],
        'Purchase' => [
            'module_key' => 'P',
            'module' => 'Purchase',
            'items' => [
                ['label' => 'New Purchase', 'page_key' => 'N', 'route' => 'purchase.new', 'permission' => 'purchase_add'],
                ['label' => 'Purchase List', 'page_key' => 'L', 'route' => 'purchase.list', 'permission' => 'purchase_view'],
                ['label' => 'Purchase Returns List', 'page_key' => 'R', 'route' => 'purchase.returns', 'permission' => 'purchase_return_view'],
            ]
        ],
        'Accounts' => [
            'module_key' => 'A',
            'module' => 'Accounts',
            'items' => [
                ['label' => 'Add Account', 'page_key' => 'A', 'route' => 'accounts.add', 'permission' => 'accounts_add'],
                ['label' => 'Accounts List', 'page_key' => 'L', 'route' => 'accounts.list', 'permission' => 'accounts_view'],
                ['label' => 'Money Transfer List', 'page_key' => 'T', 'route' => 'accounts.transfer', 'permission' => 'money_transfer_view'],
                ['label' => 'Deposit List', 'page_key' => 'D', 'route' => 'accounts.deposit', 'permission' => 'money_deposit_view'],
                ['label' => 'Cash Transactions', 'page_key' => 'C', 'route' => 'accounts.transactions', 'permission' => 'cash_transactions'],
            ]
        ],
        'Items' => [
            'module_key' => 'I',
            'module' => 'Items',
            'items' => [
                ['label' => 'Add Item', 'page_key' => 'A', 'route' => 'items.add', 'permission' => 'items_add'],
                ['label' => 'Add Service', 'page_key' => 'S', 'route' => 'items.service.add', 'permission' => 'services_add'],
                ['label' => 'Services List', 'page_key' => 'E', 'route' => 'items.service.list', 'permission' => 'services_view'],
                ['label' => 'Items List', 'page_key' => 'L', 'route' => 'items.list', 'permission' => 'items_view'],
                ['label' => 'Categories List', 'page_key' => 'C', 'route' => 'items.categories', 'permission' => 'items_category_view'],
                ['label' => 'Brands List', 'page_key' => 'B', 'route' => 'items.brands', 'permission' => 'brand_view'],
                ['label' => 'Variants List', 'page_key' => 'V', 'route' => 'items.variants', 'permission' => 'variant_view'],
                ['label' => 'Print Labels', 'page_key' => 'P', 'route' => 'items.labels', 'permission' => 'items_print_labels'],
                ['label' => 'Import Items', 'page_key' => 'I', 'route' => 'items.import', 'permission' => 'items_import_items'],
            ]
        ],
        'Stock' => [
            'module_key' => 'T',
            'module' => 'Stock',
            'items' => [
                ['label' => 'Adjustment List', 'page_key' => 'A', 'route' => 'stock.adjustment', 'permission' => 'stock_adjustment_view'],
                ['label' => 'Transfer List', 'page_key' => 'T', 'route' => 'stock.transfer', 'permission' => 'stock_transfer_view'],
            ]
        ],
        'Expenses' => [
            'module_key' => 'E',
            'module' => 'Expenses',
            'items' => [
                ['label' => 'Expenses List', 'page_key' => 'L', 'route' => 'expenses.list', 'permission' => 'expense_view'],
                ['label' => 'Categories List', 'page_key' => 'C', 'route' => 'expenses.categories', 'permission' => 'expense_category_view'],
            ]
        ],
        'Messaging' => [
            'module_key' => 'M',
            'module' => 'Messaging',
            'items' => [
                ['label' => 'SMS History', 'page_key' => 'H', 'route' => 'sms.history', 'permission' => 'sms_whatsapp_message_api_view'],
                ['label' => 'Send SMS', 'page_key' => 'S', 'route' => 'sms.send', 'permission' => 'sms_whatsapp_send_message'],
                ['label' => 'SMS Templates', 'page_key' => 'T', 'route' => 'sms.templates', 'permission' => 'sms_whatsapp_message_template_view'],
                ['label' => 'Campaigns', 'page_key' => 'C', 'route' => 'sms.campaigns', 'permission' => 'sms_whatsapp_message_api_view'],
                ['label' => 'SMS Logs', 'page_key' => 'L', 'route' => 'sms.logs', 'permission' => 'sms_whatsapp_message_api_view'],
                ['label' => 'Auto Rules', 'page_key' => 'R', 'route' => 'sms.auto-rules', 'permission' => 'sms_whatsapp_message_settings'],
                ['label' => 'SMS Settings', 'page_key' => 'G', 'route' => 'messaging.settings', 'permission' => 'sms_whatsapp_message_settings'],
            ]
        ],
        'Reports' => [
            'module_key' => 'R',
            'module' => 'Reports',
            'items' => [
                ['label' => 'Sales Summary', 'page_key' => '1', 'route' => 'reports.sales_summary', 'permission' => 'reports_view'],
                ['label' => 'Profit & Loss Report', 'page_key' => '2', 'route' => 'reports.profit_loss', 'permission' => 'reports_view'],
                ['label' => 'Sales & Payment Report', 'page_key' => '3', 'route' => 'reports.sales_payment', 'permission' => 'reports_view'],
                ['label' => 'Customer Orders', 'page_key' => '4', 'route' => 'reports.customer_orders', 'permission' => 'reports_view'],
                ['label' => 'GSTR-1 Report', 'page_key' => '5', 'route' => 'reports.gstr1', 'permission' => 'reports_view'],
                ['label' => 'GSTR-2 Report', 'page_key' => '6', 'route' => 'reports.gstr2', 'permission' => 'reports_view'],
                ['label' => 'Sales GST Report', 'page_key' => '7', 'route' => 'reports.sales_gst', 'permission' => 'reports_view'],
                ['label' => 'Purchase GST Report', 'page_key' => '8', 'route' => 'reports.purchase_gst', 'permission' => 'reports_view'],
                ['label' => 'Sales Tax Report', 'page_key' => '9', 'route' => 'reports.sales_tax', 'permission' => 'reports_view'],
                ['label' => 'Purchase Tax Report', 'page_key' => '0', 'route' => 'reports.purchase_tax', 'permission' => 'reports_view'],
                ['label' => 'Supplier Items Report', 'page_key' => 'A', 'route' => 'reports.supplier_items', 'permission' => 'reports_view'],
                ['label' => 'Sales Report', 'page_key' => 'S', 'route' => 'reports.sales', 'permission' => 'reports_view'],
                ['label' => 'Sales Return Report', 'page_key' => 'R', 'route' => 'reports.sales_return', 'permission' => 'reports_view'],
                ['label' => 'Seller Points Report', 'page_key' => 'P', 'route' => 'reports.seller_points', 'permission' => 'reports_view'],
                ['label' => 'Purchase Report', 'page_key' => 'U', 'route' => 'reports.purchase', 'permission' => 'reports_view'],
                ['label' => 'Purchase Return Report', 'page_key' => 'B', 'route' => 'reports.purchase_return', 'permission' => 'reports_view'],
                ['label' => 'Expense Report', 'page_key' => 'E', 'route' => 'reports.expense', 'permission' => 'reports_view'],
                ['label' => 'Stock Report', 'page_key' => 'T', 'route' => 'reports.stock', 'permission' => 'reports_view'],
                ['label' => 'Sales Item Report', 'page_key' => 'I', 'route' => 'reports.sales_item', 'permission' => 'reports_view'],
                ['label' => 'Return Items Report', 'page_key' => 'N', 'route' => 'reports.return_items', 'permission' => 'reports_view'],
                ['label' => 'Purchase Payments Report', 'page_key' => 'K', 'route' => 'reports.purchase_payments', 'permission' => 'reports_view'],
                ['label' => 'Sales Payments Report', 'page_key' => 'Y', 'route' => 'reports.sales_payments', 'permission' => 'reports_view'],
            ]
        ],
        'Warehouse' => [
            'module_key' => 'W',
            'module' => 'Warehouse',
            'items' => [
                ['label' => 'Add Warehouse', 'page_key' => 'A', 'route' => 'warehouse.add', 'permission' => 'warehouse_add'],
                ['label' => 'Warehouse List', 'page_key' => 'L', 'route' => 'warehouse.list', 'permission' => 'warehouse_view'],
            ]
        ],
        'Settings' => [
            'module_key' => 'G',
            'module' => 'Settings',
            'items' => [
                ['label' => 'Store', 'page_key' => 'S', 'route' => 'settings.store', 'permission' => 'store_settings_view'],
                ['label' => 'Languages List', 'page_key' => 'L', 'route' => 'settings.languages.index', 'permission' => 'language_view'],
                ['label' => 'Countries List', 'page_key' => 'C', 'route' => 'settings.countries', 'permission' => 'country_view'],
                ['label' => 'States List', 'page_key' => 'T', 'route' => 'settings.states', 'permission' => 'state_view'],
                ['label' => 'SMS/WhatsApp API', 'page_key' => 'M', 'route' => 'sms.settings', 'permission' => 'sms_whatsapp_message_settings'],
                ['label' => 'Tax List', 'page_key' => 'X', 'route' => 'settings.tax', 'permission' => 'tax_view'],
                ['label' => 'Units List', 'page_key' => 'U', 'route' => 'settings.units', 'permission' => 'unit_view'],
                ['label' => 'Payment Types', 'page_key' => 'P', 'route' => 'settings.payment_types', 'permission' => 'payment_types_view'],
                ['label' => 'Site Settings', 'page_key' => 'I', 'route' => 'settings.site_settings', 'permission' => 'site_settings_view'],
                ['label' => 'SMTP', 'page_key' => 'E', 'route' => 'settings.smtp', 'permission' => 'smtp_settings_view'],
                ['label' => 'Currency List', 'page_key' => 'R', 'route' => 'settings.currency', 'permission' => 'currency_view'],
                ['label' => 'Change Password', 'page_key' => 'W', 'route' => 'settings.password', 'permission' => 'change_password'],
                ['label' => 'Database Backup', 'page_key' => 'B', 'route' => 'settings.backup', 'permission' => 'database_backup'],
            ]
        ],
        'Account / Profile' => [
            'module_key' => 'F',
            'module' => 'Account / Profile',
            'items' => [
                ['label' => 'Profile', 'page_key' => 'P', 'route' => 'profile.edit', 'permission' => null],
            ]
        ],
    ];

    /**
     * Build the shortcut list for a given user, resolving each item's permission flag.
     * The returned array is keyed by module_key so the keyboard UI can look up a module
     * directly from the pressed module key.
     *
     * @return array
     */
    public static function getShortcutsForUser(User $user): array
    {
        $modules = [];

        foreach (self::SHORTCUTS as $module) {
            $moduleItems = [];

            foreach ($module['items'] as $item) {
                $url = $item['permission'] === null ? route($item['route']) : (Route::has($item['route']) ? route($item['route']) : '#');
                $moduleItems[] = [
                    'label'          => $item['label'],
                    'page_key'       => $item['page_key'],
                    'route'          => $item['route'],
                    'url'            => $url,
                    'permission'     => $item['permission'],
                    'has_permission' => $item['permission'] === null || $user->hasPermission($item['permission']),
                    'sequence'       => 'Ctrl+Alt+' . strtoupper($module['module_key']) . ' → ' . strtoupper($item['page_key']),
                ];
            }

            $modules[$module['module_key']] = [
                'module_key' => $module['module_key'],
                'module'     => $module['module'],
                'items'      => $moduleItems,
            ];
        }

        return $modules;
    }
}
