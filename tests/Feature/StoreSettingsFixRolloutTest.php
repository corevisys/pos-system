<?php

namespace Tests\Feature;

use App\Models\DbCoupon;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification suite for the Store Settings fix rollout:
 *
 *  - Phase 2: exists: validation on currency_id / language_id (422, not 404).
 *  - Phase 3 Item 3: ItemController::printLabels() resolves the acting store
 *    (Store-B user sees Store B's name, not Store 1's).
 *  - Phase 3 Item 4: ReportController::profitLoss() resolves the acting store
 *    (Store-B P&L header shows Store B, not Store 1).
 *  - Phase 3 Item 5: SmsTriggerService::resolvePhone() routes admin-type
 *    alerts to the RELEVANT store's contact, not always store #1.
 *  - Control: SmsSettingsController/SmtpSettingsController act on the acting
 *    store (via current_store_id()), not store #1.
 */
class StoreSettingsFixRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $storeA;
    protected DbStore $storeB;
    protected DbCurrency $currencyA;
    protected DbCurrency $currencyB;
    protected DbLanguage $languageA;
    protected DbLanguage $languageB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currencyA = DbCurrency::create([
            'currency_name' => 'Alpha Dollar',
            'currency_code' => 'AAD',
            'symbol' => '$',
            'status' => 1,
        ]);
        $this->currencyB = DbCurrency::create([
            'currency_name' => 'Beta Euro',
            'currency_code' => 'BEE',
            'symbol' => '€',
            'status' => 0,
        ]);

        $this->languageA = DbLanguage::create(['language' => 'Alpha Language', 'status' => 1]);
        $this->languageB = DbLanguage::create(['language' => 'Beta Language', 'status' => 0]);

        $this->storeA = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Alpha Store',
            'mobile' => '01700000001',
            'phone' => '01700000001',
            'email' => 'alpha@example.com',
            'address' => 'Alpha Street 1',
            'city' => 'Dhaka',
            'status' => 1,
            'currency_id' => $this->currencyA->id,
            'language_id' => $this->languageA->id,
        ]);

        $this->storeB = DbStore::create([
            'id' => 2,
            'store_code' => 'ST0002',
            'store_name' => 'Beta Store',
            'mobile' => '01700000002',
            'phone' => '01700000002',
            'email' => 'beta@example.com',
            'address' => 'Beta Street 2',
            'city' => 'Chittagong',
            'status' => 1,
            'currency_id' => $this->currencyB->id,
            'language_id' => $this->languageB->id,
        ]);

        $role = DbRole::forceCreate([
            'id' => 50,
            'role_name' => 'Store Settings Fix Tester',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'store_settings_view', 'store_settings_edit',
                'items_print_labels', 'items_view',
                'profit_report',
            ],
        ]);

        $this->userA = User::factory()->create([
            'role_id' => $role->id,
            'role_name' => 'Store Settings Fix Tester',
            'store_id' => $this->storeA->id,
            'status' => 1,
        ]);
        $this->userB = User::factory()->create([
            'role_id' => $role->id,
            'role_name' => 'Store Settings Fix Tester',
            'store_id' => $this->storeB->id,
            'status' => 1,
        ]);

        store_settings(true);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Beta Store',
            'mobile' => '01700000002',
            'email' => 'beta@example.com',
            'city' => 'Chittagong',
            'currency_id' => (string) $this->currencyB->id,
            'language_id' => (string) $this->languageB->id,
            'timezone' => 'Asia/Dhaka',
            'date_format' => 'd-m-Y',
            'time_format' => 'h:i a',
            'decimals' => 2,
            'qty_decimals' => 2,
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 2 — exists: validation (422, not 404)
    // ─────────────────────────────────────────────────────────────

    public function test_non_existent_currency_id_yields_validation_error_not_404(): void
    {
        $res = $this->actingAs($this->userB)
            ->from(route('settings.store'))
            ->post(route('settings.store.update'), $this->validPayload(['currency_id' => 999999]));

        $res->assertSessionHasErrors('currency_id');
        $res->assertSessionDoesntHaveErrors('language_id');
        $this->assertSame('Beta Store', $this->storeB->fresh()->store_name);
    }

    public function test_non_existent_language_id_yields_validation_error_not_404(): void
    {
        $res = $this->actingAs($this->userB)
            ->from(route('settings.store'))
            ->post(route('settings.store.update'), $this->validPayload(['language_id' => 999999]));

        $res->assertSessionHasErrors('language_id');
        $res->assertSessionDoesntHaveErrors('currency_id');
        $this->assertSame('Beta Store', $this->storeB->fresh()->store_name);
    }

    public function test_valid_ids_still_save_normally(): void
    {
        $res = $this->actingAs($this->userB)
            ->post(route('settings.store.update'), $this->validPayload(['store_name' => 'Beta Store Renamed']));

        $res->assertRedirect(route('settings.store'));
        $res->assertSessionHasNoErrors();
        $this->assertSame('Beta Store Renamed', $this->storeB->fresh()->store_name);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 1 (logo rollout) — logo upload is REACHABLE after save+reload
    // ─────────────────────────────────────────────────────────────

    public function test_logo_upload_persists_and_is_reachable_via_public_storage(): void
    {
        $image = \Illuminate\Http\UploadedFile::fake()->image('store-logo.png', 100, 50);

        $res = $this->actingAs($this->userB)
            ->post(route('settings.store.update'), $this->validPayload(['logo' => $image]));

        $res->assertRedirect(route('settings.store'));
        $res->assertSessionHasNoErrors();

        $store = $this->storeB->fresh();
        $this->assertNotEmpty($store->store_logo, 'store_logo DB column must be set.');
        $this->assertStringStartsWith('logos/', $store->store_logo);

        // 1. The file itself was written to the public disk root.
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('public')->exists($store->store_logo),
            'File must exist on the public disk (storage/app/public).'
        );

        // 2. The file must be REACHABLE through public/storage (the symlink/junction
        //    that web requests hit). This is the actual Phase-1 regression: before the
        //    junction existed, public/storage was a stale real directory and the file
        //    was invisible despite the DB column + disk write both succeeding.
        $this->assertFileExists(
            public_path('storage/' . $store->store_logo),
            'File must be reachable via public/storage (storage:link/junction present).'
        );

        // 3. The settings page must render the resolved URL for that logo after reload.
        $page = $this->actingAs($this->userB)->get(route('settings.store'));
        $page->assertOk();
        $page->assertSee(\Illuminate\Support\Facades\Storage::url($store->store_logo), false);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 3 ITEM 3 — printLabels() resolves the acting store
    // ─────────────────────────────────────────────────────────────

    public function test_store_b_user_prints_labels_with_store_b_name_not_store_1(): void
    {
        $itemB = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Beta Item',
            'item_code' => 'BETA-001',
            'status' => 1,
            'store_id' => 2,
        ]);

        $res = $this->actingAs($this->userB)
            ->get(route('items.labels', ['items' => $itemB->id]));

        $res->assertOk();
        $res->assertSee('Beta Store', false);
        $res->assertDontSee('Alpha Store', false);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 3 ITEM 4 — profitLoss() resolves the acting store
    // ─────────────────────────────────────────────────────────────

    public function test_store_b_user_profit_loss_header_shows_store_b_not_store_1(): void
    {
        $res = $this->actingAs($this->userB)->get(route('reports.profit_loss'));

        $res->assertOk();
        $res->assertSee('Beta Store', false);
        $res->assertSee('Beta Street 2', false);
        $res->assertDontSee('Alpha Store', false);
        $res->assertDontSee('Alpha Street 1', false);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 3 ITEM 5 — SmsTriggerService admin routing (store-aware)
    // ─────────────────────────────────────────────────────────────

    public function test_low_stock_alert_routes_to_relevant_stores_contact_not_store_1(): void
    {
        $itemB = DbItem::create([
            'store_id' => 1,
            'item_name' => 'Beta Low Stock Item',
            'item_code' => 'LOW-BETA',
            'stock' => 1,
            'alert_qty' => 5,
            'status' => 1,
            'store_id' => 2,
        ]);

        $service = app(SmsTriggerService::class);
        $ref = new \ReflectionMethod($service, 'resolvePhone');
        $ref->setAccessible(true);

        $phone = $ref->invoke($service, 'LowStock', $itemB);

        $this->assertSame('01700000002', $phone);
    }

    public function test_coupon_expiry_routes_to_relevant_stores_contact_not_store_1(): void
    {
        $couponB = DbCoupon::create([
            'store_id' => 2,
            'code' => 'BETA-COUPON',
            'name' => 'Beta Coupon',
            'value' => 10,
            'type' => 'Fixed',
            'expire_date' => now()->addDay()->toDateString(),
            'status' => 1,
        ]);

        $service = app(SmsTriggerService::class);
        $ref = new \ReflectionMethod($service, 'resolvePhone');
        $ref->setAccessible(true);

        $phone = $ref->invoke($service, 'CouponExpiry', $couponB);

        $this->assertSame('01700000002', $phone);
    }

    // ─────────────────────────────────────────────────────────────
    // PHASE 3 ITEM 5 (control) — SmsSettingsController / SmtpSettingsController
    // ─────────────────────────────────────────────────────────────

    public function test_sms_settings_act_on_acting_store_not_store_1(): void
    {
        $res = $this->actingAs($this->userB)->get(route('sms.settings'));

        $res->assertOk();
        $res->assertSee('Beta Store', false);
    }

    public function test_smtp_settings_act_on_acting_store_not_store_1(): void
    {
        $res = $this->actingAs($this->userB)->get(route('settings.smtp'));

        $res->assertOk();
        $res->assertSee('Beta Store', false);
    }
}
