<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 4 coverage — shared report filter validation.
 *
 * Proves malformed dates and non-existent id filters produce a clean 422
 * validation error (not a 500 / uncaught Carbon exception), while valid
 * filters still return correct data.
 */
class ReportFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Validation Store', 'status' => 1]);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->user = User::factory()->create([
            'store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
        ]);
    }

    public function test_malformed_date_is_rejected_on_financial_tax_and_list_reports(): void
    {
        // Financial (P&L), tax (Sales Tax), transaction-list (Sales Payments).
        foreach (['reports.profit_loss_data', 'reports.sales_tax_data', 'reports.sales_payments_data'] as $route) {
            $response = $this->actingAs($this->user)->getJson(route($route, [
                'start_date' => 'not-a-date',
                'end_date' => 'also-bad',
            ]));

            $response->assertStatus(422, "{$route} must reject a malformed date with 422.");
            $response->assertJsonPath('status', 'error');
            $this->assertArrayHasKey('start_date', $response->json('errors'), "{$route} must report the start_date error.");
        }
    }

    public function test_non_existent_id_filter_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('reports.sales_summary_data', [
            'warehouse_id' => 999999,
        ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('warehouse_id', $response->json('errors'));

        $response2 = $this->actingAs($this->user)->getJson(route('reports.cash_flow_data', [
            'account_id' => 999999,
        ]));
        $response2->assertStatus(422);
        $this->assertArrayHasKey('account_id', $response2->json('errors'));
    }

    public function test_non_integer_id_filter_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('reports.sales_payment_data', [
            'customer_id' => 'abc',
        ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('customer_id', $response->json('errors'));
    }

    public function test_start_after_end_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('reports.profit_loss_data', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-05-01',
        ]));
        $response->assertStatus(422);
        $this->assertArrayHasKey('end_date', $response->json('errors'));
    }

    public function test_valid_filters_still_return_data(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('reports.sales_tax_data', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]));
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }
}
