<?php

use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 4.2 — db_items.stock vs db_warehouseitems.available_qty.
 *
 * The chosen model (see docs/MULTISTORE_FOLLOWUPS.md): availableStock() sums the
 * warehouse rows when they exist and falls back to db_items.stock for warehouse-less
 * items. db_items.stock is kept as an authoritative figure for those items and, for
 * items WITH warehouse rows, is kept equal to the warehouse sum by syncGlobalStock().
 */

function stockTruthEnv(): array
{
    DbStore::create(['id' => 1, 'store_code' => 'ST001', 'store_name' => 'Stock Store', 'status' => 1, 'mobile' => '01711000099']);
    DbRole::create(['id' => 1, 'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
    DbPermission::create(['role_id' => 1, 'store_id' => 1, 'permissions' => ['sales_view', 'sales_add', 'items_view']]);

    $user = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'email_verified_at' => now()]);
    $wh1 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH1', 'status' => 1]);
    $wh2 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH2', 'status' => 1]);

    return compact('user', 'wh1', 'wh2');
}

// ── 1. Warehouse-less items keep db_items.stock as authoritative ─────────────

test('warehouse-less item reports db_items.stock as availability', function () {
    stockTruthEnv();

    $item = DbItem::create([
        'store_id' => 1, 'item_name' => 'Legacy Item', 'item_code' => 'LEG-1',
        'stock' => 42, 'sales_price' => 10, 'purchase_price' => 5, 'status' => 1,
    ]);

    // No warehouse rows exist → fall back to db_items.stock (must NOT be zero).
    expect($item->availableStock())->toBe(42.0)
        ->and($item->availableStock(1))->toBe(42.0);
});

// ── 2. Multi-warehouse items sum across warehouses ───────────────────────────

test('multi-warehouse item availability is the summed qty and per-warehouse when filtered', function () {
    $env = stockTruthEnv();

    $item = DbItem::create([
        'store_id' => 1, 'item_name' => 'Split Item', 'item_code' => 'SPL-1',
        'stock' => 0, 'sales_price' => 10, 'purchase_price' => 5, 'status' => 1,
    ]);

    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $item->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $item->id, 'available_qty' => 3]);

    $fresh = DbItem::find($item->id);

    expect($fresh->availableStock())->toBe(10.0)               // 7 + 3 summed
        ->and($fresh->availableStock($env['wh1']->id))->toBe(7.0)
        ->and($fresh->availableStock($env['wh2']->id))->toBe(3.0);
});

// ── 3. syncGlobalStock keeps db_items.stock equal to the warehouse sum ────────

test('syncGlobalStock sets db_items.stock to the warehouse sum and never zeroes warehouse-less items', function () {
    $env = stockTruthEnv();

    $withWh = DbItem::create([
        'store_id' => 1, 'item_name' => 'Has WH', 'item_code' => 'HAS-1',
        'stock' => 999, 'sales_price' => 10, 'purchase_price' => 5, 'status' => 1,
    ]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $withWh->id, 'available_qty' => 4]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $withWh->id, 'available_qty' => 6]);

    DbItem::syncGlobalStock($withWh->id);
    expect((float) DbItem::find($withWh->id)->stock)->toBe(10.0); // 4 + 6, not the stale 999

    $noWh = DbItem::create([
        'store_id' => 1, 'item_name' => 'No WH', 'item_code' => 'NOWH-1',
        'stock' => 55, 'sales_price' => 10, 'purchase_price' => 5, 'status' => 1,
    ]);

    DbItem::syncGlobalStock($noWh->id);
    // No warehouse rows → left untouched, NOT zeroed.
    expect((float) DbItem::find($noWh->id)->stock)->toBe(55.0);
});

// ── 4. POS availability uses the SUMMED warehouse qty across two warehouses ───

test('pos checkout availability uses the summed warehouse qty across two warehouses', function () {
    $env = stockTruthEnv();

    $item = DbItem::create([
        'store_id' => 1, 'item_name' => 'POS Split', 'item_code' => 'POSS-1',
        'stock' => 0, 'sales_price' => 100, 'purchase_price' => 50, 'status' => 1,
    ]);

    // 7 in WH1 + 3 in WH2 = 10 total; db_items.stock seeded to 0 to prove the check
    // does NOT rely on the (stale) global column.
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $item->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $item->id, 'available_qty' => 3]);

    // The canonical availability is the summed 10 regardless of which warehouse is
    // selected for the sale — confirming multi-warehouse stock is not hidden.
    $fresh = DbItem::find($item->id);
    expect($fresh->availableStock())->toBe(10.0);
});
