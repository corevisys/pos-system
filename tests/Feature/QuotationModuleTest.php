<?php

namespace Tests\Feature;

use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbQuotation;
use App\Models\DbQuotationItem;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $warehouse;
    protected $customer;
    protected $tax10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Quotation Test Store',
            'status' => 1,
            'mobile' => '+8801700000000',
            'quotation_init' => 'QU',
            'sales_init' => 'SA',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 1], [
            'store_id' => 1,
            'permissions' => [
                'quotation_view',
                'quotation_add',
                'quotation_edit',
                'quotation_delete',
                'sales_view',
                'sales_add',
            ],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->warehouse = DbWarehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'customer_name' => 'Regular Customer',
            'mobile' => '01711111111',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->tax10 = DbTax::create([
            'tax_name' => 'VAT 10%',
            'tax' => 10,
            'status' => 1,
            'store_id' => 1,
        ]);
    }

    /**
     * A1: Server honors user-entered custom/negotiated price instead of catalog price.
     */
    public function test_a1_server_honors_user_entered_custom_price(): void
    {
        $item = DbItem::create([
            'item_name' => 'Custom Price Item',
            'item_code' => 'ITM-001',
            'sales_price' => 500.00,
            'purchase_price' => 300.00,
            'tax_type' => 'Exclusive',
            'tax_id' => null,
            'status' => 1,
            'store_id' => 1,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 450.00, // Negotiated lower price entered by user
                    'discount' => 0,
                ],
            ],
            'discount_type' => 'Fixed',
            'discount_on_all' => 0,
            'other_charges_input' => 0,
            'round_off' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson(route('quotation.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $quotation = DbQuotation::latest()->first();
        $this->assertNotNull($quotation);
        $this->assertEquals(900.00, (float) $quotation->grand_total);

        $quotationItem = $quotation->items->first();
        $this->assertEquals(450.00, (float) $quotationItem->price_per_unit, 'User entered price must be honored');
        $this->assertEquals(900.00, (float) $quotationItem->total_cost);
    }

    /**
     * A2: Server honors item-level discounts and persists them on db_quotationitems.
     */
    public function test_a2_server_honors_item_level_discounts(): void
    {
        $item = DbItem::create([
            'item_name' => 'Line Discount Item',
            'item_code' => 'ITM-002',
            'sales_price' => 200.00,
            'purchase_price' => 100.00,
            'tax_type' => 'Exclusive',
            'tax_id' => null,
            'status' => 1,
            'store_id' => 1,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_date' => date('Y-m-d'),
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 3,
                    'price' => 200.00, // 3 * 200 = 600
                    'discount' => 50.00, // line discount = 50 -> net = 550
                ],
            ],
            'discount_type' => 'Fixed',
            'discount_on_all' => 0,
            'other_charges_input' => 0,
            'round_off' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson(route('quotation.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $quotation = DbQuotation::latest()->first();
        $this->assertEquals(550.00, (float) $quotation->grand_total);

        $qItem = $quotation->items->first();
        $this->assertEquals(50.00, (float) $qItem->discount_amt);
        $this->assertEquals(50.00, (float) $qItem->discount_input);
        $this->assertEquals(550.00, (float) $qItem->total_cost);
    }

    /**
     * A3: Inclusive vs Exclusive tax formulas compute accurately.
     */
    public function test_a3_inclusive_and_exclusive_tax_calculation(): void
    {
        $incItem = DbItem::create([
            'item_name' => 'Inclusive Item',
            'item_code' => 'ITM-INC',
            'sales_price' => 110.00,
            'purchase_price' => 50.00,
            'tax_type' => 'Inclusive',
            'tax_id' => $this->tax10->id, // 10%
            'status' => 1,
            'store_id' => 1,
        ]);

        $excItem = DbItem::create([
            'item_name' => 'Exclusive Item',
            'item_code' => 'ITM-EXC',
            'sales_price' => 100.00,
            'purchase_price' => 50.00,
            'tax_type' => 'Exclusive',
            'tax_id' => $this->tax10->id, // 10%
            'status' => 1,
            'store_id' => 1,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_date' => date('Y-m-d'),
            'cart' => [
                [
                    'item_id' => $incItem->id,
                    'qty' => 1,
                    'price' => 110.00,
                    'discount' => 0,
                ],
                [
                    'item_id' => $excItem->id,
                    'qty' => 1,
                    'price' => 100.00,
                    'discount' => 0,
                ],
            ],
            'discount_type' => 'Fixed',
            'discount_on_all' => 0,
            'other_charges_input' => 0,
            'round_off' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson(route('quotation.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $quotation = DbQuotation::latest()->first();
        // incItem line: total = 110 (tax backed out = 10)
        // excItem line: total = 100 + 10% = 110 (tax added on top = 10)
        // grand total = 110 + 110 = 220
        $this->assertEquals(220.00, (float) $quotation->grand_total);

        $incLine = $quotation->items->where('item_id', $incItem->id)->first();
        $this->assertEquals(10.00, (float) $incLine->tax_amt);
        $this->assertEquals(110.00, (float) $incLine->total_cost);

        $excLine = $quotation->items->where('item_id', $excItem->id)->first();
        $this->assertEquals(10.00, (float) $excLine->tax_amt);
        $this->assertEquals(110.00, (float) $excLine->total_cost);
    }

    /**
     * A4: Percentage global discount base applies to subtotal consistently.
     */
    public function test_a4_percentage_global_discount(): void
    {
        $item = DbItem::create([
            'item_name' => 'Global Discount Item',
            'item_code' => 'ITM-004',
            'sales_price' => 100.00,
            'purchase_price' => 50.00,
            'tax_type' => 'Exclusive',
            'tax_id' => $this->tax10->id, // 10%
            'status' => 1,
            'store_id' => 1,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_date' => date('Y-m-d'),
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 100.00, // subtotal = 200, tax = 20, item total = 220
                    'discount' => 0,
                ],
            ],
            'discount_type' => 'Percentage',
            'discount_on_all' => 10, // 10% on subtotal 200 = 20 discount
            'other_charges_input' => 0,
            'round_off' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson(route('quotation.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $quotation = DbQuotation::latest()->first();
        // item cost 220 - global discount 20 = 200
        $this->assertEquals(20.00, (float) $quotation->tot_discount_to_all_amt);
        $this->assertEquals(200.00, (float) $quotation->grand_total);
    }

    /**
     * A: update() method recalculates custom price, line discount, and tax correctly.
     */
    public function test_a_update_recalculates_honoring_price_and_discount(): void
    {
        $item = DbItem::create([
            'item_name' => 'Editable Item',
            'item_code' => 'ITM-EDIT',
            'sales_price' => 300.00,
            'purchase_price' => 150.00,
            'tax_type' => 'Exclusive',
            'tax_id' => $this->tax10->id, // 10%
            'status' => 1,
            'store_id' => 1,
        ]);

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-TEST-01',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 300,
            'grand_total' => 330,
        ]);

        $updatePayload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_date' => date('Y-m-d'),
            'cart' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'price' => 250.00, // custom price 250 * 2 = 500
                    'discount' => 50.00, // net = 450, tax 10% on 450 = 45, total = 495
                ],
            ],
            'discount_type' => 'Fixed',
            'discount_on_all' => 0,
            'other_charges_input' => 0,
            'round_off' => 0,
        ];

        $response = $this->actingAs($this->user)->postJson(route('quotation.update', $quotation->id), $updatePayload);
        $response->assertOk()->assertJson(['success' => true]);

        $quotation->refresh();
        $this->assertEquals(495.00, (float) $quotation->grand_total);
        $this->assertEquals(500.00, (float) $quotation->subtotal);

        $line = $quotation->items->first();
        $this->assertEquals(250.00, (float) $line->price_per_unit);
        $this->assertEquals(50.00, (float) $line->discount_amt);
        $this->assertEquals(45.00, (float) $line->tax_amt);
        $this->assertEquals(495.00, (float) $line->total_cost);
    }

    /**
     * B3: Delete action works on standard quotations and is blocked on Converted quotations.
     */
    public function test_b3_delete_action_and_converted_guard(): void
    {
        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-DEL-01',
            'quotation_date' => date('Y-m-d'),
            'quotation_status' => 'Quoted',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $item = DbItem::create([
            'item_name' => 'Delete Test Item',
            'item_code' => 'ITM-DEL',
            'sales_price' => 100,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'quotation_qty' => 1,
            'price_per_unit' => 100,
            'total_cost' => 100,
        ]);

        // Standard delete succeeds
        $response = $this->actingAs($this->user)->deleteJson(route('quotation.delete', $quotation->id));
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('db_quotation', ['id' => $quotation->id]);
        $this->assertDatabaseMissing('db_quotationitems', ['quotation_id' => $quotation->id]);

        // Converted quotation delete is rejected
        $convertedQuotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-DEL-02',
            'quotation_date' => date('Y-m-d'),
            'quotation_status' => 'Converted',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $blockResponse = $this->actingAs($this->user)->deleteJson(route('quotation.delete', $convertedQuotation->id));
        $blockResponse->assertStatus(422);
        $this->assertDatabaseHas('db_quotation', ['id' => $convertedQuotation->id]);
    }

    /**
     * B4: Status lifecycle transitions and render-time expiration.
     */
    public function test_b4_status_lifecycle_and_expiration(): void
    {
        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-STAT-01',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+7 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $this->assertEquals('Quoted', $quotation->effective_status);
        $this->assertFalse($quotation->isExpired());

        // Update to Accepted
        $resp = $this->actingAs($this->user)->postJson(route('quotation.status.update', $quotation->id), [
            'status' => 'Accepted',
        ]);
        $resp->assertOk()->assertJson(['success' => true, 'status' => 'Accepted']);
        $quotation->refresh();
        $this->assertEquals('Accepted', $quotation->quotation_status);

        // Update to Rejected
        $resp = $this->actingAs($this->user)->postJson(route('quotation.status.update', $quotation->id), [
            'status' => 'Rejected',
        ]);
        $resp->assertOk()->assertJson(['success' => true, 'status' => 'Rejected']);
        $quotation->refresh();
        $this->assertEquals('Rejected', $quotation->quotation_status);

        // Render-time Expiration check (without mutating DB stored state)
        $quotation->expire_date = date('Y-m-d', strtotime('-2 days'));
        $quotation->save();
        $quotation->refresh();

        $this->assertTrue($quotation->isExpired());
        $this->assertEquals('Expired', $quotation->effective_status);
        $this->assertEquals('Rejected', $quotation->quotation_status, 'Stored state remains intact');
    }

    /**
     * B5: Store Scoping prevents cross-tenant data leaks.
     */
    public function test_b5_store_scoping(): void
    {
        $store2 = DbStore::create([
            'id' => 2,
            'store_name' => 'Second Store',
            'status' => 1,
            'mobile' => '01800000000',
        ]);

        $userStore2 = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 2,
            'status' => 1,
        ]);

        $quotationStore1 = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-STORE-1',
            'quotation_date' => date('Y-m-d'),
            'quotation_status' => 'Quoted',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        // User from store 2 cannot view quotation from store 1 in list
        $resp = $this->actingAs($userStore2)->get(route('quotation.list'));
        $resp->assertOk();
        $resp->assertDontSee('QU-STORE-1');

        // User from store 2 cannot delete quotation from store 1
        $deleteResp = $this->actingAs($userStore2)->deleteJson(route('quotation.delete', $quotationStore1->id));
        $deleteResp->assertStatus(404);
        $this->assertDatabaseHas('db_quotation', ['id' => $quotationStore1->id]);
    }

    /**
     * C1: Convert to sale creates DbSale, DbSaleItem, decrements stock, and sets status to Converted.
     */
    public function test_c1_convert_to_sale_end_to_end(): void
    {
        $item = DbItem::create([
            'item_name' => 'Convertible Item',
            'item_code' => 'ITM-CONV',
            'sales_price' => 150.00,
            'purchase_price' => 80.00,
            'stock' => 20,
            'tax_type' => 'Exclusive',
            'tax_id' => null,
            'status' => 1,
            'store_id' => 1,
        ]);

        $whItem = DbWarehouseItem::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $item->id,
            'available_qty' => 20,
            'store_id' => 1,
        ]);

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-CONV-01',
            'reference_no' => 'REF-CONV-99',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+10 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 300.00,
            'grand_total' => 300.00,
            'quotation_note' => 'Conversion test note',
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'quotation_qty' => 2,
            'price_per_unit' => 150.00,
            'tax_amt' => 0,
            'discount_amt' => 0,
            'unit_total_cost' => 150.00,
            'total_cost' => 300.00,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('quotation.convert', $quotation->id));
        $response->assertOk()->assertJson(['success' => true]);

        $quotation->refresh();
        $this->assertEquals('Converted', $quotation->quotation_status);

        // Assert DbSale was created with quotation_id linked
        $sale = DbSale::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(300.00, (float) $sale->grand_total);
        $this->assertEquals('REF-CONV-99', $sale->reference_no);
        $this->assertEquals('Unpaid', $sale->payment_status);

        // Assert DbSaleItem was created
        $saleItem = $sale->items->first();
        $this->assertNotNull($saleItem);
        $this->assertEquals(2, (float) $saleItem->sales_qty);
        $this->assertEquals(150.00, (float) $saleItem->price_per_unit);

        // Assert stock was decremented by 2
        $item->refresh();
        $whItem->refresh();
        $this->assertEquals(18, (float) $item->stock);
        $this->assertEquals(18, (float) $whItem->available_qty);

        // Assert Eloquent relationships work bidirectionally
        $this->assertEquals($sale->id, $quotation->sale->id);
        $this->assertEquals($quotation->id, $sale->quotation->id);
    }

    /**
     * C1-ii: Double-conversion is rejected and stock is decremented ONLY once.
     */
    public function test_c1_ii_double_conversion_is_rejected(): void
    {
        $item = DbItem::create([
            'item_name' => 'Double Convert Item',
            'item_code' => 'ITM-DBL',
            'sales_price' => 100.00,
            'stock' => 10,
            'status' => 1,
            'store_id' => 1,
        ]);

        $whItem = DbWarehouseItem::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $item->id,
            'available_qty' => 10,
            'store_id' => 1,
        ]);

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-DBL-01',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+5 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 200.00,
            'grand_total' => 200.00,
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'quotation_qty' => 2,
            'price_per_unit' => 100.00,
            'total_cost' => 200.00,
            'status' => 1,
        ]);

        // First conversion succeeds
        $firstResp = $this->actingAs($this->user)->postJson(route('quotation.convert', $quotation->id));
        $firstResp->assertOk()->assertJson(['success' => true]);

        // Second conversion MUST fail with 422
        $secondResp = $this->actingAs($this->user)->postJson(route('quotation.convert', $quotation->id));
        $secondResp->assertStatus(422);

        // Stock must have decremented exactly once (10 - 2 = 8, not 6)
        $item->refresh();
        $whItem->refresh();
        $this->assertEquals(8, (float) $item->stock);
        $this->assertEquals(8, (float) $whItem->available_qty);

        // Only one DbSale created
        $this->assertEquals(1, DbSale::where('quotation_id', $quotation->id)->count());
    }

    /**
     * C1-iii: Insufficient warehouse stock rejects conversion with informative message.
     */
    public function test_c1_iii_insufficient_stock_rejects_conversion(): void
    {
        $item = DbItem::create([
            'item_name' => 'Low Stock Item',
            'item_code' => 'ITM-LOW',
            'sales_price' => 100.00,
            'stock' => 1,
            'status' => 1,
            'store_id' => 1,
        ]);

        $whItem = DbWarehouseItem::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $item->id,
            'available_qty' => 1, // only 1 available
            'store_id' => 1,
        ]);

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-LOW-01',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+5 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 500.00,
            'grand_total' => 500.00,
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'quotation_qty' => 5, // needs 5
            'price_per_unit' => 100.00,
            'total_cost' => 500.00,
            'status' => 1,
        ]);

        $resp = $this->actingAs($this->user)->postJson(route('quotation.convert', $quotation->id));
        $resp->assertStatus(422);

        // Stock remained 1, quotation remained Quoted
        $item->refresh();
        $whItem->refresh();
        $quotation->refresh();
        $this->assertEquals(1, (float) $whItem->available_qty);
        $this->assertEquals('Quoted', $quotation->quotation_status);
        $this->assertEquals(0, DbSale::where('quotation_id', $quotation->id)->count());
    }

    /**
     * C1-iv: Expired quotation cannot be converted to sale.
     */
    public function test_c1_iv_expired_quotation_cannot_be_converted(): void
    {
        $item = DbItem::create([
            'item_name' => 'Expired Item',
            'item_code' => 'ITM-EXP',
            'sales_price' => 100.00,
            'stock' => 10,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbWarehouseItem::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $item->id,
            'available_qty' => 10,
            'store_id' => 1,
        ]);

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'quotation_code' => 'QU-EXP-01',
            'quotation_date' => date('Y-m-d', strtotime('-10 days')),
            'expire_date' => date('Y-m-d', strtotime('-1 day')), // expired
            'quotation_status' => 'Quoted',
            'subtotal' => 100.00,
            'grand_total' => 100.00,
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'quotation_qty' => 1,
            'price_per_unit' => 100.00,
            'total_cost' => 100.00,
            'status' => 1,
        ]);

        $resp = $this->actingAs($this->user)->postJson(route('quotation.convert', $quotation->id));
        $resp->assertStatus(422);

        $quotation->refresh();
        $this->assertEquals('Quoted', $quotation->quotation_status);
        $this->assertEquals(0, DbSale::where('quotation_id', $quotation->id)->count());
    }
}


