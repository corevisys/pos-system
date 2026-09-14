<?php

namespace App\Console\Commands;

use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use Illuminate\Console\Command;

/**
 * Phase 4.2.0 pre-work (read-only): compare db_items.stock against the true
 * SUM(db_warehouseitems.available_qty) per item, per store, so any pre-existing
 * divergence is a known fact BEFORE the deprecation refactor begins.
 *
 * Read-only. Safe to run any time.
 */
class CompareItemStockSources extends Command
{
    protected $signature = 'items:compare-stock-sources';

    protected $description = 'Report items where db_items.stock != SUM(db_warehouseitems.available_qty)';

    public function handle(): int
    {
        $items = DbItem::withoutGlobalScopes()
            ->where('service_bit', '!=', 1)
            ->get(['id', 'store_id', 'item_code', 'item_name', 'stock']);

        $sums = DbWarehouseItem::withoutGlobalScopes()
            ->selectRaw('item_id, SUM(available_qty) as total, COUNT(*) as wh_count')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        $noWarehouseRows = 0;
        $mismatch = 0;
        $rows = [];

        foreach ($items as $item) {
            $agg = $sums->get($item->id);
            $warehouseSum = $agg ? (float) $agg->total : 0.0;
            $hasRows = $agg ? (int) $agg->wh_count > 0 : false;

            if (!$hasRows) {
                // Items with NO warehouse rows are a documented legacy case where
                // db_items.stock is intentionally the only figure. Report count only.
                $noWarehouseRows++;
                continue;
            }

            if (abs((float) $item->stock - $warehouseSum) > 0.001) {
                $mismatch++;
                $rows[] = [$item->id, $item->store_id, $item->item_code, (string) $item->stock, (string) $warehouseSum];
            }
        }

        $this->info('Non-service items scanned: ' . $items->count());
        $this->info('Items with NO warehouse rows (legacy, db_items.stock authoritative): ' . $noWarehouseRows);
        $this->info('Items WITH warehouse rows but MISMATCHED stock: ' . $mismatch);

        if ($rows) {
            $this->table(['id', 'store_id', 'item_code', 'db_items.stock', 'SUM(available_qty)'], $rows);
        }

        return self::SUCCESS;
    }
}
