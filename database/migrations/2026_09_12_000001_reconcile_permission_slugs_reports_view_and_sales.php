<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time permission-slug reconciliation data fix (production-safe).
 *
 * WHY: the Roles UI auto-slugged most modules as Str::slug(module + ' ' + perm),
 * which produced slugs the seeders/controllers never used. Two concrete effects:
 *
 *  1. The Sales module produced `sales_include_pos_view` / `sales_include_pos_add`
 *     instead of the seeded `sales_view` / `sales_add`.
 *  2. The Reports module produced per-report slugs, while the sidebar gates the
 *     whole menu on the single `reports_view` — a slug NO seeder ever granted.
 *     Net effect: the Reports menu was invisible to every non-super-admin role.
 *
 * This migration PRESERVES current effective access:
 *   - any role whose JSON contains a report-related slug also receives `reports_view`;
 *   - any stray `sales_include_pos_*` slug is mapped to its canonical seeder slug.
 *
 * It is idempotent and uses only the permissions column (no schema changes).
 */
return new class extends Migration
{
    /**
     * Report-related slugs that imply the role is meant to see reports.
     */
    private array $reportRelatedSlugs = [
        'sales_report', 'profit_report', 'purchase_report', 'stock_report',
        'item_sales_report', 'sales_tax_report', 'purchase_tax_report',
        'expense_report', 'gstr_1_report', 'gstr_2_report', 'sales_gst_report',
        'purchase_gst_report', 'customer_orders_report', 'seller_points_report',
        'supplier_items_report', 'sales_return_report', 'purchase_return_report',
        'return_items_report', 'sales_payments_report', 'purchase_payments_report',
        'cash_reconciliation_report', 'delivery_sheet_report', 'load_sheet_report',
        'reports_cash_flow_view',
    ];

    /**
     * UI-auto-slugged Sales values => canonical seeder vocabulary.
     */
    private array $salesSlugMap = [
        'sales_include_pos_view' => 'sales_view',
        'sales_include_pos_add' => 'sales_add',
        'sales_include_pos_edit' => 'sales_edit',
        'sales_include_pos_delete' => 'sales_delete',
        'sales_include_pos_sales_payments_view' => 'sales_payment_view',
        'sales_include_pos_sales_payments_add' => 'sales_payment_add',
        'sales_include_pos_sales_payments_delete' => 'sales_payment_delete',
        'sales_include_pos_show_all_users_sales_invoices' => 'show_all_users_sales_invoices',
        'sales_include_pos_show_item_purchase_price_while_making_invoice' => 'show_purchase_price',
        'pos' => 'sales_add',
        'pos_view' => 'sales_view',
    ];

    public function up(): void
    {
        $rows = DB::table('db_permissions')->get(['id', 'role_id', 'permissions']);

        foreach ($rows as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            $original = $list;

            // 1. Map any stray UI-autoslugged Sales values to the seeder vocabulary.
            foreach ($list as $idx => $slug) {
                if (isset($this->salesSlugMap[$slug])) {
                    $list[$idx] = $this->salesSlugMap[$slug];
                }
            }

            // 2. Preserve effective report access under the new single gate.
            if (array_intersect($this->reportRelatedSlugs, $list)) {
                $list[] = 'reports_view';
            }

            $list = array_values(array_unique($list));

            if ($list !== $original) {
                DB::table('db_permissions')
                    ->where('id', $row->id)
                    ->update(['permissions' => json_encode($list)]);
            }
        }
    }

    public function down(): void
    {
        // Remove the reports_view gate this migration introduced (only from rows
        // that also carry a report-related slug, mirroring up()).
        $rows = DB::table('db_permissions')->get(['id', 'permissions']);

        foreach ($rows as $row) {
            $list = json_decode((string) $row->permissions, true);
            if (!is_array($list)) {
                continue;
            }

            if (array_intersect($this->reportRelatedSlugs, $list)) {
                $list = array_values(array_diff($list, ['reports_view']));
                DB::table('db_permissions')
                    ->where('id', $row->id)
                    ->update(['permissions' => json_encode($list)]);
            }
        }
    }
};
