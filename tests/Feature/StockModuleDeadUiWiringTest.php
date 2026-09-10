<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — Dead UI wiring for the Stock Transfer & Adjustment lists.
 *
 * search / per_page actually filter & paginate; exports produce correct filtered
 * output; and the previously-dead View/Delete links are real routes.
 */
class StockModuleDeadUiWiringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'UI Wire Store', 'status' => 1, 'mobile' => '01722222222']);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);
        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin']);
    }

    protected function seedTransfer(string $ref, int $qty): void
    {
        // Warehouse names deliberately avoid the ref string so a filtered list
        // assertion targets table rows only (the filter dropdowns legitimately
        // list all warehouses regardless of the applied search). A per-call
        // unique suffix keeps repeated seedTransfer() calls in one test from
        // colliding with the Phase-5 per-store-unique warehouse name constraint
        // (db_warehouse_store_warehouse_name_unique).
        $suffix = strtolower(str_replace('.', '', uniqid('', true)));
        $whFrom = DbWarehouse::create(['warehouse_name' => 'Src-WH-' . $suffix, 'store_id' => 1, 'status' => 1]);
        $whTo = DbWarehouse::create(['warehouse_name' => 'Dst-WH-' . $suffix, 'store_id' => 1, 'status' => 1]);
        $category = DbCategory::create(['category_name' => 'Cat ' . $ref, 'status' => 1]);
        $item = DbItem::create([
            'item_name' => 'Item ' . $ref, 'item_code' => 'CODE-' . $ref,
            'category_id' => $category->id, 'purchase_price' => 5,
            'sales_price' => 10, 'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $whFrom->id, 'item_id' => $item->id, 'available_qty' => 100]);

        $this->actingAs($this->user)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $whFrom->id,
            'warehouse_to' => $whTo->id,
            'transfer_date' => now()->toDateString(),
            'reference_no' => $ref,
            'items' => [['item_id' => $item->id, 'quantity' => $qty]],
        ])->assertOk();
    }

    protected function seedAdjustment(string $ref, int $qty): void
    {
        $wh = DbWarehouse::create(['warehouse_name' => 'Adj WH ' . $ref, 'store_id' => 1, 'status' => 1]);
        $category = DbCategory::create(['category_name' => 'Adj Cat ' . $ref, 'status' => 1]);
        $item = DbItem::create([
            'item_name' => 'Adj Item ' . $ref, 'item_code' => 'ADJCODE-' . $ref,
            'category_id' => $category->id, 'purchase_price' => 5,
            'sales_price' => 10, 'stock' => 0, 'status' => 1, 'store_id' => 1,
        ]);

        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $wh->id,
            'adjustment_date' => now()->toDateString(),
            'reference_no' => $ref,
            'items' => [['item_id' => $item->id, 'quantity' => $qty]],
        ])->assertOk();
    }

    public function test_transfer_search_filters_and_per_page_paginates()
    {
        $this->seedTransfer('TR-SEARCH-1', 5);
        $this->seedTransfer('TR-SEARCH-2', 7);

        // Search filters to the matching reference.
        $this->actingAs($this->user)->get(route('stock.transfer', ['search' => 'TR-SEARCH-1']))
            ->assertOk()
            ->assertSee('TR-SEARCH-1')
            ->assertDontSee('TR-SEARCH-2');

        // per_page=25 renders pagination without error.
        $this->actingAs($this->user)->get(route('stock.transfer', ['per_page' => 25]))
            ->assertOk();
    }

    public function test_transfer_csv_export_produces_filtered_output()
    {
        $this->seedTransfer('TR-EXP-1', 5);
        $this->seedTransfer('TR-EXP-2', 7);

        $response = $this->actingAs($this->user)->get(route('stock.transfer', ['search' => 'TR-EXP-1', 'export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->streamedContent();
        $this->assertStringContainsString('TR-EXP-1', $body);
        $this->assertStringNotContainsString('TR-EXP-2', $body);
    }

    public function test_adjustment_csv_export_produces_filtered_output()
    {
        $this->seedAdjustment('ADJ-EXP-1', 5);
        $this->seedAdjustment('ADJ-EXP-2', 7);

        $response = $this->actingAs($this->user)->get(route('stock.adjustment', ['search' => 'ADJ-EXP-1', 'export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->streamedContent();
        $this->assertStringContainsString('ADJ-EXP-1', $body);
        $this->assertStringNotContainsString('ADJ-EXP-2', $body);
    }

    public function test_transfer_print_export_renders()
    {
        $this->seedTransfer('TR-PRINT-1', 5);
        $this->actingAs($this->user)->get(route('stock.transfer', ['export' => 'print']))
            ->assertOk()
            ->assertSee('Stock Transfer List')
            ->assertSee('TR-PRINT-1');
    }

    public function test_adjustment_print_export_renders()
    {
        $this->seedAdjustment('ADJ-PRINT-1', 5);
        $this->actingAs($this->user)->get(route('stock.adjustment', ['export' => 'print']))
            ->assertOk()
            ->assertSee('Stock Adjustment List')
            ->assertSee('ADJ-PRINT-1');
    }

    public function test_adjustment_per_page_accepted()
    {
        $this->seedAdjustment('ADJ-PER-1', 5);
        $this->actingAs($this->user)->get(route('stock.adjustment', ['per_page' => 25]))
            ->assertOk();
    }

    public function test_adjustment_view_and_delete_links_are_real_routes()
    {
        $this->seedAdjustment('ADJ-REAL-1', 5);
        $adjId = \App\Models\DbStockAdjustment::where('reference_no', 'ADJ-REAL-1')->value('id');

        // View route renders.
        $this->actingAs($this->user)->get(route('stock.adjustment.show', $adjId))->assertOk();

        // Delete route works and reverses.
        $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adjId))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('db_stockadjustment', ['id' => $adjId]);
    }
}
