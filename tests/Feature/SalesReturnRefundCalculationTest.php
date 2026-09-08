<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbSalesReturn;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnRefundCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $warehouse;
    protected $customer;
    protected $account;
    protected $item1;
    protected $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $language = \App\Models\DbLanguage::firstOrCreate(['id' => 1], [
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

        $role = \App\Models\DbRole::firstOrCreate(['id' => 1], [
            'store_id' => $this->store->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        \App\Models\DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => $this->store->id,
            'permissions' => ['sales_add', 'sales_view', 'sales_return_add', 'sales_return_view', 'pos', 'accounts_view'],
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

        $this->customer = DbCustomer::create([
            'customer_name' => 'Customer A',
            'mobile' => '01711111111',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Cash Drawer',
            'account_number' => 'ACC-001',
            'balance' => 20000.00,
            'status' => 1,
        ]);

        $category = \App\Models\DbCategory::create([
            'category_name' => 'General',
            'category_code' => 'CAT001',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $unit = \App\Models\DbUnit::create([
            'unit_name' => 'Piece',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->item1 = DbItem::create([
            'item_code' => 'ITM001',
            'item_name' => 'Product Alpha',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'price' => 2500.00,
            'sales_price' => 2500.00,
            'purchase_price' => 1500.00,
            'stock' => 50,
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->item2 = DbItem::create([
            'item_code' => 'ITM002',
            'item_name' => 'Product Beta',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'price' => 2500.00,
            'sales_price' => 2500.00,
            'purchase_price' => 1500.00,
            'stock' => 50,
            'store_id' => $this->store->id,
            'status' => 1,
        ]);
    }

    /**
     * Scenario A:
     * Sale: 2 items @ 2500 = 5,000. Customer paid 3,000 (Due = 2,000).
     * Customer returns 1 item (2,500).
     * Due Offset = min(2500, 2000) = 2,000.
     * Max Cash Refund = 2500 - 2000 = 500.
     */
    public function test_scenario_a_partial_return_caps_refund_at_500_and_offsets_due()
    {
        $this->actingAs($this->user);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-SCENARIO-A',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        DbSaleItem::create([
            'sales_id' => $sale->id,
            'item_id' => $this->item1->id,
            'sales_qty' => 1,
            'price_per_unit' => 2500.00,
            'total_cost' => 2500.00,
        ]);

        DbSaleItem::create([
            'sales_id' => $sale->id,
            'item_id' => $this->item2->id,
            'sales_qty' => 1,
            'price_per_unit' => 2500.00,
            'total_cost' => 2500.00,
        ]);

        $initialAccountBalance = (float)$this->account->balance;

        // Submit return for 1 item (Product Alpha, value 2,500) with the allowed cash refund of 500
        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'reference_no' => 'RET-A-001',
            'paid_amount' => 500.00,
            'account_id' => $this->account->id,
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 1,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 2500.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'return_qty' => 0,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 0,
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->account->refresh();
        $sale->refresh();

        // 1. Account balance decreased by exactly 500.00 (not 2,500.00)
        $this->assertEquals($initialAccountBalance - 500.00, (float)$this->account->balance);

        // 2. AcTransaction created for 500.00 debit
        $this->assertDatabaseHas('ac_transactions', [
            'transaction_type' => 'SALES RETURN REFUND',
            'debit_account_id' => $this->account->id,
            'debit_amt' => 500.00,
            'credit_amt' => 0,
        ]);

        // 3. DbSalesReturn recorded with gross total 2500 and cash refund 500
        $this->assertDatabaseHas('db_salesreturn', [
            'sales_id' => $sale->id,
            'grand_total' => 2500.00,
            'paid_amount' => 500.00,
        ]);

        // 4. Sale status transitioned to Paid (the remaining 2500 item is fully covered by the retained 2500 cash)
        $this->assertEquals('Paid', $sale->payment_status);
        $this->assertEquals(1, $sale->return_bit);
    }

    /**
     * Scenario B:
     * Sale: 2 items @ 2500 = 5,000. Customer paid 3,000 (Due = 2,000).
     * Customer returns BOTH items (full return, value = 5,000).
     * Due Offset = min(5000, 2000) = 2,000.
     * Max Cash Refund = 5000 - 2000 = 3,000 (Exactly what was paid, never 5,000).
     */
    public function test_scenario_b_full_return_caps_refund_at_paid_amount_3000()
    {
        $this->actingAs($this->user);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-SCENARIO-B',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        $initialAccountBalance = (float)$this->account->balance;

        // Submit return for BOTH items with max cash refund of 3000
        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'paid_amount' => 3000.00,
            'account_id' => $this->account->id,
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 1,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 2500.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'return_qty' => 1,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 2500.00,
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->account->refresh();
        $sale->refresh();

        // 1. Account balance decreased by 3,000.00 (not 5,000.00)
        $this->assertEquals($initialAccountBalance - 3000.00, (float)$this->account->balance);

        // 2. AcTransaction created for 3000.00 debit
        $this->assertDatabaseHas('ac_transactions', [
            'transaction_type' => 'SALES RETURN REFUND',
            'debit_account_id' => $this->account->id,
            'debit_amt' => 3000.00,
        ]);

        // 3. DbSalesReturn recorded
        $this->assertDatabaseHas('db_salesreturn', [
            'sales_id' => $sale->id,
            'grand_total' => 5000.00,
            'paid_amount' => 3000.00,
        ]);

        // 4. Sale is fully settled/cancelled
        $this->assertEquals('Paid', $sale->payment_status);
    }

    /**
     * Scenario C:
     * Sale: 5,000. Paid 1,000. Due = 4,000.
     * Customer returns 1 item worth 1,500 (R < D).
     * Due Offset = min(1500, 4000) = 1,500.
     * Max Cash Refund = max(0, 1500 - 4000) = 0.
     * Cash Outflow = 0. Due reduces from 4,000 to 2,500. Payment Status remains Partial.
     */
    public function test_scenario_c_return_less_than_due_allows_zero_cash_refund()
    {
        $this->actingAs($this->user);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-SCENARIO-C',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        $initialAccountBalance = (float)$this->account->balance;

        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'paid_amount' => 0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 1,
                    'price_per_unit' => 1500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 1500.00,
                ]
            ]
        ]);

        $response->assertStatus(200);

        $this->account->refresh();
        $sale->refresh();

        // Account balance untouched
        $this->assertEquals($initialAccountBalance, (float)$this->account->balance);

        // No refund transactions created
        $this->assertDatabaseMissing('ac_transactions', [
            'transaction_type' => 'SALES RETURN REFUND',
            'note' => 'Refund for Return ' . DbSalesReturn::latest('id')->first()->return_code . ' (Sale #' . $sale->sales_code . ')',
        ]);

        // Sale status remains Partial because 2,500 due still remains on the remaining 3,500 goods
        $this->assertEquals('Partial', $sale->payment_status);
        $this->assertEquals(1, $sale->return_bit);
    }

    /**
     * Scenario D:
     * Fully Paid Sale (D = 0):
     * Sale: 5,000. Paid: 5,000. Due: 0.
     * Return: 2,000.
     * Due Offset = min(2000, 0) = 0.
     * Max Cash Refund = 2,000.
     */
    public function test_scenario_d_fully_paid_sale_allows_full_refund()
    {
        $this->actingAs($this->user);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-SCENARIO-D',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 5000.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        $initialAccountBalance = (float)$this->account->balance;

        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'paid_amount' => 2000.00,
            'account_id' => $this->account->id,
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 1,
                    'price_per_unit' => 2000.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 2000.00,
                ]
            ]
        ]);

        $response->assertStatus(200);

        $this->account->refresh();
        $this->assertEquals($initialAccountBalance - 2000.00, (float)$this->account->balance);
    }

    /**
     * Scenario E:
     * Backend Over-refund Rejection:
     * Sale: 5,000. Paid: 3,000. Due: 2,000.
     * Return: 2,500. (Max Cash Refund = 500).
     * Direct POST attempt with paid_amount = 2,500 is strictly rejected with 422.
     */
    public function test_scenario_e_server_rejects_over_refund_attempt()
    {
        $this->actingAs($this->user);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-SCENARIO-E',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        $initialAccountBalance = (float)$this->account->balance;

        // Attacker or broken client tries to refund 2,500
        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'paid_amount' => 2500.00, // Exceeds max cash refund of 500.00
            'account_id' => $this->account->id,
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 1,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 2500.00,
                ]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('exceeds maximum allowable cash refund', $response->json('message'));

        // Account balance completely untouched
        $this->account->refresh();
        $this->assertEquals($initialAccountBalance, (float)$this->account->balance);

        // No returns or payments created
        $this->assertDatabaseMissing('db_salesreturn', ['sales_id' => $sale->id]);
    }

    /**
     * Edge Case Test:
     * Item has no existing db_warehouseitems row for warehouse 2.
     * When a sales return occurs for warehouse 2, db_warehouseitems is created with the returned quantity.
     */
    public function test_sales_return_safely_creates_warehouse_item_if_missing()
    {
        $this->actingAs($this->user);

        $warehouse2 = DbWarehouse::create([
            'warehouse_name' => 'Secondary Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $warehouse2->id,
            'customer_id' => $this->customer->id,
            'sales_code' => 'SA-WH2-001',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 2500.00,
            'grand_total' => 2500.00,
            'paid_amount' => 2500.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        // Ensure no db_warehouseitems exists for item1 in warehouse2
        \App\Models\DbWarehouseItem::where('warehouse_id', $warehouse2->id)
            ->where('item_id', $this->item1->id)
            ->delete();

        $this->assertDatabaseMissing('db_warehouseitems', [
            'warehouse_id' => $warehouse2->id,
            'item_id' => $this->item1->id,
        ]);

        $response = $this->postJson(route('sales.return.store'), [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'paid_amount' => 2500.00,
            'account_id' => $this->account->id,
            'payment_type' => 'Cash',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'return_qty' => 3,
                    'price_per_unit' => 2500.00,
                    'tax_amt' => 0,
                    'discount_amt' => 0,
                    'total_cost' => 7500.00,
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Confirm db_warehouseitems was created with available_qty = 3
        $this->assertDatabaseHas('db_warehouseitems', [
            'warehouse_id' => $warehouse2->id,
            'item_id' => $this->item1->id,
            'available_qty' => 3.00,
        ]);
    }
}
