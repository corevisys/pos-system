<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role Management — protected super-admin role + permission-escalation guards.
 *
 * BUG: RolePolicy guarded the super-admin ROLE only against delete()
 * (`&& !$role->is_super_admin`). update()/view() had no such guard, so a Branch
 * Admin (is_super_admin = false) holding roles_edit could open the edit form for
 * the Developer/system role, rename it, and rewrite its permission set — and the
 * permissions matrix let them grant slugs they do not themselves hold.
 *
 * The three-role model is unchanged: Branch Admin (per-store, slug-gated), Owner
 * (cross-store READ only), Developer (the sole is_super_admin identity). The
 * "super-admin role record" is the record carrying is_super_admin = true; only the
 * Developer tier may manage it.
 */
class RoleAdminSuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = DbStore::create([
            'id' => 1, 'store_name' => 'Role Guard Store', 'status' => 1, 'mobile' => '01700000007',
        ]);
    }

    /**
     * A genuine Branch Admin: per-store, NOT globally privileged, NOT an Owner.
     * Holds exactly the given slugs.
     */
    private function branchAdmin(array $permissions = ['dashboard_view', 'roles_view', 'roles_edit', 'roles_delete']): User
    {
        $role = DbRole::create([
            'store_id' => 1, 'role_name' => 'Branch Admin ' . uniqid(), 'status' => 1,
            'is_super_admin' => false, 'is_owner' => false,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);

        return User::factory()->create([
            'store_id' => 1, 'role_id' => $role->id, 'status' => 1, 'email_verified_at' => now(),
        ]);
    }

    /** A genuine Developer/super-admin identity (the only tier that may manage it). */
    private function developer(): User
    {
        $role = DbRole::create([
            'store_id' => 1, 'role_name' => 'Developer ' . uniqid(), 'status' => 1,
            'is_super_admin' => true, 'is_owner' => false,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => []]);

        return User::factory()->create([
            'store_id' => 1, 'role_id' => $role->id, 'status' => 1, 'email_verified_at' => now(),
        ]);
    }

    /** The protected record: carries the authoritative super-admin flag. */
    private function protectedRole(): DbRole
    {
        $role = DbRole::create([
            'store_id' => 1, 'role_name' => 'System Developer Role ' . uniqid(), 'status' => 1,
            'is_super_admin' => true,
        ]);
        DbPermission::create([
            'role_id' => $role->id, 'store_id' => 1, 'permissions' => ['database_backup'],
        ]);

        return $role;
    }

    /** An ordinary, editable, non-super-admin role. */
    private function plainRole(): DbRole
    {
        return DbRole::create([
            'store_id' => 1, 'role_name' => 'Manager ' . uniqid(), 'status' => 1,
            'is_super_admin' => false,
        ]);
    }

    /* ───────────────── Branch Admin blocked from the protected role ───────────────── */

    public function test_branch_admin_cannot_open_the_super_admin_role_edit_form(): void
    {
        $admin = $this->branchAdmin();
        $protected = $this->protectedRole();

        $this->actingAs($admin)
            ->get(route('users.roles.edit', $protected->id))
            ->assertStatus(403);
    }

    public function test_branch_admin_cannot_update_the_super_admin_role_via_direct_request(): void
    {
        $admin = $this->branchAdmin();
        $protected = $this->protectedRole();
        $before = $protected->role_name;

        // Server-enforced: a direct PUT (not just the UI) must be refused.
        $this->actingAs($admin)
            ->put(route('users.roles.update', $protected->id), [
                'role_name' => 'PWNED SUPER ROLE',
                'description' => 'hijacked',
            ])
            ->assertStatus(403);

        $this->assertSame($before, $protected->fresh()->role_name, 'A super-admin role must not be renameable by a branch admin.');
    }

    public function test_branch_admin_cannot_wipe_or_rewrite_super_admin_role_permissions(): void
    {
        $admin = $this->branchAdmin();
        $protected = $this->protectedRole();

        $this->actingAs($admin)
            ->put(route('users.roles.update', $protected->id), [
                'role_name' => $protected->role_name,
                'permissions' => [],
            ])
            ->assertStatus(403);

        $this->assertSame(
            ['database_backup'],
            $protected->fresh()->permissions->permissions,
            'A super-admin role\'s permissions must not be rewritable by a branch admin.'
        );
    }

    public function test_branch_admin_cannot_delete_the_super_admin_role(): void
    {
        $admin = $this->branchAdmin();
        $protected = $this->protectedRole();

        $this->actingAs($admin)
            ->delete(route('users.roles.destroy', $protected->id))
            ->assertStatus(403);

        $this->assertNotNull(DbRole::allStores()->find($protected->id), 'A super-admin role must never be deletable.');
    }

    public function test_branch_admin_list_does_not_render_an_edit_action_for_the_super_admin_role(): void
    {
        $admin = $this->branchAdmin();
        $protected = $this->protectedRole();

        $response = $this->actingAs($admin)->get(route('users.roles'));
        $response->assertOk();

        // The row stays visible (read-only), but no actionable edit link is emitted.
        $response->assertSee($protected->role_name);
        $response->assertDontSee(route('users.roles.edit', $protected->id), false);
    }

    /* ───────────────── Permission-escalation guard ───────────────── */

    public function test_branch_admin_cannot_grant_permissions_they_do_not_hold(): void
    {
        $admin = $this->branchAdmin(['roles_view', 'roles_edit']); // does NOT hold database_backup
        $plain = $this->plainRole();

        $this->actingAs($admin)
            ->put(route('users.roles.update', $plain->id), [
                'role_name' => $plain->role_name,
                'permissions' => ['database_backup'],
            ])
            ->assertStatus(403);

        $this->assertNull(
            $plain->fresh()->permissions,
            'A branch admin must not be able to grant a permission they do not hold.'
        );
    }

    public function test_branch_admin_can_still_assign_permissions_within_their_own_set(): void
    {
        $admin = $this->branchAdmin(['users_view', 'roles_view', 'roles_edit']);
        $plain = $this->plainRole();

        $this->actingAs($admin)
            ->put(route('users.roles.update', $plain->id), [
                'role_name' => 'Renamed Within Authority',
                'permissions' => ['users_view'],
            ])
            ->assertRedirect();

        $this->assertSame('Renamed Within Authority', $plain->fresh()->role_name);
        $this->assertSame(['users_view'], $plain->fresh()->permissions->permissions);
    }

    public function test_branch_admin_cannot_mint_a_super_admin_role_on_create(): void
    {
        $admin = $this->branchAdmin(['roles_view', 'roles_add']);

        $this->actingAs($admin)->post(route('users.roles.store'), [
            'role_name' => 'Escalated ' . uniqid(),
            'is_super_admin' => 1,
        ]);

        $created = DbRole::allStores()->where('role_name', 'like', 'Escalated %')->first();
        if ($created) {
            $this->assertFalse((bool) $created->is_super_admin, 'A branch admin must never mint a super-admin role.');
        }
    }

    /* ───────────────── The Developer tier is NOT over-locked ───────────────── */

    public function test_developer_can_still_manage_the_super_admin_role(): void
    {
        $developer = $this->developer();
        $protected = $this->protectedRole();
        $otherProtected = $this->protectedRole();

        // Rename the protected role.
        $this->actingAs($developer)
            ->put(route('users.roles.update', $protected->id), [
                'role_name' => 'Developer Renamed',
            ])
            ->assertRedirect();
        $this->assertSame('Developer Renamed', $protected->fresh()->role_name);

        // Rewrite its permissions.
        $this->actingAs($developer)
            ->put(route('users.roles.update', $protected->id), [
                'role_name' => 'Developer Renamed',
                'permissions' => ['database_backup', 'roles_edit'],
            ])
            ->assertRedirect();
        $this->assertSame(['database_backup', 'roles_edit'], $protected->fresh()->permissions->permissions);

        // Edit form is reachable.
        $this->actingAs($developer)
            ->get(route('users.roles.edit', $otherProtected->id))
            ->assertOk();
    }
}