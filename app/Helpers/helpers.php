<?php

use App\Models\DbStore;
use App\Providers\AppServiceProvider;
use App\Support\StoreSettingsCache;
use Illuminate\Support\Facades\Cache;

if (!function_exists('default_store_id')) {
    /**
     * Resolve the fallback store id used when no user is authenticated
     * (CLI, queue workers, unauthenticated test requests).
     *
     * Order:
     * 1. A previously memoized 'default' store id
     * 2. The first db_store row
     * 3. Hard fallback 1
     *
     * Kept free of any current_store_id()/store_settings() call so it can be
     * used to break the mutual recursion between those two functions.
     *
     * @param bool $fresh Force a re-read from the database
     * @return int
     */
    function default_store_id(bool $fresh = false): int
    {
        if (!$fresh && array_key_exists('default', StoreSettingsCache::$store)) {
            $cached = StoreSettingsCache::$store['default'];
            if ($cached && !empty($cached->id)) {
                return (int) $cached->id;
            }
        }

        try {
            $id = DbStore::query()->orderBy('id')->value('id');
            if (!empty($id)) {
                return (int) $id;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet (fresh migrations)
        }

        return 1;
    }
}

if (!function_exists('store_id_cache_key')) {
    /**
     * Build the per-store cache key ('s{id}'), or 'default' when no acting
     * store id can be resolved.
     */
    function store_id_cache_key(?int $storeId = null): string
    {
        $resolved = $storeId;
        if ($resolved === null) {
            $resolved = (auth()->check() && !empty(auth()->user()->store_id))
                ? (int) auth()->user()->store_id
                : default_store_id();
        }
        return $resolved ? 's' . $resolved : 'default';
    }
}

if (!function_exists('store_settings')) {
    /**
     * Memoized store settings for the current request / process lifecycle,
     * keyed by the acting store's id so a multi-store deployment never serves
     * the first db_store row to a different store (the "cached-query
     * store-scoping trap" fixed in Warehouse).
     *
     * When a user is authenticated their User->store_id is authoritative;
     * otherwise the default (first) store is used.
     *
     * @param bool $fresh Force reload from database
     * @param int|null $storeId Acting store id (null = resolve current)
     * @return DbStore|null
     */
    function store_settings(bool $fresh = false, ?int $storeId = null): ?DbStore
    {
        $resolvedStoreId = $storeId;
        if ($resolvedStoreId === null && auth()->check() && !empty(auth()->user()->store_id)) {
            $resolvedStoreId = (int) auth()->user()->store_id;
        }

        // No acting store (unauthenticated / CLI): resolve the default (first)
        // store once and memoize it under BOTH 'default' and 's{id}', so the
        // provider and later authenticated requests reuse the same row without
        // re-querying db_store.
        if ($resolvedStoreId === null) {
            if ($fresh || !array_key_exists('default', StoreSettingsCache::$store)) {
                try {
                    $defaultStore = DbStore::query()->orderBy('id')->first();
                } catch (\Throwable $e) {
                    // db_store may not exist yet during early boot (fresh migrations)
                    $defaultStore = null;
                }
                StoreSettingsCache::$store['default'] = $defaultStore;
                if ($defaultStore && !empty($defaultStore->id)) {
                    StoreSettingsCache::$store['s' . $defaultStore->id] = $defaultStore;
                    if ($fresh && class_exists(AppServiceProvider::class)) {
                        AppServiceProvider::resolveCurrencySymbol(true, (int) $defaultStore->id);
                        AppServiceProvider::configureStoreTimezone(true, (int) $defaultStore->id);
                    }
                }
            }
            return StoreSettingsCache::$store['default'];
        }

        $cacheKey = 's' . $resolvedStoreId;
        if ($fresh || !array_key_exists($cacheKey, StoreSettingsCache::$store)) {
            try {
                StoreSettingsCache::$store[$cacheKey] = DbStore::where('id', $resolvedStoreId)->first();
            } catch (\Throwable $e) {
                StoreSettingsCache::$store[$cacheKey] = null;
                return null;
            }
            if ($fresh && class_exists(AppServiceProvider::class)) {
                AppServiceProvider::resolveCurrencySymbol(true, $resolvedStoreId);
                AppServiceProvider::configureStoreTimezone(true, $resolvedStoreId);
            }
        }
        return StoreSettingsCache::$store[$cacheKey];
    }
}

if (!function_exists('flush_store_settings_cache')) {
    /**
     * Drop the process-lifetime memoization for all stores, timezones and
     * currency symbols so the next store_settings()/resolveCurrencySymbol()/
     * configureStoreTimezone() call re-reads from the database.
     *
     * Used by the test suite (tests/TestCase.php) between test cases so a store
     * seeded by one test never leaks into another via the static cache.
     */
    function flush_store_settings_cache(): void
    {
        StoreSettingsCache::flush();
    }
}

if (!function_exists('current_store_id')) {
    /**
     * Resolves the current active store ID.
     *
     * Hierarchy:
     * 1. Explicit acting-store context (App\Services\StoreContext), bound by the
     *    SetCurrentStore middleware from the session (falling back to the user's own
     *    store). This is what lets a cross-store role act as a chosen store.
     * 2. Authenticated user's store_id (legacy behaviour; also the fallback when the
     *    context is unresolved — e.g. console/queue contexts or guest requests where
     *    middleware does not run).
     * 3. Memoized default store id (first db_store row)
     * 4. Fallback default ID: 1
     *
     * @param bool $fresh Force reload from database
     * @return int
     */
    function current_store_id(bool $fresh = false): int
    {
        try {
            $context = app(\App\Services\StoreContext::class);
            if ($context->isResolved() && $context->storeId() !== null) {
                return (int) $context->storeId();
            }
        } catch (\Throwable $e) {
            // Container not available this early (or context not bound) — fall
            // through to the legacy resolution below.
        }

        if (auth()->check() && !empty(auth()->user()->store_id)) {
            return (int) auth()->user()->store_id;
        }

        return default_store_id($fresh);
    }
}

if (!function_exists('store_scoped_cached_list')) {
    /**
     * Shared store-scoped dropdown-list cache accessor.
     *
     * The cache key follows the established per-store convention
     * ('db_<list>_s{storeId}', matching 'db_taxes_list_{store}' /
     * 'db_warehouses_list_s{storeId}'). Because the underlying models are
     * StoreScoped, both the key AND the query are store-isolated, so a Store-2
     * user can never be served Store-1's cached dropdown list.
     *
     * @param string $keyPrefix e.g. 'db_categories_list' (no store suffix)
     * @param int $ttl
     * @param callable $resolver returns the collection to cache
     * @return mixed
     */
    function store_scoped_cached_list(string $keyPrefix, int $ttl, callable $resolver, ?int $storeId = null)
    {
        $storeId = $storeId ?? current_store_id();
        $key = $keyPrefix . '_s' . $storeId;
        return Cache::remember($key, $ttl, $resolver);
    }
}

if (!function_exists('format_currency')) {
    /**
     * Formats monetary values according to $store->decimals and $store->currency_placement.
     *
     * @param float|int|string|null $amount
     * @param bool $withSymbol (default: true)
     * @param int|null $decimals (null = use $store->decimals ?? 2)
     * @return string
     */
    function format_currency($amount, bool $withSymbol = true, ?int $decimals = null): string
    {
        $store = store_settings();
        $decimalPlaces = $decimals ?? ($store->decimals ?? 2);
        $isNegative = ((float) ($amount ?? 0)) < 0;
        $absAmount = abs((float) ($amount ?? 0));
        $formatted = number_format($absAmount, $decimalPlaces);

        if (!$withSymbol) {
            return ($isNegative ? '-' : '') . $formatted;
        }

        $symbol = AppServiceProvider::resolveCurrencySymbol();
        $placement = $store->currency_placement ?? 'before';
        $sign = $isNegative ? '-' : '';

        return ($placement === 'after')
            ? ($sign . $formatted . ' ' . $symbol)
            : ($sign . $symbol . ' ' . $formatted);
    }
}

if (!function_exists('format_quantity')) {
    /**
     * Formats quantities according to $store->qty_decimals.
     *
     * @param float|int|string|null $quantity
     * @param int|null $decimals (null = use $store->qty_decimals ?? 2)
     * @return string
     */
    function format_quantity($quantity, ?int $decimals = null): string
    {
        $store = store_settings();
        $qtyDecimals = $decimals ?? ($store->qty_decimals ?? 2);
        return number_format((float) ($quantity ?? 0), $qtyDecimals);
    }
}
