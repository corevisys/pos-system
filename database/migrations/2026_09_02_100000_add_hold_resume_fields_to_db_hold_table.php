<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add hold-resume lifecycle + state-persistence columns to db_hold.
     *
     * - status: 'open' (default) | 'completed'. Used as an atomic claim flag at
     *   store()-time so a held invoice can only ever be completed ONCE —
     *   prevents the double-resume → duplicate sale + double stock decrement
     *   race (previously the hold was only deleted after the sale committed,
     *   so two concurrent completions of the same hold_id both succeeded).
     * - discount_on_all / discount_type / coupon_* : persist the cart-level
     *   discount + coupon state at hold time so resume restores the exact held
     *   cart instead of silently dropping these fields.
     */
    public function up(): void
    {
        Schema::table('db_hold', function (Blueprint $table) {
            $table->string('status')->default('open')->index()->after('pos');
            $table->decimal('discount_on_all', 16, 2)->default(0)->after('status');
            $table->string('discount_type')->nullable()->after('discount_on_all');
            $table->integer('coupon_id')->nullable()->after('discount_type');
            $table->integer('customer_coupon_id')->nullable()->after('coupon_id');
            $table->string('coupon_code')->nullable()->after('customer_coupon_id');
            $table->string('coupon_type')->nullable()->after('coupon_code');
            $table->decimal('coupon_value', 16, 2)->default(0)->after('coupon_type');
            $table->decimal('coupon_amount', 16, 2)->default(0)->after('coupon_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_hold', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'discount_on_all',
                'discount_type',
                'coupon_id',
                'customer_coupon_id',
                'coupon_code',
                'coupon_type',
                'coupon_value',
                'coupon_amount',
            ]);
        });
    }
};
