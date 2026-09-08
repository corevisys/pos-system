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
        Schema::create('db_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gstin')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('vatin')->nullable();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('sales_due', 16, 2)->default(0);
            $table->decimal('sales_return_due', 16, 2)->default(0);
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('ship_country_id')->nullable();
            $table->unsignedBigInteger('ship_state_id')->nullable();
            $table->string('ship_city')->nullable();
            $table->string('ship_postcode')->nullable();
            $table->text('ship_address')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('location_link')->nullable();
            $table->string('attachment_1')->nullable();
            $table->string('price_level_type')->nullable();
            $table->decimal('price_level', 16, 2)->default(0);

            $table->integer('delete_bit')->default(0);
            $table->softDeletes();
            $table->decimal('tot_advance', 16, 2)->default(0);
            $table->decimal('credit_limit', 16, 2)->default(0);
            $table->unsignedBigInteger('shippingaddress_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('customer_code');
            $table->index('customer_name');
            $table->index('mobile');
            $table->index('email');
            $table->index('country_id');

            // Foreign Keys
            $table->foreign('country_id')->references('id')->on('db_country')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('ship_country_id')->references('id')->on('db_country')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_customers');
    }
};
