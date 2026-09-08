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
        Schema::create('db_emi_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('loan_amount', 16, 2);
            $table->decimal('total_payable', 16, 2);
            $table->integer('duration_months');
            $table->decimal('monthly_installment', 16, 2);
            $table->decimal('processing_fee', 16, 2)->default(0);
            $table->date('start_date');
            $table->string('status')->default('Active'); // Active, Completed, Defaulted
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('db_sales')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('db_customers')->onDelete('cascade');
        });

        Schema::create('db_emi_schedule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emi_sale_id');
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 16, 2);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->string('status')->default('Pending'); // Pending, Paid, Partially Paid
            $table->timestamps();

            $table->foreign('emi_sale_id')->references('id')->on('db_emi_sales')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_emi_schedule');
        Schema::dropIfExists('db_emi_sales');
    }
};
