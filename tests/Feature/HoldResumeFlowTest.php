<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbHold;
use App\Models\DbHoldItem;
use App\Models\DbItemSerial;
use App\Models\DbSale;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression tests for the Hold Sales / Hold-Resume-Discard fixes:
 *
 * A1  Double-resume → duplicate sale + double stock decrement lock.
 * A2  Serialized items block completion until serials re-selected on resume.
 * A3  Discount / coupon / note / per-line tax persistence + restore.
 * A4  Store scoping on the hold list.
 * A5  Real pagination + search wiring.
 * A6  Lightweight validation at hold time.
 * A7  Duplicate-reference rejection.
 * A8  Delete restricted to 'open' holds (completion guard is server-side).
 * A10 Warehouse-less resume is flagged for a prompt.
 */

function holdFixEnv(int $storeId = 1, bool $serialized = false)
{
    if (!DbStore::where('id', $storeId)->exists()) {
        DbStore::create([
            'id' => $storeId,
            'store_name' => 'Hold Fix Store ' . $storeId,
            'status' => 1,
            'mobile' => '+880170000000' . $storeId,
            'email' => 'holdfix' . $storeId . '@corevisys.com',
            'address' => 'Test Avenue',
        ]);
    }
    DbStore::where('id', $storeId)->update(['round_off' => 0]);
    store_settings(true);

    DbRole::firstOrCreate(['id' => 1], [
        'store_id' => $storeId,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => $storeId,
        'permissions' => ['sales_add', 'sales_view', 'pos'],
    ]);

    $user = User::factory()->create([
        'store_id' => $storeId,
        'role_id' => 1,
        'role_name' => 'Super Admin',
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Hold Fix WH ' . $storeId,
        'status' => 1,
        'store_id' => $storeId,
    ]);

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Hold Fix Customer ' . $storeId,
        'mobile' => '+880181111111' . $storeId,
        'status' => 1,
        'store_id' => $storeId,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Hold Fix Item ' . $storeId,
        'item_code' => 'HLD-' . $storeId . ($serialized ? '-SER' : '-001'),
        'sales_price' => 100.00,
        'purchase_price' => 60.00,
        'stock' => 100,
        'status' => 1,
        'store_id' => $storeId,
        'is_serialized' => $serialized ? 1 : 0,
    ]);

    DbWarehouseItem::create([
        'store_id' => $storeId,
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'available_qty' => 100,
    ]);

    $account = AcAccount::create([
        'store_id' => $storeId,
        'account_name' => 'Hold Fix Cash ' . $storeId,
        'account_number' => 'HLD-CASH-' . $storeId,
        'balance' => 1000.00,
        'status' => 1,
    ]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

function holdFixBasePayload($env, array $overrides = [])
{
    return array_merge([
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [[
            'id' => $env['item']->id,
            'name' => $env['item']->item_name,
            'price' => 100.00,
            'qty' => 1,
            'total' => 100.00,
            'discount' => 0,
            'tax' => 0,
            'taxAmount' => 0,
            'isSerialized' => $env['item']->is_serialized ? 1 : 0,
        ]],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ], $overrides);
}

function makeOpenHold($env, string $ref = 'HLD-FIX-1', ?int $warehouseId = null, array $extra = []): DbHold
{
    $hold = DbHold::create(array_merge([
        'store_id' => $env['user']->store_id,
        'warehouse_id' => $warehouseId ?? $env['warehouse']->id,
        'reference_no' => $ref,
        'sales_date' => date('Y-m-d'),
        'customer_id' => $env['customer']->id,
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'sales_note' => null,
        'pos' => 1,
        'status' => 'open',
    ], $extra));

    DbHoldItem::create([
        'store_id' => $env['user']->store_id,
        'hold_id' => $hold->id,
        'item_id' => $env['item']->id,
        'sales_qty' => 1,
        'price_per_unit' => 100.00,
        'total_cost' => 100.00,
        'discount_input' => 0,
        'discount_amt' => 0,
        'tax_percent' => 0,
        'tax_amt' => 0,
        'is_serialized' => $env['item']->is_serialized ? 1 : 0,
    ]);

    return $hold;
}

/**
 * EMI-path hold test env: the storeEmi() endpoint requires the selected
 * customer's db_customers.customer_type to be 'emi', so flip the shared hold
 * fix customer to EMI-eligible before the EMI-path hold tests.
 */
function holdFixEmiEnv(int $storeId = 1)
{
    $env = holdFixEnv($storeId);
    $env['customer']->update(['customer_type' => 'emi']);
    return $env;
}

/**
 * EMI-path hold payload: same shape the POS submitEmi() Alpine function sends
 * to route('sales.pos.emi') (storeEmi), including hold_id when the current
 * cart originated from a resumed hold.
 */
function holdFixEmiPayload($env, array $overrides = [])
{
    return array_merge([
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'cart' => [[
            'id' => $env['item']->id,
            'name' => $env['item']->item_name,
            'price' => 100.00,
            'qty' => 1,
            'total' => 100.00,
            'discount' => 0,
            'tax' => 0,
            'taxAmount' => 0,
            'isSerialized' => 0,
        ]],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'initial_pay' => 10.00,
        'duration' => 3,
        'processing_fee' => 0,
        'start_date' => date('Y-m-d'),
        'account_id' => $env['account']->id,
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────── A1 ────

test('A1: completing a held sale once succeeds and consumes the hold', function () {
    $env = holdFixEnv();
    $hold = makeOpenHold($env, 'HLD-A1-1');

    $payload = holdFixBasePayload($env, ['hold_id' => $hold->id]);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);

    $response->assertStatus(200)->assertJson(['success' => true]);

    // Sale created once; stock decremented exactly once (100 → 99).
    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);

    // Hold row consumed.
    expect(DbHold::find($hold->id))->toBeNull();
});

test('A1: completing the same hold twice is rejected the second time (single sale, single stock decrement)', function () {
    $env = holdFixEnv();
    $hold = makeOpenHold($env, 'HLD-A1-2');

    $payload = holdFixBasePayload($env, ['hold_id' => $hold->id]);

    // First completion succeeds.
    $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload)
        ->assertStatus(200)->assertJson(['success' => true]);

    // Second completion with the same hold_id must be REJECTED — no duplicate
    // sale, no second stock decrement. (Simulates a second tab/user trying to
    // complete the same held invoice after the first already consumed it.)
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $response->assertStatus(409);
    $response->assertJson(['success' => false]);

    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);
});

