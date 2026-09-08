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
        Schema::create('db_customer_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('salespayment_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('payment', 16, 2)->default(0);
            $table->text('payment_note')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->time('created_time')->nullable();
            $table->date('created_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_customer_payments');
    }
};
