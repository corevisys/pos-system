<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 4 — Confirmed bug fixes.
 *
 * 1. time_format: seeder previously stored 'h:i A' while the option value is
 *    'h:i a' (case mismatch) — no option was ever pre-selected.
 * 2. currency_placement: seeder previously stored 'Left' while the options are
 *    'before'/'after' — no option was ever pre-selected and format_currency()
 *    (which compares === 'after') silently treated it as 'before'.
 * 3. Phantom validated columns (sales_discount, sales_invoice_format_id,
 *    pos_invoice_format_id, mrp_column) removed — dead, no consumer anywhere.
 * 4. Placeholder logo: dead /placeholder-logo.png replaced with an inline SVG
 *    data URI so a store with no logo renders a neutral box, not a broken img.
 */
class StoreSettingsPhase4FixesTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected DbCurrency $currency;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // StoreSeeder inserts language_id=1 and currency_id=2, so the currency
        // and language tables must be seeded first (FKs are enforced).
        $this->seed([
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\LanguageSeeder::class,
            StoreSeeder::class,
        ]);

        $this->currency = DbCurrency::first();

        $this->store = DbStore::first();
        $this->store->update(['currency_id' => $this->currency->id]);

        DbRole::forceCreate([
            'id' => 1,
            'role_name' => 'Phase4 Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        \App\Models\DbPermission::create([
            'role_id' => 1,
            'store_id' => 1,
            'permissions' => ['store_settings_view', 'store_settings_edit'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Phase4 Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        store_settings(true);
    }

    public function test_seeder_time_format_matches_option_value_and_is_preselectable(): void
    {
        // Seeder must store the canonical lower-case value that matches the option.
        $this->assertEquals('h:i a', $this->store->time_format);

        $response = $this->actingAs($this->user)->get(route('settings.store'));
        $response->assertStatus(200);

        // The 12-hour option must carry selected for the seeded value.
        $response->assertSee('value="h:i a" selected', false);
    }

    public function test_seeder_currency_placement_matches_option_value(): void
    {
        // Seeder must store 'before' (the format_currency() === 'after' contract).
        $this->assertEquals('before', $this->store->currency_placement);

        $response = $this->actingAs($this->user)->get(route('settings.store'));
        $response->assertStatus(200);

        $response->assertSee('value="before" selected', false);
    }

    public function test_update_accepts_payload_without_phantom_fields(): void
    {
        // The four dead columns must no longer be required/validated — a normal
        // save (without them) succeeds, proving removal of the phantom rules.
        $response = $this->actingAs($this->user)->post(route('settings.store.update'), [
            'store_name' => 'Phantom Field Store',
            'mobile' => '01700000000',
            'email' => 'phase4@example.com',
            'city' => 'Dhaka',
            'currency_id' => $this->currency->id,
            'number_to_words' => 1,
            'change_return' => 1,
            'previous_balance_bit' => 1,
        ]);

        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');
        $this->assertEquals('Phantom Field Store', $this->store->fresh()->store_name);
    }

    public function test_phantom_columns_have_no_consumer_anywhere(): void
    {
        // Guard against re-introducing the phantom rules: these columns must
        // not be read by any app code.
        $appFiles = array_merge(
            glob(app_path('Http/**/*.php')),
            glob(app_path('Models/*.php')),
            glob(app_path('Services/*.php')),
            glob(app_path('Helpers/*.php'))
        );

        // Match actual usage (validation key / property read), not a passing
        // mention in a comment or docblock.
        $needles = [
            "'sales_discount' =>",
            "'sales_invoice_format_id' =>",
            "'pos_invoice_format_id' =>",
            "'mrp_column' =>",
            '->sales_discount',
            '->sales_invoice_format_id',
            '->pos_invoice_format_id',
            '->mrp_column',
        ];

        foreach ($needles as $needle) {
            foreach ($appFiles as $file) {
                $haystack = (string) file_get_contents($file);
                if (str_contains($haystack, $needle)) {
                    $this->fail("Phantom column usage '{$needle}' still present in {$file}");
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_page_no_longer_references_dead_placeholder_logo_file(): void
    {
        $response = $this->actingAs($this->user)->get(route('settings.store'));
        $response->assertStatus(200);

        // The broken /placeholder-logo.png reference must be gone, replaced by
        // the inline SVG data-URI placeholder.
        $response->assertDontSee('/placeholder-logo.png', false);
        $response->assertSee('data:image/svg+xml;base64,', false);
    }
}
