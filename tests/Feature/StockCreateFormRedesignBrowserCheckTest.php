<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Browser-level verification for the New Transfer / New Adjustment redesign
 * (Phase 3 of the create-form redesign pass).
 *
 * The standing convention from the Add Item incident: every Alpine component
 * must be registered via Alpine.data('name', fn) (x-data="name()"), never a
 * large inline x-data object — a raw quote in an inline x-data attribute
 * terminates the attribute early, leaking JS as visible text. PHPUnit passing
 * alone is not sufficient: we run node --check against every inline <script>
 * and scan the rendered body (script tags stripped) for leaked JS text.
 */
class StockCreateFormRedesignBrowserCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Redesign Browser Store', 'status' => 1, 'mobile' => '01700000077']);

        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => $role->id], [
            'store_id' => 1,
            'permissions' => ['stock_transfer_view', 'stock_transfer_add', 'stock_adjustment_view', 'stock_adjustment_add'],
        ]);

        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin']);

        // A serialized item whose NAME contains a raw double-quote — the exact
        // Add Item incident class: if the Alpine component leaks it unescaped,
        // it breaks an attribute boundary or appears as visible JS text.
        $cat = DbCategory::create(['category_name' => 'Quote Cat', 'status' => 1]);
        DbItem::create([
            'item_name' => 'Head"phones 1"', 'item_code' => 'QT-001',
            'category_id' => $cat->id, 'purchase_price' => 5, 'sales_price' => 10,
            'stock' => 100, 'status' => 1, 'store_id' => 1, 'is_serialized' => 1,
        ]);

        $wh = DbWarehouse::create(['warehouse_name' => 'WH-Quote', 'store_id' => 1, 'status' => 1]);
        DbWarehouse::create(['warehouse_name' => 'WH-Quote-2', 'store_id' => 1, 'status' => 1]);
    }

    protected function runNodeCheckOnInlineScripts(string $html, string $label): void
    {
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $html, $matches);
        $inlineScripts = $matches[1] ?? [];

        $this->assertNotEmpty($inlineScripts, "{$label}: no inline scripts found.");

        foreach ($inlineScripts as $idx => $js) {
            $tmp = tempnam(sys_get_temp_dir(), 'stock_form_js_') . '.js';
            file_put_contents($tmp, $js);
            exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            unlink($tmp);
            $this->assertSame(0, $code, "{$label}: inline script #{$idx} failed node --check:\n" . implode("\n", $out));
        }
    }

    protected function assertNoLeakedJsOutsideScripts(string $html, string $label): void
    {
        $body = preg_replace('/<script.*?<\/script>/is', '', $html);

        // The Alpine.data registration must only live inside a script block.
        $this->assertStringNotContainsString("Alpine.data('transferForm'", $body, "{$label}: transferForm registration leaked outside <script>.");
        $this->assertStringNotContainsString("Alpine.data('adjustmentForm'", $body, "{$label}: adjustmentForm registration leaked outside <script>.");

        // Raw double-quote item names must be JSON-escaped inside the script and
        // HTML-escaped in the table — never appear as a raw attribute-breaking quote.
        $this->assertStringNotContainsString('Head"phones', $body, "{$label}: raw double-quote leaked into the rendered body (Add Item incident class).");
    }

    public function test_transfer_create_page_node_check_and_no_leaks()
    {
        $response = $this->actingAs($this->user)->get(route('stock.transfer.create'));
        $response->assertOk();
        $html = $response->getContent();

        // Standing convention: the component is registered, not inline x-data.
        $this->assertStringContainsString('x-data="transferForm()"', $html);

        $this->runNodeCheckOnInlineScripts($html, 'Transfer Create');
        $this->assertNoLeakedJsOutsideScripts($html, 'Transfer Create');

        // The search term is bound via x-model (never interpolated raw), so no
        // user-supplied quote can terminate an attribute.
        $this->assertStringContainsString('x-model="searchQuery"', $html);
    }

    public function test_adjustment_create_page_node_check_and_no_leaks()
    {
        $response = $this->actingAs($this->user)->get(route('stock.adjustment.create'));
        $response->assertOk();
        $html = $response->getContent();

        // Standing convention: the component is registered, not inline x-data.
        $this->assertStringContainsString('x-data="adjustmentForm()"', $html);

        $this->runNodeCheckOnInlineScripts($html, 'Adjustment Create');
        $this->assertNoLeakedJsOutsideScripts($html, 'Adjustment Create');

        $this->assertStringContainsString('x-model="searchQuery"', $html);
    }
}
