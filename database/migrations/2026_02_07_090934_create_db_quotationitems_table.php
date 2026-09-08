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
        Schema::create('db_quotationitems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->string('quotation_status')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quotation_qty', 16, 2)->default(0);
            $table->decimal('price_per_unit', 16, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_amt', 16, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_input', 16, 2)->default(0);
            $table->decimal('discount_amt', 16, 2)->default(0);
            $table->decimal('unit_total_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->integer('status')->default(1);
            $table->integer('seller_points')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('quotation_id');
            $table->index('item_id');

            // Foreign Keys
            $table->foreign('quotation_id')->references('id')->on('db_quotation')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('item_id')->references('id')->on('db_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_quotationitems');
    }
};
