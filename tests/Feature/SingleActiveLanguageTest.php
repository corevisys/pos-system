<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleActiveLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbLanguage $english;
    protected DbLanguage $bangla;
    protected DbLanguage $spanish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->english = DbLanguage::create([
            'language' => 'English',
            'status' => 1,
        ]);

        $this->bangla = DbLanguage::create([
            'language' => 'Bangla',
            'status' => 0,
        ]);

        $this->spanish = DbLanguage::create([
            'language' => 'Spanish',
            'status' => 0,
        ]);

        $currency = DbCurrency::create([
            'currency_name' => 'US Dollar',
            'currency_code' => 'USD',
            'currency' => 'US Dollar',
            'symbol' => '$',
            'status' => 1,
        ]);

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_code' => 'ST001',
            'store_name' => 'Main Store',
            'status' => 1,
            'language_id' => $this->english->id,
            'currency_id' => $currency->id,
            'mobile' => '+8801700000000',
            'email' => 'store@corevisys.com',
            'city' => 'Dhaka',
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

    public function test_languages_list_page_renders_with_single_active_language()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('settings.languages.index'));

        $response->assertStatus(200);
        $response->assertSee('Language Management');
        $response->assertSee('English');
        $response->assertSee('Bangla');
        $response->assertSee('Spanish');
        $response->assertSee('Switch Active Language');
    }

    public function test_activating_new_language_deactivates_old_and_activates_new_atomically()
    {
        // Initially English is active, Bangla and Spanish are inactive
        $this->assertEquals(1, $this->english->fresh()->status);
        $this->assertEquals(0, $this->bangla->fresh()->status);
        $this->assertEquals(0, $this->spanish->fresh()->status);
        $this->assertEquals($this->english->id, $this->store->fresh()->language_id);

        // Activate Bangla
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.activate', $this->bangla->id));

        $response->assertRedirect(route('settings.languages.index'));
        $response->assertSessionHas('success');

        // Check DB state: English deactivated, Bangla activated, Spanish remains inactive
        $this->assertEquals(0, $this->english->fresh()->status);
        $this->assertEquals(1, $this->bangla->fresh()->status);
        $this->assertEquals(0, $this->spanish->fresh()->status);

        // Check store language_id is updated to Bangla
        $this->assertEquals($this->bangla->id, $this->store->fresh()->language_id);

        // Check single active language constraint in DB
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
    }

    public function test_sequence_of_multiple_activations_maintains_exactly_one_active_language()
    {
        // 1. Activate Spanish
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.activate', $this->spanish->id));

        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $this->assertEquals($this->spanish->id, DbLanguage::where('status', 1)->first()->id);
        $this->assertEquals($this->spanish->id, $this->store->fresh()->language_id);

        // 2. Activate Bangla
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.activate', $this->bangla->id));

        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $this->assertEquals($this->bangla->id, DbLanguage::where('status', 1)->first()->id);
        $this->assertEquals($this->bangla->id, $this->store->fresh()->language_id);

        // 3. Activate English
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.activate', $this->english->id));

        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $this->assertEquals($this->english->id, DbLanguage::where('status', 1)->first()->id);
        $this->assertEquals($this->english->id, $this->store->fresh()->language_id);
    }

    public function test_disallows_directly_deactivating_the_active_language_via_update()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.update', $this->english->id), [
                'language' => 'English',
                'status' => 0,
            ]);

        $response->assertSessionHas('error');
        // English remains active
        $this->assertEquals(1, $this->english->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
    }

    public function test_disallows_deleting_the_active_language()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->delete(route('settings.languages.destroy', $this->english->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('db_languages', ['id' => $this->english->id]);
        $this->assertEquals(1, $this->english->fresh()->status);
    }

    public function test_inactive_language_can_be_deleted()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->delete(route('settings.languages.destroy', $this->spanish->id));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('db_languages', ['id' => $this->spanish->id]);
    }

    public function test_updating_inactive_language_to_active_switches_system_language()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.update', $this->bangla->id), [
                'language' => 'Bangla Updated',
                'status' => 1,
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals(1, $this->bangla->fresh()->status);
        $this->assertEquals('Bangla Updated', $this->bangla->fresh()->language);
        $this->assertEquals(0, $this->english->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $this->assertEquals($this->bangla->id, $this->store->fresh()->language_id);
    }

    public function test_creating_new_language_with_status_active_switches_system_language()
    {
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.store'), [
                'language' => 'German',
                'status' => 1,
            ]);

        $response->assertSessionHas('success');

        $german = DbLanguage::where('language', 'German')->first();
        $this->assertNotNull($german);
        $this->assertEquals(1, $german->status);
        $this->assertEquals(0, $this->english->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $this->assertEquals($german->id, $this->store->fresh()->language_id);
    }

    public function test_existing_multi_active_languages_data_fix_resolves_to_single_active()
    {
        // Simulate corrupted legacy DB state: multiple active languages
        DbLanguage::query()->update(['status' => 1]);
        $this->assertEquals(3, DbLanguage::where('status', 1)->count());

        // Run data fix migration logic
        $migration = require database_path('migrations/2026_08_24_000005_enforce_single_active_language_data_fix.php');
        $migration->up();

        // Must resolve to exactly 1 active language matching store setting
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
        $activeLang = DbLanguage::where('status', 1)->first();
        $this->assertEquals($this->store->fresh()->language_id, $activeLang->id);
    }

    public function test_unauthenticated_user_cannot_activate_language()
    {
        $response = $this->post(route('settings.languages.activate', $this->bangla->id));
        $response->assertRedirect(route('login'));
        $this->assertEquals(1, $this->english->fresh()->status);
    }

    public function test_store_settings_update_atomically_activates_selected_language_and_deactivates_others()
    {
        // Initially English is active (status=1), Bangla is inactive (status=0)
        $this->assertEquals(1, $this->english->fresh()->status);
        $this->assertEquals(0, $this->bangla->fresh()->status);
        $this->assertEquals($this->english->id, $this->store->fresh()->language_id);

        // Update Store Settings choosing Bangla
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Main Store Updated',
                'mobile' => '+8801700000000',
                'email' => 'store@corevisys.com',
                'city' => 'Dhaka',
                'currency_id' => $this->store->currency_id,
                'language_id' => $this->bangla->id,
            ]);

        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');

        // Check db_store.language_id is updated to Bangla
        $this->assertEquals($this->bangla->id, $this->store->fresh()->language_id);

        // Check db_languages statuses are properly synchronized
        $this->assertEquals(0, $this->english->fresh()->status);
        $this->assertEquals(1, $this->bangla->fresh()->status);
        $this->assertEquals(0, $this->spanish->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
    }

    public function test_store_settings_and_languages_list_stay_in_two_way_sync()
    {
        // 1. Switch language via Languages List to Spanish
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.languages.activate', $this->spanish->id));

        $this->assertEquals(1, $this->spanish->fresh()->status);
        $this->assertEquals($this->spanish->id, $this->store->fresh()->language_id);

        // Verify Store Settings page shows Spanish selected
        $storePageResponse = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('settings.store'));
        $storePageResponse->assertStatus(200);
        $storePageResponse->assertSee('Spanish');

        // 2. Switch language via Store Settings to Bangla
        $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Main Store',
                'mobile' => '+8801700000000',
                'email' => 'store@corevisys.com',
                'city' => 'Dhaka',
                'currency_id' => $this->store->currency_id,
                'language_id' => $this->bangla->id,
            ]);

        // Verify Languages List page shows Bangla as active
        $langListResponse = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->get(route('settings.languages.index'));
        $langListResponse->assertStatus(200);
        $langListResponse->assertSee('Current System Language');
        $langListResponse->assertSee('Bangla');

        $this->assertEquals(1, $this->bangla->fresh()->status);
        $this->assertEquals(0, $this->spanish->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
    }

    public function test_store_settings_update_without_changing_language_preserves_active_language()
    {
        // Initially English is active
        $this->assertEquals(1, $this->english->fresh()->status);
        $this->assertEquals($this->english->id, $this->store->fresh()->language_id);

        // Update Store Settings with the SAME language_id (only changing store name)
        $response = $this->actingAs($this->user)
            ->withSession(['store_id' => 1])
            ->post(route('settings.store.update'), [
                'store_name' => 'Brand New Store Name',
                'mobile' => '+8801700000000',
                'email' => 'store@corevisys.com',
                'city' => 'Dhaka',
                'currency_id' => $this->store->currency_id,
                'language_id' => $this->english->id,
            ]);

        $response->assertRedirect(route('settings.store'));
        $this->assertEquals('Brand New Store Name', $this->store->fresh()->store_name);
        $this->assertEquals(1, $this->english->fresh()->status);
        $this->assertEquals(1, DbLanguage::where('status', 1)->count());
    }
}
