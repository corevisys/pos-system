<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 2 coverage — the 4 report methods that load User:: for a dropdown.
 *
 * User has NO StoreScoped trait (auth intentionally relies on unscoped User
 * queries), so these report-specific loads must add where('store_id', current_store_id()).
 *
 * One test per method + a control proving Store A's own users still appear.
 */
class ReportUserDropdownStoreScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $storeAUser;
    protected User $storeAOther;
    protected User $storeBUser;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Store A', 'status' => 1]);
        DbStore::firstOrCreate(['id' => 2], ['store_name' => 'Store B', 'status' => 1]);

        // Acting user: Store A super-admin (role name triggers the test-only
        // seedSuperAdminByName opt-in set in Tests\TestCase::setUp()).
        $roleA = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->storeAUser = User::factory()->create([
            'store_id' => 1,
            'role_id' => $roleA->id,
            'role_name' => 'Super Admin',
            'username' => 'storeA-boss',
            'status' => 1,
        ]);
        $this->storeAOther = User::factory()->create([
            'store_id' => 1,
            'role_id' => $roleA->id,
            'role_name' => 'Staff',
            'username' => 'storeA-staff',
            'status' => 1,
        ]);
        $this->storeBUser = User::factory()->create([
            'store_id' => 2,
            'role_id' => $roleA->id,
            'role_name' => 'Staff',
            'username' => 'storeB-staff',
            'status' => 1,
        ]);
    }

    private function assertUsersScoped(string $routeName): void
    {
        $response = $this->actingAs($this->storeAUser)->get(route($routeName));
        $response->assertOk();

        $usernames = $response->viewData('users')->pluck('username')->all();

        $this->assertContains('storeA-boss', $usernames, "{$routeName}: Store A's own users must appear.");
        $this->assertContains('storeA-staff', $usernames, "{$routeName}: Store A's own users must appear.");
        $this->assertNotContains('storeB-staff', $usernames, "{$routeName}: Store B's user must NOT leak in.");
    }

    public function test_seller_points_report_user_dropdown_is_store_scoped(): void
    {
        $this->assertUsersScoped('reports.seller_points');
    }

    public function test_purchase_payments_report_user_dropdown_is_store_scoped(): void
    {
        $this->assertUsersScoped('reports.purchase_payments');
    }

    public function test_sales_payments_report_user_dropdown_is_store_scoped(): void
    {
        $this->assertUsersScoped('reports.sales_payments');
    }

    public function test_cash_reconciliation_report_user_collection_is_store_scoped(): void
    {
        $this->assertUsersScoped('reports.cash_reconciliation');
    }
}
