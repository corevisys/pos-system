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
        Schema::create('ac_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('count_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('sort_code')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_code')->nullable();
            $table->decimal('balance', 16, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('created_date')->nullable();
            $table->time('created_time')->nullable();
            $table->string('system_ip')->nullable();
            $table->string('system_name')->nullable();
            $table->integer('status')->default(1);
            $table->integer('delete_bit')->default(0);
            $table->string('account_selection_name')->nullable();
            $table->integer('paymenttypes_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('store_id');
            $table->index('parent_id');
            $table->index('account_code');
            $table->index('account_name');

            // Foreign Keys
            $table->foreign('parent_id')->references('id')->on('ac_accounts')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ac_accounts');
    }
};
