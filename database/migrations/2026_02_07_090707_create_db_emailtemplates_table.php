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
        Schema::create('db_emailtemplates', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->string('key')->nullable();
            $table->string('template_name')->nullable();
            $table->text('content')->nullable();
            $table->text('variables')->nullable();
            $table->integer('status')->default(1);
            $table->integer('undelete_bit')->default(0);
            $table->integer('admin_only')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_emailtemplates');
    }
};
