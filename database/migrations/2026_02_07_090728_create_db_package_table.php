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
        Schema::create('db_package', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('package_type')->nullable();
            $table->string('package_code')->nullable();
            $table->string('package_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 16, 2)->default(0);
            $table->decimal('annual_price', 16, 2)->default(0);
            $table->integer('trial_days')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('max_items')->default(0);
            $table->integer('max_invoices')->default(0);
            $table->integer('max_warehouses')->default(0);
            $table->date('expire_date')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('status')->default(1);
            $table->string('plan_type')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('package_code');
            $table->index('package_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_package');
    }
};
