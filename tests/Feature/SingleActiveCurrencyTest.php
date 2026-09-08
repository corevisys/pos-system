<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleActiveCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbCurrency $usd;
    protected DbCurrency $bdt;
    protected DbCurrency $eur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = DbCurrency::create([
            'currency_name' => 'US Dollar',
            'currency_code' => 'USD',
            'currency' => 'US Dollar',
            'symbol' => '$',
            'status' => 1,
        ]);

        $this->bdt = DbCurrency::create([
            'currency_name' => 'TAKA',
            'currency_code' => 'BDT',
            'currency' => 'TAKA',
            'symbol' => '৳',
            'status' => 0,
        ]);

        $this->eur = DbCurrency::create([
            'currency_name' => 'Euro',
            'currency_code' => 'EUR',
            'currency' => 'Euro',
            'symbol' => '€',
            'status' => 0,
        ]);

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_code' => 'ST001',
            'store_name' => 'Main Store',
            'status' => 1,
            'currency_id' => $this->usd->id,
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => ['all'],
        ]);

        $this->user = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);
    }

    public function test_currencies_list_page_renders_with_single_active_currency()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('settings.currency'));

        $response->assertStatus(200);
        $response->assertSee('Currencies List');
        $response->assertSee('US Dollar');
        $response->assertSee('TAKA');
        $response->assertSee('Euro');
        $response->assertSee('Switch Active Currency');
    }

    public function test_activating_new_currency_deactivates_old_and_activates_new_atomically()
    {
        // Initially USD is active, BDT and EUR are inactive
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
        $this->assertEquals($this->usd->id, $this->store->fresh()->currency_id);

        // Activate BDT
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.activate', $this->bdt->id));

        $response->assertRedirect(route('settings.currency'));
        $response->assertSessionHas('success');

        // Check DB state: USD deactivated, BDT activated, EUR remains inactive
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(1, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);

        // Check store currency_id is updated to BDT
        $this->assertEquals($this->bdt->id, $this->store->fresh()->currency_id);

        // Check single active currency constraint in DB
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());

        // Check AppServiceProvider resolves BDT symbol
        $this->assertEquals('৳', AppServiceProvider::resolveCurrencySymbol());
    }

    public function test_sequence_of_multiple_activations_maintains_exactly_one_active_currency()
    {
        // 1. Activate EUR
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.activate', $this->eur->id));

        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($this->eur->id, DbCurrency::where('status', 1)->first()->id);
        $this->assertEquals('€', AppServiceProvider::resolveCurrencySymbol());

        // 2. Activate BDT
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.activate', $this->bdt->id));

        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($this->bdt->id, DbCurrency::where('status', 1)->first()->id);
        $this->assertEquals('৳', AppServiceProvider::resolveCurrencySymbol());

        // 3. Activate USD
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.activate', $this->usd->id));

        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($this->usd->id, DbCurrency::where('status', 1)->first()->id);
        $this->assertEquals('$', AppServiceProvider::resolveCurrencySymbol());
    }

    public function test_disallows_directly_deactivating_the_active_currency_via_update()
    {
        // Try to update USD (currently active) with status = 0
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.update', $this->usd->id), [
                'currency_name' => 'US Dollar',
                'currency_code' => 'USD',
                'symbol' => '$',
                'status' => 0,
            ]);

        $response->assertSessionHas('error');
        
        // Ensure USD is still active
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
    }

    public function test_disallows_deleting_the_active_currency()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->delete(route('settings.currency.delete', $this->usd->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('db_currency', ['id' => $this->usd->id]);
    }

    public function test_inactive_currency_can_be_deleted()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->delete(route('settings.currency.delete', $this->eur->id));

        $response->assertRedirect(route('settings.currency'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('db_currency', ['id' => $this->eur->id]);
    }

    public function test_updating_inactive_currency_to_active_switches_system_currency()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.update', $this->bdt->id), [
                'currency_name' => 'Bangladeshi Taka',
                'currency_code' => 'BDT',
                'symbol' => '৳',
                'status' => 1,
            ]);

        $response->assertRedirect(route('settings.currency'));
        $this->assertEquals(1, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($this->bdt->id, $this->store->fresh()->currency_id);
    }

    public function test_creating_new_currency_with_status_active_switches_system_currency()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.store'), [
                'currency_name' => 'British Pound',
                'currency_code' => 'GBP',
                'symbol' => '£',
                'status' => 1,
            ]);

        $response->assertRedirect(route('settings.currency'));
        $gbp = DbCurrency::where('currency_code', 'GBP')->first();
        $this->assertNotNull($gbp);
        $this->assertEquals(1, $gbp->status);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($gbp->id, $this->store->fresh()->currency_id);
    }

    public function test_existing_multi_active_currencies_data_fix_resolves_to_single_active()
    {
        // Simulate legacy buggy data where all currencies are marked status = 1
        DbCurrency::query()->update(['status' => 1]);
        $this->store->update(['currency_id' => $this->bdt->id]);

        $this->assertEquals(3, DbCurrency::where('status', 1)->count());

        // Run data migration logic
        $migration = require database_path('migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php');
        $migration->up();

        // Exactly one currency should now be active, matching the store's currency_id (BDT)
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());
        $this->assertEquals($this->bdt->id, DbCurrency::where('status', 1)->first()->id);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
    }

    public function test_unauthenticated_user_cannot_activate_currency()
    {
        $response = $this->post(route('settings.currency.activate', $this->bdt->id));
        $response->assertRedirect(route('login'));
        $this->assertEquals(1, $this->usd->fresh()->status);
    }

    public function test_store_settings_update_atomically_activates_selected_currency_and_deactivates_others()
    {
        // Initially USD is active (status=1, store->currency_id=usd)
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->bdt->fresh()->status);
        $this->assertEquals($this->usd->id, $this->store->fresh()->currency_id);

        // Update Store Settings to select BDT
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Main Store Updated',
                'mobile' => '01700000000',
                'email' => 'store@example.com',
                'city' => 'Dhaka',
                'currency_id' => $this->bdt->id,
            ]);

        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');

        // Verify DB state: store currency_id is BDT, BDT is active, USD is deactivated
        $this->assertEquals($this->bdt->id, $this->store->fresh()->currency_id);
        $this->assertEquals(1, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
        $this->assertEquals(1, DbCurrency::where('status', 1)->count());

        // Verify Currencies List page now shows BDT as active
        $listResponse = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('settings.currency'));

        $listResponse->assertStatus(200);
        $listResponse->assertSee('TAKA');
        $listResponse->assertSee('Active');

        // Verify resolveCurrencySymbol() returns BDT symbol
        $this->assertEquals('৳', AppServiceProvider::resolveCurrencySymbol());
    }

    public function test_store_settings_and_currency_list_stay_in_two_way_sync()
    {
        // 1. Change via Currencies List to EUR
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.currency.activate', $this->eur->id));

        $this->assertEquals($this->eur->id, $this->store->fresh()->currency_id);
        $this->assertEquals(1, $this->eur->fresh()->status);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->bdt->fresh()->status);
        $this->assertEquals('€', AppServiceProvider::resolveCurrencySymbol());

        // 2. Change via Store Settings to TAKA (BDT)
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Main Store',
                'mobile' => '01700000000',
                'email' => 'store@example.com',
                'city' => 'Dhaka',
                'currency_id' => $this->bdt->id,
            ]);

        $this->assertEquals($this->bdt->id, $this->store->fresh()->currency_id);
        $this->assertEquals(1, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
        $this->assertEquals(0, $this->usd->fresh()->status);
        $this->assertEquals('৳', AppServiceProvider::resolveCurrencySymbol());

        // 3. Change via Store Settings back to USD
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Main Store',
                'mobile' => '01700000000',
                'email' => 'store@example.com',
                'city' => 'Dhaka',
                'currency_id' => $this->usd->id,
            ]);

        $this->assertEquals($this->usd->id, $this->store->fresh()->currency_id);
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
        $this->assertEquals('$', AppServiceProvider::resolveCurrencySymbol());
    }

    public function test_store_settings_update_without_changing_currency_preserves_active_currency()
    {
        // Initially USD is active
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals($this->usd->id, $this->store->fresh()->currency_id);

        // Update Store Settings without changing currency_id (submitting same USD id)
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Brand New Store Name',
                'mobile' => '01800000000',
                'email' => 'newemail@example.com',
                'city' => 'Chittagong',
                'currency_id' => $this->usd->id,
            ]);

        $response->assertRedirect(route('settings.store'));
        $this->assertEquals('Brand New Store Name', $this->store->fresh()->store_name);
        $this->assertEquals($this->usd->id, $this->store->fresh()->currency_id);
        $this->assertEquals(1, $this->usd->fresh()->status);
        $this->assertEquals(0, $this->bdt->fresh()->status);
        $this->assertEquals(0, $this->eur->fresh()->status);
    }
}
