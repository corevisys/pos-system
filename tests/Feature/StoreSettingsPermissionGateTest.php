<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 2 — In-controller permission gate for Store Settings.
 *
 * A hidden sidebar link is not access control: edit() requires
 * store_settings_view and update() requires store_settings_edit, both enforced
 * in the controller (mirrors WarehouseController/ExpenseController/DepositController).
 */
class StoreSettingsPermissionGateTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected DbCurrency $currency;

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
            'store_name' => 'Gate Test Store',
            'mobile' => '01700000000',
            'email' => 'gate@example.com',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currency->id,
        ]);

        store_settings(true);
    }

    private int $nextRoleId = 50;

    private function makeUser(string $roleName, array $permissions): User
    {
        // Force a non-1 role id via forceCreate: DbRole::$fillable excludes
        // 'id', so a plain create() would silently ignore it and auto-increment
        // to id=1 on a fresh test DB — and isSuperAdmin() treats role_id === 1
        // as full access, defeating the permission-gate assertions below.
        $role = DbRole::forceCreate([
            'id' => $this->nextRoleId++,
            'role_name' => $roleName,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => $permissions,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'role_name' => $roleName,
            'store_id' => 1,
            'status' => 1,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'store_name' => 'Gate Test Store',
            'mobile' => '01700000000',
            'email' => 'gate@example.com',
            'city' => 'Dhaka',
            'currency_id' => $this->currency->id,
        ];
    }

    public function test_user_without_view_permission_gets_403_on_edit(): void
    {
        $user = $this->makeUser('No Perm', ['sales_view']);

        $this->actingAs($user)->get(route('settings.store'))->assertStatus(403);
    }

    public function test_user_without_edit_permission_gets_403_on_update(): void
    {
        $user = $this->makeUser('No Perm', ['sales_view']);

        $this->actingAs($user)
            ->post(route('settings.store.update'), $this->validPayload())
            ->assertStatus(403);

        // The store must be unchanged
        $this->assertEquals('Gate Test Store', $this->store->fresh()->store_name);
    }

    public function test_user_with_view_only_can_view_but_not_update(): void
    {
        $user = $this->makeUser('View Only', ['store_settings_view']);

        $this->actingAs($user)->get(route('settings.store'))->assertStatus(200);

        $this->actingAs($user)
            ->post(route('settings.store.update'), $this->validPayload())
            ->assertStatus(403);
    }

    public function test_user_with_both_permissions_succeeds(): void
    {
        $user = $this->makeUser('Full Perm', ['store_settings_view', 'store_settings_edit']);

        $this->actingAs($user)->get(route('settings.store'))->assertStatus(200);

        $response = $this->actingAs($user)->post(route('settings.store.update'), array_merge($this->validPayload(), [
            'store_name' => 'Renamed By Permitted User',
        ]));

        $response->assertRedirect(route('settings.store'));
        $response->assertSessionHas('success');
        $this->assertEquals('Renamed By Permitted User', $this->store->fresh()->store_name);
    }

    public function test_super_admin_slug_present_in_both_seeders(): void
    {
        // Regression guard: the slug must be grantable through the standard seeders.
        $permissionSeeder = file_get_contents(database_path('seeders/PermissionSeeder.php'));
        $roleSeeder = file_get_contents(database_path('seeders/RolePermissionSeeder.php'));

        $this->assertStringContainsString('store_settings_view', $permissionSeeder);
        $this->assertStringContainsString('store_settings_edit', $permissionSeeder);
        $this->assertStringContainsString('store_settings_view', $roleSeeder);
        $this->assertStringContainsString('store_settings_edit', $roleSeeder);
    }
}
