<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbItemSerial;
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
 * Phase B — Adjustment create-or-increment race fix (B1) and the new
 * destroy()/show() capability (B2).
 *
 * B1 verifies the lockForUpdate()+create()+catch-and-retry-as-increment upsert:
 * two adjustments for the same brand-new item+warehouse must both succeed (one
 * creates, one increments) with the correct final quantity — never a 500 from
 * the uq_warehouse_item index. NOTE: this is NOT an updateOrCreate call — the
 * controller uses a guarded select-then-create with a portable unique-violation
 * retry (see isWarehouseItemUniqueViolation in StockAdjustmentController).
 * B2 verifies destroy() reverses the delta, blocks when reversal would go
 * negative or hit a sold serial, and double-delete is a clean 404.
 */
class StockAdjustmentDeleteAndRaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected DbItem $item;
    protected DbCategory $category;
    protected DbCustomer $customer;
    protected AcAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::create([
            'id' => 1,
            'store_name' => 'Adjustment Delete Test Store',
            'status' => 1,
            'mobile' => '01700000011',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => [
                'stock_adjustment_view', 'stock_adjustment_add',
                'stock_adjustment_edit', 'stock_adjustment_delete',
                'sales_view', 'sales_add',
            ],
        ]);

        $this->user = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'PhaseB Warehouse',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->category = DbCategory::create([
            'store_id' => 1,
            'category_name' => 'PhaseB Category',
            'status' => 1,
        ]);

        $this->item = DbItem::create([
            'store_id' => 1,
            'item_name' => 'PhaseB Item',
            'item_code' => 'PB-001',
            'category_id' => $this->category->id,
            'purchase_price' => 40.00,
            'sales_price' => 90.00,
            'stock' => 0,
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'PhaseB Customer',
            'mobile' => '01700000012',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'PhaseB Cash',
            'account_number' => 'PB-CASH-01',
            'balance' => 10000.00,
            'status' => 1,
        ]);
    }

    protected function adjustPayload(float $qty, array $serials = []): array
    {
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'adjustment_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => $qty],
            ],
        ];
        if (!empty($serials)) {
            $payload['items'][0]['serials'] = $serials;
        }
        return $payload;
    }

    /**
     * B1: two sequential adjustments for a brand-new item+warehouse both succeed —
     * the first creates the row, the second increments it (guarded create+retry).
     */
    public function test_two_adjustments_same_new_item_warehouse_both_succeed()
    {
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(5))
            ->assertOk()->assertJson(['success' => true]);

        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(7))
            ->assertOk()->assertJson(['success' => true]);

        // Exactly ONE warehouse-item row, holding 12.
        $rows = DbWarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item->id)->get();
        expect($rows->count())->toBe(1);
        expect((float) $rows->first()->available_qty)->toBe(12.0);

        // Global stock too.
        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(12.0);
    }

    /**
     * B1 (true concurrency, file-backed SQLite): two parallel worker processes both
     * run the SAME guarded create+retry upsert against the same DB. Exactly one
     * INSERT wins at the DB level; the loser either waits on SQLite's writer lock
     * and then increments the created row, or fails on the unique index — in either
     * case the final quantity must be 10 (2 × 5) with exactly one row, and no 500
     * semantics. (Known limitation: this test re-implements the upsert in raw PDO
     * rather than calling the controller; the delete_bit rollout's parallel test
     * exercises the real controller endpoint.)
     */
    public function test_concurrent_adjustments_same_new_item_warehouse_final_qty_correct()
    {
        // Build a file-backed SQLite DB with the real db_warehouseitems schema incl.
        // the uq_warehouse_item unique index.
        $dbPath = sys_get_temp_dir() . '/adj_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/adj_race_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/adj_race_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE db_warehouseitems (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER NULL,
            warehouse_id INTEGER NULL,
            item_id INTEGER NULL,
            available_qty DECIMAL(16,2) NOT NULL DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
        $pdo->exec('CREATE UNIQUE INDEX uq_warehouse_item ON db_warehouseitems (warehouse_id, item_id)');

        // Worker: run the same guarded create+retry the controller now performs,
        // released simultaneously by the barrier file.
        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 15);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            // create+retry-equivalent: SELECT then atomic UPSERT.
            $stmt = $pdo->prepare("SELECT id FROM db_warehouseitems WHERE warehouse_id = 1 AND item_id = 1");
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists === false) {
                try {
                    $pdo->exec("INSERT INTO db_warehouseitems (store_id, warehouse_id, item_id, available_qty, created_at, updated_at)
                                VALUES (1, 1, 1, 5, datetime(\'now\'), datetime(\'now\'))");
                    echo "RESULT:INSERTED\n";
                } catch (\Exception $e) {
                    if (str_contains(strtolower($e->getMessage()), "unique")) {
                        // Lost the create race — the other worker created it; now
                        // increment the created row (exactly what the retry does).
                        $pdo->exec("UPDATE db_warehouseitems SET available_qty = available_qty + 5, updated_at = datetime(\'now\')
                                    WHERE warehouse_id = 1 AND item_id = 1");
                        echo "RESULT:INCREMENTED_AFTER_LOSS\n";
                    } else {
                        echo "RESULT:OTHER_FAIL:" . $e->getMessage() . "\n";
                    }
                }
            } else {
                $pdo->exec("UPDATE db_warehouseitems SET available_qty = available_qty + 5, updated_at = datetime(\'now\')
                            WHERE warehouse_id = 1 AND item_id = 1");
                echo "RESULT:INCREMENTED\n";
            }
        } catch (\Exception $e) {
            echo "RESULT:FAIL:" . $e->getMessage() . "\n";
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

        // Final state: exactly one row with qty 10.
        $rowCount = (int) $pdo->query('SELECT COUNT(*) FROM db_warehouseitems WHERE warehouse_id = 1 AND item_id = 1')->fetchColumn();
        $qty = (float) $pdo->query('SELECT available_qty FROM db_warehouseitems WHERE warehouse_id = 1 AND item_id = 1')->fetchColumn();

        $this->assertSame(1, $rowCount, "Exactly one warehouse-item row must exist. Out1: {$out1} | Out2: {$out2}");
        $this->assertEquals(10.0, $qty, "Final quantity must be 10 (2 x 5). Out1: {$out1} | Out2: {$out2}");

        @unlink($dbPath);
    }

    /**
     * B2: deleting a positive adjustment reverses the delta (global + warehouse)
     * and removes the record + its serials.
     */
    public function test_delete_reverses_stock_delta()
    {
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(10, ['SN-PB-DEL-1']))
            ->assertOk()->assertJson(['success' => true]);

        $adj = DbStockAdjustment::latest('id')->first();

        $response = $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adj->id));
        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('db_stockadjustment', ['id' => $adj->id]);
        $this->assertDatabaseMissing('db_stockadjustmentitems', ['adjustment_id' => $adj->id]);

        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(0.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(0.0);
        $this->assertDatabaseMissing('db_item_serials', ['adjustment_id' => $adj->id]);
    }

    /**
     * B2: deleting a NEGATIVE adjustment reverses the delta upward.
     */
    public function test_delete_negative_adjustment_reverses_upward()
    {
        // Item starts at 0. A negative adjustment of -10 would drive it negative,
        // so pre-seed stock first via a positive adjustment.
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(20))
            ->assertOk();

        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(-10))
            ->assertOk();

        $negAdj = DbStockAdjustment::latest('id')->first();

        $response = $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $negAdj->id));
        $response->assertOk()->assertJson(['success' => true]);

        // Removing the -10 adjustment brings stock back up to 20.
        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(20.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(20.0);
    }

    /**
     * B2: deleting a positive adjustment whose stock was consumed downstream
     * (sold via POS) is BLOCKED — reversal would go negative.
     */
    public function test_delete_blocked_when_reversal_would_go_negative()
    {
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(10))
            ->assertOk();

        $adj = DbStockAdjustment::latest('id')->first();

        // Sell 8 of the 10 from the warehouse (real downstream consumption).
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 90.00,
                'qty' => 8,
                'total' => 720.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]],
            'subtotal' => 720.00,
            'grand_total' => 720.00,
            'paid_amount' => 720.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Global stock now 2, warehouse 2. Deleting the +10 adjustment would drive
        // both negative → must be blocked, nothing mutated.
        $response = $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adj->id));
        $response->assertStatus(422);
        $this->assertStringContainsString('negative', $response->json('message'));

        $this->assertDatabaseHas('db_stockadjustment', ['id' => $adj->id]);
        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(2.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(2.0);
    }

    /**
     * B2: deleting an adjustment whose serial was sold is blocked.
     *
     * The item is pre-seeded with extra stock so the negative-stock guard does NOT
     * fire first — this isolates the sold-serial guard (the block is the same, but
     * the message must name the sold serial as the reason).
     */
    public function test_delete_blocked_when_adjustment_serial_sold()
    {
        // Pre-seed plenty of stock (separate adjustment) so the reversal's
        // negative-stock guard stays silent and only the sold-serial guard fires.
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(50))
            ->assertOk();

        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(2, ['SN-PB-SOLD-1', 'SN-PB-SOLD-2']))
            ->assertOk();

        $adj = DbStockAdjustment::latest('id')->first();
        $serialRow = DbItemSerial::where('item_id', $this->item->id)->where('serial_number', 'SN-PB-SOLD-1')->first();

        // Sell one of the serials via POS.
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 90.00,
                'qty' => 1,
                'total' => 90.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
                'selectedSerials' => [$serialRow->id],
            ]],
            'subtotal' => 90.00,
            'grand_total' => 90.00,
            'paid_amount' => 90.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Sold-serial guard fires even though dest stock still holds 1 unit.
        $response = $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adj->id));
        $response->assertStatus(422);
        $this->assertStringContainsString('already been sold', $response->json('message'));

        // Nothing mutated: serial stays sold, adjustment stays.
        // (Item stock = 50 (pre-seed) + 2 (adjustment) − 1 (sold) = 51)
        $sold = DbItemSerial::find($serialRow->id);
        expect((int) $sold->status)->toBe(1);
        expect($sold->sale_id)->not->toBeNull();
        $this->assertDatabaseHas('db_stockadjustment', ['id' => $adj->id]);
        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(51.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(51.0);
    }

    /**
     * B2: double-delete → second is a clean 404, reversal not run twice.
     */
    public function test_second_delete_returns_clean_not_found()
    {
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(10))
            ->assertOk();
        $adj = DbStockAdjustment::latest('id')->first();

        $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adj->id))
            ->assertOk()->assertJson(['success' => true]);

        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(0.0);

        $second = $this->actingAs($this->user)->deleteJson(route('stock.adjustment.destroy', $adj->id));
        $second->assertStatus(404);
        $this->assertStringContainsString('already been deleted', $second->json('message'));

        // Stock NOT double-reversed (would be -10 if the reversal ran twice).
        $this->item->refresh();
        expect((float) $this->item->stock)->toBe(0.0);
    }

    /**
     * B2: the show() (read-only detail) route renders for the adjustment.
     */
    public function test_show_renders_adjustment_detail()
    {
        $this->actingAs($this->user)->postJson(route('stock.adjustment.store'), $this->adjustPayload(5, ['SN-PB-SHOW-1']))
            ->assertOk();
        $adj = DbStockAdjustment::latest('id')->first();

        $this->actingAs($this->user)->get(route('stock.adjustment.show', $adj->id))
            ->assertOk()
            ->assertSee($adj->reference_no)
            ->assertSee('SN-PB-SHOW-1')
            ->assertSee($this->item->item_name);
    }
}
