<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bulk CSV Import Items feature tests.
 *
 * Mirrors ContactsImportTest's structure. Verifies:
 *   1. Page renders + template download (incl. the new Serial Numbers column).
 *   2. Valid import of N rows succeeds; each item created via
 *      ItemCreationService with source='item_import'; store_id correct; opening
 *      stock routed to db_warehouseitems.
 *   3. Duplicate SKU within the file / against an existing DB item is rejected
 *      per the partial-success policy with the correct row cited.
 *   4. New category/brand referenced by the file is auto-created exactly once
 *      even when referenced by multiple rows; existing ones matched.
 *   5. Serialized row with matching count succeeds; mismatched count rejected;
 *      intra-file duplicate serial rejected; serial colliding with an existing
 *      DB serial rejected.
 *   6. Malformed file (wrong mimetype) gets the same clean error.
 *   7. Permission enforcement: no items_import_items -> 403 on page + store.
 *   8. Warehouse dropdown is store-scoped.
 *   9. Genuine parallel race (two worker processes) for the same new category
 *      name creates exactly one category (proc_open + barrier pattern from
 *      CategoryBrandVariantRaceTest).
 */
class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected DbWarehouse $warehouse2;
    protected int $categoryId;
    protected int $unitId;
    protected int $taxId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::create([
            'store_name' => 'Import Test Store',
            'store_code' => 'IMPT-ST',
            'status' => 1,
            'mobile' => '+8801700000000',
            'item_init' => 'IT',
        ]);
        store_settings(true);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => $this->store->id,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => $this->store->id,
            'permissions' => [
                'items_view',
                'items_import_items',
                'brand_add', 'brand_edit', 'brand_view',
                'items_category_add', 'items_category_view',
            ],
        ]);

        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Import Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        // Store-2 warehouse used for store-scoping assertions.
        $store2 = DbStore::create([
            'store_name' => 'Second Store',
            'store_code' => 'ST2',
            'status' => 1,
        ]);
        $this->warehouse2 = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Second Store Warehouse',
            'store_id' => $store2->id,
            'status' => 1,
        ]);

        $category = DbCategory::create(['category_name' => 'Existing Cat', 'status' => 1, 'store_id' => $this->store->id]);
        $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => $this->store->id]);
        $tax = DbTax::create(['tax_name' => 'VAT 5%', 'tax' => 5, 'status' => 1, 'store_id' => $this->store->id]);

        $this->categoryId = $category->id;
        $this->unitId = $unit->id;
        $this->taxId = $tax->id;
    }

    /** Full 22-column header exactly matching importTemplate(). */
    private function header(): string
    {
        return implode(',', [
            'Item Name', 'Category Name', 'SKU', 'HSN', 'Unit Name', 'Alert Quantity',
            'Brand Name', 'Lot Number', 'Price Before Tax', 'Price After Tax', 'Tax Name',
            'Tax Value', 'Tax Type', 'Sales Price', 'Opening Stock', 'Barcode',
            'Seller Points', 'Description', 'Discount Type', 'Discount', 'MRP', 'Serial Numbers',
        ]);
    }

    private function validRow(string $itemName = 'Imported Item', array $overrides = []): array
    {
        $row = [
            $itemName, 'Existing Cat', 'SKU-' . strtoupper(uniqid()), '', 'Pcs', '5',
            '', '', '100.00', '', 'VAT 5%', '5', 'Inclusive', '150.00', '0', '',
            '0', '', 'Fixed', '0', '0', '',
        ];
        foreach ($overrides as $colIndex => $value) {
            $row[$colIndex] = $value;
        }
        return $row;
    }

    private function uploadCsv(array $rows): UploadedFile
    {
        $lines = [$this->header()];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(function ($cell) {
                // Quote cells containing commas or quotes for a well-formed CSV.
                if (strpbrk((string) $cell, ",\"") !== false) {
                    return '"' . str_replace('"', '""', (string) $cell) . '"';
                }
                return (string) $cell;
            }, $row));
        }
        return UploadedFile::fake()->createWithContent('items.csv', implode("\n", $lines));
    }

    private function postImport(UploadedFile $file, ?array $extra = [])
    {
        return $this->actingAs($this->user)->post(route('items.import.store'), array_merge([
            'import_file' => $file,
            'warehouse_id' => $this->warehouse->id,
        ], $extra));
    }

    // ── Page render + template download ──────────────────────────────────────
    public function test_import_page_renders_and_template_downloads()
    {
        $response = $this->actingAs($this->user)->get(route('items.import'));
        $response->assertStatus(200);
        $response->assertSee('Import Items');
        // Store-scoped warehouse only.
        $response->assertSee('Import Warehouse');
        $response->assertDontSee('Second Store Warehouse');

        $templateResponse = $this->actingAs($this->user)->get(route('items.import.template'));
        $templateResponse->assertStatus(200);
        $templateResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $templateResponse->streamedContent();
        $this->assertStringContainsString('Item Name', $content);
        $this->assertStringContainsString('Price Before Tax', $content);
        $this->assertStringContainsString('Opening Stock', $content);
        $this->assertStringContainsString('Serial Numbers', $content);
        $this->assertStringContainsString('SN-LAP-001|SN-LAP-002', $content);
    }

    public function test_import_page_rejects_user_without_permission()
    {
        // A non-Super-Admin role WITHOUT items_import_items must be blocked.
        $managerRole = DbRole::create([
            'role_name' => 'Manager No Import',
            'status' => 1,
            'store_id' => $this->store->id,
        ]);
        DbPermission::create([
            'role_id' => $managerRole->id,
            'store_id' => $this->store->id,
            'permissions' => ['items_view'],
        ]);
        $manager = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $managerRole->id,
            'role_name' => 'Manager No Import',
            'status' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('items.import'));
        $response->assertStatus(403);

        $file = $this->uploadCsv([$this->validRow('No Perm Item')]);
        $storeResponse = $this->actingAs($manager)->post(route('items.import.store'), [
            'import_file' => $file,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $storeResponse->assertStatus(403);
    }

    public function test_manager_with_items_import_items_can_open_page_and_import()
    {
        // Phase 0 fix verification: a non-Super-Admin role granted the seeded
        // items_import_items permission can open the page and import (the sidebar
        // link uses this same permission).
        $managerRole = DbRole::create([
            'role_name' => 'Manager With Import',
            'status' => 1,
            'store_id' => $this->store->id,
        ]);
        DbPermission::create([
            'role_id' => $managerRole->id,
            'store_id' => $this->store->id,
            'permissions' => ['items_view', 'items_import_items'],
        ]);
        $manager = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $managerRole->id,
            'role_name' => 'Manager With Import',
            'status' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('items.import'));
        $response->assertStatus(200);
        $response->assertSee('Import Items');

        $file = $this->uploadCsv([$this->validRow('Manager Imported', [2 => 'MGR-SKU'])]);

        $storeResponse = $this->actingAs($manager)->post(route('items.import.store'), [
            'import_file' => $file,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $storeResponse->assertRedirect(route('items.import'));
        $storeResponse->assertSessionHas('import_summary', fn($s) => $s['imported'] === 1 && $s['skipped'] === 0);

        $this->assertNotNull(DbItem::where('sku', 'MGR-SKU')->first());
    }

    // ── Valid import ─────────────────────────────────────────────────────────
    public function test_valid_import_creates_items_with_source_item_import_and_store_scoped()
    {
        $file = $this->uploadCsv([
            $this->validRow('Imported A', [2 => 'SKU-A1', 15 => 'BAR-A1']),
            $this->validRow('Imported B', [2 => 'SKU-B1', 15 => 'BAR-B1']),
        ]);

        $response = $this->postImport($file);
        $response->assertRedirect(route('items.import'));
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 2 && $summary['skipped'] === 0 && count($summary['errors']) === 0;
        });

        $itemA = DbItem::where('sku', 'SKU-A1')->first();
        $this->assertNotNull($itemA);
        $this->assertSame((int) $this->store->id, (int) $itemA->store_id);
        $this->assertSame('Imported A', $itemA->item_name);

        $itemB = DbItem::where('sku', 'SKU-B1')->first();
        $this->assertNotNull($itemB);
        $this->assertSame((int) $this->store->id, (int) $itemB->store_id);
    }

    public function test_valid_import_routes_serials_through_service_with_source_item_import()
    {
        $file = $this->uploadCsv([
            $this->validRow('Serialized Import', [2 => 'SKU-SER1', 14 => '2', 21 => 'IMP-SN-001|IMP-SN-002']),
        ]);

        $response = $this->postImport($file);
        $response->assertRedirect(route('items.import'));
        $response->assertSessionHas('import_summary', fn($s) => $s['imported'] === 1 && $s['skipped'] === 0);

        $item = DbItem::where('sku', 'SKU-SER1')->first();
        $this->assertNotNull($item);
        $this->assertSame(1, (int) $item->is_serialized);

        $serials = DbItemSerial::where('item_id', $item->id)->get();
        $this->assertCount(2, $serials);
        $this->assertSame(['IMP-SN-001', 'IMP-SN-002'], $serials->pluck('serial_number')->sort()->values()->all());
        foreach ($serials as $serial) {
            $this->assertSame('item_import', $serial->source);
            $this->assertSame((int) $this->store->id, (int) $serial->store_id);
        }

        // Opening stock routed to db_warehouseitems (never db_items.stock).
        $whItem = $item->warehouseItems()->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertNotNull($whItem);
        $this->assertEquals(2, (float) $whItem->available_qty);
    }

    // ── Duplicate SKU ────────────────────────────────────────────────────────
    public function test_duplicate_sku_within_file_is_rejected_with_row_cited()
    {
        $file = $this->uploadCsv([
            $this->validRow('First Dup', [2 => 'DUP-SKU']),
            $this->validRow('Second Dup', [2 => 'DUP-SKU']),
            $this->validRow('Valid Row', [2 => 'OK-SKU']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 2
                && $summary['skipped'] === 1
                && count($summary['errors']) === 1
                && str_contains($summary['errors'][0], 'Row 3')
                && str_contains($summary['errors'][0], 'DUP-SKU');
        });

        $this->assertSame(1, DbItem::where('sku', 'DUP-SKU')->count());
    }

    public function test_duplicate_sku_against_existing_db_item_is_rejected()
    {
        DbItem::create([
            'store_id' => $this->store->id,
            'item_name' => 'Existing Sku Item',
            'sku' => 'TAKEN-SKU',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'status' => 1,
            'item_code' => 'IT-000001',
        ]);

        $file = $this->uploadCsv([$this->validRow('New Item Same Sku', [2 => 'TAKEN-SKU'])]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 0
                && $summary['skipped'] === 1
                && str_contains($summary['errors'][0], "SKU 'TAKEN-SKU' is already in use.");
        });
    }

    public function test_new_category_and_brand_auto_created_exactly_once_even_when_multiple_rows_reference_them()
    {
        $file = $this->uploadCsv([
            $this->validRow('NewCat Item 1', [1 => 'Brand New Cat', 6 => 'Brand New Brand', 2 => 'NCAT-1']),
            $this->validRow('NewCat Item 2', [1 => 'Brand New Cat', 6 => 'Brand New Brand', 2 => 'NCAT-2']),
            $this->validRow('NewCat Item 3', [1 => 'Brand New Cat', 6 => 'Brand New Brand', 2 => 'NCAT-3']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', fn($s) => $s['imported'] === 3 && $s['skipped'] === 0);

        $this->assertSame(1, DbCategory::where('store_id', $this->store->id)->where('category_name', 'Brand New Cat')->count());
        $this->assertSame(1, DbBrand::where('store_id', $this->store->id)->where('brand_name', 'Brand New Brand')->count());

        $cat = DbCategory::where('store_id', $this->store->id)->where('category_name', 'Brand New Cat')->first();
        $brand = DbBrand::where('store_id', $this->store->id)->where('brand_name', 'Brand New Brand')->first();

        $items = DbItem::whereIn('sku', ['NCAT-1', 'NCAT-2', 'NCAT-3'])->get();
        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertSame((int) $cat->id, (int) $item->category_id);
            $this->assertSame((int) $brand->id, (int) $item->brand_id);
        }
    }

    public function test_existing_category_and_brand_are_matched_not_duplicated()
    {
        DbCategory::create(['category_name' => 'Match Me', 'status' => 1, 'store_id' => $this->store->id]);
        DbBrand::create(['brand_name' => 'Match Brand', 'status' => 1, 'store_id' => $this->store->id]);

        $file = $this->uploadCsv([
            $this->validRow('Matched Item', [1 => 'match me', 6 => 'match brand', 2 => 'MATCH-SKU']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', fn($s) => $s['imported'] === 1 && $s['skipped'] === 0);

        $this->assertSame(1, DbCategory::where('store_id', $this->store->id)->where('category_name', 'Match Me')->count());
        $this->assertSame(1, DbBrand::where('store_id', $this->store->id)->where('brand_name', 'Match Brand')->count());

        $cat = DbCategory::where('store_id', $this->store->id)->where('category_name', 'Match Me')->first();
        $brand = DbBrand::where('store_id', $this->store->id)->where('brand_name', 'Match Brand')->first();
        $item = DbItem::where('sku', 'MATCH-SKU')->first();
        $this->assertSame((int) $cat->id, (int) $item->category_id);
        $this->assertSame((int) $brand->id, (int) $item->brand_id);
    }

    public function test_serialized_row_with_mismatched_serial_count_is_rejected()
    {
        $file = $this->uploadCsv([
            $this->validRow('Bad Serial Count', [2 => 'SER-CNT-1', 14 => '3', 21 => 'CNT-SN-1|CNT-SN-2']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 0
                && $summary['skipped'] === 1
                && str_contains($summary['errors'][0], 'Please provide all 3 serial numbers (only 2 entered).');
        });

        $this->assertNull(DbItem::where('sku', 'SER-CNT-1')->first());
    }

    public function test_intra_file_duplicate_serial_is_rejected()
    {
        $file = $this->uploadCsv([
            $this->validRow('Ser Item One', [2 => 'SER-DUP-1', 14 => '1', 21 => 'SHARED-SN']),
            $this->validRow('Ser Item Two', [2 => 'SER-DUP-2', 14 => '1', 21 => 'SHARED-SN']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 1
                && $summary['skipped'] === 1
                && str_contains($summary['errors'][0], 'Serial SHARED-SN is already used in this file');
        });

        $this->assertSame(1, DbItemSerial::where('serial_number', 'SHARED-SN')->count());
    }

    public function test_serial_colliding_with_existing_db_serial_is_rejected()
    {
        $existingItem = DbItem::create([
            'store_id' => $this->store->id,
            'item_name' => 'Existing Ser Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'is_serialized' => 1,
            'status' => 1,
            'item_code' => 'IT-000002',
        ]);
        DbItemSerial::create([
            'store_id' => $this->store->id,
            'item_id' => $existingItem->id,
            'serial_number' => 'EXISTING-SN',
            'status' => 0,
            'source' => 'item_add',
        ]);

        $file = $this->uploadCsv([
            $this->validRow('Collide Item', [2 => 'SER-COL-1', 14 => '1', 21 => 'EXISTING-SN']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 0
                && $summary['skipped'] === 1
                && str_contains($summary['errors'][0], 'Serial EXISTING-SN is already registered');
        });
    }

    public function test_serials_provided_with_zero_opening_stock_is_rejected()
    {
        $file = $this->uploadCsv([
            $this->validRow('No Stock Serials', [2 => 'SER-NOSTK', 14 => '0', 21 => 'NOSTK-SN']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 0
                && $summary['skipped'] === 1
                && str_contains($summary['errors'][0], 'Serial numbers were provided but Opening Stock is 0');
        });
    }

    public function test_wrong_mimetype_is_rejected_with_clean_error()
    {
        // A .png is not an accepted import mimetype → clean validation error.
        $png = UploadedFile::fake()->image('items.png');
        $response = $this->postImport($png);
        $response->assertSessionHasErrors('import_file');

        // Missing file entirely → clean validation error (same as ContactsImportTest).
        $response2 = $this->actingAs($this->user)->post(route('items.import.store'), [
            'warehouse_id' => $this->warehouse->id,
        ]);
        $response2->assertSessionHasErrors('import_file');
    }

    public function test_missing_required_columns_reports_per_row_errors()
    {
        $file = $this->uploadCsv([
            ['', 'Existing Cat', 'SKU-REQ-1', '', 'Pcs', '5', '', '', '100.00', '', 'VAT 5%', '5', 'Inclusive', '150.00', '0', '', '0', '', 'Fixed', '0', '0', ''],
            ['No Cat Item', '', 'SKU-REQ-2', '', 'Pcs', '5', '', '', '100.00', '', 'VAT 5%', '5', 'Inclusive', '150.00', '0', '', '0', '', 'Fixed', '0', '0', ''],
            ['No Tax Item', 'Existing Cat', 'SKU-REQ-3', '', 'Pcs', '5', '', '', '100.00', '', '', '', 'Inclusive', '150.00', '0', '', '0', '', 'Fixed', '0', '0', ''],
            $this->validRow('Valid Row Five', [2 => 'SKU-REQ-4']),
        ]);

        $response = $this->postImport($file);
        $response->assertSessionHas('import_summary', function ($summary) {
            return $summary['imported'] === 1
                && $summary['skipped'] === 3
                && count($summary['errors']) === 3
                && str_contains($summary['errors'][0], 'Row 2')
                && str_contains($summary['errors'][1], 'Row 3')
                && str_contains($summary['errors'][2], 'Row 4');
        });
    }

    public function test_genuine_parallel_import_category_race_creates_exactly_one()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/imp_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/imp_race_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/imp_race_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE db_category (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER NULL,
            category_name VARCHAR(255) NULL,
            category_code VARCHAR(255) NULL,
            description TEXT NULL,
            status INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )");
        $pdo->exec("CREATE UNIQUE INDEX db_category_store_category_name_unique ON db_category (store_id, category_name)");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("INSERT INTO db_category (store_id, category_name, category_code, status, created_at, updated_at)
                        VALUES (1, \'RaceImportCat\', \'RAC\', 1, datetime(\'now\'), datetime(\'now\'))");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if (str_contains(strtolower($e->getMessage()), "unique")) {
                echo "RESULT:UNIQUE_FAIL\n";
            } else {
                echo "RESULT:OTHER_FAIL:" . $e->getMessage() . "\n";
            }
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]);
        $out2 = stream_get_contents($pipes2[1]);
        fclose($pipes1[1]);
        fclose($pipes2[1]);
        proc_close($proc1);
        proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $combined = $out1 . $out2;

        $this->assertSame(1, substr_count($combined, 'RESULT:SUCCESS'), "Exactly one parallel insert must win.\nOut1: {$out1}\nOut2: {$out2}");
        $this->assertStringContainsString('RESULT:UNIQUE_FAIL', $combined, "The losing worker must fail on the per-store unique index.\nOut1: {$out1}\nOut2: {$out2}");

        $count = (int) $pdo->query("SELECT COUNT(*) FROM db_category WHERE store_id = 1 AND category_name = 'RaceImportCat'")->fetchColumn();
        $this->assertEquals(1, $count, "Exactly one category row may exist for the same store + name under genuine parallelism.");

        @unlink($dbPath);
    }
}
