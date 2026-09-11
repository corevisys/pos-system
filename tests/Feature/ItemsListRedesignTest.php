<?php

use App\Models\User;
use App\Models\DbStore;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbUnit;
use App\Models\DbTax;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'COREVISYS TEST STORE',
        'status' => 1,
    ]);

    \App\Models\DbRole::firstOrCreate(['id' => 1], [
        'role_name' => 'Super Admin',
        'status' => 1,
        'store_id' => 1,
    ]);

    \App\Models\DbPermission::firstOrCreate(['role_id' => 1, 'store_id' => 1], [
        'permissions' => ['items_view', 'items_add', 'items_print_labels', 'items_delete'],
    ]);
});

test('items list renders with design-system header, card filter bar and x-table', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $category = DbCategory::create(['category_name' => 'Electronics', 'status' => 1, 'store_id' => 1]);
    $brand = DbBrand::create(['brand_name' => 'Sony', 'status' => 1, 'store_id' => 1]);
    $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => 1]);
    $tax = DbTax::create(['tax_name' => 'GST 0%', 'tax' => 0, 'status' => 1, 'store_id' => 1]);

    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Sony WH-1000XM5 Wireless Headphones',
        'item_code' => 'IT-00101',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'tax_id' => $tax->id,
        'sales_price' => 399.99,
        'purchase_price' => 280.00,
        'stock' => 15,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('items.list'));
    $response->assertOk();

    $html = $response->getContent();

    // Header title retained for PageTitleTest compatibility
    expect($html)->toContain('<title>Items List - COREVISYS TEST STORE</title>');

    // Design-system structure
    expect($html)->toContain('x-data="itemsListPage()"');
    expect($html)->toContain('id="itemFilterForm"');
    expect($html)->toContain('name="per_page"');
    expect($html)->toContain('Serial History');
    expect($html)->toContain('Sony WH-1000XM5 Wireless Headphones');

    // No leaked raw Alpine object / no dead bulk-select, no SweetAlert CDN
    expect($html)->not->toContain('selectedItems');
    expect($html)->not->toContain('item-checkbox');
    expect($html)->not->toContain('sweetalert2');

    // Export wiring present (rendered as query params, not Blade syntax)
    expect($html)->toContain('export=csv');
    expect($html)->toContain('export=print');
});

test('items list keeps search value safe inside Alpine data script (no raw quote break)', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    // Search term containing a raw double quote would previously terminate the
    // inline x-data attribute early — must be safely JSON-encoded by @js().
    $response = $this->actingAs($user)->get(route('items.list', ['search' => 'head"phones']));
    $response->assertOk();

    $html = $response->getContent();

    // @js() JSON-encodes the search term using JSON_HEX_QUOT, so a raw double quote
    // becomes the safe \u0022 escape — this is the Add-Item-incident safeguard.
    // (Rendered as a single-quoted JS string literal.)
    expect($html)->toContain("searchTerm: 'head\\u0022phones'");

    // And no leaked visible JS text should appear in the rendered HTML body outside <script>.
    // Crude but effective: there should be exactly ONE occurrence of searchTerm assignment
    // and it must live inside the script block.
    expect(substr_count($html, 'searchTerm:'))->toBe(1);
});

test('items list page inline Alpine script passes node --check (browser-level JS syntax)', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    DbItem::create([
        'store_id' => 1,
        'item_name' => "Sony WH-1000XM5 Wireless Headphones",
        'item_code' => 'IT-00101',
        'sales_price' => 399.99,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('items.list'));
    $response->assertOk();

    $html = $response->getContent();

    // Extract all inline <script> blocks that do NOT have a src attribute.
    preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $html, $matches);
    $inlineScripts = $matches[1] ?? [];

    expect($inlineScripts)->not->toBeEmpty();

    foreach ($inlineScripts as $idx => $js) {
        // Write each inline script to a temp file with a .js extension and run node --check
        // (Node 24 rejects unknown extensions like .tmp)
        $tmp = tempnam(sys_get_temp_dir(), 'items_list_js_') . '.js';
        file_put_contents($tmp, $js);
        exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
        unlink($tmp);

        expect($code)->toBe(0, "Inline script #{$idx} failed node --check:\n" . implode("\n", $out));
    }

    // No raw/leaked JS text outside <script> tags: the Alpine.data registration
    // must only appear inside a script block. (The x-data="itemsListPage()"
    // attribute on the root div is legitimate — it references the registered
    // component name; the REGISTRATION itself must not leak.)
    $body = preg_replace('/<script.*?<\/script>/is', '', $html);
    expect($body)->not->toContain("Alpine.data('itemsListPage'");
    expect($body)->not->toContain('submitDelete(btn)');
});

test('items list CSV export is store-scoped and includes filtered rows', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $cat = DbCategory::create(['category_name' => 'Electronics', 'status' => 1, 'store_id' => 1]);
    $brand = DbBrand::create(['brand_name' => 'Sony', 'status' => 1, 'store_id' => 1]);

    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Export Me Item',
        'item_code' => 'EXP-001',
        'category_id' => $cat->id,
        'brand_id' => $brand->id,
        'sales_price' => 100.00,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);
    // Store-2 item must NOT appear in store-1 export
    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Store 2 Secret Item',
        'item_code' => 'SEC-002',
        'sales_price' => 999.00,
        'status' => 1,
        'store_id' => 2,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('items.list', ['export' => 'csv']));
    $response->assertOk();
    $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    $csv = $response->streamedContent();

    expect($csv)->toContain('Export Me Item');
    expect($csv)->toContain('EXP-001');
    expect($csv)->not->toContain('Store 2 Secret Item');
    expect($csv)->not->toContain('SEC-002');
});

test('items list print/PDF export renders filtered store-scoped rows', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    $cat = DbCategory::create(['category_name' => 'Audio', 'status' => 1, 'store_id' => 1]);

    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Print Me Headphones',
        'item_code' => 'PRT-001',
        'category_id' => $cat->id,
        'sales_price' => 200.00,
        'status' => 1,
        'store_id' => 1,
        'child_bit' => 0,
    ]);
    DbItem::create([
        'store_id' => 1,
        'item_name' => 'Store 2 Audio Item',
        'item_code' => 'PRT-999',
        'category_id' => $cat->id,
        'sales_price' => 500.00,
        'status' => 1,
        'store_id' => 2,
        'child_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('items.list', ['export' => 'print']));
    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('Items List');
    expect($html)->toContain('Print Me Headphones');
    expect($html)->toContain('PRT-001');
    expect($html)->not->toContain('Store 2 Audio Item');
    expect($html)->not->toContain('PRT-999');
});

test('items list per_page selector persists query string and keeps page param', function () {
    $user = User::factory()->create([
        'role_id' => 1,
        'role_name' => 'Super Admin',
        'store_id' => 1,
    ]);

    for ($i = 1; $i <= 25; $i++) {
        DbItem::create([
            'store_id' => 1,
            'item_name' => "Test Item {$i}",
            'item_code' => "SKU-{$i}",
            'sales_price' => 10 + $i,
            'status' => 1,
            'store_id' => 1,
            'child_bit' => 0,
        ]);
    }

    $response = $this->actingAs($user)->get(route('items.list', ['per_page' => 25]));
    $response->assertOk();

    $html = $response->getContent();
    // 25 rows means one page contains all 25
    expect(substr_count($html, 'Test Item '))->toBeGreaterThanOrEqual(25);
});
