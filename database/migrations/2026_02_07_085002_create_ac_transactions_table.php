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
        Schema::create('ac_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('payment_code')->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('transaction_type')->nullable();
            $table->unsignedBigInteger('debit_account_id')->nullable();
            $table->unsignedBigInteger('credit_account_id')->nullable();
            $table->decimal('debit_amt', 16, 2)->default(0);
            $table->decimal('credit_amt', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->unsignedBigInteger('ref_accounts_id')->nullable();
            $table->unsignedBigInteger('ref_moneytransfer_id')->nullable();
            $table->unsignedBigInteger('ref_moneydeposits_id')->nullable();
            $table->unsignedBigInteger('ref_salespayments_id')->nullable();
            $table->unsignedBigInteger('ref_salespaymentsreturn_id')->nullable();
            $table->unsignedBigInteger('ref_purchasepayments_id')->nullable();
            $table->unsignedBigInteger('ref_purchasepaymentsreturn_id')->nullable();
            $table->unsignedBigInteger('ref_expense_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('short_code')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('debit_account_id');
            $table->index('credit_account_id');
            $table->index('transaction_date');
            
            // Foreign Keys
            $table->foreign('debit_account_id')->references('id')->on('ac_accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('credit_account_id')->references('id')->on('ac_accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('ref_moneytransfer_id')->references('id')->on('ac_moneytransfer')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('ref_moneydeposits_id')->references('id')->on('ac_moneydeposits')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ac_transactions');
    }
};
