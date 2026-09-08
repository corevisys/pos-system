<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C + Phase D foundation for the Accounts cluster.
 *
 * Adds a targeted system-account discriminator to ac_accounts:
 *   - system_key  : nullable string marking well-known system accounts
 *                   ('external_deposit_clearing' | 'opening_balance_equity').
 *   - is_system   : boolean flag (1 = generated contra/equity account).
 *
 * A unique index on (store_id, system_key) provides DB-level protection against
 * the concurrent find-or-create race for these accounts (Phase C) WITHOUT
 * imposing a blanket unique constraint on account_name (which would be unsafe,
 * since legitimate user accounts in different parent hierarchies may share names).
 *
 * Phase D reuses the is_system flag to exclude these accounts from user-facing
 * dropdowns while the system continues to reference them internally by system_key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ac_accounts', function (Blueprint $table) {
            $table->string('system_key')->nullable()->after('account_code');
            $table->boolean('is_system')->default(false)->after('system_key');
        });

        // Backfill existing system-generated accounts (from earlier deployments).
        DB::table('ac_accounts')
            ->where('delete_bit', 0)
            ->where('account_name', 'External Deposit Clearing')
            ->whereNull('system_key')
            ->update([
                'system_key' => 'external_deposit_clearing',
                'is_system' => 1,
            ]);

        DB::table('ac_accounts')
            ->where('delete_bit', 0)
            ->where('account_name', 'Opening Balance Equity')
            ->whereNull('system_key')
            ->update([
                'system_key' => 'opening_balance_equity',
                'is_system' => 1,
            ]);

        // Guard: any duplicate legacy rows per (store, system_key) would block the
        // unique index. Detect and fail loudly rather than silently corrupting.
        $dupes = DB::select("
            SELECT store_id, system_key, COUNT(*) AS cnt
            FROM ac_accounts
            WHERE system_key IS NOT NULL
            GROUP BY store_id, system_key
            HAVING COUNT(*) > 1
        ");

        if (!empty($dupes)) {
            throw new \RuntimeException(
                'Pre-existing duplicate system accounts detected per store. ' .
                'Manually reconcile before adding the unique index: ' . json_encode($dupes)
            );
        }

        Schema::table('ac_accounts', function (Blueprint $table) {
            $table->unique(['store_id', 'system_key'], 'ac_accounts_store_system_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ac_accounts', function (Blueprint $table) {
            $table->dropUnique('ac_accounts_store_system_key_unique');
        });

        Schema::table('ac_accounts', function (Blueprint $table) {
            $table->dropColumn(['system_key', 'is_system']);
        });
    }
};
