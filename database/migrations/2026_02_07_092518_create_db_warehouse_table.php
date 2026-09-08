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
        Schema::create('db_warehouse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('warehouse_type')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->integer('status')->default(1);
            $table->date('created_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('warehouse_name');
            $table->index('email');
            $table->index('mobile');

            // Foreign Keys
            $table->foreign('store_id')->references('id')->on('db_store')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_warehouse');
    }
};
