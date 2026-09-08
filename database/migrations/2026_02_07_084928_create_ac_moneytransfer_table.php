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
        Schema::create('ac_moneytransfer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('transfer_code')->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('debit_account_id')->nullable();
            $table->unsignedBigInteger('credit_account_id')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('transfer_date');
            $table->index('debit_account_id');
            $table->index('credit_account_id');

            // Foreign Keys
            $table->foreign('debit_account_id')->references('id')->on('ac_accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('credit_account_id')->references('id')->on('ac_accounts')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ac_moneytransfer');
    }
};
