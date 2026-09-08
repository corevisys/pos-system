<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the GLOBAL unique indexes on db_suppliers.mobile / .email (added by
     * 2026_09_02_210000) with PER-STORE composite uniques (store_id, mobile) and
     * (store_id, email).
     *
     * Multi-store decision (default): blank/null mobile and email remain EXEMPT from
     * uniqueness — any number of suppliers across stores (or within one store) may
     * leave these fields empty. Only a non-null value must be unique within the same
     * store, which lets each branch keep its own phone/email registry while still
     * rejecting genuine duplicates inside that branch.
     */
    public function up(): void
    {
        // 1. Normalize empty strings to NULL first (nullable unique columns treat
        //    multiple NULLs as distinct, so this keeps blank rows exempt).
        DB::table('db_suppliers')->where('mobile', '')->update(['mobile' => null]);
        DB::table('db_suppliers')->where('email', '')->update(['email' => null]);

        // 2. Drop the global unique indexes.
        Schema::table('db_suppliers', function (Blueprint $table) {
            try {
                $table->dropUnique('db_suppliers_mobile_unique');
            } catch (\Exception $e) {
                // Ignore if not present under this exact name.
            }

            try {
                $table->dropUnique('db_suppliers_email_unique');
            } catch (\Exception $e) {
                // Ignore if not present under this exact name.
            }
        });

        // 3. Add per-store composite unique indexes. Existing rows that already collide
        //    WITHIN the same store (legacy data duplicated after the global-unique era)
        //    would make this fail — dedupe is deliberately NOT automatic here: the
        //    migration surfaces the conflict so an operator can resolve it consciously.
        Schema::table('db_suppliers', function (Blueprint $table) {
            $table->unique(['store_id', 'mobile'], 'db_suppliers_store_mobile_unique');
            $table->unique(['store_id', 'email'], 'db_suppliers_store_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_suppliers', function (Blueprint $table) {
            try {
                $table->dropUnique('db_suppliers_store_mobile_unique');
            } catch (\Exception $e) {
                // Ignore if not present.
            }

            try {
                $table->dropUnique('db_suppliers_store_email_unique');
            } catch (\Exception $e) {
                // Ignore if not present.
            }

            $table->unique('mobile', 'db_suppliers_mobile_unique');
            $table->unique('email', 'db_suppliers_email_unique');
        });
    }
};
