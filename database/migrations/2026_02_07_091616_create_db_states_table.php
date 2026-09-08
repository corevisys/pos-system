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
        Schema::create('db_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('state_code')->nullable();
            $table->string('state')->nullable();
            $table->string('country_code')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('added_on')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('country_id');
            $table->index('company_id');
            $table->index('state');

            // Foreign Keys
            $table->foreign('country_id')->references('id')->on('db_country')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_states');
    }
};
