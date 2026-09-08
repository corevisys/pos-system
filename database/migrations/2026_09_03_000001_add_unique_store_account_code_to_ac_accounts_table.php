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
        // 1. Check for existing duplicate account_code values per store
        $duplicates = DB::select("
            SELECT store_id, account_code, COUNT(*) as cnt 
            FROM ac_accounts 
            WHERE delete_bit = 0 
            GROUP BY store_id, account_code 
            HAVING COUNT(*) > 1
        ");

        if (!empty($duplicates)) {
            throw new \RuntimeException("Pre-existing duplicate account_code detected for store. Migration stopped before adding unique constraint: " . json_encode($duplicates));
        }

        // 2. Replace non-unique index with composite unique index (store_id, account_code)
        Schema::table('ac_accounts', function (Blueprint $table) {
            try {
                $table->dropIndex(['account_code']);
            } catch (\Exception $e) {
                // Ignore if index name differs or not found
            }

            $table->unique(['store_id', 'account_code'], 'ac_accounts_store_account_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ac_accounts', function (Blueprint $table) {
            $table->dropUnique('ac_accounts_store_account_code_unique');
            $table->index('account_code');
        });
    }
};
