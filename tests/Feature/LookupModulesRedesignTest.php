<?php

namespace Tests\Feature;

use App\Models\DbCountry;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbState;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lookup-module redesign rollout (Units, Tax, Languages, Countries, States).
 *
 * Verifies, per module:
 *  - the design-system baseline renders (x-card / input-base / btn-primary /
 *    btn-secondary / text-text-muted / text-text-primary / text-danger) and the
 *    pre-redesign raw-utility baseline is gone;
 *  - every pre-existing name= attribute, route target and Alpine reference is
 *    preserved;
 *  - pagination ACTUALLY paginates (page 2 returns different rows);
 *  - search ACTUALLY filters server-side;
 *  - the @js inline-handler escape pattern holds (a quote in a stored value can
 *    never break an inline Alpine handler);
 *  - consumers (quick-add JSON contract, item unit/tax FKs, customer country/state
 *    FKs) still read the same ids/field names.
 */
class LookupModulesRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Lookup Store', 'status' => 1, 'mobile' => '01700000001']);

        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => $role->id], ['store_id' => 1, 'permissions' => ['all']]);

        $this->user = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => 'Super Admin',
            'status' => 1,
        ]);
    }

    private function assertDesignSystem(string $html, string $label): void
    {
        $this->assertStringContainsString('class="card', $html, "{$label}: no x-card rendered.");
        $this->assertStringContainsString('input-base', $html, "{$label}: no input-base class.");
        $this->assertStringContainsString('btn-primary', $html, "{$label}: no btn-primary class.");
        $this->assertStringContainsString('text-text-muted', $html, "{$label}: no text-text-muted class.");
        $this->assertStringContainsString('text-text-primary', $html, "{$label}: no text-text-primary class.");
        $this->assertStringContainsString('text-danger', $html, "{$label}: no text-danger class.");
    }

    private function assertRawBaselineGone(string $html, string $label): void
    {
        // Tokens unique to the pre-redesign MODULE markup (verified the shared app
        // layout never emits these exact sequences):
        //  - `shadow-rose-200/50` — rose button shadow on every pre-redesign New/Add button;
        //  - `bg-emerald-100 text-emerald-600 rounded-[4px]` — the old Active status badge;
        //  - `bg-rose-100 text-rose-600 rounded-[4px]` — the old Inactive status badge;
        //  - `bg-rose-600 text-white rounded-` — the old rose primary button.
        $this->assertStringNotContainsString('shadow-rose-200/50', $html, "{$label}: pre-redesign rose button shadow still present.");
        $this->assertStringNotContainsString('bg-emerald-100 text-emerald-600 rounded-[4px]', $html, "{$label}: pre-redesign Active status badge still present.");
        $this->assertStringNotContainsString('bg-rose-100 text-rose-600 rounded-[4px]', $html, "{$label}: pre-redesign Inactive status badge still present.");
        $this->assertStringNotContainsString('bg-rose-600 text-white rounded-', $html, "{$label}: pre-redesign rose primary button still present.");
    }

    private function assertSubmitGuard(string $html, string $label): void
    {
        $this->assertStringContainsString('isSubmitting', $html, "{$label}: submit-disable guard missing.");
        $this->assertStringContainsString(':disabled="isSubmitting"', $html, "{$label}: :disabled guard not bound.");
        $this->assertStringContainsString('@submit="isSubmitting = true"', $html, "{$label}: @submit guard not wired.");
    }

    /* ────────────────────────────── UNITS ────────────────────────────── */

    public function test_units_list_renders_design_system_and_preserves_contracts(): void
    {
        DbUnit::create(['unit_name' => 'Kilogram', 'description' => 'Weight', 'status' => 1, 'store_id' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.units'))->assertOk()->getContent();

        $this->assertDesignSystem($html, 'Units');
        $this->assertRawBaselineGone($html, 'Units');

        // Preserved form field names.
        $this->assertStringContainsString('name="unit_name"', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('name="status"', $html);

        // Preserved route targets.
        $this->assertStringContainsString('action="/settings/units"', $html);
        $this->assertStringContainsString(":action=\"'/settings/units/' + editId\"", $html);
        $this->assertStringContainsString('settings/units/', $html);

        // Preserved Alpine wiring.
        $this->assertStringContainsString('showAddModal', $html);
        $this->assertStringContainsString('showEditModal', $html);
        $this->assertStringContainsString('editName', $html);
        $this->assertStringContainsString('editDescription', $html);
        $this->assertStringContainsString('editStatus', $html);

        // Dead controls removed.
        $this->assertStringNotContainsString('type="checkbox"', $html, 'Units: select-all/row checkbox should be gone.');
        $this->assertStringNotContainsString('>Copy<', $html, 'Units: Copy button should be gone.');
        $this->assertStringNotContainsString('>Excel<', $html, 'Units: Excel button should be gone.');
        $this->assertStringNotContainsString('>PDF<', $html, 'Units: PDF button should be gone.');

        $this->assertSubmitGuard($html, 'Units');
    }

    public function test_units_pagination_actually_paginates(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DbUnit::create(['unit_name' => sprintf('Unit%02d', $i), 'status' => 1, 'store_id' => 1]);
        }

        $page1 = $this->actingAs($this->user)->get(route('settings.units', ['limit' => 10]))->getContent();
        $this->assertStringContainsString('Unit01', $page1);
        $this->assertStringNotContainsString('Unit12', $page1, 'Unit12 must be on page 2.');
        $this->assertMatchesRegularExpression('/href="[^"]*page=2/', $page1, 'Units: page=2 link expected.');

        $page2 = $this->actingAs($this->user)->get(route('settings.units', ['limit' => 10, 'page' => 2]))->getContent();
        $this->assertStringContainsString('Unit12', $page2);
        $this->assertStringNotContainsString('Unit01', $page2, 'Unit01 must not be on page 2.');
    }

    public function test_units_search_filters_server_side(): void
    {
        DbUnit::create(['unit_name' => 'Kilogram', 'status' => 1, 'store_id' => 1]);
        DbUnit::create(['unit_name' => 'Carton Box', 'status' => 1, 'store_id' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.units', ['search' => 'Kilo']))->getContent();

        $this->assertStringContainsString('Kilogram', $html);
        $this->assertStringNotContainsString('Carton Box', $html, 'Units: search did not filter out non-matching row.');
    }

    public function test_units_edit_modal_handler_is_js_escaped(): void
    {
        // A raw apostrophe/quote previously terminated the inline @click handler.
        DbUnit::create(['unit_name' => 'Head\'s "Unit"', 'description' => "O'Brien", 'status' => 1, 'store_id' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.units'))->assertOk()->getContent();

        // @js() hex-escapes the quote characters.
        $this->assertStringContainsString('\u0027', $html, 'Units: apostrophe was not JSON-escaped.');
        $this->assertStringContainsString('\u0022', $html, 'Units: double-quote was not JSON-escaped.');
        // The raw, handler-breaking sequence must not appear.
        $this->assertStringNotContainsString("editName = 'Head's", $html, 'Units: raw unescaped quote leaked into the handler.');
    }

    public function test_units_quick_add_json_contract_preserved(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('settings.units.store'), ['unit_name' => 'Dozen', 'status' => 1, 'description' => '']);

        $response->assertOk();
        $response->assertJson(['success' => true, 'name' => 'Dozen']);
        $response->assertJsonStructure(['success', 'id', 'name']);
    }

    /* ─────────────────────────────── TAX ─────────────────────────────── */

    public function test_tax_list_renders_design_system_and_preserves_contracts(): void
    {
        $t1 = DbTax::create(['tax_name' => 'VAT 5%', 'tax' => 5, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        DbTax::create(['tax_name' => 'VAT 10%', 'tax' => 10, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        $group = DbTax::create(['tax_name' => 'Sales Group', 'tax' => 5, 'group_bit' => 1, 'subtax_ids' => (string) $t1->id, 'status' => 1, 'store_id' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.tax'))->assertOk()->getContent();

        $this->assertDesignSystem($html, 'Tax');
        $this->assertRawBaselineGone($html, 'Tax');

        // Preserved field names + hidden group_bit variants.
        $this->assertStringContainsString('name="tax_name"', $html);
        $this->assertStringContainsString('name="tax"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('name="group_bit"', $html);
        $this->assertMatchesRegularExpression('/name="group_bit"[^>]*value="0"/', $html);
        $this->assertMatchesRegularExpression('/name="group_bit"[^>]*value="1"/', $html);
        $this->assertStringContainsString('name="subtax_ids_array[]"', $html);

        // Preserved route targets + Alpine refs.
        $this->assertStringContainsString('action="/settings/tax"', $html);
        $this->assertStringContainsString(":action=\"'/settings/tax/' + editId\"", $html);
        $this->assertStringContainsString(":action=\"'/settings/tax/' + editGroupId\"", $html);
        $this->assertStringContainsString('showAddGroupModal', $html);
        $this->assertStringContainsString('editGroupSubtaxes', $html);
        $this->assertStringContainsString('editGroupSubtaxes.includes(', $html);

        // Sub-tax checkbox source must include ALL individual taxes (not paginated subset).
        $this->assertStringContainsString('VAT 5%', $html);
        $this->assertStringContainsString('VAT 10%', $html);

        // Dead controls removed (both tables).
        $this->assertStringNotContainsString('>Copy<', $html, 'Tax: Copy button should be gone.');
        $this->assertStringNotContainsString('>Excel<', $html, 'Tax: Excel button should be gone.');
        $this->assertStringNotContainsString('>PDF<', $html, 'Tax: PDF button should be gone.');
        $this->assertStringNotContainsString('>Print<', $html, 'Tax: Print button should be gone.');
        $this->assertStringNotContainsString('>CSV<', $html, 'Tax: CSV button should be gone.');
        $this->assertStringNotContainsString('>Cols<', $html, 'Tax: Cols button should be gone.');

        $this->assertSubmitGuard($html, 'Tax');
    }

    /**
     * Extracts the N-th <table> body from the rendered page. The Tax page renders
     * the individual-tax table first, then the tax-group table — but every tax
     * name also appears inside the Add/Edit tax-group modal checkbox sources, so
     * whole-page assertions cannot prove pagination. Scoping to the table region
     * isolates the actual paginated rows.
     */
    private function tableRegion(string $html, int $n): string
    {
        preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $matches);
        return $matches[1][$n - 1] ?? '';
    }

    public function test_tax_both_lists_paginate_independently(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DbTax::create(['tax_name' => sprintf('Tax%02d', $i), 'tax' => 1, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        }
        for ($i = 1; $i <= 12; $i++) {
            DbTax::create(['tax_name' => sprintf('Group%02d', $i), 'tax' => 1, 'group_bit' => 1, 'status' => 1, 'store_id' => 1]);
        }

        // Tax table, page 1 = rows 1-10 (asc); page 2 = rows 11-12.
        $page1Table = $this->tableRegion($this->actingAs($this->user)->get(route('settings.tax', ['limit' => 10]))->getContent(), 1);
        $this->assertStringContainsString('Tax01', $page1Table);
        $this->assertStringContainsString('Tax10', $page1Table);
        $this->assertStringNotContainsString('Tax11', $page1Table, 'Tax table page 1 must not contain Tax11.');
        $this->assertStringNotContainsString('Tax12', $page1Table, 'Tax table page 1 must not contain Tax12.');

        $page2Table = $this->tableRegion($this->actingAs($this->user)->get(route('settings.tax', ['limit' => 10, 'page' => 2]))->getContent(), 1);
        $this->assertStringContainsString('Tax11', $page2Table);
        $this->assertStringContainsString('Tax12', $page2Table);
        $this->assertStringNotContainsString('Tax01', $page2Table, 'Tax table page 2 must not contain Tax01.');
        $this->assertStringNotContainsString('Tax10', $page2Table, 'Tax table page 2 must not contain Tax10.');

        // Group table stays on page 1 when only ?page is set.
        $page1Groups = $this->tableRegion($this->actingAs($this->user)->get(route('settings.tax', ['limit' => 10, 'page' => 2]))->getContent(), 2);
        $this->assertStringContainsString('Group01', $page1Groups, 'Group table must remain on page 1 when only ?page is set.');

        // Group table, page 2 via ?gpage.
        $gpage2Groups = $this->tableRegion($this->actingAs($this->user)->get(route('settings.tax', ['limit' => 10, 'gpage' => 2]))->getContent(), 2);
        $this->assertStringContainsString('Group12', $gpage2Groups, 'Group table page 2 must show the 12th group.');
        $this->assertStringNotContainsString('Group01', $gpage2Groups, 'Group table page 2 must not show the 1st group.');

        // Tax table stays on page 1 when only ?gpage is set.
        $gpage1Taxes = $this->tableRegion($this->actingAs($this->user)->get(route('settings.tax', ['limit' => 10, 'gpage' => 2]))->getContent(), 1);
        $this->assertStringContainsString('Tax01', $gpage1Taxes, 'Tax table must remain on page 1 when only ?gpage is set.');
    }

    public function test_tax_search_filters_both_lists(): void
    {
        DbTax::create(['tax_name' => 'VAT Special', 'tax' => 5, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        DbTax::create(['tax_name' => 'GST Other', 'tax' => 7, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        DbTax::create(['tax_name' => 'VAT Group', 'tax' => 5, 'group_bit' => 1, 'status' => 1, 'store_id' => 1]);
        DbTax::create(['tax_name' => 'GST Group', 'tax' => 7, 'group_bit' => 1, 'status' => 1, 'store_id' => 1]);

        // Baseline (no search): both GST rows present in their tables.
        $noSearch = $this->actingAs($this->user)->get(route('settings.tax'))->getContent();
        $this->assertStringContainsString('GST Other', $this->tableRegion($noSearch, 1), 'No-search: GST Other must be in the tax table.');
        $this->assertStringContainsString('GST Group', $this->tableRegion($noSearch, 2), 'No-search: GST Group must be in the group table.');

        // Search VAT: GST rows must be filtered OUT of their tables (checkbox sources may remain).
        $html = $this->actingAs($this->user)->get(route('settings.tax', ['search' => 'VAT']))->getContent();

        $this->assertStringContainsString('VAT Special', $this->tableRegion($html, 1), 'Search: VAT Special must remain in the tax table.');
        $this->assertStringNotContainsString('GST Other', $this->tableRegion($html, 1), 'Search: GST Other must be filtered out of the tax table.');
        $this->assertStringContainsString('VAT Group', $this->tableRegion($html, 2), 'Search: VAT Group must remain in the group table.');
        $this->assertStringNotContainsString('GST Group', $this->tableRegion($html, 2), 'Search: GST Group must be filtered out of the group table.');
    }

    public function test_tax_edit_modal_handler_is_js_escaped(): void
    {
        DbTax::create(['tax_name' => 'Tax "A" O\'Brien', 'tax' => 5, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.tax'))->assertOk()->getContent();

        $this->assertStringContainsString('\u0027', $html, 'Tax: apostrophe was not JSON-escaped.');
        $this->assertStringContainsString('\u0022', $html, 'Tax: double-quote was not JSON-escaped.');
        $this->assertStringNotContainsString("editName = 'Tax \"A\" O'Brien", $html, 'Tax: raw unescaped quote leaked into the handler.');
    }

    public function test_tax_quick_add_json_contract_preserved(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('settings.tax.store'), ['tax_name' => 'Quick VAT', 'tax' => 8, 'group_bit' => 0, 'status' => 1]);

        $response->assertOk();
        // The controller renders the rate as the raw decimal (SQLite stores 8, not 8.00);
        // the quick-add consumer only reads `name` as display text for the new <option>.
        $response->assertJson(['success' => true, 'name' => 'Quick VAT (8%)']);
        $response->assertJsonStructure(['success', 'id', 'name']);
    }

    /* ──────────────────────────── LANGUAGES ──────────────────────────── */

    public function test_languages_list_renders_design_system_and_preserves_contracts(): void
    {
        DbLanguage::create(['language' => 'English', 'status' => 1]);
        DbLanguage::create(['language' => 'Spanish', 'status' => 0]);

        $html = $this->actingAs($this->user)->get(route('settings.languages.index'))->assertOk()->getContent();

        $this->assertDesignSystem($html, 'Languages');
        $this->assertRawBaselineGone($html, 'Languages');

        // Preserved single-active-language banner + activate wiring.
        $this->assertStringContainsString('Current System Language', $html);
        $this->assertStringContainsString('Switch Active Language', $html);
        $this->assertStringContainsString('openActivateConfirm', $html);
        $this->assertStringContainsString('showConfirmModal', $html);
        $this->assertStringContainsString("+ targetLanguageId + '/activate'", $html);
        $this->assertStringContainsString('currentActiveName', $html);

        // Preserved route targets.
        $this->assertStringContainsString(route('settings.languages.create'), $html);
        $this->assertStringContainsString('settings/languages/', $html);
    }

    public function test_languages_pagination_and_search(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DbLanguage::create(['language' => sprintf('Lang%02d', $i), 'status' => 0]);
        }
        DbLanguage::create(['language' => 'Klingon', 'status' => 1]);

        $html = $this->actingAs($this->user)->get(route('settings.languages.index', ['search' => 'Klingon']))->getContent();
        $this->assertStringContainsString('Klingon', $html);
        $this->assertStringNotContainsString('Lang01', $html, 'Languages: search did not filter.');

        // Pagination: seed 12 inactive rows; page 2 must differ from page 1.
        DbLanguage::query()->where('language', 'Klingon')->delete();
        DbLanguage::query()->delete();
        for ($i = 1; $i <= 12; $i++) {
            DbLanguage::create(['language' => sprintf('Page%02d', $i), 'status' => 0]);
        }
        $p1 = $this->actingAs($this->user)->get(route('settings.languages.index', ['limit' => 10]))->getContent();
        $this->assertStringContainsString('Page01', $p1);
        $this->assertStringNotContainsString('Page12', $p1);
        $p2 = $this->actingAs($this->user)->get(route('settings.languages.index', ['limit' => 10, 'page' => 2]))->getContent();
        $this->assertStringContainsString('Page12', $p2);
        $this->assertStringNotContainsString('Page01', $p2);
    }

    public function test_languages_create_and_edit_pages_use_design_system(): void
    {
        $lang = DbLanguage::create(['language' => 'English', 'status' => 1]);

        $create = $this->actingAs($this->user)->get(route('settings.languages.create'))->assertOk()->getContent();
        $this->assertDesignSystem($create, 'Languages Create');
        $this->assertStringContainsString('name="language"', $create);
        $this->assertStringContainsString('name="status"', $create);
        $this->assertStringContainsString(route('settings.languages.store'), $create);
        $this->assertSubmitGuard($create, 'Languages Create');

        $edit = $this->actingAs($this->user)->get(route('settings.languages.edit', $lang->id))->assertOk()->getContent();
        $this->assertDesignSystem($edit, 'Languages Edit');
        $this->assertStringContainsString('name="language"', $edit);
        // Active language keeps the locked hidden-status field contract.
        $this->assertMatchesRegularExpression('/name="status"[^>]*value="1"/', $edit);
        $this->assertStringContainsString(route('settings.languages.update', $lang->id), $edit);
        $this->assertSubmitGuard($edit, 'Languages Edit');
    }

    /* ──────────────────────────── COUNTRIES ──────────────────────────── */

    public function test_countries_list_renders_design_system_and_preserves_contracts(): void
    {
        $country = DbCountry::create(['country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);

        $html = $this->actingAs($this->user)->get(route('settings.countries'))->assertOk()->getContent();

        $this->assertDesignSystem($html, 'Countries');
        $this->assertRawBaselineGone($html, 'Countries');

        $this->assertStringContainsString(route('settings.countries.add'), $html);
        $this->assertStringContainsString(route('settings.countries.edit', $country->id), $html);
        $this->assertStringContainsString(route('settings.countries.delete', $country->id), $html);
        // The list page carries the server-side search input (the add/edit name="country"
        // contract is asserted on the add/edit page tests).
        $this->assertStringContainsString('name="search"', $html);

        // Hardcoded placeholder pagination removed.
        $this->assertStringNotContainsString('>Prev<', $html, 'Countries: hardcoded Prev button should be replaced by the real paginator.');
        $this->assertStringNotContainsString('>Next<', $html, 'Countries: hardcoded Next button should be replaced by the real paginator.');
    }

    public function test_countries_pagination_and_search(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DbCountry::create(['country' => sprintf('Country%02d', $i), 'status' => 1, 'added_on' => now()]);
        }

        $p1 = $this->actingAs($this->user)->get(route('settings.countries', ['limit' => 10]))->getContent();
        $this->assertStringContainsString('Country01', $p1);
        $this->assertStringNotContainsString('Country12', $p1);
        $this->assertMatchesRegularExpression('/href="[^"]*page=2/', $p1);

        $p2 = $this->actingAs($this->user)->get(route('settings.countries', ['limit' => 10, 'page' => 2]))->getContent();
        $this->assertStringContainsString('Country12', $p2);
        $this->assertStringNotContainsString('Country01', $p2);

        $s = $this->actingAs($this->user)->get(route('settings.countries', ['search' => 'Country05']))->getContent();
        $this->assertStringContainsString('Country05', $s);
        $this->assertStringNotContainsString('Country01', $s, 'Countries: search did not filter.');
    }

    public function test_countries_add_and_edit_pages_use_design_system(): void
    {
        $country = DbCountry::create(['country' => 'Nepal', 'status' => 1, 'added_on' => now()]);

        $add = $this->actingAs($this->user)->get(route('settings.countries.add'))->assertOk()->getContent();
        $this->assertDesignSystem($add, 'Countries Add');
        $this->assertStringContainsString('name="country"', $add);
        $this->assertStringContainsString('name="status"', $add);
        $this->assertStringContainsString(route('settings.countries.store'), $add);
        $this->assertSubmitGuard($add, 'Countries Add');

        $edit = $this->actingAs($this->user)->get(route('settings.countries.edit', $country->id))->assertOk()->getContent();
        $this->assertDesignSystem($edit, 'Countries Edit');
        $this->assertStringContainsString('name="country"', $edit);
        $this->assertStringContainsString(route('settings.countries.update', $country->id), $edit);
        $this->assertSubmitGuard($edit, 'Countries Edit');
    }

    /* ────────────────────────────── STATES ────────────────────────────── */

    public function test_states_list_renders_design_system_and_preserves_contracts(): void
    {
        $country = DbCountry::create(['country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        $state = DbState::create(['state' => 'Dhaka', 'country_id' => $country->id, 'country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);

        $html = $this->actingAs($this->user)->get(route('settings.states'))->assertOk()->getContent();

        $this->assertDesignSystem($html, 'States');
        $this->assertRawBaselineGone($html, 'States');

        $this->assertStringContainsString(route('settings.states.add'), $html);
        $this->assertStringContainsString(route('settings.states.edit', $state->id), $html);
        $this->assertStringContainsString(route('settings.states.delete', $state->id), $html);

        $this->assertStringNotContainsString('>Prev<', $html, 'States: hardcoded Prev button should be replaced by the real paginator.');
        $this->assertStringNotContainsString('>Next<', $html, 'States: hardcoded Next button should be replaced by the real paginator.');
    }

    public function test_states_pagination_and_search(): void
    {
        $country = DbCountry::create(['country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        for ($i = 1; $i <= 12; $i++) {
            DbState::create(['state' => sprintf('State%02d', $i), 'country_id' => $country->id, 'country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        }

        $p1 = $this->actingAs($this->user)->get(route('settings.states', ['limit' => 10]))->getContent();
        $this->assertStringContainsString('State01', $p1);
        $this->assertStringNotContainsString('State12', $p1);
        $this->assertMatchesRegularExpression('/href="[^"]*page=2/', $p1);

        $p2 = $this->actingAs($this->user)->get(route('settings.states', ['limit' => 10, 'page' => 2]))->getContent();
        $this->assertStringContainsString('State12', $p2);
        $this->assertStringNotContainsString('State01', $p2);

        $s = $this->actingAs($this->user)->get(route('settings.states', ['search' => 'State05']))->getContent();
        $this->assertStringContainsString('State05', $s);
        $this->assertStringNotContainsString('State01', $s, 'States: search did not filter.');
    }

    public function test_states_add_and_edit_pages_use_design_system_and_preserve_select_contract(): void
    {
        $country = DbCountry::create(['country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        $state = DbState::create(['state' => 'Dhaka', 'country_id' => $country->id, 'country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);

        $add = $this->actingAs($this->user)->get(route('settings.states.add'))->assertOk()->getContent();
        $this->assertDesignSystem($add, 'States Add');
        $this->assertStringContainsString('name="state"', $add);
        $this->assertStringContainsString('name="country_id"', $add);
        $this->assertStringContainsString('name="status"', $add);
        $this->assertStringContainsString(route('settings.states.store'), $add);
        $this->assertSubmitGuard($add, 'States Add');

        $edit = $this->actingAs($this->user)->get(route('settings.states.edit', $state->id))->assertOk()->getContent();
        $this->assertDesignSystem($edit, 'States Edit');
        $this->assertStringContainsString('name="state"', $edit);
        $this->assertStringContainsString('name="country_id"', $edit);
        $this->assertStringContainsString(route('settings.states.update', $state->id), $edit);
        $this->assertSubmitGuard($edit, 'States Edit');
    }

    /* ─────────────────────────── CONSUMERS ─────────────────────────── */

    public function test_consumers_read_same_ids_and_field_names(): void
    {
        // Units consumed by items (unit_id FK -> unit_name).
        $unit = DbUnit::create(['unit_name' => 'Piece', 'status' => 1, 'store_id' => 1]);
        $tax = DbTax::create(['tax_name' => 'GST', 'tax' => 5, 'group_bit' => 0, 'status' => 1, 'store_id' => 1]);
        $item = DbItem::create([
            'item_name' => 'Consumer Item', 'item_code' => 'CI-001',
            'unit_id' => $unit->id, 'tax_id' => $tax->id,
            'purchase_price' => 1, 'sales_price' => 2, 'stock' => 1, 'status' => 1, 'store_id' => 1,
        ]);
        $this->assertSame('Piece', $item->fresh()->unit->unit_name);
        $this->assertSame('GST', $item->fresh()->tax->tax_name);

        // Countries/States consumed by customers (country_id/state_id FK -> country/state).
        $country = DbCountry::create(['country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        $state = DbState::create(['state' => 'Dhaka', 'country_id' => $country->id, 'country' => 'Bangladesh', 'status' => 1, 'added_on' => now()]);
        $customer = DbCustomer::create([
            'customer_name' => 'C', 'customer_code' => 'C-001',
            'country_id' => $country->id, 'state_id' => $state->id,
            'status' => 1, 'store_id' => 1,
        ]);
        $customer = $customer->fresh();
        $this->assertSame('Bangladesh', $customer->country->country);
        $this->assertSame('Dhaka', $customer->state->state);

        // Language consumer: single-active-language invariant still drives the store.
        $lang = DbLanguage::create(['language' => 'English', 'status' => 0]);
        DbLanguage::activateLanguage($lang->id, 1);
        $this->assertSame(1, $lang->fresh()->status);
        $this->assertSame($lang->id, DbStore::find(1)->language_id);
    }
}
