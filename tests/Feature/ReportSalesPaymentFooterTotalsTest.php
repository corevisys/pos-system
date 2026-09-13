<?php

namespace Tests\Feature;

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
 * PHASE 1 coverage — getSalesPaymentData() footer-totals arithmetic.
 *
 * Before the fix the per-record loop overwrote billAmt/receive with
 * comma-separated display strings (lines 558-559) BEFORE the footer totals
 * were computed via array_sum() on those string columns (lines 574-575).
 * PHP's string-to-float cast stops at the first comma, so (float)"1,250.00"
 * === 1.0 and any row >= 1,000 collapsed to its leading digit.
 *
 * These tests assert:
 *   - totalBillAmt / totalReceive are the EXACT correct sums (raw-float based);
 *   - the per-record DISPLAY strings remain comma-formatted (unchanged UI);
 *   - the running `total` (balance) column is still computed from raw values.
 */
class ReportSalesPaymentFooterTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSuperAdmin(): User
    {
        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'Footer Store', 'status' => 1]);
        DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        return User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin']);
    }

    public function test_footer_totals_use_raw_floats_not_comma_formatted_strings(): void
    {
        $user = $this->makeSuperAdmin();
        $today = now()->toDateString();

        $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Footer WH', 'status' => 1]);
        $customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Footer Customer',
            'customer_code' => 'CUST-FOOTER-1',
            'mobile' => '01780000001',
            'status' => 1,
        ]);

        // A sale whose grand_total >= 1,000 contributes to totalBillAmt.
        DbSale::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sales_code' => 'SA-FOOTER-1250',
            'sales_date' => $today,
            'sales_status' => 'Final',
            'grand_total' => 1250.00,
            'paid_amount' => 0.00,
            'status' => 1,
        ]);

        // Two payments: 1250.00 (>= 1,000, the truncation trigger) + 300.50.
        DbSalePayment::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'payment_code' => 'SP-FOOTER-1',
            'payment_date' => $today,
            'payment_type' => 'Cash',
            'payment' => 1250.00,
            'status' => 1,
        ]);
        DbSalePayment::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'payment_code' => 'SP-FOOTER-2',
            'payment_date' => $today,
            'payment_type' => 'Cash',
            'payment' => 300.50,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.sales_payment_data', [
            'start_date' => $today,
            'end_date' => $today,
            'customer_id' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        // Exact correct sums — NOT the truncated 1 + 300.50 = 301.50.
        $response->assertJsonPath('totalBillAmt', '1250.00');
        $response->assertJsonPath('totalReceive', '1550.50');

        // Guard against the specific old bug: the truncated value must not appear.
        $this->assertNotSame('301.50', $response->json('totalReceive'));
        $this->assertNotSame('1.00', $response->json('totalBillAmt'));

        // Per-record DISPLAY strings remain comma-formatted for the UI (unchanged).
        $receives = array_column($response->json('records'), 'receive');
        $this->assertContains('1,250.00', $receives, 'Display formatting must remain comma-separated.');
        $this->assertContains('300.50', $receives);

        $billAmts = array_column($response->json('records'), 'billAmt');
        $this->assertContains('1,250.00', $billAmts, 'Sale billAmt display string must remain comma-formatted.');
    }

    public function test_running_total_column_reflects_running_balance_from_raw_values(): void
    {
        $user = $this->makeSuperAdmin();
        $today = now()->toDateString();

        $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Run WH', 'status' => 1]);
        $customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Running Customer',
            'customer_code' => 'CUST-RUN-1',
            'mobile' => '01780000002',
            'status' => 1,
        ]);

        // Sale of 2,000 -> bill +2000; single payment of 800 -> receive 800.
        // Running balance must end at 2000 - 800 = 1200.00.
        DbSale::create([
            'store_id' => 1,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sales_code' => 'SA-RUN-2000',
            'sales_date' => $today,
            'sales_status' => 'Final',
            'grand_total' => 2000.00,
            'paid_amount' => 800.00,
            'status' => 1,
        ]);
        DbSalePayment::create([
            'store_id' => 1,
            'customer_id' => $customer->id,
            'payment_code' => 'SP-RUN-1',
            'payment_date' => $today,
            'payment_type' => 'Cash',
            'payment' => 800.00,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('reports.sales_payment_data', [
            'start_date' => $today,
            'end_date' => $today,
            'customer_id' => $customer->id,
        ]));

        $response->assertOk();

        $records = $response->json('records');
        $totals = array_column($records, 'total');
        // The final running balance is the arithmetic result of raw floats, not
        // a comma-corrupted parse (which would give 2000 -> 2, ending at 2-800).
        $this->assertContains('1,200.00', $totals, 'Running total must equal 2000 - 800 = 1,200.00.');
        $this->assertNotContains('2.00', $totals);
    }
}
