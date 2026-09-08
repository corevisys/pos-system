<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbTax;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePhaseATest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected DbSupplier $supplier;
    protected AcAccount $account;
    protected DbTax $tax10;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $language = DbLanguage::firstOrCreate(['id' => 1], [
            'language' => 'English',
            'code' => 'en',
            'status' => 1,
        ]);

        $this->store = DbStore::create([
            'store_code' => 'ST001',
            'store_name' => 'Main Branch',
            'mobile' => '01700000000',
            'status' => 1,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
            'decimals' => 2,
            'qty_decimals' => 2,
        ]);
        store_settings(true);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => $this->store->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => $this->store->id,
            'permissions' => ['purchase_add', 'purchase_view', 'purchase_edit', 'purchase_delete', 'purchase_return_add', 'purchase_return_view'],
        ]);

        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->warehouse = DbWarehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->supplier = DbSupplier::create([
            'supplier_name' => 'Supplier Alpha',
            'supplier_code' => 'SUP-001',
            'mobile' => '01711111111',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Cash Drawer',
            'account_number' => 'ACC-001',
            'balance' => 50000.00,
            'status' => 1,
        ]);

        $this->tax10 = DbTax::create([
            'tax_name' => 'VAT 10%',
            'tax' => 10.00,
            'status' => 1,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_a1_inclusive_and_exclusive_tax_purchase_full_payment_succeeds()
    {
        // Item 1: Inclusive tax: 2 units @ 100 each. Line total with tax = 200.
        $itemInclusive = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-INCL',
            'item_name' => 'Inclusive Product',
            'purchase_price' => 100.00,
            'sales_price' => 150.00,
            'tax_id' => $this->tax10->id,
            'tax_type' => 'Inclusive',
            'stock' => 0,
            'status' => 1,
        ]);

        // Item 2: Exclusive tax: 1 unit @ 100. Tax = 10, Line total with tax = 110.
        $itemExclusive = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-EXCL',
            'item_name' => 'Exclusive Product',
            'purchase_price' => 100.00,
            'sales_price' => 150.00,
            'tax_id' => $this->tax10->id,
            'tax_type' => 'Exclusive',
            'stock' => 0,
            'status' => 1,
        ]);

        // Expected grand total = 200 (inclusive) + 110 (exclusive) = 310.00
        $expectedGrandTotal = 310.00;

        $response = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => $expectedGrandTotal,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $itemInclusive->id,
                    'qty' => 2,
                    'price' => 100.00,
                    'discount' => 0,
                    'tax_id' => $this->tax10->id,
                    'tax_type' => 'Inclusive',
                ],
                [
                    'item_id' => $itemExclusive->id,
                    'qty' => 1,
                    'price' => 100.00,
                    'discount' => 0,
                    'tax_id' => $this->tax10->id,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($purchase);
        $this->assertEquals($expectedGrandTotal, (float) $purchase->grand_total);
        $this->assertEquals($expectedGrandTotal, (float) $purchase->paid_amount);
        $this->assertEquals('Paid', $purchase->payment_status);
    }

    public function test_a2_weighted_average_cost_on_edit_correctly_subtracts_old_stock()
    {
        // Initial state: Item has 5 units at average cost 10.00
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-WAVG',
            'item_name' => 'WAVG Product',
            'purchase_price' => 10.00,
            'sales_price' => 20.00,
            'profit_margin' => 0,
            'stock' => 5,
            'status' => 1,
        ]);

        // Purchase 3 units at unit cost 15.00
        // Expected new avg cost: (10 * 5 + 15 * 3) / (5 + 3) = 95 / 8 = 11.875 -> 11.88
        $response = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 45.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 3,
                    'price' => 15.00,
                    'discount' => 0,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $response->assertStatus(200);

        $item->refresh();
        $this->assertEquals(8.0, (float) $item->stock);
        $this->assertEquals(11.88, (float) $item->purchase_price);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Now EDIT the purchase: Change quantity from 3 to 4 units at unit cost 15.00
        // Pre-purchase base stock should be: 8 - 3 = 5 units @ cost 10.00
        // New purchase: 4 units @ cost 15.00
        // True weighted average cost: (10 * 5 + 15 * 4) / (5 + 4) = 110 / 9 = 12.222... -> 12.22
        // (With the old bug of adding oldPurchasedQty, it calculated over 8 + 3 = 11 base stock: (10*11 + 15*4)/15 = 170/15 = 11.33)
        $editResponse = $this->actingAs($this->user)->postJson(route('purchase.update', $purchase->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 4,
                    'purchase_price' => 15.00,
                    'discount' => 0,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $editResponse->assertStatus(200);

        $item->refresh();
        $this->assertEquals(9.0, (float) $item->stock);
        // Assert exact mathematically correct weighted average with 2-decimal precision:
        // 5 units @ 10.00 + 3 units @ 15.00 = 11.875 -> rounded in DB to 11.88.
        // Reversal yields: (8 * 11.88 - 45.00) / 5 = 10.01 pre-edit basis.
        // Re-adding 4 units @ 15.00 yields: (5 * 10.01 + 60.00) / 9 = 110.05 / 9 = 12.23.
        $this->assertEquals(12.23, (float) $item->purchase_price);
    }

    public function test_a3_blank_account_rejected_when_amount_paid_is_greater_than_zero()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-ACC',
            'item_name' => 'Account Test Item',
            'purchase_price' => 50.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // Attempt to save purchase with payment > 0 but NO account_id
        $response = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 50.00,
            'payment_type' => 'Cash',
            'account_id' => null, // BLANK ACCOUNT
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'price' => 50.00,
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['account_id']);

        // Attempt with payment = 0 and NO account_id: MUST SUCCEED
        $zeroPayResponse = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'account_id' => null,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'price' => 50.00,
                ]
            ]
        ]);

        $zeroPayResponse->assertStatus(200);
        $zeroPayResponse->assertJson(['success' => true]);
    }

    /**
     * A2 EXACT NUMERIC CORRECTNESS TEST:
     * Validates that editing a purchase accurately reverses the pre-edit basis
     * and reapplies the updated line, resulting in the exact hand-calculated
     * weighted-average cost to 2 decimal places in BOTH directions (increase and decrease).
     */
    public function test_a2_exact_numeric_weighted_average_cost_on_edit_both_directions()
    {
        // --------------------------------------------------------------------
        // SCENARIO A: EDIT TO INCREASE QUANTITY AND UNIT COST
        // --------------------------------------------------------------------
        // Known starting state:
        // Item with Stock = 10, Average Cost = 20.00
        $itemA = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-NUMERIC-A',
            'item_name' => 'Exact Numeric Item A',
            'purchase_price' => 20.00,
            'sales_price' => 35.00,
            'profit_margin' => 0,
            'stock' => 10,
            'status' => 1,
        ]);

        // Purchase #1: 10 units @ 30.00 landed cost
        // Hand-calculation Step 1:
        // Initial Total Value = 10 * 20.00 = 200.00
        // Added Value         = 10 * 30.00 = 300.00
        // New Total Value     = 500.00
        // New Total Stock     = 10 + 10 = 20
        // Hand-calculated expected avg cost = 500.00 / 20 = 25.00
        $storeResA = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 300.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $itemA->id,
                    'qty' => 10,
                    'price' => 30.00,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $storeResA->assertStatus(200);

        $itemA->refresh();
        $this->assertEquals(20.00, (float) $itemA->stock);
        $this->assertEquals(25.00, (float) $itemA->purchase_price);

        $purchaseA = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Edit Purchase #1 to INCREASE: Change line to 15 units @ 32.00
        // Hand-calculation Step 2 (Edit Reversal + Re-add):
        // 1. Reversal:
        //    Old purchased qty = 10 @ 30.00
        //    Remaining stock   = 20 - 10 = 10
        //    Reverted cost     = (20 * 25.00 - 10 * 30.00) / 10 = (500 - 300) / 10 = 200 / 10 = 20.00
        // 2. Re-add:
        //    Effective old stock = 10 @ 20.00 = 200.00
        //    New purchased line  = 15 @ 32.00 = 480.00
        //    Total new value     = 200.00 + 480.00 = 680.00
        //    Total new stock     = 10 + 15 = 25
        //    Hand-calculated expected avg cost = 680.00 / 25 = 27.20
        $editResA = $this->actingAs($this->user)->postJson(route('purchase.update', $purchaseA->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $itemA->id,
                    'quantity' => 15,
                    'purchase_price' => 32.00,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $editResA->assertStatus(200);

        $itemA->refresh();
        $this->assertEquals(25.00, (float) $itemA->stock);
        $this->assertEquals(27.20, (float) $itemA->purchase_price);

        // --------------------------------------------------------------------
        // SCENARIO B: EDIT TO DECREASE QUANTITY AND UNIT COST
        // --------------------------------------------------------------------
        // Known starting state:
        // Item with Stock = 10, Average Cost = 20.00
        $itemB = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-NUMERIC-B',
            'item_name' => 'Exact Numeric Item B',
            'purchase_price' => 20.00,
            'sales_price' => 35.00,
            'profit_margin' => 0,
            'stock' => 10,
            'status' => 1,
        ]);

        // Purchase #2: 10 units @ 30.00 -> stock 20, avg cost 25.00
        $storeResB = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 300.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $itemB->id,
                    'qty' => 10,
                    'price' => 30.00,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $storeResB->assertStatus(200);

        $itemB->refresh();
        $this->assertEquals(20.00, (float) $itemB->stock);
        $this->assertEquals(25.00, (float) $itemB->purchase_price);

        $purchaseB = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Edit Purchase #2 to DECREASE: Change line to 6 units @ 25.00
        // Hand-calculation Step 2 (Edit Reversal + Re-add):
        // 1. Reversal:
        //    Old purchased qty = 10 @ 30.00
        //    Remaining stock   = 20 - 10 = 10
        //    Reverted cost     = 20.00
        // 2. Re-add:
        //    Effective old stock = 10 @ 20.00 = 200.00
        //    New purchased line  = 6 @ 25.00  = 150.00
        //    Total new value     = 200.00 + 150.00 = 350.00
        //    Total new stock     = 10 + 6 = 16
        //    Hand-calculated expected avg cost = 350.00 / 16 = 21.875 -> rounded to 21.88
        $editResB = $this->actingAs($this->user)->postJson(route('purchase.update', $purchaseB->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $itemB->id,
                    'quantity' => 6,
                    'purchase_price' => 25.00,
                    'tax_type' => 'Exclusive',
                ]
            ]
        ]);
        $editResB->assertStatus(200);

        $itemB->refresh();
        $this->assertEquals(16.00, (float) $itemB->stock);
        $this->assertEquals(21.88, (float) $itemB->purchase_price);
    }
}
