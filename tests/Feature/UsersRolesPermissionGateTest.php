<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Users/Roles permission-gate alignment.
 *
 * BASELINE (pre-fix): UserPolicy/RolePolicy gate on isSuperAdmin() ONLY, so a
 * non-super-admin granted users_edit / users_delete / roles_edit / roles_delete
 * was BLOCKED (403) — the slugs were decorative.
 *
 * POST-FIX: the Policies additionally honour hasPermission(<slug>), so those
 * slugs actually gate their actions, while isSuperAdmin() continues to bypass
 * every check (as everywhere else in the app). The existing guards are retained:
 * a super-admin ROLE is never deletable, and a user can still view/edit/delete
 * their own account context exactly as before.
 */
class UsersRolesPermissionGateTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = DbStore::create([
            'id' => 1, 'store_name' => 'UR Store', 'status' => 1, 'mobile' => '01700000001',
        ]);
    }

    /** A NON-super-admin actor with exactly $permissions. */
    private function actor(array $permissions): User
    {
        $role = DbRole::create([
            'store_id' => $this->store->id,
            'role_name' => 'UR Role ' . uniqid(),
            'status' => 1,
            'is_super_admin' => false,
        ]);
        DbPermission::create([
            'role_id' => $role->id, 'store_id' => $this->store->id, 'permissions' => $permissions,
        ]);

        return $this->makeUser($role->id);
    }

    /** A user with every field the update() validation requires populated. */
    private function makeUser(int $roleId, array $overrides = []): User
    {
        $suffix = substr(uniqid(), -6);

        return User::factory()->create(array_merge([
            'store_id'   => $this->store->id,
            'role_id'    => $roleId,
            'username'   => 'ur_user_' . $suffix,
            'name'       => 'UR User ' . $suffix,
            'first_name' => 'UR',
            'last_name'  => 'User' . $suffix,
            'status'     => 1,
        ], $overrides));
    }

    /** A genuine super admin. */
    private function superAdmin(): User
    {
        $role = DbRole::create([
            'store_id' => $this->store->id,
            'role_name' => 'UR Super ' . uniqid(),
            'status' => 1,
            'is_super_admin' => true,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => $this->store->id, 'permissions' => []]);

        return $this->makeUser($role->id);
    }

    private function makeTargetUser(): User
    {
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Target Role ' . uniqid(), 'status' => 1,
        ]);

        return $this->makeUser($role->id);
    }

    private function userUpdatePayload(User $target, string $name = 'Renamed Target'): array
    {
        return [
            'store_id'   => $this->store->id,
            'username'   => $target->username,
            'name'       => $name,
            'first_name' => 'Renamed',
            'last_name'  => 'Target',
            'email'      => $target->email,
            'role_id'    => $target->role_id,
            'status'     => 1,
        ];
    }

    /* ══════════════════ users_edit ══════════════════ */

    public function test_non_superadmin_with_users_edit_can_edit_another_user(): void
    {
        $editor = $this->actor(['users_view', 'users_edit']);
        $target = $this->makeTargetUser();

        $this->actingAs($editor)
            ->post(route('users.update', $target->id), $this->userUpdatePayload($target, 'Edited By Slug'))
            ->assertRedirect();

        $this->assertSame('Edited By Slug', $target->fresh()->name);
    }

    public function test_non_superadmin_without_users_edit_gets_403(): void
    {
        $editor = $this->actor(['users_view']); // no users_edit
        $target = $this->makeTargetUser();
        $before = $target->name;

        $this->actingAs($editor)
            ->post(route('users.update', $target->id), $this->userUpdatePayload($target, 'Should Not Apply'))
            ->assertStatus(403);

        $this->assertSame($before, $target->fresh()->name, 'User must be unchanged without users_edit.');
    }

    /* ══════════════════ users_delete ══════════════════ */

    public function test_non_superadmin_with_users_delete_can_delete_another_user(): void
    {
        $deleter = $this->actor(['users_view', 'users_delete']);
        $target = $this->makeTargetUser();

        $this->actingAs($deleter)
            ->delete(route('users.delete', $target->id))
            ->assertRedirect();

        $this->assertNull(User::find($target->id), 'Target must be deleted with users_delete.');
    }

    public function test_non_superadmin_without_users_delete_gets_403(): void
    {
        $deleter = $this->actor(['users_view']); // no users_delete
        $target = $this->makeTargetUser();

        $this->actingAs($deleter)
            ->delete(route('users.delete', $target->id))
            ->assertStatus(403);

        $this->assertNotNull(User::find($target->id), 'Target must survive without users_delete.');
    }

    /* ══════════════════ roles_edit ══════════════════ */

    public function test_non_superadmin_with_roles_edit_can_update_a_role(): void
    {
        $editor = $this->actor(['roles_view', 'roles_edit']);
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Editable Role ' . uniqid(), 'status' => 1,
        ]);

        $this->actingAs($editor)
            ->put(route('users.roles.update', $role->id), ['role_name' => 'Renamed By Slug'])
            ->assertRedirect();

        $this->assertSame('Renamed By Slug', $role->fresh()->role_name);
    }

    public function test_non_superadmin_without_roles_edit_gets_403(): void
    {
        $editor = $this->actor(['roles_view']); // no roles_edit
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Untouchable Role ' . uniqid(), 'status' => 1,
        ]);
        $before = $role->role_name;

        $this->actingAs($editor)
            ->put(route('users.roles.update', $role->id), ['role_name' => 'Should Not Apply'])
            ->assertStatus(403);

        $this->assertSame($before, $role->fresh()->role_name, 'Role must be unchanged without roles_edit.');
    }

    /* ══════════════════ roles_delete ══════════════════ */

    public function test_non_superadmin_with_roles_delete_can_delete_an_unused_role(): void
    {
        $deleter = $this->actor(['roles_view', 'roles_delete']);
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Deletable Role ' . uniqid(),
            'status' => 1, 'is_super_admin' => false,
        ]);

        $this->actingAs($deleter)
            ->delete(route('users.roles.destroy', $role->id))
            ->assertRedirect();

        $this->assertNull(DbRole::allStores()->find($role->id), 'Unused role must be deleted with roles_delete.');
    }

    public function test_non_superadmin_without_roles_delete_gets_403(): void
    {
        $deleter = $this->actor(['roles_view']); // no roles_delete
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Protected Role ' . uniqid(), 'status' => 1,
        ]);

        $this->actingAs($deleter)
            ->delete(route('users.roles.destroy', $role->id))
            ->assertStatus(403);

        $this->assertNotNull(DbRole::allStores()->find($role->id), 'Role must survive without roles_delete.');
    }

    /* ══════════════════ retained guards + superadmin control ══════════════════ */

    public function test_superadmin_still_bypasses_all_users_roles_checks(): void
    {
        $admin = $this->superAdmin();
        $target = $this->makeTargetUser();
        $role = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Admin Deletable ' . uniqid(),
            'status' => 1, 'is_super_admin' => false,
        ]);

        // Edit another user
        $this->actingAs($admin)
            ->post(route('users.update', $target->id), $this->userUpdatePayload($target, 'Admin Edited'))
            ->assertRedirect();
        $this->assertSame('Admin Edited', $target->fresh()->name);

        // Delete another user
        $this->actingAs($admin)->delete(route('users.delete', $target->id))->assertRedirect();
        $this->assertNull(User::find($target->id));

        // Update + delete a role
        $this->actingAs($admin)->put(route('users.roles.update', $role->id), ['role_name' => 'Admin Renamed'])->assertRedirect();
        $this->assertSame('Admin Renamed', $role->fresh()->role_name);

        $this->actingAs($admin)->delete(route('users.roles.destroy', $role->id))->assertRedirect();
        $this->assertNull(DbRole::allStores()->find($role->id));
    }

    public function test_super_admin_role_remains_undeletable_even_with_roles_delete(): void
    {
        // A non-super-admin holding roles_delete STILL must not delete a
        // super-admin role — the pre-existing RolePolicy guard must survive.
        $deleter = $this->actor(['roles_view', 'roles_delete']);
        $superRole = DbRole::create([
            'store_id' => $this->store->id, 'role_name' => 'Immutable Super ' . uniqid(),
            'status' => 1, 'is_super_admin' => true,
        ]);

        $this->actingAs($deleter)
            ->delete(route('users.roles.destroy', $superRole->id))
            ->assertStatus(403);

        $this->assertNotNull(DbRole::allStores()->find($superRole->id), 'A super-admin role must never be deletable.');
    }

    public function test_user_cannot_delete_their_own_account_via_the_admin_route(): void
    {
        // The self-delete guard must survive the alignment.
        $deleter = $this->actor(['users_view', 'users_delete']);

        $this->actingAs($deleter)
            ->delete(route('users.delete', $deleter->id))
            ->assertStatus(403);

        $this->assertNotNull(User::find($deleter->id));
    }
}
