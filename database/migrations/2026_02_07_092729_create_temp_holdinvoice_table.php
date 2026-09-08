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
        Schema::create('temp_holdinvoice', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id')->nullable();
            $table->date('invoice_date')->nullable();
            $table->integer('reference_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->decimal('item_qty', 16, 2)->default(0);
            $table->decimal('item_price', 16, 2)->default(0);
            $table->decimal('tax', 16, 2)->default(0);
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('pos')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_holdinvoice');
    }
};
