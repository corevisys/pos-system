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
        Schema::create('db_instamojo', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('sandbox')->default(0);
            $table->string('api_key')->nullable();
            $table->string('api_token')->nullable();
            $table->integer('updated_by')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_instamojo');
    }
};