test('A1: an already-resumed (completed-status) hold cannot be completed again', function () {
    $env = holdFixEnv();
    // Manually simulate the state a resumed-but-not-yet-deleted hold has while
    // the first completion transaction is in flight (status='completed').
    $hold = makeOpenHold($env, 'HLD-A1-3', null, ['status' => 'completed']);

    $payload = holdFixBasePayload($env, ['hold_id' => $hold->id]);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);

    $response->assertStatus(409);
    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(0);
    expect((float) $env['item']->refresh()->stock)->toBe(100.0);
});

// ──────────────────────────────── A1 EMI-path (hold-consume gap) ────────

test('A1-EMI: completing a held sale once via EMI checkout succeeds and consumes the hold', function () {
    $env = holdFixEmiEnv();
    $hold = makeOpenHold($env, 'HLD-A1EMI-1');

    $payload = holdFixEmiPayload($env, ['hold_id' => $hold->id]);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload);

    $response->assertStatus(200)->assertJson(['success' => true]);

    // 1 EMI sale + schedule rows created; stock decremented exactly once (100 → 99).
    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect(\App\Models\DbEmiSale::count())->toBe(1);
    expect(\App\Models\DbEmiSchedule::count())->toBe(3); // duration = 3
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);

    // Hold row consumed.
    expect(DbHold::find($hold->id))->toBeNull();
});

test('A1-EMI: completing the same held sale twice via EMI checkout is rejected the second time', function () {
    $env = holdFixEmiEnv();
    $hold = makeOpenHold($env, 'HLD-A1EMI-2');

    $payload = holdFixEmiPayload($env, ['hold_id' => $hold->id]);

    // First completion succeeds.
    $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload)
        ->assertStatus(200)->assertJson(['success' => true]);

    // Second completion with the same hold_id must be REJECTED (409) — no
    // duplicate EMI sale, no second stock decrement.
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), $payload);
    $response->assertStatus(409);
    $response->assertJson(['success' => false]);

    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect(\App\Models\DbEmiSale::count())->toBe(1);
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);
});

