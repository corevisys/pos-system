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
        Schema::create('db_holditems', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('hold_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('sales_qty', 16, 2)->default(0);
            $table->decimal('price_per_unit', 16, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->integer('tax_id')->nullable();
            $table->decimal('tax_amt', 16, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_input', 16, 2)->default(0);
            $table->decimal('discount_amt', 16, 2)->default(0);
            $table->decimal('unit_total_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_holditems');
    }
};
