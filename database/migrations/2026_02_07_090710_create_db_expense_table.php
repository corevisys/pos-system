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
        Schema::create('db_expense', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('count_id')->nullable();
            $table->string('expense_code')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->date('expense_date')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('expense_for')->nullable();
            $table->decimal('expense_amt', 16, 2)->default(0);
            $table->string('payment_type')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('category_id');
            $table->index('account_id');
            $table->index('expense_code');
            $table->index('expense_date');

            // Foreign Keys
            $table->foreign('account_id')->references('id')->on('ac_accounts')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_expense');
    }
};
