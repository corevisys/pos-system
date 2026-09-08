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
        Schema::create('sms_auto_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->string('event_type'); // InvoiceCreated, CustomerBirthday, etc.
            $table->string('event_source'); // invoice, emi, birthday, manual
            $table->unsignedBigInteger('template_id');
            
            $table->string('trigger_time')->default('immediate'); // immediate, before_due, after_due
            $table->integer('days_offset')->default(0);
            $table->integer('cooldown_days')->default(0);
            
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('event_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_auto_rules');
    }
};
