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
        Schema::create('db_paypalpaylog', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('product_id')->nullable();
            $table->string('txn_id')->nullable();
            $table->decimal('payment_gross', 16, 2)->default(0);
            $table->string('currency_code', 10)->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_paypalpaylog');
    }
};
