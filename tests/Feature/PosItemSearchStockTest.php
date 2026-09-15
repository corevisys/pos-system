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
 * Phase 4.2.2 fix — POS/Sale item-search (`PosController::searchItems`) must report
 * `stock` using the SAME canonical rule as checkout (`DbItem::availableStock()`):
 *  - warehouse-less item, any warehouse_id → db_items.stock (never 0)
 *  - tracked item, specific warehouse_id   → that warehouse's available_qty
 *  - tracked item, no warehouse_id          → SUM(available_qty)
 *
 * Assertions compare the ENDPOINT RESPONSE `stock` to `availableStock()` — not merely
 * "not 0" — so any future divergence between search and the canonical rule fails.
 *
 * `sales.pos.search.items` is the POS/Sale-SHARED endpoint (pos.blade.php AND
 * add.blade.php both call it), so covering it here covers both pages.
 */

function searchStockEnv(): array
{
    DbStore::create(['id' => 1, 'store_code' => 'ST001', 'store_name' => 'Search Stock Store', 'status' => 1, 'mobile' => '01711000077']);
    DbRole::create(['id' => 1, 'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
    DbPermission::create(['role_id' => 1, 'store_id' => 1, 'permissions' => ['sales_add', 'sales_view']]);

    $user = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'email_verified_at' => now()]);

    $wh1 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH1', 'status' => 1]);
    $wh2 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH2', 'status' => 1]);

    return compact('user', 'wh1', 'wh2');
}

function searchStockItem(int $code): DbItem
{
    return DbItem::create([
        'store_id' => 1,
        'item_name' => 'Search Stock Item ' . $code,
        'item_code' => 'SS-' . $code,
        'sales_price' => 100,
        'purchase_price' => 60,
        'status' => 1,
        'service_bit' => 0,
    ]);
}

/** Call the shared search endpoint and return the single matched item's `stock`. */
function searchStockFetch(User $user, string $code, ?int $warehouseId): float
{
    $query = 'q=' . urlencode($code);
    if ($warehouseId !== null) {
        $query .= '&warehouse_id=' . $warehouseId;
    }

    $response = test()->actingAs($user)->getJson(route('sales.pos.search.items') . '?' . $query);
    $response->assertOk();

    $rows = collect($response->json());
    $match = $rows->firstWhere('item_code', $code);
    expect($match)->not->toBeNull("Search returned no row for {$code}");

    return (float) $match['stock'];
}

// 1. Warehouse-less item, WITH warehouse_id → db_items.stock (not 0)
test('search stock for a warehouse-less item with warehouse_id equals availableStock (db_items.stock)', function () {
    $env = searchStockEnv();
    $item = searchStockItem(1);
    DbItem::withoutGlobalScopes()->where('id', $item->id)->update(['stock' => 15]);

    $fresh = DbItem::find($item->id);
    $canonical = $fresh->availableStock($env['wh1']->id);

    expect($canonical)->toBe(15.0) // sanity: canonical fallback is db_items.stock
        ->and(searchStockFetch($env['user'], 'SS-1', $env['wh1']->id))->toBe($canonical);
});

// 2. Warehouse-less item, WITHOUT warehouse_id → db_items.stock (not 0)
test('search stock for a warehouse-less item without warehouse_id equals availableStock (db_items.stock)', function () {
    $env = searchStockEnv();
    $item = searchStockItem(2);
    DbItem::withoutGlobalScopes()->where('id', $item->id)->update(['stock' => 15]);

    $canonical = DbItem::find($item->id)->availableStock(null);

    expect($canonical)->toBe(15.0)
        ->and(searchStockFetch($env['user'], 'SS-2', null))->toBe($canonical);
});

// 3. Tracked item, WITH warehouse_id → that warehouse's available_qty
test('search stock for a tracked item with warehouse_id equals that warehouse available_qty', function () {
    $env = searchStockEnv();
    $item = searchStockItem(3);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $item->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $item->id, 'available_qty' => 3]);

    $canonical = DbItem::find($item->id)->availableStock($env['wh1']->id);

    expect($canonical)->toBe(7.0)
        ->and(searchStockFetch($env['user'], 'SS-3', $env['wh1']->id))->toBe($canonical);
});

// 4. Tracked item, WITHOUT warehouse_id → SUM(available_qty)
test('search stock for a tracked item without warehouse_id equals the summed available_qty', function () {
    $env = searchStockEnv();
    $item = searchStockItem(4);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $item->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $item->id, 'available_qty' => 3]);

    $canonical = DbItem::find($item->id)->availableStock(null);

    expect($canonical)->toBe(10.0)
        ->and(searchStockFetch($env['user'], 'SS-4', null))->toBe($canonical);
});

// 5. Parity guard: search stock MUST equal the checkout gate's value for the same item+warehouse.
test('search stock equals the value the checkout gate would use for the same item and warehouse', function () {
    $env = searchStockEnv();

    // Tracked item: 7 + 3 across two warehouses.
    $tracked = searchStockItem(5);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh1']->id, 'item_id' => $tracked->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $env['wh2']->id, 'item_id' => $tracked->id, 'available_qty' => 3]);

    // Warehouse-less item.
    $less = searchStockItem(6);
    DbItem::withoutGlobalScopes()->where('id', $less->id)->update(['stock' => 15]);

    $checkoutValue = fn (int $itemId) => DbItem::with('warehouseItems')->find($itemId)->availableStock($env['wh1']->id);

    expect(searchStockFetch($env['user'], 'SS-5', $env['wh1']->id))->toBe($checkoutValue($tracked->id))
        ->and(searchStockFetch($env['user'], 'SS-6', $env['wh1']->id))->toBe($checkoutValue($less->id));
});
