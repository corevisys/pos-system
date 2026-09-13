<?php

namespace Tests\Feature;

use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 7 coverage — redesign / cleanup verification.
 *
 *  - every one of the 24 report VIEW pages still renders (no Blade breakage
 *    from the dead-UI removal / table realignment);
 *  - the shared export component is present on the 21 wired reports;
 *  - the decorative selection state + dead href="#" links are gone;
 *  - Chart.js / Moment.js are no longer loaded from external CDNs.
 */
class ReportPhase7RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::firstOrCreate(['id' => 1], ['store_name' => 'P7 Store', 'status' => 1]);
        $role = DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
        DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => ['reports_view']]);

        $this->user = User::factory()->create([
            'store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
        ]);
    }

    public static function reportViewRoutes(): array
    {
        $names = [
            'sales_summary', 'profit_loss', 'sales_payment', 'customer_orders', 'gstr1', 'gstr2',
            'sales_gst', 'purchase_gst', 'sales_tax', 'purchase_tax', 'supplier_items', 'sales',
            'sales_return', 'seller_points', 'purchase', 'purchase_return', 'expense', 'stock',
            'sales_item', 'return_items', 'purchase_payments', 'sales_payments',
            'cash_reconciliation', 'cash_flow',
        ];
        $out = [];
        foreach ($names as $n) {
            $out[$n] = ['reports.' . $n];
        }
        return $out;
    }

    /**
     * @dataProvider reportViewRoutes
     */
    public function test_all_24_report_pages_render(string $route): void
    {
        $this->actingAs($this->user)->get(route($route))->assertOk();
    }

    public function test_decorative_selection_state_and_dead_links_are_gone(): void
    {
        foreach (['reports.sales', 'reports.gstr1', 'reports.purchase', 'reports.expense', 'reports.sales_payments'] as $route) {
            $html = $this->actingAs($this->user)->get(route($route))->getContent();
            $this->assertStringNotContainsString('x-model="selectedRecords"', $html, "{$route}: decorative row checkboxes must be gone.");
            $this->assertStringNotContainsString('x-model="selectedAll"', $html, "{$route}: decorative select-all must be gone.");
            $this->assertStringNotContainsString('href="#"', $html, "{$route}: dead action links must be gone.");
        }
    }

    public function test_shared_export_component_is_rendered_on_wired_reports(): void
    {
        foreach (['reports.sales', 'reports.gstr1', 'reports.sales_payments', 'reports.expense', 'reports.cash_reconciliation', 'reports.profit_loss'] as $route) {
            $html = $this->actingAs($this->user)->get(route($route))->getContent();
            // The component renders the three real controls.
            $this->assertStringContainsString('exportNow(', $html, "{$route}: shared export JS must be present.");
            $this->assertMatchesRegularExpression('/>\s*Excel\s*</', $html, "{$route}: Excel export control must render.");
        }
    }

    public function test_external_cdn_scripts_are_vendored_locally(): void
    {
        foreach (['reports.cash_flow', 'reports.profit_loss'] as $route) {
            $html = $this->actingAs($this->user)->get(route($route))->getContent();
            $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/chart.js', $html, "{$route}: Chart.js CDN must be gone.");
            $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/moment.js', $html, "{$route}: Moment.js CDN must be gone.");
            $this->assertStringContainsString('/vendor/', $html, "{$route}: local vendored script must be referenced.");
        }
    }

    public function test_vendored_assets_exist_on_disk(): void
    {
        $this->assertFileExists(public_path('vendor/chart.umd.min.js'));
        $this->assertFileExists(public_path('vendor/moment.min.js'));
    }
}
