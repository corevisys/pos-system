<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // MySQL parity for fixtures that pin well-known primary keys.
        //
        // Many fixtures do e.g. DbRole::firstOrCreate(['id' => 1], [...]). 'id' is not
        // in any model's $fillable, so it is normally dropped by mass assignment and
        // the row gets an auto-increment id. On SQLite (:memory:) that is harmless —
        // each test starts from a freshly built DB whose auto-increment resets to 1.
        // On MySQL the shared test DB is not recreated per test: RefreshDatabase wraps
        // each test in a transaction, but MySQL auto-increment counters are
        // NON-transactional and keep climbing across rollbacks, so id 1 stops existing
        // after the first test and later User::factory()->create(['role_id' => 1])
        // calls hit a foreign-key violation (users.role_id -> db_roles.id).
        //
        // The fix is the PinsExplicitIdInTests trait on the six id-pinning models
        // (User, DbRole, DbStore, DbCurrency, DbLanguage, DbPermission), which makes
        // 'id' fillable ONLY while the test runner is active. It is deliberately NOT a
        // global Model::unguard(): that flag is shared by every model subclass and
        // would re-enable mass assignment everywhere, surfacing phantom attributes such
        // as account_number (passed to AcAccount even though ac_accounts has no such
        // column). See App\Models\Concerns\PinsExplicitIdInTests.

        // The active store's settings / timezone / currency symbol are memoized for the whole
        // process lifetime (App\Support\StoreSettingsCache). RefreshDatabase only resets DB rows,
        // never this static cache, so a store seeded by one test (e.g. "SMS Store" in
        // CustomerAddedSmsTest) leaks into every later test that reads store_settings() without
        // $fresh — e.g. PageTitleTest asserting "<title>Dashboard - SMS Store</title>" instead of
        // the seeded store name.
        //
        // Flush after the application/migrations are ready and before this test seeds its own
        // data. flush_store_settings_cache() only nulls the static holder (no DB query), so it is
        // safe at any point here; the next store_settings() call re-reads the freshly seeded row.
        if (function_exists('flush_store_settings_cache')) {
            flush_store_settings_cache();
        }

        // Test-fixture convenience: many existing fixtures create a role named
        // "Super Admin" as shorthand for "make this a super admin". That name-based
        // grant is DISABLED in production (DbRole::$seedSuperAdminByName defaults
        // to false) precisely so it can never be a privilege-escalation vector via
        // any application request. Opting in here keeps those fixtures working
        // without weakening the production behaviour.
        \App\Models\DbRole::seedSuperAdminByName(true);
    }
}