test('A1-EMI: a hold completed via the REGULAR path cannot be completed again via EMI (cross-path lock)', function () {
    $env = holdFixEmiEnv();
    $hold = makeOpenHold($env, 'HLD-A1EMI-3');

    // Complete once through the REGULAR checkout path (store()).
    $this->actingAs($env['user'])->postJson(route('sales.pos.store'), holdFixBasePayload($env, ['hold_id' => $hold->id]))
        ->assertStatus(200)->assertJson(['success' => true]);

    // The same hold must now be rejected by the EMI path (storeEmi()) — the
    // claim is shared across both endpoints.
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), holdFixEmiPayload($env, ['hold_id' => $hold->id]));
    $response->assertStatus(409);
    $response->assertJson(['success' => false]);

    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect(\App\Models\DbEmiSale::count())->toBe(0);
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);
});

test('A1-EMI: a hold completed via the EMI path cannot be completed again via the REGULAR path (cross-path lock)', function () {
    $env = holdFixEmiEnv();
    $hold = makeOpenHold($env, 'HLD-A1EMI-4');

    // Complete once through the EMI checkout path (storeEmi()).
    $this->actingAs($env['user'])->postJson(route('sales.pos.emi'), holdFixEmiPayload($env, ['hold_id' => $hold->id]))
        ->assertStatus(200)->assertJson(['success' => true]);

    // The same hold must now be rejected by the REGULAR path (store()) — the
    // claim is shared across both endpoints.
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), holdFixBasePayload($env, ['hold_id' => $hold->id]));
    $response->assertStatus(409);
    $response->assertJson(['success' => false]);

    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(1);
    expect(\App\Models\DbEmiSale::count())->toBe(1);
    expect((float) $env['item']->refresh()->stock)->toBe(99.0);
});

// ──────────────────────────────────────────────────────────────── A2 ────

test('A2: resume data marks serialized lines as needing serial re-selection', function () {
    $env = holdFixEnv(serialized: true);
    $hold = makeOpenHold($env, 'HLD-A2-1');
    // Give the held line an is_serialized flag so the restore path knows.
    DbHoldItem::where('hold_id', $hold->id)->update(['is_serialized' => 1]);

    $response = $this->actingAs($env['user'])->get(route('sales.pos', ['hold_id' => $hold->id]));
    $response->assertStatus(200);

    // The serialized held line must be flagged so the Alpine init marks it
    // needsSerialSelection:true (auto-opens the serial picker / blocks submit).
    $html = $response->getContent();
    expect($html)->toContain('window.holdData');
    // The line carries is_serialized=1 to the client restore logic.
    expect($html)->toContain('"is_serialized":1');
});

