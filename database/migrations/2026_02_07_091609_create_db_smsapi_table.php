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
        Schema::create('db_smsapi', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->text('info')->nullable();
            $table->string('key')->nullable();
            $table->text('key_value')->nullable();
            $table->integer('delete_bit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_smsapi');
    }
};
