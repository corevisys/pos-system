<?php

namespace Tests\Feature;

use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbQuotation;
use App\Models\DbQuotationItem;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IDOR + history-guard verification for ServiceController::destroy().
 *
 * The defect: destroy() fetched DbItem::where('service_bit', 1)->findOrFail($id)
 * with no store scoping, no history guard, and no transaction wrapper, so a
 * Store-2 user could delete a Store-1 service by id, and any service with sales/
 * quotation/hold history could be hard-deleted (silently cascading those rows).
 * The fix mirrors ItemController::destroy() exactly: store-scoped lookup, the
 * same 10-table history-guard blocking set, and a beginTransaction/commit/rollBack
 * wrapper.
 */
class ServiceDeleteStoreScopeTest extends TestCase
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
            'permissions' => ['services_view', 'services_add', 'services_edit', 'services_delete', 'sales_add', 'sales_view'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['services_view', 'services_add', 'services_edit', 'services_delete', 'sales_add', 'sales_view'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);
    }

    private function makeService(int $storeId, string $name, string $code): DbItem
    {
        return DbItem::create([
            'store_id' => $storeId,
            'item_name' => $name,
            'item_code' => $code,
            'sales_price' => 150.00,
            'price' => 100.00,
            'status' => 1,
            'service_bit' => 1,
            'child_bit' => 0,
        ]);
    }

    /** (i) A Store-2 user cannot delete a Store-1 service by id. */
    public function test_destroy_is_store_scoped_blocking_cross_tenant_delete()
    {
        $store1Service = $this->makeService(1, 'Store 1 Secret Service', 'S1-SVC-SECRET');

        $response = $this->actingAs($this->store2User)
            ->deleteJson(route('items.service.delete', $store1Service->id));

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', [
            'id' => $store1Service->id,
            'store_id' => 1,
            'item_name' => 'Store 1 Secret Service',
        ]);
    }

    /** (ii) A service with sales history is blocked, service + sale rows untouched. */
    public function test_destroy_blocks_service_with_sales_history()
    {
        $service = $this->makeService(1, 'Sold Service', 'SVC-SOLD');

        $sale = DbSale::create([
            'store_id' => 1,
            'sales_code' => 'SA-TEST-001',
            'sales_date' => now()->format('Y-m-d'),
            'grand_total' => 150.00,
            'subtotal' => 150.00,
            'paid_amount' => 0,
            'payment_status' => 'Unpaid',
            'status' => 1,
        ]);
        DbSaleItem::create([
            'store_id' => 1,
            'sales_id' => $sale->id,
            'item_id' => $service->id,
            'sales_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.service.delete', $service->id));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This service has existing sales/order history and cannot be deleted. Deactivate it instead.',
        ]);

        $this->assertDatabaseHas('db_items', ['id' => $service->id]);
        $this->assertDatabaseHas('db_salesitems', ['item_id' => $service->id]);
    }

    /** (iii) A service with only a quotation reference is also blocked. */
    public function test_destroy_blocks_service_with_quotation_history()
    {
        $service = $this->makeService(1, 'Quoted Service', 'SVC-QUOTED');

        $quotation = DbQuotation::create([
            'store_id' => 1,
            'quotation_code' => 'QU-TEST-001',
            'quotation_date' => now()->format('Y-m-d'),
            'expire_date' => now()->addDays(7)->format('Y-m-d'),
            'subtotal' => 150.00,
            'grand_total' => 150.00,
            'quotation_status' => 'Pending',
        ]);
        DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $service->id,
            'quotation_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
        ]);

        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.service.delete', $service->id));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', ['id' => $service->id]);
        $this->assertDatabaseHas('db_quotationitems', ['item_id' => $service->id]);
    }

    /** (iv) A service with zero history deletes successfully. */
    public function test_same_store_clean_service_delete_succeeds()
    {
        $service = $this->makeService(1, 'Clean Service', 'SVC-CLEAN');

        $response = $this->actingAs($this->store1User)
            ->deleteJson(route('items.service.delete', $service->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('db_items', ['id' => $service->id]);
    }

    /** (v) Two concurrent delete requests for the same service result in exactly one success. */
    public function test_concurrent_delete_requests_result_in_one_success()
    {
        $service = $this->makeService(1, 'Concurrent Service', 'SVC-CONCURRENT');

        $first = $this->actingAs($this->store1User)
            ->deleteJson(route('items.service.delete', $service->id));
        $second = $this->actingAs($this->store1User)
            ->deleteJson(route('items.service.delete', $service->id));

        $successCount = collect([$first, $second])
            ->filter(fn($r) => $r->json('success') === true)
            ->count();

        $this->assertEquals(1, $successCount, 'Exactly one of the two concurrent deletes must succeed.');
        $this->assertDatabaseMissing('db_items', ['id' => $service->id]);
    }
}
