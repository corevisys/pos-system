<?php

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPurchasePayment;
use App\Models\DbPurchasePaymentReturn;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;

function purchaseFlowUser(): User
{
    DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Test Store', 'status' => 1]);
    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('purchase, return, and signed stock adjustment keep stock and payment records consistent', function () {
    $user = purchaseFlowUser();
    $warehouse = DbWarehouse::create(['warehouse_name' => 'Flow Warehouse', 'status' => 1]);
    $supplier = DbSupplier::create(['store_id' => 1, 'supplier_name' => 'Flow Supplier', 'status' => 1]);
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Flow Cash',
        'account_code' => 'FLOW-CASH',
        'balance' => 10000,
        'status' => 1,
    ]);
    $category = DbCategory::create(['category_name' => 'Flow Category', 'status' => 1]);
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Flow Item',
        'item_code' => 'FLOW-ITEM',
        'category_id' => $category->id,
        'purchase_price' => 10,
        'price' => 10,
        'sales_price' => 20,
        'stock' => 5,
        'alert_qty' => 1,
        'status' => 1,
    ]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 5]);

    $purchaseResponse = $this->actingAs($user)->postJson(route('purchase.store'), [
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'purchase_date' => now()->toDateString(),
        'discount_type' => 'Fixed',
        'discount_on_all' => 0,
        'other_charges_input' => 0,
        'round_off' => 0,
        'amount_paid' => 15,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'cart' => [[
            'item_id' => $item->id,
            'qty' => 3,
            'price' => 15,
            'discount' => 0,
        ]],
    ]);

    $purchaseResponse->assertOk()->assertJson(['success' => true]);
    $purchaseId = \App\Models\DbPurchase::latest('id')->value('id');
    $item->refresh();
    expect((float) $item->stock)->toBe(8.0)
        ->and((float) DbWarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('available_qty'))->toBe(8.0)
        ->and((float) $item->purchase_price)->toBe(11.88) // weighted avg: (10*5 + 15*3) / 8 = 11.875
        ->and((float) DbPurchasePayment::where('purchase_id', $purchaseId)->value('payment'))->toBe(15.0)
        ->and((float) AcTransaction::where('supplier_id', $supplier->id)->where('transaction_type', 'PURCHASE PAYABLE')->value('credit_amt'))->toBe(30.0);

    $returnResponse = $this->actingAs($user)->postJson(route('purchase.return.store'), [
        'purchase_id' => $purchaseId,
        'return_date' => now()->toDateString(),
        'items' => [[
            'item_id' => $item->id,
            'return_qty' => 1,
            'purchase_price' => 1,
            'tax_id' => null,
        ]],
    ]);

    $returnResponse->assertOk()->assertJson(['success' => true]);
    $item->refresh();
    expect((float) $item->stock)->toBe(7.0)
        ->and((float) DbWarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('available_qty'))->toBe(7.0)
        ->and(DbPurchasePaymentReturn::where('purchase_id', $purchaseId)->value('payment_type'))->toBe('Cash')
        ->and((float) DbPurchasePaymentReturn::where('purchase_id', $purchaseId)->value('payment'))->toBe(15.0);

    $this->actingAs($user)->postJson(route('purchase.return.store'), [
        'purchase_id' => $purchaseId,
        'return_date' => now()->toDateString(),
        'items' => [['item_id' => $item->id, 'return_qty' => 3, 'purchase_price' => 1]],
    ])->assertStatus(422);

    $adjust = fn (float $quantity) => $this->actingAs($user)->postJson(route('stock.adjustment.store'), [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_note' => 'Flow test adjustment',
        'items' => [['item_id' => $item->id, 'quantity' => $quantity, 'description' => 'Flow test reason']],
    ])->assertOk();

    $adjust(2);
    $adjust(-1);
    $item->refresh();
    expect((float) $item->stock)->toBe(8.0)
        ->and((float) DbWarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->value('available_qty'))->toBe(8.0);
});