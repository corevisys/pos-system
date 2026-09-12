<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DbCurrency;
use App\Models\DbLanguage;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activity trail (Gap 5 — audit trail) verification.
 *
 * Covers the single-table activity_logs design:
 *  - login / logout are recorded with a correct actor + store, with the logout
 *    actor captured BEFORE the session is invalidated;
 *  - store-settings updates log ONLY genuinely-changed fields (no row at all
 *    when a submission posts identical values);
 *  - the read surface is explicitly store-scoped.
 *
 * Store-switch logging is intentionally NOT tested — no store-switching feature
 * exists in this application, so no such event can be produced.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $storeA;
    protected DbStore $storeB;
    protected DbCurrency $currency;
    protected DbLanguage $language;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = DbCurrency::create([
            'currency_name' => 'Log Dollar',
            'currency_code' => 'LGD',
            'symbol' => '$',
            'status' => 1,
        ]);

        $this->language = DbLanguage::create([
            'language' => 'Log English',
            'status' => 1,
        ]);

        // FK target: users.role_id references db_roles.id.
        // is_super_admin is the authoritative global-privilege flag (name/id no
        // longer imply it), so it must be set explicitly for this Super Admin role.
        DbRole::forceCreate([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
            'is_super_admin' => true,
        ]);

        // date/time format + timezone are pinned so a "no change" submission can
        // post the exact persisted values and legitimately produce no diff.
        $this->storeA = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Alpha Store',
            'mobile' => '01700000001',
            'email' => 'alpha@example.com',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currency->id,
            'language_id' => $this->language->id,
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd-m-Y',
            'time_format' => 'h:i a',
            'decimals' => 2,
            'qty_decimals' => 2,
        ]);

        $this->storeB = DbStore::create([
            'id' => 2,
            'store_code' => 'ST0002',
            'store_name' => 'Beta Store',
            'mobile' => '01700000002',
            'email' => 'beta@example.com',
            'city' => 'Chittagong',
            'status' => 1,
            'currency_id' => $this->currency->id,
            'language_id' => $this->language->id,
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd-m-Y',
            'time_format' => 'h:i a',
            'decimals' => 2,
            'qty_decimals' => 2,
        ]);

        store_settings(true);
    }

    /**
     * A Super Admin bypasses the store-settings permission gate (hasPermission()
     * short-circuits on isSuperAdmin()), keeping these tests focused on the log.
     */
    private function superAdminFor(DbStore $store): User
    {
        return User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => $store->id,
            'status' => 1,
        ]);
    }

    /**
     * Build a store-settings payload whose values match the persisted row,
     * so only deliberate $overrides register as a change.
     */
    private function settingsPayload(DbStore $store, array $overrides = []): array
    {
        $store->refresh();

        return array_merge([
            'store_name' => $store->store_name,
            'mobile' => $store->mobile,
            'email' => $store->email,
            'city' => $store->city,
            'currency_id' => (string) $store->currency_id,
            'language_id' => (string) $store->language_id,
            'timezone' => $store->timezone,
            'date_format' => $store->date_format,
            'time_format' => $store->time_format,
            'decimals' => $store->decimals,
            'qty_decimals' => $store->qty_decimals,
        ], $overrides);
    }

    public function test_successful_login_creates_activity_log_row(): void
    {
        $user = $this->superAdminFor($this->storeA);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $row = ActivityLog::where('action', 'login')->first();

        $this->assertNotNull($row, 'A successful login must create exactly one activity_logs row.');
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame($this->storeA->id, (int) $row->store_id);
        $this->assertSame(1, ActivityLog::where('action', 'login')->count());
    }

    public function test_logout_creates_activity_log_row_with_actor_captured_before_invalidation(): void
    {
        $user = $this->superAdminFor($this->storeA);

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();

        $row = ActivityLog::where('action', 'logout')->first();

        $this->assertNotNull($row, 'A logout must create an activity_logs row.');
        // Captured BEFORE logout() — must not be null.
        $this->assertSame($user->id, (int) $row->user_id, 'Logout must capture the actor before the session is invalidated.');
        $this->assertSame($this->storeA->id, (int) $row->store_id);
    }

    public function test_settings_update_logs_only_changed_fields(): void
    {
        $user = $this->superAdminFor($this->storeA);

        $payload = $this->settingsPayload($this->storeA, [
            'store_name' => 'Alpha Store Renamed',
        ]);

        $response = $this->actingAs($user)->post(route('settings.store.update'), $payload);
        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHasNoErrors();

        $rows = ActivityLog::where('action', 'settings_updated')->get();

        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertSame($this->storeA->id, (int) $row->store_id);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame(DbStore::class, $row->entity_type);
        $this->assertSame($this->storeA->id, (int) $row->entity_id);

        // ONLY store_name changed — nothing else may leak into the snapshot.
        $this->assertSame(['store_name'], array_keys($row->new_values));
        $this->assertSame(['store_name'], array_keys($row->old_values));
        $this->assertSame('Alpha Store', $row->old_values['store_name']);
        $this->assertSame('Alpha Store Renamed', $row->new_values['store_name']);
    }

    public function test_settings_update_with_no_actual_changes_creates_no_row(): void
    {
        $user = $this->superAdminFor($this->storeA);

        // Re-submits the exact persisted values → zero diff.
        $payload = $this->settingsPayload($this->storeA);

        $response = $this->actingAs($user)->post(route('settings.store.update'), $payload);
        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            ActivityLog::where('action', 'settings_updated')->count(),
            'A no-op settings submission must NOT create an audit row.'
        );
    }

    public function test_activity_read_surface_is_store_scoped(): void
    {
        $userA = $this->superAdminFor($this->storeA);
        $userB = $this->superAdminFor($this->storeB);

        ActivityLog::create([
            'store_id' => $this->storeA->id,
            'user_id' => $userA->id,
            'action' => 'login',
            'ip_address' => '10.0.0.1',
        ]);
        ActivityLog::create([
            'store_id' => $this->storeB->id,
            'user_id' => $userB->id,
            'action' => 'login',
            'ip_address' => '10.0.0.2',
        ]);

        $response = $this->actingAs($userA)->get(route('settings.store'));
        $response->assertOk();

        $activities = $response->viewData('activities');

        $this->assertCount(1, $activities, 'The activity list must only contain the acting store\'s rows.');
        $this->assertSame($this->storeA->id, (int) $activities->first()->store_id);
        $this->assertSame('10.0.0.1', $activities->first()->ip_address);
    }

    public function test_scope_for_store_filters_rows(): void
    {
        ActivityLog::create(['store_id' => 1, 'action' => 'login']);
        ActivityLog::create(['store_id' => 2, 'action' => 'login']);

        $this->assertSame(1, ActivityLog::query()->forStore(1)->count());
        $this->assertSame(2, ActivityLog::query()->forStore()->count());
    }
}
