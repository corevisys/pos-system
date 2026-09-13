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
 * PHASE 5 coverage — placeholder/hardcoded-data decisions.
 *
 *  item 8  — P&L's static "+12.5% vs prev" is removed.
 *  item 9  — GSTR-1/GSTR-2 no longer emit confident "0.00" cgst/sgst/igst.
 *  item 10 — Sales Report's 'method' is derived (N/A / single type / Mixed).
 *  item 11 — P&L "Custom Range" reveals date inputs and honours them.
 */
class ReportPhase5PlaceholderDecisionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DbWarehouse $warehouse;
    protected DbCustomer $customer;
    protected string $today;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'P5 Store', 'status' => 1]);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->user = User::factory()->create([
            'store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
        ]);

        $this->warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'P5 WH', 'status' => 1]);
        $this->customer = DbCustomer::create([
            'store_id' => 1, 'customer_name' => 'P5 Cust', 'customer_code' => 'CUST-P5',
            'mobile' => '01783000001', 'status' => 1, 'delete_bit' => 0,
        ]);
        $this->today = now()->toDateString();
    }

    private function makeSale(string $code, float $total = 100.00): DbSale
    {
        return DbSale::create([
            'store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'sales_code' => $code,
            'sales_date' => $this->today,
            'sales_status' => 'Final',
            'subtotal' => $total,
            'grand_total' => $total,
            'paid_amount' => 0.00,
            'status' => 1,
        ]);
    }

    public function test_item8_profit_loss_view_no_longer_shows_static_trend_percentage(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.profit_loss'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('+12.5% vs prev', $html, 'The fake hardcoded trend must be gone.');
        $this->assertStringNotContainsString('+12.5%', $html);
    }

    public function test_item9_gstr1_and_gstr2_no_longer_emit_zero_cgst_sgst_igst(): void
    {
        $this->makeSale('SA-P5-GSTR1');

        $res1 = $this->actingAs($this->user)->getJson(route('reports.gstr1_data', [
            'start_date' => $this->today, 'end_date' => $this->today,
        ]));
        $res1->assertOk();
        $record = $res1->json('records.0');
        $this->assertArrayNotHasKey('cgst', $record);
        $this->assertArrayNotHasKey('sgst', $record);
        $this->assertArrayNotHasKey('igst', $record);

        $res2 = $this->actingAs($this->user)->getJson(route('reports.gstr2_data', [
            'start_date' => $this->today, 'end_date' => $this->today,
        ]));
        $res2->assertOk();
    }

    public function test_item10_sales_report_payment_method_is_derived(): void
    {
        // Sale A: no payments -> 'N/A'
        $this->makeSale('SA-P5-A');

        // Sale B: single Cash payment -> 'Cash'
        $saleB = $this->makeSale('SA-P5-B');
        DbSalePayment::create([
            'store_id' => 1, 'sales_id' => $saleB->id, 'customer_id' => $this->customer->id,
            'payment_code' => 'SP-P5-B', 'payment_date' => $this->today, 'payment_type' => 'Cash',
            'payment' => 50.00, 'status' => 1,
        ]);

        // Sale C: two distinct payment types -> 'Mixed/Multiple'
        $saleC = $this->makeSale('SA-P5-C');
        DbSalePayment::create([
            'store_id' => 1, 'sales_id' => $saleC->id, 'customer_id' => $this->customer->id,
            'payment_code' => 'SP-P5-C1', 'payment_date' => $this->today, 'payment_type' => 'Cash',
            'payment' => 30.00, 'status' => 1,
        ]);
        DbSalePayment::create([
            'store_id' => 1, 'sales_id' => $saleC->id, 'customer_id' => $this->customer->id,
            'payment_code' => 'SP-P5-C2', 'payment_date' => $this->today, 'payment_type' => 'bkash',
            'payment' => 20.00, 'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('reports.sales_data', [
            'start_date' => $this->today, 'end_date' => $this->today,
        ]));
        $response->assertOk();

        $byInvoice = collect($response->json('records'))->keyBy('invoice');
        $this->assertSame('N/A', $byInvoice['SA-P5-A']['method']);
        $this->assertSame('Cash', $byInvoice['SA-P5-B']['method']);
        $this->assertSame('Mixed/Multiple', $byInvoice['SA-P5-C']['method']);
    }

    public function test_item11_profit_loss_renders_custom_range_inputs_bound_to_start_end_date(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.profit_loss'));
        $response->assertOk();

        $html = $response->getContent();
        // The custom-range block must be present and bound to the real date state.
        $this->assertStringContainsString("dateRange === 'Custom Range'", $html);
        $this->assertStringContainsString('x-model="startDate"', $html);
        $this->assertStringContainsString('x-model="endDate"', $html);
        // And the fall-through guard exists.
        $this->assertStringContainsString("case 'Custom Range':", $html);
    }

    public function test_item11_custom_range_dates_actually_change_report_output(): void
    {
        $this->makeSale('SA-P5-RANGE');

        // In-range request.
        $inRange = $this->actingAs($this->user)->getJson(route('reports.profit_loss_data', [
            'start_date' => $this->today, 'end_date' => $this->today,
        ]));
        $inRange->assertOk();

        // Out-of-range request (a genuinely custom window with no data).
        $outRange = $this->actingAs($this->user)->getJson(route('reports.profit_loss_data', [
            'start_date' => '2000-01-01', 'end_date' => '2000-12-31',
        ]));
        $outRange->assertOk();

        $this->assertNotSame(
            $inRange->json('data.sales.grandTotal'),
            $outRange->json('data.sales.grandTotal'),
            'A genuinely custom date window must change the P&L output.'
        );
    }
}
