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
        Schema::create('db_purchaseitems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->string('purchase_status')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('purchase_qty', 16, 2)->default(0);
            $table->decimal('price_per_unit', 16, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_amt', 16, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_input', 16, 2)->default(0);
            $table->decimal('discount_amt', 16, 2)->default(0);
            $table->decimal('unit_total_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->decimal('profit_margin_per', 16, 2)->default(0);
            $table->decimal('unit_sales_price', 16, 2)->default(0);
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('purchase_id');
            $table->index('item_id');

            // Foreign Keys
            $table->foreign('purchase_id')->references('id')->on('db_purchase')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('item_id')->references('id')->on('db_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_purchaseitems');
    }
};
