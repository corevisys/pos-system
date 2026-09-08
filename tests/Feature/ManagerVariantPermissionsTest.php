<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbVariant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manager-role Variants permission grant verification.
 *
 * The audit flagged Manager had brand_* and items_category_* grants but no
 * variant_* slugs at all — Variants was entirely invisible to Manager. Decision
 * (confirmed): grant Manager the same level as Category/Brand, including delete.
 * Added variant_view / variant_add / variant_edit / variant_delete to the Manager
 * role in RolePermissionSeeder. This suite seeds the real seeder and proves the
 * end-to-end capability plus confirms no other role's grants were altered.
 */
class ManagerVariantPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);

        // Run the real seeder so the Manager grant list is exactly production data.
        $this->seed(RolePermissionSeeder::class);
    }

    private function managerUser(): User
    {
        $role = DbRole::where('role_name', 'Manager')->firstOrFail();
        return User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);
    }

    public function test_manager_role_now_has_all_four_variant_slugs(): void
    {
        $permissions = DbPermission::where('role_id', DbRole::where('role_name', 'Manager')->first()->id)
            ->first()
            ->permissions;

        foreach (['variant_view', 'variant_add', 'variant_edit', 'variant_delete'] as $slug) {
            $this->assertContains($slug, $permissions, "Manager missing {$slug}");
        }
    }

    public function test_manager_can_view_variants_list_and_sidebar_link_visible(): void
    {
        $response = $this->actingAs($this->managerUser())->get(route('items.variants'));
        $response->assertOk();
        $response->assertSee('Variants');
        // The Variants sidebar link is only rendered when variant_view is granted.
        $response->assertSee(route('items.variants'));
    }

    public function test_manager_can_create_variant(): void
    {
        $this->actingAs($this->managerUser())
            ->post(route('items.variants.store'), ['variant_name' => 'Manager Created Variant'])
            ->assertRedirect(route('items.variants'));

        $this->assertDatabaseHas('db_variants', [
            'variant_name' => 'Manager Created Variant',
            'status' => 1,
            'store_id' => 1,
        ]);
    }

    public function test_manager_can_edit_variant(): void
    {
        $variant = DbVariant::create(['variant_name' => 'Before Edit', 'status' => 1, 'store_id' => 1]);

        $this->actingAs($this->managerUser())
            ->get(route('items.variants.edit', $variant->id))
            ->assertOk();

        $this->actingAs($this->managerUser())
            ->put(route('items.variants.update', $variant->id), ['variant_name' => 'After Edit', 'status' => 1])
            ->assertRedirect(route('items.variants'));

        $this->assertDatabaseHas('db_variants', ['id' => $variant->id, 'variant_name' => 'After Edit']);
    }

    public function test_manager_can_delete_variant(): void
    {
        $variant = DbVariant::create(['variant_name' => 'To Delete', 'status' => 1, 'store_id' => 1]);

        $this->actingAs($this->managerUser())
            ->from(route('items.variants'))
            ->delete(route('items.variants.destroy', $variant->id))
            ->assertRedirect(route('items.variants'));

        $this->assertDatabaseMissing('db_variants', ['id' => $variant->id]);
    }

    public function test_other_roles_grants_unchanged_by_manager_variant_addition(): void
    {
        $all = DbPermission::with('role')->get()->keyBy('role_id');

        // Super Admin & Admin get the full catalog (unchanged).
        foreach (['Super Admin', 'Admin'] as $name) {
            $role = DbRole::where('role_name', $name)->first();
            $perms = $all[$role->id]->permissions ?? [];
            $this->assertContains('variant_view', $perms);
            $this->assertContains('variant_delete', $perms);
        }

        // Salesman still has NO variant slugs (unchanged — read-only items access).
        $salesman = DbRole::where('role_name', 'Salesman')->first();
        $salesmanPerms = $all[$salesman->id]->permissions ?? [];
        $this->assertNotContains('variant_view', $salesmanPerms);
        $this->assertNotContains('variant_add', $salesmanPerms);
        $this->assertNotContains('variant_edit', $salesmanPerms);
        $this->assertNotContains('variant_delete', $salesmanPerms);

        // Cashier still has NO variant slugs (unchanged).
        $cashier = DbRole::where('role_name', 'Cashier')->first();
        $cashierPerms = $all[$cashier->id]->permissions ?? [];
        $this->assertNotContains('variant_view', $cashierPerms);
        $this->assertNotContains('variant_delete', $cashierPerms);
    }
}
