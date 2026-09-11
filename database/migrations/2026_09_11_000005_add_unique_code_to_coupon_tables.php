<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a unique index on `code` for both coupon tables.
 *
 * WHY: The application validates uniqueness via a SELECT before INSERT.
 * Without a DB-level constraint, two concurrent requests can both pass
 * the SELECT check and both succeed, creating duplicate coupon codes.
 *
 * db_coupons        — codes are network-wide (shared across all stores),
 *                     so the constraint is simply unique(code).
 * db_customer_coupons — same: codes are also network-wide (they must not
 *                     clash with db_coupons codes, cross-store).
 *
 * The cross-table check (CouponController / CustomerCouponController) remains
 * as defence-in-depth but the DB constraint is the authoritative last line.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deduplicate any existing rows before adding the constraint
        // (keeps the LOWER(TRIM(code)) semantic the app uses).
        // On MySQL/MariaDB this is safe; on SQLite unique is case-insensitive
        // by default for ASCII so duplicates are extremely unlikely in practice.

        Schema::table('db_coupons', function (Blueprint $table) {
            // Drop the plain (non-unique) index on code that already exists,
            // then add a unique index in its place.
            try {
                $table->dropIndex(['code']);
            } catch (\Exception $e) {
                // Index may not exist (fresh install) — ignore.
            }
            $table->unique('code', 'db_coupons_code_unique');
        });

        Schema::table('db_customer_coupons', function (Blueprint $table) {
            try {
                $table->dropIndex(['code']);
            } catch (\Exception $e) {
                // Index may not exist — ignore.
            }
            $table->unique('code', 'db_customer_coupons_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('db_coupons', function (Blueprint $table) {
            $table->dropUnique('db_coupons_code_unique');
            $table->index('code');
        });

        Schema::table('db_customer_coupons', function (Blueprint $table) {
            $table->dropUnique('db_customer_coupons_code_unique');
            $table->index('code');
        });
    }
};
