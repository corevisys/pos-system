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
        Schema::create('db_custadvance', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('payment_code')->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('payment_type')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('payment_code');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_custadvance');
    }
};
