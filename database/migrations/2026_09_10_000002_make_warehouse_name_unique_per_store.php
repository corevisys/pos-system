<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (Warehouse rollout): make warehouse_name unique PER STORE instead of
 * globally.
 *
 * Prior state: the `unique:db_warehouse,warehouse_name` validation rule was
 * GLOBAL — Store B could not create "Main Branch" if Store A already had it, even
 * though warehouses are store-owned sub-locations.
 *
 * This migration follows the exact precedent set by
 * 2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store (and
 * 2026_09_07_000002_make_supplier_mobile_email_unique_per_store):
 *   1. Surface any pre-existing per-store duplicate (do NOT silently dedupe).
 *   2. Drop the superseded plain single-column index (db_warehouse_warehouse_name_index).
 *   3. Add a composite unique index (store_id, warehouse_name).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Pre-existing per-store duplicate detection — abort loudly rather than
        //    silently corrupting (mirrors the accounts/category migrations).
        $duplicates = DB::select(
            "SELECT store_id, warehouse_name, COUNT(*) as cnt
             FROM db_warehouse
             WHERE warehouse_name IS NOT NULL AND warehouse_name <> ''
             GROUP BY store_id, warehouse_name
             HAVING COUNT(*) > 1"
        );

        if (!empty($duplicates)) {
            throw new \RuntimeException(
                'Pre-existing duplicate db_warehouse.warehouse_name detected per store. Migration stopped before adding unique constraint db_warehouse_store_warehouse_name_unique: ' . json_encode($duplicates)
            );
        }

        // 2 + 3. Drop the plain index and add the composite per-store unique.
        Schema::table('db_warehouse', function (Blueprint $table) {
            try {
                $table->dropIndex('db_warehouse_warehouse_name_index');
            } catch (\Exception $e) {
                // Ignore if the index name differs or is not present across drivers.
            }

            $table->unique(['store_id', 'warehouse_name'], 'db_warehouse_store_warehouse_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('db_warehouse', function (Blueprint $table) {
            try {
                $table->dropUnique('db_warehouse_store_warehouse_name_unique');
            } catch (\Exception $e) {
                // Ignore if not present.
            }

            $table->index('warehouse_name');
        });
    }
};
