<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Step 1 — slug reconciliation regression cover.
 *
 * Asserts the static sources of truth now agree:
 *  - the Sales module's Roles-UI checkbox generates the SEEDER slugs
 *    (sales_view / sales_add) rather than the auto-slugged sales_include_pos_*;
 *  - the Reports module is a single "View Reports" checkbox -> reports_view;
 *  - reports_view is present in BOTH seeders;
 *  - the sidebar checks sales_view / sales_add.
 *
 * These are source-level assertions (the Roles UI derives checkbox values at
 * render time via Str::slug, so a per-role install check would not cover the
 * template itself). The live data migration is covered separately.
 */
class PermissionSlugReconciliationTest extends TestCase
{
    private function bladeSource(string $name): string
    {
        return File::get(base_path('resources/views/module/users/' . $name));
    }

    public function test_roles_edit_sales_module_maps_to_seeder_slugs(): void
    {
        $blade = $this->bladeSource('roles_edit.blade.php');

        $this->assertStringContainsString("'name' => 'Sales (Include POS)'", $blade);
        $this->assertStringContainsString("'Add' => 'sales_add'", $blade);
        $this->assertStringContainsString("'View' => 'sales_view'", $blade);
        $this->assertStringContainsString("'Edit' => 'sales_edit'", $blade);
        $this->assertStringContainsString("'Delete' => 'sales_delete'", $blade);
    }

    public function test_roles_add_sales_module_maps_to_seeder_slugs(): void
    {
        $blade = $this->bladeSource('roles_add.blade.php');

        $this->assertStringContainsString("'name' => 'Sales (Include POS)'", $blade);
        $this->assertStringContainsString("'Add' => 'sales_add'", $blade);
        $this->assertStringContainsString("'View' => 'sales_view'", $blade);
    }

    public function test_reports_module_collapsed_to_single_reports_view_checkbox(): void
    {
        foreach (['roles_edit.blade.php', 'roles_add.blade.php'] as $file) {
            $blade = $this->bladeSource($file);
            $this->assertStringContainsString("'name' => 'Reports', 'perms' => ['View Reports']", $blade, $file);
            $this->assertStringContainsString("'View Reports' => 'reports_view'", $blade, $file);
            // The old per-report checkbox list must be gone.
            $this->assertStringNotContainsString("'GSTR-1 Report', 'GSTR-2 Report'", $blade, $file);
        }
    }

    public function test_reports_view_present_in_both_seeders(): void
    {
        foreach (['PermissionSeeder.php', 'RolePermissionSeeder.php'] as $file) {
            $seeder = File::get(base_path('database/seeders/' . $file));
            $this->assertStringContainsString("'reports_view'", $seeder, $file);
        }
    }

    public function test_sidebar_checks_sales_view_and_sales_add(): void
    {
        $blade = File::get(base_path('resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString("hasPermission('sales_view')", $blade);
        $this->assertStringContainsString("hasPermission('sales_add')", $blade);
        // The sidebar must no longer gate the Sales menu on the auto-slugged value.
        $this->assertStringNotContainsString("hasPermission('sales_include_pos_view')", $blade);
        $this->assertStringNotContainsString("hasPermission('sales_include_pos_add')", $blade);
    }

    public function test_navigation_shortcut_service_uses_seeder_slugs(): void
    {
        $service = File::get(base_path('app/Services/NavigationShortcutService.php'));

        $this->assertStringContainsString("'route' => 'sales.pos', 'permission' => 'sales_add'", $service);
        $this->assertStringContainsString("'route' => 'sales.list', 'permission' => 'sales_view'", $service);
        $this->assertStringNotContainsString('sales_include_pos', $service);
    }

    public function test_reconciliation_data_migration_exists(): void
    {
        $this->assertTrue(
            File::exists(base_path('database/migrations/2026_09_12_000001_reconcile_permission_slugs_reports_view_and_sales.php'))
        );
    }
}
