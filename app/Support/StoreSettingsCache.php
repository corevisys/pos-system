<?php

namespace App\Support;

use App\Models\DbStore;

/**
 * Process-lifetime memoization holder for store settings, timezone, and
 * currency symbol, keyed by store id.
 *
 * Previously these values were kept in single static slots ($store,
 * $timezone, $currencySymbol) with no store-id dimension, so in a multi-store
 * deployment the FIRST row in db_store (DbStore::first()) was cached and
 * served to every store — the same "cached-query store-scoping trap" already
 * found and fixed in Warehouse.
 *
 * Each entry is keyed by 's{storeId}'; the special key 'default' is used when
 * no store can be resolved (unauthenticated / CLI contexts). array_key_exists()
 * is used to distinguish "not yet resolved" from "resolved to null" (i.e. the
 * store row does not exist).
 *
 * Hoisting the cache here lets tests drop it between cases via
 * StoreSettingsCache::flush() while keeping production behaviour identical.
 */
class StoreSettingsCache
{
    /** @var array<string, DbStore|null> The memoized store rows by cache key. */
    public static array $store = [];

    /** @var array<string, string|null> The memoized resolved timezone identifiers by cache key. */
    public static array $timezone = [];

    /** @var array<string, string|null> The memoized active currency symbols by cache key. */
    public static array $currencySymbol = [];

    /**
     * Drop every memoized value so the next access re-reads from the database.
     */
    public static function flush(): void
    {
        static::$store = [];
        static::$timezone = [];
        static::$currencySymbol = [];
    }
}
