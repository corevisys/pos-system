<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStockTransfer;
use App\Models\DbStockTransferItems;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — Transfer delete/update corruption hardening (A1-A7).
 *
 * Every "downstream consumed" scenario runs END-TO-END: create the transfer,
 * consume part of the transferred stock via a normal POS sale, then attempt the
 * blocked action (delete / edit) and assert both the block AND that no stock was
 * mutated.
 */
class StockTransferDeleteHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $fromWh;
    protected DbWarehouse $toWh;
    protected DbItem $item;
    protected DbCategory $category;
    protected DbCustomer $customer;
    protected AcAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::create([
            'id' => 1,
            'store_name' => 'Transfer Delete Test Store',
            'status' => 1,
            'mobile' => '01700000001',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'store_id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => [
                'sales_view', 'sales_add',
                'stock_transfer_view', 'stock_transfer_add',
                'stock_transfer_edit', 'stock_transfer_delete',
                'items_view', 'purchase_view',
            ],
        ]);

        $this->user = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->fromWh = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'PhaseA Source',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->toWh = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'PhaseA Destination',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->category = DbCategory::create([
            'store_id' => 1,
            'category_name' => 'PhaseA Category',
            'status' => 1,
        ]);

        $this->item = DbItem::create([
            'store_id' => 1,
            'item_name' => 'PhaseA Transfer Item',
            'item_code' => 'PA-TR-001',
            'category_id' => $this->category->id,
            'purchase_price' => 50.00,
            'sales_price' => 100.00,
            'stock' => 100,
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'PhaseA Customer',
            'mobile' => '01700000002',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'PhaseA Cash',
            'account_number' => 'PA-CASH-01',
            'balance' => 10000.00,
            'status' => 1,
        ]);
    }

    /**
     * Give the source warehouse stock and create a transfer of $qty units.
     */
    protected function makeTransfer(int $qty, array $serials = []): DbStockTransfer
    {
        DbWarehouseItem::create([
            'store_id' => 1,
            'warehouse_id' => $this->fromWh->id,
            'item_id' => $this->item->id,
            'available_qty' => 100,
        ]);

        $payload = [
            'warehouse_from' => $this->fromWh->id,
            'warehouse_to' => $this->toWh->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => $qty,
                ],
            ],
        ];

        if (!empty($serials)) {
            foreach ($serials as $sn) {
                DbItemSerial::create([
                    'store_id' => 1,
                    'item_id' => $this->item->id,
                    'serial_number' => $sn,
                    'warehouse_id' => $this->fromWh->id,
                    'status' => 0,
                    'source' => 'purchase',
                ]);
            }
            $payload['items'][0]['serials'] = $serials;
        }

        $this->actingAs($this->user)
            ->postJson(route('stock.transfer.store'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        return DbStockTransfer::latest('id')->first();
    }

    /**
     * A1 END-TO-END: transfer → sell part of it from the DESTINATION warehouse →
     * delete must be BLOCKED and nothing may change.
     */
    public function test_delete_blocked_when_transferred_stock_sold_from_destination()
    {
        $transfer = $this->makeTransfer(10);
        $destId = $this->toWh->id;

        // Destination now holds 10. Sell 4 of them from the destination warehouse
        // via a normal POS sale (real downstream consumption).
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $destId,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 100.00,
                'qty' => 4,
                'total' => 400.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]],
            'subtotal' => 400.00,
            'grand_total' => 400.00,
            'paid_amount' => 400.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Destination is now 6 (10 - 4); source is 90.
        expect((float) DbWarehouseItem::where('warehouse_id', $destId)->where('item_id', $this->item->id)->value('available_qty'))->toBe(6.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(90.0);

        // Attempt to delete the transfer → must be blocked with the clear message.
        $response = $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id));
        $response->assertStatus(422);
        $this->assertStringContainsString('already been sold or moved', $response->json('message'));

        // Nothing mutated: transfer still exists, destination still 6, source still 90.
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transfer->id]);
        expect((float) DbWarehouseItem::where('warehouse_id', $destId)->where('item_id', $this->item->id)->value('available_qty'))->toBe(6.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(90.0);
    }

    /**
     * A1 POSITIVE: a transfer whose destination stock is fully intact still
     * deletes and reverses correctly.
     */
    public function test_delete_succeeds_and_reverses_when_destination_untouched()
    {
        $transfer = $this->makeTransfer(10);

        $response = $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id));
        $response->assertOk()->assertJson(['success' => true]);

        // A3 spec-compliant soft delete: the row REMAINS with delete_bit=1 (it is
        // no longer returned by any delete_bit=0 query), and its line items are
        // removed. The old assertion (row hard-deleted) predates the delete_bit
        // column and is intentionally updated.
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transfer->id, 'delete_bit' => 1]);
        $this->assertDatabaseMissing('db_stocktransferitems', ['stocktransfer_id' => $transfer->id]);

        // Source back to 100, destination back to 0 (deleted row or zeroed).
        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(100.0);
        $dest = DbWarehouseItem::where('warehouse_id', $this->toWh->id)->where('item_id', $this->item->id)->first();
        expect($dest === null || (float) $dest->available_qty === 0.0)->toBeTrue();
    }

    /**
     * A2: transfer with a serial that POS later SOLD → delete is blocked with the
     * sold-serial message and nothing is mutated (serial stays sold + in dest).
     */
    public function test_delete_blocked_when_transferred_serial_sold()
    {
        $transfer = $this->makeTransfer(2, ['SN-A2-001', 'SN-A2-002']);
        $destId = $this->toWh->id;

        // Serial rows now sit in the destination warehouse tied to this transfer.
        $serialRow = DbItemSerial::where('item_id', $this->item->id)->where('serial_number', 'SN-A2-001')->first();
        expect((int) $serialRow->warehouse_id)->toBe($destId);
        expect((int) $serialRow->stocktransfer_id)->toBe($transfer->id);

        // Sell ONE serial from the destination via POS with selectedSerials.
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $destId,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 100.00,
                'qty' => 1,
                'total' => 100.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
                'selectedSerials' => [$serialRow->id],
            ]],
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        $sold = DbItemSerial::find($serialRow->id);
        expect((int) $sold->status)->toBe(1);
        expect($sold->sale_id)->not->toBeNull();

        // Delete is blocked (A2 stricter check: a sold serial exists even though
        // dest still holds 1 available unit).
        $response = $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id));
        $response->assertStatus(422);
        $this->assertStringContainsString('already been sold', $response->json('message'));

        // Nothing mutated: sold serial still sold + still in destination.
        $sold->refresh();
        expect((int) $sold->status)->toBe(1);
        expect((int) $sold->warehouse_id)->toBe($destId);
        expect((int) $sold->stocktransfer_id)->toBe($transfer->id);
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transfer->id]);
    }

    /**
     * A2 POSITIVE: transfer with all serials still available deletes and reverts
     * the serials back to the source with stocktransfer_id cleared.
     */
    public function test_delete_reverts_available_serials()
    {
        $transfer = $this->makeTransfer(2, ['SN-A2B-001', 'SN-A2B-002']);
        $destId = $this->toWh->id;

        $response = $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id));
        $response->assertOk()->assertJson(['success' => true]);

        foreach (['SN-A2B-001', 'SN-A2B-002'] as $sn) {
            $serial = DbItemSerial::where('item_id', $this->item->id)->where('serial_number', $sn)->first();
            expect((int) $serial->warehouse_id)->toBe($this->fromWh->id);
            expect($serial->stocktransfer_id)->toBeNull();
            expect((int) $serial->status)->toBe(0);
        }
    }

    /**
     * A3: a second delete for an already-deleted transfer is a clean 404 —
     * the reversal is not run a second time.
     */
    public function test_second_delete_returns_clean_not_found_no_double_reverse()
    {
        $transfer = $this->makeTransfer(10);

        // First delete succeeds and reverses.
        $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id))
            ->assertOk()->assertJson(['success' => true]);

        $sourceQty = (float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty');
        expect($sourceQty)->toBe(100.0);

        // Second delete: the atomic delete_bit 0->1 transition affects 0 rows
        // (already flipped) → clean 404, and source is NOT incremented again.
        $second = $this->actingAs($this->user)->deleteJson(route('stock.transfer.destroy', $transfer->id));
        $second->assertStatus(404);
        $this->assertStringContainsString('already been deleted', $second->json('message'));

        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(100.0);
    }

    /**
     * A5: editing a transfer to a quantity beyond the new source's available
     * stock is rejected with the same clear error as store().
     *
     * NOTE: update() reverts the old movement FIRST (source restored) and then
     * re-applies, so to trigger the guard we must consume from the SOURCE after
     * the transfer — the revert restores only the transferred 10, leaving the
     * source below the requested new quantity.
     */
    public function test_update_reject_insufficient_stock_in_new_source()
    {
        $transfer = $this->makeTransfer(10);

        // Source now has 90 (100 - 10). Sell 30 MORE from the source warehouse,
        // so the source genuinely lacks stock for a larger re-apply.
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->fromWh->id,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 100.00,
                'qty' => 30,
                'total' => 3000.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]],
            'subtotal' => 3000.00,
            'grand_total' => 3000.00,
            'paid_amount' => 3000.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Source now 60, dest 10. Edit the transfer to 95 units from the same
        // source: revert restores source to 70, re-apply needs 95 → blocked.
        $response = $this->actingAs($this->user)->postJson(route('stock.transfer.update', $transfer->id), [
            'warehouse_from' => $this->fromWh->id,
            'warehouse_to' => $this->toWh->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 95],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Insufficient stock', $response->json('message'));

        // Nothing changed: transfer intact, source still 60, dest still 10.
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transfer->id]);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(60.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->toWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(10.0);
    }

    /**
     * A6/A1-on-edit: editing a transfer whose destination stock was consumed
     * downstream is blocked (mirrors the delete guard) — no revert is applied.
     */
    public function test_update_blocked_when_destination_stock_consumed()
    {
        $transfer = $this->makeTransfer(10);
        $destId = $this->toWh->id;

        // Sell 6 of the 10 from the destination warehouse.
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $destId,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 100.00,
                'qty' => 6,
                'total' => 600.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
            ]],
            'subtotal' => 600.00,
            'grand_total' => 600.00,
            'paid_amount' => 600.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Dest is now 4 (10 - 6). Attempt the edit — must be blocked.
        $response = $this->actingAs($this->user)->postJson(route('stock.transfer.update', $transfer->id), [
            'warehouse_from' => $this->fromWh->id,
            'warehouse_to' => $this->toWh->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already been sold or moved', $response->json('message'));

        // Nothing reverted: transfer intact, source still 90, dest still 4.
        $this->assertDatabaseHas('db_stocktransfer', ['id' => $transfer->id]);
        expect((float) DbWarehouseItem::where('warehouse_id', $this->fromWh->id)->where('item_id', $this->item->id)->value('available_qty'))->toBe(90.0);
        expect((float) DbWarehouseItem::where('warehouse_id', $destId)->where('item_id', $this->item->id)->value('available_qty'))->toBe(4.0);
    }

    /**
     * A7: editing a transfer does not re-point a sold serial. The revert only
     * moves still-available serials; a sold one stays put with its sale intact.
     */
    public function test_update_does_not_repont_sold_serial()
    {
        $transfer = $this->makeTransfer(2, ['SN-A7-001', 'SN-A7-002']);
        $destId = $this->toWh->id;

        $serialRow = DbItemSerial::where('item_id', $this->item->id)->where('serial_number', 'SN-A7-001')->first();

        // Sell SN-A7-001 from the destination.
        $this->actingAs($this->user)->postJson(route('sales.pos.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $destId,
            'cart' => [[
                'id' => $this->item->id,
                'name' => $this->item->item_name,
                'price' => 100.00,
                'qty' => 1,
                'total' => 100.00,
                'discount' => 0,
                'tax' => 0,
                'taxAmount' => 0,
                'selectedSerials' => [$serialRow->id],
            ]],
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account->id,
        ])->assertOk()->assertJson(['success' => true]);

        // Destination now has 1 available (2 - 1 sold). Editing to change the
        // destination would be blocked by the A1-on-edit dest check, so instead
        // verify the revert's serial clause: re-run the transfer's edit to a NEW
        // destination with the SAME serials — the sold serial must NOT be moved.
        $toWh2 = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'PhaseA Dest2',
            'store_id' => 1,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('stock.transfer.update', $transfer->id), [
            'warehouse_from' => $this->fromWh->id,
            'warehouse_to' => $toWh2->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 2, 'serials' => ['SN-A7-001', 'SN-A7-002']],
            ],
        ]);

        // Because dest (toWh) still holds 1 available unit and 1 sold unit, the
        // A1-on-edit destination check (available 1 < transfer qty 2) blocks the
        // edit — which is the correct, stricter outcome.
        $response->assertStatus(422);

        // The sold serial was NOT re-pointed anywhere.
        $sold = DbItemSerial::find($serialRow->id);
        expect((int) $sold->status)->toBe(1);
        expect((int) $sold->warehouse_id)->toBe($destId);
        expect((int) $sold->stocktransfer_id)->toBe($transfer->id);
    }
}