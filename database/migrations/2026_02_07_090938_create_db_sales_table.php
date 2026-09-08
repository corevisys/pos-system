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
        Schema::create('db_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('init_code')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('sales_code')->nullable();
            $table->string('reference_no')->nullable();
            $table->date('sales_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('sales_status')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('other_charges_input', 16, 2)->default(0);
            $table->integer('other_charges_tax_id')->nullable();
            $table->decimal('other_charges_amt', 16, 2)->default(0);
            $table->decimal('discount_to_all_input', 16, 2)->default(0);
            $table->string('discount_to_all_type')->nullable();
            $table->decimal('tot_discount_to_all_amt', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('round_off', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);
            $table->text('sales_note')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('pos')->default(0);
            $table->integer('status')->default(1);
            $table->integer('return_bit')->default(0);
            $table->decimal('customer_previous_due', 16, 2)->default(0);
            $table->decimal('customer_total_due', 16, 2)->default(0);
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('coupon_amt', 16, 2)->default(0);
            $table->text('invoice_terms')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('warehouse_id');
            $table->index('customer_id');
            $table->index('sales_code');
            $table->index('reference_no');
            $table->index('sales_date');
            $table->index('sales_status');
            $table->index('payment_status');

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('db_customers')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('quotation_id')->references('id')->on('db_quotation')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('coupon_id')->references('id')->on('db_coupons')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_sales');
    }
};
