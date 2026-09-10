<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbCustomer;
use App\Models\DbLanguage;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 1 — Store-scoping regression tests.
 *
 * Proves that Store Settings reads/writes, currency/language activation, the
 * per-store settings cache, and every downstream consumer (invoice header,
 * invoice currency symbol, POS round-off, code prefixes) resolve the CORRECT
 * store rather than always db_store.first().
 */
class StoreSettingsStoreScopingTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $storeA;
    protected DbStore $storeB;
    protected DbCurrency $currencyA;
    protected DbCurrency $currencyB;
    protected DbLanguage $languageA;
    protected DbLanguage $languageB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currencyA = DbCurrency::create([
            'currency_name' => 'Alpha Dollar',
            'currency_code' => 'AAD',
            'currency' => 'Alpha Dollar',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $this->currencyB = DbCurrency::create([
            'currency_name' => 'Beta Euro',
            'currency_code' => 'BEE',
            'currency' => 'Beta Euro',
            'symbol' => '€',
            'status' => 0,
        ]);

        $this->languageA = DbLanguage::create(['language' => 'Alpha Language', 'status' => 1]);
        $this->languageB = DbLanguage::create(['language' => 'Beta Language', 'status' => 0]);

        $this->storeA = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Alpha Store',
            'mobile' => '01700000001',
            'email' => 'alpha@example.com',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currencyA->id,
            'language_id' => $this->languageA->id,
            'sales_init' => 'AAA',
        ]);

        $this->storeB = DbStore::create([
            'id' => 2,
            'store_code' => 'ST0002',
            'store_name' => 'Beta Store',
            'mobile' => '01700000002',
            'email' => 'beta@example.com',
            'city' => 'Chittagong',
            'status' => 1,
            'currency_id' => $this->currencyB->id,
            'language_id' => $this->languageB->id,
            'sales_init' => 'BBB',
        ]);

        $role = DbRole::create([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->userA = User::factory()->create([
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'store_id' => $this->storeA->id,
            'status' => 1,
        ]);

        $this->userB = User::factory()->create([
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'store_id' => $this->storeB->id,
            'status' => 1,
        ]);

        store_settings(true);
    }

    // ─── (a) editing settings for Store A must not touch Store B ──────────────

    public function test_store_a_admin_update_does_not_change_store_b_row(): void
    {
        $response = $this->actingAs($this->userA)->post(route('settings.store.update'), [
            'store_name' => 'Alpha Renamed',
            'mobile' => '01799999999',
            'email' => 'alpha-new@example.com',
            'city' => 'Khulna',
            'currency_id' => $this->currencyA->id,
        ]);

        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');

        $this->assertEquals('Alpha Renamed', $this->storeA->fresh()->store_name);
        $this->assertEquals('01799999999', $this->storeA->fresh()->mobile);

        // Store B is completely untouched
        $this->assertEquals('Beta Store', $this->storeB->fresh()->store_name);
        $this->assertEquals('01700000002', $this->storeB->fresh()->mobile);
        $this->assertEquals('Chittagong', $this->storeB->fresh()->city);
        $this->assertEquals($this->currencyB->id, $this->storeB->fresh()->currency_id);
    }

    public function test_store_b_admin_edits_only_store_b_even_when_store_a_is_first_row(): void
    {
        // Store A is the first row — historically Store B's admin would have
        // overwritten Store A. Assert the opposite now holds.
        $this->actingAs($this->userB)->post(route('settings.store.update'), [
            'store_name' => 'Beta Renamed',
            'mobile' => '01788888888',
            'email' => 'beta-new@example.com',
            'city' => 'Sylhet',
            'currency_id' => $this->currencyB->id,
        ]);

        $this->assertEquals('Beta Renamed', $this->storeB->fresh()->store_name);
        $this->assertEquals('Alpha Store', $this->storeA->fresh()->store_name);
        $this->assertEquals('01700000001', $this->storeA->fresh()->mobile);
    }

    // ─── (b) currency / language activation is store-scoped ───────────────────

    public function test_activating_currency_for_store_a_does_not_change_store_b_currency_id(): void
    {
        DbCurrency::activateCurrency($this->currencyB->id, $this->storeA->id);

        $this->assertEquals($this->currencyB->id, $this->storeA->fresh()->currency_id);
        // Store B's currency_id must remain unchanged
        $this->assertEquals($this->currencyB->id, $this->storeB->fresh()->currency_id);

        // Prove isolation with a third currency activated only for Store B
        $third = DbCurrency::create([
            'currency_name' => 'Gamma Yen',
            'currency_code' => 'GGY',
            'currency' => 'Gamma Yen',
            'symbol' => '¥',
            'status' => 0,
        ]);
        DbCurrency::activateCurrency($third->id, $this->storeB->id);

        $this->assertEquals($third->id, $this->storeB->fresh()->currency_id);
        $this->assertEquals($this->currencyB->id, $this->storeA->fresh()->currency_id, 'Store A currency changed by Store B activation');
    }

    public function test_activating_language_for_store_a_does_not_change_store_b_language_id(): void
    {
        DbLanguage::activateLanguage($this->languageB->id, $this->storeA->id);

        $this->assertEquals($this->languageB->id, $this->storeA->fresh()->language_id);
        $this->assertEquals($this->languageB->id, $this->storeB->fresh()->language_id);

        $third = DbLanguage::create(['language' => 'Gamma Language', 'status' => 0]);
        DbLanguage::activateLanguage($third->id, $this->storeB->id);

        $this->assertEquals($third->id, $this->storeB->fresh()->language_id);
        $this->assertEquals($this->languageB->id, $this->storeA->fresh()->language_id, 'Store A language changed by Store B activation');
    }

    // ─── cache is per-store keyed ─────────────────────────────────────────────

    public function test_store_settings_cache_is_keyed_per_store(): void
    {
        $this->actingAs($this->userA);
        $this->assertEquals(1, store_settings()->id);
        $this->assertEquals('Alpha Store', store_settings()->store_name);

        $this->actingAs($this->userB);
        $this->assertEquals(2, store_settings()->id);
        $this->assertEquals('Beta Store', store_settings()->store_name);

        // Switch back — cache must still return the correct per-store row
        $this->actingAs($this->userA);
        $this->assertEquals('Alpha Store', store_settings()->store_name);
    }

    // ─── (c) consumers resolve the correct store ─────────────────────────────

    public function test_code_prefix_consumer_resolves_acting_store(): void
    {
        $this->actingAs($this->userA);
        $codeA = CodeGeneratorService::generate('sale');
        $this->assertStringStartsWith('AAA', $codeA, "Acting as Store A expected AAA prefix, got {$codeA}");

        $this->actingAs($this->userB);
        $codeB = CodeGeneratorService::generate('sale');
        $this->assertStringStartsWith('BBB', $codeB, "Acting as Store B expected BBB prefix, got {$codeB}");
    }

    public function test_pos_round_off_consumer_resolves_acting_store(): void
    {
        $this->storeA->update(['round_off' => 1]);
        $this->storeB->update(['round_off' => 0]);

        // Fresh resolve per acting user (direct DB writes bypass the memoized cache)
        $this->actingAs($this->userA);
        store_settings(true);
        $this->assertNotEmpty(store_settings()->round_off, 'Store A should have round_off enabled');

        $this->actingAs($this->userB);
        store_settings(true);
        $this->assertEmpty(store_settings()->round_off, 'Store B should have round_off disabled');
    }

    public function test_currency_symbol_resolver_is_store_scoped(): void
    {
        $symbolA = AppServiceProvider::resolveCurrencySymbol(true, $this->storeA->id);
        $symbolB = AppServiceProvider::resolveCurrencySymbol(true, $this->storeB->id);

        $this->assertEquals('৳', $symbolA);
        $this->assertEquals('€', $symbolB);
    }

    public function test_invoice_consumer_resolves_sale_store_not_acting_user_store(): void
    {
        $warehouse = DbWarehouse::create([
            'warehouse_name' => 'Beta Warehouse',
            'status' => 1,
            'store_id' => $this->storeB->id,
        ]);

        $customer = DbCustomer::create([
            'customer_name' => 'Beta Customer',
            'mobile' => '01811111111',
            'status' => 1,
            'store_id' => $this->storeB->id,
        ]);

        // Sale belongs to Store B
        $sale = DbSale::create([
            'store_id' => $this->storeB->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $this->userB->id,
            'sales_code' => 'BBB-00001',
            'sales_date' => now()->toDateString(),
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        // Acting as Store A's admin, viewing Store B's invoice
        $response = $this->actingAs($this->userA)->get(route('sales.invoice', ['id' => $sale->id]));

        $response->assertStatus(200);
        $response->assertSee('Beta Store');
        $response->assertDontSee('Alpha Store');
    }

    // ─── GAP 2 — exact "one db_store query per request" guarantee ────────────

    public function test_single_request_resolves_store_settings_with_exactly_one_db_store_query(): void
    {
        // Realistic single-store request: the acting user's store IS the store
        // being rendered. This is the case the "one db_store query per request"
        // guarantee covers — timezone config + currency symbol + invoice header
        // all resolve the SAME store and must reuse one memoized row.
        $warehouse = DbWarehouse::create([
            'warehouse_name' => 'Query Alpha Warehouse',
            'status' => 1,
            'store_id' => $this->storeA->id,
        ]);

        $customer = DbCustomer::create([
            'customer_name' => 'Query Alpha Customer',
            'mobile' => '01822222222',
            'status' => 1,
            'store_id' => $this->storeA->id,
        ]);

        $sale = DbSale::create([
            'store_id' => $this->storeA->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $this->userA->id,
            'sales_code' => 'QAA-00001',
            'sales_date' => now()->toDateString(),
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        // Cold-start the settings cache so the request must resolve the store
        // itself (not reuse a row memoized earlier in this test).
        flush_store_settings_cache();

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $response = $this->actingAs($this->userA)->get(route('sales.invoice', ['id' => $sale->id]));
        $response->assertStatus(200);

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        $dbStoreQueries = array_values(array_filter($queries, function ($q) {
            return preg_match('/\bfrom\s+["`]?db_store["`]?\b/i', $q['query']) === 1
                && stripos($q['query'], 'information_schema') === false;
        }));

        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertSame(
            1,
            count($dbStoreQueries),
            'Expected exactly ONE db_store SELECT for this single-store request; got ' . count($dbStoreQueries)
            . "\nQueries: " . json_encode(array_column($dbStoreQueries, 'query'))
        );
    }
}
