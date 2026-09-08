<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make category/brand/variant name+code unique PER STORE instead of globally.
     *
     * Prior state (audit `18a-categories-brands-variants-audit.md`): the `unique:`
     * validation rules on db_category.category_name / db_brands.brand_name /
     * db_variants.variant_name (and the code equivalents) were GLOBAL — Store B
     * could not create "Electronics" if Store A already had it, even though these
     * are store-owned lists.
     *
     * This migration follows the exact precedent set by
     * 2026_09_07_000002_make_supplier_mobile_email_unique_per_store and
     * 2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table:
     *   1. Surface any pre-existing per-store duplicate (do NOT silently dedupe).
     *   2. Drop the plain single-column indexes that are superseded.
     *   3. Add composite unique indexes (store_id, name) and (store_id, code).
     *
     * SQLite's `unique()` allows multiple NULLs (blank name/code rows remain
     * exempt, matching the supplier migration's blank-exemption decision).
     */
    public function up(): void
    {
        // 1. Pre-existing per-store duplicate detection — abort loudly rather than
        //    silently corrupting (mirrors the accounts migration).
        $this->assertNoDuplicates('db_category', 'category_name', 'db_category_store_category_name_unique');
        $this->assertNoDuplicates('db_category', 'category_code', 'db_category_store_category_code_unique');
        $this->assertNoDuplicates('db_brands', 'brand_name', 'db_brands_store_brand_name_unique');
        $this->assertNoDuplicates('db_brands', 'brand_code', 'db_brands_store_brand_code_unique');
        $this->assertNoDuplicates('db_variants', 'variant_name', 'db_variants_store_variant_name_unique');
        $this->assertNoDuplicates('db_variants', 'variant_code', 'db_variants_store_variant_code_unique');

        // 2 + 3. Drop superseded plain indexes and add composite per-store uniques.
        Schema::table('db_category', function (Blueprint $table) {
            $this->dropIndexSafe($table, 'db_category_category_name_index');
            $this->dropIndexSafe($table, 'db_category_category_code_index');
            $table->unique(['store_id', 'category_name'], 'db_category_store_category_name_unique');
            $table->unique(['store_id', 'category_code'], 'db_category_store_category_code_unique');
        });

        Schema::table('db_brands', function (Blueprint $table) {
            $this->dropIndexSafe($table, 'db_brands_brand_name_index');
            $this->dropIndexSafe($table, 'db_brands_brand_code_index');
            $table->unique(['store_id', 'brand_name'], 'db_brands_store_brand_name_unique');
            $table->unique(['store_id', 'brand_code'], 'db_brands_store_brand_code_unique');
        });

        Schema::table('db_variants', function (Blueprint $table) {
            $table->unique(['store_id', 'variant_name'], 'db_variants_store_variant_name_unique');
            $table->unique(['store_id', 'variant_code'], 'db_variants_store_variant_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_category', function (Blueprint $table) {
            $this->dropIndexSafe($table, 'db_category_store_category_name_unique');
            $this->dropIndexSafe($table, 'db_category_store_category_code_unique');
            $table->index('category_name');
            $table->index('category_code');
        });

        Schema::table('db_brands', function (Blueprint $table) {
            $this->dropIndexSafe($table, 'db_brands_store_brand_name_unique');
            $this->dropIndexSafe($table, 'db_brands_store_brand_code_unique');
            $table->index('brand_name');
            $table->index('brand_code');
        });

        Schema::table('db_variants', function (Blueprint $table) {
            $this->dropIndexSafe($table, 'db_variants_store_variant_name_unique');
            $this->dropIndexSafe($table, 'db_variants_store_variant_code_unique');
        });
    }

    private function assertNoDuplicates(string $table, string $column, string $constraintName): void
    {
        $duplicates = DB::select(
            "SELECT store_id, {$column}, COUNT(*) as cnt
             FROM {$table}
             WHERE {$column} IS NOT NULL AND {$column} <> ''
             GROUP BY store_id, {$column}
             HAVING COUNT(*) > 1"
        );

        if (!empty($duplicates)) {
            throw new \RuntimeException(
                "Pre-existing duplicate {$table}.{$column} detected per store. Migration stopped before adding unique constraint {$constraintName}: " . json_encode($duplicates)
            );
        }
    }

    private function dropIndexSafe(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Ignore if the index name differs or is not present (SQLite reports
            // missing indexes differently across drivers).
        }
    }
};
