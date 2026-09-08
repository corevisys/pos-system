<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an index on ac_transactions.supplier_id.
     *
     * The Suppliers List "Account Payable Only" filter (SupplierController::index)
     * computes a supplier's live purchase due with correlated subqueries that match
     * ac_transactions by (supplier_id, store_id, transaction_type). The table only
     * indexed store_id before, so every subquery would scan all rows of the matching
     * store. This composite index lets the bounded, SQL-side filter (added in the
     * same rollout) resolve each supplier's payable/payment sums via an index seek
     * instead of a full scan as the ledger grows.
     */
    public function up(): void
    {
        Schema::table('ac_transactions', function (Blueprint $table) {
            $table->index(['supplier_id', 'store_id', 'transaction_type'], 'idx_ac_transactions_supplier_store_type');
        });
    }

    public function down(): void
    {
        Schema::table('ac_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_ac_transactions_supplier_store_type');
        });
    }
};
