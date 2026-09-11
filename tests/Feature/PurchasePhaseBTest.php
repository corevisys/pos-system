<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchasePayment;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePhaseBTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected DbSupplier $supplier;
    protected AcAccount $account;

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
            'store_id' => 1,
            'warehouse_name' => 'Main Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Supplier Beta',
            'supplier_code' => 'SUP-002',
            'mobile' => '01722222222',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Cash Drawer',
            'account_number' => 'ACC-002',
            'balance' => 10000.00,
            'status' => 1,
        ]);
    }

    public function test_b1_edit_purchase_payment_creates_ledger_and_updates_payable()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-PAY',
            'item_name' => 'Payable Test Item',
            'purchase_price' => 100.00,
            'sales_price' => 150.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // 1. Create Purchase: 1 item @ 100. Paid: 20. Remaining payable: 80.
        $createRes = $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 20.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 1,
                    'price' => 100.00,
                ]
            ]
        ]);
        $createRes->assertStatus(200);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertEquals(20.0, (float) $purchase->paid_amount);
        $this->assertEquals(100.0, (float) $purchase->grand_total);
        $this->assertEquals('Partial', $purchase->payment_status);

        // Account balance decremented by 20 -> 9980
        $this->account->refresh();
        $this->assertEquals(9980.00, (float) $this->account->balance);

        // PURCHASE PAYABLE credit_amt should be 80
        $payable = AcTransaction::where('payment_code', $purchase->purchase_code)
            ->where('transaction_type', 'PURCHASE PAYABLE')
            ->first();
        $this->assertNotNull($payable);
        $this->assertEquals(80.00, (float) $payable->credit_amt);

        // 2. Edit Purchase: Add 30 payment (amount_paid = 30)
        $editRes = $this->actingAs($this->user)->postJson(route('purchase.update', $purchase->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 30.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'purchase_price' => 100.00,
                ]
            ]
        ]);
        $editRes->assertStatus(200);

        $purchase->refresh();
        // Total paid is now 20 + 30 = 50
        $this->assertEquals(50.0, (float) $purchase->paid_amount);
        $this->assertEquals('Partial', $purchase->payment_status);

        // Assert new DbPurchasePayment record exists for 30
        $this->assertDatabaseHas('db_purchasepayments', [
            'purchase_id' => $purchase->id,
            'payment' => 30.00,
            'account_id' => $this->account->id,
        ]);

        // Assert AcTransaction for PURCHASE PAYMENT exists for 30
        $this->assertDatabaseHas('ac_transactions', [
            'transaction_type' => 'PURCHASE PAYMENT',
            'payment_code' => $purchase->purchase_code,
            'debit_amt' => 30.00,
            'debit_account_id' => $this->account->id,
        ]);

        // Assert Account balance decremented by another 30 -> 9950
        $this->account->refresh();
        $this->assertEquals(9950.00, (float) $this->account->balance);

        // Assert PURCHASE PAYABLE transaction updated to remaining due: 100 - 50 = 50
        $payables = AcTransaction::where('payment_code', $purchase->purchase_code)
            ->where('transaction_type', 'PURCHASE PAYABLE')
            ->get();
        $this->assertCount(1, $payables, 'Exactly one PURCHASE PAYABLE transaction must exist');
        $this->assertEquals(50.00, (float) $payables->first()->credit_amt);
    }

    public function test_b2_purchase_return_refund_syncs_ledger_and_decrements_paid_amount()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-RET',
            'item_name' => 'Return Test Item',
            'purchase_price' => 100.00,
            'sales_price' => 150.00,
            'stock' => 0,
            'status' => 1,
        ]);

        // Create Purchase: 2 items @ 100 = 200. Fully paid = 200.
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
        $this->assertEquals(200.00, (float) $purchase->paid_amount);
        $this->assertEquals('Paid', $purchase->payment_status);

        $initialBalance = (float) $this->account->fresh()->balance;

        // Return 1 unit (subtotal = 100.00). Refund amount should be min(100, 200) = 100.00.
        $returnRes = $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'reference_no' => 'RET-REF-01',
            'items' => [
                [
                    'item_id' => $item->id,
                    'return_qty' => 1,
                ]
            ]
        ]);
        $returnRes->assertStatus(200);

        // 1. Parent purchase paid_amount decremented from 200 by 100 to 100
        $purchase->refresh();
        $this->assertEquals(100.00, (float) $purchase->paid_amount);

        // 2. AcTransaction with PURCHASE RETURN REFUND created with credit_amt 100
        $this->assertDatabaseHas('ac_transactions', [
            'transaction_type' => 'PURCHASE RETURN REFUND',
            'credit_account_id' => $this->account->id,
            'credit_amt' => 100.00,
            'debit_amt' => 0,
        ]);

        // 3. Account balance increased by refund of 100
        $this->assertEquals($initialBalance + 100.00, (float) $this->account->fresh()->balance);
    }

    public function test_b3_returned_serials_marked_as_status_two()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'ITEM-SN',
            'item_name' => 'Serialized Phone',
            'purchase_price' => 500.00,
            'sales_price' => 700.00,
            'is_serialized' => 1,
            'stock' => 0,
            'status' => 1,
        ]);

        // Purchase 2 serialized units
        $this->actingAs($this->user)->postJson(route('purchase.store'), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 500.00,
                    'is_serialized' => 1,
                    'serials' => ['SN-ALPHA-01', 'SN-ALPHA-02'],
                ]
            ]
        ]);

        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Both serials start with status = 0 (Available)
        $this->assertEquals(2, DbItemSerial::where('purchase_id', $purchase->id)->where('status', 0)->count());

        // Return 1 unit
        $returnRes = $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'reference_no' => 'RET-SN-01',
            'items' => [
                [
                    'item_id' => $item->id,
                    'return_qty' => 1,
                ]
            ]
        ]);
        $returnRes->assertStatus(200);

        // Exactly 1 serial is marked status = 2 (Returned to Supplier)
        $this->assertEquals(1, DbItemSerial::where('purchase_id', $purchase->id)->where('status', 2)->count());
        // Exactly 1 serial remains status = 0 (Available)
        $this->assertEquals(1, DbItemSerial::where('purchase_id', $purchase->id)->where('status', 0)->count());
    }
}
