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
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            
            $table->string('phone');
            $table->text('message');
            $table->string('message_type')->default('transactional'); // promotional, transactional
            $table->string('encoding')->default('GSM-7'); // GSM-7, Unicode
            $table->integer('sms_parts')->default(1);
            
            $table->decimal('cost', 10, 4)->default(0.0000);
            $table->decimal('rate_per_sms', 10, 4)->default(0.0000);
            
            $table->string('provider')->nullable();
            $table->string('request_id')->nullable(); // Provider side tracking
            $table->string('batch_id')->nullable();   // Batch/Chunk grouping
            $table->string('provider_message_id')->nullable(); // For Idempotency & Delivery tracking
            
            $table->string('status')->default('Pending'); // Pending, Sent, Delivered, Failed, Rejected
            $table->text('api_response')->nullable();
            $table->string('error_code')->nullable();
            
            $table->string('message_hash')->nullable(); // For duplicate prevention: hash(phone + message + date_window)
            $table->string('ip_address')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0);
            
            $table->timestamps();

            // Indexing strategy for high volume
            $table->index('phone');
            $table->index('campaign_id');
            $table->index('status');
            $table->index('sent_at');
            $table->index('message_hash');
            $table->index('batch_id');
            $table->index('provider_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
