<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsFormattingTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected DbCurrency $currency;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::create([
            'id' => 1,
            'store_name' => 'COREVISYS TEST STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'store@corevisys.com',
            'address' => '123 Test Avenue, Dhaka',
            'decimals' => 2,
            'qty_decimals' => 2,
            'currency_placement' => 'before',
        ]);

        $this->currency = DbCurrency::create([
            'currency_name' => 'US Dollar',
            'currency_code' => 'USD',
            'symbol' => '$',
            'status' => 1,
        ]);

        $this->store->update(['currency_id' => $this->currency->id]);
        store_settings(true);

        DbRole::create([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => 1,
            'store_id' => 1,
            'permissions' => ['sales_view', 'sales_add'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
        ]);
    }

    protected function createSale(array $attributes = []): DbSale
    {
        $warehouse = DbWarehouse::create([
            'warehouse_name' => 'Main Test Warehouse',
            'status' => 1,
            'store_id' => 1,
        ]);

        $customer = DbCustomer::create([
            'customer_name' => 'John Doe Corp',
            'mobile' => '+8801811111111',
            'address' => '456 Client Road, Dhaka',
            'status' => 1,
            'store_id' => 1,
        ]);

        $sale = DbSale::create(array_merge([
            'sales_code' => 'SA-TEST-' . rand(1000, 9999),
            'sales_date' => now()->toDateString(),
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $this->user->id,
            'subtotal' => 1000,
            'tax_amt' => 50,
            'discount_amt' => 20,
            'grand_total' => 1030,
            'paid_amount' => 1030,
            'payment_status' => 'Paid',
        ], $attributes));

        $item = DbItem::create([
            'item_name' => 'Test Item',
            'item_code' => 'SKU-001',
            'sales_price' => 1000,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbSaleItem::create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'sales_qty' => 1,
            'price_per_unit' => 1000,
            'total_cost' => 1000,
            'store_id' => 1,
        ]);

        return $sale;
    }

    public function test_format_currency_respects_store_decimals_and_placement_before(): void
    {
        $this->store->update([
            'decimals' => 2,
            'currency_placement' => 'before',
        ]);
        store_settings(true);

        $this->assertEquals('$ 1,234.50', format_currency(1234.5));
        $this->assertEquals('$ 0.00', format_currency(0));
        $this->assertEquals('-$ 50.00', format_currency(-50));
    }

    public function test_format_currency_respects_store_decimals_and_placement_after(): void
    {
        $this->store->update([
            'decimals' => 3,
            'currency_placement' => 'after',
        ]);
        store_settings(true);

        $this->assertEquals('1,234.500 $', format_currency(1234.5));
    }

    public function test_format_currency_with_zero_decimals(): void
    {
        $this->store->update([
            'decimals' => 0,
            'currency_placement' => 'before',
        ]);
        store_settings(true);

        $this->assertEquals('$ 1,235', format_currency(1234.56));
    }

    public function test_format_quantity_respects_qty_decimals(): void
    {
        $this->store->update(['qty_decimals' => 0]);
        store_settings(true);
        $this->assertEquals('15', format_quantity(15.4));

        $this->store->update(['qty_decimals' => 3]);
        store_settings(true);
        $this->assertEquals('15.400', format_quantity(15.4));
    }

    public function test_store_settings_memoization_caches_across_invocations(): void
    {
        $this->store->update(['decimals' => 2]);
        store_settings(true);

        // Fetch once
        $s1 = store_settings();
        $this->assertEquals(2, $s1->decimals);

        // Update directly in DB without clearing helper cache
        DbStore::where('id', $this->store->id)->update(['decimals' => 4]);

        // Memoized instance should still return 2
        $s2 = store_settings();
        $this->assertEquals(2, $s2->decimals);

        // Force reload
        $s3 = store_settings(true);
        $this->assertEquals(4, $s3->decimals);
    }

    public function test_invoice_view_renders_footer_text_and_terms_when_enabled(): void
    {
        $this->store->update([
            'sales_invoice_footer_text' => 'Thank you for choosing CorevisysPOS!',
            'invoice_terms' => 'Items can be returned within 7 days with original receipt.',
            't_and_c_status' => 1,
        ]);
        store_settings(true);

        $sale = $this->createSale(['grand_total' => 100.00]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', $sale->id));
        $response->assertStatus(200);
        $response->assertSee('Thank you for choosing CorevisysPOS!');
        $response->assertSee('Items can be returned within 7 days with original receipt.');
        $response->assertSee('Terms & Conditions:', false);
    }

    public function test_invoice_view_hides_terms_when_disabled(): void
    {
        $this->store->update([
            'sales_invoice_footer_text' => 'Thank you for choosing CorevisysPOS!',
            'invoice_terms' => 'Items can be returned within 7 days with original receipt.',
            't_and_c_status' => 0,
        ]);
        store_settings(true);

        $sale = $this->createSale(['grand_total' => 100.00]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', $sale->id));
        $response->assertStatus(200);
        $response->assertSee('Thank you for choosing CorevisysPOS!');
        $response->assertDontSee('Items can be returned within 7 days with original receipt.');
    }

    public function test_invoice_pdf_renders_with_custom_footer_and_terms(): void
    {
        $this->store->update([
            'sales_invoice_footer_text' => 'PDF Custom Footer Text',
            'invoice_terms' => 'PDF Terms and Conditions 12345',
            't_and_c_status' => 1,
        ]);
        store_settings(true);

        $sale = $this->createSale(['grand_total' => 150.00]);

        $response = $this->actingAs($this->user)->get(route('sales.invoice', ['id' => $sale->id, 'mode' => 'pdf']));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
