<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalesReturn;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification for the additional service_bit stock guards on downstream sale paths
 * that were added beyond the original 13-item scope:
 *   - PosController::store() sale-edit reversion (service line stock NOT restored)
 *   - SaleController::destroy() sale-delete restore (service line stock NOT restored)
 *   - SalesReturnController::store()/destroy() return create/delete (service line
 *     stock NOT mutated at any point)
 *
 * In every test a physical product in the same sale still mutates stock normally,
 * proving only the service line is exempt.
 */
class ServiceStockGuardPathsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $warehouse;
    protected $customer;
    protected $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::create([
            'store_code' => 'ST001',
            'store_name' => 'Main Branch',
            'mobile' => '01700000000',
            'status' => 1,
            'decimals' => 2,
            'qty_decimals' => 2,
        ]);
        store_settings(true);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => $this->store->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);
        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => $this->store->id,
            'permissions' => ['sales_add', 'sales_view', 'sales_return_add', 'sales_return_view', 'pos', 'accounts_view'],
        ]);

        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Main Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Customer A',
            'mobile' => '01711111111',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Cash Drawer',
            'account_number' => 'ACC-001',
            'balance' => 20000.00,
            'status' => 1,
        ]);

        $category = DbCategory::create([
            'store_id' => 1,
            'category_name' => 'General',
            'category_code' => 'CAT001',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);
        $this->cat = $category;
    }

    private function makeService(string $code): DbItem
    {
        return DbItem::create([
            'store_id' => $this->store->id,
            'item_name' => 'Svc ' . $code,
            'item_code' => $code,
            'category_id' => $this->cat->id,
            'price' => 100.00,
            'sales_price' => 150.00,
            'stock' => 0,
            'status' => 1,
            'service_bit' => 1,
            'child_bit' => 0,
        ]);
    }

    private function makeProduct(string $code, float $stock = 10): DbItem
    {
        $product = DbItem::create([
            'store_id' => $this->store->id,
            'item_name' => 'Product ' . $code,
            'item_code' => $code,
            'category_id' => $this->cat->id,
            'price' => 50.00,
            'sales_price' => 80.00,
            'stock' => $stock,
            'status' => 1,
            'service_bit' => 0,
            'child_bit' => 0,
        ]);
        DbWarehouseItem::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $product->id,
            'available_qty' => $stock,
        ]);
        return $product;
    }

    private function makeSale(string $code, DbItem $service, DbItem $product): DbSale
    {
        $sale = DbSale::create([
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => $code,
            'sales_date' => date('Y-m-d'),
            'subtotal' => 230.00,
            'grand_total' => 230.00,
            'paid_amount' => 0,
            'payment_status' => 'Unpaid',
            'status' => 1,
        ]);
        DbSaleItem::create([
            'store_id' => $this->store->id,
            'sales_id' => $sale->id,
            'item_id' => $service->id,
            'sales_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
            'status' => 1,
        ]);
        DbSaleItem::create([
            'store_id' => $this->store->id,
            'sales_id' => $sale->id,
            'item_id' => $product->id,
            'sales_qty' => 1,
            'price_per_unit' => 80.00,
            'total_cost' => 80.00,
            'status' => 1,
        ]);

        // Mimic a real checkout: the physical product's stock was decremented at sale
        // time (service lines never decrement). This is the state the guard code reads.
        DbItem::where('id', $product->id)->decrement('stock', 1);
        DbWarehouseItem::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $product->id)
            ->decrement('available_qty', 1);

        return $sale;
    }

    /**
     * C-guard 1: Editing a sale via POS store() with sale_id must NOT restore stock for
     * the service line (its stock was never decremented), but MUST restore the product's
     * stock before re-applying (revert old → re-decrement new). Final: service stock
     * unchanged (0), product stock back to original after a same-cart edit.
     */
    public function test_sale_edit_via_pos_does_not_restore_service_stock()
    {
        $service = $this->makeService('EDIT-SVC');
        $product = $this->makeProduct('EDIT-PROD', 10);

        $sale = $this->makeSale('SA-EDIT-1', $service, $product);

        // Product stock after sale: 10 - 1 = 9 (service stays 0).
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);

        // Edit the same sale (same cart) via POS store with sale_id.
        $payload = [
            'sale_id' => $sale->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'grand_total' => 230.00,
            'subtotal' => 230.00,
            'cart' => [
                ['id' => $service->id, 'qty' => 1, 'price' => 150.00, 'total' => 150.00, 'discount' => 0],
                ['id' => $product->id, 'qty' => 1, 'price' => 80.00, 'total' => 80.00, 'discount' => 0],
            ],
            'discount_type' => 'fixed',
            'discount_on_all' => 0,
            'other_charges' => 0,
            'round_off' => 0,
            'payment_type' => 'Cash',
            'paid_amount' => 0,
        ];

        $resp = $this->actingAs($this->user)->postJson(route('sales.pos.store'), $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        // Service stock must remain 0 through the whole edit (revert skipped + re-apply skipped).
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        // Product reverted to 10 then re-decremented to 9 (same cart).
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);
    }

    /**
     * C-guard 2: Deleting a sale containing a service line must NOT restore the service's
     * stock (0 stays 0), while the product's stock is restored (9 → 10).
     */
    public function test_sale_delete_does_not_restore_service_stock()
    {
        $service = $this->makeService('DEL-SVC');
        $product = $this->makeProduct('DEL-PROD', 10);

        $sale = $this->makeSale('SA-DEL-1', $service, $product);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);

        $resp = $this->actingAs($this->user)->deleteJson(route('sales.delete', $sale->id));
        // SaleController::destroy() redirects (302) rather than returning JSON.
        $resp->assertStatus(302);

        // Service untouched; product restored.
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 10]);
    }

    /**
     * C-guard 3: Creating a sales return for a service line must NOT increment the service's
     * stock (0 stays 0) — while a product line's stock IS incremented. Deleting that return
     * must likewise NOT decrement the service's stock.
     */
    public function test_sales_return_create_and_delete_do_not_mutate_service_stock()
    {
        $service = $this->makeService('RET-SVC');
        $product = $this->makeProduct('RET-PROD', 10);

        $sale = $this->makeSale('SA-RET-1', $service, $product);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);

        // Create a return covering BOTH lines (full refund not required; paid_amount 0 keeps
        // the flow simple but the return-item rows are still written and stock incremented).
        $payload = [
            'sales_id' => $sale->id,
            'return_date' => date('Y-m-d'),
            'reference_no' => 'RET-SVC-1',
            'paid_amount' => 0,
            'payment_type' => 'Cash',
            'items' => [
                ['item_id' => $service->id, 'return_qty' => 1, 'price_per_unit' => 150.00, 'tax_amt' => 0, 'discount_amt' => 0, 'total_cost' => 150.00],
                ['item_id' => $product->id, 'return_qty' => 1, 'price_per_unit' => 80.00, 'tax_amt' => 0, 'discount_amt' => 0, 'total_cost' => 80.00],
            ],
        ];

        $resp = $this->actingAs($this->user)->postJson(route('sales.return.store'), $payload);
        $resp->assertStatus(200);

        // Service stock must remain 0 after return-create; product restored to 10.
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 10]);

        $return = DbSalesReturn::where('reference_no', 'RET-SVC-1')->first();
        $this->assertNotNull($return);

        // Delete the return: service still 0, product back to 9.
        $resp = $this->actingAs($this->user)->deleteJson(route('sales.return.delete', $return->id));
        // SalesReturnController::destroy() redirects (302) rather than returning JSON.
        $resp->assertStatus(302);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);
    }
}