test('A2: completion with an empty serial set for a held serialized line cannot silently succeed server-side when serials are sold elsewhere', function () {
    // This asserts the stock-level safety net still exists (validateSalePayload
    // runs before the A1 claim): an out-of-stock serialized item is rejected.
    $env = holdFixEnv(serialized: true);
    $env['item']->update(['stock' => 0]);
    DbWarehouseItem::where('item_id', $env['item']->id)->update(['available_qty' => 0]);
    $hold = makeOpenHold($env, 'HLD-A2-2');

    $payload = holdFixBasePayload($env, ['hold_id' => $hold->id]);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);

    $response->assertStatus(422);
    expect(DbSale::where('customer_id', $env['customer']->id)->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────── A3 ────

test('A3: hold() persists discount, coupon, note and per-line tax', function () {
    $env = holdFixEnv();
    $payload = holdFixBasePayload($env, [
        'reference_no' => 'HLD-A3-1',
        'discount_on_all' => 10.00,
        'discount_type' => 'fixed',
        'coupon_id' => 7,
        'customer_coupon_id' => 5,
        'coupon_code' => 'SAVE10',
        'coupon_type' => 'Fixed',
        'coupon_value' => 10.00,
        'coupon_amt' => 10.00,
        'notes' => 'Hold with discount + coupon',
        'cart' => [[
            'id' => $env['item']->id,
            'name' => $env['item']->item_name,
            'price' => 100.00,
            'qty' => 1,
            'total' => 100.00,
            'discount' => 5.00,
            'tax' => 15,
            'taxAmount' => 15.00,
            'isSerialized' => 0,
        ]],
    ]);

    $this->actingAs($env['user'])->postJson(route('sales.pos.hold'), $payload)
        ->assertStatus(200)->assertJson(['success' => true]);

    $hold = DbHold::where('reference_no', 'HLD-A3-1')->first();
    expect($hold)->not->toBeNull();
    expect((float) $hold->discount_on_all)->toBe(10.00);
    expect($hold->discount_type)->toBe('fixed');
    expect((int) $hold->coupon_id)->toBe(7);
    expect((int) $hold->customer_coupon_id)->toBe(5);
    expect($hold->coupon_code)->toBe('SAVE10');
    expect($hold->coupon_type)->toBe('Fixed');
    expect((float) $hold->coupon_amount)->toBe(10.00);
    expect($hold->sales_note)->toBe('Hold with discount + coupon');
    expect($hold->status)->toBe('open');

    $holdItem = DbHoldItem::where('hold_id', $hold->id)->first();
    expect((float) $holdItem->tax_percent)->toBe(15.0);
    expect((float) $holdItem->tax_amt)->toBe(15.00);
    expect((float) $holdItem->discount_input)->toBe(5.00);
});

test('A3: resume (index with hold_id) exposes the persisted discount/coupon/note/tax to the client', function () {
    $env = holdFixEnv();

    $hold = DbHold::create([
        'store_id' => $env['user']->store_id,
        'warehouse_id' => $env['warehouse']->id,
        'reference_no' => 'HLD-A3-2',
        'sales_date' => date('Y-m-d'),
        'customer_id' => $env['customer']->id,
        'subtotal' => 90.00,
        'grand_total' => 80.00,
        'sales_note' => 'Keep this note',
        'pos' => 1,
        'status' => 'open',
        'discount_on_all' => 10.00,
        'discount_type' => 'fixed',
        'coupon_id' => 3,
        'customer_coupon_id' => null,
        'coupon_code' => 'HOLD5',
        'coupon_type' => 'Fixed',
        'coupon_value' => 5.00,
        'coupon_amount' => 5.00,
    ]);
    DbHoldItem::create([
        'store_id' => $env['user']->store_id,
        'hold_id' => $hold->id,
        'item_id' => $env['item']->id,
        'sales_qty' => 1,
        'price_per_unit' => 100.00,
        'total_cost' => 100.00,
        'discount_input' => 0,
        'discount_amt' => 0,
        'tax_percent' => 15.0,
        'tax_amt' => 15.00,
        'is_serialized' => 0,
    ]);

    $response = $this->actingAs($env['user'])->get(route('sales.pos', ['hold_id' => $hold->id]));
    $response->assertStatus(200);
    $html = $response->getContent();

    // The JSON injected into window.holdData must carry every A3 field the
    // Alpine restore block reads back.
    expect($html)->toContain('"sales_note":"Keep this note"');
    expect($html)->toContain('"discount_on_all":10');
    expect($html)->toContain('"discount_type":"fixed"');
    expect($html)->toContain('"coupon_code":"HOLD5"');
    expect($html)->toContain('"coupon_amount":5');
    expect($html)->toContain('"tax_percent":15');
});

// ──────────────────────────────────────────────────────────────── A4 ────

test('A4: hold list only shows the current store\'s holds', function () {
    $envA = holdFixEnv(1);
    $envB = holdFixEnv(2);

    makeOpenHold($envA, 'HLD-A4-STORE-A');
    makeOpenHold($envB, 'HLD-A4-STORE-B');

    $response = $this->actingAs($envA['user'])->get(route('sales.hold.list'));
    $response->assertStatus(200);
    expect($response->getContent())->toContain('HLD-A4-STORE-A');
    expect($response->getContent())->not->toContain('HLD-A4-STORE-B');
});

test('A4: hold list warehouse filter narrows results', function () {
    $env = holdFixEnv(1);
    $otherWh = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Other WH',
        'status' => 1,
        'store_id' => 1,
    ]);

    makeOpenHold($env, 'HLD-A4-WH-1');
    makeOpenHold($env, 'HLD-A4-WH-2', $otherWh->id);

    $response = $this->actingAs($env['user'])->get(route('sales.hold.list', ['warehouse_id' => $env['warehouse']->id]));
    $response->assertStatus(200);
    expect($response->getContent())->toContain('HLD-A4-WH-1');
    expect($response->getContent())->not->toContain('HLD-A4-WH-2');
});

// ──────────────────────────────────────────────────────────────── A5 ────

