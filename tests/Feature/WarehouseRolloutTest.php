<?php

namespace Tests\Feature;

use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbPurchase;
use App\Models\DbSale;
use App\Models\DbStockTransfer;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Warehouse rollout — Phases 1-7 regression tests.
 *
 * Phase 1: delete guards (stock>0, dangling refs) + soft-delete.
 * Phase 2: store scoping on index/stats/edit/update/destroy.
 * Phase 3: permission gates on all 6 methods.
 * Phase 4: unscoped dropdown consumers store-scoped + active-only.
 * Phase 5: per-store unique warehouse_name.
 * Phase 6: stats cards, status filter, CSV/PDF export.
 */
class WarehouseRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected function store(int $id = 1, string $name = 'WH Store'): DbStore
    {
        return DbStore::firstOrCreate(['id' => $id], [
            'store_name' => $name,
            'status' => 1,
            'mobile' => '0177' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        ]);
    }

    protected function makeUser(int $storeId = 1, array $permissions = []): User
    {
        $this->store($storeId);

        // Store scope bypassed: DbRole/DbPermission are StoreScoped now, and this
        // helper may run while acting as another store's user.
        DbRole::allStores()->firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::allStores()->firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);

        if (empty($permissions)) {
            return User::factory()->create(['store_id' => $storeId, 'role_id' => 1, 'role_name' => 'Super Admin']);
        }

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Limited-' . uniqid(), 'status' => 1]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);
        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id, 'role_name' => $role->role_name]);
    }

    protected function makeWarehouse(int $storeId = 1, ?string $name = null, int $status = 1): DbWarehouse
    {
        // Ensure the owning store row exists (FK on db_warehouse.store_id).
        $this->store($storeId);

        return DbWarehouse::create([
            'store_id' => $storeId,
            'warehouse_name' => $name ?? 'WH-' . uniqid(),
            'status' => $status,
            'delete_bit' => 0,
        ]);
    }

    // ─────────────────────────────── PHASE 1 ───────────────────────────────

    public function test_phase1_delete_blocked_when_stock_present_shows_counts(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        $item = DbItem::create([
            'store_id' => 1, 'item_name' => 'WH Item', 'item_code' => 'WHI-1',
            'category_id' => null, 'purchase_price' => 10, 'sales_price' => 20,
            'stock' => 5, 'status' => 1,
        ]);
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'available_qty' => 5]);

        $response = $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('1 item(s)', session('error'), 'Message must state the item count.');
        $this->assertStringContainsString('5.00', session('error'), 'Message must state the qty count.');

        $wh->refresh();
        $this->assertSame(0, (int) $wh->delete_bit, 'Row must be untouched (not soft-deleted) when stock present.');
        $this->assertDatabaseHas('db_warehouseitems', ['warehouse_id' => $wh->id]);
    }

    public function test_phase1_delete_blocked_when_dangling_references_name_the_table(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        // Historical sale referencing this warehouse (no stock rows).
        DbSale::create([
            'store_id' => 1, 'warehouse_id' => $wh->id, 'customer_id' => null,
            'sales_code' => 'SA-WH-REF', 'sales_date' => now()->format('Y-m-d'),
            'grand_total' => 10, 'paid_amount' => 10, 'status' => 1,
        ]);

        $response = $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('db_sales', session('error'), 'Message must name the referencing table.');

        $wh->refresh();
        $this->assertSame(0, (int) $wh->delete_bit, 'Row must be untouched.');
    }

    public function test_phase1_clean_warehouse_soft_deletes_with_delete_bit(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id))->assertSessionHas('success');

        $wh->refresh();
        $this->assertSame(1, (int) $wh->delete_bit, 'Soft-delete must set delete_bit=1 (row retained for audit).');
        $this->assertTrue(DbWarehouse::where('id', $wh->id)->exists(), 'Row must still exist (soft-delete default).');
    }

    public function test_phase1_serial_reference_also_blocks_delete(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        $item = DbItem::create([
            'store_id' => 1, 'item_name' => 'SerItem', 'item_code' => 'SER-IT-1',
            'category_id' => null, 'purchase_price' => 1, 'sales_price' => 2,
            'stock' => 0, 'status' => 1,
        ]);
        DbItemSerial::create([
            'store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item->id,
            'serial_number' => 'SER-WH-1', 'status' => 0,
        ]);

        $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id))->assertSessionHas('error');
        $this->assertStringContainsString('db_item_serials', session('error'));
        $this->assertSame(0, (int) $wh->refresh()->delete_bit);
    }

    // ─────────────────────────────── PHASE 2 ───────────────────────────────

    public function test_phase2_store_b_cannot_edit_update_delete_store_a_warehouse(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $whA = $this->makeWarehouse(1, 'StoreA-Only');

        $userB = $this->makeUser(2);

        // GET edit → 404.
        $this->actingAs($userB)->get(route('warehouse.edit', $whA->id))->assertNotFound();

        // PUT update → 404; row unchanged.
        $this->actingAs($userB)->put(route('warehouse.update', $whA->id), [
            'warehouse_name' => 'Hacked',
            'mobile' => null,
            'email' => null,
            'status' => 1,
        ])->assertNotFound();
        $this->assertSame('StoreA-Only', $whA->refresh()->warehouse_name);

        // DELETE → 404; row still present, delete_bit 0.
        $this->actingAs($userB)->delete(route('warehouse.destroy', $whA->id))->assertNotFound();
        $this->assertSame(0, (int) $whA->refresh()->delete_bit);

        // Control: Store-A user can edit own warehouse.
        $userA = $this->makeUser(1);
        $this->actingAs($userA)->put(route('warehouse.update', $whA->id), [
            'warehouse_name' => 'StoreA-Renamed',
            'mobile' => null,
            'email' => null,
            'status' => 1,
        ])->assertRedirect(route('warehouse.list'));
        $this->assertSame('StoreA-Renamed', $whA->refresh()->warehouse_name);
    }

    public function test_phase2_index_and_stats_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $this->makeWarehouse(1, 'S1-WH');
        $this->makeWarehouse(2, 'S2-WH');

        $userA = $this->makeUser(1);
        $res = $this->actingAs($userA)->get(route('warehouse.list'));
        $res->assertOk();
        $res->assertSee('S1-WH');
        $res->assertDontSee('S2-WH');
        $this->assertSame(1, $res->viewData('stats')['total'], 'Stats total must be store-scoped (1, not 2).');
    }

    // ─────────────────────────────── PHASE 3 ───────────────────────────────

    public function test_phase3_permission_gates_403_without_slug_and_control_succeeds(): void
    {
        $wh = $this->makeWarehouse(1);

        // No warehouse perms at all.
        $noPerm = $this->makeUser(1, ['sales_view']);
        $this->actingAs($noPerm)->get(route('warehouse.list'))->assertForbidden();
        $this->actingAs($noPerm)->get(route('warehouse.add'))->assertForbidden();
        $this->actingAs($noPerm)->get(route('warehouse.edit', $wh->id))->assertForbidden();
        $this->actingAs($noPerm)->post(route('warehouse.store'), ['warehouse_name' => 'Nope', 'mobile' => null, 'email' => null])->assertForbidden();
        $this->actingAs($noPerm)->put(route('warehouse.update', $wh->id), ['warehouse_name' => 'Nope', 'mobile' => null, 'email' => null, 'status' => 1])->assertForbidden();
        $this->actingAs($noPerm)->delete(route('warehouse.destroy', $wh->id))->assertForbidden();

        // View-only cannot create.
        $viewOnly = $this->makeUser(1, ['warehouse_view']);
        $this->actingAs($viewOnly)->get(route('warehouse.list'))->assertOk();
        $this->actingAs($viewOnly)->post(route('warehouse.store'), ['warehouse_name' => 'Nope2', 'mobile' => null, 'email' => null])->assertForbidden();

        // Control: full-permission user succeeds.
        $full = $this->makeUser(1, ['warehouse_view', 'warehouse_add', 'warehouse_edit', 'warehouse_delete']);
        $this->actingAs($full)->post(route('warehouse.store'), ['warehouse_name' => 'PermOK', 'mobile' => null, 'email' => null])->assertRedirect(route('warehouse.list'));
        $this->assertSame(1, DbWarehouse::where('warehouse_name', 'PermOK')->count());
    }

    // ─────────────────────────────── PHASE 5 ───────────────────────────────

    public function test_phase5_same_warehouse_name_allowed_across_stores_but_blocked_within_store(): void
    {
        $this->store(1);
        $this->store(2);
        $userA = $this->makeUser(1, ['warehouse_view', 'warehouse_add']);
        $userB = $this->makeUser(2, ['warehouse_view', 'warehouse_add']);

        $this->actingAs($userA)->post(route('warehouse.store'), ['warehouse_name' => 'Shared Name', 'mobile' => null, 'email' => null])->assertRedirect();

        // Store B can use the same name (previously blocked by global unique).
        $this->actingAs($userB)->post(route('warehouse.store'), ['warehouse_name' => 'Shared Name', 'mobile' => null, 'email' => null])->assertRedirect(route('warehouse.list'));

        // Store A cannot create a second "Shared Name".
        $this->actingAs($userA)->post(route('warehouse.store'), ['warehouse_name' => 'Shared Name', 'mobile' => null, 'email' => null])->assertSessionHasErrors('warehouse_name');
        $this->assertSame(2, DbWarehouse::allStores()->where('warehouse_name', 'Shared Name')->count(), 'Exactly one per store.');
    }

    // ─────────────────────────────── PHASE 6 ───────────────────────────────

    public function test_phase6_status_filter_narrows_list_and_stats_cards_render(): void
    {
        $user = $this->makeUser(1);
        $this->makeWarehouse(1, 'ActiveWH', 1);
        $this->makeWarehouse(1, 'InactiveWH', 0);

        $resAll = $this->actingAs($user)->get(route('warehouse.list'));
        $resAll->assertOk();
        $resAll->assertSee('ActiveWH');
        $resAll->assertSee('InactiveWH');
        $this->assertSame(2, $resAll->viewData('stats')['total']);
        $this->assertSame(1, $resAll->viewData('stats')['active']);
        $this->assertSame(1, $resAll->viewData('stats')['inactive']);

        $resActive = $this->actingAs($user)->get(route('warehouse.list', ['status' => 1]));
        $resActive->assertOk();
        $resActive->assertSee('ActiveWH');
        $resActive->assertDontSee('InactiveWH');
    }

    public function test_phase6_csv_export_is_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $this->makeWarehouse(1, 'ExportS1');
        $this->makeWarehouse(2, 'ExportS2');

        $userA = $this->makeUser(1);
        $res = $this->actingAs($userA)->get(route('warehouse.list', ['export' => 'csv']));
        $res->assertOk();
        $content = $res->streamedContent();
        $this->assertStringContainsString('ExportS1', $content);
        $this->assertStringNotContainsString('ExportS2', $content, 'CSV must be store-scoped.');
    }

    public function test_phase6_pdf_export_is_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $this->makeWarehouse(1, 'PdfS1');
        $this->makeWarehouse(2, 'PdfS2');

        $userA = $this->makeUser(1);
        $res = $this->actingAs($userA)->get(route('warehouse.list', ['export' => 'pdf']));
        $res->assertOk();
        $res->assertSee('PdfS1');
        $res->assertDontSee('PdfS2', 'PDF print view must be store-scoped.');
    }

    // ─────────────────────────────── PHASE 4 ───────────────────────────────

    protected function assertDropdownDoesNotContainOtherStore(array $whNames, string $label): void
    {
        foreach ($whNames as $name) {
            $this->assertStringNotContainsString('StoreB-Only', $name, "{$label}: Store-B warehouse must not leak into Store-A dropdown.");
        }
        foreach ($whNames as $name) {
            $this->assertStringNotContainsString('Inactive-Only', $name, "{$label}: inactive warehouse must not appear.");
        }
    }

    protected function seedPhase4(): array
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $storeBWh = $this->makeWarehouse(2, 'StoreB-Only', 1);
        $inactive = $this->makeWarehouse(1, 'Inactive-Only', 0);
        $active = $this->makeWarehouse(1, 'Active-Only', 1);
        $userA = $this->makeUser(1);
        return compact('storeBWh', 'inactive', 'active', 'userA');
    }

    public function test_phase4_purchase_list_and_create_dropdowns_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('purchase.list'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Purchase list');
        $this->assertContains('Active-Only', $res->viewData('warehouses')->pluck('warehouse_name')->all());
    }

    public function test_phase4_item_dropdown_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('items.add'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Item add');
    }

    public function test_phase4_pos_dropdown_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('sales.pos'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'POS');
    }

    public function test_phase4_sale_create_and_edit_dropdowns_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('sales.add'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Sale create');
    }

    public function test_phase4_sales_return_dropdown_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('sales.returns'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Sales return');
    }

    public function test_phase4_purchase_create_dropdown_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        $res = $this->actingAs($seed['userA'])->get(route('purchase.new'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Purchase create');
    }

    public function test_phase4_report_dropdowns_store_scoped_and_active_only(): void
    {
        $seed = $this->seedPhase4();
        // Spot-check 2 report pages that pass $warehouses to the view.
        $res = $this->actingAs($seed['userA'])->get(route('reports.sales_summary'));
        $res->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res->viewData('warehouses')->pluck('warehouse_name')->all(), 'Sales summary report');
        $res2 = $this->actingAs($seed['userA'])->get(route('reports.stock'));
        $res2->assertOk();
        $this->assertDropdownDoesNotContainOtherStore($res2->viewData('warehouses')->pluck('warehouse_name')->all(), 'Stock report');
    }

    // ─────────────────────────────── GAP 1 ───────────────────────────────

    public function test_gap1_soft_deleted_warehouse_excluded_from_index_and_stats(): void
    {
        $user = $this->makeUser(1);
        $wh1 = $this->makeWarehouse(1, 'Gap1-A');
        $wh2 = $this->makeWarehouse(1, 'Gap1-B');
        $wh3 = $this->makeWarehouse(1, 'Gap1-C');

        // Baseline: 3 in list + stats.
        $resBefore = $this->actingAs($user)->get(route('warehouse.list'));
        $resBefore->assertOk();
        $this->assertSame(3, $resBefore->viewData('stats')['total'], 'Baseline stats total = 3.');
        $resBefore->assertSee('Gap1-A');
        $resBefore->assertSee('Gap1-C');

        // Soft-delete one via the real destroy() flow (clean warehouse).
        $this->actingAs($user)->delete(route('warehouse.destroy', $wh2->id))->assertSessionHas('success');
        $this->assertSame(1, (int) $wh2->refresh()->delete_bit, 'Precondition: destroy() soft-deleted the row.');

        // After: not in list, stats total drops 3 → 2 (exact).
        $resAfter = $this->actingAs($user)->get(route('warehouse.list'));
        $resAfter->assertOk();
        $this->assertSame(2, $resAfter->viewData('stats')['total'], 'stats total must drop by exactly 1 (3→2).');
        $this->assertSame(3, $resAfter->viewData('stats')['total'] + 1, 'Exact delta check.');
        $resAfter->assertDontSee('Gap1-B');
        $resAfter->assertSee('Gap1-A');
        $resAfter->assertSee('Gap1-C');
    }

    // ─────────────────────────────── GAP 2 ───────────────────────────────

    public function test_gap2_store_b_with_delete_permission_still_blocked_from_store_a_warehouse(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $whA = $this->makeWarehouse(1, 'Gap2-StoreA');

        // Store-B user WITH the delete permission — scope must still block.
        $userB = $this->makeUser(2, ['warehouse_view', 'warehouse_edit', 'warehouse_delete']);

        $this->actingAs($userB)->put(route('warehouse.update', $whA->id), [
            'warehouse_name' => 'Hacked',
            'mobile' => null,
            'email' => null,
            'status' => 1,
        ])->assertNotFound();
        $this->assertSame('Gap2-StoreA', $whA->refresh()->warehouse_name, 'Row unchanged despite Store-B having edit permission.');

        $this->actingAs($userB)->delete(route('warehouse.destroy', $whA->id))->assertNotFound();
        $this->assertSame(0, (int) $whA->refresh()->delete_bit, 'Row not soft-deleted despite Store-B having delete permission.');
    }

    public function test_gap2_store_a_with_permission_can_edit_and_delete_own_warehouse(): void
    {
        $this->store(1, 'Store A');
        $whA = $this->makeWarehouse(1, 'Gap2-Own');
        $userA = $this->makeUser(1, ['warehouse_view', 'warehouse_edit', 'warehouse_delete']);

        // Control: same-store + permission → edit succeeds.
        $this->actingAs($userA)->put(route('warehouse.update', $whA->id), [
            'warehouse_name' => 'Gap2-Own-Renamed',
            'mobile' => null,
            'email' => null,
            'status' => 1,
        ])->assertRedirect(route('warehouse.list'));
        $this->assertSame('Gap2-Own-Renamed', $whA->refresh()->warehouse_name);

        // Control: same-store + permission → delete (soft) succeeds.
        $this->actingAs($userA)->delete(route('warehouse.destroy', $whA->id))->assertSessionHas('success');
        $this->assertSame(1, (int) $whA->refresh()->delete_bit);
    }

    // GAP 3 moved to tests/Feature/WarehouseMigrationDuplicatePreCheckTest.php —
    // its sqlite connection swap must not share a RefreshDatabase transaction
    // state with sibling tests.

    // ─────────────────────────────── GAP 4 ───────────────────────────────

    public function test_gap4_item_row_at_zero_qty_blocks_delete_total_items_reading(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        $item = DbItem::create([
            'store_id' => 1, 'item_name' => 'ZeroQty', 'item_code' => 'ZQ-1',
            'category_id' => null, 'purchase_price' => 10, 'sales_price' => 20,
            'stock' => 0, 'status' => 1,
        ]);
        // Item row exists but available_qty sums to 0.
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'available_qty' => 0]);

        // The fix's reading of "holds stock" is "item rows exist": total_items=1 > 0
        // must block delete even when quantity is 0 (an item row occupies the warehouse).
        $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id))->assertSessionHas('error');
        $this->assertStringContainsString('1 item(s)', session('error'));
        $this->assertSame(0, (int) $wh->refresh()->delete_bit, 'Delete must be blocked (item rows exist).');
    }

    public function test_gap4_negative_available_qty_blocks_delete(): void
    {
        $user = $this->makeUser(1);
        $wh = $this->makeWarehouse(1);

        $item = DbItem::create([
            'store_id' => 1, 'item_name' => 'NegQty', 'item_code' => 'NQ-1',
            'category_id' => null, 'purchase_price' => 10, 'sales_price' => 20,
            'stock' => -3, 'status' => 1,
        ]);
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'available_qty' => -3]);

        // Negative qty is still "quantity present in the warehouse" per the
        // `> 0` check's intent — a negative balance must NOT allow deletion.
        $this->actingAs($user)->delete(route('warehouse.destroy', $wh->id))->assertSessionHas('error');
        $this->assertSame(0, (int) $wh->refresh()->delete_bit, 'Delete must be blocked (quantity row exists, even negative).');
    }
}
