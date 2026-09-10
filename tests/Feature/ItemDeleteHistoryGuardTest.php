<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbPermission;
use App\Models\DbPurchaseItem;
use App\Models\DbQuotation;
use App\Models\DbQuotationItem;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * History guard verification for ItemController::destroy().
 *
 * destroy() now rejects deletion when the item (or any of its child variants)
 * has rows in any history table that would otherwise be cascade-deleted:
 * sales items, sales returns, purchase items, purchase returns, quotation
 * items, stock adjustments, stock transfers, stock entries, serials
 * (any status), or held carts.
 */
class ItemDeleteHistoryGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);

        $role = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => ['items_view', 'items_add', 'items_delete'],
        ]);
        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);
    }

    private function makeItem(string $name, string $code): DbItem
    {
        return DbItem::create([
            'store_id' => 1,
            'item_name' => $name,
            'item_code' => $code,
            'sales_price' => 100.00,
            'status' => 1,
            'child_bit' => 0,
        ]);
    }

    private function makeWarehouse(): DbWarehouse
    {
        return DbWarehouse::create(['warehouse_name' => 'Main WH', 'status' => 1, 'store_id' => 1]);
    }

    private function makeCustomer(): DbCustomer
    {
        return DbCustomer::create([
            'customer_name' => 'Test Customer',
            'customer_code' => 'CUST-TEST-01',
            'mobile' => '01792000099',
            'status' => 1,
            'store_id' => 1,
        ]);
    }

    /**
     * H1: An item with at least one sale against it is blocked with a clear
     * message, and the item + its sale row remain completely untouched.
     */
    public function test_item_with_sale_history_is_blocked_and_untouched()
    {
        $item = $this->makeItem('Sold Item', 'SOLD-001');
        $warehouse = $this->makeWarehouse();
        $customer = $this->makeCustomer();

        $sale = DbSale::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sales_code' => 'SA-HG-001',
            'sales_date' => Carbon::today()->format('Y-m-d'),
            'subtotal' => 100,
            'grand_total' => 100,
            'paid_amount' => 100,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);

        DbSaleItem::create([
            'store_id' => 1,
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'sales_qty' => 1,
            'price_per_unit' => 100,
            'total_cost' => 100,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $item->id));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This item has existing sales/purchase history and cannot be deleted. Deactivate it instead.',
        ]);

        // Item and its sale row remain completely untouched.
        $this->assertDatabaseHas('db_items', ['id' => $item->id, 'store_id' => 1]);
        $this->assertDatabaseHas('db_salesitems', ['item_id' => $item->id, 'sales_id' => $sale->id]);
        $this->assertDatabaseHas('db_sales', ['id' => $sale->id]);
    }

    /**
     * H2: An item with ONLY purchase history (no sales) is also blocked.
     */
    public function test_item_with_only_purchase_history_is_blocked()
    {
        $item = $this->makeItem('Purchased Item', 'PUR-001');

        DbPurchaseItem::create([
            'store_id' => 1,
            'item_id' => $item->id,
            'purchase_qty' => 5,
            'price_per_unit' => 20,
            'total_cost' => 100,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $item->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        // Item + purchase line untouched.
        $this->assertDatabaseHas('db_items', ['id' => $item->id]);
        $this->assertDatabaseHas('db_purchaseitems', ['item_id' => $item->id]);
    }

    /**
     * H3: An item with ONLY a registered serial (any status — Available, not just Sold)
     * is blocked. Serials are tracked usage regardless of status.
     */
    public function test_item_with_only_serial_history_is_blocked()
    {
        $item = $this->makeItem('Serialized Item', 'SER-ITEM-001');
        $warehouse = $this->makeWarehouse();

        DbItemSerial::create([
            'store_id' => 1,
            'item_id' => $item->id,
            'serial_number' => 'SN-AVAILABLE-01',
            'status' => 0, // Available, not sold — still counts as tracked history
            'warehouse_id' => $warehouse->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $item->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', ['id' => $item->id]);
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $item->id, 'serial_number' => 'SN-AVAILABLE-01']);
    }

    /**
     * H4: An item with ONLY a quotation referencing it is blocked.
     */
    public function test_item_with_only_quotation_history_is_blocked()
    {
        $item = $this->makeItem('Quoted Item', 'QUO-001');
        $customer = $this->makeCustomer();

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'quotation_code' => 'Q-HG-001',
            'quotation_date' => Carbon::today()->format('Y-m-d'),
            'subtotal' => 100,
            'grand_total' => 100,
            'status' => 1,
        ]);

        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'qty' => 1,
            'price_per_unit' => 100,
            'total_cost' => 100,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $item->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', ['id' => $item->id]);
        $this->assertDatabaseHas('db_quotationitems', ['item_id' => $item->id]);
    }

    /**
     * H5: A Box parent whose VARIANT has history is blocked too (variant id
     * resolves through parent_id into the guard's item set).
     */
    public function test_box_parent_with_variant_history_is_blocked()
    {
        $parent = $this->makeItem('Box Parent', 'BOX-PARENT-001');
        $variant = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Box Parent-Variant A',
            'item_code' => 'BOX-VAR-A',
            'sales_price' => 50.00,
            'status' => 1,
            'parent_id' => $parent->id,
            'child_bit' => 1,
        ]);

        DbSaleItem::create([
            'store_id' => 1,
            'sales_id' => null,
            'item_id' => $variant->id,
            'sales_qty' => 1,
            'price_per_unit' => 50,
            'total_cost' => 50,
        ]);

        // Deleting the parent must be blocked because the variant has sales history.
        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $parent->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', ['id' => $parent->id]);
        $this->assertDatabaseHas('db_items', ['id' => $variant->id]);
    }

    /**
     * H6: An item with genuinely zero history in every category still deletes
     * successfully — the clean-delete flow must not regress.
     */
    public function test_item_with_zero_history_still_deletes()
    {
        $item = $this->makeItem('Clean Delete Item', 'CLEAN-002');

        $response = $this->actingAs($this->user)->deleteJson(route('items.delete', $item->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('db_items', ['id' => $item->id]);
    }
}
