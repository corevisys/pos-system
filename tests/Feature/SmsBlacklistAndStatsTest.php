<?php

namespace Tests\Feature;

use App\Models\DbSmsTemplate;
use App\Models\DbStore;
use App\Models\SmsAutoRule;
use App\Models\SmsBlacklist;
use App\Models\SmsDailyStat;
use App\Models\SmsLog;
use App\SMS\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Per-store SMS blacklist, daily stats and duplicate suppression.
 *
 * (The prior task established that sms_logs.store_id exists; Step 3 here depends
 * on it, and these tests exercise it directly.)
 */
class SmsBlacklistAndStatsTest extends TestCase
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

        config(['sms.sandbox' => true]); // Sandbox provider needs no credentials.
    }

    private function admin(int $storeId): \App\Models\User
    {
        $role = \App\Models\DbRole::create([
            'store_id' => $storeId,
            'role_name' => 'SMS Admin ' . $storeId . ' ' . uniqid(),
            'status' => 1,
        ]);
        \App\Models\DbPermission::create([
            'role_id' => $role->id,
            'store_id' => $storeId,
            'permissions' => ['sms_blacklist_view', 'sms_blacklist_add', 'sms_blacklist_delete'],
        ]);

        return \App\Models\User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    /* ═════════════ Step 1: per-store blacklist CRUD + uniqueness ═════════════ */

    public function test_same_phone_can_be_blacklisted_by_two_different_stores(): void
    {
        $a = SmsBlacklist::create(['store_id' => 1, 'phone' => '01700000009', 'reason' => 'a']);
        $b = SmsBlacklist::create(['store_id' => 2, 'phone' => '01700000009', 'reason' => 'b']);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, SmsBlacklist::where('phone', '01700000009')->count());
    }

    public function test_same_phone_cannot_be_blacklisted_twice_in_the_same_store(): void
    {
        SmsBlacklist::create(['store_id' => 1, 'phone' => '01700000010']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SmsBlacklist::create(['store_id' => 1, 'phone' => '01700000010']);
    }

    public function test_blacklist_store_scoped_crud_via_http(): void
    {
        $user1 = $this->admin(1);
        $user2 = $this->admin(2);

        // Add via store 1.
        $this->actingAs($user1)->post(route('sms.blacklist.store'), [
            'phone' => '01700000011',
            'reason' => 'opt-out',
        ])->assertRedirect(route('sms.blacklist'));

        $this->assertDatabaseHas('sms_blacklists', ['store_id' => 1, 'phone' => '01700000011']);

        // Same phone again in store 1 → validation error.
        $this->actingAs($user1)->post(route('sms.blacklist.store'), ['phone' => '01700000011'])
            ->assertSessionHasErrors('phone');

        // Same phone in store 2 → allowed.
        $this->actingAs($user2)->post(route('sms.blacklist.store'), ['phone' => '01700000011'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sms_blacklists', ['store_id' => 2, 'phone' => '01700000011']);

        // Store 1 cannot see store 2's row.
        $entry = SmsBlacklist::where('store_id', 1)->first();
        $list = $this->actingAs($user1)->get(route('sms.blacklist'));
        $list->assertOk();
        $phones = collect($list->viewData('blacklists')->items())->pluck('phone')->all();
        $this->assertSame(1, SmsBlacklist::where('store_id', 1)->count());
        $this->assertContains('01700000011', $phones);
    }

    public function test_blacklist_delete_is_cross_store_safe(): void
    {
        $user1 = $this->admin(1);
        $store2Entry = SmsBlacklist::create(['store_id' => 2, 'phone' => '01700000012']);

        $this->actingAs($user1)
            ->delete(route('sms.blacklist.delete', $store2Entry->id))
            ->assertNotFound();

        $this->assertNotNull(SmsBlacklist::find($store2Entry->id), 'Store 2 entry must survive.');
    }

    /* ═════════════ Step 1: enforcement in the send path ═════════════ */

    public function test_blacklisted_phone_in_acting_store_is_not_sent_to(): void
    {
        SmsBlacklist::create(['store_id' => 1, 'phone' => '01888000001']);

        $result = app(SmsService::class)->sendSingle('01888000001', 'Hello', ['store_id' => 1]);

        $this->assertFalse($result->success);
        $this->assertSame('BLACKLISTED', $result->error_code);
        $this->assertSame(0, SmsLog::where('phone', '01888000001')->count(), 'No log row may be written for a blocked send.');
    }

    public function test_phone_blacklisted_in_a_DIFFERENT_store_can_still_be_sent_to(): void
    {
        // Blacklisted by store 1 only.
        SmsBlacklist::create(['store_id' => 1, 'phone' => '01888000002']);

        $result = app(SmsService::class)->sendSingle('01888000002', 'Hello', ['store_id' => 2]);

        $this->assertTrue($result->success, 'Store 2 must not be blocked by store 1\'s blacklist.');
        $this->assertSame(1, SmsLog::where('phone', '01888000002')->where('store_id', 2)->count());
    }

    /* ═════════════ Step 2: per-store daily stats ═════════════ */

    public function test_sends_in_two_stores_same_day_create_two_separate_stats_rows(): void
    {
        $svc = app(SmsService::class);

        $svc->sendSingle('01999000001', 'Store one message', ['store_id' => 1]);
        $svc->sendSingle('01999000002', 'Store two message', ['store_id' => 2]);
        $svc->sendSingle('01999000003', 'Store two second', ['store_id' => 2]);

        // Query with the SAME normalized value the service writes: the `date` cast
        // stores "Y-m-d 00:00:00", so a bare "Y-m-d" would match nothing.
        $rows = SmsDailyStat::where('date', now()->startOfDay())->get();

        $this->assertCount(2, $rows, 'One row per store per day, not one global row.');

        $store1 = $rows->firstWhere('store_id', 1);
        $store2 = $rows->firstWhere('store_id', 2);

        $this->assertNotNull($store1);
        $this->assertNotNull($store2);
        $this->assertSame(1, (int) $store1->total_sent);
        $this->assertSame(2, (int) $store2->total_sent);
    }

    public function test_same_store_same_day_increments_a_single_row(): void
    {
        $svc = app(SmsService::class);
        $svc->sendSingle('01999000010', 'One', ['store_id' => 1]);
        $svc->sendSingle('01999000011', 'Two', ['store_id' => 1]);

        $this->assertSame(1, SmsDailyStat::where('store_id', 1)->where('date', now()->startOfDay())->count());
        $this->assertSame(2, (int) SmsDailyStat::where('store_id', 1)->first()->total_sent);
    }

    /* ═════════════ Step 3: per-store duplicate suppression ═════════════ */

    public function test_same_phone_and_message_twice_in_one_store_is_suppressed(): void
    {
        $svc = app(SmsService::class);

        $first = $svc->sendSingle('01777000055', 'Identical body', ['store_id' => 1]);
        $second = $svc->sendSingle('01777000055', 'Identical body', ['store_id' => 1]);

        $this->assertTrue($first->success);
        $this->assertFalse($second->success);
        $this->assertSame('DUPLICATE', $second->error_code);
        $this->assertSame(1, SmsLog::where('phone', '01777000055')->where('store_id', 1)->count());
    }

    public function test_same_phone_and_message_from_two_stores_is_NOT_suppressed(): void
    {
        $svc = app(SmsService::class);

        $first = $svc->sendSingle('01777000056', 'Identical body', ['store_id' => 1]);
        $second = $svc->sendSingle('01777000056', 'Identical body', ['store_id' => 2]);

        $this->assertTrue($first->success);
        $this->assertTrue($second->success, 'Store 2 must not be treated as a duplicate of store 1.');
        $this->assertSame(1, SmsLog::where('phone', '01777000056')->where('store_id', 1)->count());
        $this->assertSame(1, SmsLog::where('phone', '01777000056')->where('store_id', 2)->count());
    }

    /* ═════════════ Schema assertions ═════════════ */

    public function test_composite_uniques_and_store_columns_exist(): void
    {
        foreach (['sms_blacklists', 'sms_daily_stats'] as $table) {
            $indexes = collect(\Illuminate\Support\Facades\Schema::getIndexes($table));

            $hasStoreId = \Illuminate\Support\Facades\Schema::hasColumn($table, 'store_id');
            $this->assertTrue($hasStoreId, "{$table}.store_id must exist.");
        }

        $bl = collect(\Illuminate\Support\Facades\Schema::getIndexes('sms_blacklists'))
            ->firstWhere('name', 'sms_blacklists_store_phone_unique');
        $this->assertNotNull($bl);
        $this->assertSame(['store_id', 'phone'], $bl['columns']);
        $this->assertTrue((bool) $bl['unique']);

        $ds = collect(\Illuminate\Support\Facades\Schema::getIndexes('sms_daily_stats'))
            ->firstWhere('name', 'sms_daily_stats_store_date_unique');
        $this->assertNotNull($ds);
        $this->assertSame(['store_id', 'date'], $ds['columns']);
        $this->assertTrue((bool) $ds['unique']);

        // The old GLOBAL uniques must be gone.
        $this->assertNull(
            collect(\Illuminate\Support\Facades\Schema::getIndexes('sms_blacklists'))
                ->firstWhere('name', 'sms_blacklists_phone_unique'),
            'The global phone unique must have been dropped.'
        );
        $this->assertNull(
            collect(\Illuminate\Support\Facades\Schema::getIndexes('sms_daily_stats'))
                ->firstWhere('name', 'sms_daily_stats_date_unique'),
            'The global date unique must have been dropped.'
        );
    }
}