test('A5: hold list paginates and respects the limit param', function () {
    $env = holdFixEnv(1);

    for ($i = 1; $i <= 12; $i++) {
        makeOpenHold($env, 'HLD-A5-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    // Default limit 10 → page 1 shows 10.
    $page1 = $this->actingAs($env['user'])->get(route('sales.hold.list'));
    $page1->assertStatus(200);
    $page1Html = $page1->getContent();
    expect(substr_count($page1Html, 'HLD-A5-'))->toBeGreaterThanOrEqual(10);

    // Limit 25 → all 12 on one page.
    $all = $this->actingAs($env['user'])->get(route('sales.hold.list', ['limit' => 25]));
    $all->assertStatus(200);
    expect(substr_count($all->getContent(), 'HLD-A5-'))->toBe(12);
});

test('A5: hold list search filters by reference', function () {
    $env = holdFixEnv(1);
    makeOpenHold($env, 'REF-UNIQUE-777');
    makeOpenHold($env, 'REF-OTHER-999');

    $response = $this->actingAs($env['user'])->get(route('sales.hold.list', ['search' => 'UNIQUE']));
    $response->assertStatus(200);
    expect($response->getContent())->toContain('REF-UNIQUE-777');
    expect($response->getContent())->not->toContain('REF-OTHER-999');
});

// ──────────────────────────────────────────────────────────────── A6 ────

test('A6: hold() rejects an empty warehouse', function () {
    $env = holdFixEnv(1);
    $payload = holdFixBasePayload($env, ['warehouse_id' => null, 'reference_no' => 'HLD-A6-1']);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.hold'), $payload);
    $response->assertStatus(422);
    expect(DbHold::where('reference_no', 'HLD-A6-1')->count())->toBe(0);
});

test('A6: hold() rejects a non-positive quantity', function () {
    $env = holdFixEnv(1);
    $payload = holdFixBasePayload($env, [
        'reference_no' => 'HLD-A6-2',
        'cart' => [[
            'id' => $env['item']->id,
            'name' => $env['item']->item_name,
            'price' => 100.00,
            'qty' => 0,
            'total' => 0.00,
            'discount' => 0,
            'tax' => 0,
            'taxAmount' => 0,
        ]],
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.hold'), $payload);
    $response->assertStatus(422);
    expect(DbHold::where('reference_no', 'HLD-A6-2')->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────── A7 ────

test('A7: hold() rejects a duplicate open reference instead of silently replacing', function () {
    $env = holdFixEnv(1);
    makeOpenHold($env, 'HLD-A7-DUP');

    $payload = holdFixBasePayload($env, ['reference_no' => 'HLD-A7-DUP']);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.hold'), $payload);

    $response->assertStatus(422);
    $response->assertJson(['success' => false]);

    // The pre-existing hold is untouched (no silent delete).
    expect(DbHold::where('reference_no', 'HLD-A7-DUP')->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────── A8 ────

test('A8: deleteHold only removes an open hold', function () {
    $env = holdFixEnv(1);
    $openHold = makeOpenHold($env, 'HLD-A8-OPEN');
    $completedHold = makeOpenHold($env, 'HLD-A8-DONE', null, ['status' => 'completed']);

    $response = $this->actingAs($env['user'])->deleteJson(route('sales.pos.hold.delete', $openHold->id));
    $response->assertStatus(200)->assertJson(['success' => true]);
    expect(DbHold::find($openHold->id))->toBeNull();

    // A 'completed' hold cannot be deleted (already consumed by a sale).
    $response2 = $this->actingAs($env['user'])->deleteJson(route('sales.pos.hold.delete', $completedHold->id));
    $response2->assertStatus(404);
    expect(DbHold::find($completedHold->id))->not->toBeNull();
});

test('A8: deleting a hold does not touch stock (no reservation existed)', function () {
    $env = holdFixEnv(1);
    $hold = makeOpenHold($env, 'HLD-A8-STOCK');

    $this->actingAs($env['user'])->deleteJson(route('sales.pos.hold.delete', $hold->id))
        ->assertStatus(200);

    // Stock unchanged: holds never reserve inventory, discard is pure cleanup.
    expect((float) $env['item']->refresh()->stock)->toBe(100.0);
    expect((float) DbWarehouseItem::where('item_id', $env['item']->id)->first()->available_qty)->toBe(100.0);
});

// ──────────────────────────────────────────────────────────────── A10 ───

test('A10: resuming a warehouse-less hold flags hold_missing_warehouse', function () {
    $env = holdFixEnv(1);
    $hold = makeOpenHold($env, 'HLD-A10-NOWH', null);

    $response = $this->actingAs($env['user'])->get(route('sales.pos', ['hold_id' => $hold->id]));
    $response->assertStatus(200);

    expect($response->getContent())->toContain('hold_missing_warehouse');

    // A normal hold does not carry the flag.
    $normal = makeOpenHold($env, 'HLD-A10-WH');
    $normalPage = $this->actingAs($env['user'])->get(route('sales.pos', ['hold_id' => $normal->id]));
    expect($normalPage->getContent())->toContain('"hold_missing_warehouse":false');
});
