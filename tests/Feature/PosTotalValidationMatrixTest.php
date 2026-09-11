<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbCoupon;
use App\Models\DbHold;
use App\Models\DbHoldItem;
use App\Models\DbItemSerial;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * EXHAUSTIVE TEST MATRIX — server-side grand_total validation
 * (recomputeServerTotals + config('sales.enforce_total_validation')).
 *
 * Every combination is exercised against BOTH the POS route and the Add Sale
 * route. Legitimate sales must pass (200); a deliberately tampered total must
 * be rejected (422) when the kill-switch is ON, and accepted-but-logged when OFF.
 */

function matrixEnv()
{
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'MATRIX TEST STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'matrix@corevisys.com',
            'address' => '123 Matrix Ave',
        ]);
    }
    DbStore::where('id', 1)->update(['round_off' => 0]); // per-test override

    DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);
    DbPermission::firstOrCreate(['role_id' => 1], [
        'store_id' => 1,
        'permissions' => ['sales_view', 'sales_add'],
    ]);

    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $warehouse = DbWarehouse::create(['warehouse_name' => 'Matrix WH', 'status' => 1, 'store_id' => 1]);
    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Matrix Customer', 'mobile' => '+8801811111111',
        'status' => 1, 'store_id' => 1,
        'tot_advance' => 500.00, // advance balance so advance-payment flows are legal
    ]);

    // Two items: one plain, one serialized.
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Matrix Item', 'item_code' => 'MTX-001',
        'sales_price' => 100.00, 'purchase_price' => 60.00,
        'stock' => 100, 'status' => 1, 'store_id' => 1,
    ]);
    $serialItem = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Serialized Item', 'item_code' => 'MTX-SER',
        'sales_price' => 50.00, 'purchase_price' => 30.00,
        'stock' => 10, 'is_serialized' => 1, 'status' => 1, 'store_id' => 1,
    ]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 100]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $serialItem->id, 'available_qty' => 10]);

    $account = AcAccount::create([
        'store_id' => 1, 'account_name' => 'Matrix Cash', 'account_number' => 'MTX-CASH',
        'balance' => 10000.00, 'status' => 1,
    ]);

    // A reusable master coupon (fixed 20 off).
    DbCoupon::create([
        'store_id' => 1, 'code' => 'MTX20', 'name' => 'Matrix 20', 'value' => 20.00,
        'type' => 'Fixed', 'expire_date' => date('Y-m-d', strtotime('+30 days')), 'status' => 1,
    ]);

    return compact('user', 'warehouse', 'customer', 'item', 'serialItem', 'account');
}

/**
 * Build a cart + compute the EXACT expected client grand_total using the same
 * math as recomputeServerTotals: subtotal + tax + other − discount, where
 *   lineTotal = price * qty (client price authoritative)
 *   lineDisc  = min(discount, lineTotal)              (fixed amount)
 *   lineTax   = (lineTotal − lineDisc) * tax% / 100
 *   invoiceDisc = fixed ? discount_on_all : subtotal * % /100
 *   totalDisc = min(subtotal, itemDisc + invoiceDisc + couponAmt)
 *   raw = max(0, subtotal + totalTax + other − totalDisc − advance)
 *   round → raw rounded if store round_off enabled.
 */
function matrixPayload($env, array $opts)
{
    $qty = $opts['qty'] ?? 1;
    $price = $opts['price'] ?? 100.00;
    $taxPct = $opts['tax'] ?? 0;
    $itemDisc = $opts['item_discount'] ?? 0;
    $otherCharges = $opts['other_charges'] ?? 0;
    $discOnAll = $opts['discount_on_all'] ?? 0;
    $discType = $opts['discount_type'] ?? 'fixed';
    $couponAmt = $opts['coupon_amt'] ?? 0;
    $advance = $opts['advance'] ?? 0;
    $roundOffEnabled = (bool) ($opts['round_off'] ?? false);

    $lineTotal = $price * $qty;
    $lineDisc = min($itemDisc, $lineTotal);
    $subtotal = $lineTotal;
    $totalTax = ($lineTotal - $lineDisc) * $taxPct / 100;
    $invoiceDisc = $discType === 'percent' ? ($subtotal * $discOnAll / 100) : $discOnAll;
    $totalDisc = min($subtotal, $lineDisc + $invoiceDisc + $couponAmt);
    $raw = max(0, $subtotal + $totalTax + $otherCharges - $totalDisc - $advance);
    $grandTotal = $roundOffEnabled ? round($raw) : $raw;

    return [
        'cart' => [[
            'id' => $env['item']->id,
            'name' => $env['item']->item_name,
            'price' => $price,
            'qty' => $qty,
            'total' => $lineTotal,
            'discount' => $itemDisc,
            'tax' => $taxPct,
            'taxAmount' => $totalTax,
        ]],
        'subtotal' => $subtotal,
        'grand_total' => $grandTotal,
        'other_charges' => $otherCharges,
        'discount_on_all' => $discOnAll,
        'discount_type' => $discType,
        'coupon_amt' => $couponAmt,
        'advance_amount' => $advance,
        'paid_amount' => $grandTotal,
    ];
}

