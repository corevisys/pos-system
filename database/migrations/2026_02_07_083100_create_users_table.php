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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('member_of')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('mobile')->nullable();
            $table->string('photo')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postcode')->nullable();
            $table->string('role_name')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('profile_picture')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('creater_id')->nullable();
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('role_id');
            $table->index('username');
            $table->index('mobile');
            $table->index('status');

            // Foreign Keys
            if (Schema::hasTable('db_store')) {
                $table->foreign('store_id')->references('id')->on('db_store')->onDelete('cascade')->onUpdate('cascade');
            }
            if (Schema::hasTable('db_roles')) {
                $table->foreign('role_id')->references('id')->on('db_roles')->onDelete('cascade')->onUpdate('cascade');
            }
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
