<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStockAdjustment;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — Route-level permission enforcement for Stock Transfer & Adjustment.
 *
 * A role WITHOUT the stock_* slugs must get 403 on every route (even via direct
 * URL); a view-only role cannot reach create/store/update/destroy.
 */
class StockModulePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $permissions): User
    {
        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Perm Test Store', 'status' => 1, 'mobile' => '01711111111']);

        // Role 1 is always Super Admin (exempt). Use a distinct role for limited users.
        DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Limited-' . uniqid(), 'status' => 1]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);

        return User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => $role->role_name,
        ]);
    }

    protected function makeSuperAdmin(): User
    {
        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Perm Test Store', 'status' => 1, 'mobile' => '01711111111']);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);
        return User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin']);
    }

    protected function makeSeed(): array
    {
        $super = $this->makeSuperAdmin();
        $category = DbCategory::create(['store_id' => 1, 'category_name' => 'Perm Category', 'status' => 1]);
        $wh = DbWarehouse::create(['warehouse_name' => 'Perm WH', 'store_id' => 1, 'status' => 1]);
        $item = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Perm Item', 'item_code' => 'PERM-001',
            'category_id' => $category->id, 'purchase_price' => 5,
            'sales_price' => 10, 'stock' => 100, 'status' => 1, 'store_id' => 1,
        ]);
        DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh->id, 'item_id' => $item->id, 'available_qty' => 100]);
        return [$super, $wh, $item];
    }

    public function test_no_permission_gets_403_on_transfer_list_and_create()
    {
        [$super, $wh, $item] = $this->makeSeed();
        $noPerm = $this->makeUser(['sales_view']); // no stock_* slugs

        $this->actingAs($noPerm)->get(route('stock.transfer'))->assertForbidden();
        $this->actingAs($noPerm)->get(route('stock.transfer.create'))->assertForbidden();

        $this->actingAs($noPerm)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $wh->id,
            'warehouse_to' => $wh->id, // invalid anyway
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_no_permission_gets_403_on_adjustment_list_and_create()
    {
        [$super, $wh, $item] = $this->makeSeed();
        $noPerm = $this->makeUser(['sales_view']);

        $this->actingAs($noPerm)->get(route('stock.adjustment'))->assertForbidden();
        $this->actingAs($noPerm)->get(route('stock.adjustment.create'))->assertForbidden();

        $this->actingAs($noPerm)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_view_only_role_cannot_reach_edit_update_or_destroy_endpoints()
    {
        [$super, $wh, $item] = $this->makeSeed();
        $viewOnly = $this->makeUser(['stock_transfer_view', 'stock_adjustment_view']);

        // Super admin creates records.
        $this->actingAs($super)->postJson(route('stock.transfer.store'), [
            'warehouse_from' => $wh->id,
            'warehouse_to' => DbWarehouse::create(['warehouse_name' => 'Perm WH2', 'store_id' => 1, 'status' => 1])->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 5]],
        ])->assertOk();
        $transferId = \App\Models\DbStockTransfer::latest('id')->value('id');

        $this->actingAs($super)->postJson(route('stock.adjustment.store'), [
            'warehouse_id' => $wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 5]],
        ])->assertOk();
        $adjId = DbStockAdjustment::latest('id')->value('id');

        // View-only: list pages OK.
        $this->actingAs($viewOnly)->get(route('stock.transfer'))->assertOk();
        $this->actingAs($viewOnly)->get(route('stock.adjustment'))->assertOk();

        // View-only: edit/create/update/destroy all 403.
        $this->actingAs($viewOnly)->get(route('stock.transfer.edit', $transferId))->assertForbidden();
        $this->actingAs($viewOnly)->postJson(route('stock.transfer.update', $transferId), [
            'warehouse_from' => $wh->id,
            'warehouse_to' => $wh->id,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();
        $this->actingAs($viewOnly)->deleteJson(route('stock.transfer.destroy', $transferId))->assertForbidden();

        $this->actingAs($viewOnly)->get(route('stock.adjustment.edit', $adjId))->assertForbidden();
        $this->actingAs($viewOnly)->postJson(route('stock.adjustment.update', $adjId), [
            'warehouse_id' => $wh->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();
        $this->actingAs($viewOnly)->deleteJson(route('stock.adjustment.destroy', $adjId))->assertForbidden();

        // And the records were NOT touched by the view-only user.
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transferId]);
        $this->assertDatabaseHas('db_stockadjustment', ['id' => $adjId]);
    }
}
