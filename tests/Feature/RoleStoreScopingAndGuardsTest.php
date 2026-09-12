<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Closes the original Role/Permission scoping task:
 *  1. no `orWhereNull('store_id')` anywhere (global scope or hand-written);
 *  2. DbRole / DbPermission are StoreScoped and reads are store-isolated;
 *  3. RoleController show/edit/update/destroy are cross-store safe (404, not the record);
 *  4. a role with assigned users cannot be deleted.
 */
class RoleStoreScopingAndGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store One', 'status' => 1, 'mobile' => '01711111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store Two', 'status' => 1, 'mobile' => '01722222222']);
    }

    /** A super admin bound to $storeId (flagged explicitly; name never grants). */
    private function superAdminForStore(int $storeId, int $roleId): User
    {
        $role = DbRole::forceCreate([
            'id' => $roleId,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => $storeId,
            'is_super_admin' => true,
        ]);

        return User::factory()->create([
            'store_id' => $storeId,
            'role_id' => $role->id,
            'role_name' => $role->role_name,
        ]);
    }

    /* ───────────────── Item 1: no orWhereNull('store_id') anywhere ───────────────── */

    public function test_no_or_where_null_store_id_remains_in_app_code(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = File::get($file->getPathname());
            if (preg_match("/orWhereNull\(\s*['\"]?[\\\$a-zA-Z_]*\.?'?store_id/", $contents)
                || preg_match("/orWhereNull\(\s*['\"]store_id['\"]/", $contents)) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "orWhereNull('store_id') must not remain anywhere. Found in: " . implode(', ', $offenders)
        );
    }

    public function test_store_scoped_trait_has_no_null_branch(): void
    {
        $trait = File::get(app_path('Models/Traits/StoreScoped.php'));

        // Assert on the actual CALL, not on the word appearing in the explanatory
        // comment above it.
        $this->assertDoesNotMatchRegularExpression('/->orWhereNull\s*\(/', $trait);
        $this->assertDoesNotMatchRegularExpression('/\borWhereNull\s*\(/', $trait);
        // scopeAllStores() must be preserved as the sanctioned bypass.
        $this->assertStringContainsString("withoutGlobalScope('store_id')", $trait);
    }

    /* ───────────────── Item 2: DbRole/DbPermission are StoreScoped ───────────────── */

    public function test_db_role_and_permission_use_store_scoped_trait(): void
    {
        $this->assertContains(\App\Models\Traits\StoreScoped::class, class_uses_recursive(DbRole::class));
        $this->assertContains(\App\Models\Traits\StoreScoped::class, class_uses_recursive(DbPermission::class));
    }

    public function test_role_list_is_store_isolated(): void
    {
        // Store 2 has a distinctive role; store 1's admin must never list it.
        DbRole::create(['store_id' => 2, 'role_name' => 'StoreTwoOnlyRole', 'status' => 1]);
        DbRole::create(['store_id' => 1, 'role_name' => 'StoreOneOnlyRole', 'status' => 1]);

        $admin = $this->superAdminForStore(1, 91);

        $response = $this->actingAs($admin)->get(route('users.roles'));
        $response->assertOk();

        $listed = collect($response->viewData('roles')->items())->pluck('role_name')->all();

        $this->assertContains('StoreOneOnlyRole', $listed);
        $this->assertNotContains('StoreTwoOnlyRole', $listed, 'Cross-store role leaked into the list.');
    }

    public function test_permission_reads_are_store_isolated(): void
    {
        $otherRole = DbRole::create(['store_id' => 2, 'role_name' => 'Other Store Role', 'status' => 1]);
        DbPermission::create([
            'role_id' => $otherRole->id,
            'store_id' => 2,
            'permissions' => ['sales_add'],
        ]);

        $admin = $this->superAdminForStore(1, 92);
        $this->actingAs($admin);

        // Acting store is 1 → the store-2 permission row must not be visible.
        $this->assertSame(0, DbPermission::where('role_id', $otherRole->id)->count());
        // …but it is still there (visible with the scope explicitly bypassed).
        $this->assertSame(1, DbPermission::allStores()->where('role_id', $otherRole->id)->count());
    }

    /* ───────────────── Item 3: cross-store IDOR on role ids ───────────────── */

    public function test_edit_update_and_destroy_of_another_stores_role_return_404(): void
    {
        $store1Admin = $this->superAdminForStore(1, 93);
        $store2Role = DbRole::create(['store_id' => 2, 'role_name' => 'Store Two Target', 'status' => 1]);

        // edit
        $this->actingAs($store1Admin)
            ->get(route('users.roles.edit', $store2Role->id))
            ->assertNotFound();

        // update
        $this->actingAs($store1Admin)
            ->put(route('users.roles.update', $store2Role->id), ['role_name' => 'HACKED'])
            ->assertNotFound();

        // destroy
        $this->actingAs($store1Admin)
            ->delete(route('users.roles.destroy', $store2Role->id))
            ->assertNotFound();

        // The store-2 role is completely untouched.
        $fresh = DbRole::allStores()->find($store2Role->id);
        $this->assertNotNull($fresh, 'Store 2 role must still exist.');
        $this->assertSame('Store Two Target', $fresh->role_name);
    }

    public function test_show_of_another_stores_role_returns_404(): void
    {
        $store1Admin = $this->superAdminForStore(1, 94);
        $store2Role = DbRole::create(['store_id' => 2, 'role_name' => 'Store Two Showable', 'status' => 1]);

        $this->actingAs($store1Admin)
            ->get(route('users.roles.show', $store2Role->id))
            ->assertNotFound();
    }

    /* ───────────────── Item 4: delete-guard for roles with users ───────────────── */

    public function test_deleting_a_role_with_assigned_users_is_blocked(): void
    {
        $admin = $this->superAdminForStore(1, 95);

        $target = DbRole::create(['store_id' => 1, 'role_name' => 'Has Users', 'status' => 1]);
        User::factory()->create(['store_id' => 1, 'role_id' => $target->id, 'role_name' => $target->role_name]);

        $response = $this->actingAs($admin)->delete(route('users.roles.destroy', $target->id));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('1 user(s)', session('error'));
        // Role AND the assigned user must both survive.
        $this->assertNotNull(DbRole::allStores()->find($target->id), 'Role with users must not be deleted.');
        $this->assertSame(1, User::where('role_id', $target->id)->count(), 'Assigned user must not be cascade-deleted.');
    }

    public function test_deleting_a_role_with_no_users_succeeds(): void
    {
        $admin = $this->superAdminForStore(1, 96);

        $target = DbRole::create(['store_id' => 1, 'role_name' => 'No Users', 'status' => 1]);
        DbPermission::create(['role_id' => $target->id, 'store_id' => 1, 'permissions' => ['sales_view']]);

        $response = $this->actingAs($admin)->delete(route('users.roles.destroy', $target->id));

        $response->assertSessionHas('success');
        $this->assertNull(DbRole::allStores()->find($target->id), 'Unused role should be deleted.');
        // Its permission row is removed alongside it.
        $this->assertSame(0, DbPermission::allStores()->where('role_id', $target->id)->count());
    }
}
