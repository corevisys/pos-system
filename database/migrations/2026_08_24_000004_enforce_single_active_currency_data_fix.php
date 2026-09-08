<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('db_currency')) {
            return;
        }

        // Determine the target active currency ID
        $targetCurrencyId = null;

        if (Schema::hasTable('db_store')) {
            $store = DB::table('db_store')->first();
            if ($store && !empty($store->currency_id)) {
                $currencyExists = DB::table('db_currency')->where('id', $store->currency_id)->exists();
                if ($currencyExists) {
                    $targetCurrencyId = $store->currency_id;
                }
            }
        }

        // If no store currency_id was found, pick the first existing currency
        if (!$targetCurrencyId) {
            $firstCurrency = DB::table('db_currency')->first();
            if ($firstCurrency) {
                $targetCurrencyId = $firstCurrency->id;
            }
        }

        // If there are currencies in the database, enforce single active currency
        if ($targetCurrencyId) {
            DB::table('db_currency')->where('id', '!=', $targetCurrencyId)->update(['status' => 0]);
            DB::table('db_currency')->where('id', $targetCurrencyId)->update(['status' => 1]);

            if (Schema::hasTable('db_store')) {
                DB::table('db_store')->update(['currency_id' => $targetCurrencyId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data correction is intentionally one-way.
    }
};
