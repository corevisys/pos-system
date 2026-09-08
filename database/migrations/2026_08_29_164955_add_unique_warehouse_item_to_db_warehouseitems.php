<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bug B fix: add a unique constraint on db_warehouseitems(warehouse_id, item_id)
     * to prevent duplicate per-warehouse stock rows (which double-count stock).
     *
     * Before adding the constraint, dedupe existing rows: merge available_qty into
     * the lowest-id row for each (warehouse_id, item_id) group, then delete the rest.
     */
    public function up(): void
    {
        // 1. Merge duplicate rows: add the duplicate's qty to the surviving (lowest-id) row.
        $duplicates = DB::table('db_warehouseitems as wi')
            ->join('db_warehouseitems as wsurvivor', function ($join) {
                $join->on('wsurvivor.warehouse_id', '=', 'wi.warehouse_id')
                     ->on('wsurvivor.item_id', '=', 'wi.item_id')
                     ->whereRaw('wsurvivor.id < wi.id');
            })
            ->select('wi.id as dup_id', 'wsurvivor.id as survivor_id', 'wi.available_qty as dup_qty')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('db_warehouseitems')
                ->where('id', $dup->survivor_id)
                ->increment('available_qty', $dup->dup_qty);

            DB::table('db_warehouseitems')
                ->where('id', $dup->dup_id)
                ->delete();
        }

        // 2. Now add the unique index (safe because no duplicates remain).
        Schema::table('db_warehouseitems', function (Blueprint $table) {
            $table->unique(['warehouse_id', 'item_id'], 'uq_warehouse_item');
        });
    }

    public function down(): void
    {
        Schema::table('db_warehouseitems', function (Blueprint $table) {
            $table->dropUnique('uq_warehouse_item');
        });
    }
};
