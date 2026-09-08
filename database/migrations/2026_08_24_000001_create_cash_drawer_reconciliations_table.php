<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_drawer_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_code')->unique(); // e.g. REC-00001
            $table->unsignedBigInteger('store_id')->default(1);
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->date('reconciliation_date');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();

            // Financial Breakdown Components
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('cash_sales_amount', 16, 2)->default(0);
            $table->decimal('cash_refunds_amount', 16, 2)->default(0);
            $table->decimal('cash_expenses_amount', 16, 2)->default(0);
            $table->decimal('cash_deposits_amount', 16, 2)->default(0);
            $table->decimal('cash_transfers_in', 16, 2)->default(0);
            $table->decimal('cash_transfers_out', 16, 2)->default(0);

            // Summary Totals
            $table->decimal('expected_closing_balance', 16, 2)->default(0);
            $table->decimal('counted_amount', 16, 2)->default(0);
            $table->decimal('variance', 16, 2)->default(0);

            // Optional Denomination Breakdown
            $table->json('denominations')->nullable();

            // Status & Ledger Adjustment
            $table->string('status')->default('Reconciled'); // 'Reconciled', 'Adjusted'
            $table->unsignedBigInteger('adjustment_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Custom short index names
            $table->index(['warehouse_id', 'reconciliation_date'], 'idx_cdr_wh_date');
            $table->index(['account_id', 'reconciliation_date'], 'idx_cdr_acc_date');
            $table->foreign('account_id')->references('id')->on('ac_accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('db_warehouse')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_reconciliations');
    }
};
