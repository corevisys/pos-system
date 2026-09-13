<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\CashDrawerReconciliation;
use App\Models\DbCustomer;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 6 coverage — shared export mechanism (EnsureReportExport middleware).
 *
 * Proves a Store-A user's export contains ONLY Store-A rows, for representative
 * reports across different data families: transaction-list (Sales),
 * payment (Sales Payments), GST (GSTR-1), tax (Sales Tax) and Cash Reconciliation.
 */
class ReportSharedExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected string $today;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Store A', 'status' => 1]);
        DbStore::firstOrCreate(['id' => 2], ['store_name' => 'Store B', 'status' => 1]);

        DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->userA = User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        $this->userB = User::factory()->create(['store_id' => 2, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        $this->today = now()->toDateString();

        $this->seedStore(1, 'STORE-A');
        $this->seedStore(2, 'STORE-B');
    }

    private function seedStore(int $storeId, string $tag): void
    {
        $wh = DbWarehouse::create(['store_id' => $storeId, 'warehouse_name' => "WH {$tag}", 'status' => 1]);
        $cust = DbCustomer::create([
            'store_id' => $storeId, 'customer_name' => "Cust {$tag}", 'customer_code' => "C-{$tag}",
            'mobile' => '0178' . str_pad((string) $storeId, 8, '0', STR_PAD_LEFT), 'status' => 1, 'delete_bit' => 0,
        ]);
        $sale = DbSale::create([
            'store_id' => $storeId, 'warehouse_id' => $wh->id, 'customer_id' => $cust->id,
            'sales_code' => "SA-{$tag}", 'sales_date' => $this->today, 'sales_status' => 'Final',
            'subtotal' => 111.00, 'grand_total' => 111.00, 'paid_amount' => 50.00, 'status' => 1,
        ]);
        DbSalePayment::create([
            'store_id' => $storeId, 'sales_id' => $sale->id, 'customer_id' => $cust->id,
            'payment_code' => "SP-{$tag}", 'payment_date' => $this->today, 'payment_type' => 'Cash',
            'payment' => 50.00, 'status' => 1,
        ]);

        // Cash Reconciliation row so reports.cash_reconciliation_data has data to export.
        $account = AcAccount::create([
            'store_id' => $storeId, 'account_name' => "Cash {$tag}", 'account_code' => "ACC-{$tag}",
            'status' => 1, 'delete_bit' => 0,
        ]);
        $user = $storeId === 1 ? $this->userA : $this->userB;
        CashDrawerReconciliation::create([
            'store_id' => $storeId,
            'reconciliation_code' => "CDR-{$tag}",
            'account_id' => $account->id,
            'user_id' => $user->id,
            'reconciliation_date' => $this->today,
            'opening_balance' => 10.00,
            'expected_closing_balance' => 60.00,
            'counted_amount' => 60.00,
            'variance' => 0.00,
            'status' => 'Closed',
            'delete_bit' => 0,
        ]);
    }

    /**
     * @dataProvider exportRoutes
     */
    public function test_store_a_csv_export_contains_only_store_a_rows(string $route): void
    {
        $response = $this->actingAs($this->userA)->get(route($route, [
            'start_date' => $this->today,
            'end_date' => $this->today,
            'export' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('STORE-A', $body, "{$route}: Store A rows must be present.");
        $this->assertStringNotContainsString('STORE-B', $body, "{$route}: Store B rows must NOT leak into Store A's export.");
    }

    public static function exportRoutes(): array
    {
        return [
            'transaction-list (Sales)'      => ['reports.sales_data'],
            'payment (Sales Payments)'      => ['reports.sales_payments_data'],
            'GST (GSTR-1)'                  => ['reports.gstr1_data'],
            'tax (Sales Tax)'               => ['reports.sales_tax_data'],
            'cash reconciliation'           => ['reports.cash_reconciliation_data'],
        ];
    }

    public function test_print_export_returns_html_view(): void
    {
        $response = $this->actingAs($this->userA)->get(route('reports.sales_data', [
            'start_date' => $this->today,
            'end_date' => $this->today,
            'export' => 'pdf',
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Print', $html);
        $this->assertStringContainsString('STORE-A', $html);
        $this->assertStringNotContainsString('STORE-B', $html);
    }

    public function test_export_is_not_triggered_without_export_param(): void
    {
        $response = $this->actingAs($this->userA)->getJson(route('reports.sales_data', [
            'start_date' => $this->today,
            'end_date' => $this->today,
        ]));
        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    public function test_profit_loss_export_produces_a_real_file(): void
    {
        $response = $this->actingAs($this->userA)->get(route('reports.profit_loss_data', [
            'start_date' => $this->today,
            'end_date' => $this->today,
            'export' => 'csv',
        ]));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }
}
