<?php

namespace Tests\Feature;

use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * role_name uniqueness is PER STORE, not global.
 *
 * Two branches may each define a "Manager" role; one store must not have two.
 * Covered by both application validation (RoleController) and a DB-level
 * composite unique (store_id, role_name) as defence-in-depth.
 */
class RoleNamePerStoreUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store One', 'status' => 1, 'mobile' => '01711111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store Two', 'status' => 1, 'mobile' => '01722222222']);
    }

    /**
     * A super admin (so Gate::authorize('create', DbRole::class) passes) bound to
     * the given store. Flagged explicitly — the name never confers privilege.
     */
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

    public function test_cross_store_same_role_name_is_allowed(): void
    {
        // Store 1 already has a "Manager" role.
        DbRole::create(['store_id' => 1, 'role_name' => 'Manager', 'status' => 1]);

        // Store 2 admin creates a "Manager" role of their own — must succeed.
        $store2Admin = $this->superAdminForStore(2, 90);

        $response = $this->actingAs($store2Admin)->post(route('users.roles.store'), [
            'role_name' => 'Manager',
            'description' => 'Store 2 manager',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('users.roles'));

        $this->assertDatabaseHas('db_roles', ['store_id' => 2, 'role_name' => 'Manager']);

        // Store 1's row is untouched. Read it with the global store scope bypassed —
        // the acting user is a store-2 admin, so StoreScoped (correctly) hides it from
        // an ordinary query. This is the Step 2 scoping working, not a regression.
        $this->assertSame(1, DbRole::allStores()->where('store_id', 1)->where('role_name', 'Manager')->count());
    }

    public function test_same_store_duplicate_role_name_is_blocked_by_validation(): void
    {
        $admin = $this->superAdminForStore(1, 91);

        // First "Manager" in store 1 — allowed.
        $this->actingAs($admin)->post(route('users.roles.store'), [
            'role_name' => 'Manager',
        ])->assertSessionHasNoErrors();

        // Second "Manager" in the SAME store — blocked.
        $response = $this->actingAs($admin)->post(route('users.roles.store'), [
            'role_name' => 'Manager',
        ]);

        $response->assertSessionHasErrors('role_name');
        $this->assertSame(1, DbRole::where('store_id', 1)->where('role_name', 'Manager')->count());
    }

    public function test_edit_keeping_own_name_succeeds(): void
    {
        $admin = $this->superAdminForStore(1, 92);

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Accountant', 'status' => 1]);

        // Re-submitting the role's own name (the ignore($id) path) must pass.
        $response = $this->actingAs($admin)->put(route('users.roles.update', $role->id), [
            'role_name' => 'Accountant',
            'description' => 'Now with a description',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame('Accountant', $role->fresh()->role_name);
        $this->assertSame('Now with a description', $role->fresh()->description);
    }

    public function test_edit_renaming_to_another_roles_name_in_same_store_is_blocked(): void
    {
        $admin = $this->superAdminForStore(1, 93);

        DbRole::create(['store_id' => 1, 'role_name' => 'Accountant', 'status' => 1]);
        $other = DbRole::create(['store_id' => 1, 'role_name' => 'Auditor', 'status' => 1]);

        $response = $this->actingAs($admin)->put(route('users.roles.update', $other->id), [
            'role_name' => 'Accountant',
        ]);

        $response->assertSessionHasErrors('role_name');
        $this->assertSame('Auditor', $other->fresh()->role_name);
    }

    public function test_edit_renaming_to_a_name_used_only_in_another_store_is_allowed(): void
    {
        $admin = $this->superAdminForStore(1, 94);

        // "Manager" exists in store 2 only.
        DbRole::create(['store_id' => 2, 'role_name' => 'Manager', 'status' => 1]);

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Supervisor', 'status' => 1]);

        $response = $this->actingAs($admin)->put(route('users.roles.update', $role->id), [
            'role_name' => 'Manager',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Manager', $role->fresh()->role_name);
    }

    /**
     * DB-level defence-in-depth: even bypassing the controller entirely (raw
     * model write), a same-store duplicate must be rejected by the composite
     * unique index uq_db_roles_store_role_name.
     */
    public function test_database_composite_unique_rejects_raw_same_store_duplicate(): void
    {
        DbRole::create(['store_id' => 1, 'role_name' => 'Manager', 'status' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DbRole::create(['store_id' => 1, 'role_name' => 'Manager', 'status' => 1]);
    }

    public function test_database_composite_unique_allows_raw_cross_store_same_name(): void
    {
        DbRole::create(['store_id' => 1, 'role_name' => 'Manager', 'status' => 1]);
        DbRole::create(['store_id' => 2, 'role_name' => 'Manager', 'status' => 1]);

        $this->assertSame(2, DbRole::where('role_name', 'Manager')->count());
    }

    /**
     * Driver-portable index assertion (Schema::getIndexes works on MySQL AND the
     * sqlite :memory: test connection, unlike SHOW INDEX).
     */
    public function test_composite_unique_index_exists(): void
    {
        $index = collect(\Illuminate\Support\Facades\Schema::getIndexes('db_roles'))
            ->firstWhere('name', 'uq_db_roles_store_role_name');

        $this->assertNotNull($index, 'Composite index uq_db_roles_store_role_name must exist.');
        $this->assertSame(['store_id', 'role_name'], $index['columns']);
        $this->assertTrue((bool) $index['unique'], 'The composite index must be UNIQUE.');
    }
}
