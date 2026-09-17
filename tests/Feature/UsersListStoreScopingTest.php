<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Users List / User-record store scoping.
 *
 * BUG: UserController::index() never filtered by store_id, and the User model
 * deliberately has NO StoreScoped trait (its role relation backs authentication).
 * Every branch admin therefore saw EVERY store's users. The list (and its stat
 * cards) and the per-record show/edit/update/delete/toggle endpoints are now
 * scoped to the acting store; cross-store ids return 404 (parallel to
 * RoleController::findActingStoreRole()).
 *
 * Roles are per-store by design and remain store-scoped via the DbRole
 * StoreScoped trait — asserted here so a future change cannot silently regress it.
 *
 * Mirrors the conventions of RoleStoreScopingAndGuardsTest and
 * InvoiceAndGlobalSearchStoreScopingTest.
 */
class UsersListStoreScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store One', 'status' => 1, 'mobile' => '01711111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store Two', 'status' => 1, 'mobile' => '01722222222']);
    }

    /** An ordinary branch admin scoped to $storeId with the given permission slugs. */
    private function branchAdmin(int $storeId, array $permissions = ['users_view', 'users_edit', 'users_delete']): User
    {
        $role = DbRole::create([
            'store_id' => $storeId,
            'role_name' => 'Branch Admin ' . $storeId . ' ' . uniqid(),
            'status' => 1,
        ]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => $storeId,
            'permissions' => $permissions,
        ]);

        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    /** A genuine cross-store identity (Developer/super admin). */
    private function crossStoreAdmin(int $storeId): User
    {
        $role = DbRole::forceCreate([
            'store_id' => $storeId,
            'role_name' => 'Developer ' . uniqid(),
            'status' => 1,
            'is_super_admin' => true,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => $storeId, 'permissions' => []]);

        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    private function otherStoreUser(string $username): User
    {
        return User::factory()->create([
            'store_id' => 2,
            'username' => $username,
            'first_name' => 'StoreTwo',
            'last_name' => 'Only',
        ]);
    }

    /* ───────────────── List isolation (both directions) ───────────────── */

    public function test_users_list_shows_only_the_acting_stores_users(): void
    {
        $ownUser = User::factory()->create(['store_id' => 1, 'username' => 'store_one_only']);
        $this->otherStoreUser('store_two_only');

        $admin = $this->branchAdmin(1);

        $response = $this->actingAs($admin)->get(route('users.list'));
        $response->assertOk();

        $listed = collect($response->viewData('users')->items())->pluck('username')->all();

        $this->assertContains('store_one_only', $listed, 'The acting store\'s own user must be listed.');
        $this->assertNotContains('store_two_only', $listed, 'A store-2 user leaked into the store-1 Users List.');
        $this->assertNotContains('store_two_only', $listed);
    }

    public function test_users_list_is_isolated_in_the_other_direction(): void
    {
        $storeOneUser = User::factory()->create(['store_id' => 1, 'username' => 'store_one_secret']);
        $this->otherStoreUser('store_two_visible');

        $admin = $this->branchAdmin(2);

        $response = $this->actingAs($admin)->get(route('users.list'));
        $response->assertOk();

        $listed = collect($response->viewData('users')->items())->pluck('username')->all();

        $this->assertContains('store_two_visible', $listed, 'The acting store\'s own user must be listed.');
        $this->assertNotContains('store_one_secret', $listed, 'A store-1 user leaked into the store-2 Users List.');
    }

    public function test_users_list_stats_are_store_scoped(): void
    {
        User::factory()->create(['store_id' => 1, 'username' => 's1_u1', 'status' => 1]);
        User::factory()->create(['store_id' => 1, 'username' => 's1_u2', 'status' => 0]);
        User::factory()->create(['store_id' => 2, 'username' => 's2_u1', 'status' => 1]);

        $admin = $this->branchAdmin(1); // +1 for the admin themselves = 3 store-1 users

        $response = $this->actingAs($admin)->get(route('users.list'));
        $response->assertOk();

        $stats = $response->viewData('stats');

        // Before the fix, 'total' counted every store's users.
        $this->assertSame(
            User::where('store_id', 1)->count(),
            $stats['total'],
            'The total stat must count only the acting store\'s users.'
        );
        $this->assertSame(2, $stats['active'], 'Active stat must be store-scoped.');
        $this->assertSame(1, $stats['inactive'], 'Inactive stat must be store-scoped.');
        $this->assertSame(1, User::where('store_id', 2)->count(), 'Store 2 user must exist but never be counted.');
    }

    /* ───────────────── Cross-store IDOR on per-record endpoints ───────────────── */

    public function test_cross_store_user_show_returns_404(): void
    {
        $target = $this->otherStoreUser('cross_store_target');

        $this->actingAs($this->branchAdmin(1))
            ->get(route('users.show', $target->id))
            ->assertNotFound();
    }

    public function test_cross_store_user_edit_update_delete_and_toggle_return_404(): void
    {
        $admin = $this->branchAdmin(1);
        $target = $this->otherStoreUser('cross_store_mutable');

        // edit
        $this->actingAs($admin)->get(route('users.edit', $target->id))->assertNotFound();

        // update
        $this->actingAs($admin)->post(route('users.update', $target->id), [
            'username' => 'hacked', 'name' => 'Hacked', 'first_name' => 'H', 'last_name' => 'X',
            'email' => 'hacked@example.com', 'role_id' => $target->role_id, 'status' => 1,
        ])->assertNotFound();

        // toggle status
        $this->actingAs($admin)->patch(route('users.toggle.status', $target->id))->assertNotFound();

        // delete
        $this->actingAs($admin)->delete(route('users.delete', $target->id))->assertNotFound();

        // The store-2 user is entirely untouched.
        $fresh = User::withoutGlobalScopes()->find($target->id);
        $this->assertNotNull($fresh, 'Store-2 user must still exist.');
        $this->assertSame('cross_store_mutable', $fresh->username, 'Store-2 user must not have been renamed.');
        $this->assertSame(1, (int) $fresh->status, 'Store-2 user status must be unchanged.');
    }

    public function test_same_store_user_show_still_works(): void
    {
        $admin = $this->branchAdmin(1);
        $ownUser = User::factory()->create(['store_id' => 1, 'username' => 'same_store_user']);

        $this->actingAs($admin)
            ->get(route('users.show', $ownUser->id))
            ->assertOk();
    }

    /* ───────────────── Cross-store identities keep full visibility ───────────────── */

    public function test_cross_store_identity_sees_all_stores_users(): void
    {
        User::factory()->create(['store_id' => 1, 'username' => 's1_visible_to_owner']);
        $this->otherStoreUser('s2_visible_to_owner');

        $admin = $this->crossStoreAdmin(1);

        $response = $this->actingAs($admin)->get(route('users.list'));
        $response->assertOk();

        $listed = collect($response->viewData('users')->items())->pluck('username')->all();

        $this->assertContains('s1_visible_to_owner', $listed);
        $this->assertContains('s2_visible_to_owner', $listed, 'A cross-store identity must still see every store.');
    }

    /* ───────────────── Roles stay per-store (no regression) ───────────────── */

    public function test_roles_list_remains_store_scoped(): void
    {
        DbRole::create(['store_id' => 2, 'role_name' => 'StoreTwoOnlyRole', 'status' => 1]);
        DbRole::create(['store_id' => 1, 'role_name' => 'StoreOneOnlyRole', 'status' => 1]);

        $admin = $this->branchAdmin(1, ['roles_view']);

        $response = $this->actingAs($admin)->get(route('users.roles'));
        $response->assertOk();

        $listed = collect($response->viewData('roles')->items())->pluck('role_name')->all();

        $this->assertContains('StoreOneOnlyRole', $listed);
        $this->assertNotContains('StoreTwoOnlyRole', $listed, 'Cross-store role leaked into the list.');
    }
}