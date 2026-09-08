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
        Schema::create('db_stockadjustmentitems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('adjustment_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('adjustment_qty', 16, 2)->default(0);
            $table->integer('status')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('warehouse_id');
            $table->index('adjustment_id');
            $table->index('item_id');

            // Foreign Keys
            $table->foreign('adjustment_id')->references('id')->on('db_stockadjustment')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('item_id')->references('id')->on('db_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_stockadjustmentitems');
    }
};
