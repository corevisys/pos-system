<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role Management — permissions-matrix DISPLAY filter.
 *
 * ADD-ON to the RolePolicy::update() guard + the RoleController permission-grant
 * subset guard. Those stop an actor GRANTING a slug they don't hold; this covers
 * the separate display requirement: a permission item the acting user does not
 * themselves hold must not be RENDERED at all on the Role Management matrix
 * (create/edit) or the permission list on the view page.
 *
 * The filter reuses the subset guard's single source of truth — this test also
 * asserts that source is User::effectivePermissions() (null = unrestricted for a
 * genuine super admin), so the UI filter and the server-side guard cannot drift.
 *
 * Assertions inspect the raw HTML (assertSee/assertDontSee on the checkbox
 * `value="<slug>"` attribute) to prove items are absent, not merely hidden.
 */
class RolePermissionDisplayFilterTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = DbStore::create([
            'id' => 1, 'store_name' => 'Display Filter Store', 'status' => 1, 'mobile' => '01700000008',
        ]);
    }

    /** A Branch Admin holding exactly $permissions (never globally privileged). */
    private function branchAdmin(array $permissions): User
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

    /** A genuine Developer/super-admin identity (unrestricted). */
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

    /** An ordinary editable role with the given stored permission slugs. */
    private function roleWith(array $permissions): DbRole
    {
        $role = DbRole::create([
            'store_id' => 1, 'role_name' => 'Target ' . uniqid(), 'status' => 1, 'is_super_admin' => false,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);

        return $role;
    }

    /* ──────────── single source of truth ───────────── */

    public function test_effective_permissions_is_null_for_a_super_admin_and_a_slug_array_for_others(): void
    {
        $this->assertNull($this->developer()->effectivePermissions(), 'Super admin must be unrestricted (null).');

        $admin = $this->branchAdmin(['sales_view', 'roles_edit']);
        $this->assertSame(['sales_view', 'roles_edit'], $admin->effectivePermissions());
    }

    /* ───────────── edit matrix ───────────── */

    public function test_branch_admin_edit_matrix_omits_permissions_they_do_not_hold(): void
    {
        $admin = $this->branchAdmin(['dashboard_view', 'roles_view', 'roles_edit', 'sales_view']);
        $target = $this->roleWith(['sales_view']);

        $response = $this->actingAs($admin)->get(route('users.roles.edit', $target->id));
        $response->assertOk();

        // Held slug renders (as a real checkbox value in the HTML).
        $response->assertSee('value="sales_view"', false);

        // Unheld matrix slugs must be absent from the markup entirely.
        $response->assertDontSee('value="sales_add"', false);
        $response->assertDontSee('value="items_add"', false);
        $response->assertDontSee('value="purchase_add"', false);
    }

    public function test_developer_edit_matrix_renders_the_full_unfiltered_list(): void
    {
        $developer = $this->developer();
        $target = $this->roleWith(['sales_view']);

        $response = $this->actingAs($developer)->get(route('users.roles.edit', $target->id));
        $response->assertOk();

        // Full matrix — including slugs a Branch Admin would never hold.
        $response->assertSee('value="sales_view"', false);
        $response->assertSee('value="sales_add"', false);
        $response->assertSee('value="items_add"', false);
    }

    /* ───────────── create matrix ───────────── */

    public function test_branch_admin_create_matrix_omits_permissions_they_do_not_hold(): void
    {
        $admin = $this->branchAdmin(['dashboard_view', 'roles_view', 'roles_add', 'sales_view']);

        $response = $this->actingAs($admin)->get(route('users.roles.create'));
        $response->assertOk();

        $response->assertSee('value="sales_view"', false);
        $response->assertDontSee('value="sales_add"', false);
        $response->assertDontSee('value="items_add"', false);
    }

    /* ───────────── view page: overlap only ───────────── */

    public function test_branch_admin_view_shows_only_the_overlapping_subset_of_a_richer_role(): void
    {
        $admin = $this->branchAdmin(['dashboard_view', 'roles_view', 'sales_view']);
        // The role itself holds MORE than the actor — actor must see only the overlap.
        $rich = $this->roleWith(['sales_view', 'sales_add', 'items_add']);

        $response = $this->actingAs($admin)->get(route('users.roles.show', $rich->id));
        $response->assertOk();

        // Match the permission-card node specifically: the slug is emitted as
        // <p ...>slug</p> in the permissions grid. A page-wide substring would
        // collide with the layout's inline APP_SHORTCUTS JS (page_key names).
        $response->assertSee('>sales_view</p>', false);   // overlap renders
        $response->assertDontSee('>sales_add</p>', false); // beyond the actor's set → absent
        $response->assertDontSee('>items_add</p>', false); // beyond the actor's set → absent
        $response->assertSee('1 Permissions');            // count reflects the visible overlap
    }

    public function test_developer_view_shows_the_roles_full_permission_list(): void
    {
        $developer = $this->developer();
        $rich = $this->roleWith(['sales_view', 'sales_add', 'items_add']);

        $response = $this->actingAs($developer)->get(route('users.roles.show', $rich->id));
        $response->assertOk();

        $response->assertSee('>sales_view</p>', false);
        $response->assertSee('>sales_add</p>', false);
        $response->assertSee('>items_add</p>', false);
        $response->assertSee('3 Permissions');
    }

    /* ───────────── display filter does not weaken the guard ───────────── */

    public function test_saving_still_rejects_an_unheld_permission_despite_the_filter(): void
    {
        // Actor cannot even see sales_add in the matrix, but if it is submitted
        // directly the server-side subset guard must still reject it.
        $admin = $this->branchAdmin(['dashboard_view', 'roles_view', 'roles_edit', 'sales_view']);
        $target = $this->roleWith(['sales_view']);

        $this->actingAs($admin)
            ->put(route('users.roles.update', $target->id), [
                'role_name' => $target->role_name,
                'permissions' => ['sales_add'], // not held by the actor
            ])
            ->assertStatus(403);

        $this->assertSame(['sales_view'], $target->fresh()->permissions->permissions);
    }
}