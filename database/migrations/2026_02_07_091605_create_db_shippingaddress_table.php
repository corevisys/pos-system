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
        Schema::create('db_shippingaddress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->text('address')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('location_link')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('customer_id');

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('db_customers')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('country_id')->references('id')->on('db_country')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_shippingaddress');
    }
};
