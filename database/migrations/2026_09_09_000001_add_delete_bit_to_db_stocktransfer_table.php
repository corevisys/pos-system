<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the app-wide delete_bit soft-delete flag to db_stocktransfer.
     *
     * Every other transactional module in this project (ac_accounts,
     * ac_moneytransfer, ac_moneydeposits, cash_drawer_reconciliations,
     * db_suppliers, db_customers) uses the delete_bit flag as the single
     * soft-delete guard, combined with an ATOMIC conditional transition
     * (where('delete_bit', 0)->update(['delete_bit' => 1])) to make a
     * concurrent double-delete race lose deterministically (affected = 0).
     *
     * The prior rollout substituted a lockForUpdate()-only guard, which is a
     * no-op under SQLite (the actual test engine) and provided no
     * engine-independent compare-and-set. This migration restores spec
     * compliance: delete_bit defaults to 0 (active) and is indexed so the
     * transition predicate stays cheap.
     */
    public function up(): void
    {
        Schema::table('db_stocktransfer', function (Blueprint $table) {
            if (!Schema::hasColumn('db_stocktransfer', 'delete_bit')) {
                $table->integer('delete_bit')->default(0)->after('status');
                $table->index('delete_bit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('db_stocktransfer', function (Blueprint $table) {
            if (Schema::hasColumn('db_stocktransfer', 'delete_bit')) {
                $table->dropIndex(['delete_bit']);
                $table->dropColumn('delete_bit');
            }
        });
    }
};
