<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 2 — sidebar/view slug alignment.
 *
 * The sidebar (layouts/app.blade.php) + the advance list referenced slugs that
 * do NOT exist in either seeder, so the controller-correct backend was gated by
 * a slug the UI never checked and the links were permanently hidden for every
 * non-super-admin. Each fixed link now checks the ACTUAL seeded slug.
 *
 * Layer 1 (authoritative, one test per mismatch): the sidebar SOURCE references
 *   the seeded slug — directly proves the mismatch is closed for all 14 items.
 * Layer 2 (end-to-end, representative): a user WITH the seeded slug sees the
 *   rendered link; WITHOUT it does not.
 */
class SidebarSlugAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Taka', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1,
        ]);
        $language = DbLanguage::firstOrCreate(['id' => 1], [
            'language' => 'English', 'code' => 'en', 'status' => 1,
        ]);

        $this->store = DbStore::create([
            'store_code' => 'SIDEBAR-ST', 'store_name' => 'Sidebar Store',
            'mobile' => '01755550000', 'status' => 1,
            'currency_id' => $currency->id, 'language_id' => $language->id,
            'decimals' => 2, 'qty_decimals' => 2,
        ]);
        store_settings(true);
    }

    /** A NON-super-admin actor granted exactly $permissions. */
    private function actor(array $permissions): User
    {
        $role = DbRole::create([
            'store_id' => $this->store->id,
            'role_name' => 'Sidebar Role ' . uniqid(),
            'status' => 1, 'is_super_admin' => false,
        ]);
        DbPermission::create([
            'role_id' => $role->id, 'store_id' => $this->store->id, 'permissions' => $permissions,
        ]);

        return User::factory()->create(['store_id' => $this->store->id, 'role_id' => $role->id]);
    }

    private function dashboard(User $user)
    {
        return $this->actingAs($user)->get(route('dashboard'));
    }

    /**
     * Every mismatch: [seeded slug, route name, human label, orphan string it
     * replaced]. The orphan is asserted ABSENT from the sidebar source.
     */
    public static function mismatchProvider(): array
    {
        return [
            'SMS → History/Campaigns'      => ['sms_api_view', 'sms.campaigns', 'Campaigns', 'sms_whatsapp_message_api_view'],
            'SMS → Send'                    => ['send_sms', 'sms.send', 'Send Message', 'sms_whatsapp_send_message'],
            'SMS → Templates'               => ['sms_template_view', 'sms.templates', 'SMS Templates', 'sms_whatsapp_message_template_view'],
            'SMS → Settings/Auto-rules'     => ['sms_settings', 'sms.settings', 'SMS Settings', 'sms_whatsapp_message_settings'],
            'Coupons → Master'              => ['discountCouponView', 'coupons.master', 'Coupons Master', 'discount_coupon_view'],
            'Coupons → Create'              => ['discountCouponAdd', 'coupons.create', 'Create Coupon', 'discount_coupon_add'],
            'Customer coupons → List'       => ['customerCouponView', 'coupons.customer.list', 'Customer Coupons', 'customer_coupon_view'],
            'Customer coupons → Create'     => ['customerCouponAdd', 'coupons.customer.create', 'Create Customer Coupon', 'customer_coupon_add'],
            'Advance → List'                => ['cust_adv_payments_view', 'advance.list', 'Advance List', 'customers_advance_payments_view'],
            'Advance → Add'                 => ['cust_adv_payments_add', 'advance.add', 'Add Advance', 'customers_advance_payments_add'],
            'Customers → Import'            => ['import_customers', 'contacts.customers.import', 'Import Customers', 'customers_import_customers'],
            'Suppliers → Import'            => ['import_suppliers', 'contacts.suppliers.import', 'Import Suppliers', 'suppliers_import_suppliers'],
            'Units'                         => ['units_view', 'settings.units', 'Units', 'unit_view'],
            'Dashboard'                     => ['dashboard_view', 'dashboard', 'Dashboard', 'dashboard_view_dashboard_data'],
        ];
    }

    /** The subset that can be asserted end-to-end on the rendered dashboard HTML. */
    public static function behavioralProvider(): array
    {
        $all = self::mismatchProvider();
        // Excluded from the raw-HTML layer because their URLs appear in the page
        // for unrelated reasons (e.g. route('dashboard') is the always-rendered
        // logo/footer link), which makes a substring presence check ambiguous.
        // They remain fully covered by the structural test above.
        unset(
            $all['Dashboard'],
            $all['Coupons → Create'],
            $all['Customer coupons → Create'],
            $all['Advance → Add'],
            $all['Customers → Import'],
            $all['Suppliers → Import'],
        );
        return $all;
    }

    /**
     * @dataProvider mismatchProvider
     */
    public function test_sidebar_source_uses_the_seeded_slug_and_drops_the_orphan(
        string $seededSlug,
        string $routeName,
        string $label,
        string $orphan
    ): void {
        $blade = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString(
            "hasPermission('{$seededSlug}')",
            $blade,
            "Sidebar must gate on the seeded slug {$seededSlug}."
        );

        $this->assertStringNotContainsString(
            "hasPermission('{$orphan}')",
            $blade,
            "Sidebar must no longer reference the orphan slug {$orphan}."
        );
    }

    /**
     * @dataProvider behavioralProvider
     */
    public function test_sidebar_link_rendered_only_with_the_seeded_slug(
        string $seededSlug,
        string $routeName,
        string $label,
        string $orphan
    ): void {
        $anchor = '<a href="' . route($routeName) . '"';

        // WITHOUT the seeded slug → the sidebar link is NOT rendered.
        $this->dashboard($this->actor([]))
            ->assertOk()
            ->assertDontSee($anchor, false);

        // WITH the seeded slug → the sidebar link IS rendered.
        $this->dashboard($this->actor([$seededSlug]))
            ->assertOk()
            ->assertSee($anchor, false);
    }
}
