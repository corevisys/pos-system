<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStockAdjustment;
use App\Models\DbStockTransfer;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — Store/warehouse scoping for Stock Transfer & Stock Adjustment.
 *
 * A Store-2 user must never see / edit / delete / transfer / adjust Store-1
 * records, even by direct URL id. Lists, dropdowns, single-record lookups and
 * global search must all be store-scoped.
 */
class StockModuleStoreScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $store1User;
    protected User $store2User;
    protected DbWarehouse $s1Wh;
    protected DbWarehouse $s2Wh;
    protected DbItem $s1Item;
    protected DbCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1, 2] as $sid) {
            DbStore::create([
                'id' => $sid,
                'store_name' => "Scope Store {$sid}",
                'status' => 1,
                'mobile' => "0170000000{$sid}",
            ]);
        }

        $this->category = DbCategory::create([
            'category_name' => 'Scope Category',
            'status' => 1,
        ]);

        // Store 1 warehouse + item
        $this->s1Wh = DbWarehouse::create(['warehouse_name' => 'S1 Warehouse', 'store_id' => 1, 'status' => 1]);
        $this->s1Item = DbItem::create([
            'item_name' => 'S1 Item', 'item_code' => 'S1-001',
            'category_id' => $this->category->id, 'purchase_price' => 10,
            'sales_price' => 20, 'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);

        // Store 2 warehouse + item
        $this->s2Wh = DbWarehouse::create(['warehouse_name' => 'S2 Warehouse', 'store_id' => 2, 'status' => 1]);
        DbItem::create([
            'item_name' => 'S2 Item', 'item_code' => 'S2-001',
            'category_id' => $this->category->id, 'purchase_price' => 10,
            'sales_price' => 20, 'stock' => 100, 'status' => 1, 'store_id' => 2,
        ]);

        foreach ([1, 2] as $sid) {
            $role = DbRole::create([
                'role_name' => "Store {$sid} Manager",
                'status' => 1,
                'store_id' => $sid,
            ]);
            DbPermission::create([
                'role_id' => $role->id,
                'store_id' => $sid,
                'permissions' => [
                    'stock_transfer_view', 'stock_transfer_add', 'stock_transfer_edit', 'stock_transfer_delete',
                    'stock_adjustment_view', 'stock_adjustment_add', 'stock_adjustment_edit', 'stock_adjustment_delete',
                ],
            ]);
        }

        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => DbRole::where('store_id', 1)->value('id'), 'name' => 'S1 User', 'email' => 's1@example.com']);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => DbRole::where('store_id', 2)->value('id'), 'name' => 'S2 User', 'email' => 's2@example.com']);
    }

    /** Store-1 transfer between two S1 warehouses. */
    protected function makeS1Transfer(): DbStockTransfer
    {
        $s1Wh2 = DbWarehouse::create(['warehouse_name' => 'S1 Warehouse B', 'store_id' => 1, 'status' => 1]);
        DbWarehouseItem::create([
            'store_id' => 1, 'warehouse_id' => $this->s1Wh->id,
            'item_id' => $this->s1Item->id, 'available_qty' => 50,
        ]);
        $this->actingAs($this->store1User)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $this->s1Wh->id,
            'warehouse_to' => $s1Wh2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $this->s1Item->id, 'quantity' => 10]],
        ])->assertOk();
        return DbStockTransfer::latest('id')->first();
    }

    /** Store-1 adjustment on S1 warehouse. */
    protected function makeS1Adjustment(): DbStockAdjustment
    {
        $this->actingAs($this->store1User)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $this->s1Wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $this->s1Item->id, 'quantity' => 5]],
        ])->assertOk();
        return DbStockAdjustment::latest('id')->first();
    }

    public function test_store2_user_cannot_see_store1_transfers_in_list()
    {
        $this->makeS1Transfer();

        $this->actingAs($this->store2User)->get(route('stock.transfer'))
            ->assertOk()
            ->assertDontSee('S1-001');
    }

    public function test_store2_user_cannot_edit_or_delete_store1_transfer()
    {
        $t = $this->makeS1Transfer();

        // Direct-URL edit → 404 (not Store-2's transfer).
        $this->actingAs($this->store2User)->get(route('stock.transfer.edit', $t->id))->assertNotFound();

        // Direct delete → 404, and the transfer + stock stay intact.
        $this->actingAs($this->store2User)->deleteJson(route('stock.transfer.destroy', $t->id))->assertStatus(404);
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $t->id]);
    }

    public function test_store2_user_cannot_create_transfer_from_store1_warehouse()
    {
        // Store-2 tries to transfer out of Store-1's warehouse → blocked.
        $s2Wh2 = DbWarehouse::create(['warehouse_name' => 'S2 Warehouse B', 'store_id' => 2, 'status' => 1]);

        $this->actingAs($this->store2User)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $this->s1Wh->id,
            'warehouse_to' => $s2Wh2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => DbItem::where('store_id', 2)->first()->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('db_stocktransfer', 0);
    }

    public function test_store2_user_cannot_see_store1_adjustments_in_list()
    {
        $this->makeS1Adjustment();

        $this->actingAs($this->store2User)->get(route('stock.adjustment'))
            ->assertOk()
            ->assertDontSee('S1-001');
    }

    public function test_store2_user_cannot_edit_or_delete_store1_adjustment()
    {
        $a = $this->makeS1Adjustment();

        $this->actingAs($this->store2User)->get(route('stock.adjustment.edit', $a->id))->assertNotFound();
        $this->actingAs($this->store2User)->deleteJson(route('stock.adjustment.destroy', $a->id))->assertStatus(404);
        $this->assertDatabaseHas('db_stockadjustment', ['id' => $a->id]);
    }

    public function test_store2_user_cannot_create_adjustment_on_store1_warehouse_or_item()
    {
        // Cross-store warehouse.
        $this->actingAs($this->store2User)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $this->s1Wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $this->s1Item->id, 'quantity' => 5]],
        ])->assertStatus(422);

        // Same-store warehouse but cross-store item.
        $this->actingAs($this->store2User)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $this->s2Wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $this->s1Item->id, 'quantity' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('db_stockadjustment', 0);
    }

    public function test_store2_warehouse_dropdowns_do_not_include_store1()
    {
        // Adjustment create page shows only S2 warehouses.
        $this->actingAs($this->store2User)->get(route('stock.adjustment.create'))
            ->assertOk()
            ->assertDontSee('S1 Warehouse');

        // Transfer create page shows only S2 warehouses.
        $this->actingAs($this->store2User)->get(route('stock.transfer.create'))
            ->assertOk()
            ->assertDontSee('S1 Warehouse');
    }

    public function test_global_search_does_not_leak_store1_transfers_or_adjustments_to_store2()
    {
        $this->makeS1Transfer();
        $this->makeS1Adjustment();

        $this->actingAs($this->store2User)->getJson(route('global.search', ['q' => 'TRANSFER']))
            ->assertOk()
            ->assertJsonMissingPath('categories.stock_transfers');

        $this->actingAs($this->store2User)->getJson(route('global.search', ['q' => 'ADJUST']))
            ->assertOk()
            ->assertJsonMissingPath('categories.stock_adjustments');
    }
}
