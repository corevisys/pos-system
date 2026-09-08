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
        Schema::create('db_store', function (Blueprint $table) {
            $table->id();
            $table->string('store_code')->nullable();
            $table->string('store_name')->nullable();
            $table->string('store_website')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('store_logo')->nullable();
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
            $table->string('cid')->nullable();
            $table->string('category_init')->nullable();
            $table->string('item_init')->nullable();
            $table->string('supplier_init')->nullable();
            $table->string('purchase_init')->nullable();
            $table->string('purchase_return_init')->nullable();
            $table->string('customer_init')->nullable();
            $table->string('sales_init')->nullable();
            $table->string('sales_return_init')->nullable();
            $table->string('expense_init')->nullable();
            $table->string('accounts_init')->nullable();
            $table->string('journal_init')->nullable();
            $table->string('cust_advance_init')->nullable();
            $table->integer('invoice_view')->nullable();
            $table->integer('sms_status')->default(0);
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('language_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_placement')->nullable();
            $table->string('timezone')->nullable();
            $table->string('date_format')->nullable();
            $table->string('time_format')->nullable();
            $table->decimal('sales_discount', 16, 2)->default(0);
            $table->unsignedBigInteger('currencysymbol_id')->nullable();
            $table->string('regno_key')->nullable();
            $table->string('fav_icon')->nullable();
            $table->string('purchase_code')->nullable();
            $table->integer('change_return')->default(0);
            $table->unsignedBigInteger('sales_invoice_format_id')->nullable();
            $table->unsignedBigInteger('pos_invoice_format_id')->nullable();
            $table->text('sales_invoice_footer_text')->nullable();
            $table->integer('round_off')->default(0);
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->string('quotation_init')->nullable();
            $table->integer('decimals')->default(2);
            $table->string('money_transfer_init')->nullable();
            $table->string('sales_payment_init')->nullable();
            $table->string('sales_return_payment_init')->nullable();
            $table->string('purchase_payment_init')->nullable();
            $table->string('purchase_return_payment_init')->nullable();
            $table->string('expense_payment_init')->nullable();
            $table->unsignedBigInteger('current_subscriptionlist_id')->nullable();
            $table->string('smtp_host')->nullable();
            $table->string('smtp_port')->nullable();
            $table->string('smtp_user')->nullable();
            $table->string('smtp_pass')->nullable();
            $table->integer('smtp_status')->default(0);
            $table->text('sms_url')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('mrp_column')->default(0);
            $table->text('invoice_terms')->nullable();
            $table->integer('previous_balance_bit')->default(0);
            $table->integer('qty_decimals')->default(2);
            $table->integer('t_and_c_status')->default(0);
            $table->integer('t_and_c_status_pos')->default(0);
            $table->integer('number_to_words')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('store_code');
            $table->index('language_id');
            $table->index('currency_id');
            $table->index('user_id');

            // Foreign Keys
            // db_languages and db_currency exist before db_store naturally in this folder.
            // db_languages (09:07:26) < db_store (09:18:20) -> Safe
            // db_currency (08:56:21) < db_store (09:18:20) -> Safe
            $table->foreign('language_id')->references('id')->on('db_languages')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('currency_id')->references('id')->on('db_currency')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_store');
    }
};
