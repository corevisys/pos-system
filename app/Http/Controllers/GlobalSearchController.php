<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbCustomer;
use App\Models\DbSupplier;
use App\Models\DbSale;
use App\Models\DbPurchase;
use App\Models\DbQuotation;
use App\Models\DbSalesReturn;
use App\Models\DbPurchaseReturn;
use App\Models\DbStockTransfer;
use App\Models\DbStockAdjustment;
use App\Models\DbExpense;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    /**
     * Complete catalogue of searchable application navigation pages and menu actions.
     * All items map to their exact route name and permission gate.
     */
    private const PAGES = [
        // ── Dashboard & Profile ─────────────────────────────────────────────
        ['label' => 'Dashboard', 'module' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'keywords' => ['home', 'analytics', 'overview', 'stats']],
        ['label' => 'Profile', 'module' => 'Account', 'route' => 'profile.edit', 'permission' => null, 'keywords' => ['user profile', 'my account', 'settings']],

        // ── Users & Roles ───────────────────────────────────────────────────
        ['label' => 'Users List', 'module' => 'Users', 'route' => 'users.list', 'permission' => 'users_view', 'keywords' => ['staff', 'employees', 'all users']],
        ['label' => 'Create User', 'module' => 'Users', 'route' => 'users.create', 'permission' => 'users_add', 'keywords' => ['add user', 'new user', 'register staff']],
        ['label' => 'Roles List', 'module' => 'Users', 'route' => 'users.roles', 'permission' => 'roles_view', 'keywords' => ['permissions', 'user roles', 'access control']],
        ['label' => 'Create Role', 'module' => 'Users', 'route' => 'users.roles.create', 'permission' => 'roles_add', 'keywords' => ['add role', 'new role', 'permission group']],

        // ── Sales & POS ─────────────────────────────────────────────────────
        ['label' => 'POS (Point of Sale)', 'module' => 'Sales', 'route' => 'sales.pos', 'permission' => 'sales_add', 'keywords' => ['pos terminal', 'checkout', 'billing', 'cashier']],
        ['label' => 'Add Sale', 'module' => 'Sales', 'route' => 'sales.add', 'permission' => 'sales_add', 'keywords' => ['new sale', 'create invoice', 'sell']],
        ['label' => 'Sales List', 'module' => 'Sales', 'route' => 'sales.list', 'permission' => 'sales_view', 'keywords' => ['invoices', 'all sales', 'orders']],
        ['label' => 'Sales Payments', 'module' => 'Sales', 'route' => 'sales.payments', 'permission' => 'sales_payment_view', 'keywords' => ['received payments', 'customer payments']],
        ['label' => 'Sales Returns List', 'module' => 'Sales', 'route' => 'sales.returns', 'permission' => 'sales_return_view', 'keywords' => ['refunds', 'credit notes', 'customer returns']],
        ['label' => 'EMI Sale List', 'module' => 'Sales', 'route' => 'sales.emi.list', 'permission' => 'sales_view', 'keywords' => ['installments', 'emi payments', 'loan sales']],
        ['label' => 'Hold Sales List', 'module' => 'Sales', 'route' => 'sales.hold.list', 'permission' => 'sales_view', 'keywords' => ['suspended sales', 'draft orders', 'saved cart']],

        // ── Contacts ────────────────────────────────────────────────────────
        ['label' => 'Add Customer', 'module' => 'Contacts', 'route' => 'contacts.customers.add', 'permission' => 'customers_add', 'keywords' => ['new customer', 'create client']],
        ['label' => 'Customers List', 'module' => 'Contacts', 'route' => 'contacts.customers.list', 'permission' => 'customers_view', 'keywords' => ['all customers', 'clients', 'buyers']],
        ['label' => 'Add Supplier', 'module' => 'Contacts', 'route' => 'contacts.suppliers.add', 'permission' => 'suppliers_add', 'keywords' => ['new supplier', 'create vendor']],
        ['label' => 'Suppliers List', 'module' => 'Contacts', 'route' => 'contacts.suppliers.list', 'permission' => 'suppliers_view', 'keywords' => ['vendors', 'all suppliers']],
        ['label' => 'Import Customers', 'module' => 'Contacts', 'route' => 'contacts.customers.import', 'permission' => 'customers_import_customers', 'keywords' => ['csv import customers', 'bulk customer upload']],
        ['label' => 'Import Suppliers', 'module' => 'Contacts', 'route' => 'contacts.suppliers.import', 'permission' => 'suppliers_import_suppliers', 'keywords' => ['csv import suppliers', 'bulk supplier upload']],

        // ── Advance Payments ────────────────────────────────────────────────
        ['label' => 'Add Advance', 'module' => 'Advance', 'route' => 'advance.add', 'permission' => 'customers_advance_payments_add', 'keywords' => ['advance payment', 'deposit money', 'pre-payment']],
        ['label' => 'Advance List', 'module' => 'Advance', 'route' => 'advance.list', 'permission' => 'customers_advance_payments_view', 'keywords' => ['all advances', 'customer deposits']],

        // ── Coupons ─────────────────────────────────────────────────────────
        ['label' => 'Create Customer Coupon', 'module' => 'Coupons', 'route' => 'coupons.customer.create', 'permission' => 'customer_coupon_add', 'keywords' => ['assign coupon', 'give voucher']],
        ['label' => 'Customer Coupons List', 'module' => 'Coupons', 'route' => 'coupons.customer.list', 'permission' => 'customer_coupon_view', 'keywords' => ['issued coupons', 'customer discounts']],
        ['label' => 'Create Coupon', 'module' => 'Coupons', 'route' => 'coupons.create', 'permission' => 'discount_coupon_add', 'keywords' => ['new coupon', 'add promo code']],
        ['label' => 'Coupons Master', 'module' => 'Coupons', 'route' => 'coupons.master', 'permission' => 'discount_coupon_view', 'keywords' => ['all coupons', 'promo master', 'discounts']],

        // ── Quotations ──────────────────────────────────────────────────────
        ['label' => 'New Quotation', 'module' => 'Quotation', 'route' => 'quotation.new', 'permission' => 'quotation_add', 'keywords' => ['add quote', 'create estimate']],
        ['label' => 'Quotation List', 'module' => 'Quotation', 'route' => 'quotation.list', 'permission' => 'quotation_view', 'keywords' => ['all quotations', 'estimates', 'proforma']],

        // ── Purchases ───────────────────────────────────────────────────────
        ['label' => 'New Purchase', 'module' => 'Purchase', 'route' => 'purchase.new', 'permission' => 'purchase_add', 'keywords' => ['add purchase', 'buy stock', 'supplier bill']],
        ['label' => 'Purchase List', 'module' => 'Purchase', 'route' => 'purchase.list', 'permission' => 'purchase_view', 'keywords' => ['purchase orders', 'all purchases']],
        ['label' => 'Purchase Returns List', 'module' => 'Purchase', 'route' => 'purchase.returns', 'permission' => 'purchase_return_view', 'keywords' => ['vendor return', 'debit note', 'purchase refund']],

        // ── Accounts ────────────────────────────────────────────────────────
        ['label' => 'Add Account', 'module' => 'Accounts', 'route' => 'accounts.add', 'permission' => 'accounts_add', 'keywords' => ['new bank account', 'cash account', 'wallet']],
        ['label' => 'Accounts List', 'module' => 'Accounts', 'route' => 'accounts.list', 'permission' => 'accounts_view', 'keywords' => ['bank accounts', 'ledger accounts', 'balances']],
        ['label' => 'Money Transfer List', 'module' => 'Accounts', 'route' => 'accounts.transfer', 'permission' => 'money_transfer_view', 'keywords' => ['bank transfer', 'fund transfer']],
        ['label' => 'Deposit List', 'module' => 'Accounts', 'route' => 'accounts.deposit', 'permission' => 'money_deposit_view', 'keywords' => ['add funds', 'cash deposit']],
        ['label' => 'Cash Transactions', 'module' => 'Accounts', 'route' => 'accounts.transactions', 'permission' => 'cash_transactions', 'keywords' => ['cashbook', 'transaction history', 'ledger']],

        // ── Items & Services ────────────────────────────────────────────────
        ['label' => 'Add Item', 'module' => 'Items', 'route' => 'items.add', 'permission' => 'items_add', 'keywords' => ['new product', 'create item', 'inventory add']],
        ['label' => 'Add Service', 'module' => 'Items', 'route' => 'items.service.add', 'permission' => 'services_add', 'keywords' => ['new service', 'create service']],
        ['label' => 'Services List', 'module' => 'Items', 'route' => 'items.service.list', 'permission' => 'services_view', 'keywords' => ['all services', 'service items']],
        ['label' => 'Items List', 'module' => 'Items', 'route' => 'items.list', 'permission' => 'items_view', 'keywords' => ['products list', 'inventory', 'stock list', 'catalog']],
        ['label' => 'Categories List', 'module' => 'Items', 'route' => 'items.categories', 'permission' => 'items_category_view', 'keywords' => ['item categories', 'product groups']],
        ['label' => 'Brands List', 'module' => 'Items', 'route' => 'items.brands', 'permission' => 'brand_view', 'keywords' => ['manufacturers', 'product brands']],
        ['label' => 'Variants List', 'module' => 'Items', 'route' => 'items.variants', 'permission' => 'variant_view', 'keywords' => ['product variations', 'sizes', 'colors', 'attributes']],
        ['label' => 'Print Labels', 'module' => 'Items', 'route' => 'items.labels', 'permission' => 'items_print_labels', 'keywords' => ['barcode printing', 'item stickers', 'price tags']],
        ['label' => 'Import Items', 'module' => 'Items', 'route' => 'items.import', 'permission' => 'items_import_items', 'keywords' => ['csv upload products', 'bulk item import']],

        // ── Stock Management ────────────────────────────────────────────────
        ['label' => 'Adjustment List', 'module' => 'Stock', 'route' => 'stock.adjustment', 'permission' => 'stock_adjustment_view', 'keywords' => ['stock adjustments', 'inventory count', 'waste']],
        ['label' => 'Create Stock Adjustment', 'module' => 'Stock', 'route' => 'stock.adjustment.create', 'permission' => 'stock_adjustment_add', 'keywords' => ['new stock adjustment', 'adjust inventory']],
        ['label' => 'Transfer List', 'module' => 'Stock', 'route' => 'stock.transfer', 'permission' => 'stock_transfer_view', 'keywords' => ['warehouse transfer', 'stock movement']],
        ['label' => 'Create Stock Transfer', 'module' => 'Stock', 'route' => 'stock.transfer.create', 'permission' => 'stock_transfer_add', 'keywords' => ['new stock transfer', 'send stock']],

        // ── Expenses ────────────────────────────────────────────────────────
        ['label' => 'Expenses List', 'module' => 'Expenses', 'route' => 'expenses.list', 'permission' => 'expense_view', 'keywords' => ['all expenses', 'bills', 'costs', 'petty cash']],
        ['label' => 'Add Expense', 'module' => 'Expenses', 'route' => 'expenses.add', 'permission' => 'expense_add', 'keywords' => ['new expense', 'record cost', 'spend']],
        ['label' => 'Expense Categories List', 'module' => 'Expenses', 'route' => 'expenses.categories', 'permission' => 'expense_category_view', 'keywords' => ['expense types', 'cost centers']],

        // ── Messaging (SMS/WhatsApp) ────────────────────────────────────────
        ['label' => 'SMS History', 'module' => 'Messaging', 'route' => 'sms.history', 'permission' => 'sms_whatsapp_message_api_view', 'keywords' => ['sent messages', 'outbox']],
        ['label' => 'Send SMS', 'module' => 'Messaging', 'route' => 'sms.send', 'permission' => 'sms_whatsapp_send_message', 'keywords' => ['compose sms', 'broadcast', 'text customers']],
        ['label' => 'SMS Templates', 'module' => 'Messaging', 'route' => 'sms.templates', 'permission' => 'sms_whatsapp_message_template_view', 'keywords' => ['message templates', 'sms formats']],
        ['label' => 'Campaigns', 'module' => 'Messaging', 'route' => 'sms.campaigns', 'permission' => 'sms_whatsapp_message_api_view', 'keywords' => ['sms marketing', 'bulk campaigns']],
        ['label' => 'SMS Logs', 'module' => 'Messaging', 'route' => 'sms.logs', 'permission' => 'sms_whatsapp_message_api_view', 'keywords' => ['delivery status', 'sms audit']],
        ['label' => 'Auto Rules', 'module' => 'Messaging', 'route' => 'sms.auto-rules', 'permission' => 'sms_whatsapp_message_settings', 'keywords' => ['automated sms', 'triggers', 'event sms']],
        ['label' => 'SMS Settings', 'module' => 'Messaging', 'route' => 'messaging.settings', 'permission' => 'sms_whatsapp_message_settings', 'keywords' => ['sms gateway', 'api keys', 'sms config']],

        // ── Reports ─────────────────────────────────────────────────────────
        ['label' => 'Sales Summary', 'module' => 'Reports', 'route' => 'reports.sales_summary', 'permission' => 'reports_view', 'keywords' => ['sales metrics', 'revenue summary', 'daily sales']],
        ['label' => 'Cash Flow Statement', 'module' => 'Reports', 'route' => 'reports.cash_flow', 'permission' => 'reports_view', 'keywords' => ['cash flow', 'cash statement', 'operating cash', 'financing cash', 'cash inflows', 'cash outflows', 'cash position']],
        ['label' => 'Profit & Loss Report', 'module' => 'Reports', 'route' => 'reports.profit_loss', 'permission' => 'reports_view', 'keywords' => ['pnl', 'net profit', 'gross profit', 'margins']],
        ['label' => 'Sales & Payment Report', 'module' => 'Reports', 'route' => 'reports.sales_payment', 'permission' => 'reports_view', 'keywords' => ['sales collections', 'paid vs due']],
        ['label' => 'Customer Orders', 'module' => 'Reports', 'route' => 'reports.customer_orders', 'permission' => 'reports_view', 'keywords' => ['client orders', 'order history']],
        ['label' => 'GSTR-1 Report', 'module' => 'Reports', 'route' => 'reports.gstr1', 'permission' => 'reports_view', 'keywords' => ['gst return 1', 'outward supplies']],
        ['label' => 'GSTR-2 Report', 'module' => 'Reports', 'route' => 'reports.gstr2', 'permission' => 'reports_view', 'keywords' => ['gst return 2', 'inward supplies']],
        ['label' => 'Sales GST Report', 'module' => 'Reports', 'route' => 'reports.sales_gst', 'permission' => 'reports_view', 'keywords' => ['gst on sales', 'tax breakdown']],
        ['label' => 'Purchase GST Report', 'module' => 'Reports', 'route' => 'reports.purchase_gst', 'permission' => 'reports_view', 'keywords' => ['gst on purchases', 'input tax credit']],
        ['label' => 'Sales Tax Report', 'module' => 'Reports', 'route' => 'reports.sales_tax', 'permission' => 'reports_view', 'keywords' => ['vat report', 'tax collection']],
        ['label' => 'Purchase Tax Report', 'module' => 'Reports', 'route' => 'reports.purchase_tax', 'permission' => 'reports_view', 'keywords' => ['input vat', 'tax paid']],
        ['label' => 'Supplier Items Report', 'module' => 'Reports', 'route' => 'reports.supplier_items', 'permission' => 'reports_view', 'keywords' => ['vendor items', 'items by supplier']],
        ['label' => 'Sales Report', 'module' => 'Reports', 'route' => 'reports.sales', 'permission' => 'reports_view', 'keywords' => ['detailed sales', 'invoice report']],
        ['label' => 'Sales Return Report', 'module' => 'Reports', 'route' => 'reports.sales_return', 'permission' => 'reports_view', 'keywords' => ['returns analysis', 'refunds report']],
        ['label' => 'Seller Points Report', 'module' => 'Reports', 'route' => 'reports.seller_points', 'permission' => 'reports_view', 'keywords' => ['sales commission', 'staff points']],
        ['label' => 'Purchase Report', 'module' => 'Reports', 'route' => 'reports.purchase', 'permission' => 'reports_view', 'keywords' => ['detailed purchases', 'procurement report']],
        ['label' => 'Purchase Return Report', 'module' => 'Reports', 'route' => 'reports.purchase_return', 'permission' => 'reports_view', 'keywords' => ['vendor returns report']],
        ['label' => 'Expense Report', 'module' => 'Reports', 'route' => 'reports.expense', 'permission' => 'reports_view', 'keywords' => ['expenses breakdown', 'spending report']],
        ['label' => 'Stock Report', 'module' => 'Reports', 'route' => 'reports.stock', 'permission' => 'reports_view', 'keywords' => ['inventory balance', 'warehouse valuation', 'current stock']],
        ['label' => 'Sales Item Report', 'module' => 'Reports', 'route' => 'reports.sales_item', 'permission' => 'reports_view', 'keywords' => ['item sales breakdown', 'top selling products']],
        ['label' => 'Return Items Report', 'module' => 'Reports', 'route' => 'reports.return_items', 'permission' => 'reports_view', 'keywords' => ['returned items breakdown']],
        ['label' => 'Purchase Payments Report', 'module' => 'Reports', 'route' => 'reports.purchase_payments', 'permission' => 'reports_view', 'keywords' => ['vendor payment history']],
        ['label' => 'Sales Payments Report', 'module' => 'Reports', 'route' => 'reports.sales_payments', 'permission' => 'reports_view', 'keywords' => ['customer payment history']],

        // ── Warehouse ───────────────────────────────────────────────────────
        ['label' => 'Add Warehouse', 'module' => 'Warehouse', 'route' => 'warehouse.add', 'permission' => 'warehouse_add', 'keywords' => ['new warehouse', 'create location', 'store branch']],
        ['label' => 'Warehouse List', 'module' => 'Warehouse', 'route' => 'warehouse.list', 'permission' => 'warehouse_view', 'keywords' => ['all warehouses', 'locations', 'depots']],

        // ── Settings ────────────────────────────────────────────────────────
        ['label' => 'Store Settings', 'module' => 'Settings', 'route' => 'settings.store', 'permission' => 'store_settings_view', 'keywords' => ['company settings', 'business details', 'store profile']],
        ['label' => 'Languages List', 'module' => 'Settings', 'route' => 'settings.languages.index', 'permission' => 'language_view', 'keywords' => ['language settings', 'locales', 'translations']],
        ['label' => 'Countries List', 'module' => 'Settings', 'route' => 'settings.countries', 'permission' => 'country_view', 'keywords' => ['country settings', 'locations']],
        ['label' => 'States List', 'module' => 'Settings', 'route' => 'settings.states', 'permission' => 'state_view', 'keywords' => ['state settings', 'provinces', 'districts']],
        ['label' => 'SMS/WhatsApp API Settings', 'module' => 'Settings', 'route' => 'sms.settings', 'permission' => 'sms_whatsapp_message_settings', 'keywords' => ['sms configuration', 'twilio', 'api keys']],
        ['label' => 'Tax List', 'module' => 'Settings', 'route' => 'settings.tax', 'permission' => 'tax_view', 'keywords' => ['tax rates', 'vat rates', 'gst rates', 'tax master']],
        ['label' => 'Units List', 'module' => 'Settings', 'route' => 'settings.units', 'permission' => 'unit_view', 'keywords' => ['measurement units', 'pcs', 'kg', 'box']],
        ['label' => 'Payment Types', 'module' => 'Settings', 'route' => 'settings.payment_types', 'permission' => 'payment_types_view', 'keywords' => ['payment methods', 'cash', 'card', 'bkash', 'nagad']],
        ['label' => 'Site Settings', 'module' => 'Settings', 'route' => 'settings.site_settings', 'permission' => 'site_settings_view', 'keywords' => ['system settings', 'branding', 'logo']],
        ['label' => 'SMTP Settings', 'module' => 'Settings', 'route' => 'settings.smtp', 'permission' => 'smtp_settings_view', 'keywords' => ['email settings', 'mail configuration', 'smtp host']],
        ['label' => 'Currency List', 'module' => 'Settings', 'route' => 'settings.currency', 'permission' => 'currency_view', 'keywords' => ['currency settings', 'exchange rates', 'bdt', 'usd']],
        ['label' => 'Change Password', 'module' => 'Settings', 'route' => 'settings.password', 'permission' => 'change_password', 'keywords' => ['update password', 'security', 'account password']],
        ['label' => 'Database Backup', 'module' => 'Settings', 'route' => 'settings.backup', 'permission' => 'database_backup', 'keywords' => ['backup database', 'sql dump', 'restore']],
    ];

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([
                'status'      => 'success',
                'query'       => $q,
                'total_count' => 0,
                'categories'  => [],
            ]);
        }

        $user = auth()->user();
        $like = "%{$q}%";
        $categories = [];
        $total = 0;

        // ── 0. Navigation Pages & Menu Actions ──────────────────────────────
        $matchedPages = collect(self::PAGES)
            ->filter(function ($page) use ($q, $user) {
                // Permission check
                if ($page['permission'] !== null && !$user->isSuperAdmin() && !$user->hasPermission($page['permission'])) {
                    return false;
                }

                // Substring search in label, module name, or keywords
                if (stripos($page['label'], $q) !== false || stripos($page['module'], $q) !== false) {
                    return true;
                }

                if (!empty($page['keywords'])) {
                    foreach ($page['keywords'] as $keyword) {
                        if (stripos($keyword, $q) !== false) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->sortBy(function ($page) use ($q) {
                // Exact label match = rank 0
                if (strcasecmp($page['label'], $q) === 0) {
                    return 0;
                }
                // Label starts with query = rank 1
                if (stripos($page['label'], $q) === 0) {
                    return 1;
                }
                // Label contains query = rank 2
                if (stripos($page['label'], $q) !== false) {
                    return 2;
                }
                // Module contains query = rank 3
                if (stripos($page['module'], $q) !== false) {
                    return 3;
                }
                // Keyword match = rank 4
                return 4;
            })
            ->take(self::LIMIT)
            ->map(function ($page) {
                return [
                    'id'       => 'page_' . md5($page['route'] . $page['label']),
                    'title'    => $page['label'],
                    'subtitle' => $page['module'] . ' · Page',
                    'badge'    => 'PAGE',
                    'url'      => route($page['route']),
                ];
            })
            ->values();

        if ($matchedPages->isNotEmpty()) {
            $categories['pages'] = [
                'label' => 'Pages & Actions',
                'icon'  => 'compass',
                'items' => $matchedPages,
            ];
            $total += $matchedPages->count();
        }

        // ── 1. Items (products only, not services) ─────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('items_view')) {
            $currencySymbol = AppServiceProvider::resolveCurrencySymbol();

            $results = DbItem::where('status', 1)
                ->where(function ($q2) {
                    $q2->where('service_bit', 0)->orWhereNull('service_bit');
                })
                ->where(function ($q2) use ($like) {
                    $q2->where('item_name', 'like', $like)
                        ->orWhere('item_code', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('custom_barcode', 'like', $like);
                })
                ->select('id', 'item_name', 'item_code', 'sku', 'sales_price')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($item) => [
                    'id'       => $item->id,
                    'title'    => $item->item_name,
                    'subtitle' => $item->item_code,
                    'meta'     => $currencySymbol . number_format((float) $item->sales_price, 2, '.', ','),
                    'badge'    => 'ITEM',
                    'url'      => route('items.show', $item->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['items'] = ['label' => 'Items', 'icon' => 'package', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 2. Services ────────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('services_view')) {
            // Re-use the already-resolved symbol if Items block ran, otherwise resolve now
            $currencySymbol = $currencySymbol ?? AppServiceProvider::resolveCurrencySymbol();

            $results = DbItem::where('status', 1)
                ->where('service_bit', 1)
                ->where(function ($q2) use ($like) {
                    $q2->where('item_name', 'like', $like)
                        ->orWhere('item_code', 'like', $like);
                })
                ->select('id', 'item_name', 'item_code', 'sales_price')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'title'    => $s->item_name,
                    'subtitle' => $s->item_code,
                    'meta'     => $currencySymbol . number_format((float) $s->sales_price, 2, '.', ','),
                    'badge'    => 'SERVICE',
                    'url'      => route('items.service.edit', $s->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['services'] = ['label' => 'Services', 'icon' => 'wrench', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 3. Customers ───────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('customers_view')) {
            $results = DbCustomer::where(function ($q2) use ($like) {
                    $q2->where('customer_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->select('id', 'customer_name', 'mobile', 'customer_code')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($c) => [
                    'id'       => $c->id,
                    'title'    => $c->customer_name,
                    'subtitle' => $c->mobile ?? $c->customer_code,
                    'badge'    => 'CUSTOMER',
                    'url'      => route('contacts.customers.edit', $c->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['customers'] = ['label' => 'Customers', 'icon' => 'user', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 4. Suppliers ───────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('suppliers_view')) {
            $results = DbSupplier::where(function ($q2) use ($like) {
                    $q2->where('supplier_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('supplier_code', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->select('id', 'supplier_name', 'mobile', 'supplier_code')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'title'    => $s->supplier_name,
                    'subtitle' => $s->mobile ?? $s->supplier_code,
                    'badge'    => 'SUPPLIER',
                    'url'      => route('contacts.suppliers.edit', $s->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['suppliers'] = ['label' => 'Suppliers', 'icon' => 'truck', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 5. Sales / Invoices ────────────────────────────────────────────
        // Legacy sales_include_pos_view accepted during the slug-reconciliation
        // window; the data migration maps any such grant to sales_view.
        if ($user->isSuperAdmin() || $user->hasPermission('sales_view') || $user->hasPermission('sales_include_pos_view')) {
            $results = DbSale::where(function ($q2) use ($like) {
                    $q2->where('sales_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                })
                ->select('id', 'sales_code', 'reference_no', 'sales_date', 'grand_total')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'title'    => $s->sales_code,
                    'subtitle' => $s->reference_no ? "Ref: {$s->reference_no}" : $s->sales_date,
                    'badge'    => 'SALE',
                    'url'      => route('sales.show', $s->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['sales'] = ['label' => 'Sales & Invoices', 'icon' => 'file-text', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 6. Purchases ───────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('purchase_view')) {
            $results = DbPurchase::where(function ($q2) use ($like) {
                    $q2->where('purchase_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                })
                ->select('id', 'purchase_code', 'reference_no', 'purchase_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($p) => [
                    'id'       => $p->id,
                    'title'    => $p->purchase_code,
                    'subtitle' => $p->reference_no ? "Ref: {$p->reference_no}" : $p->purchase_date,
                    'badge'    => 'PURCHASE',
                    'url'      => route('purchase.invoice', $p->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['purchases'] = ['label' => 'Purchases', 'icon' => 'shopping-cart', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 7. Quotations ──────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('quotation_view')) {
            $results = DbQuotation::where(function ($q2) use ($like) {
                    $q2->where('quotation_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                })
                ->select('id', 'quotation_code', 'reference_no', 'quotation_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($qt) => [
                    'id'       => $qt->id,
                    'title'    => $qt->quotation_code,
                    'subtitle' => $qt->reference_no ? "Ref: {$qt->reference_no}" : $qt->quotation_date,
                    'badge'    => 'QUOTE',
                    'url'      => route('quotation.invoice', $qt->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['quotations'] = ['label' => 'Quotations', 'icon' => 'clipboard-list', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 8. Sales Returns ───────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('sales_return_view')) {
            $results = DbSalesReturn::where(function ($q2) use ($like) {
                    $q2->where('return_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                })
                ->select('id', 'return_code', 'reference_no', 'return_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($r) => [
                    'id'       => $r->id,
                    'title'    => $r->return_code,
                    'subtitle' => $r->reference_no ? "Ref: {$r->reference_no}" : $r->return_date,
                    'badge'    => 'RETURN',
                    'url'      => route('sales.return.show', $r->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['sales_returns'] = ['label' => 'Sales Returns', 'icon' => 'rotate-ccw', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 9. Purchase Returns ────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('purchase_return_view')) {
            $results = DbPurchaseReturn::where(function ($q2) use ($like) {
                    $q2->where('return_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                })
                ->select('id', 'return_code', 'reference_no', 'return_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($r) => [
                    'id'       => $r->id,
                    'title'    => $r->return_code,
                    'subtitle' => $r->reference_no ? "Ref: {$r->reference_no}" : $r->return_date,
                    'badge'    => 'PR',
                    'url'      => route('purchase.returns', ['search' => $r->return_code]),
                ]);

            if ($results->isNotEmpty()) {
                $categories['purchase_returns'] = ['label' => 'Purchase Returns', 'icon' => 'undo-2', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 10. Stock Transfers ────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('stock_transfer_view')) {
            $results = DbStockTransfer::where('store_id', $user->store_id)
                ->where(function ($q2) use ($like) {
                    $q2->where('reference_no', 'like', $like)
                        ->orWhere('note', 'like', $like);
                })
                ->select('id', 'reference_no', 'note', 'transfer_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($t) => [
                    'id'       => $t->id,
                    'title'    => $t->reference_no ?: "Transfer #{$t->id}",
                    'subtitle' => $t->note ?: $t->transfer_date,
                    'badge'    => 'TRANSFER',
                    'url'      => route('stock.transfer.edit', $t->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['stock_transfers'] = ['label' => 'Stock Transfers', 'icon' => 'arrow-left-right', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 11. Stock Adjustments ──────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('stock_adjustment_view')) {
            $results = DbStockAdjustment::where('store_id', $user->store_id)
                ->where(function ($q2) use ($like) {
                    $q2->where('reference_no', 'like', $like)
                        ->orWhere('adjustment_note', 'like', $like);
                })
                ->select('id', 'reference_no', 'adjustment_note', 'adjustment_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($a) => [
                    'id'       => $a->id,
                    'title'    => $a->reference_no ?: "Adjustment #{$a->id}",
                    'subtitle' => $a->adjustment_note ?: $a->adjustment_date,
                    'badge'    => 'ADJUST',
                    'url'      => route('stock.adjustment.edit', $a->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['stock_adjustments'] = ['label' => 'Stock Adjustments', 'icon' => 'sliders-horizontal', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 12. Expenses ───────────────────────────────────────────────────
        if ($user->isSuperAdmin() || $user->hasPermission('expense_view')) {
            $results = DbExpense::where('delete_bit', 0)
                ->where(function ($q2) use ($like) {
                    $q2->where('expense_code', 'like', $like)
                        ->orWhere('reference_no', 'like', $like)
                        ->orWhere('expense_for', 'like', $like);
                })
                ->select('id', 'expense_code', 'reference_no', 'expense_for', 'expense_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($e) => [
                    'id'       => $e->id,
                    'title'    => $e->expense_for,
                    'subtitle' => $e->expense_code ?? $e->reference_no,
                    'badge'    => 'EXPENSE',
                    'url'      => route('expenses.list', ['search' => $e->expense_code ?? $e->expense_for]),
                ]);

            if ($results->isNotEmpty()) {
                $categories['expenses'] = ['label' => 'Expenses', 'icon' => 'receipt', 'items' => $results];
                $total += $results->count();
            }
        }

        // ── 13. Users (Super Admin or explicit users_view only) ────────────
        if ($user->isSuperAdmin() || $user->hasPermission('users_view')) {
            // Store-scoped for everyone EXCEPT a confirmed super-admin, who may
            // legitimately search users across the whole network (mirroring the
            // multi-store dashboard). User has no StoreScoped trait, so the scoping
            // is explicit — same pattern as the transfer/adjustment blocks above.
            $results = User::where('status', 1)
                ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('store_id', $user->store_id))
                ->where(function ($q2) use ($like) {
                    $q2->where('username', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->select('id', 'username', 'first_name', 'last_name', 'email', 'role_name')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($u) => [
                    'id'       => $u->id,
                    'title'    => trim("{$u->first_name} {$u->last_name}") ?: $u->username,
                    'subtitle' => "@{$u->username} · {$u->role_name}",
                    'badge'    => 'USER',
                    'url'      => route('users.edit', $u->id),
                ]);

            if ($results->isNotEmpty()) {
                $categories['users'] = ['label' => 'Users', 'icon' => 'shield-check', 'items' => $results];
                $total += $results->count();
            }
        }

        return response()->json([
            'status'      => 'success',
            'query'       => $q,
            'total_count' => $total,
            'categories'  => $categories,
        ]);
    }
}
