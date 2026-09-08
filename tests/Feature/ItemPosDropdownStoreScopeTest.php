<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.2 verification — Items + POS category/brand dropdown store-scoping.
 *
 * Previously ItemController::index/create/edit/printLabels and PosController::index
 * loaded DbCategory/DbBrand with only where('status', 1) — zero store scoping, so a
 * Store-2 user's Add Item / POS screens could see Store-1 categories/brands. Fixed to
 * ->where('store_id', current_store_id()).
 *
 * Phase 3.1 confirmation — ItemController::destroy() cross-store blocking is already
 * covered by ItemDeleteStoreScopeTest; this suite re-asserts the dropdown scope only.
 */
class ItemPosDropdownStoreScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'mobile' => '2222222222']);

        $role1 = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => ['items_view', 'items_add', 'items_edit', 'sales_view', 'sales_add'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['items_view', 'items_add', 'items_edit', 'sales_view', 'sales_add'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);

        // Store-1-only category and brand.
        $this->cat1 = DbCategory::create(['category_name' => 'Store 1 Only Cat', 'status' => 1, 'store_id' => 1]);
        $this->brand1 = DbBrand::create(['brand_name' => 'Store 1 Only Brand', 'status' => 1, 'store_id' => 1]);

        // Store-2-only category and brand.
        $this->cat2 = DbCategory::create(['category_name' => 'Store 2 Only Cat', 'status' => 1, 'store_id' => 2]);
        $this->brand2 = DbBrand::create(['brand_name' => 'Store 2 Only Brand', 'status' => 1, 'store_id' => 2]);
    }

    /* ─────────────────── ITEMS LIST ─────────────────── */

    public function test_items_list_dropdowns_are_store_scoped(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('items.list'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 1 Only Cat', $names) && !in_array('Store 2 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 1 Only Brand', $names) && !in_array('Store 2 Only Brand', $names);
        });
    }

    public function test_items_list_dropdowns_show_zero_other_store_rows_for_store_2(): void
    {
        $response = $this->actingAs($this->store2User)->get(route('items.list'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 2 Only Cat', $names) && !in_array('Store 1 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 2 Only Brand', $names) && !in_array('Store 1 Only Brand', $names);
        });
    }

    /* ─────────────────── ADD ITEM ─────────────────── */

    public function test_add_item_dropdowns_are_store_scoped(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('items.add'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 1 Only Cat', $names) && !in_array('Store 2 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 1 Only Brand', $names) && !in_array('Store 2 Only Brand', $names);
        });
    }

    public function test_add_item_dropdowns_show_zero_other_store_rows_for_store_2(): void
    {
        $response = $this->actingAs($this->store2User)->get(route('items.add'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 2 Only Cat', $names) && !in_array('Store 1 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 2 Only Brand', $names) && !in_array('Store 1 Only Brand', $names);
        });
    }

    /* ─────────────────── EDIT ITEM ─────────────────── */

    public function test_edit_item_dropdowns_are_store_scoped(): void
    {
        $item = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Store 1 Item',
            'item_code' => 'IT-' . uniqid(),
            'category_id' => $this->cat1->id,
            'brand_id' => $this->brand1->id,
            'status' => 1,
            'child_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->get(route('items.edit', $item->id));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 1 Only Cat', $names) && !in_array('Store 2 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 1 Only Brand', $names) && !in_array('Store 2 Only Brand', $names);
        });
    }

    /* ─────────────────── POS ─────────────────── */

    public function test_pos_categories_and_brands_are_store_scoped(): void
    {
        $response = $this->actingAs($this->store1User)->get(route('sales.pos'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 1 Only Cat', $names) && !in_array('Store 2 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 1 Only Brand', $names) && !in_array('Store 2 Only Brand', $names);
        });
    }

    public function test_pos_categories_and_brands_show_zero_other_store_rows_for_store_2(): void
    {
        $response = $this->actingAs($this->store2User)->get(route('sales.pos'));
        $response->assertOk();

        $response->assertViewHas('categories', function ($categories) {
            $names = collect($categories)->pluck('category_name')->all();
            return in_array('Store 2 Only Cat', $names) && !in_array('Store 1 Only Cat', $names);
        });
        $response->assertViewHas('brands', function ($brands) {
            $names = collect($brands)->pluck('brand_name')->all();
            return in_array('Store 2 Only Brand', $names) && !in_array('Store 1 Only Brand', $names);
        });
    }
}
