<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a real foreign key linking an EMI installment payment back to the
     * db_emi_schedule row it settled. This replaces the previous implicit
     * (and fragile) string-based 'EMI Installment N' payment_note matching
     * when reversing a payment (SaleController@destroyPayment).
     *
     * The column is nullable because non-EMI payments (POS, Add Sale,
     * generic "Receive Payment") have no schedule row.
     */
    public function up(): void
    {
        Schema::table('db_salespayments', function (Blueprint $table) {
            $table->unsignedBigInteger('emi_schedule_id')->nullable()->after('sales_id');
            $table->index('emi_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('db_salespayments', function (Blueprint $table) {
            $table->dropIndex(['emi_schedule_id']);
            $table->dropColumn('emi_schedule_id');
        });
    }
};
