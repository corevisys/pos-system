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
        Schema::create('db_company', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_website')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('logo')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('upi_code')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postcode')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('vat_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->text('bank_details')->nullable();
            $table->integer('cid')->nullable();
            $table->string('category_init')->nullable();
            $table->string('item_init')->nullable();
            $table->string('supplier_init')->nullable();
            $table->string('purchase_init')->nullable();
            $table->string('purchase_return_init')->nullable();
            $table->string('customer_init')->nullable();
            $table->string('sales_init')->nullable();
            $table->string('sales_return_init')->nullable();
            $table->string('expense_init')->nullable();
            $table->integer('invoice_view')->default(1);
            $table->integer('status')->default(1);
            $table->integer('sms_status')->default(1);
            $table->text('sales_terms_and_conditions')->nullable();
            $table->timestamps();

            $table->index('company_code');
            $table->index('email');
            $table->index('mobile');
            $table->index('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_company');
    }
};
