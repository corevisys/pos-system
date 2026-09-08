<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualLiveVerificationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'CurrencySeeder']);
        $this->artisan('db:seed', ['--class' => 'LanguageSeeder']);
        $this->artisan('db:seed', ['--class' => 'StoreSeeder']);

        \App\Models\DbRole::create([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        \App\Models\DbPermission::create([
            'role_id' => 1,
            'store_id' => 1,
            'permissions' => ['sales_view', 'sales_add', 'items_view'],
        ]);
    }

    protected function createSale(): DbSale
    {
        $warehouse = \App\Models\DbWarehouse::create([
            'warehouse_name' => 'Main Test Warehouse',
            'status' => 1,
            'store_id' => 1,
        ]);

        $customer = \App\Models\DbCustomer::create([
            'customer_name' => 'John Doe Corp',
            'mobile' => '+8801811111111',
            'address' => '456 Client Road, Dhaka',
            'status' => 1,
            'store_id' => 1,
        ]);

        $user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
        ]);

        $sale = DbSale::create([
            'sales_code' => 'SA-TEST-0001',
            'sales_date' => now()->toDateString(),
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'subtotal' => 1000,
            'tax_amt' => 50,
            'discount_amt' => 20,
            'grand_total' => 1030,
            'paid_amount' => 1030,
            'payment_status' => 'Paid',
        ]);

        $item = DbItem::create([
            'item_name' => 'Test Item',
            'item_code' => 'SKU-001',
            'sales_price' => 1000,
            'status' => 1,
            'store_id' => 1,
        ]);

        \App\Models\DbSaleItem::create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'sales_qty' => 1,
            'price_per_unit' => 1000,
            'total_cost' => 1000,
            'store_id' => 1,
        ]);

        return $sale;
    }

    public function test_check1_pdf_and_terms_and_footer(): void
    {
        $store = DbStore::first();
        $store->update([
            'sales_invoice_footer_text' => 'THANK YOU FOR SHOPPING AT COREVISYS POS!',
            'invoice_terms' => "1. Returns accepted within 7 days.\n2. Original receipt required.",
            't_and_c_status' => 1,
        ]);
        store_settings(true);

        $sale = DbSale::first() ?? $this->createSale();
        $user = User::first() ?? User::factory()->create(['role_id' => 1, 'store_id' => 1]);

        $response = $this->actingAs($user)->get("/sales/invoice/{$sale->id}?mode=stream&paper_size=a4");
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $pdfContent = $response->getContent();
        
        echo "\n[CHECK 1 - PDF VERIFICATION]\n";
        echo "- PDF Binary Size: " . strlen($pdfContent) . " bytes\n";
        echo "- Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "- Content-Disposition: " . $response->headers->get('Content-Disposition') . "\n";

        // Also check HTML view for exact text structure and placement
        $htmlResponse = $this->actingAs($user)->get("/sales/invoice/{$sale->id}");
        $html = $htmlResponse->getContent();
        
        $hasFooter = str_contains($html, 'THANK YOU FOR SHOPPING AT COREVISYS POS!');
        $hasTerms = str_contains($html, 'Returns accepted within 7 days.');
        $hasTermsHeader = str_contains($html, 'Terms & Conditions:');

        echo "- Footer Text in HTML Invoice: " . ($hasFooter ? "CONFIRMED VISIBLE" : "NOT FOUND") . "\n";
        echo "- Terms Content in HTML Invoice: " . ($hasTerms ? "CONFIRMED VISIBLE" : "NOT FOUND") . "\n";
        echo "- Terms Box Header in HTML Invoice: " . ($hasTermsHeader ? "CONFIRMED VISIBLE" : "NOT FOUND") . "\n";

        $this->assertTrue($hasFooter);
        $this->assertTrue($hasTerms);
        $this->assertTrue($hasTermsHeader);
    }

    public function test_check2_many_rows_and_memoization_performance(): void
    {
        $user = User::first() ?? User::factory()->create(['role_id' => 1, 'store_id' => 1]);

        for ($i = 1; $i <= 25; $i++) {
            DbItem::create([
                'item_name' => "Performance Test Item #{$i}",
                'item_code' => "PERF-SKU-{$i}",
                'sales_price' => 150.50 + $i,
                'status' => 1,
                'store_id' => 1,
            ]);
        }

        $itemCount = DbItem::count();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $response = $this->actingAs($user)->get('/items/list');
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $queries = DB::getQueryLog();
        $storeQueries = array_filter($queries, function ($q) {
            return str_contains(strtolower($q['query']), 'db_store');
        });

        echo "\n[CHECK 2 - PERFORMANCE & ROWS VERIFICATION]\n";
        echo "- Total Items in DB: {$itemCount}\n";
        echo "- Items List HTTP Status: " . $response->getStatusCode() . "\n";
        echo "- Total Render Time: {$elapsedMs} ms\n";
        echo "- Total Queries Run: " . count($queries) . "\n";
        echo "- DbStore Queries: " . count($storeQueries) . " (Memoized: 1 query)\n";

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThanOrEqual(2, count($storeQueries));
    }

    public function test_check3_decimals_0_immediate_reflection_and_cache_invalidation(): void
    {
        $store = DbStore::first();
        $user = User::first() ?? User::factory()->create(['role_id' => 1]);

        // POST update to /settings/store with decimals = 0
        $response = $this->actingAs($user)->post('/settings/store', [
            'store_name' => $store->store_name,
            'mobile' => $store->mobile ?: '01700000000',
            'email' => $store->email ?: 'store@example.com',
            'city' => $store->city ?: 'Dhaka',
            'currency_id' => $store->currency_id,
            'decimals' => 0,
            'qty_decimals' => 0,
            'currency_placement' => 'before',
        ]);

        $formattedCurrency = format_currency(1234.56);
        $formattedQty = format_quantity(25.75);

        echo "\n[CHECK 3 - DECIMALS = 0 IMMEDIATE CACHE INVALIDATION]\n";
        echo "- StoreSettings POST Response Status: " . $response->getStatusCode() . "\n";
        echo "- format_currency(1234.56) Output: '{$formattedCurrency}'\n";
        echo "- format_quantity(25.75) Output: '{$formattedQty}'\n";

        $symbol = \App\Providers\AppServiceProvider::resolveCurrencySymbol();
        $this->assertEquals("{$symbol} 1,235", $formattedCurrency);
        $this->assertEquals('26', $formattedQty);
    }

    public function test_check4_currency_placement_after_live_flip(): void
    {
        $store = DbStore::first();
        $user = User::first() ?? User::factory()->create(['role_id' => 1]);

        // POST update to /settings/store with currency_placement = 'after'
        $response = $this->actingAs($user)->post('/settings/store', [
            'store_name' => $store->store_name,
            'mobile' => $store->mobile ?: '01700000000',
            'email' => $store->email ?: 'store@example.com',
            'city' => $store->city ?: 'Dhaka',
            'currency_id' => $store->currency_id,
            'decimals' => 2,
            'qty_decimals' => 2,
            'currency_placement' => 'after',
        ]);

        $formattedCurrency = format_currency(1234.50);
        $symbol = \App\Providers\AppServiceProvider::resolveCurrencySymbol();

        echo "\n[CHECK 4 - CURRENCY PLACEMENT 'AFTER' FLIP]\n";
        echo "- StoreSettings POST Response Status: " . $response->getStatusCode() . "\n";
        echo "- Active Currency Symbol: '{$symbol}'\n";
        echo "- format_currency(1234.50) Output: '{$formattedCurrency}'\n";

        $this->assertEquals("1,234.50 {$symbol}", $formattedCurrency);

        // Reset back to clean defaults
        $this->actingAs($user)->post('/settings/store', [
            'store_name' => $store->store_name,
            'mobile' => $store->mobile ?: '01700000000',
            'email' => $store->email ?: 'store@example.com',
            'city' => $store->city ?: 'Dhaka',
            'currency_id' => $store->currency_id,
            'decimals' => 2,
            'qty_decimals' => 2,
            'currency_placement' => 'before',
        ]);
    }
}
