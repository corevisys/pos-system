<?php

namespace Tests\Feature;

use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2SettingsTogglesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $warehouse;
    protected $customer;
    protected $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = \App\Models\DbCurrency::create([
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $this->store = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Corevisys Test Store',
            'mobile' => '01700000000',
            'email' => 'store@corevisys.com',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currency->id,
            'decimals' => 2,
            'qty_decimals' => 2,
            'currency_placement' => 'before',
            'round_off' => 0,
            'previous_balance_bit' => 0,
            'number_to_words' => 1,
            'change_return' => 1,
            't_and_c_status' => 1,
            'invoice_terms' => 'Standard terms apply.',
            'sales_invoice_footer_text' => 'Thank you for shopping.',
        ]);

        \App\Models\DbRole::create([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        \App\Models\DbPermission::create([
            'role_id' => 1,
            'store_id' => 1,
            'permissions' => json_encode(['sales' => ['view', 'create', 'edit', 'delete'], 'settings' => ['view', 'edit']]),
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'store_id' => 1,
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Main Warehouse',
            'mobile' => '01711111111',
            'status' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Rahim Ahmed',
            'mobile' => '01811111111',
            'customer_type' => 'regular',
            'opening_balance' => 500.00,
            'status' => 1,
        ]);

        $this->item = DbItem::create([
            'store_id' => 1,
            'item_code' => 'ITM-001',
            'item_name' => 'Wireless Mouse',
            'sales_price' => 100.40,
            'purchase_price' => 70.00,
            'stock' => 50,
            'status' => 1,
        ]);

        store_settings(true);
    }

    public function test_pos_store_with_round_off_enabled_rounds_grand_total_and_saves_round_off()
    {
        $this->store->update(['round_off' => 1]);
        store_settings(true);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 100.40,
            'grand_total' => 100.40,
            'paid_amount' => 100.00,
            'cart' => [
                [
                    'id' => $this->item->id,
                    'name' => $this->item->item_name,
                    'price' => 100.40,
                    'qty' => 1,
                    'total' => 100.40,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson(route('sales.pos.store'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $saleId = $response->json('sale_id');
        $sale = DbSale::find($saleId);

        $this->assertNotNull($sale);
        $this->assertEquals(100.00, (float) $sale->grand_total);
        $this->assertEquals(-0.40, (float) $sale->round_off);
    }

    public function test_pos_store_with_round_off_disabled_keeps_exact_grand_total()
    {
        $this->store->update(['round_off' => 0]);
        store_settings(true);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 100.40,
            'grand_total' => 100.40,
            'paid_amount' => 100.40,
            'cart' => [
                [
                    'id' => $this->item->id,
                    'name' => $this->item->item_name,
                    'price' => 100.40,
                    'qty' => 1,
                    'total' => 100.40,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)->postJson(route('sales.pos.store'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $saleId = $response->json('sale_id');
        $sale = DbSale::find($saleId);

        $this->assertNotNull($sale);
        $this->assertEquals(100.40, (float) $sale->grand_total);
        $this->assertEquals(0.00, (float) $sale->round_off);
    }

    public function test_invoice_shows_previous_balance_when_previous_balance_bit_enabled()
    {
        $this->store->update(['previous_balance_bit' => 1]);
        store_settings(true);

        // First sale
        $sale1 = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00001',
            'sales_date' => '2026-08-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 200.00,
            'grand_total' => 200.00,
            'paid_amount' => 50.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        DbSalePayment::create([
            'store_id' => $this->store->id,
            'sales_id' => $sale1->id,
            'customer_id' => $this->customer->id,
            'payment_date' => '2026-08-01',
            'payment' => 50.00,
            'payment_type' => 'Cash',
        ]);

        // Second sale
        $sale2 = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00002',
            'sales_date' => '2026-08-02',
            'customer_id' => $this->customer->id,
            'subtotal' => 300.00,
            'grand_total' => 300.00,
            'paid_amount' => 100.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        DbSalePayment::create([
            'store_id' => $this->store->id,
            'sales_id' => $sale2->id,
            'customer_id' => $this->customer->id,
            'payment_date' => '2026-08-02',
            'payment' => 100.00,
            'payment_type' => 'Cash',
        ]);

        // Invoice view for sale 2 should show opening balance (500) + sale1 due (150) = 650
        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale2->id]));
        $response->assertStatus(200);
        $response->assertSee('PREVIOUS DUE:');
        $response->assertSee('TOTAL DUE:');
        $response->assertSee('650.00'); // Previous due
        $response->assertSee('850.00'); // Total due (650 previous + 200 current due)
    }

    public function test_invoice_hides_previous_balance_when_previous_balance_bit_disabled()
    {
        $this->store->update(['previous_balance_bit' => 0]);
        store_settings(true);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00001',
            'sales_date' => '2026-08-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 200.00,
            'grand_total' => 200.00,
            'paid_amount' => 50.00,
            'payment_status' => 'Partial',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale->id]));
        $response->assertStatus(200);
        $response->assertDontSee('PREVIOUS DUE:');
        $response->assertDontSee('TOTAL DUE:');
    }

    public function test_invoice_renders_amount_in_words_when_number_to_words_enabled()
    {
        $this->store->update(['number_to_words' => 1]);
        store_settings(true);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00001',
            'sales_date' => '2026-08-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale->id]));
        $response->assertStatus(200);
        $response->assertSee('Amount In Words (Invoicing Currency)');
        $response->assertSee('Five Hundred Taka Only');
    }

    public function test_invoice_hides_amount_in_words_when_number_to_words_disabled()
    {
        $this->store->update(['number_to_words' => 0]);
        store_settings(true);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00001',
            'sales_date' => '2026-08-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale->id]));
        $response->assertStatus(200);
        $response->assertDontSee('Amount In Words (Invoicing Currency)');
        $response->assertDontSee('Five Hundred Taka Only');
    }

    public function test_invoice_shows_change_return_when_enabled_and_paid_exceeds_total()
    {
        $this->store->update(['change_return' => 1]);
        store_settings(true);

        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'sales_code' => 'SA-00001',
            'sales_date' => '2026-08-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 150.00,
            'grand_total' => 150.00,
            'paid_amount' => 200.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        DbSalePayment::create([
            'store_id' => $this->store->id,
            'sales_id' => $sale->id,
            'customer_id' => $this->customer->id,
            'payment_date' => '2026-08-01',
            'payment' => 200.00,
            'payment_type' => 'Cash',
        ]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale->id]));
        $response->assertStatus(200);
        $response->assertSee('CHANGE RETURN:');
        $response->assertSee('50.00');
    }

    public function test_store_settings_update_succeeds_without_obsolete_fields()
    {
        $payload = [
            'store_name' => 'Updated Corevisys Store',
            'mobile' => '01799999999',
            'email' => 'admin@corevisys.com',
            'city' => 'Chittagong',
            'currency_id' => $this->currency->id,
            'round_off' => 1,
            'number_to_words' => 0,
            'change_return' => 1,
            'previous_balance_bit' => 1,
            'sales_invoice_footer_text' => 'New footer text',
            't_and_c_status' => 1,
            'invoice_terms' => 'New terms',
        ];

        $response = $this->actingAs($this->user)->post(route('settings.store.update'), $payload);
        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');

        $this->store->refresh();
        $this->assertEquals('Updated Corevisys Store', $this->store->store_name);
        $this->assertEquals(1, $this->store->round_off);
        $this->assertEquals(0, $this->store->number_to_words);
        $this->assertEquals(1, $this->store->change_return);
        $this->assertEquals(1, $this->store->previous_balance_bit);
    }
}
