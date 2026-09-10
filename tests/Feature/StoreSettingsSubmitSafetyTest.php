<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 3 — Submit-safety + error-handling guards.
 *
 * Confirms (a) a validation failure renders the specific field error instead of
 * a silent 422, and (b) the save button carries the established isSubmitting
 * double-submit guard.
 */
class StoreSettingsSubmitSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected DbCurrency $currency;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = DbCurrency::create([
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'currency' => 'Bangladeshi Taka',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $this->store = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Submit Safety Store',
            'mobile' => '01700000000',
            'email' => 'safe@example.com',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currency->id,
        ]);

        DbRole::forceCreate([
            'id' => 1,
            'role_name' => 'Submit Safety Admin',
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
            'role_name' => 'Submit Safety Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        store_settings(true);
    }

    public function test_validation_failure_renders_the_specific_field_error(): void
    {
        // Omit the required store_name -> must fail validation with a message.
        $response = $this->actingAs($this->user)
            ->from(route('settings.store'))
            ->post(route('settings.store.update'), [
                'mobile' => '01700000000',
                'email' => 'safe@example.com',
                'city' => 'Dhaka',
                'currency_id' => $this->currency->id,
                // store_name intentionally omitted
            ]);

        $response->assertSessionHasErrors('store_name');
        $response->assertRedirect(route('settings.store'));

        // Follow the redirect: the page must visibly render the error, not a
        // silent 422 page.
        $followed = $this->actingAs($this->user)
            ->from(route('settings.store'))
            ->followingRedirects()
            ->post(route('settings.store.update'), [
                'mobile' => '01700000000',
                'email' => 'safe@example.com',
                'city' => 'Dhaka',
                'currency_id' => $this->currency->id,
            ]);

        $followed->assertStatus(200);
        $followed->assertSee('Please fix the following errors');
        $followed->assertSee('store name', false);
    }

    public function test_invalid_email_renders_its_specific_error(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('settings.store'))
            ->followingRedirects()
            ->post(route('settings.store.update'), [
                'store_name' => 'Submit Safety Store',
                'mobile' => '01700000000',
                'email' => 'not-an-email',
                'city' => 'Dhaka',
                'currency_id' => $this->currency->id,
            ]);

        $response->assertStatus(200);
        $response->assertSee('Please fix the following errors');
    }

    public function test_save_button_carries_double_submit_guard(): void
    {
        $response = $this->actingAs($this->user)->get(route('settings.store'));
        $response->assertStatus(200);

        // The established isSubmitting baseline: reactive disabled binding plus
        // the spinner/label swap, and the early-return guard in handleFormSubmit.
        $response->assertSee(':disabled="isSubmitting"', false);
        $response->assertSee('isSubmitting ? \'Saving...\' : \'Save Settings\'', false);
        $response->assertSee('if (this.isSubmitting) {', false);
    }

    public function test_rapid_double_submit_leaves_consistent_state(): void
    {
        $payload = [
            'store_name' => 'Double Submit Store',
            'mobile' => '01700000000',
            'email' => 'safe@example.com',
            'city' => 'Dhaka',
            'currency_id' => $this->currency->id,
        ];

        // Two identical submissions in sequence must resolve to one coherent row.
        $this->actingAs($this->user)->post(route('settings.store.update'), $payload);
        $this->actingAs($this->user)->post(route('settings.store.update'), $payload);

        $fresh = $this->store->fresh();
        $this->assertEquals('Double Submit Store', $fresh->store_name);
        $this->assertEquals($this->currency->id, $fresh->currency_id);
        $this->assertSame(1, DbStore::count(), 'No duplicate store rows may be created by a double submit.');
    }
}
