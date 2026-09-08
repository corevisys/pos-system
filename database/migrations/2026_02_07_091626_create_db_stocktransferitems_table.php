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
        Schema::create('db_stocktransferitems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stocktransfer_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->unsignedBigInteger('warehouse_from')->nullable();
            $table->unsignedBigInteger('warehouse_to')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->decimal('transfer_qty', 16, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('stocktransfer_id');
            $table->index('store_id');
            $table->index('to_store_id');
            $table->index('warehouse_from');
            $table->index('warehouse_to');
            $table->index('item_id');

            // Foreign Keys
            $table->foreign('stocktransfer_id')->references('id')->on('db_stocktransfer')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('item_id')->references('id')->on('db_items')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_stocktransferitems');
    }
};
