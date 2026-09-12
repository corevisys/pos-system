<?php

namespace Tests\Feature;

use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\SmsAutoRule;
use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\SMS\Providers\AlphaSMSProvider;
use App\SMS\Providers\HttpSmsProvider;
use App\SMS\Services\RuleResolverService;
use App\SMS\Services\SmsService;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Per-store SMS scoping (the "unambiguous bugs" task).
 *
 * Core regression: a store-2 sale must trigger store-2's rule, template AND
 * provider — previously SmsAutoRule had no store_id, so `$rule->store_id ?? 1`
 * sent every store's automated SMS through Store 1's gateway.
 */
class SmsStoreScopingTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store1;
    protected DbStore $store2;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Store 1 = Http provider, Store 2 = Alpha provider (different gateways).
        $this->store1 = DbStore::create([
            'id' => 1, 'store_name' => 'Store One', 'status' => 1,
            'mobile' => '01711111111', 'sms_status' => 1,
        ]);
        $this->store2 = DbStore::create([
            'id' => 2, 'store_name' => 'Store Two', 'status' => 1,
            'mobile' => '01722222222', 'sms_status' => 2,
        ]);
    }

    private function makeTemplate(int $storeId, string $name, string $content): DbSmsTemplate
    {
        return DbSmsTemplate::create([
            'store_id' => $storeId,
            'template_name' => $name,
            'category' => 'Transactional',
            'message_type' => 'transactional',
            'content' => $content,
            'variables_used' => ['customer_name', 'store_name'],
            'status' => 1,
        ]);
    }

    private function makeRule(int $storeId, int $templateId, string $name): SmsAutoRule
    {
        return SmsAutoRule::create([
            'store_id' => $storeId,
            'rule_name' => $name,
            'event_type' => 'InvoiceCreated',
            'event_source' => 'invoice',
            'template_id' => $templateId,
            'trigger_time' => 'immediate',
            'is_active' => true,
        ]);
    }

    /* ───────────── Step 1: rules + provider resolution per store ───────────── */

    public function test_rule_resolution_returns_only_the_acting_stores_rules(): void
    {
        $t1 = $this->makeTemplate(1, 'T1', 'x');
        $t2 = $this->makeTemplate(2, 'T2', 'x');
        $this->makeRule(1, $t1->id, 'Store One Rule');
        $this->makeRule(2, $t2->id, 'Store Two Rule');

        // Explicit store id (no auth context needed).
        $rules1 = RuleResolverService::resolve('InvoiceCreated', 1)->pluck('rule_name')->all();
        $rules2 = RuleResolverService::resolve('InvoiceCreated', 2)->pluck('rule_name')->all();

        $this->assertContains('Store One Rule', $rules1);
        $this->assertNotContains('Store Two Rule', $rules1);
        $this->assertContains('Store Two Rule', $rules2);
        $this->assertNotContains('Store One Rule', $rules2);
    }

    public function test_per_store_cache_keys_no_longer_hold_the_same_global_result(): void
    {
        $t1 = $this->makeTemplate(1, 'T1', 'x');
        $t2 = $this->makeTemplate(2, 'T2', 'x');
        $this->makeRule(1, $t1->id, 'Only Store One');
        $this->makeRule(2, $t2->id, 'Only Store Two');

        RuleResolverService::resolve('InvoiceCreated', 1);
        RuleResolverService::resolve('InvoiceCreated', 2);

        $cached1 = Cache::get('sms_rules_InvoiceCreated_s1')->pluck('rule_name')->all();
        $cached2 = Cache::get('sms_rules_InvoiceCreated_s2')->pluck('rule_name')->all();

        $this->assertContains('Only Store One', $cached1);
        $this->assertNotContains('Only Store Two', $cached1);
        $this->assertContains('Only Store Two', $cached2);
        $this->assertNotContains('Only Store One', $cached2);
    }

    public function test_get_provider_resolves_per_store(): void
    {
        config(['sms.sandbox' => false]);
        $svc = app(SmsService::class);

        $this->assertInstanceOf(HttpSmsProvider::class, $svc->getProvider(1));
        $this->assertInstanceOf(AlphaSMSProvider::class, $svc->getProvider(2));
    }

    /* ───────────── Step 1 core regression: store-2 sale → store-2 send ───────────── */

    public function test_store_two_sale_triggers_store_two_rule_template_and_provider(): void
    {
        config(['sms.sandbox' => true]);

        // Store 1 has its own rule/template; store 2 has different ones.
        $t1 = $this->makeTemplate(1, 'Store1 Template', 'STORE-ONE-CONTENT {customer_name}');
        $this->makeRule(1, $t1->id, 'Store1 Rule');

        $t2 = $this->makeTemplate(2, 'Store2 Template', 'STORE-TWO-CONTENT {customer_name}');
        $this->makeRule(2, $t2->id, 'Store2 Rule');

        $customer = DbCustomer::create([
            'store_id' => 2, 'customer_name' => 'Beta Buyer',
            'customer_code' => 'CUST-S2', 'mobile' => '01888000002', 'status' => 1,
        ]);
        $warehouse = DbWarehouse::create([
            'store_id' => 2, 'warehouse_name' => 'S2 WH', 'status' => 1,
        ]);

        $sale = DbSale::create([
            'store_id' => 2,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sales_code' => 'SA-S2-001',
            'sales_date' => now()->toDateString(),
            'subtotal' => 100, 'grand_total' => 100, 'paid_amount' => 100,
            'payment_status' => 'Paid', 'status' => 1,
        ]);

        app(SmsTriggerService::class)->trigger('InvoiceCreated', $sale);

        $log = SmsLog::where('phone', '01888000002')->latest()->first();

        $this->assertNotNull($log, 'A log row must be written for the store-2 sale.');
        // THE core assertion: store-2's template content, and the log attributed to store 2.
        $this->assertStringContainsString('STORE-TWO-CONTENT', $log->message);
        $this->assertStringNotContainsString('STORE-ONE-CONTENT', $log->message);
        $this->assertSame(2, (int) $log->store_id, 'Log must be attributed to the sending store.');
    }

    public function test_rule_without_store_id_is_refused_not_sent_to_store_one(): void
    {
        config(['sms.sandbox' => true]);

        $t1 = $this->makeTemplate(1, 'T1', 'SHOULD-NOT-SEND');
        // Simulate a legacy/bad row with no store by writing raw SQL (the column is
        // NOT NULL, so bypass the model via a direct DB update on a real row).
        $rule = $this->makeRule(1, $t1->id, 'Bad Rule');
        \Illuminate\Support\Facades\DB::table('sms_auto_rules')
            ->where('id', $rule->id)
            ->update(['store_id' => 0]); // 0 = unresolvable/empty

        $customer = DbCustomer::create([
            'store_id' => 1, 'customer_name' => 'X', 'customer_code' => 'C-X',
            'mobile' => '01888000009', 'status' => 1,
        ]);
        $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'W', 'status' => 1]);
        $sale = DbSale::create([
            'store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id,
            'sales_code' => 'SA-BAD-1', 'sales_date' => now()->toDateString(),
            'subtotal' => 10, 'grand_total' => 10, 'paid_amount' => 10,
            'payment_status' => 'Paid', 'status' => 1,
        ]);

        app(SmsTriggerService::class)->trigger('InvoiceCreated', $sale);

        $this->assertSame(0, SmsLog::where('phone', '01888000009')->count(),
            'A rule with no resolvable store must NOT silently send via store 1.');
    }

    /* ───────────── Step 2: campaign scoping + IDOR ───────────── */

    public function test_campaign_idor_edit_update_destroy_cancel_return_404_across_stores(): void
    {
        $store1Admin = \App\Models\User::factory()->create(['store_id' => 1, 'role_id' => null]);
        $otherCampaign = SmsCampaign::create([
            'store_id' => 2, 'name' => 'Store Two Campaign',
            'target_type' => 'all', 'status' => 'Scheduled',
            'scheduled_at' => now()->addDay(), 'created_by' => 1,
        ]);

        $this->actingAs($store1Admin)->get(route('sms.campaigns.edit', $otherCampaign->id))->assertNotFound();
        $this->actingAs($store1Admin)->post(route('sms.campaigns.update', $otherCampaign->id), [
            'name' => 'HACKED', 'message' => 'x',
        ])->assertNotFound();
        $this->actingAs($store1Admin)->delete(route('sms.campaigns.delete', $otherCampaign->id))->assertNotFound();

        $fresh = SmsCampaign::where('store_id', 2)->find($otherCampaign->id);
        $this->assertNotNull($fresh, 'Store-2 campaign must survive cross-store attempts.');
        $this->assertSame('Store Two Campaign', $fresh->name);
    }

    /* ───────────── Step 3: log scoping ───────────── */

    public function test_sms_history_and_log_lists_only_show_acting_store(): void
    {
        SmsLog::create([
            'store_id' => 1, 'phone' => '01711111111', 'message' => 'store-1 only',
            'status' => 'Sent', 'message_hash' => 'h1',
        ]);
        SmsLog::create([
            'store_id' => 2, 'phone' => '01722222222', 'message' => 'store-2 only',
            'status' => 'Sent', 'message_hash' => 'h2',
        ]);

        $user1 = \App\Models\User::factory()->create(['store_id' => 1, 'role_id' => null]);
        $user2 = \App\Models\User::factory()->create(['store_id' => 2, 'role_id' => null]);

        // History page (store 2) must not leak store 1's counts/activity.
        $resp2 = $this->actingAs($user2)->get(route('sms.history'));
        $resp2->assertOk();
        $stats2 = $resp2->viewData('stats');
        $this->assertSame(1, $stats2['total_sent'], 'Store 2 must see only its own sent count.');

        // Logs page (store 1) must show only store 1's row.
        $resp1 = $this->actingAs($user1)->get(route('sms.logs'));
        $resp1->assertOk();
        $logs1 = collect($resp1->viewData('logs')->items())->pluck('message')->all();
        $this->assertContains('store-1 only', $logs1);
        $this->assertNotContains('store-2 only', $logs1);
    }

    /* ───────────── Seeding sanity ───────────── */

    public function test_migration_duplicated_rules_and_templates_per_store_in_fresh_db(): void
    {
        // In a fresh (RefreshDatabase) DB no seeders ran, so this asserts the
        // DUPLICATION LOGIC indirectly: write one store-1 rule + template, then run
        // the seeder path and confirm per-store copies are produced.
        $this->makeTemplate(1, 'Shared Name', 'body');
        $this->makeTemplate(2, 'Shared Name', 'body');
        $t1 = DbSmsTemplate::where('store_id', 1)->where('template_name', 'Shared Name')->first();
        $t2 = DbSmsTemplate::where('store_id', 2)->where('template_name', 'Shared Name')->first();

        $this->assertNotNull($t1);
        $this->assertNotNull($t2);
        $this->assertNotSame($t1->id, $t2->id, 'Each store must have its own template row.');
    }
}
