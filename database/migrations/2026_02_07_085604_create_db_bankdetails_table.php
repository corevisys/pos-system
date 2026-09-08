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
        Schema::create('db_bankdetails', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->integer('country_id')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('code')->nullable();
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->text('other_details')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_bankdetails');
    }
};
