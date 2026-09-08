<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Normalize empty strings to NULL so nullable unique constraints work as expected
        DB::table('db_suppliers')->where('mobile', '')->update(['mobile' => null]);
        DB::table('db_suppliers')->where('email', '')->update(['email' => null]);

        // 2. Drop existing regular index if needed, and add unique indexes
        Schema::table('db_suppliers', function (Blueprint $table) {
            // Drop regular index if exists
            try {
                $table->dropIndex('db_suppliers_mobile_index');
            } catch (\Exception $e) {
                // Ignore if not present under this exact name
            }

            try {
                $table->dropIndex('db_suppliers_email_index');
            } catch (\Exception $e) {
                // Ignore if not present under this exact name
            }

            $table->unique('mobile', 'db_suppliers_mobile_unique');
            $table->unique('email', 'db_suppliers_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_suppliers', function (Blueprint $table) {
            $table->dropUnique('db_suppliers_mobile_unique');
            $table->dropUnique('db_suppliers_email_unique');
            $table->index('mobile', 'db_suppliers_mobile_index');
            $table->index('email', 'db_suppliers_email_index');
        });
    }
};
