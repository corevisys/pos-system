<?php

namespace Tests\Feature;

use App\Http\Controllers\SmsSendController;
use App\Jobs\DispatchCampaignJob;
use App\Jobs\SendSingleSmsJob;
use App\Models\DbCustomer;
use App\Models\DbEmiSale;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\SmsAutoRule;
use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Models\Traits\StoreScoped;
use App\Models\User;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * SMS Automation hardening rollout (Phases 1-6).
 *
 * Covers the six recurring bug patterns found on SmsAutoRuleController,
 * SmsSettingsController, SmsSendController and the SmsAutoRule model:
 *   1. store_id never set on create (NOT NULL crash vector)
 *   2. unscoped reads (index / autoSettings)
 *   3. cross-tenant IDOR on update / toggle / destroy
 *   4. cross-tenant write via SmsSettingsController::updateAutoStatus
 *   5. globally-unique event_type (should be per-store)
 *   6. cross-tenant EMI recipient leak (web + queue paths)
 */
class SmsAutomationStoreScopingTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store1;
    protected DbStore $store2;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->store1 = DbStore::create([
            'id' => 1, 'store_name' => 'Store One', 'status' => 1,
            'mobile' => '01711111111', 'sms_status' => 1,
        ]);
        $this->store2 = DbStore::create([
            'id' => 2, 'store_name' => 'Store Two', 'status' => 1,
            'mobile' => '01722222222', 'sms_status' => 1,
        ]);
    }

    /** An authenticated, verified user bound to $storeId (no SMS permission gate exists on these routes). */
    private function user(int $storeId): User
    {
        $role = DbRole::create([
            'store_id'  => $storeId,
            'role_name' => 'SMS Automation Admin ' . $storeId . ' ' . uniqid(),
            'status'    => 1,
        ]);
        DbPermission::create([
            'role_id'     => $role->id,
            'store_id'    => $storeId,
            // send_sms is now required by SmsSendController (all methods) — the
            // Phase 6 HTTP count endpoint test acts as this user.
            'permissions' => ['send_sms'],
        ]);

        return User::factory()->create([
            'store_id' => $storeId,
            'role_id'  => $role->id,
        ]);
    }

    private function template(int $storeId, string $name): DbSmsTemplate
    {
        return DbSmsTemplate::create([
            'store_id'      => $storeId,
            'template_name' => $name,
            'category'      => 'Transactional',
            'message_type'  => 'transactional',
            'content'       => 'Hello {customer_name}',
            'variables_used' => ['customer_name'],
            'status'        => 1,
        ]);
    }

    private function rule(int $storeId, int $templateId, string $name, string $eventType = 'InvoiceCreated', bool $active = true): SmsAutoRule
    {
        return SmsAutoRule::create([
            'store_id'     => $storeId,
            'rule_name'    => $name,
            'event_type'   => $eventType,
            'event_source' => 'invoice',
            'template_id'  => $templateId,
            'trigger_time' => 'immediate',
            'is_active'    => $active,
        ]);
    }

    private function validRulePayload(int $templateId, string $eventType = 'InvoiceCreated'): array
    {
        return [
            'rule_name'    => 'New Rule',
            'event_type'   => $eventType,
            'event_source' => 'invoice',
            'template_id'  => $templateId,
            'trigger_time' => 'immediate',
            'days_offset'  => 0,
            'cooldown_days' => 0,
        ];
    }

    /* ══════════════ PHASE 1: store_id set explicitly on create ══════════════ */

    public function test_phase1_create_sets_store_id_to_the_acting_store_exactly(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');

        // Store 2 actor creates a rule — store_id must be EXACTLY 2 (not 1, not null).
        $this->actingAs($this->user(2))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t2->id, 'InvoiceCreated'))
            ->assertRedirect();

        $created = SmsAutoRule::withoutGlobalScope('store_id')->where('event_type', 'InvoiceCreated')->latest('id')->first();
        $this->assertNotNull($created, 'The rule must actually be inserted (previously a NOT NULL violation).');
        $this->assertSame(2, (int) $created->store_id, 'store_id must equal the acting store exactly.');
    }

    public function test_phase1_create_from_store_one_sets_store_one(): void
    {
        $t1 = $this->template(1, 'T1');

        $this->actingAs($this->user(1))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t1->id, 'PaymentReceived'))
            ->assertRedirect();

        $created = SmsAutoRule::withoutGlobalScope('store_id')->where('event_type', 'PaymentReceived')->first();
        $this->assertNotNull($created);
        $this->assertSame(1, (int) $created->store_id);
    }

    /* ══════════════ PHASE 2: StoreScoped trait + scoped reads ═══════════════ */

    public function test_phase2_model_uses_the_store_scoped_trait(): void
    {
        $this->assertContains(
            StoreScoped::class,
            class_uses_recursive(SmsAutoRule::class),
            'SmsAutoRule must reuse the app StoreScoped trait (not a bespoke scope).'
        );
    }

    public function test_phase2_trait_alone_scopes_eloquent_reads(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');
        $this->rule(1, $t1->id, 'Store One Rule', 'InvoiceCreated');
        $this->rule(2, $t2->id, 'Store Two Rule', 'PaymentReceived');

        // Acting as Store 1 — the global scope must auto-apply.
        $this->actingAs($this->user(1));
        $names1 = SmsAutoRule::pluck('rule_name')->all();
        $this->assertContains('Store One Rule', $names1);
        $this->assertNotContains('Store Two Rule', $names1);

        // Control: Store 2 still sees its own and not Store 1's.
        $this->actingAs($this->user(2));
        $names2 = SmsAutoRule::pluck('rule_name')->all();
        $this->assertContains('Store Two Rule', $names2);
        $this->assertNotContains('Store One Rule', $names2);
    }

    public function test_phase2_index_view_lists_only_the_acting_stores_rules(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');
        $this->rule(1, $t1->id, 'Store One Rule', 'InvoiceCreated');
        $this->rule(2, $t2->id, 'Store Two Rule', 'PaymentReceived');

        // Store 1 sees only its own rule.
        $res1 = $this->actingAs($this->user(1))->get(route('sms.auto-rules'));
        $res1->assertOk();
        $rules1 = $res1->viewData('rules')->pluck('rule_name')->all();
        $this->assertContains('Store One Rule', $rules1);
        $this->assertNotContains('Store Two Rule', $rules1, 'Store-A index must not contain Store-B rules.');

        // Control: Store 2 sees only its own.
        $res2 = $this->actingAs($this->user(2))->get(route('sms.auto-rules'));
        $res2->assertOk();
        $rules2 = $res2->viewData('rules')->pluck('rule_name')->all();
        $this->assertContains('Store Two Rule', $rules2);
        $this->assertNotContains('Store One Rule', $rules2, 'Store-B index must not contain Store-A rules.');
    }

    public function test_phase2_auto_settings_read_is_store_scoped(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');
        $this->rule(1, $t1->id, 'Store One Rule', 'InvoiceCreated');
        $this->rule(2, $t2->id, 'Store Two Rule', 'InvoiceCreated');

        $res = $this->actingAs($this->user(2))->get(route('messaging.settings'));
        $res->assertOk();

        $grouped = $res->viewData('rules');            // Collection grouped by event_type
        $allNames = collect($grouped)->flatten(1)->pluck('rule_name')->all();
        $this->assertContains('Store Two Rule', $allNames);
        $this->assertNotContains('Store One Rule', $allNames, 'autoSettings must not expose another store\'s rules.');
    }

    /* ══════════════ PHASE 3: cross-tenant IDOR on update/toggle/destroy ═════ */

    public function test_phase3_cross_store_update_is_blocked_and_row_unchanged(): void
    {
        $t1 = $this->template(1, 'T1');
        $victim = $this->rule(1, $t1->id, 'Victim Rule', 'InvoiceCreated');

        $this->actingAs($this->user(2))
            ->post(route('sms.auto-rules.update', $victim->id), [
                'rule_name'    => 'HACKED',
                'event_type'   => 'InvoiceCreated',
                'event_source' => 'invoice',
                'template_id'  => $t1->id,
                'trigger_time' => 'immediate',
                'days_offset'  => 0,
                'cooldown_days' => 0,
            ])
            ->assertStatus(404);

        $this->assertSame('Victim Rule', $victim->fresh()->rule_name, 'Store-A rule must be unchanged by a Store-B update.');
    }

    public function test_phase3_cross_store_toggle_is_blocked_and_row_unchanged(): void
    {
        $t1 = $this->template(1, 'T1');
        $victim = $this->rule(1, $t1->id, 'Victim Rule', 'InvoiceCreated', true);

        $this->actingAs($this->user(2))
            ->patch(route('sms.auto-rules.toggle', $victim->id))
            ->assertStatus(404);

        $this->assertTrue((bool) $victim->fresh()->is_active, 'Store-A flag must be unchanged by a Store-B toggle.');
    }

    public function test_phase3_cross_store_destroy_is_blocked_and_row_survives(): void
    {
        $t1 = $this->template(1, 'T1');
        $victim = $this->rule(1, $t1->id, 'Victim Rule', 'InvoiceCreated');

        $this->actingAs($this->user(2))
            ->delete(route('sms.auto-rules.delete', $victim->id))
            ->assertStatus(404);

        $this->assertNull($victim->fresh()->deleted_at, 'Store-A rule must not be soft-deleted by a Store-B delete.');
    }

    public function test_phase3_own_update_still_succeeds(): void
    {
        $t1 = $this->template(1, 'T1');
        $mine = $this->rule(1, $t1->id, 'My Rule', 'InvoiceCreated');

        $this->actingAs($this->user(1))
            ->post(route('sms.auto-rules.update', $mine->id), [
                'rule_name'    => 'My Rule Renamed',
                'event_type'   => 'InvoiceCreated',
                'event_source' => 'invoice',
                'template_id'  => $t1->id,
                'trigger_time' => 'immediate',
                'days_offset'  => 0,
                'cooldown_days' => 0,
            ])
            ->assertRedirect();

        $this->assertSame('My Rule Renamed', $mine->fresh()->rule_name);
    }

    public function test_phase3_own_toggle_still_succeeds(): void
    {
        $t1 = $this->template(1, 'T1');
        $mine = $this->rule(1, $t1->id, 'My Rule', 'InvoiceCreated', true);

        $this->actingAs($this->user(1))
            ->patch(route('sms.auto-rules.toggle', $mine->id))
            ->assertOk()
            ->assertJson(['success' => true, 'is_active' => false]);

        $this->assertFalse((bool) $mine->fresh()->is_active);
    }

    public function test_phase3_own_destroy_still_succeeds(): void
    {
        $t1 = $this->template(1, 'T1');
        $mine = $this->rule(1, $t1->id, 'My Rule', 'InvoiceCreated');

        $this->actingAs($this->user(1))
            ->delete(route('sms.auto-rules.delete', $mine->id))
            ->assertRedirect();

        $this->assertNotNull($mine->fresh()->deleted_at, 'Store-A must be able to delete its own rule.');
    }

    /* ══════════════ PHASE 4: cross-tenant write via updateAutoStatus ═════════ */

    public function test_phase4_toggling_an_event_only_changes_the_acting_store(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');
        $ruleA = $this->rule(1, $t1->id, 'Store One Rule', 'InvoiceCreated', true);
        $ruleB = $this->rule(2, $t2->id, 'Store Two Rule', 'InvoiceCreated', true);

        // Store 2 turns the event OFF.
        $this->actingAs($this->user(2))
            ->post(route('messaging.settings.status'), [
                'event_type' => 'InvoiceCreated',
                'is_active'  => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse((bool) $ruleB->fresh()->is_active, 'Store B\'s rule must be turned off.');
        $this->assertTrue((bool) $ruleA->fresh()->is_active, 'Store A\'s rule for the SAME event_type must be untouched.');
    }

    /* ══════════════ PHASE 5: per-store unique event_type ═══════════════════ */

    public function test_phase5_two_stores_can_share_the_same_event_type(): void
    {
        $t1 = $this->template(1, 'T1');
        $t2 = $this->template(2, 'T2');

        // Store 1 creates InvoiceCreated.
        $this->actingAs($this->user(1))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t1->id, 'InvoiceCreated'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Store 2 must now ALSO be able to create InvoiceCreated (previously blocked).
        $this->actingAs($this->user(2))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t2->id, 'InvoiceCreated'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SmsAutoRule::withoutGlobalScope('store_id')->where('event_type', 'InvoiceCreated')->count());
    }

    public function test_phase5_same_store_cannot_duplicate_event_type(): void
    {
        $t1 = $this->template(1, 'T1');

        $this->actingAs($this->user(1))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t1->id, 'InvoiceCreated'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Control: the SAME store cannot create a second rule for the same event_type.
        $this->actingAs($this->user(1))
            ->post(route('sms.auto-rules.store'), $this->validRulePayload($t1->id, 'InvoiceCreated'))
            ->assertSessionHasErrors('event_type');

        $this->assertSame(1, SmsAutoRule::withoutGlobalScope('store_id')->where('store_id', 1)->where('event_type', 'InvoiceCreated')->count());
    }

    /* ══════════════ PHASE 6: cross-tenant EMI recipient leak ═══════════════ */

    private function emiCustomer(int $storeId, string $name, string $mobile): DbCustomer
    {
        return DbCustomer::create([
            'store_id'      => $storeId,
            'customer_name' => $name,
            'customer_code' => 'C-' . strtoupper(uniqid()),
            'mobile'        => $mobile,
            'status'        => 1,
        ]);
    }

    private function emiSale(int $storeId, DbCustomer $customer): DbEmiSale
    {
        $sale = DbSale::create([
            'store_id'      => $storeId,
            'customer_id'   => $customer->id,
            'sales_code'    => 'EMI-' . strtoupper(uniqid()),
            'sales_date'    => now()->toDateString(),
            'subtotal'      => 900,
            'grand_total'   => 900,
            'paid_amount'   => 0,
            'status'        => 1,
        ]);

        return DbEmiSale::create([
            'sale_id'            => $sale->id,
            'customer_id'        => $customer->id,
            'loan_amount'        => 900,
            'total_payable'      => 900,
            'duration_months'    => 3,
            'monthly_installment' => 300,
            'start_date'         => now()->toDateString(),
            'status'             => 'Active',
        ]);
    }

    public function test_phase6_static_resolver_scopes_emi_recipients_by_explicit_store(): void
    {
        $custA = $this->emiCustomer(1, 'EMI Cust A', '01900000001');
        $custB = $this->emiCustomer(2, 'EMI Cust B', '01900000002');
        $this->emiSale(1, $custA);
        $this->emiSale(2, $custB);

        // No auth context (simulates the queue) — the explicit store is the only scope.
        $idsStore2 = SmsSendController::resolveRecipients('emi', [], 2)->pluck('id')->all();
        $idsStore1 = SmsSendController::resolveRecipients('emi', [], 1)->pluck('id')->all();

        $this->assertSame([$custB->id], $idsStore2, 'Store 2 must only pull Store 2 EMI customers.');
        $this->assertSame([$custA->id], $idsStore1, 'Store 1 must only pull Store 1 EMI customers.');
        $this->assertNotContains($custA->id, $idsStore2, 'No cross-tenant EMI recipient leak.');
    }

    public function test_phase6_http_count_endpoint_is_store_scoped(): void
    {
        $custA = $this->emiCustomer(1, 'EMI Cust A', '01900000011');
        $custB = $this->emiCustomer(2, 'EMI Cust B', '01900000012');
        $this->emiSale(1, $custA);
        $this->emiSale(2, $custB);

        $res = $this->actingAs($this->user(2))->get(route('sms.send.count', ['target' => 'emi']));
        $res->assertOk()->assertJson(['count' => 1]);

        $res1 = $this->actingAs($this->user(1))->get(route('sms.send.count', ['target' => 'emi']));
        $res1->assertOk()->assertJson(['count' => 1]);
    }

    public function test_phase6_queued_campaign_pulls_recipients_for_the_campaigns_own_store(): void
    {
        $custA = $this->emiCustomer(1, 'EMI Cust A', '01900000021');
        $custB = $this->emiCustomer(2, 'EMI Cust B', '01900000022');
        $this->emiSale(1, $custA);
        $this->emiSale(2, $custB);

        $campaign = SmsCampaign::create([
            'store_id'         => 2,
            'name'             => 'Store Two EMI Blast',
            'target_type'      => 'emi',
            'target_filters'   => ['custom_message' => 'Hello {customer_name}'],
            'status'           => 'Scheduled',
            'total_recipients' => 0,
            'created_by'       => 1,
        ]);

        Queue::fake();

        // Faithful queue simulation: NO auth/request context (exactly how the
        // queued job runs in production). The job must target the campaign's own
        // store (2) — never fall back to the default store.
        (new DispatchCampaignJob($campaign->id))->handle();

        Queue::assertPushed(SendSingleSmsJob::class, 1);

        // SendSingleSmsJob keeps its payload protected; read it via reflection.
        $pushed = Queue::pushed(SendSingleSmsJob::class);
        $this->assertSame(1, $pushed->count());

        $optionsRef = new \ReflectionProperty(SendSingleSmsJob::class, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($pushed->first());

        $this->assertSame(2, (int) $options['store_id'], 'Job must carry the campaign\'s own store.');
        $this->assertSame($custB->id, $options['customer_id'], 'Only Store 2\'s EMI customer may be targeted.');
        $this->assertNotSame($custA->id, $options['customer_id'], 'Store 1\'s EMI customer must NOT leak into Store 2\'s blast.');
    }

    /* ══════════════ GAP 2: soft-deleted rules must not fire ═══════════════ */

    public function test_gap2_soft_deleted_rule_does_not_fire_and_active_rule_still_fires(): void
    {
        config(['sms.sandbox' => true]);
        Cache::flush();

        $customer = $this->emiCustomer(1, 'Gap Two Customer', '01877000001');
        $warehouse = DbWarehouse::create([
            'store_id' => 1, 'warehouse_name' => 'Gap Two WH', 'status' => 1,
        ]);
        $sale = DbSale::create([
            'store_id'      => 1,
            'warehouse_id'  => $warehouse->id,
            'customer_id'   => $customer->id,
            'sales_code'    => 'GAP2-' . strtoupper(uniqid()),
            'sales_date'    => now()->toDateString(),
            'subtotal'      => 100,
            'grand_total'   => 100,
            'paid_amount'   => 100,
            'payment_status' => 'Paid',
            'status'        => 1,
        ]);

        $tpl = $this->template(1, 'Gap Two Template');

        // Event A: the ONLY rule for 'LargeTransactionAlert' is soft-deleted.
        $deletedRule = $this->rule(1, $tpl->id, 'Gap Two Deleted Rule', 'LargeTransactionAlert', true);
        $deletedRule->delete();
        $this->assertNotNull($deletedRule->fresh()->deleted_at, 'Precondition: the rule must be soft-deleted.');

        // Event B (control): an ACTIVE rule for a DIFFERENT event.
        $this->rule(1, $tpl->id, 'Gap Two Active Rule', 'ServiceDueReminder', true);

        $trigger = app(SmsTriggerService::class);

        // 1. Trigger the event whose only rule is soft-deleted.
        $trigger->trigger('LargeTransactionAlert', $sale);
        $this->assertSame(0, SmsLog::where('phone', '01877000001')->count(), 'A soft-deleted rule must NOT fire any SMS.');

        // 2. Control: trigger the event with a live rule — it must fire normally.
        $trigger->trigger('ServiceDueReminder', $sale);
        $this->assertSame(1, SmsLog::where('phone', '01877000001')->count(), 'A live rule for another event must still fire.');
    }
}
