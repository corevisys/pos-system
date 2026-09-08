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
        Schema::create('db_subscription', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('payment_id')->nullable();
            $table->integer('package_id')->nullable();
            $table->string('package_type')->nullable();
            $table->string('package_name')->nullable();
            $table->text('description')->nullable();
            $table->date('subscription_date')->nullable();
            $table->date('expire_date')->nullable();
            $table->integer('trial_days')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('max_warehouses')->default(0);
            $table->integer('max_items')->default(0);
            $table->integer('max_invoices')->default(0);
            $table->string('payment_by')->nullable();
            $table->string('txn_id')->nullable();
            $table->decimal('payment_gross', 16, 2)->default(0);
            $table->string('currency_code')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payment_status')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('package_status')->default(1);
            $table->string('payment_type')->nullable();
            $table->integer('package_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_subscription');
    }
};
