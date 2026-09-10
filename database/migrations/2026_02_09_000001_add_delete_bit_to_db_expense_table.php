<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 (Expenses rollout): introduce soft-delete + ledger versioning on db_expense.
 *
 * 1. delete_bit — db_expense previously had NO delete_bit column and
 *    ExpenseController@destroy performed an unconditional hard delete, destroying
 *    the audit trail (and, before the Phase 1 fix, without reversing the
 *    ledger/balance effect). Adding delete_bit lets destroy() transition the row
 *    atomically and retain both the original row and its EXPENSE REVERSAL ledger
 *    entry, mirroring the established Deposit module
 *    (ac_moneydeposits.delete_bit + 'DEPOSIT REVERSAL') convention.
 *
 * 2. ledger_version — optimistic-lock counter used as an atomic claim by
 *    update() so a genuine concurrent double-submit cannot double-apply the
 *    ledger/balance effect. Mirrors the atomic conditional transition pattern
 *    already used by DepositController@destroy ($affected !== 1 guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_expense', function (Blueprint $table) {
            if (!Schema::hasColumn('db_expense', 'delete_bit')) {
                $table->integer('delete_bit')->default(0)->after('status');
                $table->index('delete_bit');
            }
            if (!Schema::hasColumn('db_expense', 'ledger_version')) {
                $table->unsignedInteger('ledger_version')->default(0)->after('delete_bit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('db_expense', function (Blueprint $table) {
            if (Schema::hasColumn('db_expense', 'ledger_version')) {
                $table->dropColumn('ledger_version');
            }
            if (Schema::hasColumn('db_expense', 'delete_bit')) {
                $table->dropIndex(['delete_bit']);
                $table->dropColumn('delete_bit');
            }
        });
    }
};
