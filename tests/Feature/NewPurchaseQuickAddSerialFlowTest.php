<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbLanguage;
use App\Models\DbPermission;
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
 * Regression coverage for the New Purchase page's two reported serial bugs:
 *
 *   Issue A — serials entered in the Quick-Add Product modal (opening stock) were
 *             never shown on the parent purchase line. Root cause: the modal
 *             registered the item's serials itself (source 'purchase_quick_add_item')
 *             while addItem() hard-coded the new line to serials: []. The fix
 *             (Option 1) makes quick-add create the item DEFINITION only
 *             (opening_stock 0, no serials) and hands the typed serials to the cart
 *             line, so the PURCHASE registers them (source 'purchase') exactly like a
 *             normal serialized line.
 *
 *   Issue B — serial entry never auto-advanced. The fix adds Enter-key focus
 *             chaining to both serial grids (Quick-Add grid + per-line serial modal).
 *
 * These tests assert both the produced backend flow AND the front-end wiring, so a
 * regression in either half fails loudly.
 */
class NewPurchaseQuickAddSerialFlowTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected User $user;
    protected DbWarehouse $warehouse;
    protected DbSupplier $supplier;
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
            'store_code' => 'NP-QA-ST',
            'store_name' => 'New Purchase Quick Add Store',
            'mobile' => '01711110000',
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
                'items_add', 'items_edit', 'items_view',
                'purchase_add', 'purchase_edit', 'purchase_view',
            ],
        ]);

        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => $this->store->id,
            'warehouse_name' => 'Main Warehouse',
            'status' => 1,
        ]);

        $this->supplier = DbSupplier::create([
            'store_id' => $this->store->id,
            'supplier_name' => 'Quick Add Supplier',
            'supplier_code' => 'SUP-NPQA-01',
            'mobile' => '01712220000',
            'status' => 1,
        ]);

        AcAccount::create([
            'store_id' => $this->store->id,
            'account_name' => 'Quick Add Cash',
            'account_number' => 'ACC-NPQA-01',
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

    /**
     * The FIXED front-end contract: a serialized quick-add with opening stock sends
     * the item-create request with opening_stock = 0 and no serial_numbers, because
     * the serials (and stock) are supplied later by the purchase.
     */
    protected function quickAddDefinitionOnlyPayload(string $itemName): array
    {
        return [
            'item_name' => $itemName,
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'purchase_price' => 100,
            'sales_price' => 200,
            'is_serialized' => 1,
            'opening_stock' => 0,
            'serial_numbers' => [],
        ];
    }

    /** Standard New Purchase payload for a serialized line. */
    protected function purchasePayload(int $itemId, array $serials): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_date' => date('Y-m-d'),
            'amount_paid' => 0,
            'cart' => [[
                'item_id' => $itemId,
                'qty' => count($serials),
                'price' => 100.00,
                'is_serialized' => 1,
                'serials' => $serials,
            ]],
        ];
    }

    /**
     * Issue A (end-to-end): quick-add creates the item DEFINITION only, then the
     * purchase registers all typed serials (source 'purchase') with status available,
     * and the item's stock reflects the purchase qty — i.e. nothing is double-counted
     * and the serials are owned by the purchase line.
     */
    public function test_quick_add_definition_then_purchase_registers_serials(): void
    {
        $serials = ['SN-NPQA-1', 'SN-NPQA-2', 'SN-NPQA-3'];

        $quick = $this->actingAs($this->user)
            ->postJson(route('purchase.quick.item.store'), $this->quickAddDefinitionOnlyPayload('Quick Add Serialized Item'));

        $quick->assertJson(['success' => true]);
        $itemId = $quick->json('item.id');
        $this->assertNotNull($itemId);

        // Definition-only: NO serial rows, NO opening stock/warehouse row created here.
        $this->assertSame(0, DbItemSerial::where('item_id', $itemId)->count());
        $this->assertDatabaseHas('db_items', ['id' => $itemId, 'is_serialized' => 1, 'stock' => 0]);

        $purchase = $this->actingAs($this->user)
            ->postJson(route('purchase.store'), $this->purchasePayload($itemId, $serials));

        $purchase->assertJson(['success' => true]);

        foreach ($serials as $sn) {
            $this->assertDatabaseHas('db_item_serials', [
                'item_id' => $itemId,
                'serial_number' => $sn,
                'source' => 'purchase',
                'status' => 0,
            ]);
        }

        // Exactly one row per serial (no duplication from a second registration path).
        $this->assertSame(3, DbItemSerial::where('item_id', $itemId)->count());
        $this->assertEquals(3, (float) DbItem::find($itemId)->stock);
    }

    /**
     * Issue A (documents WHY the front-end fix is required): if quick-add registered
     * the serials itself and the purchase then submitted the SAME serials for the same
     * item, the cross-entry-point uniqueness check rejects the purchase. This is the
     * behaviour the old front-end produced and the fixed front-end avoids.
     */
    public function test_pre_fix_double_registration_is_rejected(): void
    {
        $serials = ['SN-NPQA-DUP-1', 'SN-NPQA-DUP-2'];

        // OLD behaviour: quick-add registers opening stock + serials itself.
        $quick = $this->actingAs($this->user)->postJson(route('purchase.quick.item.store'), [
            'item_name' => 'Quick Add Pre-Fix Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'purchase_price' => 100,
            'sales_price' => 200,
            'is_serialized' => 1,
            'opening_stock' => 2,
            'warehouse_id' => $this->warehouse->id,
            'serial_numbers' => $serials,
        ]);
        $quick->assertJson(['success' => true]);
        $itemId = $quick->json('item.id');

        $this->assertSame(2, DbItemSerial::where('item_id', $itemId)->where('source', 'purchase_quick_add_item')->count());

        // Purchase re-submitting the same serials → clean 422, not a double insert.
        $purchase = $this->actingAs($this->user)
            ->postJson(route('purchase.store'), $this->purchasePayload($itemId, $serials));

        $purchase->assertStatus(422);
        $purchase->assertJson(['success' => false]);
    }

    /**
     * Issue A + B (front-end wiring): the New Purchase blade must
     *  - divert quick-add serials to the cart line (definition-only payload), and
     *  - carry Enter-key focus auto-advance on both serial grids.
     * Asserted against both the rendered page and the Blade source so a JS
     * rewording that breaks the contract fails here.
     */
    public function test_new_purchase_blade_hands_quick_add_serials_to_line_and_autoadvances(): void
    {
        $blade = file_get_contents(base_path('resources/views/module/purchase/new_purchase.blade.php'));

        // --- Issue A: definition-only quick-add + serial hand-off to the cart line ---
        $this->assertStringContainsString('pendingQuickAddSerials', $blade);
        $this->assertStringContainsString('opening_stock: serializedQuickAdd ? 0 :', $blade);
        $this->assertStringContainsString('payload.serial_numbers = [];', $blade);
        $this->assertStringContainsString('this.pendingQuickAddSerials = enteredSerials;', $blade);
        $this->assertStringContainsString('serials: (isSerialized && pendingSerials.length > 0) ? pendingSerials.slice() : []', $blade);

        // --- Issue B: Enter auto-advance on both serial grids ---
        $this->assertStringContainsString('focusSerialSlot(container, currentEl = null)', $blade);
        $this->assertStringContainsString('x-ref="quickAddSerialGrid"', $blade);
        $this->assertStringContainsString('x-ref="serialModalGrid"', $blade);
        $this->assertStringContainsString('@keydown.enter.prevent="focusSerialSlot($refs.quickAddSerialGrid, $event.target)"', $blade);
        $this->assertStringContainsString('@keydown.enter.prevent="focusSerialSlot($refs.serialModalGrid, $event.target)"', $blade);

        // Render the page too, proving the Blade compiles and the component is reachable.
        $this->actingAs($this->user)
            ->get(route('purchase.new'))
            ->assertOk()
            ->assertSee('Quick Add Product', false);
    }

    /**
     * Issue A (save button loading state) + Issue B (backdrop-close guard) in the
     * Quick Add Product modal:
     *
     *  Issue A — submitQuickItem() previously set NO in-flight flag, so the Save
     *            button gave no feedback and could be double-clicked. The fix adds
     *            a `quickItemSaving` flag (set before the fetch, reset in finally),
     *            a re-entry guard, a disabled state and a spinner + "Saving..."
     *            label — mirroring the in-file savePurchase() precedent.
     *
     *  Issue B — the modal is a hand-rolled div (NOT <x-modal>) dismissed by
     *            @click.away; the fix guards it so the backdrop cannot close the
     *            modal while a save is in flight (idle backdrop-close still works).
     *
     * Asserted against the Blade source so a rewording that breaks the contract
     * fails here; the rendered page proves the Blade still compiles.
     */
    public function test_quick_add_save_button_loading_state_and_backdrop_guard(): void
    {
        $blade = file_get_contents(base_path('resources/views/module/purchase/new_purchase.blade.php'));

        // --- Issue A: in-flight flag wired end to end ---
        $this->assertStringContainsString('quickItemSaving: false,', $blade);
        $this->assertStringContainsString('if (this.quickItemSaving) return;', $blade);
        $this->assertStringContainsString('this.quickItemSaving = true;', $blade);
        $this->assertStringContainsString('this.quickItemSaving = false;', $blade);

        // Save button: bound to the flag, disabled while saving, spinner + label.
        $this->assertStringContainsString('@click="submitQuickItem()" :disabled="quickItemSaving"', $blade);
        $this->assertStringContainsString('<template x-if="quickItemSaving">', $blade);
        $this->assertStringContainsString('<span>Saving...</span>', $blade);

        // --- Issue B: backdrop-close is guarded while saving ---
        $this->assertStringContainsString('@click.away="if(!quickItemSaving) showQuickAdd = false"', $blade);

        // Explicit close controls must remain unconditional (only the backdrop is guarded).
        $this->assertStringContainsString('@click="showQuickAdd = false" class="btn-secondary !py-1.5 !px-3 text-xs font-bold">Cancel</button>', $blade);

        $this->actingAs($this->user)
            ->get(route('purchase.new'))
            ->assertOk()
            ->assertSee('Save Product', false);
    }
}