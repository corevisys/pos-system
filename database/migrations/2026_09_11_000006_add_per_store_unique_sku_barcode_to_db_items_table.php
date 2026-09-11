<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add PER-STORE composite unique constraints on db_items.sku and
 * db_items.custom_barcode, mirroring the existing
 * uq_db_items_store_item_code (store_id, item_code) constraint.
 *
 * WHY: these two columns previously had no unique constraint at all (not even
 * global). Items are tenant-isolated via the StoreScoped trait, so uniqueness
 * belongs per store — matching item_code/customer_code/supplier_code which were
 * already migrated to composite per-store uniques.
 *
 * BLANK HANDLING: MySQL treats multiple NULLs as distinct but multiple empty
 * strings ('') as duplicates. To keep blank sku/barcode rows exempt (the same
 * decision taken for suppliers' blank mobile/email), existing '' values are
 * normalized to NULL BEFORE the constraint is added. The app's global
 * ConvertEmptyStringsToNull middleware keeps future blank submissions as NULL,
 * so the constraint never blocks an item that simply omits sku/barcode.
 *
 * SAFETY: aborts loudly if any (store_id, column) duplicate already exists
 * rather than silently corrupting — matching the established precedent in
 * 2026_09_08_000001 and 2026_09_11_000003.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalize empty strings to NULL so blank rows are exempt from the
        //    composite unique (multiple NULLs are allowed).
        DB::table('db_items')->where('sku', '')->update(['sku' => null]);
        DB::table('db_items')->where('custom_barcode', '')->update(['custom_barcode' => null]);

        // 2. Pre-existing per-store duplicate detection — abort loudly.
        $this->assertNoDuplicates('sku', 'uq_db_items_store_sku');
        $this->assertNoDuplicates('custom_barcode', 'uq_db_items_store_custom_barcode');

        // 3. Add the composite per-store unique constraints.
        Schema::table('db_items', function (Blueprint $table) {
            $table->unique(['store_id', 'sku'], 'uq_db_items_store_sku');
            $table->unique(['store_id', 'custom_barcode'], 'uq_db_items_store_custom_barcode');
        });
    }

    public function down(): void
    {
        Schema::table('db_items', function (Blueprint $table) {
            $table->dropUnique('uq_db_items_store_sku');
            $table->dropUnique('uq_db_items_store_custom_barcode');
        });
    }

    private function assertNoDuplicates(string $column, string $constraintName): void
    {
        $duplicates = DB::select(
            "SELECT store_id, {$column}, COUNT(*) as cnt
             FROM db_items
             WHERE {$column} IS NOT NULL AND {$column} <> ''
             GROUP BY store_id, {$column}
             HAVING COUNT(*) > 1"
        );

        if (!empty($duplicates)) {
            throw new \RuntimeException(
                "Pre-existing duplicate db_items.{$column} detected per store. Migration stopped before adding unique constraint {$constraintName}: " . json_encode($duplicates)
            );
        }
    }
};
