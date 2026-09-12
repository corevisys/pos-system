<?php

namespace Tests\Feature;

use App\Models\DbStore;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-1 — Server-side permission enforcement for the 8 previously-unprotected
 * transactional controllers (POS, Sale, SalesReturn, Purchase, Quotation,
 * Customer, Supplier, Report).
 *
 * Covers the "S" (sensitive) rows from the discovery Part B inventory: each
 * sensitive route must 403 for a role WITHOUT the slug and be reachable WITH it.
 * A non-super-admin role (is_super_admin = false, forced high id) is always used
 * so the assertion can only come from the injected gate — never from the
 * super-admin short-circuit.
 */
class TransactionalModulePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Perm Store', 'status' => 1, 'mobile' => '01711111111']);
    }

    /**
     * A regular (non-super-admin) user holding exactly the given slugs.
     * is_super_admin is explicitly false — id/name no longer confer bypass.
     */
    private function userWith(array $permissions): User
    {
        $role = DbRole::forceCreate([
            'id' => 200 + DbRole::max('id'),
            'role_name' => 'Perm Role ' . uniqid(),
            'status' => 1,
            'store_id' => 1,
            'is_super_admin' => false,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => $permissions,
        ]);

        return User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'role_name' => $role->role_name,
        ]);
    }

    /* ───────────────────────── POS / Sales ───────────────────────── */

    public function test_pos_terminal_denied_without_sales_add(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('sales.pos'))->assertForbidden();
    }

    public function test_pos_terminal_allowed_with_sales_add(): void
    {
        $user = $this->userWith(['sales_add']);
        $this->actingAs($user)->get(route('sales.pos'))->assertOk();
    }

    public function test_pos_checkout_denied_without_sales_add(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)
            ->postJson(route('sales.pos.store'), [])
            ->assertForbidden();
    }

    public function test_pos_search_items_json_gate_returns_403(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)
            ->getJson(route('sales.pos.search.items', ['q' => 'x']))
            ->assertForbidden();
    }

    public function test_sales_list_denied_without_sales_view(): void
    {
        $user = $this->userWith(['sales_add']);
        $this->actingAs($user)->get(route('sales.list'))->assertForbidden();
    }

    public function test_sales_list_allowed_with_sales_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('sales.list'))->assertOk();
    }

    public function test_add_sale_form_denied_without_sales_add(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('sales.add'))->assertForbidden();
    }

    public function test_held_sales_list_denied_without_sales_view(): void
    {
        $user = $this->userWith(['sales_add']);
        $this->actingAs($user)->get(route('sales.hold.list'))->assertForbidden();
    }

    public function test_sales_payments_list_denied_without_sales_payment_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('sales.payments'))->assertForbidden();
    }

    public function test_emi_list_denied_without_sales_view(): void
    {
        $user = $this->userWith(['sales_add']);
        $this->actingAs($user)->get(route('sales.emi.list'))->assertForbidden();
    }

    /* ───────────────────────── Sales Return ───────────────────────── */

    public function test_sales_return_list_denied_without_sales_return_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('sales.returns'))->assertForbidden();
    }

    public function test_sales_return_list_allowed_with_sales_return_view(): void
    {
        $user = $this->userWith(['sales_return_view']);
        $this->actingAs($user)->get(route('sales.returns'))->assertOk();
    }

    public function test_sales_return_store_denied_without_sales_return_add(): void
    {
        $user = $this->userWith(['sales_return_view']);
        $this->actingAs($user)
            ->post(route('sales.return.store'), [])
            ->assertForbidden();
    }

    public function test_sales_return_delete_denied_without_sales_return_delete(): void
    {
        $user = $this->userWith(['sales_return_view', 'sales_return_add']);
        $this->actingAs($user)
            ->delete(route('sales.return.delete', 999999))
            ->assertForbidden();
    }

    /* ───────────────────────── Purchase ───────────────────────── */

    public function test_purchase_list_denied_without_purchase_view(): void
    {
        $user = $this->userWith(['purchase_add']);
        $this->actingAs($user)->get(route('purchase.list'))->assertForbidden();
    }

    public function test_purchase_list_allowed_with_purchase_view(): void
    {
        $user = $this->userWith(['purchase_view']);
        $this->actingAs($user)->get(route('purchase.list'))->assertOk();
    }

    public function test_new_purchase_form_denied_without_purchase_add(): void
    {
        $user = $this->userWith(['purchase_view']);
        $this->actingAs($user)->get(route('purchase.new'))->assertForbidden();
    }

    public function test_purchase_store_denied_without_purchase_add(): void
    {
        $user = $this->userWith(['purchase_view']);
        $this->actingAs($user)
            ->post(route('purchase.store'), [])
            ->assertForbidden();
    }

    public function test_purchase_search_items_json_gate_returns_403(): void
    {
        $user = $this->userWith(['purchase_view']);
        $this->actingAs($user)
            ->getJson(route('purchase.search.items', ['q' => 'x']))
            ->assertForbidden();
    }

    /**
     * A FormRequest's authorize() runs BEFORE rules(). If it returned true
     * unconditionally, an unauthorized user would receive *validation errors*
     * (potentially leaking store-scoped existence info) instead of a 403.
     * Assert the 403 fires FIRST, with no validation error surface.
     */
    public function test_purchase_store_returns_403_not_validation_errors_without_permission(): void
    {
        $user = $this->userWith(['purchase_view']);

        $response = $this->actingAs($user)->post(route('purchase.store'), []);

        $response->assertForbidden();
        // No validation error bag may be produced — the 403 must fire first.
        $response->assertSessionHasNoErrors();
    }

    public function test_purchase_update_returns_403_not_validation_errors_without_permission(): void
    {
        $user = $this->userWith(['purchase_view']);

        $response = $this->actingAs($user)->post(route('purchase.update', 999999), []);

        $response->assertForbidden();
    }

    public function test_purchase_quick_item_store_gated_on_items_add(): void
    {
        // Has every purchase slug but NOT items_add — creating an item must still 403.
        $user = $this->userWith(['purchase_add', 'purchase_view']);
        $this->actingAs($user)
            ->postJson(route('purchase.quick.item.store'), [])
            ->assertForbidden();
    }

    public function test_purchase_return_list_denied_without_purchase_return_view(): void
    {
        $user = $this->userWith(['purchase_view']);
        $this->actingAs($user)->get(route('purchase.returns'))->assertForbidden();
    }

    public function test_purchase_delete_denied_without_purchase_delete(): void
    {
        $user = $this->userWith(['purchase_view', 'purchase_edit', 'purchase_add']);
        $this->actingAs($user)
            ->delete(route('purchase.delete', 999999))
            ->assertForbidden();
    }

    public function test_purchase_payment_denied_without_purchase_payment_add(): void
    {
        $user = $this->userWith(['purchase_view', 'purchase_add']);
        $this->actingAs($user)
            ->post(route('purchase.payment.store', 999999), [])
            ->assertForbidden();
    }

    /* ───────────────────────── Quotation ───────────────────────── */

    public function test_quotation_list_denied_without_quotation_view(): void
    {
        $user = $this->userWith(['quotation_add']);
        $this->actingAs($user)->get(route('quotation.list'))->assertForbidden();
    }

    public function test_quotation_list_allowed_with_quotation_view(): void
    {
        $user = $this->userWith(['quotation_view']);
        $this->actingAs($user)->get(route('quotation.list'))->assertOk();
    }

    public function test_new_quotation_form_denied_without_quotation_add(): void
    {
        $user = $this->userWith(['quotation_view']);
        $this->actingAs($user)->get(route('quotation.new'))->assertForbidden();
    }

    public function test_quotation_delete_denied_without_quotation_delete(): void
    {
        $user = $this->userWith(['quotation_view', 'quotation_edit']);
        $this->actingAs($user)
            ->delete(route('quotation.delete', 999999))
            ->assertForbidden();
    }

    public function test_quotation_convert_requires_sales_add(): void
    {
        // Holds full quotation rights but no sales_add — conversion must 403
        // because it creates a real sale with stock/ledger effects.
        $user = $this->userWith(['quotation_view', 'quotation_add', 'quotation_edit']);
        $this->actingAs($user)
            ->post(route('quotation.convert', 999999), [])
            ->assertForbidden();
    }

    /* ───────────────────────── Customers ───────────────────────── */

    public function test_customers_list_denied_without_customers_view(): void
    {
        $user = $this->userWith(['customers_add']);
        $this->actingAs($user)->get(route('contacts.customers.list'))->assertForbidden();
    }

    public function test_customers_list_allowed_with_customers_view(): void
    {
        $user = $this->userWith(['customers_view']);
        $this->actingAs($user)->get(route('contacts.customers.list'))->assertOk();
    }

    public function test_add_customer_form_denied_without_customers_add(): void
    {
        $user = $this->userWith(['customers_view']);
        $this->actingAs($user)->get(route('contacts.customers.add'))->assertForbidden();
    }

    public function test_customer_delete_denied_without_customers_delete(): void
    {
        $user = $this->userWith(['customers_view', 'customers_add', 'customers_edit']);
        $this->actingAs($user)
            ->delete(route('contacts.customers.delete', 999999))
            ->assertForbidden();
    }

    public function test_customer_import_denied_without_import_customers(): void
    {
        $user = $this->userWith(['customers_view', 'customers_add']);
        $this->actingAs($user)->get(route('contacts.customers.import'))->assertForbidden();
    }

    /* ───────────────────────── Suppliers ───────────────────────── */

    public function test_suppliers_list_denied_without_suppliers_view(): void
    {
        $user = $this->userWith(['suppliers_add']);
        $this->actingAs($user)->get(route('contacts.suppliers.list'))->assertForbidden();
    }

    public function test_suppliers_list_allowed_with_suppliers_view(): void
    {
        $user = $this->userWith(['suppliers_view']);
        $this->actingAs($user)->get(route('contacts.suppliers.list'))->assertOk();
    }

    public function test_add_supplier_form_denied_without_suppliers_add(): void
    {
        $user = $this->userWith(['suppliers_view']);
        $this->actingAs($user)->get(route('contacts.suppliers.add'))->assertForbidden();
    }

    public function test_supplier_delete_denied_without_suppliers_delete(): void
    {
        $user = $this->userWith(['suppliers_view', 'suppliers_add', 'suppliers_edit']);
        $this->actingAs($user)
            ->delete(route('contacts.suppliers.delete', 999999))
            ->assertForbidden();
    }

    public function test_supplier_import_denied_without_import_suppliers(): void
    {
        $user = $this->userWith(['suppliers_view', 'suppliers_add']);
        $this->actingAs($user)->get(route('contacts.suppliers.import'))->assertForbidden();
    }

    /* ───────────────────────── Reports (single coarse gate) ───────────────────────── */

    public function test_reports_profit_loss_denied_without_reports_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('reports.profit_loss'))->assertForbidden();
    }

    public function test_reports_profit_loss_allowed_with_reports_view(): void
    {
        $user = $this->userWith(['reports_view']);
        $this->actingAs($user)->get(route('reports.profit_loss'))->assertOk();
    }

    public function test_reports_data_endpoint_denied_without_reports_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)
            ->getJson(route('reports.profit_loss_data'))
            ->assertForbidden();
    }

    public function test_reports_cash_flow_denied_without_reports_view(): void
    {
        $user = $this->userWith(['sales_view']);
        $this->actingAs($user)->get(route('reports.cash_flow'))->assertForbidden();
    }

    public function test_reports_stock_allowed_with_reports_view(): void
    {
        $user = $this->userWith(['reports_view']);
        $this->actingAs($user)->get(route('reports.stock'))->assertOk();
    }
}