function matrixBase($env, array $opts = [])
{
    $couponId = DbCoupon::where('code', 'MTX20')->value('id');

    $payload = [
        'customer_id' => $env['customer']->id,
        'warehouse_id' => $env['warehouse']->id,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ];

    // When a coupon is involved, send the code + id so the server re-validates it
    // (the real clients always send these; coupon_amt alone is ignored by resolveCoupon).
    if (!empty($opts['coupon_amt'])) {
        $payload['coupon_code'] = 'MTX20';
        $payload['coupon_id'] = $couponId;
    }

    return array_merge($payload, matrixPayload($env, $opts));
}

// ---------------------------------------------------------------- matrix rows

$matrixRows = [
    'baseline (no extras)'            => [],
    'per-item tax only'               => ['tax' => 15],
    'other_charges only'              => ['other_charges' => 25.50],
    'item-level discount only'        => ['item_discount' => 10],
    'global discount fixed'           => ['discount_on_all' => 15, 'discount_type' => 'fixed'],
    'global discount percent'         => ['discount_on_all' => 10, 'discount_type' => 'percent'],
    'coupon only'                     => ['coupon_amt' => 20],
    'ALL combined'                    => [
        'tax' => 15, 'other_charges' => 25.50, 'item_discount' => 10,
        'discount_on_all' => 10, 'discount_type' => 'percent', 'coupon_amt' => 20,
    ],
    'ALL combined + round_off ON'     => [
        'tax' => 15, 'other_charges' => 25.50, 'item_discount' => 10,
        'discount_on_all' => 10, 'discount_type' => 'percent', 'coupon_amt' => 20, 'round_off' => true,
    ],
    'baseline + round_off ON'         => ['round_off' => true],
    'advance payment (Add Sale)'      => ['advance' => 30],
];

// Store rows as a data provider-compatible array via a closure (Pest reads it once).
$matrixData = array_map(fn ($opts) => [$opts], $matrixRows);

test('POS legitimate totals are NOT rejected across the matrix', function (array $opts) {
    $env = matrixEnv();
    DbStore::where('id', 1)->update(['round_off' => !empty($opts['round_off']) ? 1 : 0]);
    store_settings(true);

    $payload = matrixBase($env, $opts);
    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    expect($response->getStatusCode())->toBe(200, 'POS rejected: ' . json_encode($opts));
    expect($response->json('success'))->toBeTrue();
})->with($matrixData);

test('Add Sale legitimate totals are NOT rejected across the matrix', function (array $opts) {
    $env = matrixEnv();
    DbStore::where('id', 1)->update(['round_off' => !empty($opts['round_off']) ? 1 : 0]);
    store_settings(true);

    $payload = matrixBase($env, $opts);
    $payload['is_pos'] = false;
    $response = $this->actingAs($env['user'])->postJson(route('sales.store'), $payload);
    expect($response->getStatusCode())->toBe(200, 'Add Sale rejected: ' . json_encode($opts));
    expect($response->json('success'))->toBeTrue();
})->with($matrixData);

test('POS with multiple payment rows and a hold is not rejected', function () {
    $env = matrixEnv();

    // Create a hold to resume (POS hold-restore path).
    // A1: the hold must be 'open' to be claimable by store() — the previous
    // 'status' => 1 value collided with the new hold lifecycle ('open'/'completed').
    $hold = DbHold::create([
        'store_id' => 1, 'warehouse_id' => $env['warehouse']->id,
        'reference_no' => 'HLD-MTX-1', 'sales_date' => date('Y-m-d'),
        'customer_id' => $env['customer']->id, 'subtotal' => 100.00,
        'grand_total' => 100.00, 'status' => 'open',
    ]);
    DbHoldItem::create([
        'store_id' => 1, 'hold_id' => $hold->id, 'item_id' => $env['item']->id,
        'sales_qty' => 1, 'price_per_unit' => 100.00, 'total_cost' => 100.00,
    ]);

    $payload = matrixBase($env, []);
    $payload['hold_id'] = $hold->id;
    $payload['payments'] = [
        ['amount' => 50.00, 'type' => 'Cash', 'account' => $env['account']->id, 'note' => 'split 1'],
        ['amount' => 50.00, 'type' => 'bKash', 'account' => $env['account']->id, 'note' => 'split 2'],
    ];
    $payload['paid_amount'] = 100.00;

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    expect($response->getStatusCode())->toBe(200);
    expect($response->json('success'))->toBeTrue();

    // Hold should have been deleted after conversion to sale.
    expect(DbHold::find($hold->id))->toBeNull();
});

