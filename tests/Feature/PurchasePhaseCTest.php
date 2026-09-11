<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbLanguage;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchaseItem;
use App\Models\DbPurchasePayment;
use App\Models\DbPurchaseReturn;
use App\Models\DbPurchaseItemReturn;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePhaseCTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbStore $store2;
    protected DbWarehouse $warehouse;
    protected DbSupplier $supplier;
    protected AcAccount $account;
    protected DbPaymentType $paymentType;

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

        $this->store2 = DbStore::create([
            'store_code' => 'ST002',
            'store_name' => 'Second Branch',
            'mobile' => '01799999999',
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
            'store_id' => 1,
            'warehouse_name' => 'Main Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Supplier Gamma',
            'supplier_code' => 'SUP-003',
            'mobile' => '01733333333',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Cash Drawer',
            'account_number' => 'ACC-003',
            'balance' => 20000.00,
            'status' => 1,
        ]);

        $this->paymentType = DbPaymentType::firstOrCreate(['store_id' => 1, 'payment_type' => 'Cash'], [
            'status' => 1,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_c1_purchase_delete_reverses_stock_cost_payments_and_ledger()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-DEL',
            'item_name' => 'Delete Test Item',
            'purchase_price' => 10.00,
            'sales_price' => 20.00,
            'stock' => 5,
            'status' => 1,
        ]);

        // Purchase 3 units @ 15.00 (Total 45). Paid 20.
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 20.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 3,
                    'price' => 15.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($purchase);

        $item->refresh();
        $this->assertEquals(8.0, (float) $item->stock);
        $this->assertEquals(11.88, (float) $item->purchase_price);

        // Account balance decremented by 20 -> 19980
        $this->assertEquals(19980.00, (float) $this->account->fresh()->balance);

        // Delete the purchase
        $delRes = $this->actingAs($this->user)->deleteJson(route('purchase.delete', $purchase->id));
        $delRes->assertStatus(200);
        $delRes->assertJson(['success' => true]);

        // Assert purchase and items deleted
        $this->assertDatabaseMissing('db_purchase', ['id' => $purchase->id]);
        $this->assertDatabaseMissing('db_purchaseitems', ['purchase_id' => $purchase->id]);

        // Assert stock decremented back from 8 to 5
        $item->refresh();
        $this->assertEquals(5.0, (float) $item->stock);

        // Assert weighted-average cost reverted back to pre-purchase basis (10.01 or 10.00)
        $this->assertEquals(10.01, (float) $item->purchase_price);

        // Assert account balance restored: 19980 + 20 = 20000
        $this->assertEquals(20000.00, (float) $this->account->fresh()->balance);

        // Assert payments and ledger entries wiped clean
        $this->assertDatabaseMissing('db_purchasepayments', ['purchase_id' => $purchase->id]);
        $this->assertDatabaseMissing('ac_transactions', ['payment_code' => $purchase->purchase_code]);
    }

    public function test_c1_purchase_delete_blocked_if_returns_exist()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-RET-BLOCK',
            'item_name' => 'Return Block Item',
            'purchase_price' => 50.00,
            'stock' => 0,
            'status' => 1,
        ]);

        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 50.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Create a return
        $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'return_qty' => 1,
                ]
            ]
        ]);

        // Attempt delete purchase -> must be blocked
        $delRes = $this->actingAs($this->user)->deleteJson(route('purchase.delete', $purchase->id));
        $delRes->assertStatus(422);
        $this->assertDatabaseHas('db_purchase', ['id' => $purchase->id]);
    }

    public function test_c2_return_invoice_and_return_delete()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-RET-DEL',
            'item_name' => 'Return Delete Item',
            'purchase_price' => 100.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // Purchase 2 items fully paid (200)
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 200.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 100.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Return 1 item -> refund 100
        $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'return_qty' => 1,
                ]
            ]
        ]);

        $return = DbPurchaseReturn::where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($return);

        // Test C2.a: Return invoice view
        $viewRes = $this->actingAs($this->user)->get(route('purchase.return.invoice', $return->id));
        $viewRes->assertStatus(200);
        $viewRes->assertSee($return->return_code);

        $item->refresh();
        $this->assertEquals(1.0, (float) $item->stock); // 2 - 1 = 1

        // Test C2.b: Delete return
        $delRetRes = $this->actingAs($this->user)->deleteJson(route('purchase.return.delete', $return->id));
        $delRetRes->assertStatus(200);

        // Assert return record deleted
        $this->assertDatabaseMissing('db_purchasereturn', ['id' => $return->id]);

        // Assert item stock restored from 1 to 2
        $item->refresh();
        $this->assertEquals(2.0, (float) $item->stock);

        // Assert purchase paid_amount restored from 100 back to 200
        $purchase->refresh();
        $this->assertEquals(200.00, (float) $purchase->paid_amount);
        $this->assertEquals('Paid', $purchase->payment_status);
    }

    public function test_c3_edit_purchase_blocked_if_return_exists()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-EDIT-GUARD',
            'item_name' => 'Edit Guard Item',
            'purchase_price' => 80.00,
            'stock' => 0,
            'status' => 1,
        ]);

        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 80.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Create return
        $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'return_qty' => 1,
                ]
            ]
        ]);

        // 1. GET edit page should redirect with error
        $editGetRes = $this->actingAs($this->user)->get(route('purchase.edit', $purchase->id));
        $editGetRes->assertRedirect(route('purchase.list'));
        $editGetRes->assertSessionHas('error');

        // 2. POST update should fail
        $editPostRes = $this->actingAs($this->user)->postJson(route('purchase.update', $purchase->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 3,
                    'purchase_price' => 80.00,
                ]
            ]
        ]);
        $editPostRes->assertStatus(500);
        $this->assertStringContainsString('one or more purchase returns', $editPostRes->json('message'));
    }

    public function test_c5_store_scoping_isolates_records()
    {
        // Purchase in store 1
        DbPurchase::create([
            'store_id' => $this->store->id,
            'purchase_code' => 'PUR-ST1-001',
            'purchase_date' => date('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'grand_total' => 500,
            'paid_amount' => 500,
            'payment_status' => 'Paid',
            'status' => 1,
            'created_by' => $this->user->id,
        ]);

        // Purchase in store 2
        DbPurchase::create([
            'store_id' => $this->store2->id,
            'purchase_code' => 'PUR-ST2-002',
            'purchase_date' => date('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'grand_total' => 900,
            'paid_amount' => 900,
            'payment_status' => 'Paid',
            'status' => 1,
            'created_by' => $this->user->id,
        ]);

        // Access purchase list as store 1 user
        $response = $this->actingAs($this->user)->get(route('purchase.list'));
        $response->assertStatus(200);
        $response->assertSee('PUR-ST1-001');
        $response->assertDontSee('PUR-ST2-002');
    }

    public function test_c8_record_payment_stores_payment_and_updates_payable()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-REC-PAY',
            'item_name' => 'Record Payment Item',
            'purchase_price' => 300.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // Create purchase with 0 paid (Total 300, Unpaid)
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'price' => 300.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertEquals(0, (float) $purchase->paid_amount);
        $this->assertEquals('Unpaid', $purchase->payment_status);

        // Record a partial payment of 150 via the new endpoint
        $payRes = $this->actingAs($this->user)->postJson(route('purchase.payment.store', $purchase->id), [
            'payment_date' => date('Y-m-d'),
            'payment_amount' => 150.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'note' => 'Partial installment',
        ]);
        $payRes->assertStatus(200);
        $payRes->assertJson(['success' => true]);

        // Assert purchase paid_amount is 150, payment_status is 'Partial'
        $purchase->refresh();
        $this->assertEquals(150.00, (float) $purchase->paid_amount);
        $this->assertEquals('Partial', $purchase->payment_status);

        // Assert payment record and ledger transaction exist
        $this->assertDatabaseHas('db_purchasepayments', [
            'purchase_id' => $purchase->id,
            'payment' => 150.00,
            'account_id' => $this->account->id,
        ]);

        $this->assertDatabaseHas('ac_transactions', [
            'transaction_type' => 'PURCHASE PAYMENT',
            'payment_code' => $purchase->purchase_code,
            'debit_amt' => 150.00,
            'debit_account_id' => $this->account->id,
        ]);

        // Assert remaining payable is 150
        $payable = AcTransaction::where('payment_code', $purchase->purchase_code)
            ->where('transaction_type', 'PURCHASE PAYABLE')
            ->first();
        $this->assertEquals(150.00, (float) $payable->credit_amt);
    }

    public function test_c1_purchase_delete_blocked_if_serials_sold_and_allows_delete_when_unsold()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-SERIAL-DEL-GUARD',
            'item_name' => 'Serialized Guard Item',
            'purchase_price' => 100.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // 1. Create a purchase with two serialized items
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 100.00,
                    'serials' => ['SN-GUARD-001', 'SN-GUARD-002'],
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $serials = DbItemSerial::where('purchase_id', $purchase->id)->orderBy('id')->get();
        $this->assertCount(2, $serials);
        $this->assertEquals(0, $serials[0]->status);
        $this->assertEquals(0, $serials[1]->status);

        $initialStock = (float) $item->fresh()->stock;
        $initialBalance = (float) $this->account->fresh()->balance;

        // 2. Sell one of the serials through a sale
        $customer = DbCustomer::create([
            'store_id' => $this->store->id,
            'customer_name' => 'Serial Buyer',
            'mobile' => '01711223344',
            'status' => 1,
        ]);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'sales_date' => date('Y-m-d'),
            'sales_status' => 'Final',
            'grand_total' => 150.00,
            'paid_amount' => 150.00,
            'payment_status' => 'Paid',
            'sales_code' => 'SL-GUARD-001',
            'created_by' => $this->user->id,
        ]);

        $serials[0]->update([
            'status' => 1,
            'sale_id' => $sale->id,
        ]);

        // 3. Attempt to delete the purchase -> MUST be blocked with 422
        $delRes = $this->actingAs($this->user)->deleteJson(route('purchase.delete', $purchase->id));
        $delRes->assertStatus(422);
        $delRes->assertJson(['success' => false]);
        $this->assertStringContainsString('serialized item(s) from this purchase have already been sold', $delRes->json('message'));

        // Assert purchase record still exists
        $this->assertDatabaseHas('db_purchase', ['id' => $purchase->id]);

        // Assert stock/cost/ledger are completely untouched
        $this->assertEquals($initialStock, (float) $item->fresh()->stock);
        $this->assertEquals(100.00, (float) $item->fresh()->purchase_price);
        $this->assertEquals($initialBalance, (float) $this->account->fresh()->balance);

        // Assert sold serial's status remains 1 and sale link intact
        $soldSerial = $serials[0]->fresh();
        $this->assertEquals(1, $soldSerial->status);
        $this->assertEquals($sale->id, $soldSerial->sale_id);

        // 4. CONTROL INVERSE: Create another purchase with serials where NONE are sold
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'price' => 100.00,
                    'serials' => ['SN-UNSOLD-CONTROL'],
                ]
            ]
        ]);

        $purchaseUnsold = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotEquals($purchase->id, $purchaseUnsold->id);
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-UNSOLD-CONTROL', 'status' => 0]);

        // Delete the purchase with no sold serials -> MUST succeed
        $delResUnsold = $this->actingAs($this->user)->deleteJson(route('purchase.delete', $purchaseUnsold->id));
        $delResUnsold->assertStatus(200);
        $delResUnsold->assertJson(['success' => true]);

        // Assert purchase and unsold serials are cleanly removed
        $this->assertDatabaseMissing('db_purchase', ['id' => $purchaseUnsold->id]);
        $this->assertDatabaseMissing('db_item_serials', ['serial_number' => 'SN-UNSOLD-CONTROL']);
    }

    public function test_c4_create_return_passes_correct_returned_quantities_per_item()
    {
        $item1 = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-RET-QTY-1',
            'item_name' => 'Return Qty Item 1',
            'purchase_price' => 50.00,
            'stock' => 0,
            'status' => 1,
        ]);

        $item2 = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-RET-QTY-2',
            'item_name' => 'Return Qty Item 2',
            'purchase_price' => 80.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // Create purchase: 10 units of item1, 5 units of item2
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [
                [
                    'item_id' => $item1->id,
                    'qty' => 10,
                    'price' => 50.00,
                ],
                [
                    'item_id' => $item2->id,
                    'qty' => 5,
                    'price' => 80.00,
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Perform a first partial return: 3 units of item1
        $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item1->id,
                    'return_qty' => 3,
                ]
            ]
        ]);

        // Perform a second partial return: 2 MORE units of item1, and 1 unit of item2
        $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [
                [
                    'item_id' => $item1->id,
                    'return_qty' => 2,
                ],
                [
                    'item_id' => $item2->id,
                    'return_qty' => 1,
                ]
            ]
        ]);

        // Total returned: item1 has 3 + 2 = 5 returned; item2 has 1 returned.
        // Now call GET createReturn
        $response = $this->actingAs($this->user)->get(route('purchase.return', $purchase->id));
        $response->assertStatus(200);
        $response->assertViewHas('returnedQuantities');

        $viewReturnedQuantities = $response->viewData('returnedQuantities');
        $this->assertEquals(5, (float) ($viewReturnedQuantities[$item1->id] ?? 0));
        $this->assertEquals(1, (float) ($viewReturnedQuantities[$item2->id] ?? 0));
    }
}
