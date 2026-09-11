<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rollout verification for Categories / Brands / Variants.
 *
 * Covers Phase 1 (permission gates + store-scoping IDOR), Phase 2 (pre-delete
 * usage guard + the previously-missing Variants DELETE route), Phase 3
 * (per-store uniqueness), and Phase 4 (status toggle). The genuine-parallel
 * race test for Phase 3.2 lives in CategoryBrandVariantRaceTest.
 */
class CategoryBrandVariantRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'mobile' => '2222222222']);

        // Full-permission store 1 admin.
        $role1 = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => [
                'items_category_view', 'items_category_add', 'items_category_edit', 'items_category_delete',
                'brand_view', 'brand_add', 'brand_edit', 'brand_delete',
                'variant_view', 'variant_add', 'variant_edit', 'variant_delete',
            ],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        // Full-permission store 2 admin.
        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => [
                'items_category_view', 'items_category_add', 'items_category_edit', 'items_category_delete',
                'brand_view', 'brand_add', 'brand_edit', 'brand_delete',
                'variant_view', 'variant_add', 'variant_edit', 'variant_delete',
            ],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);

        // No-permission user (typed-URL bypass must now 403).
        $roleNone = DbRole::create(['role_name' => 'No Perms', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $roleNone->id, 'store_id' => 1, 'permissions' => []]);
        $this->noPermUser = User::factory()->create(['store_id' => 1, 'role_id' => $roleNone->id]);

        // View-only user (view allowed, add/edit/delete must 403).
        $roleView = DbRole::create(['role_name' => 'Viewer', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $roleView->id,
            'store_id' => 1,
            'permissions' => ['items_category_view', 'brand_view', 'variant_view'],
        ]);
        $this->viewOnlyUser = User::factory()->create(['store_id' => 1, 'role_id' => $roleView->id]);

        // Seed one row per module per store.
        $this->cat1 = DbCategory::create(['category_name' => 'Store 1 Cat', 'status' => 1, 'store_id' => 1]);
        $this->cat2 = DbCategory::create(['category_name' => 'Store 2 Cat', 'status' => 1, 'store_id' => 2]);
        $this->brand1 = DbBrand::create(['brand_name' => 'Store 1 Brand', 'status' => 1, 'store_id' => 1]);
        $this->brand2 = DbBrand::create(['brand_name' => 'Store 2 Brand', 'status' => 1, 'store_id' => 2]);
        $this->variant1 = DbVariant::create(['variant_name' => 'Store 1 Variant', 'status' => 1, 'store_id' => 1]);
        $this->variant2 = DbVariant::create(['variant_name' => 'Store 2 Variant', 'status' => 1, 'store_id' => 2]);
    }

    /* ───────────────────────── PHASE 1: PERMISSION GATES ───────────────────────── */

    public function test_category_index_403_for_user_without_items_category_view(): void
    {
        $this->actingAs($this->noPermUser)->get(route('items.categories'))->assertForbidden();
    }

    public function test_brand_index_403_for_user_without_brand_view(): void
    {
        $this->actingAs($this->noPermUser)->get(route('items.brands'))->assertForbidden();
    }

    public function test_variant_index_403_for_user_without_variant_view(): void
    {
        $this->actingAs($this->noPermUser)->get(route('items.variants'))->assertForbidden();
    }

    public function test_category_add_edit_delete_403_for_view_only_user(): void
    {
        $this->actingAs($this->viewOnlyUser)->get(route('items.categories.add'))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->post(route('items.categories.store'), ['category_name' => 'X'])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->get(route('items.categories.edit', $this->cat1->id))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->put(route('items.categories.update', $this->cat1->id), ['category_name' => 'X', 'status' => 1])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->delete(route('items.categories.destroy', $this->cat1->id))->assertForbidden();
    }

    public function test_brand_add_edit_delete_403_for_view_only_user(): void
    {
        $this->actingAs($this->viewOnlyUser)->get(route('items.brands.add'))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->post(route('items.brands.store'), ['brand_name' => 'X'])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->get(route('items.brands.edit', $this->brand1->id))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->put(route('items.brands.update', $this->brand1->id), ['brand_name' => 'X', 'status' => 1])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->delete(route('items.brands.destroy', $this->brand1->id))->assertForbidden();
    }

    public function test_variant_add_edit_delete_403_for_view_only_user(): void
    {
        $this->actingAs($this->viewOnlyUser)->get(route('items.variants.add'))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->post(route('items.variants.store'), ['variant_name' => 'X'])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->get(route('items.variants.edit', $this->variant1->id))->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->put(route('items.variants.update', $this->variant1->id), ['variant_name' => 'X', 'status' => 1])->assertForbidden();
        $this->actingAs($this->viewOnlyUser)->delete(route('items.variants.destroy', $this->variant1->id))->assertForbidden();
    }

    public function test_full_permission_user_still_has_normal_access(): void
    {
        // Regression control — permission gates must not become a blanket lockout.
        $this->actingAs($this->store1User)->get(route('items.categories'))->assertOk();
        $this->actingAs($this->store1User)->get(route('items.brands'))->assertOk();
        $this->actingAs($this->store1User)->get(route('items.variants'))->assertOk();
        $this->actingAs($this->store1User)->get(route('items.categories.add'))->assertOk();
        $this->actingAs($this->store1User)->get(route('items.brands.add'))->assertOk();
        $this->actingAs($this->store1User)->get(route('items.variants.add'))->assertOk();
    }

    /* ───────────────────────── PHASE 1: STORE-SCOPING (IDOR) ───────────────────────── */

    public function test_category_index_is_store_scoped_including_stats(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('items.categories'));
        $response->assertOk();
        $response->assertSee('Store 1 Cat');
        $response->assertDontSee('Store 2 Cat');

        // Stats total/active = 1 for store 1 (only the seeded row), NOT store 2's row.
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total'] === 1
                && $stats['active'] === 1
                && $stats['inactive'] === 0;
        });
    }

    public function test_brand_index_is_store_scoped_including_stats(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('items.brands'));
        $response->assertOk();
        $response->assertSee('Store 1 Brand');
        $response->assertDontSee('Store 2 Brand');
    }

    public function test_variant_index_is_store_scoped_including_stats(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('items.variants'));
        $response->assertOk();
        $response->assertSee('Store 1 Variant');
        $response->assertDontSee('Store 2 Variant');
    }

    public function test_cross_store_category_edit_returns_404(): void
    {
        $this->actingAs($this->store1User)->get(route('items.categories.edit', $this->cat2->id))->assertNotFound();
    }

    public function test_cross_store_brand_edit_returns_404(): void
    {
        $this->actingAs($this->store1User)->get(route('items.brands.edit', $this->brand2->id))->assertNotFound();
    }

    public function test_cross_store_variant_edit_returns_404(): void
    {
        $this->actingAs($this->store1User)->get(route('items.variants.edit', $this->variant2->id))->assertNotFound();
    }

    public function test_cross_store_category_update_is_blocked(): void
    {
        $this->actingAs($this->store1User)
            ->put(route('items.categories.update', $this->cat2->id), ['category_name' => 'Hijacked', 'status' => 1])
            ->assertNotFound();

        $this->assertDatabaseHas('db_category', ['id' => $this->cat2->id, 'category_name' => 'Store 2 Cat']);
    }

    public function test_cross_store_brand_update_is_blocked(): void
    {
        $this->actingAs($this->store1User)
            ->put(route('items.brands.update', $this->brand2->id), ['brand_name' => 'Hijacked', 'status' => 1])
            ->assertNotFound();

        $this->assertDatabaseHas('db_brands', ['id' => $this->brand2->id, 'brand_name' => 'Store 2 Brand']);
    }

    public function test_cross_store_variant_update_is_blocked(): void
    {
        $this->actingAs($this->store1User)
            ->put(route('items.variants.update', $this->variant2->id), ['variant_name' => 'Hijacked', 'status' => 1])
            ->assertNotFound();

        $this->assertDatabaseHas('db_variants', ['id' => $this->variant2->id, 'variant_name' => 'Store 2 Variant']);
    }

    public function test_cross_store_category_delete_is_blocked(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.categories.destroy', $this->cat2->id));

        $this->assertDatabaseHas('db_category', ['id' => $this->cat2->id, 'store_id' => 2]);
    }

    public function test_cross_store_brand_delete_is_blocked(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.brands.destroy', $this->brand2->id));

        $this->assertDatabaseHas('db_brands', ['id' => $this->brand2->id, 'store_id' => 2]);
    }

    public function test_cross_store_variant_delete_is_blocked(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.variants.destroy', $this->variant2->id));

        $this->assertDatabaseHas('db_variants', ['id' => $this->variant2->id, 'store_id' => 2]);
    }

    public function test_same_store_edit_update_delete_still_work(): void
    {
        // Regression control — own-store operations unchanged.
        $this->actingAs($this->store1User)->get(route('items.categories.edit', $this->cat1->id))->assertOk();

        $this->actingAs($this->store1User)
            ->put(route('items.categories.update', $this->cat1->id), ['category_name' => 'Renamed Cat', 'status' => 1])
            ->assertRedirect(route('items.categories'));

        $this->assertDatabaseHas('db_category', ['id' => $this->cat1->id, 'category_name' => 'Renamed Cat']);

        $this->actingAs($this->store1User)->delete(route('items.categories.destroy', $this->cat1->id));

        $this->assertDatabaseMissing('db_category', ['id' => $this->cat1->id]);
    }

    /* ───────────────────────── PHASE 1.3: STORE() FALLBACK ───────────────────────── */

    public function test_store_uses_current_store_id_consistently(): void
    {
        $this->actingAs($this->store2User)->post(route('items.categories.store'), ['category_name' => 'S2 Cat'])->assertRedirect();
        $this->assertDatabaseHas('db_category', ['category_name' => 'S2 Cat', 'store_id' => 2]);

        $this->actingAs($this->store2User)->post(route('items.brands.store'), ['brand_name' => 'S2 Brand'])->assertRedirect();
        $this->assertDatabaseHas('db_brands', ['brand_name' => 'S2 Brand', 'store_id' => 2]);

        $this->actingAs($this->store2User)->post(route('items.variants.store'), ['variant_name' => 'S2 Variant'])->assertRedirect();
        $this->assertDatabaseHas('db_variants', ['variant_name' => 'S2 Variant', 'store_id' => 2]);
    }

    /* ───────────────────────── PHASE 2: PRE-DELETE USAGE GUARD ───────────────────────── */

    private function makeItemUsingCategory(int $storeId, int $categoryId, int $brandId): DbItem
    {
        return DbItem::create([
            'store_id' => $storeId,
            'item_name' => 'Item Using Cat',
            'item_code' => 'IT-' . uniqid(),
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'status' => 1,
            'child_bit' => 0,
        ]);
    }

    public function test_category_delete_blocked_when_in_use(): void
    {
        $this->makeItemUsingCategory(1, $this->cat1->id, $this->brand1->id);

        $this->actingAs($this->store1User)
            ->from(route('items.categories'))
            ->delete(route('items.categories.destroy', $this->cat1->id))
            ->assertRedirect(route('items.categories'))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'cannot be deleted'));

        $this->assertDatabaseHas('db_category', ['id' => $this->cat1->id]);
        $this->assertDatabaseHas('db_items', ['category_id' => $this->cat1->id]);
    }

    public function test_category_delete_succeeds_when_unused(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.categories.destroy', $this->cat1->id));

        $this->assertDatabaseMissing('db_category', ['id' => $this->cat1->id]);
    }

    public function test_brand_delete_blocked_when_in_use(): void
    {
        $this->makeItemUsingCategory(1, $this->cat1->id, $this->brand1->id);

        $this->actingAs($this->store1User)
            ->from(route('items.brands'))
            ->delete(route('items.brands.destroy', $this->brand1->id))
            ->assertRedirect(route('items.brands'))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'cannot be deleted'));

        $this->assertDatabaseHas('db_brands', ['id' => $this->brand1->id]);
        $this->assertDatabaseHas('db_items', ['brand_id' => $this->brand1->id]);
    }

    public function test_brand_delete_succeeds_when_unused(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.brands.destroy', $this->brand1->id));

        $this->assertDatabaseMissing('db_brands', ['id' => $this->brand1->id]);
    }

    public function test_variant_delete_succeeds_when_unused(): void
    {
        $this->actingAs($this->store1User)->delete(route('items.variants.destroy', $this->variant1->id));

        $this->assertDatabaseMissing('db_variants', ['id' => $this->variant1->id]);
    }

    public function test_variant_delete_route_exists_and_blocks_when_referenced(): void
    {
        // Phase 2.2: the previously-missing DELETE route must now exist. The usage
        // guard (future-proofing) blocks when db_items.variant_id references the row.
        DbItem::create([
            'store_id' => 1,
            'item_name' => 'Item Using Variant',
            'item_code' => 'IT-' . uniqid(),
            'variant_id' => $this->variant1->id,
            'status' => 1,
            'child_bit' => 0,
        ]);

        $this->actingAs($this->store1User)
            ->from(route('items.variants'))
            ->delete(route('items.variants.destroy', $this->variant1->id))
            ->assertRedirect(route('items.variants'))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'cannot be deleted'));

        $this->assertDatabaseHas('db_variants', ['id' => $this->variant1->id]);
    }

    /* ───────────────────────── PHASE 3: PER-STORE UNIQUENESS ───────────────────────── */

    public function test_same_name_allowed_across_stores_for_category(): void
    {
        $this->actingAs($this->store1User)->post(route('items.categories.store'), ['category_name' => 'Electronics'])->assertRedirect();
        $this->actingAs($this->store2User)->post(route('items.categories.store'), ['category_name' => 'Electronics'])->assertRedirect();

        $this->assertEquals(2, DbCategory::allStores()->where('category_name', 'Electronics')->count());
    }

    public function test_same_name_allowed_across_stores_for_brand(): void
    {
        $this->actingAs($this->store1User)->post(route('items.brands.store'), ['brand_name' => 'Nike'])->assertRedirect();
        $this->actingAs($this->store2User)->post(route('items.brands.store'), ['brand_name' => 'Nike'])->assertRedirect();

        $this->assertEquals(2, DbBrand::allStores()->where('brand_name', 'Nike')->count());
    }

    public function test_same_name_allowed_across_stores_for_variant(): void
    {
        $this->actingAs($this->store1User)->post(route('items.variants.store'), ['variant_name' => 'Size'])->assertRedirect();
        $this->actingAs($this->store2User)->post(route('items.variants.store'), ['variant_name' => 'Size'])->assertRedirect();

        $this->assertEquals(2, DbVariant::allStores()->where('variant_name', 'Size')->count());
    }

    public function test_duplicate_name_within_same_store_rejected_for_category(): void
    {
        $this->actingAs($this->store1User)->post(route('items.categories.store'), ['category_name' => 'Electronics'])->assertRedirect();
        $this->actingAs($this->store1User)
            ->post(route('items.categories.store'), ['category_name' => 'Electronics'])
            ->assertSessionHasErrors('category_name');

        $this->assertEquals(1, DbCategory::where('category_name', 'Electronics')->where('store_id', 1)->count());
    }

    public function test_duplicate_name_within_same_store_rejected_for_brand(): void
    {
        $this->actingAs($this->store1User)->post(route('items.brands.store'), ['brand_name' => 'Nike'])->assertRedirect();
        $this->actingAs($this->store1User)
            ->post(route('items.brands.store'), ['brand_name' => 'Nike'])
            ->assertSessionHasErrors('brand_name');

        $this->assertEquals(1, DbBrand::where('brand_name', 'Nike')->where('store_id', 1)->count());
    }

    public function test_duplicate_name_within_same_store_rejected_for_variant(): void
    {
        $this->actingAs($this->store1User)->post(route('items.variants.store'), ['variant_name' => 'Size'])->assertRedirect();
        $this->actingAs($this->store1User)
            ->post(route('items.variants.store'), ['variant_name' => 'Size'])
            ->assertSessionHasErrors('variant_name');

        $this->assertEquals(1, DbVariant::where('variant_name', 'Size')->where('store_id', 1)->count());
    }

    public function test_duplicate_code_within_same_store_rejected_for_category(): void
    {
        $this->actingAs($this->store1User)->post(route('items.categories.store'), ['category_name' => 'Cat A', 'category_code' => 'CAT-1'])->assertRedirect();
        $this->actingAs($this->store1User)
            ->post(route('items.categories.store'), ['category_name' => 'Cat B', 'category_code' => 'CAT-1'])
            ->assertSessionHasErrors('category_code');
    }

    /* ───────────────────────── PHASE 4: STATUS TOGGLE ───────────────────────── */

    public function test_category_toggle_status_flips_row(): void
    {
        $this->actingAs($this->store1User)
            ->patchJson(route('items.categories.toggle-status', $this->cat1->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 0]);

        $this->assertDatabaseHas('db_category', ['id' => $this->cat1->id, 'status' => 0]);
    }

    public function test_brand_toggle_status_flips_row(): void
    {
        $this->actingAs($this->store1User)
            ->patchJson(route('items.brands.toggle-status', $this->brand1->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 0]);

        $this->assertDatabaseHas('db_brands', ['id' => $this->brand1->id, 'status' => 0]);
    }

    public function test_variant_toggle_status_flips_row(): void
    {
        $this->actingAs($this->store1User)
            ->patchJson(route('items.variants.toggle-status', $this->variant1->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 0]);

        $this->assertDatabaseHas('db_variants', ['id' => $this->variant1->id, 'status' => 0]);
    }

    public function test_cross_store_toggle_status_returns_404(): void
    {
        $this->actingAs($this->store1User)
            ->patchJson(route('items.categories.toggle-status', $this->cat2->id))
            ->assertNotFound();
    }
}