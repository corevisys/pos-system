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
        Schema::create('db_hold', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('warehouse_id')->nullable();
            $table->integer('reference_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->date('sales_date')->nullable();
            $table->string('sales_status')->nullable();
            $table->integer('customer_id')->nullable();
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
            $table->integer('pos')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_hold');
    }
};
