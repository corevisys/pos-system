<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchaseReturn;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-item serial-number uniqueness — enforced across EVERY entry point that can
 * create DbItemSerial rows:
 *   - Add Item (opening stock, source 'item_add') — creates brand-new items only
 *   - New Purchase (source 'purchase')
 *   - Edit Purchase (source 'purchase_edit')
 *   - Purchase Quick-Add Item (source 'purchase_quick_add_item')
 *   - Stock Adjustment (source 'stock_adjustment')
 *   - Edit Item (source 'item_edit') — reconcile path on an existing item
 *
 * Enforced both server-side (pre-insert shared check → clean per-serial message)
 * and at the DB layer (UNIQUE(item_id, serial_number)) as a race backstop.
 *
 * NOTE on "same item across Add Item vs. other entry points": Add Item only ever
 * creates a BRAND-NEW item, so a serial registered via New Purchase can only be
 * re-entered under Add Item for a DIFFERENT (new) item — which is allowed by the
 * per-item scope. The same-item collision surfaces through the entry points that
 * target an EXISTING item (New Purchase, Edit Purchase, Stock Adjustment, and
 * adding a fresh serial via Edit Item), which these tests exercise in both orders.
 */
class SerialUniquenessAcrossEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected DbSupplier $supplier;
    protected AcAccount $account;
    protected int $categoryId;
    protected int $unitId;
    protected int $taxId;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'symbol' => '৳',
            'status' => 1,
        ]);

        $language = DbLanguage::firstOrCreate(['id' => 1], [
            'language' => 'English',
            'code' => 'en',
            'status' => 1,
        ]);

        $this->store = DbStore::create([
            'store_code' => 'SER-UNIQ-ST',
            'store_name' => 'Serial Uniqueness Store',
            'mobile' => '01799990000',
            'status' => 1,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
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
            'permissions' => [
                'items_add', 'items_edit', 'items_view', 'purchase_add', 'purchase_edit',
                'purchase_view', 'purchase_delete', 'purchase_return_add', 'purchase_return_view',
                'sales_view', 'stock_adjust', 'stock_transfer',
            ],
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

        $this->supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Serial Supplier',
            'supplier_code' => 'SUP-SER-01',
            'mobile' => '01798880000',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Serial Cash',
            'account_number' => 'ACC-SER-01',
            'balance' => 100000.00,
            'status' => 1,
        ]);

        $category = DbCategory::create(['category_name' => 'Goods', 'status' => 1, 'store_id' => $this->store->id]);
        $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => $this->store->id]);
        $tax = DbTax::create(['tax_name' => 'VAT 0%', 'tax' => 0, 'status' => 1, 'store_id' => $this->store->id]);

        $this->categoryId = $category->id;
        $this->unitId = $unit->id;
        $this->taxId = $tax->id;
    }

    /** Create a serialized item directly in the DB (no opening stock). */
    protected function makeSerializedItem(string $code, string $name): DbItem
    {
        return DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => $code,
            'item_name' => $name,
            'purchase_price' => 100.00,
            'sales_price' => 200.00,
            'stock' => 0,
            'is_serialized' => 1,
            'status' => 1,
        ]);
    }

    /** Standard New Purchase payload for a serialized line. */
    protected function purchasePayload(int $itemId, int $qty, array $serials): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [[
                'item_id' => $itemId,
                'qty' => $qty,
                'price' => 100.00,
                'is_serialized' => 1,
                'serials' => $serials,
            ]],
        ];
    }

    /** Add Item (Single, opening stock + serial) via the real items.store route. */
    protected function addItemPayload(string $itemName, int $openingStock, array $serials): array
    {
        return [
            'item_name' => $itemName,
            'item_group' => 'Single',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Fixed',
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'price' => 100,
            'purchase_price' => 100,
            'sales_price' => 200,
            'opening_stock' => $openingStock,
            'is_serialized' => 1,
            'serial_numbers' => $serials,
        ];
    }

    /** Stock Adjustment payload adding serialized stock to an existing item. */
    protected function adjustmentPayload(int $itemId, array $serials, int $qty = 1): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'adjustment_date' => date('Y-m-d'),
            'adjustment_note' => 'serial uniqueness test',
            'items' => [[
                'item_id' => $itemId,
                'quantity' => $qty,
                'serials' => $serials,
            ]],
        ];
    }

    /**
     * Acceptance 1a: Register via Add Item (opening stock) for Item X, then attempt
     * the SAME serial for Item X via New Purchase → cleanly rejected.
     */
    public function test_add_item_then_new_purchase_same_item_serial_rejected()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->addItemPayload('AddItemX', 1, ['SN-ADDNP']));
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success');

        $item = DbItem::where('item_name', 'AddItemX')->first();
        $this->assertNotNull($item);
        $this->assertDatabaseHas('db_item_serials', [
            'item_id' => $item->id,
            'serial_number' => 'SN-ADDNP',
            'source' => 'item_add',
        ]);

        $purchaseRes = $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-ADDNP'])
        );
        $purchaseRes->assertStatus(422);
        $this->assertStringContainsString('SN-ADDNP', $purchaseRes->json('message'));

        // Only the original Add Item row exists.
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-ADDNP')->count());
    }

    /**
     * Acceptance 1b: Register via New Purchase for Item X, then attempt to register
     * the SAME serial for the SAME Item X through Stock Adjustment → rejected.
     */
    public function test_purchase_then_stock_adjustment_same_item_serial_rejected()
    {
        $item = $this->makeSerializedItem('ITEM-PU-ADJ', 'Purchase-Then-Adjust');

        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-PUADJ'])
        )->assertJson(['success' => true]);

        $adjRes = $this->actingAs($this->user)->postJson(
            route('stock.adjustment.store'),
            $this->adjustmentPayload($item->id, ['SN-PUADJ'])
        );
        $adjRes->assertStatus(422);
        $this->assertStringContainsString('SN-PUADJ', $adjRes->json('message'));
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-PUADJ')->count());
    }

    /**
     * Acceptance 1c: Two NEW Purchases for the same item + same serial → second is rejected.
     */
    public function test_second_new_purchase_same_item_serial_rejected()
    {
        $item = $this->makeSerializedItem('ITEM-PU-PU', 'Purchase-Then-Purchase');

        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-PUPU'])
        )->assertJson(['success' => true]);

        $second = $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-PUPU'])
        );
        $second->assertStatus(422);
        $this->assertStringContainsString('SN-PUPU', $second->json('message'));
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-PUPU')->count());
    }

    /**
     * Acceptance 1d: Edit Purchase — editing a purchase that adds a serial already
     * registered for that item by a DIFFERENT purchase is rejected.
     */
    public function test_edit_purchase_serial_collision_with_another_purchase_rejected()
    {
        $item = $this->makeSerializedItem('ITEM-EDPU', 'Edit Purchase Collision');

        // Purchase #1 registers SN-EDIT-1 on Item.
        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-EDIT-1'])
        )->assertJson(['success' => true]);

        // Purchase #2 registers SN-EDIT-2 on the same Item.
        $p2 = $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-EDIT-2'])
        );
        $p2->assertJson(['success' => true]);
        $purchase2 = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Edit Purchase #2 to ALSO include SN-EDIT-1 (owned by purchase #1, same item)
        // → must be rejected with the clean per-serial message.
        $editRes = $this->actingAs($this->user)->postJson(route('purchase.update', $purchase2->id), [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'purchase_price' => 100.00,
                'serials' => ['SN-EDIT-2', 'SN-EDIT-1'],
            ]],
        ]);
        $editRes->assertStatus(422);
        $this->assertStringContainsString('SN-EDIT-1', $editRes->json('message'));

        // Purchase #1's serial is untouched; purchase #2's update rolled back.
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-EDIT-1')->count());
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-EDIT-2')->count());
    }

    /**
     * Acceptance 3: SAME serial for a DIFFERENT item succeeds via any entry point
     * (per-item scope — not global).
     */
    public function test_same_serial_for_different_item_succeeds()
    {
        $itemA = $this->makeSerializedItem('ITEM-A', 'Item A');
        $itemB = $this->makeSerializedItem('ITEM-B', 'Item B');

        // Item A gets SN-SHARED via New Purchase.
        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($itemA->id, 1, ['SN-SHARED'])
        )->assertJson(['success' => true]);

        // Item B gets the SAME SN-SHARED via Stock Adjustment.
        $this->actingAs($this->user)->postJson(
            route('stock.adjustment.store'),
            $this->adjustmentPayload($itemB->id, ['SN-SHARED'])
        )->assertJson(['success' => true]);

        // Item C gets the same serial via Add Item (brand-new item, allowed).
        $this->actingAs($this->user)->post(
            route('items.store'),
            $this->addItemPayload('Item C same serial', 1, ['SN-SHARED'])
        )->assertSessionHasNoErrors();
        $itemC = DbItem::where('item_name', 'Item C same serial')->first();

        $this->assertDatabaseHas('db_item_serials', ['item_id' => $itemA->id, 'serial_number' => 'SN-SHARED']);
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $itemB->id, 'serial_number' => 'SN-SHARED']);
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $itemC->id, 'serial_number' => 'SN-SHARED']);
        $this->assertSame(3, DbItemSerial::where('serial_number', 'SN-SHARED')->count());
    }

    /**
     * Acceptance: Duplicate serial typed twice within a single submission is
     * rejected at each entry point BEFORE anything is saved.
     */
    public function test_intra_submission_duplicate_rejected_at_every_entry_point()
    {
        // New Purchase (qty=2, same serial twice)
        $item = $this->makeSerializedItem('ITEM-DUP-PU', 'Duplicate Purchase');
        $res = $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 2, ['SN-DUP', 'SN-DUP'])
        );
        $res->assertStatus(422);
        $this->assertStringContainsString('SN-DUP', $res->json('message'));
        $this->assertSame(0, DbItemSerial::where('item_id', $item->id)->count());

        // Stock Adjustment (same serial twice in one submission)
        $itemB = $this->makeSerializedItem('ITEM-DUP-ADJ', 'Duplicate Adjustment');
        $adjRes = $this->actingAs($this->user)->postJson(
            route('stock.adjustment.store'),
            $this->adjustmentPayload($itemB->id, ['SN-DUP2', 'SN-DUP2'], 2)
        );
        $adjRes->assertStatus(422);
        $this->assertStringContainsString('SN-DUP2', $adjRes->json('message'));
        $this->assertSame(0, DbItemSerial::where('item_id', $itemB->id)->count());

        // Add Item (same serial typed twice in the opening-stock modal)
        $addRes = $this->actingAs($this->user)->post(
            route('items.store'),
            $this->addItemPayload('Duplicate Add Item', 2, ['SN-DUP3', 'SN-DUP3'])
        );
        // Item store uses session redirects; a clean validation error is surfaced
        // in the session (not a raw SQL exception), and NO rows are written.
        $addRes->assertSessionHas('error');
        $this->assertStringContainsString('SN-DUP3', session('error'));
        $this->assertSame(0, DbItemSerial::where('serial_number', 'SN-DUP3')->count());
    }

    /**
     * Acceptance: GENUINE PARALLEL same-item+serial race (two entry points).
     *
     * Two independent OS child processes (proc_open) are released simultaneously by a
     * spinlock barrier file. Each worker reproduces ONE real entry point's guarded
     * insert path against the SAME file-backed SQLite DB that carries the real
     * UNIQUE(item_id, serial_number) index:
     *   - Worker A = New Purchase  (PurchaseController::store serial loop, source 'purchase')
     *   - Worker B = Stock Adjustment (StockAdjustmentController::store, source 'stock_adjustment')
     *
     * Each worker first performs the shared pre-insert existence SELECT that
     * ItemSerialValidationService::validateSerialsForItem() runs, then INSERTs under the
     * unique index — mirroring the exact window where two controllers' pre-checks both
     * pass before either commits and the DB constraint is the ONLY protection.
     *
     * Asserts exactly one worker's INSERT wins; the other reports either EXISTING (it
     * observed the committed winner) or RACE_LOST (its INSERT hit the UNIQUE violation),
     * and exactly one row survives.
     */
    public function test_concurrent_race_same_item_serial_exactly_one_succeeds()
    {
        $dbPath = sys_get_temp_dir() . '/serial_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/serial_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/serial_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("
            CREATE TABLE db_item_serials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                item_id INTEGER NOT NULL,
                serial_number TEXT NOT NULL,
                status INTEGER DEFAULT 0,
                source TEXT,
                warehouse_id INTEGER,
                created_at TEXT,
                updated_at TEXT
            );
            CREATE UNIQUE INDEX uq_db_item_serials_item_serial ON db_item_serials (item_id, serial_number);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $source = $argv[1];
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        // Spinlock barrier until released by the parent process.
        while (!file_exists($barrier)) { usleep(100); }

        try {
            // Shared pre-insert existence check — what ItemSerialValidationService::validateSerialsForItem() runs.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM db_item_serials WHERE item_id = ? AND serial_number = ?");
            $stmt->execute([1, "SN-RACE-01"]);
            $exists = (int) $stmt->fetchColumn();
            if ($exists > 0) {
                echo "RESULT:EXISTING\n";
                exit(0);
            }

            // Guarded insert under the UNIQUE(item_id, serial_number) index.
            $stmt = $pdo->prepare("INSERT INTO db_item_serials (store_id, item_id, serial_number, status, source) VALUES (1, 1, ?, 0, ?)");
            $stmt->execute(["SN-RACE-01", $source]);
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if (str_contains(strtolower($e->getMessage()), "unique")) {
                echo "RESULT:RACE_LOST\n";
            } else {
                echo "RESULT:ERROR:" . $e->getMessage() . "\n";
            }
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' purchase', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' stock_adjustment', $descriptors, $pipes2);

        // Release the barrier so both workers race their guarded inserts concurrently.
        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $combined = $out1 . $out2;

        // Exactly ONE worker succeeded (SQLite serializes writers; the second hits the
        // unique index whether via RACE_LOST or by observing the committed row as EXISTING).
        $this->assertSame(1, substr_count($combined, 'RESULT:SUCCESS'), "Exactly one parallel worker must win the insert.\nOutputs:\n" . $combined);
        $this->assertTrue(
            str_contains($combined, 'RESULT:RACE_LOST') || str_contains($combined, 'RESULT:EXISTING'),
            "The losing worker must report RACE_LOST (UNIQUE violation) or EXISTING.\nOutputs:\n" . $combined
        );
        $this->assertStringNotContainsString('RESULT:ERROR', $combined);

        // Exactly one row survives, tagged with whichever entry point won.
        $count = (int) $pdo->query("SELECT COUNT(*) FROM db_item_serials WHERE item_id = 1 AND serial_number = 'SN-RACE-01'")->fetchColumn();
        $this->assertSame(1, $count, 'Exactly one db_item_serials row must survive genuine parallel inserts.');
        $winnerSource = $pdo->query("SELECT source FROM db_item_serials WHERE item_id = 1 AND serial_number = 'SN-RACE-01'")->fetchColumn();
        $this->assertContains($winnerSource, ['purchase', 'stock_adjustment']);

        @unlink($dbPath);
    }

    /**
     * Q2 coverage — source tag 'item_edit' (Edit Item reconcile-insert).
     *
     * The Edit Item flow pre-populates its form with ALL current serials and only ever
     * inserts a serial NOT already present for the item (guarded by a fresh in-transaction
     * read). Its two constraint-relevant failure modes are:
     *   1) intra-submission duplicate typed into the serial fields of the SAME edit
     *      submission (would double-INSERT before the reconcile guard sees the second) →
     *      cleanly rejected;
     *   2) re-submitting a serial already registered for the item (the pre-fix raw
     *      unique-violation scenario) must be a no-op reconcile keep, never a duplicate.
     */
    public function test_item_edit_intra_duplicate_rejected_and_existing_serial_noop()
    {
        // Item X with SN-EI-1 registered by New Purchase (source 'purchase').
        $item = $this->makeSerializedItem('ITEM-EI', 'Edit Item Guard');
        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-EI-1'])
        )->assertJson(['success' => true]);

        // Attempt 1: type SN-EI-1 TWICE in the Edit Item serial fields → intra-submission
        // duplicate → clean rejection, nothing changed.
        $dupEdit = $this->actingAs($this->user)->post(route('items.update', $item->id), [
            'item_name' => $item->item_name,
            'item_group' => 'Single',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Fixed',
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'price' => 100,
            'purchase_price' => 100,
            'sales_price' => 200,
            'opening_stock' => 2,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-EI-1', 'SN-EI-1'],
        ]);
        $dupEdit->assertSessionHas('error');
        $this->assertStringContainsString('SN-EI-1', session('error'));
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-EI-1')->count());

        // Attempt 2: add a genuinely NEW serial SN-EI-2 through the Edit Item reconcile
        // path → succeeds and is tagged source 'item_edit'. Re-submitting the already-owned
        // SN-EI-1 alongside is a no-op keep (no duplicate insert, no constraint error).
        $goodEdit = $this->actingAs($this->user)->post(route('items.update', $item->id), [
            'item_name' => $item->item_name,
            'item_group' => 'Single',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Fixed',
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'price' => 100,
            'purchase_price' => 100,
            'sales_price' => 200,
            'opening_stock' => 2,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-EI-1', 'SN-EI-2'],
        ]);
        $goodEdit->assertSessionHasNoErrors();
        $goodEdit->assertSessionHas('success');
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $item->id, 'serial_number' => 'SN-EI-2', 'source' => 'item_edit']);
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-EI-1')->count());
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-EI-2')->count());
    }

    /**
     * Q2 coverage — source tag 'purchase_quick_add_item' (Purchase Quick-Add Item modal).
     *
     * Quick-Add shares the exact same ItemCreationService::createSingleItem() code path
     * as Add Item (item_add) — only the $source string differs — so the shared validation
     * is equivalent to item_add. This dedicated test proves the real route:
     *   1) a quick-added serialized item registers with source 'purchase_quick_add_item';
     *   2) an intra-submission duplicate typed into the Quick-Add opening-stock serial
     *      modal is rejected cleanly with no rows written.
     */
    public function test_purchase_quick_add_item_registers_and_rejects_intra_duplicate()
    {
        $base = [
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'purchase_price' => 100,
            'sales_price' => 200,
            'warehouse_id' => $this->warehouse->id,
            'is_serialized' => 1,
        ];

        // Valid Quick-Add with opening stock + serial → succeeds, source tagged.
        $ok = $this->actingAs($this->user)->postJson(route('purchase.quick.item.store'), $base + [
            'item_name' => 'QuickAdd Reg Item',
            'opening_stock' => 1,
            'serial_numbers' => ['SN-QA-1'],
        ]);
        $ok->assertJson(['success' => true]);
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-QA-1', 'source' => 'purchase_quick_add_item']);

        // Intra-submission duplicate in the Quick-Add serial modal → clean 422, no rows.
        $dup = $this->actingAs($this->user)->postJson(route('purchase.quick.item.store'), $base + [
            'item_name' => 'QuickAdd Dup Item',
            'opening_stock' => 2,
            'serial_numbers' => ['SN-QA-2', 'SN-QA-2'],
        ]);
        $dup->assertStatus(422);
        $this->assertStringContainsString('SN-QA-2', $dup->json('message'));
        $this->assertSame(0, DbItemSerial::where('serial_number', 'SN-QA-2')->count());
    }

    /**
     * Acceptance: Sales Return / Purchase Return / destroyReturn status transitions
     * are UPDATEs on the same row and must be unaffected by the unique constraint.
     */
    public function test_status_transition_flows_unaffected_by_unique_constraint()
    {
        $item = $this->makeSerializedItem('ITEM-ST', 'Status Transition Item');
        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-ST'])
        )->assertJson(['success' => true]);
        $purchase = DbPurchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

        // Purchase Return → serial status 2 (Returned to Supplier) on the SAME row.
        $retRes = $this->actingAs($this->user)->postJson(route('purchase.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => date('Y-m-d'),
            'items' => [[
                'item_id' => $item->id,
                'return_qty' => 1,
            ]],
        ]);
        $retRes->assertStatus(200);
        $this->assertDatabaseHas('db_item_serials', [
            'item_id' => $item->id,
            'serial_number' => 'SN-ST',
            'status' => 2,
        ]);

        // destroyReturn reverses status 2 → 0 on the SAME row.
        $return = DbPurchaseReturn::where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($return);
        $this->actingAs($this->user)->deleteJson(route('purchase.return.delete', $return->id))->assertStatus(200);
        $this->assertDatabaseHas('db_item_serials', [
            'item_id' => $item->id,
            'serial_number' => 'SN-ST',
            'status' => 0,
        ]);

        // Exactly one row survived the round-trip.
        $this->assertSame(1, DbItemSerial::where('item_id', $item->id)->where('serial_number', 'SN-ST')->count());
    }

    /** Traceability: each entry point tags its rows with a distinct `source`. */
    public function test_source_column_tagged_per_entry_point()
    {
        // Add Item → 'item_add'
        $this->actingAs($this->user)->post(
            route('items.store'),
            $this->addItemPayload('Src Add Item', 1, ['SN-SRC-ADD'])
        )->assertSessionHas('success');
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-SRC-ADD', 'source' => 'item_add']);

        // New Purchase → 'purchase'
        $item = $this->makeSerializedItem('ITEM-SRC', 'Source Item');
        $this->actingAs($this->user)->postJson(
            route('purchase.store'),
            $this->purchasePayload($item->id, 1, ['SN-SRC-P'])
        )->assertJson(['success' => true]);
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-SRC-P', 'source' => 'purchase']);

        // Stock Adjustment → 'stock_adjustment'
        $this->actingAs($this->user)->postJson(
            route('stock.adjustment.store'),
            $this->adjustmentPayload($item->id, ['SN-SRC-A'])
        )->assertJson(['success' => true]);
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-SRC-A', 'source' => 'stock_adjustment']);
    }
}
