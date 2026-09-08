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
        Schema::create('db_stocktransfer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('to_store_id')->nullable();
            $table->unsignedBigInteger('warehouse_from')->nullable();
            $table->unsignedBigInteger('warehouse_to')->nullable();
            $table->date('transfer_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('to_store_id');
            $table->index('warehouse_from');
            $table->index('warehouse_to');
            $table->index('transfer_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_stocktransfer');
    }
};
