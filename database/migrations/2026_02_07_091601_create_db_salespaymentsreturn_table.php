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
        Schema::create('db_salespaymentsreturn', function (Blueprint $table) {
            $table->id();
            $table->integer('count_id')->nullable();
            $table->string('payment_code')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('sales_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('payment', 16, 2)->default(0);
            $table->text('payment_note')->nullable();
            $table->decimal('change_return', 16, 2)->default(0);
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->time('created_time')->nullable();
            $table->date('created_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('short_code')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('sales_id');
            $table->index('return_id');
            $table->index('account_id');
            $table->index('customer_id');

            // Foreign Keys
            $table->foreign('sales_id')->references('id')->on('db_sales')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('customer_id')->references('id')->on('db_customers')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('account_id')->references('id')->on('ac_accounts')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_salespaymentsreturn');
    }
};
