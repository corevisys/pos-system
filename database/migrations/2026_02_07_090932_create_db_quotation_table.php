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
        Schema::create('db_quotation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('quotation_code')->nullable();
            $table->string('reference_no')->nullable();
            $table->date('quotation_date')->nullable();
            $table->date('expire_date')->nullable();
            $table->string('quotation_status')->nullable();
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
            $table->text('quotation_note')->nullable();
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
            $table->string('sales_status')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('warehouse_id');
            $table->index('customer_id');
            $table->index('quotation_code');
            $table->index('reference_no');

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('db_customers')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_quotation');
    }
};
