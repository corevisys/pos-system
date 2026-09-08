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
        Schema::create('db_stripepayments', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->string('paid_amount_currency')->nullable();
            $table->string('txn_id')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_stripepayments');
    }
};
