<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
