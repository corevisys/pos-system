<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-3 — db_roles.is_super_admin is the authoritative global-privilege flag.
 *
 * Verifies:
 *  - isSuperAdmin() reads the flag, not role_name / role_id;
 *  - a role named "Super Admin" WITHOUT the flag is NOT a super admin;
 *  - RolePolicy's delete-guard blocks deleting a super-admin role by flag;
 *  - a non-super-admin cannot grant themselves the flag via the Roles UI endpoint.
 */
class SuperAdminFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Flag Store', 'status' => 1, 'mobile' => '01711111112']);
    }

    private function role(string $name, bool $isSuperAdmin, int $id = 0): DbRole
    {
        $attrs = [
            'role_name' => $name,
            'status' => 1,
            'store_id' => 1,
            'is_super_admin' => $isSuperAdmin,
        ];

        if ($id > 0) {
            $attrs['id'] = $id;
            return DbRole::forceCreate($attrs);
        }

        return DbRole::create($attrs);
    }

    public function test_is_super_admin_true_for_flagged_role(): void
    {
        $role = $this->role('Super Admin', true, 1);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => $role->role_name]);

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertTrue($user->fresh()->hasPermission('anything_at_all'));
    }

    public function test_role_named_super_admin_without_flag_is_not_super_admin(): void
    {
        // The exact trap this change removes: name matches, flag is false.
        $role = $this->role('Super Admin', false, 2);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => []]);

        $this->assertFalse($user->fresh()->isSuperAdmin());
        $this->assertFalse($user->fresh()->hasPermission('sales_add'));
    }

    public function test_role_id_one_without_flag_is_not_super_admin(): void
    {
        // The other half of the trap: id === 1 but flag false.
        $role = $this->role('Some Role', false, 1);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Some Role']);

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    public function test_role_policy_delete_guard_blocks_super_admin_role_by_flag(): void
    {
        // A super-admin role with an id FAR from 1 must still be undeletable.
        $superRole = $this->role('Super Admin', true, 77);
        $admin = User::factory()->create(['store_id' => 1, 'role_id' => $superRole->id, 'role_name' => 'Super Admin']);

        $this->actingAs($admin);
        $this->assertFalse($admin->can('delete', $superRole));
    }

    public function test_role_policy_allows_deleting_a_non_super_admin_role(): void
    {
        $superRole = $this->role('Super Admin', true, 1);
        $admin = User::factory()->create(['store_id' => 1, 'role_id' => $superRole->id, 'role_name' => 'Super Admin']);

        $plainRole = $this->role('Plain Role', false, 5);

        $this->actingAs($admin);
        $this->assertTrue($admin->can('delete', $plainRole));
    }

    public function test_non_super_admin_cannot_grant_themselves_super_admin_via_role_create(): void
    {
        // The requester holds roles_add but is NOT a super admin. Gate::authorize
        // currently restricts role CRUD to super admins, so assert the flag is not
        // persisted even if the route is somehow reached.
        $role = $this->role('Role Maker', false, 40);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => ['roles_add', 'roles_edit'],
        ]);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => $role->role_name]);

        $result = $this->actingAs($user)->post(route('users.roles.store'), [
            'role_name' => 'Escalated ' . uniqid(),
            'is_super_admin' => 1,
        ]);

        // Either blocked outright (403) or created WITHOUT the flag — never granted.
        $created = DbRole::where('role_name', 'like', 'Escalated %')->first();
        if ($created) {
            $this->assertFalse((bool) $created->is_super_admin, 'Non-super-admin must not be able to mint a super-admin role.');
        } else {
            $this->assertContains($result->status(), [403, 302, 422]);
        }
    }

    public function test_non_super_admin_cannot_escalate_an_existing_role(): void
    {
        $role = $this->role('Escalation Target', false, 41);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => ['roles_edit'],
        ]);
        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => $role->role_name]);

        $this->actingAs($user)->put(route('users.roles.update', $role->id), [
            'role_name' => $role->role_name,
            'is_super_admin' => 1,
        ]);

        $this->assertFalse((bool) $role->fresh()->is_super_admin, 'Non-super-admin must not be able to set is_super_admin on any role.');
    }

    /**
     * VERIFICATION (Item 1): the privilege-escalation guard must hold even when a
     * non-super-admin tries the highest-value name. `role_name` uniqueness is
     * GLOBAL in the validation rule, but the security property is: naming a role
     * "Super Admin" must never confer the privilege.
     *
     * This asserts BOTH the HTTP outcome and the persisted row, so it fails loudly
     * if some other layer (model hook / policy / guard) ever regresses.
     */
    public function test_non_super_admin_cannot_mint_a_super_admin_role_by_naming_it_super_admin(): void
    {
        // Simulate a PRODUCTION request context, where the name-based seeding hook
        // is disabled (it is enabled only by the test bootstrap for fixture
        // convenience). This is the context that actually ships.
        DbRole::seedSuperAdminByName(false);

        $role = $this->role('Role Author', false, 42);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => ['roles_add', 'roles_edit'],
        ]);
        $attacker = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => $role->role_name]);

        $response = $this->actingAs($attacker)->post(route('users.roles.store'), [
            'role_name' => 'Super Admin',
            'is_super_admin' => 1,
        ]);

        $created = DbRole::where('role_name', 'Super Admin')
            ->where('id', '!=', $role->id)
            ->first();

        // Hard requirement: no super-admin role may be produced by this request,
        // and the attacker must not end up a super admin.
        if ($created) {
            $this->assertFalse(
                (bool) $created->is_super_admin,
                'CONFIRMED VULNERABILITY: a non-super-admin created a role named "Super Admin" and it received is_super_admin = true.'
            );
        }

        $this->assertFalse($attacker->fresh()->isSuperAdmin(), 'Attacker must not become a super admin.');
        $this->assertContains($response->status(), [403, 302, 422]);
    }

    /**
     * VERIFICATION (Item 1): does the DbRole creation-time name hook fire for
     * ORDINARY application code (not just seeders/migrations)?
     *
     * Calling DbRole::create() directly is the same code path RoleController::store()
     * reaches after its guard. If the hook sets the flag from the NAME alone, then
     * the guard is bypassed by the model layer and any code path — including a
     * future relaxed `create` policy — becomes an escalation vector.
     */
    public function test_creation_time_hook_does_not_grant_super_admin_from_the_name_alone(): void
    {
        // PRODUCTION context: the name-based seeding hook must be OFF by default,
        // so plain DbRole::create() can never confer global privileges.
        DbRole::seedSuperAdminByName(false);

        $created = DbRole::create([
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->assertFalse(
            (bool) $created->fresh()->is_super_admin,
            'CONFIRMED VULNERABILITY: DbRole::create() with role_name = "Super Admin" auto-granted is_super_admin.'
        );
    }

    /**
     * Companion assertion: the hook IS available when explicitly opted in (the
     * test/seed context), proving the fix is a scoping change and not a removal
     * that would break the existing fixture corpus.
     */
    public function test_name_based_seeding_hook_works_when_explicitly_opted_in(): void
    {
        DbRole::seedSuperAdminByName(true);

        $created = DbRole::create([
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->assertTrue((bool) $created->fresh()->is_super_admin);

        // Restore the test-bootstrap default for subsequent tests.
        DbRole::seedSuperAdminByName(true);
    }
}
