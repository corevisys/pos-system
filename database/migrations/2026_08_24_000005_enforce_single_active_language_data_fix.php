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
        if (!Schema::hasTable('db_languages')) {
            return;
        }

        // Determine the target active language ID
        $targetLanguageId = null;

        if (Schema::hasTable('db_store')) {
            $store = DB::table('db_store')->first();
            if ($store && !empty($store->language_id)) {
                $languageExists = DB::table('db_languages')->where('id', $store->language_id)->exists();
                if ($languageExists) {
                    $targetLanguageId = $store->language_id;
                }
            }
        }

        // If no store language_id was found, pick English or the first existing language
        if (!$targetLanguageId) {
            $englishLang = DB::table('db_languages')->where('language', 'English')->first();
            if ($englishLang) {
                $targetLanguageId = $englishLang->id;
            } else {
                $firstLang = DB::table('db_languages')->first();
                if ($firstLang) {
                    $targetLanguageId = $firstLang->id;
                }
            }
        }

        // If there are languages in the database, enforce single active language
        if ($targetLanguageId) {
            DB::table('db_languages')->where('id', '!=', $targetLanguageId)->update(['status' => 0]);
            DB::table('db_languages')->where('id', $targetLanguageId)->update(['status' => 1]);

            if (Schema::hasTable('db_store')) {
                DB::table('db_store')->update(['language_id' => $targetLanguageId]);
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
