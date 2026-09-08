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
        Schema::create('db_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('hsn')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('alert_qty', 16, 2)->default(0);
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expire_date')->nullable();
            $table->decimal('price', 16, 2)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('purchase_price', 16, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->decimal('profit_margin', 16, 2)->default(0);
            $table->decimal('sales_price', 16, 2)->default(0);
            $table->decimal('stock', 16, 2)->default(0);
            $table->string('item_image')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('discount_type')->nullable();
            $table->decimal('discount', 16, 2)->default(0);
            $table->integer('service_bit')->default(0);
            $table->integer('seller_points')->default(0);
            $table->string('custom_barcode')->nullable();
            $table->text('description')->nullable();
            $table->string('item_group')->nullable();
            $table->integer('parent_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->integer('child_bit')->default(0);
            $table->decimal('mrp', 16, 2)->default(0);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('category_id');
            $table->index('brand_id');
            $table->index('unit_id');
            $table->index('tax_id');
            $table->index('item_code');
            $table->index('item_name');
            $table->index('sku');

            // Foreign Keys
            // Only adding FKs for tables that definitely exist before db_items (09:07:24)
            // db_brands (08:56:06) - Safe
            // db_category (08:56:09) - Safe
            // db_store (09:18:20) - Unsafe (Later)
            // db_units (09:25:03) - Unsafe (Later)
            // db_tax (09:24:57) - Unsafe (Later)
            
            $table->foreign('category_id')->references('id')->on('db_category')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('brand_id')->references('id')->on('db_brands')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_items');
    }
};
