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
 * Phase 1 — Add Item form validation fixes.
 *
 * Verifies that:
 *   1. Every MISSING/PARTIAL field now rejects bad input with a clear,
 *      inline-attributable error (server-side session error keyed to the field,
 *      plus the DuplicateSerialNumberException flashing the offending serial
 *      under 'serial_error' for row-level inline rendering).
 *   2. A valid Single-item submission still succeeds (control).
 *   3. The Purchase Quick-Add-Item modal (its own smaller validator, untouched)
 *      still succeeds with its smaller required-field set — the full Add Item
 *      form's stricter/extra rules must NOT leak into quick-add.
 */
class ItemAddValidationAndContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbStore $store;
    protected DbWarehouse $warehouse;
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
            'store_code' => 'ITEMVAL-ST',
            'store_name' => 'Item Validation Store',
            'mobile' => '01799991111',
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
            ],
        ]);

        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
        ]);

        $this->warehouse = DbWarehouse::create([
            'store_id' => 1,
            'warehouse_name' => 'Validation Warehouse',
            'store_id' => $this->store->id,
            'status' => 1,
        ]);

        $category = DbCategory::create(['category_name' => 'Validation Goods', 'status' => 1, 'store_id' => $this->store->id]);
        $unit = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => $this->store->id]);
        $tax = DbTax::create(['tax_name' => 'VAT 5%', 'tax' => 5, 'status' => 1, 'store_id' => $this->store->id]);

        $this->categoryId = $category->id;
        $this->unitId = $unit->id;
        $this->taxId = $tax->id;
    }

    /** A valid Single-item payload (control). */
    protected function validPayload(string $itemName = 'Valid Single Item', array $overrides = []): array
    {
        return array_merge([
            'item_name' => $itemName,
            'item_group' => 'Single',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Percentage',
            'discount' => 0,
            'warehouse_id' => $this->warehouse->id,
            'price' => 100,
            'purchase_price' => 100,
            'sales_price' => 150,
            'opening_stock' => 0,
        ], $overrides);
    }

    /** The Purchase Quick-Add-Item modal's own smaller payload. */
    protected function quickAddPayload(string $itemName = 'QuickAdd Contract Item', array $overrides = []): array
    {
        return array_merge([
            'item_name' => $itemName,
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'purchase_price' => 100,
            'sales_price' => 150,
            'warehouse_id' => $this->warehouse->id,
            'is_serialized' => 0,
        ], $overrides);
    }

    /* ─────────────────────────── REJECTION TESTS ─────────────────────────── */

    /** Reject: item_name blank → session error attributable to item_name. */
    public function test_store_rejects_blank_item_name_with_field_attributable_error()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('', ['price' => 100]));
        $res->assertSessionHasErrors('item_name');
        $this->assertStringContainsString('item name', session('errors')->first('item_name'));
        $this->assertDatabaseMissing('db_items', ['item_name' => '']);
    }

    /** Reject: category missing → session error attributable to category_id. */
    public function test_store_rejects_missing_category()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('No Category Item', ['category_id' => null]));
        $res->assertSessionHasErrors('category_id');
    }

    /** Reject: price negative → session error attributable to price. */
    public function test_store_rejects_negative_price()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Negative Price', ['price' => -5]));
        $res->assertSessionHasErrors('price');
    }

    /** Reject: SKU format with invalid characters (space / special) server-side. */
    public function test_store_rejects_invalid_sku_format()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Bad SKU Item', ['sku' => 'SKU WITH SPACES!!']));
        $res->assertSessionHasErrors('sku');
    }

    /** Reject: warehouse missing when opening stock is added. */
    public function test_store_rejects_missing_warehouse_with_stock()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('No Wh Item', ['opening_stock' => 3, 'warehouse_id' => null]));
        $res->assertSessionHasErrors('warehouse_id');
    }

    /** Reject: intra-submission duplicate serial → clean per-serial message AND serial_error flash. */
    public function test_store_intra_submission_duplicate_serial_flashes_offending_serial_inline()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Dup Serial Item', [
            'opening_stock' => 2,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-DUPX', 'SN-DUPX'],
        ]));

        $res->assertSessionHas('error');
        $this->assertStringContainsString('SN-DUPX', session('error'));
        // NEW (Phase 1): the offending serial is flashed under a dedicated key so the
        // blade can highlight the exact SLN row inline, not just toast the error.
        $this->assertSame('SN-DUPX', session('serial_error'));
        $this->assertSame(0, DbItemSerial::where('serial_number', 'SN-DUPX')->count());
    }

    /** Reject: serial count mismatch (blank serial slot) → field-attributable error, no rows. */
    public function test_store_rejects_serial_count_mismatch()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Blank Serial Slot', [
            'opening_stock' => 2,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-ONE', ''],
        ]));

        // Phase 1: the count-vs-stock mismatch is now a validator-level error keyed
        // to serial_numbers so the blade can render it inline next to the SLN grid.
        $res->assertSessionHasErrors('serial_numbers');
        $msg = session('errors')->first('serial_numbers');
        $this->assertStringContainsString('2 serial numbers', $msg);
        $this->assertStringContainsString('1 entered', $msg);

        // No item / serial rows were committed.
        $this->assertNull(DbItem::where('item_name', 'Blank Serial Slot')->first());
        $this->assertSame(0, DbItemSerial::where('serial_number', 'SN-ONE')->count());
    }

    /* ───────────── LEGACY SKU/BARCODE EDIT-SAFETY (CHANGE-DETECTION) ───────────── */

    /**
     * Legacy-data safety: an item created directly in the DB with a NON-conforming
     * SKU (simulating pre-rule data) must still be EDITABLE for an unrelated field.
     * Submitting the same (bad) SKU unchanged must NOT be rejected by the new
     * format regex — the rule only bites when the value actually changes.
     */
    public function test_update_legacy_non_conforming_sku_untouched_succeeds()
    {
        $legacy = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'LEGACY-SKU',
            'item_name' => 'Legacy Bad SKU Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Percentage',
            'item_group' => 'Single',
            'price' => 50,
            'purchase_price' => 50,
            'sales_price' => 75,
            'sku' => 'BAD SKU WITH SPACES', // legacy non-conforming value
            'custom_barcode' => 'BARCODE!!LEGACY',
            'stock' => 0,
            'status' => 1,
        ]);

        $res = $this->actingAs($this->user)->post(route('items.update', $legacy->id), [
            'item_name' => 'Legacy Bad SKU Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Percentage',
            'item_group' => 'Single',
            'price' => 55,            // unrelated field CHANGED
            'purchase_price' => 55,
            'sales_price' => 80,
            'sku' => 'BAD SKU WITH SPACES', // SKU left UNCHANGED (legacy value)
            'custom_barcode' => 'BARCODE!!LEGACY', // barcode left UNCHANGED
            'opening_stock' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect(route('items.list'));
        $legacy->refresh();
        $this->assertEqualsWithDelta(55.0, (float) $legacy->price, 0.01);
        $this->assertSame('BAD SKU WITH SPACES', $legacy->sku);
        $this->assertSame('BARCODE!!LEGACY', $legacy->custom_barcode);
    }

    /**
     * The rule MUST still apply to genuinely CHANGED values: editing an item's SKU
     * to a new non-conforming value (that differs from the stored one) is rejected.
     */
    public function test_update_changed_sku_to_non_conforming_rejected()
    {
        $item = DbItem::create([
            'store_id' => $this->store->id,
            'item_code' => 'CHANGE-SKU',
            'item_name' => 'Change SKU Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Percentage',
            'item_group' => 'Single',
            'price' => 50,
            'purchase_price' => 50,
            'sales_price' => 75,
            'sku' => 'GOOD-SKU-1', // conforming stored value
            'stock' => 0,
            'status' => 1,
        ]);

        $res = $this->actingAs($this->user)->post(route('items.update', $item->id), [
            'item_name' => 'Change SKU Item',
            'category_id' => $this->categoryId,
            'unit_id' => $this->unitId,
            'tax_id' => $this->taxId,
            'tax_type' => 'Inclusive',
            'discount_type' => 'Percentage',
            'item_group' => 'Single',
            'price' => 60,
            'purchase_price' => 60,
            'sales_price' => 90,
            'sku' => 'NEW BAD SKU', // CHANGED to non-conforming → must reject
            'opening_stock' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertSessionHasErrors('sku');
        $msg = session('errors')->first('sku');
        $this->assertStringContainsString('SKU may only contain letters', $msg);
        $item->refresh();
        $this->assertSame('GOOD-SKU-1', $item->sku); // unchanged in DB
    }

    /* ─────────────────────────── CONTROL TESTS ─────────────────────────── */

    /** Control: valid Single item succeeds. */
    public function test_store_valid_single_item_succeeds()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Control Valid Item'));
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success');
        $res->assertRedirect(route('items.list'));

        $item = DbItem::where('item_name', 'Control Valid Item')->first();
        $this->assertNotNull($item);
        $this->assertSame('Single', $item->item_group);
        $this->assertEqualsWithDelta(100.0, (float) $item->price, 0.01);
    }

    /** Control: valid Single serialized item with opening stock succeeds + source tagged. */
    public function test_store_valid_serialized_single_item_succeeds()
    {
        $res = $this->actingAs($this->user)->post(route('items.store'), $this->validPayload('Control Serial Item', [
            'opening_stock' => 2,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-CTRL-1', 'SN-CTRL-2'],
        ]));
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success');

        $item = DbItem::where('item_name', 'Control Serial Item')->first();
        $this->assertNotNull($item);
        $this->assertSame(1, $item->is_serialized);
        $this->assertSame(2, DbItemSerial::where('item_id', $item->id)->where('status', 0)->count());
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $item->id, 'serial_number' => 'SN-CTRL-1', 'source' => 'item_add']);
        $this->assertDatabaseHas('db_item_serials', ['item_id' => $item->id, 'serial_number' => 'SN-CTRL-2', 'source' => 'item_add']);
    }

    /* ─────────────────── PURCHASE QUICK-ADD CONTRACT (PROTECTED) ─────────────────── */

    /**
     * Protected contract: the Purchase Quick-Add-Item modal (PurchaseController::
     * quickStoreItem) shares ItemCreationService::createSingleItem() but has its
     * OWN smaller validator. The extra rules added on the full Add Item form in
     * this pass (discount_type, mrp, seller_points, hsn, variants, ...) must NOT
     * be required here. This test posts the modal's real (small) payload and
     * asserts it still succeeds.
     */
    public function test_purchase_quick_add_item_modal_still_succeeds_with_smaller_field_set()
    {
        $res = $this->actingAs($this->user)->postJson(route('purchase.quick.item.store'), $this->quickAddPayload());
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);
        $this->assertDatabaseHas('db_items', ['item_name' => 'QuickAdd Contract Item']);
    }

    /** Quick-add with opening stock + serial (serialized) still registers + tags source. */
    public function test_purchase_quick_add_item_modal_serialized_still_succeeds()
    {
        $res = $this->actingAs($this->user)->postJson(route('purchase.quick.item.store'), $this->quickAddPayload('QuickAdd Serial Item', [
            'opening_stock' => 1,
            'is_serialized' => 1,
            'serial_numbers' => ['SN-QA-CONTRACT'],
        ]));
        $res->assertJson(['success' => true]);
        $this->assertDatabaseHas('db_item_serials', ['serial_number' => 'SN-QA-CONTRACT', 'source' => 'purchase_quick_add_item']);
    }
}
