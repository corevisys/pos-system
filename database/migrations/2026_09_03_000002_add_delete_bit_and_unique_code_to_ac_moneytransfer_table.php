<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pre-check for duplicate transfer codes within the same store
        $duplicates = DB::select("
            SELECT store_id, transfer_code, COUNT(*) as cnt
            FROM ac_moneytransfer
            WHERE transfer_code IS NOT NULL
            GROUP BY store_id, transfer_code
            HAVING cnt > 1
        ");

        if (!empty($duplicates)) {
            $report = json_encode($duplicates);
            throw new \RuntimeException("Cannot apply unique constraint: duplicate transfer_code detected: {$report}");
        }

        Schema::table('ac_moneytransfer', function (Blueprint $table) {
            if (!Schema::hasColumn('ac_moneytransfer', 'delete_bit')) {
                $table->integer('delete_bit')->default(0)->after('status');
                $table->index('delete_bit');
            }

            $table->unique(['store_id', 'transfer_code'], 'ac_moneytransfer_store_transfer_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ac_moneytransfer', function (Blueprint $table) {
            $table->dropUnique('ac_moneytransfer_store_transfer_code_unique');
            if (Schema::hasColumn('ac_moneytransfer', 'delete_bit')) {
                $table->dropColumn('delete_bit');
            }
        });
    }
};