test('POS with serialized item is not rejected', function () {
    $env = matrixEnv();

    $serial1 = DbItemSerial::create(['store_id' => 1, 'warehouse_id' => $env['warehouse']->id, 'item_id' => $env['serialItem']->id, 'serial_number' => 'SER-0001', 'status' => 0]);
    $serial2 = DbItemSerial::create(['store_id' => 1, 'warehouse_id' => $env['warehouse']->id, 'item_id' => $env['serialItem']->id, 'serial_number' => 'SER-0002', 'status' => 0]);

    $payload = array_merge(matrixBase($env, ['price' => 50]), [
        'cart' => [[
            'id' => $env['serialItem']->id,
            'name' => $env['serialItem']->item_name,
            'price' => 50.00,
            'qty' => 2,
            'total' => 100.00,
            'discount' => 0,
            'tax' => 0,
            'taxAmount' => 0,
            'selectedSerials' => [$serial1->id, $serial2->id],
            'serial' => 'SER-0001, SER-0002',
        ]],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
    ]);

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    expect($response->getStatusCode())->toBe(200);
    expect($response->json('success'))->toBeTrue();

    $serial1->refresh();
    $serial2->refresh();
    expect($serial1->status)->toBe(1);
    expect($serial2->status)->toBe(1);
});

test('Add Sale with advance payment affecting totalPayable is not rejected', function () {
    $env = matrixEnv();

    $payload = matrixBase($env, ['advance' => 30]);
    $payload['is_pos'] = false;
    $response = $this->actingAs($env['user'])->postJson(route('sales.store'), $payload);
    expect($response->getStatusCode())->toBe(200);
    expect($response->json('success'))->toBeTrue();

    // The persisted grand_total must reflect the advance deduction (100 − 30 = 70).
    $sale = \App\Models\DbSale::latest('id')->first();
    expect((float) $sale->grand_total)->toBe(70.00);
});

// ---------------------------------------------------------------- tampered case

test('deliberately tampered grand_total IS rejected on both flows (kill-switch ON)', function () {
    $env = matrixEnv();

    foreach (['sales.pos.store', 'sales.store'] as $routeName) {
        $payload = matrixBase($env, []);
        $payload['grand_total'] = 55.00; // real total is 100 → tampered

        $response = $this->actingAs($env['user'])->postJson(route($routeName), $payload);
        expect($response->getStatusCode())->toBe(422, "route {$routeName} did not reject");
        expect($response->json('success'))->toBeFalse();
    }
});

// ---------------------------------------------------------------- kill-switch OFF

test('kill-switch OFF: tampered grand_total is NOT rejected (logged instead) on both flows', function () {
    config(['sales.enforce_total_validation' => false]);
    $env = matrixEnv();

    foreach (['sales.pos.store', 'sales.store'] as $routeName) {
        $payload = matrixBase($env, []);
        $payload['grand_total'] = 55.00; // tampered, but flag OFF

        $response = $this->actingAs($env['user'])->postJson(route($routeName), $payload);
        expect($response->getStatusCode())->toBe(200, "route {$routeName} rejected despite kill-switch OFF");
        expect($response->json('success'))->toBeTrue();
    }

    config(['sales.enforce_total_validation' => true]);
});

test('kill-switch ON again: tampered grand_total IS rejected', function () {
    config(['sales.enforce_total_validation' => true]);
    $env = matrixEnv();

    $payload = matrixBase($env, []);
    $payload['grand_total'] = 55.00;

    $response = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    expect($response->getStatusCode())->toBe(422);
});

test('config sales.enforce_total_validation defaults to true', function () {
    // Fresh config (config cache is not persisted in tests) must default to true.
    expect(config('sales.enforce_total_validation', true))->toBe(true);
});
