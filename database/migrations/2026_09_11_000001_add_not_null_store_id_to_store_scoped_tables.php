<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.4 — Enforce NOT NULL on store_id across every store-scoped table.
 *
 * Table list derived from INFORMATION_SCHEMA (authoritative, not guessed):
 * 58 tables have store_id; cash_drawer_reconciliations is already NOT NULL
 * (default 1); the other 57 are covered here.
 *
 * DATA SAFETY: verified live that the database is currently EMPTY (0 rows in
 * every table), so there is zero data-corruption risk. up() still re-checks
 * for NULL rows at run time and aborts (no silent backfill) if any appear.
 */
return new class extends Migration
{
    private array $tables = [
        'ac_accounts',
        'ac_moneydeposits',
        'ac_moneytransfer',
        'ac_transactions',
        'db_bankdetails',
        'db_brands',
        'db_category',
        'db_coupons',
        'db_custadvance',
        'db_customers',
        'db_customer_coupons',
        'db_emailtemplates',
        'db_expense',
        'db_expense_category',
        'db_fivemojo',
        'db_hold',
        'db_holditems',
        'db_instamojo',
        'db_items',
        'db_item_serials',
        'db_package',
        'db_paymenttypes',
        'db_paypal',
        'db_permissions',
        'db_purchase',
        'db_purchaseitems',
        'db_purchaseitemsreturn',
        'db_purchasepayments',
        'db_purchasepaymentsreturn',
        'db_purchasereturn',
        'db_quotation',
        'db_quotationitems',
        'db_roles',
        'db_sales',
        'db_salesitems',
        'db_salesitemsreturn',
        'db_salespayments',
        'db_salespaymentsreturn',
        'db_salesreturn',
        'db_shippingaddress',
        'db_smsapi',
        'db_smstemplates',
        'db_states',
        'db_stockadjustment',
        'db_stockadjustmentitems',
        'db_stocktransfer',
        'db_stocktransferitems',
        'db_stripe',
        'db_subscription',
        'db_suppliers',
        'db_tax',
        'db_twilio',
        'db_units',
        'db_variants',
        'db_warehouse',
        'db_warehouseitems',
        'users',
    ];

    public function up(): void
    {
        // ── Data-safety pre-flight: abort on ANY NULL store_id ──────────────
        $offenders = [];
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            $nulls = (int) DB::table($table)->whereNull('store_id')->count();
            if ($nulls > 0) {
                $offenders[$table] = $nulls;
            }
        }

        if (!empty($offenders)) {
            $detail = collect($offenders)
                ->map(fn($n, $t) => "{$t}: {$n} NULL row(s)")
                ->implode(', ');
            throw new RuntimeException(
                'Phase 1.4 aborted: tables still contain NULL store_id rows — ' . $detail .
                '. Backfill explicitly (not silently to store_id=1) before re-running.'
            );
        }

        // ── Apply NOT NULL to every NULLABLE store_id column ────────────────
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable()->change();
            });
        }
    }
};
