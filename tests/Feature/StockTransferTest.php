<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbCategory;
use Carbon\Carbon;

function getStockTransferTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Test Store',
        'status' => 1,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['stock_transfer', 'stock_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('stock transfer to a new destination warehouse with no prior stock record correctly initializes quantity', function () {
    $user = getStockTransferTestUser();

    $warehouseFrom = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Source Warehouse A',
        'status' => 1,
        'store_id' => 1,
    ]);

    $warehouseTo = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Destination Warehouse B',
        'status' => 1,
        'store_id' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'General',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Transfer Item 1',
        'item_code' => 'ITM-TR-001',
        'category_id' => $category->id,
        'purchase_price' => 20.00,
        'sales_price' => 40.00,
        'stock' => 100,
        'status' => 1,
        'store_id' => 1,
    ]);

    // Give source warehouse 50 initial qty
    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouseFrom->id,
        'item_id' => $item->id,
        'available_qty' => 50,
    ]);

    // Destination warehouse has NO record at all
    expect(DbWarehouseItem::where('warehouse_id', $warehouseTo->id)->where('item_id', $item->id)->first())->toBeNull();

    $response = $this->actingAs($user)->postJson(route('stock.transfer.store'), [
        'warehouse_from' => $warehouseFrom->id,
        'warehouse_to' => $warehouseTo->id,
        'transfer_date' => Carbon::today()->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 15,
            ]
        ]
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    // Source warehouse should be decremented to 35
    $sourceWh = DbWarehouseItem::where('warehouse_id', $warehouseFrom->id)->where('item_id', $item->id)->first();
    expect((float)$sourceWh->available_qty)->toBe(35.0);

    // Destination warehouse MUST have 15 available_qty (NOT null or 0)
    $destWh = DbWarehouseItem::where('warehouse_id', $warehouseTo->id)->where('item_id', $item->id)->first();
    expect($destWh)->not->toBeNull();
    expect((float)$destWh->available_qty)->toBe(15.0);
});

test('stock transfer to an existing destination warehouse increments stock properly', function () {
    $user = getStockTransferTestUser();

    $warehouseFrom = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Source Warehouse C',
        'status' => 1,
        'store_id' => 1,
    ]);

    $warehouseTo = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Destination Warehouse D',
        'status' => 1,
        'store_id' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'General',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Transfer Item 2',
        'item_code' => 'ITM-TR-002',
        'category_id' => $category->id,
        'purchase_price' => 20.00,
        'sales_price' => 40.00,
        'stock' => 100,
        'status' => 1,
        'store_id' => 1,
    ]);

    // Source has 40, Destination already has 10
    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouseFrom->id,
        'item_id' => $item->id,
        'available_qty' => 40,
    ]);

    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouseTo->id,
        'item_id' => $item->id,
        'available_qty' => 10,
    ]);

    $response = $this->actingAs($user)->postJson(route('stock.transfer.store'), [
        'warehouse_from' => $warehouseFrom->id,
        'warehouse_to' => $warehouseTo->id,
        'transfer_date' => Carbon::today()->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 12,
            ]
        ]
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    // Source warehouse should be decremented to 28
    $sourceWh = DbWarehouseItem::where('warehouse_id', $warehouseFrom->id)->where('item_id', $item->id)->first();
    expect((float)$sourceWh->available_qty)->toBe(28.0);

    // Destination warehouse should be incremented from 10 to 22
    $destWh = DbWarehouseItem::where('warehouse_id', $warehouseTo->id)->where('item_id', $item->id)->first();
    expect((float)$destWh->available_qty)->toBe(22.0);
});

test('stock transfer update to a new destination warehouse initializes destination stock properly', function () {
    $user = getStockTransferTestUser();

    $warehouseFrom = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Source Warehouse E',
        'status' => 1,
        'store_id' => 1,
    ]);

    $warehouseTo1 = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Destination Warehouse F1',
        'status' => 1,
        'store_id' => 1,
    ]);

    $warehouseTo2 = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Destination Warehouse F2 (New)',
        'status' => 1,
        'store_id' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'General',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Transfer Item 3',
        'item_code' => 'ITM-TR-003',
        'category_id' => $category->id,
        'purchase_price' => 20.00,
        'sales_price' => 40.00,
        'stock' => 100,
        'status' => 1,
        'store_id' => 1,
    ]);

    // Initial stock in source = 50
    DbWarehouseItem::create([
        'store_id' => 1,
        'warehouse_id' => $warehouseFrom->id,
        'item_id' => $item->id,
        'available_qty' => 50,
    ]);

    // First store transfer of 10 to warehouseTo1
    $storeResponse = $this->actingAs($user)->postJson(route('stock.transfer.store'), [
        'warehouse_from' => $warehouseFrom->id,
        'warehouse_to' => $warehouseTo1->id,
        'transfer_date' => Carbon::today()->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 10,
            ]
        ]
    ]);
    $storeResponse->assertOk();

    $transfer = \App\Models\DbStockTransfer::latest()->first();

    // Now update transfer: change destination to warehouseTo2 (which has no record yet)
    $updateResponse = $this->actingAs($user)->postJson(route('stock.transfer.update', ['id' => $transfer->id]), [
        'warehouse_from' => $warehouseFrom->id,
        'warehouse_to' => $warehouseTo2->id,
        'transfer_date' => Carbon::today()->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 10,
            ]
        ]
    ]);
    $updateResponse->assertOk();
    $updateResponse->assertJson(['success' => true]);

    // WarehouseTo1 should now be 0 (reverted)
    $f1 = DbWarehouseItem::where('warehouse_id', $warehouseTo1->id)->where('item_id', $item->id)->first();
    expect((float)$f1->available_qty)->toBe(0.0);

    // WarehouseTo2 should now be 10 (initialized properly)
    $f2 = DbWarehouseItem::where('warehouse_id', $warehouseTo2->id)->where('item_id', $item->id)->first();
    expect($f2)->not->toBeNull();
    expect((float)$f2->available_qty)->toBe(10.0);
});

