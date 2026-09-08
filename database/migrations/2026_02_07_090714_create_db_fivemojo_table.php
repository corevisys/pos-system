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
        Schema::create('db_fivemojo', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->string('url')->nullable();
            $table->string('token')->nullable();
            $table->string('instance_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_fivemojo');
    }
};
