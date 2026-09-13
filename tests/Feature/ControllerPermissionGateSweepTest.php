<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbCoupon;
use App\Models\DbCurrency;
use App\Models\DbCustomer;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\SmsCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Permission-gate sweep rollout — per-slug 403 + no-effect + control tests.
 *
 * Covers the 7 previously-ungated controllers:
 *   Phase 1 — BackupController        (database_backup — NEW seeded slug)
 *   Phase 2 — AdvanceController       (cust_adv_payments_view/add/edit/delete)
 *   Phase 3 — CouponController        (discountCoupon*) + CustomerCouponController (customerCoupon*)
 *   Phase 4 — SmsTemplateController   (sms_template_view/edit)
 *             MessageTemplateController (email_template_view/edit)
 *             SmsCampaignController   (sms_api_view)
 *             SmsSendController       (send_sms)
 */
class ControllerPermissionGateSweepTest extends TestCase
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
            'store_code' => 'SWEEP-ST', 'store_name' => 'Sweep Store',
            'mobile' => '01788880000', 'status' => 1,
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
            'role_name' => 'Sweep Role ' . uniqid(),
            'status' => 1,
        ]);
        DbPermission::create([
            'role_id' => $role->id, 'store_id' => $this->store->id,
            'permissions' => $permissions,
        ]);

        return User::factory()->create(['store_id' => $this->store->id, 'role_id' => $role->id]);
    }

    private function customer(): DbCustomer
    {
        return DbCustomer::create([
            'store_id' => $this->store->id, 'customer_name' => 'Sweep Cust',
            'customer_code' => 'SC-' . uniqid(), 'mobile' => '01711110000', 'status' => 1,
        ]);
    }

    /* ══════════════════ PHASE 1 — BackupController (database_backup) ══════════════════ */

    public function test_phase1_backup_index_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['store_settings_view']))
            ->get(route('settings.backup'))->assertStatus(403);

        $this->actingAs($this->actor(['database_backup']))
            ->get(route('settings.backup'))->assertOk();
    }

    public function test_phase1_backup_create_denied_when_unauthorized(): void
    {
        // A non-super-admin WITHOUT database_backup must be stopped BEFORE any
        // backup runs (asserted by the 403 — the guard is the first statement).
        $this->actingAs($this->actor(['store_settings_view']))
            ->postJson(route('settings.backup.create'))->assertStatus(403);
    }

    public function test_phase1_backup_destroy_denied_when_unauthorized_and_file_survives(): void
    {
        // Place a real backup artifact on the local disk, then confirm an
        // unauthorized delete neither succeeds nor removes it.
        $appName = config('backup.backup.name', env('APP_NAME', 'laravel-backup'));
        $dir = Storage::disk('local')->path($appName);
        File::ensureDirectoryExists($dir);
        $file = $dir . '/sweep_test_backup.zip';
        file_put_contents($file, 'dummy');

        try {
            $this->actingAs($this->actor(['store_settings_view']))
                ->deleteJson(route('settings.backup.delete', 'sweep_test_backup.zip'))
                ->assertStatus(403);

            $this->assertFileExists($file, 'An unauthorized delete must NOT remove the backup file.');
        } finally {
            @unlink($file);
        }
    }

    /* ══════════════════ PHASE 2 — AdvanceController ══════════════════ */

    public function test_phase2_advance_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['cust_adv_payments_add']))
            ->get(route('advance.list'))->assertStatus(403);

        $this->actingAs($this->actor(['cust_adv_payments_view']))
            ->get(route('advance.list'))->assertOk();
    }

    public function test_phase2_advance_add_denied_and_allowed(): void
    {
        $customer = $this->customer();
        $account = AcAccount::create([
            'store_id' => $this->store->id, 'account_name' => 'Adv Cash',
            'account_code' => 'ADV-' . uniqid(), 'balance' => 0, 'status' => 1, 'delete_bit' => 0,
        ]);
        $payload = [
            'payment_date' => now()->toDateString(),
            'customer_id'  => $customer->id,
            'amount'       => 100,
            'payment_type' => 'Cash',
            'account_id'   => $account->id,
        ];

        // Without cust_adv_payments_add → 403, no row created.
        $this->actingAs($this->actor(['cust_adv_payments_view']))
            ->post(route('advance.store'), $payload)->assertStatus(403);
        $this->assertSame(0, \App\Models\DbCustAdvance::withoutGlobalScope('store_id')->count());

        // Control: with the slug, the create succeeds.
        $this->actingAs($this->actor(['cust_adv_payments_view', 'cust_adv_payments_add']))
            ->post(route('advance.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\DbCustAdvance::withoutGlobalScope('store_id')->count());
    }

    public function test_phase2_advance_add_rejects_cross_store_account_id(): void
    {
        // Account belonging to ANOTHER store.
        $otherStore = DbStore::create([
            'store_code' => 'SWEEP-OTHER', 'store_name' => 'Other Store',
            'mobile' => '01788880001', 'status' => 1,
        ]);
        $foreignAccount = AcAccount::create([
            'store_id' => $otherStore->id, 'account_name' => 'Foreign Cash',
            'account_code' => 'FOR-' . uniqid(), 'balance' => 0, 'status' => 1, 'delete_bit' => 0,
        ]);

        $user = $this->actor(['cust_adv_payments_view', 'cust_adv_payments_add']);

        $this->actingAs($user)->post(route('advance.store'), [
            'payment_date' => now()->toDateString(),
            'customer_id'  => $this->customer()->id,
            'amount'       => 100,
            'payment_type' => 'Cash',
            'account_id'   => $foreignAccount->id,
        ])->assertSessionHasErrors('account_id');

        $this->assertSame(0, \App\Models\DbCustAdvance::withoutGlobalScope('store_id')->count());
    }

    public function test_phase2_advance_add_accepts_same_store_account_id(): void
    {
        $account = AcAccount::create([
            'store_id' => $this->store->id, 'account_name' => 'Own Cash',
            'account_code' => 'OWN-' . uniqid(), 'balance' => 0, 'status' => 1, 'delete_bit' => 0,
        ]);

        $this->actingAs($this->actor(['cust_adv_payments_view', 'cust_adv_payments_add']))
            ->post(route('advance.store'), [
                'payment_date' => now()->toDateString(),
                'customer_id'  => $this->customer()->id,
                'amount'       => 100,
                'payment_type' => 'Cash',
                'account_id'   => $account->id,
            ])->assertSessionHasNoErrors();

        $this->assertSame(1, \App\Models\DbCustAdvance::withoutGlobalScope('store_id')->count());
    }

    public function test_phase2_advance_delete_denied_and_allowed(): void
    {
        // The customer must still hold the un-consumed advance (tot_advance >= amount),
        // otherwise destroy()'s underflow guard correctly blocks deletion.
        $customer = $this->customer();
        $customer->tot_advance = 100;
        $customer->save();

        $advance = \App\Models\DbCustAdvance::create([
            'store_id' => $this->store->id, 'customer_id' => $customer->id,
            'amount' => 100, 'payment_date' => now()->toDateString(),
            'payment_type' => 'Cash', 'payment_code' => 'ADV-' . uniqid(), 'count_id' => 1,
        ]);

        $this->actingAs($this->actor(['cust_adv_payments_view']))
            ->delete(route('advance.delete', $advance->id))->assertStatus(403);
        $this->assertNotNull(\App\Models\DbCustAdvance::withoutGlobalScope('store_id')->find($advance->id));

        $this->actingAs($this->actor(['cust_adv_payments_view', 'cust_adv_payments_delete']))
            ->delete(route('advance.delete', $advance->id))->assertRedirect();
        $this->assertNull(\App\Models\DbCustAdvance::withoutGlobalScope('store_id')->find($advance->id));
    }

    /* ══════════════════ PHASE 3 — CouponController (discountCoupon*) ══════════════════ */

    public function test_phase3_coupon_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['discountCouponAdd']))
            ->get(route('coupons.master'))->assertStatus(403);

        $this->actingAs($this->actor(['discountCouponView']))
            ->get(route('coupons.master'))->assertOk();
    }

    public function test_phase3_coupon_add_denied_and_allowed(): void
    {
        $payload = [
            'name' => 'Sweep Coupon', 'code' => 'SWEEP-' . uniqid(),
            'type' => 'Fixed', 'value' => 10, 'expire_date' => now()->addMonth()->toDateString(),
        ];

        $this->actingAs($this->actor(['discountCouponView']))
            ->post(route('coupons.store'), $payload)->assertStatus(403);
        $this->assertSame(0, DbCoupon::withoutGlobalScope('store_id')->count());

        $this->actingAs($this->actor(['discountCouponView', 'discountCouponAdd']))
            ->post(route('coupons.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, DbCoupon::withoutGlobalScope('store_id')->count());
    }

    public function test_phase3_coupon_delete_denied_and_allowed(): void
    {
        $coupon = DbCoupon::create([
            'store_id' => $this->store->id, 'name' => 'Del Coupon',
            'code' => 'DEL-' . uniqid(), 'type' => 'Fixed', 'value' => 5,
            'expire_date' => now()->addMonth()->toDateString(), 'status' => 1,
        ]);

        $this->actingAs($this->actor(['discountCouponView']))
            ->delete(route('coupons.delete', $coupon->id))->assertStatus(403);
        $this->assertNotNull(DbCoupon::withoutGlobalScope('store_id')->find($coupon->id));

        $this->actingAs($this->actor(['discountCouponView', 'discountCouponDelete']))
            ->delete(route('coupons.delete', $coupon->id))->assertRedirect();
        $this->assertNull(DbCoupon::withoutGlobalScope('store_id')->find($coupon->id));
    }

    /* ══════════════════ PHASE 3 — CustomerCouponController (customerCoupon*) ══════════════════ */

    public function test_phase3_customer_coupon_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['customerCouponAdd']))
            ->get(route('coupons.customer.list'))->assertStatus(403);

        $this->actingAs($this->actor(['customerCouponView']))
            ->get(route('coupons.customer.list'))->assertOk();
    }

    public function test_phase3_customer_coupon_add_denied_and_allowed(): void
    {
        $payload = [
            'customer_id' => $this->customer()->id,
            'name' => 'Sweep CC', 'code' => 'SCC-' . uniqid(),
            'type' => 'Fixed', 'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
        ];

        $this->actingAs($this->actor(['customerCouponView']))
            ->post(route('coupons.customer.store'), $payload)->assertStatus(403);
        $this->assertSame(0, \App\Models\DbCustomerCoupon::withoutGlobalScope('store_id')->count());

        $this->actingAs($this->actor(['customerCouponView', 'customerCouponAdd']))
            ->post(route('coupons.customer.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\DbCustomerCoupon::withoutGlobalScope('store_id')->count());
    }

    /* ══════════════════ PHASE 4 — SmsTemplateController (sms_template_*) ══════════════════ */

    public function test_phase4_sms_template_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['sms_template_edit']))
            ->get(route('sms.templates'))->assertStatus(403);

        $this->actingAs($this->actor(['sms_template_view']))
            ->get(route('sms.templates'))->assertOk();
    }

    public function test_phase4_sms_template_edit_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['sms_template_view']))
            ->post(route('sms.templates.store'), ['template_name' => 'X', 'content' => 'Y'])
            ->assertStatus(403);
        $this->assertSame(0, DbSmsTemplate::where('template_name', 'X')->count());

        $this->actingAs($this->actor(['sms_template_view', 'sms_template_edit']))
            ->post(route('sms.templates.store'), ['template_name' => 'X', 'content' => 'Y'])
            ->assertRedirect();
        $this->assertSame(1, DbSmsTemplate::where('template_name', 'X')->count());
    }

    /* ══════════════════ PHASE 4 — MessageTemplateController (email_template_*) ══════════════════ */

    public function test_phase4_message_template_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['email_template_edit']))
            ->get(route('messaging.templates'))->assertStatus(403);

        $this->actingAs($this->actor(['email_template_view']))
            ->get(route('messaging.templates'))->assertOk();
    }

    public function test_phase4_message_template_edit_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['email_template_view']))
            ->post(route('messaging.templates.store'), ['template_name' => 'MT', 'content' => 'C'])
            ->assertStatus(403);
        $this->assertSame(0, DbSmsTemplate::where('template_name', 'MT')->count());

        $this->actingAs($this->actor(['email_template_view', 'email_template_edit']))
            ->post(route('messaging.templates.store'), ['template_name' => 'MT', 'content' => 'C'])
            ->assertRedirect();
        $this->assertSame(1, DbSmsTemplate::where('template_name', 'MT')->count());
    }

    /* ══════════════════ PHASE 4 — SmsCampaignController (sms_api_view) ══════════════════ */

    public function test_phase4_sms_campaign_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['send_sms']))
            ->get(route('sms.campaigns'))->assertStatus(403);

        $this->actingAs($this->actor(['sms_api_view']))
            ->get(route('sms.campaigns'))->assertOk();
    }

    public function test_phase4_sms_campaign_destroy_denied_and_allowed(): void
    {
        $campaign = SmsCampaign::create([
            'store_id' => $this->store->id, 'name' => 'Sweep Campaign',
            'target_type' => 'all', 'status' => 'Scheduled', 'created_by' => 1,
        ]);

        $this->actingAs($this->actor(['send_sms']))
            ->delete(route('sms.campaigns.delete', $campaign->id))->assertStatus(403);
        $this->assertNull($campaign->fresh()->deleted_at);

        $this->actingAs($this->actor(['sms_api_view']))
            ->delete(route('sms.campaigns.delete', $campaign->id))->assertRedirect();
        $this->assertNotNull($campaign->fresh()->deleted_at);
    }

    /* ══════════════════ PHASE 4 — SmsSendController (send_sms) ══════════════════ */

    public function test_phase4_sms_send_view_denied_and_allowed(): void
    {
        $this->actingAs($this->actor(['sms_api_view']))
            ->get(route('sms.send'))->assertStatus(403);

        $this->actingAs($this->actor(['send_sms']))
            ->get(route('sms.send'))->assertOk();
    }

    public function test_phase4_sms_send_process_denied_and_allowed(): void
    {
        $payload = ['message' => 'Hi', 'target' => 'all'];

        $this->actingAs($this->actor(['sms_api_view']))
            ->postJson(route('sms.send.process'), $payload)->assertStatus(403);
        $this->assertSame(0, SmsCampaign::withoutGlobalScope('store_id')->count());

        $this->actingAs($this->actor(['send_sms']))
            ->postJson(route('sms.send.process'), $payload)->assertOk();
        $this->assertSame(1, SmsCampaign::withoutGlobalScope('store_id')->count());
    }
}
