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
 * Phase 4.2.2 fix (extended) — the Stock Adjustment and Stock Transfer item-search
 * endpoints must report `stock` via the SAME canonical rule as everywhere else
 * (`DbItem::availableStock($warehouse_id ?: null)`): warehouse-less items fall back to
 * `db_items.stock` (not 0); tracked items keep the requested warehouse's qty; the
 * no-warehouse param path is unchanged (store-wide aggregate).
 *
 * Assertions compare the ENDPOINT RESPONSE `stock` to `availableStock()` exactly —
 * not merely "not 0".
 */

function stockSearchEnv(): array
{
    DbStore::create(['id' => 1, 'store_code' => 'ST001', 'store_name' => 'Stock Search Store', 'status' => 1, 'mobile' => '01711000055']);
    DbRole::create(['id' => 1, 'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
    DbPermission::create(['role_id' => 1, 'store_id' => 1, 'permissions' => ['stock_adjustment_view', 'stock_transfer_view']]);

    $user = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'email_verified_at' => now()]);
    $wh1 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH1', 'status' => 1]);
    $wh2 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH2', 'status' => 1]);

    return compact('user', 'wh1', 'wh2');
}

function stockSearchItem(string $code): DbItem
{
    return DbItem::create([
        'store_id' => 1,
        'item_name' => 'Stock Search Item ' . $code,
        'item_code' => $code,
        'sales_price' => 100,
        'purchase_price' => 60,
        'status' => 1,
        'service_bit' => 0,
    ]);
}

/** Insert a tracked item (2 warehouses: 7 + 3). */
function stockSearchTracked(DbWarehouse $wh1, DbWarehouse $wh2, string $code): DbItem
{
    $item = stockSearchItem($code);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh1->id, 'item_id' => $item->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh2->id, 'item_id' => $item->id, 'available_qty' => 3]);
    return $item;
}

/** Insert a warehouse-less item with db_items.stock = 15. */
function stockSearchWarehouseLess(string $code): DbItem
{
    $item = stockSearchItem($code);
    DbItem::withoutGlobalScopes()->where('id', $item->id)->update(['stock' => 15]);
    return $item;
}

function stockSearchFetch(User $user, string $routeName, string $code, ?int $warehouseId): float
{
    $query = 'query=' . urlencode($code);
    if ($warehouseId !== null) {
        $query .= '&warehouse_id=' . $warehouseId;
    }

    $response = test()->actingAs($user)->getJson(route($routeName) . '?' . $query);
    $response->assertOk();

    $match = collect($response->json())->firstWhere('item_code', $code);
    expect($match)->not->toBeNull("Search returned no row for {$code}");

    return (float) $match['stock'];
}

// ── Stock Adjustment ─────────────────────────────────────────────────────────

test('stock adjustment search stock for a warehouse-less item with warehouse_id equals availableStock (not 0)', function () {
    $env = stockSearchEnv();
    stockSearchWarehouseLess('ADJ-WL');

    $canonical = DbItem::with('warehouseItems')->where('item_code', 'ADJ-WL')->first()->availableStock($env['wh1']->id);
    expect($canonical)->toBe(15.0)
        ->and(stockSearchFetch($env['user'], 'stock.adjustment.search.items', 'ADJ-WL', $env['wh1']->id))->toBe($canonical);
});

test('stock adjustment search stock for a tracked item with warehouse_id equals that warehouse available_qty', function () {
    $env = stockSearchEnv();
    stockSearchTracked($env['wh1'], $env['wh2'], 'ADJ-TR');

    $canonical = DbItem::with('warehouseItems')->where('item_code', 'ADJ-TR')->first()->availableStock($env['wh1']->id);
    expect($canonical)->toBe(7.0)
        ->and(stockSearchFetch($env['user'], 'stock.adjustment.search.items', 'ADJ-TR', $env['wh1']->id))->toBe($canonical);
});

// ── Stock Transfer ───────────────────────────────────────────────────────────

test('stock transfer search stock for a warehouse-less item with warehouse_id equals availableStock (not 0)', function () {
    $env = stockSearchEnv();
    stockSearchWarehouseLess('TRF-WL');

    $canonical = DbItem::with('warehouseItems')->where('item_code', 'TRF-WL')->first()->availableStock($env['wh1']->id);
    expect($canonical)->toBe(15.0)
        ->and(stockSearchFetch($env['user'], 'stock.transfer.search.items', 'TRF-WL', $env['wh1']->id))->toBe($canonical);
});

test('stock transfer search stock for a tracked item with warehouse_id equals that warehouse available_qty', function () {
    $env = stockSearchEnv();
    stockSearchTracked($env['wh1'], $env['wh2'], 'TRF-TR');

    $canonical = DbItem::with('warehouseItems')->where('item_code', 'TRF-TR')->first()->availableStock($env['wh1']->id);
    expect($canonical)->toBe(7.0)
        ->and(stockSearchFetch($env['user'], 'stock.transfer.search.items', 'TRF-TR', $env['wh1']->id))->toBe($canonical);
});

// ── No-warehouse path unchanged (store-wide aggregate) ───────────────────────

test('no-warehouse path returns the store-wide aggregate for both endpoints', function () {
    $env = stockSearchEnv();
    stockSearchTracked($env['wh1'], $env['wh2'], 'NO-AREA');

    // availableStock(null) = 7 + 3 = 10 for the tracked item.
    $canonical = DbItem::with('warehouseItems')->where('item_code', 'NO-AREA')->first()->availableStock(null);
    expect($canonical)->toBe(10.0)
        ->and(stockSearchFetch($env['user'], 'stock.adjustment.search.items', 'NO-AREA', null))->toBe($canonical)
        ->and(stockSearchFetch($env['user'], 'stock.transfer.search.items', 'NO-AREA', null))->toBe($canonical);
});
