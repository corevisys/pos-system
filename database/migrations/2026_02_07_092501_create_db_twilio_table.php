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
        Schema::create('db_twilio', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->string('account_sid')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('twilio_phone')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_twilio');
    }
};
