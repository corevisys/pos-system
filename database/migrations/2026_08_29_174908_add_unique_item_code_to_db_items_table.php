<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique constraint on db_items.item_code to prevent code collisions
     * (the Quick-Add flow previously hand-rolled the code and could collide).
     *
     * Before adding the constraint, dedupe existing rows: for each group of rows
     * sharing an item_code, keep the lowest id and regenerate a fresh unique code
     * (via CodeGeneratorService::generate('item')) for the duplicates.
     */
    public function up(): void
    {
        // 1. Dedupe: find item_codes that occur more than once (excluding NULL/empty).
        $duplicateCodes = DB::table('db_items')
            ->select('item_code')
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->groupBy('item_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('item_code');

        foreach ($duplicateCodes as $code) {
            $rows = DB::table('db_items')
                ->where('item_code', $code)
                ->orderBy('id')
                ->get();

            // Keep the first (lowest id); regenerate a unique code for the rest.
            foreach ($rows as $i => $row) {
                if ($i === 0) {
                    continue;
                }
                $newCode = $this->freshCode();
                DB::table('db_items')
                    ->where('id', $row->id)
                    ->update(['item_code' => $newCode]);
            }
        }

        // 2. Add the unique index (safe because no duplicates remain).
        Schema::table('db_items', function (Blueprint $table) {
            $table->unique('item_code', 'uq_db_items_item_code');
        });
    }

    public function down(): void
    {
        Schema::table('db_items', function (Blueprint $table) {
            $table->dropUnique('uq_db_items_item_code');
        });
    }

    private function freshCode(): string
    {
        // Keep generating until we find one not currently in use (avoids re-collision
        // during the dedupe pass).
        do {
            $code = \App\Services\CodeGeneratorService::generate('item');
        } while (DB::table('db_items')->where('item_code', $code)->exists());

        return $code;
    }
};
