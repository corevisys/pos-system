<?php

namespace App\Support;

use App\Models\DbStore;

/**
 * Process-lifetime memoization holder for the active store's settings,
 * timezone, and currency symbol.
 *
 * These values were previously kept in function/method-local `static` variables
 * (store_settings(), AppServiceProvider::configureStoreTimezone(),
 * AppServiceProvider::resolveCurrencySymbol()). Locals like that live for the
 * entire PHP process, so in the test suite a store row seeded by one test (e.g.
 * "SMS Store" in CustomerAddedSmsTest) would be cached and then leak into every
 * later test that calls store_settings() without $fresh — even after
 * RefreshDatabase has replaced the rows — causing cross-suite failures such as
 * PageTitleTest asserting <title>Dashboard - SMS Store</title>.
 *
 * Hoisting the cache here lets tests drop it between cases via
 * StoreSettingsCache::flush() while keeping production behaviour identical.
 */
class StoreSettingsCache
{
    /** @var DbStore|null The memoized active store (null = not yet resolved). */
    public static ?DbStore $store = null;

    /** @var string|null The memoized resolved timezone identifier. */
    public static ?string $timezone = null;

    /** @var string|null The memoized active currency symbol. */
    public static ?string $currencySymbol = null;

    /**
     * Drop every memoized value so the next access re-reads from the database.
     */
    public static function flush(): void
    {
        static::$store = null;
        static::$timezone = null;
        static::$currencySymbol = null;
    }
}
