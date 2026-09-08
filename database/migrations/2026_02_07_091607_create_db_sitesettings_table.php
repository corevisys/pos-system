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
        Schema::create('db_sitesettings', function (Blueprint $table) {
            $table->id();
            $table->string('version')->nullable();
            $table->string('site_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('machine_id')->nullable();
            $table->string('domain')->nullable();
            $table->string('unique_code')->nullable();
            $table->timestamps();

            $table->index('unique_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_sitesettings');
    }
};
