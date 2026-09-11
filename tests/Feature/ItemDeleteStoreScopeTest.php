<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IDOR hotfix verification for ItemController::destroy().
 *
 * The defect: destroy() fetched DbItem::findOrFail($id) with no store scoping,
 * so a Store-2 user could delete a Store-1 item by supplying its id directly.
 * The fix scopes the lookup (and the variant/warehouse cleanup) by the current
 * store — a cross-store id resolves to "not found" and is rejected cleanly.
 */
class ItemDeleteStoreScopeTest extends TestCase
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
            'permissions' => ['items_view', 'items_add', 'items_delete'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['items_view', 'items_add', 'items_delete'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);
    }

    private function makeItem(int $storeId, string $name, string $code): DbItem
    {
        return DbItem::create([
            'store_id' => $storeId,
            'item_name' => $name,
            'item_code' => $code,
            'sales_price' => 100.00,
            'status' => 1,
            'child_bit' => 0,
        ]);
    }

    /**
     * B2: A Store-2 user attempting to delete a Store-1 item id directly
     * must be rejected (item not found) — the Store-1 item stays untouched.
     */
    public function test_destroy_is_store_scoped_blocking_cross_tenant_delete()
    {
        $store1Item = $this->makeItem(1, 'Store 1 Secret Item', 'S1-SECRET');

        // Store-2 user bypasses the UI and hits the raw delete route with a Store-1 id.
        $response = $this->actingAs($this->store2User)
            ->deleteJson(route('items.delete', $store1Item->id));

        // JSON contract: success=false + 404 (item not found), consistent with the
        // not-found rejection on the other already-fixed controllers.
        $response->assertStatus(404);
        $response->assertJson(['success' => false]);

        // The Store-1 item must remain, completely untouched.
        $this->assertDatabaseHas('db_items', [
            'id' => $store1Item->id,
            'store_id' => 1,
            'item_name' => 'Store 1 Secret Item',
        ]);
    }

    /**
     * B2: Same-store legitimate delete still works end-to-end.
     */
    public function test_same_store_clean_item_delete_succeeds()
    {
        $item = $this->makeItem(1, 'Clean Item', 'CLEAN-001');

        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.delete', $item->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Hard delete — the row (and its variants/warehouse rows) are gone.
        $this->assertDatabaseMissing('db_items', ['id' => $item->id]);
    }

    /**
     * B2: Deleting an item also removes its warehouse stock rows (existing
     * behavior preserved) — but only for the same store.
     */
    public function test_same_store_delete_removes_variants_and_warehouse_rows()
    {
        $store1 = DbStore::find(1);
        $warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Main WH',
            'status' => 1,
            'store_id' => 1,
        ]);

        $item = $this->makeItem(1, 'Parent Item', 'PARENT-001');
        $variant = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Parent Item-Variant A',
            'item_code' => 'VAR-A',
            'sales_price' => 50.00,
            'status' => 1,
            'parent_id' => $item->id,
            'child_bit' => 1,
        ]);

        DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'available_qty' => 10,
        ]);
        DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $variant->id,
            'available_qty' => 5,
        ]);

        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.delete', $item->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Parent + variant + both warehouse rows gone.
        $this->assertDatabaseMissing('db_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('db_items', ['id' => $variant->id]);
        $this->assertDatabaseMissing('db_warehouseitems', ['item_id' => $item->id]);
        $this->assertDatabaseMissing('db_warehouseitems', ['item_id' => $variant->id]);
    }

    /**
     * B1: Two sequential delete requests for the same item — the first hard-deletes
     * the row; the second must observe it as gone (404 / success=false). Hard delete
     * needs no delete_bit flip: after the first delete commits, the store-scoped
     * lookup returns null for the second request.
     */
    public function test_double_delete_same_item_only_first_succeeds()
    {
        $item = $this->makeItem(1, 'Double Delete Item', 'DD-001');

        $first = $this->actingAs($this->store1User)
            ->deleteJson(route('items.delete', $item->id));
        $first->assertOk();
        $first->assertJson(['success' => true]);

        // Second request: row already hard-deleted → store-scoped lookup finds nothing.
        $second = $this->actingAs($this->store1User)
            ->deleteJson(route('items.delete', $item->id));
        $second->assertStatus(404);
        $second->assertJson(['success' => false]);

        $this->assertDatabaseMissing('db_items', ['id' => $item->id]);
    }

    /**
     * B2: The scoped cleanup must not touch a Store-2 item's warehouse rows
     * even if item ids happen to collide in the request (cross-store leak guard
     * on the cleanup queries themselves).
     */
    public function test_cross_store_delete_leaves_store2_warehouse_rows_untouched()
    {
        $warehouse1 = DbWarehouse::create(['warehouse_name' => 'WH1', 'status' => 1, 'store_id' => 1]);
        $warehouse2 = DbWarehouse::create(['warehouse_name' => 'WH2', 'status' => 1, 'store_id' => 2]);

        $s1Item = $this->makeItem(1, 'S1 Item', 'S1-ITEM');
        $s2Item = $this->makeItem(2, 'S2 Item', 'S2-ITEM');

        // Store-2 warehouse row referencing S2 item.
        DbWarehouseItem::create([
            'store_id' => 2,
            'warehouse_id' => $warehouse2->id,
            'item_id' => $s2Item->id,
            'available_qty' => 99,
        ]);
        // Store-1 warehouse row referencing S1 item.
        DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse1->id,
            'item_id' => $s1Item->id,
            'available_qty' => 7,
        ]);

        // Store-1 user tries to delete the Store-2 item id → rejected.
        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.delete', $s2Item->id));
        $response->assertStatus(404);

        // Store-2 item AND its warehouse row remain untouched.
        $this->assertDatabaseHas('db_items', ['id' => $s2Item->id, 'store_id' => 2]);
        $this->assertDatabaseHas('db_warehouseitems', [
            'item_id' => $s2Item->id,
            'store_id' => 2,
            'available_qty' => 99,
        ]);
    }
}
