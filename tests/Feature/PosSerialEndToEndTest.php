<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Models\AcAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * END-TO-END — Scan serial → checkout → status flip → sibling untouched →
 * invoice shows it → sale details shows it → serial history shows Sold with
 * the exact sale reference. Drives ONLY real HTTP endpoints (the search
 * endpoint, the POS store endpoint, the invoice view, the sale-details view,
 * the serial-history page) — no internal methods, no shortcuts.
 */

function posE2eEnv()
{
    if (!DbStore::where('id', 1)->exists()) {
        DbStore::create([
            'id' => 1,
            'store_name' => 'E2E SCAN STORE',
            'status' => 1,
            'mobile' => '+8801700000000',
            'email' => 'e2e-store@corevisys.com',
            'address' => '123 E2E Avenue',
        ]);
    }

    DbRole::firstOrCreate(['id' => 1], ['role_name' => 'Super Admin', 'status' => 1, 'store_id' => 1]);
    DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['sales_view', 'sales_add']]);

    $user = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin', 'store_id' => 1]);
    $warehouse = DbWarehouse::create(['warehouse_name' => 'E2E WH', 'status' => 1, 'store_id' => 1]);
    $customer = DbCustomer::create(['customer_name' => 'E2E Customer', 'mobile' => '+8801811111111', 'status' => 1, 'store_id' => 1]);

    // IT-00048, serialized, with TWO serials: the scanned unit + a sibling.
    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'IT-00048 Product', 'item_code' => 'IT-00048',
        'custom_barcode' => 'BC-00048', 'sales_price' => 100.00, 'purchase_price' => 60.00,
        'stock' => 10, 'is_serialized' => 1, 'status' => 1, 'store_id' => 1,
    ]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'available_qty' => 10]);

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'E2E Cash', 'account_number' => 'E2E-CASH', 'balance' => 10000.00, 'status' => 1]);

    return compact('user', 'warehouse', 'customer', 'item', 'account');
}

test('END-TO-END: scan serial MS26A8dg → checkout → invoice → sale details → serial history (one continuous flow)', function () {
    $env = posE2eEnv();

    // ── Seed: the scanned unit + a sibling serial for the SAME item ────────
    $scanned = DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'MS26A8dg',
        'status' => 0,
        'source' => 'purchase',
    ]);
    $sibling = DbItemSerial::create([
        'store_id' => 1,
        'warehouse_id' => $env['warehouse']->id,
        'item_id' => $env['item']->id,
        'serial_number' => 'MS26B91x',
        'status' => 0,
        'source' => 'purchase',
    ]);

    // ── STEP 1: the cashier scans MS26A8dg ─────────────────────────────────
    $scan = $this->actingAs($env['user'])->getJson(route('sales.pos.search.items', [
        'q' => 'MS26A8dg',
        'warehouse_id' => $env['warehouse']->id,
    ]));
    $scan->assertStatus(200);
    $scanData = $scan->json();
    expect($scanData)->toHaveCount(1);
    expect($scanData[0]['item_code'])->toBe('IT-00048');
    expect((int) $scanData[0]['matched_serial_id'])->toBe($scanned->id);
    expect($scanData[0]['matched_serial'])->toBe('MS26A8dg');

    // ── STEP 2: checkout payload exactly as addToCartBySerial() builds it ──
    $payload = [
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
            // populated by addToCartBySerial() from matched_serial_id
            'selectedSerials' => [$scanned->id],
            'serial' => 'MS26A8dg',
        ]],
        'subtotal' => 100.00,
        'grand_total' => 100.00,
        'paid_amount' => 100.00,
        'payment_type' => 'Cash',
        'account_id' => $env['account']->id,
    ];

    $checkout = $this->actingAs($env['user'])->postJson(route('sales.pos.store'), $payload);
    $checkout->assertStatus(200)->assertJson(['success' => true]);
    $saleId = $checkout->json('sale_id');
    expect($saleId)->toBeInt();

    // ── STEP 3: the scanned serial is now Sold + linked to THIS sale ───────
    $scanned->refresh();
    expect($scanned->status)->toBe(1);
    expect((int) $scanned->sale_id)->toBe($saleId);

    // ── STEP 4: the sibling serial is UNCHANGED (only the scanned unit sold) ─
    $sibling->refresh();
    expect($sibling->status)->toBe(0);
    expect($sibling->sale_id)->toBeNull();

    // ── STEP 5: the invoice view shows MS26A8dg for that line item ─────────
    $invoice = $this->actingAs($env['user'])->get(route('sales.invoice', $saleId));
    $invoice->assertOk();
    $invoiceHtml = $invoice->getContent();
    expect($invoiceHtml)->toContain('MS26A8dg');
    expect($invoiceHtml)->not->toContain('MS26B91x');

    // ── STEP 6: the sale-details view shows MS26A8dg too ───────────────────
    $details = $this->actingAs($env['user'])->get(route('sales.show', $saleId));
    $details->assertOk();
    $detailsHtml = $details->getContent();
    expect($detailsHtml)->toContain('MS26A8dg');
    expect($detailsHtml)->not->toContain('MS26B91x');

    // ── STEP 7: Serial History for MS26A8dg shows Sold + the exact sale ────
    $history = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'MS26A8dg']));
    $history->assertOk();
    $historyHtml = $history->getContent();
    expect($historyHtml)->toContain('MS26A8dg');
    expect($historyHtml)->toContain('Sold');
    expect($historyHtml)->toContain(route('sales.invoice', $saleId)); // linked invoice reference

    // And the sibling's history shows it is STILL AVAILABLE (not sold).
    $siblingHistory = $this->actingAs($env['user'])->get(route('items.serial-history', ['q' => 'MS26B91x']));
    $siblingHistory->assertOk();
    expect($siblingHistory->getContent())->toContain('Available');
    expect($siblingHistory->getContent())->not->toContain('Sold');
});
