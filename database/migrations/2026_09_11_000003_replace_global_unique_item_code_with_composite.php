<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 - Replace the global unique index on db_items.item_code
     * (uq_db_items_item_code) with a store-scoped composite unique index
     * (store_id, item_code), matching the pattern already used by ac_accounts
     * and ac_moneytransfer.
     *
     * Rationale: DbItem uses the StoreScoped trait - items are fully tenant-
     * isolated. A global unique prevents Store B from onboarding a product with
     * the same item_code that Store A already uses, which is a legitimate
     * multi-store scenario.
     *
     * Pre-check: Abort loudly if any (store_id, item_code) pair is duplicated,
     * because that would prevent the new composite index from being created.
     */
    public function up(): void
    {
        // 1. Assert no (store_id, item_code) duplicates already exist.
        $dups = DB::table('db_items')
            ->select('store_id', 'item_code', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->groupBy('store_id', 'item_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dups->isNotEmpty()) {
            $details = $dups->map(fn ($r) => "store_id={$r->store_id} item_code={$r->item_code} count={$r->cnt}")->implode('; ');
            throw new \RuntimeException(
                "Phase 4 migration aborted: duplicate (store_id, item_code) pairs found in db_items. " .
                "Resolve them before re-running: {$details}"
            );
        }

        Schema::table('db_items', function (Blueprint $table) {
            // Drop the old global unique index.
            // Use try/catch in case the migration is run against a DB where
            // the old index was never applied (e.g. a fresh test database).
            try {
                $table->dropUnique('uq_db_items_item_code');
            } catch (\Throwable $e) {
                // Already absent - not an error.
            }

            // Add the correct composite unique constraint.
            $table->unique(['store_id', 'item_code'], 'uq_db_items_store_item_code');
        });
    }

    public function down(): void
    {
        Schema::table('db_items', function (Blueprint $table) {
            $table->dropUnique('uq_db_items_store_item_code');

            // Restore the old global unique (best-effort; may fail if duplicates
            // now exist across stores - acceptable for a rollback scenario).
            try {
                $table->unique('item_code', 'uq_db_items_item_code');
            } catch (\Throwable $e) {
                // Cannot restore global unique when cross-store duplicates exist.
            }
        });
    }
};
