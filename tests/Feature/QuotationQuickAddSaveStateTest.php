<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Quotation page's Quick Add Item modal UX bugs,
 * mirroring the verified New Purchase fix (see
 * NewPurchaseQuickAddSerialFlowTest::test_quick_add_save_button_loading_state_and_backdrop_guard).
 *
 *   Issue A — the Quick Add Save button was NEVER wired: submitQuickItem() set no
 *             in-flight flag, so there was no spinner, no disabled state and a
 *             double-click could fire two item-create requests. The fix adds a
 *             `quickItemSaving` flag (guard at top, set true before the request,
 *             reset in finally), a disabled state and a spinner + "Saving..."
 *             label — matching the New Purchase implementation's markup exactly.
 *
 *   Issue B — the modal is a hand-rolled div (NOT <x-modal>) dismissed by
 *             @click.away; the fix guards it so the backdrop cannot close the
 *             modal while a save is in flight (idle backdrop-close still works).
 *
 * The quotation Quick Add is deliberately SIMPLER than New Purchase's: quotations
 * do not register stock/serials, so there is no serial grid / opening-stock
 * hand-off. These tests also assert that New Purchase's serials logic was NOT
 * blindly copied in.
 */
class QuotationQuickAddSaveStateTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Quotation Quick Add Test Store',
            'status' => 1,
            'mobile' => '+8801700000000',
            'quotation_init' => 'QU',
            'sales_init' => 'SA',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 1], [
            'store_id' => 1,
            'permissions' => [
                'quotation_view',
                'quotation_add',
                'quotation_edit',
                'items_add',
            ],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Main Warehouse', 'status' => 1]);
        DbCustomer::create(['store_id' => 1, 'customer_name' => 'Regular Customer', 'mobile' => '01711111111', 'status' => 1]);
        DbTax::create(['store_id' => 1, 'tax_name' => 'VAT 0%', 'tax' => 0, 'status' => 1]);
        DbCategory::create(['store_id' => 1, 'category_name' => 'Goods', 'status' => 1]);
        DbUnit::create(['store_id' => 1, 'unit_name' => 'Pcs', 'status' => 1]);
        DbBrand::create(['store_id' => 1, 'brand_name' => 'Generic', 'status' => 1]);
    }

    /**
     * Issue A (save button loading state) + Issue B (backdrop-close guard) in the
     * Quotation Quick Add Item modal, asserted against the Blade source so a
     * rewording that breaks the contract fails here; rendering the page proves the
     * Blade still compiles.
     */
    public function test_quotation_quick_add_save_button_loading_state_and_backdrop_guard(): void
    {
        $blade = file_get_contents(base_path('resources/views/module/quotation/new_quotation.blade.php'));

        // --- Issue A: in-flight flag wired end to end ---
        $this->assertStringContainsString('quickItemSaving: false,', $blade);
        $this->assertStringContainsString('if (this.quickItemSaving) return;', $blade);
        $this->assertStringContainsString('this.quickItemSaving = true;', $blade);
        $this->assertStringContainsString('this.quickItemSaving = false;', $blade);

        // Save button: bound to the flag, disabled while saving, spinner + label,
        // matching the New Purchase markup structure.
        $this->assertStringContainsString('@click="submitQuickItem()" :disabled="quickItemSaving"', $blade);
        $this->assertStringContainsString('<template x-if="quickItemSaving">', $blade);
        $this->assertStringContainsString('<span>Saving...</span>', $blade);
        $this->assertStringContainsString('<span>Save Item</span>', $blade);

        // --- Issue B: backdrop-close is guarded while saving ---
        $this->assertStringContainsString('@click.away="if(!quickItemSaving) showQuickAdd = false"', $blade);

        // Explicit close controls remain unconditional (only the backdrop is guarded).
        $this->assertStringContainsString('@click="showQuickAdd = false" class="btn-secondary !py-1.5 !px-3 text-xs font-bold">', $blade);

        // --- Data-flow difference: the serials hand-off was NOT blindly copied ---
        // Quotations do not register stock/serials, so none of New Purchase's
        // serial machinery should appear on this page.
        $this->assertStringNotContainsString('pendingQuickAddSerials', $blade);
        $this->assertStringNotContainsString('is_serialized', $blade);
        $this->assertStringNotContainsString('serial_numbers', $blade);

        $this->actingAs($this->user)
            ->get(route('quotation.new'))
            ->assertOk()
            ->assertSee('Quick Add Item', false)
            ->assertSee('Save Item', false);
    }
}