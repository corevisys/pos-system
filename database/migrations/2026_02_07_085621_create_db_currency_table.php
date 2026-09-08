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
        Schema::create('db_currency', function (Blueprint $table) {
            $table->id();
            $table->string('currency_name')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('currency')->nullable();
            $table->string('symbol')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            $table->index('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_currency');
    }
};
