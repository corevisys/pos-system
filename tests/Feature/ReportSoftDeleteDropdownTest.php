<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\CashDrawerReconciliation;
use App\Models\DbCustomer;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PHASE 3 coverage — delete_bit=0 on the 13 report consumers that read
 * soft-deletable tables:
 *   12 customer/supplier dropdowns + getCashReconciliationData()'s base query.
 *
 * Per the Expenses gap-closing standard, this proves EXACT before/after
 * behaviour: a soft-deleted row is still-included BEFORE the flip and EXCLUDED
 * AFTER, while an active sibling remains included throughout.
 */
class ReportSoftDeleteDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'SD Store', 'status' => 1]);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->user = User::factory()->create([
            'store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
        ]);

        // Cache is not reset by RefreshDatabase — clear the one cached dropdown
        // this test exercises so it re-reads the freshly seeded rows.
        Cache::flush();
    }

    public function test_customer_dropdowns_exclude_soft_deleted_customers(): void
    {
        $active = DbCustomer::create([
            'store_id' => 1, 'customer_name' => 'Active Cust', 'customer_code' => 'CUST-SD-ACTIVE',
            'mobile' => '01781000001', 'status' => 1, 'delete_bit' => 0,
        ]);
        $deleted = DbCustomer::create([
            'store_id' => 1, 'customer_name' => 'Deleted Cust', 'customer_code' => 'CUST-SD-DELETED',
            'mobile' => '01781000002', 'status' => 1, 'delete_bit' => 1,
        ]);

        // 7 customer-dropdown consumers.
        $routes = [
            'reports.sales_payment',
            'reports.customer_orders',
            'reports.sales_gst',
            'reports.sales',
            'reports.sales_return',
            'reports.sales_summary',
            'reports.sales_payments',
        ];

        foreach ($routes as $route) {
            if ($route === 'reports.sales_summary') {
                Cache::flush(); // ensure the cached list is rebuilt with current rows
            }
            $response = $this->actingAs($this->user)->get(route($route));
            $response->assertOk();

            $ids = $response->viewData('customers')->pluck('id')->all();
            $this->assertContains($active->id, $ids, "{$route}: active customer must be present.");
            $this->assertNotContains($deleted->id, $ids, "{$route}: soft-deleted customer must be excluded.");
        }
    }

    public function test_supplier_dropdowns_exclude_soft_deleted_suppliers(): void
    {
        $active = DbSupplier::create([
            'store_id' => 1, 'supplier_name' => 'Active Supp', 'supplier_code' => 'SUP-SD-ACTIVE',
            'mobile' => '01782000001', 'status' => 1, 'delete_bit' => 0,
        ]);
        $deleted = DbSupplier::create([
            'store_id' => 1, 'supplier_name' => 'Deleted Supp', 'supplier_code' => 'SUP-SD-DELETED',
            'mobile' => '01782000002', 'status' => 1, 'delete_bit' => 1,
        ]);

        // 5 supplier-dropdown consumers.
        $routes = [
            'reports.purchase_gst',
            'reports.supplier_items',
            'reports.purchase',
            'reports.purchase_return',
            'reports.purchase_payments',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->user)->get(route($route));
            $response->assertOk();

            $ids = $response->viewData('suppliers')->pluck('id')->all();
            $this->assertContains($active->id, $ids, "{$route}: active supplier must be present.");
            $this->assertNotContains($deleted->id, $ids, "{$route}: soft-deleted supplier must be excluded.");
        }
    }

    public function test_cash_reconciliation_records_exclude_soft_deleted_rows(): void
    {
        $today = now()->toDateString();

        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'SD Cash Account',
            'account_code' => 'ACC-SD-1',
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $active = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'CDR-SD-ACTIVE',
            'account_id' => $account->id,
            'user_id' => $this->user->id,
            'reconciliation_date' => $today,
            'opening_balance' => 100.00,
            'expected_closing_balance' => 150.00,
            'counted_amount' => 150.00,
            'variance' => 0.00,
            'status' => 'Closed',
            'delete_bit' => 0,
        ]);
        $deleted = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'CDR-SD-DELETED',
            'account_id' => $account->id,
            'user_id' => $this->user->id,
            'reconciliation_date' => $today,
            'opening_balance' => 900.00,
            'expected_closing_balance' => 900.00,
            'counted_amount' => 900.00,
            'variance' => 0.00,
            'status' => 'Closed',
            'delete_bit' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.cash_reconciliation_data', [
            'start_date' => $today,
            'end_date' => $today,
        ]));
        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('CDR-SD-ACTIVE', $codes, 'Active reconciliation must be present.');
        $this->assertNotContains('CDR-SD-DELETED', $codes, 'Soft-deleted reconciliation must be excluded.');

        // Footer totals must be computed from the active row only (150, not 1050).
        $this->assertSame(150.0, (float) $response->json('summary.total_expected'));
    }
}
