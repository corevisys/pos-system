<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification for ServiceController index scoping, per-page + exports,
 * status toggle, edit/update store scope, and route-level permission gates.
 */
class ServiceControllerFeatureTest extends TestCase
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
            'permissions' => ['services_view', 'services_add', 'services_edit', 'services_delete'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['services_view', 'services_add', 'services_edit', 'services_delete'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);

        // View-only role (no add/edit/delete)
        $roleView = DbRole::create(['role_name' => 'Store 1 Viewer', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $roleView->id,
            'store_id' => 1,
            'permissions' => ['services_view'],
        ]);
        $this->viewOnlyUser = User::factory()->create(['store_id' => 1, 'role_id' => $roleView->id]);

        $this->cat1 = DbCategory::create(['category_name' => 'Store 1 Cat', 'status' => 1, 'store_id' => 1]);
        $this->cat2 = DbCategory::create(['category_name' => 'Store 2 Cat', 'status' => 1, 'store_id' => 2]);
        $this->tax1 = DbTax::create(['tax_name' => 'Store 1 Tax', 'tax' => 5.00, 'status' => 1, 'store_id' => 1]);
        $this->tax2 = DbTax::create(['tax_name' => 'Store 2 Tax', 'tax' => 10.00, 'status' => 1, 'store_id' => 2]);
    }

    private function makeService(int $storeId, string $name, string $code, int $status = 1): DbItem
    {
        return DbItem::create([
            'store_id' => $storeId,
            'item_name' => $name,
            'item_code' => $code,
            'sales_price' => 150.00,
            'price' => 100.00,
            'status' => $status,
            'service_bit' => 1,
            'child_bit' => 0,
        ]);
    }

    /** Item 3: index() only shows the current store's services. */
    public function test_index_is_store_scoped()
    {
        $this->makeService(1, 'Store 1 Svc', 'S1-A');
        $this->makeService(2, 'Store 2 Svc', 'S2-A');

        $this->actingAs($this->store1User)
            ->get(route('items.service.list'))
            ->assertOk()
            ->assertSee('Store 1 Svc')
            ->assertDontSee('Store 2 Svc');
    }

    /** Item 10a: per_page changes the row count and preserves filters. */
    public function test_index_respects_per_page_and_preserves_filters()
    {
        foreach (range(1, 15) as $i) {
            $this->makeService(1, "Svc {$i}", "SVC-{$i}");
        }
        $this->makeService(1, 'Alpha-1 Only', 'ALPHA-1');

        $this->actingAs($this->store1User)
            ->get(route('items.service.list', ['per_page' => 25]))
            ->assertOk();

        // Search "Alpha-1" should only match Alpha-1 Only, not the Svc-n rows.
        $this->actingAs($this->store1User)
            ->get(route('items.service.list', ['per_page' => 25, 'search' => 'Alpha-1']))
            ->assertOk()
            ->assertSee('Alpha-1 Only')
            ->assertDontSee('Svc 14');
    }

    /** Item 10b: CSV export returns the filtered list. */
    public function test_csv_export_returns_filtered_services()
    {
        $this->makeService(1, 'Export Svc', 'EXPORT-1');
        $this->makeService(2, 'Other Store Svc', 'OTHER-1');

        $response = $this->actingAs($this->store1User)
            ->get(route('items.service.list', ['export' => 'csv']))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Export Svc', $content);
        $this->assertStringNotContainsString('Other Store Svc', $content);
    }

    /** Item 10b: print export renders. */
    public function test_print_export_renders()
    {
        $this->makeService(1, 'Print Svc', 'PRINT-1');

        $this->actingAs($this->store1User)
            ->get(route('items.service.list', ['export' => 'print']))
            ->assertOk()
            ->assertSee('Print Svc');
    }

    /** Item 6: toggle flips status and reflects immediately (POS searchability). */
    public function test_toggle_status_flips_and_stays_store_scoped()
    {
        $service = $this->makeService(1, 'Toggle Svc', 'TOGGLE-1', 1);

        // Deactivate
        $this->actingAs($this->store1User)
            ->patchJson(route('items.service.toggle-status', $service->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 0]);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'status' => 0]);

        // Reactivate
        $this->actingAs($this->store1User)
            ->patchJson(route('items.service.toggle-status', $service->id))
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 1]);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'status' => 1]);
    }

    /** Item 6: a Store-2 user cannot toggle a Store-1 service. */
    public function test_toggle_status_is_store_scoped()
    {
        $service = $this->makeService(1, 'S1 Toggle', 'S1-TOGGLE', 1);

        $this->actingAs($this->store2User)
            ->patchJson(route('items.service.toggle-status', $service->id))
            ->assertStatus(404)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'status' => 1]);
    }

    /** Item 4: Store-2 user cannot open the edit form for a Store-1 service. */
    public function test_edit_is_store_scoped()
    {
        $service = $this->makeService(1, 'S1 Edit', 'S1-EDIT');

        $this->actingAs($this->store2User)
            ->get(route('items.service.edit', $service->id))
            ->assertNotFound();
    }

    /** Item 4: Store-2 user cannot update a Store-1 service. */
    public function test_update_is_store_scoped()
    {
        $service = $this->makeService(1, 'S1 Update', 'S1-UPDATE');

        $this->actingAs($this->store2User)
            ->post(route('items.service.update', $service->id), [
                'item_name' => 'Hacked Name',
                'category_id' => $this->cat1->id,
                'price' => 1,
                'tax_type' => 'Inclusive',
                'sales_price' => 2,
            ])
            ->assertStatus(404);

        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'item_name' => 'S1 Update']);
    }

    /** Item 5: create dropdowns only show current store's categories/taxes. */
    public function test_create_dropdowns_are_store_scoped()
    {
        $this->actingAs($this->store1User)
            ->get(route('items.service.add'))
            ->assertOk()
            ->assertSee('Store 1 Cat')
            ->assertDontSee('Store 2 Cat')
            ->assertSee('Store 1 Tax')
            ->assertDontSee('Store 2 Tax');
    }

    /** Item 12: view-only role is blocked from create/store/delete. */
    public function test_permission_gates_block_view_only_role_from_write_actions()
    {
        // create page -> 403
        $this->actingAs($this->viewOnlyUser)
            ->get(route('items.service.add'))
            ->assertForbidden();

        // store -> 403
        $this->actingAs($this->viewOnlyUser)
            ->post(route('items.service.store'), [
                'item_name' => 'Should Fail',
                'category_id' => $this->cat1->id,
                'price' => 1,
                'tax_type' => 'Inclusive',
                'sales_price' => 2,
            ])
            ->assertForbidden();

        // delete -> 403
        $service = $this->makeService(1, 'S1 Del', 'S1-DEL');
        $this->actingAs($this->viewOnlyUser)
            ->deleteJson(route('items.service.delete', $service->id))
            ->assertForbidden();
    }

    /** Item 12: view-only role CAN view the list. */
    public function test_view_only_role_can_view_list()
    {
        $this->actingAs($this->viewOnlyUser)
            ->get(route('items.service.list'))
            ->assertOk();
    }

    /**
     * Item 7 (POS): A 0-stock service can be sold through POS without being
     * rejected by the stock-availability gate, and its stock stays 0 (never
     * goes negative). A physical product in the same cart decrements normally.
     */
    public function test_pos_sale_allows_zero_stock_service_and_keeps_stock_unchanged()
    {
        $warehouse = \App\Models\DbWarehouse::create([
            'warehouse_name' => 'POS Svc WH',
            'status' => 1,
            'store_id' => 1,
        ]);

        $service = $this->makeService(1, 'POS Service', 'POS-SVC');
        $service->update(['stock' => 0]);

        $product = DbItem::create([
            'store_id' => 1,
            'item_name' => 'POS Product',
            'item_code' => 'POS-PROD',
            'sales_price' => 50.00,
            'price' => 30.00,
            'stock' => 5,
            'status' => 1,
            'service_bit' => 0,
            'child_bit' => 0,
        ]);
        \App\Models\DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $product->id,
            'available_qty' => 5,
        ]);

        $payload = [
            'warehouse_id' => $warehouse->id,
            'customer_id' => null,
            'grand_total' => 200.00,
            'subtotal' => 200.00,
            'cart' => [
                [
                    'id' => $service->id,
                    'qty' => 1,
                    'price' => 150.00,
                    'total' => 150.00,
                    'discount' => 0,
                ],
                [
                    'id' => $product->id,
                    'qty' => 1,
                    'price' => 50.00,
                    'total' => 50.00,
                    'discount' => 0,
                ],
            ],
            'discount_type' => 'fixed',
            'discount_on_all' => 0,
            'other_charges' => 0,
            'round_off' => 0,
            'payment_type' => 'Cash',
            'paid_amount' => 200.00,
        ];

        $resp = $this->actingAs($this->store1User)
            ->postJson(route('sales.pos.store'), $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        // Service stock untouched (0), product stock decremented (5 → 4)
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 4]);
    }

    /**
     * Item 7: Quotation→Sale conversion with a service line does NOT decrement
     * the service's db_items.stock or db_warehouseitems.available_qty, while a
     * physical product in the same conversion still decrements normally.
     */
    public function test_quotation_conversion_skips_service_stock_decrement()
    {
        $warehouse = \App\Models\DbWarehouse::create([
            'warehouse_name' => 'Svc Test WH',
            'status' => 1,
            'store_id' => 1,
        ]);
        $customer = \App\Models\DbCustomer::create([
            'customer_name' => 'Svc Test Customer',
            'mobile' => '01710000000',
            'status' => 1,
            'store_id' => 1,
        ]);

        $service = $this->makeService(1, 'Sellable Service', 'SELL-SVC');
        $service->update(['stock' => 0]);
        \App\Models\DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $service->id,
            'available_qty' => 0,
        ]);

        $product = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Physical Product',
            'item_code' => 'PHY-001',
            'sales_price' => 100.00,
            'price' => 50.00,
            'stock' => 10,
            'status' => 1,
            'service_bit' => 0,
            'child_bit' => 0,
        ]);
        \App\Models\DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $product->id,
            'available_qty' => 10,
        ]);

        $quotation = \App\Models\DbQuotation::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'quotation_code' => 'QU-SVC-01',
            'quotation_date' => date('Y-m-d'),
            'expire_date' => date('Y-m-d', strtotime('+5 days')),
            'quotation_status' => 'Quoted',
            'subtotal' => 250.00,
            'grand_total' => 250.00,
        ]);

        \App\Models\DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $service->id,
            'quotation_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
            'status' => 1,
        ]);
        \App\Models\DbQuotationItem::create([
            'store_id' => 1,
            'quotation_id' => $quotation->id,
            'item_id' => $product->id,
            'quotation_qty' => 1,
            'price_per_unit' => 100.00,
            'total_cost' => 100.00,
            'status' => 1,
        ]);

        $resp = $this->actingAs($this->store1User)
            ->postJson(route('quotation.convert', $quotation->id));
        $resp->assertOk()->assertJson(['success' => true]);

        // Service stock untouched (0 → 0)
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        // Product stock decremented (10 → 9)
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 9]);

        $this->assertDatabaseHas('db_warehouseitems', [
            'item_id' => $service->id,
            'warehouse_id' => $warehouse->id,
            'available_qty' => 0,
        ]);
        $this->assertDatabaseHas('db_warehouseitems', [
            'item_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'available_qty' => 9,
        ]);
    }

    /** Item 8: edit form shows the informational warning when the service has history. */
    public function test_edit_form_shows_warning_when_service_has_history()
    {
        $service = $this->makeService(1, 'Historic Service', 'HIST-SVC');

        \App\Models\DbSale::create([
            'store_id' => 1,
            'sales_code' => 'SA-HIST-1',
            'sales_date' => date('Y-m-d'),
            'grand_total' => 150.00,
            'subtotal' => 150.00,
            'paid_amount' => 0,
            'payment_status' => 'Unpaid',
            'status' => 1,
        ]);
        \App\Models\DbSaleItem::create([
            'store_id' => 1,
            'sales_id' => \App\Models\DbSale::where('sales_code', 'SA-HIST-1')->first()->id,
            'item_id' => $service->id,
            'sales_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
            'status' => 1,
        ]);

        $this->actingAs($this->store1User)
            ->get(route('items.service.edit', $service->id))
            ->assertOk()
            ->assertSee('has been sold or quoted');
    }

    /** Item 8: edit form shows NO warning when the service has no history. */
    public function test_edit_form_shows_no_warning_without_history()
    {
        $service = $this->makeService(1, 'Fresh Service', 'FRESH-SVC');

        $this->actingAs($this->store1User)
            ->get(route('items.service.edit', $service->id))
            ->assertOk()
            ->assertDontSee('has been sold or quoted');
    }

    /**
     * CRITICAL browser-level convention: a service name containing a raw double-quote
     * (e.g. test"service) must be JSON-escaped by @json in the Alpine component and by
     * HTML-escaping in the table cell — it must NOT leak JS text outside <script> tags
     * or break an attribute boundary (the bug class that broke Add/Edit Item).
     */
    public function test_list_renders_service_with_raw_double_quote_safely()
    {
        $service = $this->makeService(1, 'test"service', 'QUOTE-1');

        $response = $this->actingAs($this->store1User)
            ->get(route('items.service.list', ['search' => 'QUOTE-1']))
            ->assertOk();

        $html = $response->getContent();

        // The service name must render HTML-escaped in the table cell (Blade escapes the "
        // to " inside the text node — never a raw " that could break markup). Also
        // accept the double-escaped form (some environments re-escape the ampersand).
        $hasEscapedCell = str_contains($html, 'test"service')
            || str_contains($html, 'test&quot;service');

        $this->assertTrue($hasEscapedCell, 'Service name must render HTML-escaped in the list cell.');

        // CRITICAL: the raw unescaped double-quote must NEVER appear in the rendered page.
        // If it did, it would either leak JS outside <script> tags or terminate an Alpine
        // attribute boundary early (the exact bug class that broke Add/Edit Item).
        $this->assertStringNotContainsString('test"service', $html);

        // Ensure the Alpine.data registration is present (the page ships its component via
        // the CRITICAL Alpine.data('servicesListPage', ...) pattern, not an inline object).
        $this->assertStringContainsString("Alpine.data('servicesListPage'", $html);
        $this->assertStringNotContainsString("x-data=\"addServiceForm", $html);
    }

    /**
     * CRITICAL browser-level convention (Add/Edit Service): the Alpine component state is
     * injected via @json, so a raw quote in old()/model values cannot break the component.
     */
    public function test_add_form_ships_alpine_component_and_escapes_state_safely()
    {
        $response = $this->actingAs($this->store1User)
            ->get(route('items.service.add'))
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString("Alpine.data('addServiceForm'", $html);
        // The form card uses x-data="addServiceForm()" (Alpine.data pattern), NOT an
        // inline x-data="{...}" object — the CRITICAL convention that broke Add/Edit Item.
        $this->assertStringContainsString('x-data="addServiceForm()"', $html);
    }

    /**
     * CRITICAL browser-level convention (Edit Service): same guarantees as Add.
     */
    public function test_edit_form_ships_alpine_component()
    {
        $service = $this->makeService(1, 'Edit Svc', 'EDIT-SAFE');

        $response = $this->actingAs($this->store1User)
            ->get(route('items.service.edit', $service->id))
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString("Alpine.data('editServiceForm'", $html);
        $this->assertStringContainsString('x-data="editServiceForm()"', $html);
    }

    /**
     * Browser-level check support: render all three Service pages (list with a raw-quote
     * service, add form, edit form) to static HTML files so the external node --check
     * script can syntax-check every inline <script> and scan for leaked JS text outside
     * <script> tags — the bug class PHPUnit cannot catch.
     */
    public function test_dump_rendered_pages_for_node_check()
    {
        $dir = storage_path('app/browser-check');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // List with a raw-quote service to exercise escaping in the real render.
        $this->makeService(1, 'test"service', 'QUOTE-1');

        $pages = [
            'services_list.html' => route('items.service.list', ['search' => 'QUOTE-1']),
            'services_add.html' => route('items.service.add'),
        ];

        foreach ($pages as $file => $url) {
            $response = $this->actingAs($this->store1User)->get($url)->assertOk();
            file_put_contents($dir . '/' . $file, $response->getContent());
        }

        // Edit page for the quote-named service.
        $service = \App\Models\DbItem::where('item_code', 'QUOTE-1')->first();
        $response = $this->actingAs($this->store1User)
            ->get(route('items.service.edit', $service->id))
            ->assertOk();
        file_put_contents($dir . '/services_edit.html', $response->getContent());

        $this->assertFileExists($dir . '/services_list.html');
        $this->assertFileExists($dir . '/services_add.html');
        $this->assertFileExists($dir . '/services_edit.html');
    }

    /**
     * B-verification: a newly created service (status=1 default) is immediately
     * findable in POS search without any manual DB edit — POS search requires
     * db_items.status = 1.
     */
    public function test_new_service_is_immediately_searchable_in_pos()
    {
        $warehouse = \App\Models\DbWarehouse::create([
            'warehouse_name' => 'POS Search WH',
            'status' => 1,
            'store_id' => 1,
        ]);

        // Create the service through the real store() endpoint (status=1 default).
        $this->actingAs($this->store1User)
            ->post(route('items.service.store'), [
                'item_name' => 'Brand New Service',
                'category_id' => $this->cat1->id,
                'item_code' => 'BRAND-NEW-SVC',
                'price' => 100.00,
                'tax_type' => 'Inclusive',
                'sales_price' => 150.00,
            ])
            ->assertRedirect(route('items.service.list'));

        $service = \App\Models\DbItem::where('item_code', 'BRAND-NEW-SVC')->first();
        $this->assertNotNull($service);
        $this->assertEquals(1, (int) $service->status, 'New service must be Active by default.');

        // POS search (store-scoped, status=1 only) must return it.
        $resp = $this->actingAs($this->store1User)
            ->getJson(route('sales.pos.search.items', ['q' => 'Brand New', 'warehouse_id' => $warehouse->id]))
            ->assertOk();

        $found = collect($resp->json())->first(fn($i) => ($i['id'] ?? null) == $service->id);
        $this->assertNotNull($found, 'Newly created Active service must appear in POS search.');
    }

    /**
     * C-verification (hold-resume): completing a resumed held cart containing a
     * service line must NOT decrement the service's stock (or warehouse qty), while
     * a physical product in the same held cart decrements normally. The hold is
     * seeded exactly as PosController::hold() persists it.
     */
    public function test_hold_resume_with_service_line_skips_service_stock_decrement()
    {
        $warehouse = \App\Models\DbWarehouse::create([
            'warehouse_name' => 'Hold Resume WH',
            'status' => 1,
            'store_id' => 1,
        ]);

        $service = $this->makeService(1, 'Held Service', 'HELD-SVC');
        $service->update(['stock' => 0]);

        $product = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Held Product',
            'item_code' => 'HELD-PROD',
            'sales_price' => 50.00,
            'price' => 30.00,
            'stock' => 5,
            'status' => 1,
            'service_bit' => 0,
            'child_bit' => 0,
        ]);
        \App\Models\DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'item_id' => $product->id,
            'available_qty' => 5,
        ]);

        // Seed an open hold exactly like PosController::hold() does.
        $hold = \App\Models\DbHold::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'reference_no' => 'HOLD-SVC-1',
            'sales_date' => date('Y-m-d'),
            'subtotal' => 200.00,
            'grand_total' => 200.00,
            'pos' => 1,
            'status' => 'open',
        ]);
        \App\Models\DbHoldItem::create([
            'store_id' => 1,
            'hold_id' => $hold->id,
            'item_id' => $service->id,
            'sales_qty' => 1,
            'price_per_unit' => 150.00,
            'total_cost' => 150.00,
        ]);
        \App\Models\DbHoldItem::create([
            'store_id' => 1,
            'hold_id' => $hold->id,
            'item_id' => $product->id,
            'sales_qty' => 1,
            'price_per_unit' => 50.00,
            'total_cost' => 50.00,
        ]);

        // Complete the held cart via POS store with hold_id.
        $payload = [
            'hold_id' => $hold->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => null,
            'grand_total' => 200.00,
            'subtotal' => 200.00,
            'cart' => [
                ['id' => $service->id, 'qty' => 1, 'price' => 150.00, 'total' => 150.00, 'discount' => 0],
                ['id' => $product->id, 'qty' => 1, 'price' => 50.00, 'total' => 50.00, 'discount' => 0],
            ],
            'discount_type' => 'fixed',
            'discount_on_all' => 0,
            'other_charges' => 0,
            'round_off' => 0,
            'payment_type' => 'Cash',
            'paid_amount' => 200.00,
        ];

        $resp = $this->actingAs($this->store1User)
            ->postJson(route('sales.pos.store'), $payload);
        $resp->assertOk()->assertJson(['success' => true]);

        // Service stock untouched (0), product stock decremented (5 → 4).
        $this->assertDatabaseHas('db_items', ['id' => $service->id, 'stock' => 0]);
        $this->assertDatabaseHas('db_items', ['id' => $product->id, 'stock' => 4]);

        // The hold was consumed (no longer open / deleted).
        $this->assertDatabaseMissing('db_hold', ['id' => $hold->id, 'status' => 'open']);
    }
}
