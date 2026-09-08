<?php

use App\Models\DbStore;
use App\Providers\AppServiceProvider;
use App\Support\StoreSettingsCache;

if (!function_exists('store_settings')) {
    /**
     * Memoized store settings for the current request / process lifecycle.
     * Guarantees DbStore::first() is only queried ONCE per request.
     *
     * @param bool $fresh Force reload from database
     * @return DbStore|null
     */
    function store_settings(bool $fresh = false): ?DbStore
    {
        if ($fresh || StoreSettingsCache::$store === null) {
            StoreSettingsCache::$store = DbStore::first();
            if ($fresh && class_exists(AppServiceProvider::class)) {
                AppServiceProvider::resolveCurrencySymbol(true);
                AppServiceProvider::configureStoreTimezone(true);
            }
        }
        return StoreSettingsCache::$store;
    }
}

if (!function_exists('flush_store_settings_cache')) {
    /**
     * Drop the process-lifetime memoization for the active store, timezone and
     * currency symbol so the next store_settings()/resolveCurrencySymbol()/
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
     * 1. Authenticated user's store_id: auth()->user()->store_id (if logged in)
     * 2. Active store settings ID: store_settings($fresh)?->id (memoized)
     * 3. Fallback default ID: 1
     *
     * @param bool $fresh Force reload from database
     * @return int
     */
    function current_store_id(bool $fresh = false): int
    {
        if (auth()->check() && !empty(auth()->user()->store_id)) {
            return (int) auth()->user()->store_id;
        }

        $store = store_settings($fresh);
        if ($store && !empty($store->id)) {
            return (int) $store->id;
        }

        return 1;
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
