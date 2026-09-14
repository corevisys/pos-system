<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.1 — remove confirmed dead columns.
 *
 * `to_store_id` on db_stocktransfer / db_stocktransferitems was intended for
 * inter-store stock transfer, which is explicitly OUT OF SCOPE (owner decision):
 * outlets within one store are separate warehouses and move stock via
 * warehouse-to-warehouse transfer. No controller ever writes `to_store_id`, so the
 * columns are dead and have been removed from both models' $fillable too.
 *
 * Guarded with hasColumn so it is safe on databases that never had the column.
 * Dropping a column also drops any single-column index on it (MySQL), so no
 * separate dropIndex is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite requires the index to be dropped before the column it references
        // (MySQL drops the index automatically with the column). Drop the index
        // first, tolerating its absence on engines that already removed it.
        foreach ([
            ['db_stocktransfer', 'db_stocktransfer_to_store_id_index'],
            ['db_stocktransferitems', 'db_stocktransferitems_to_store_id_index'],
        ] as [$table, $index]) {
            if (!Schema::hasColumn($table, 'to_store_id')) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable $e) {
                // Index may not exist (already dropped) — safe to continue.
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('to_store_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('db_stocktransfer', 'to_store_id')) {
            Schema::table('db_stocktransfer', function (Blueprint $table) {
                $table->unsignedBigInteger('to_store_id')->nullable()->after('store_id');
            });
        }

        if (!Schema::hasColumn('db_stocktransferitems', 'to_store_id')) {
            Schema::table('db_stocktransferitems', function (Blueprint $table) {
                $table->unsignedBigInteger('to_store_id')->nullable()->after('store_id');
            });
        }
    }
};
