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
        Schema::create('db_instamojopayments', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('buyer_name')->nullable();
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('purpose')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->nullable();
            $table->integer('send_sms')->default(0);
            $table->integer('send_email')->default(0);
            $table->string('sms_status')->nullable();
            $table->string('email_status')->nullable();
            $table->string('shorturl')->nullable();
            $table->string('longurl')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('webhook')->nullable();
            $table->integer('allow_repeated_payments')->default(0);
            $table->integer('customer_id')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_instamojopayments');
    }
};
