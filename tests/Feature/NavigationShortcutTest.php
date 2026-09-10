<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Services\NavigationShortcutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class NavigationShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_90_shortcuts_are_registered_and_have_valid_named_routes()
    {
        $superAdmin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        $shortcuts = NavigationShortcutService::getShortcutsForUser($superAdmin);

        $this->assertCount(17, $shortcuts, 'Expected 17 unique modules');

        $totalItems = 0;
        foreach ($shortcuts as $modKey => $module) {
            foreach ($module['items'] as $item) {
                $totalItems++;
                $this->assertTrue(Route::has($item['route']), "Route {$item['route']} does not exist.");
                $this->assertNotEmpty($item['url'], "URL for {$item['label']} should not be empty.");
            }
        }

        $this->assertEquals(90, $totalItems, 'Expected exactly 90 shortcut pages in total.');
    }

    public function test_zero_module_key_and_zero_page_key_collisions()
    {
        $raw = NavigationShortcutService::SHORTCUTS;
        $this->assertCount(17, $raw);

        $moduleKeys = [];
        $totalItems = 0;

        foreach ($raw as $mName => $data) {
            $mKey = strtoupper($data['module_key']);
            $this->assertArrayNotHasKey($mKey, $moduleKeys, "Module key collision detected for key: {$mKey}");
            $moduleKeys[$mKey] = $mName;

            $pageKeys = [];
            foreach ($data['items'] as $item) {
                $totalItems++;
                $pKey = strtoupper($item['page_key']);
                $this->assertArrayNotHasKey($pKey, $pageKeys, "Page key collision in module {$mName} for key: {$pKey}");
                $pageKeys[$pKey] = $item['label'];
            }
        }

        $this->assertEquals(90, $totalItems);
    }

    public function test_super_admin_has_full_permission_for_all_90_shortcuts()
    {
        $superAdmin = User::where('role_id', 1)->first();
        if (!$superAdmin) {
            $superAdmin = User::factory()->create(['role_id' => 1, 'role_name' => 'Super Admin']);
        }

        $shortcuts = NavigationShortcutService::getShortcutsForUser($superAdmin);

        foreach ($shortcuts as $module) {
            foreach ($module['items'] as $item) {
                $this->assertTrue($item['has_permission'], "Super admin should have permission for {$item['label']}");
            }
        }
    }

    public function test_limited_role_cashier_strictly_enforces_permission_flags()
    {
        /**
         * NOTE: The seeder Cashier role uses LEGACY slugs (sales_add, cash_transactions).
         * The sidebar & service use NEW slugs (sales_include_pos_add, accounts_cash_transactions).
         * This is a pre-existing mismatch in the project — Cashier from the seeder
         * would not see Sales/POS in the sidebar either.
         *
         * For this test we create a fresh role+permission set with the EXACT slugs
         * the service checks (mirroring what the sidebar gate-checks in app.blade.php).
         */
        $role = DbRole::create([
            'role_name'   => 'Cashier Test Role',
            'description' => 'Test cashier with sidebar-accurate permission slugs',
            'status'      => 1,
            'store_id'    => 1,
        ]);

        // Use the EXACT sidebar permission slugs that appear in app.blade.php:
        DbPermission::create([
            'role_id'     => $role->id,
            'store_id'    => 1,
            'permissions' => [
                'dashboard_view_dashboard_data', // Dashboard
                'customers_view',                // Customers List — allowed
                'customers_add',                 // Add Customer — allowed
                'items_view',                    // Items List — allowed
                // Deliberately omitting: users_view, roles_view, reports_view,
                // purchase_add, purchase_view, stock_transfer_view, expense_view,
                // sms_whatsapp_send_message, store_settings_view, database_backup,
                // sales_include_pos_add, etc.
            ],
        ]);

        $cashier = User::factory()->create([
            'role_id'   => $role->id,
            'role_name' => 'Cashier Test Role',
        ]);

        $shortcuts = NavigationShortcutService::getShortcutsForUser($cashier);

        // ── 10 Restricted shortcuts — MUST be blocked ──────────────────────────
        $restrictedChecks = [
            ['mod' => 'G', 'key' => 'B', 'label' => 'Database Backup',         'perm' => 'database_backup'],
            ['mod' => 'G', 'key' => 'S', 'label' => 'Store Settings',          'perm' => 'store_settings_view'],
            ['mod' => 'U', 'key' => 'U', 'label' => 'Users List',              'perm' => 'users_view'],
            ['mod' => 'U', 'key' => 'R', 'label' => 'Roles List',              'perm' => 'roles_view'],
            ['mod' => 'R', 'key' => '1', 'label' => 'Sales Summary',           'perm' => 'reports_view'],
            ['mod' => 'R', 'key' => '2', 'label' => 'Profit & Loss Report',    'perm' => 'reports_view'],
            ['mod' => 'P', 'key' => 'N', 'label' => 'New Purchase',            'perm' => 'purchase_add'],
            ['mod' => 'T', 'key' => 'T', 'label' => 'Transfer List',           'perm' => 'stock_transfer_view'],
            ['mod' => 'E', 'key' => 'L', 'label' => 'Expenses List',           'perm' => 'expense_view'],
            ['mod' => 'M', 'key' => 'S', 'label' => 'Send SMS',                'perm' => 'sms_whatsapp_send_message'],
        ];

        foreach ($restrictedChecks as $check) {
            $modItems = $shortcuts[$check['mod']]['items'] ?? [];
            $item     = collect($modItems)->firstWhere('page_key', $check['key']);
            $this->assertNotNull($item, "Shortcut {$check['mod']}->{$check['key']} ({$check['label']}) not found");
            $this->assertFalse(
                $item['has_permission'],
                "BLOCK FAILED: Cashier accessed {$check['label']} (permission: {$check['perm']}) — must be blocked"
            );
        }

        // ── 3 Allowed shortcuts — MUST navigate ────────────────────────────────
        $allowedChecks = [
            ['mod' => 'C', 'key' => 'L', 'label' => 'Customers List',  'perm' => 'customers_view'],
            ['mod' => 'I', 'key' => 'L', 'label' => 'Items List',       'perm' => 'items_view'],
            ['mod' => 'F', 'key' => 'P', 'label' => 'Profile',          'perm' => null],  // No permission required
        ];

        foreach ($allowedChecks as $check) {
            $modItems = $shortcuts[$check['mod']]['items'] ?? [];
            $item     = collect($modItems)->firstWhere('page_key', $check['key']);
            $this->assertNotNull($item, "Shortcut {$check['mod']}->{$check['key']} ({$check['label']}) not found");
            $this->assertTrue(
                $item['has_permission'],
                "ALLOW FAILED: Cashier was blocked from {$check['label']} — should be allowed"
            );
        }
    }

    public function test_layout_renders_logo_link_and_shortcut_components()
    {
        $superAdmin = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        // Logo link exists
        $response->assertSee('href="' . route('dashboard') . '"', false);
        // Shortcut helper button exists
        $response->assertSee('open-shortcuts-help', false);
        // APP_SHORTCUTS config injected
        $response->assertSee('window.APP_SHORTCUTS', false);
    }
}
