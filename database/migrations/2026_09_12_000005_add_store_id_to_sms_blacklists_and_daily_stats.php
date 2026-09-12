<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the last two SMS tables per-store (business decision now confirmed:
 * blacklist, daily stats and duplicate suppression are all per-store).
 *
 *   - sms_blacklists  : UNIQUE(phone)  -> UNIQUE(store_id, phone)
 *   - sms_daily_stats : UNIQUE(date)   -> UNIQUE(store_id, date)
 *
 * ROW COUNTS VERIFIED LIVE IMMEDIATELY BEFORE WRITING THIS: both tables = 0 rows,
 * so the new NOT NULL store_id needs no backfill and no data can be lost. If that
 * ever changes, this migration already handles it: it adds the column NULLABLE,
 * backfills any existing rows to the first store, then tightens to NOT NULL.
 *
 * The old GLOBAL uniques are dropped first — they would otherwise reject a second
 * store blacklisting the same phone / recording stats for the same date.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        /* ───────────── sms_blacklists ───────────── */
        if (!Schema::hasColumn('sms_blacklists', 'store_id')) {
            Schema::table('sms_blacklists', function (Blueprint $t) {
                $t->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }

        $firstStoreId = (int) (DB::table('db_store')->orderBy('id')->value('id') ?? 1);
        DB::table('sms_blacklists')->whereNull('store_id')->update(['store_id' => $firstStoreId]);

        Schema::table('sms_blacklists', function (Blueprint $t) {
            try { $t->dropUnique('sms_blacklists_phone_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('sms_blacklists', function (Blueprint $t) {
            try {
                $t->unique(['store_id', 'phone'], 'sms_blacklists_store_phone_unique');
            } catch (\Throwable $e) {
                // Already present (re-run) — fine.
            }
            try {
                $t->index('store_id', 'sms_blacklists_store_id_index');
            } catch (\Throwable $e) {}
        });

        Schema::table('sms_blacklists', function (Blueprint $t) {
            $t->unsignedBigInteger('store_id')->nullable(false)->change();
        });

        /* ───────────── sms_daily_stats ───────────── */
        if (!Schema::hasColumn('sms_daily_stats', 'store_id')) {
            Schema::table('sms_daily_stats', function (Blueprint $t) {
                $t->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }

        DB::table('sms_daily_stats')->whereNull('store_id')->update(['store_id' => $firstStoreId]);

        Schema::table('sms_daily_stats', function (Blueprint $t) {
            try { $t->dropUnique('sms_daily_stats_date_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('sms_daily_stats', function (Blueprint $t) {
            try {
                $t->unique(['store_id', 'date'], 'sms_daily_stats_store_date_unique');
            } catch (\Throwable $e) {}
            try {
                $t->index('store_id', 'sms_daily_stats_store_id_index');
            } catch (\Throwable $e) {}
        });

        Schema::table('sms_daily_stats', function (Blueprint $t) {
            $t->unsignedBigInteger('store_id')->nullable(false)->change();
        });

        /* ───────────── Foreign keys (skipped on SQLite) ───────────── */
        if ($driver !== 'sqlite') {
            Schema::table('sms_blacklists', function (Blueprint $t) {
                try {
                    $t->foreign('store_id', 'sms_blacklists_store_id_foreign')
                        ->references('id')->on('db_store')->onDelete('cascade');
                } catch (\Throwable $e) {}
            });
            Schema::table('sms_daily_stats', function (Blueprint $t) {
                try {
                    $t->foreign('store_id', 'sms_daily_stats_store_id_foreign')
                        ->references('id')->on('db_store')->onDelete('cascade');
                } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        foreach (['sms_blacklists', 'sms_daily_stats'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            if ($driver !== 'sqlite') {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    try { $t->dropForeign($table . '_store_id_foreign'); } catch (\Throwable $e) {}
                });
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                try { $t->dropUnique($table === 'sms_blacklists'
                    ? 'sms_blacklists_store_phone_unique'
                    : 'sms_daily_stats_store_date_unique'); } catch (\Throwable $e) {}
                try { $t->dropIndex($table . '_store_id_index'); } catch (\Throwable $e) {}
                $t->dropColumn('store_id');
            });
        }

        // Restore the original global uniques.
        Schema::table('sms_blacklists', function (Blueprint $t) {
            try { $t->unique('phone', 'sms_blacklists_phone_unique'); } catch (\Throwable $e) {}
        });
        Schema::table('sms_daily_stats', function (Blueprint $t) {
            try { $t->unique('date', 'sms_daily_stats_date_unique'); } catch (\Throwable $e) {}
        });
    }
};
