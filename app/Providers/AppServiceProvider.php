<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use App\Models\DbStore;
use App\Models\DbCurrency;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Helpers/helpers.php');

        if (PHP_OS_FAMILY === 'Windows') {
            $mysqlPath = env('DUMP_BINARY_PATH', 'C:\\xampp\\mysql\\bin');
            if ($mysqlPath) {
                putenv("PATH=" . getenv("PATH") . ";" . $mysqlPath);
            }
            // Ensure SystemRoot and windir are set for Winsock/Socket initialization
            if (!getenv('SystemRoot')) {
                putenv("SystemRoot=" . ($SystemRoot = getenv('SystemRoot') ?: 'C:\\Windows'));
            }
            if (!getenv('windir')) {
                putenv("windir=" . ($windir = getenv('windir') ?: 'C:\\Windows'));
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(\App\Models\DbRole::class, \App\Policies\RolePolicy::class);

        // Registered Observers
        \App\Models\DbSale::observe(\App\Observers\SMSObserver::class);

        // Configure active store timezone dynamically across all runtime contexts
        self::configureStoreTimezone();

        // Share active currency symbol globally across all views (including test requests)
        View::share('currencySymbol', self::resolveCurrencySymbol());

        // Avoid bootstrapping web-specific behavior when running artisan commands (e.g. migrations)
        if (app()->runningInConsole()) {
            return;
        }

        $isLocalHost = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true);
        if (env('FORCE_HTTPS', false) || (app()->isProduction() && !$isLocalHost) || request()->isSecure()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Eager load permissions for the sidebar if user is logged in
        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                auth()->user()->load(['role.permissions']);
            }
        });
    }

    /**
     * Resolve the acting store id used to key the per-store caches.
     *
     * An explicit id (threaded from store_settings()) always wins. Otherwise
     * the authenticated user's store_id is used. When neither is available the
     * caller falls back to the dedicated 'default' cache key — we deliberately
     * do NOT guess an id from other cached entries, which could leak another
     * store's settings.
     */
    private static function resolveCacheStoreId(?int $storeId = null): ?int
    {
        if ($storeId !== null) {
            return (int) $storeId;
        }

        if (auth()->check() && !empty(auth()->user()->store_id)) {
            return (int) auth()->user()->store_id;
        }

        return null;
    }

    /**
     * Resolve the store row for a cache key.
     *
     * Prefers the memoized settings cache (so a request that already resolved
     * its store pays no extra query); otherwise delegates to store_settings(),
     * which is boot-safe (try/catch → null when db_store does not exist yet).
     */
    private static function cachedStoreFor(?int $resolvedStoreId, string $cacheKey): ?DbStore
    {
        if (array_key_exists($cacheKey, \App\Support\StoreSettingsCache::$store)) {
            return \App\Support\StoreSettingsCache::$store[$cacheKey];
        }

        if (!function_exists('store_settings')) {
            return null;
        }

        return store_settings(false, $resolvedStoreId);
    }

    /**
     * Resolve and configure the acting store's timezone.
     *
     * Sets date_default_timezone_set() and config(['app.timezone' => $timezone]).
     * Falls back to 'Asia/Dhaka' when:
     *  - db_store table does not exist yet (fresh migrations)
     *  - db_store has no timezone configured or is null/empty
     *  - The configured timezone string is not a valid PHP timezone identifier
     *
     * @param bool $fresh Force reload from database
     * @param int|null $storeId Acting store id (null = resolve current)
     */
    public static function configureStoreTimezone(bool $fresh = false, ?int $storeId = null): string
    {
        $resolvedStoreId = self::resolveCacheStoreId($storeId);
        $cacheKey = $resolvedStoreId !== null ? 's' . $resolvedStoreId : 'default';

        if (!$fresh && array_key_exists($cacheKey, \App\Support\StoreSettingsCache::$timezone)) {
            $memoized = \App\Support\StoreSettingsCache::$timezone[$cacheKey];
            if ($memoized !== null) {
                date_default_timezone_set($memoized);
                config(['app.timezone' => $memoized]);
                return $memoized;
            }
        }

        $fallbackTz = 'Asia/Dhaka';
        $timezone = $fallbackTz;

        $store = self::cachedStoreFor($resolvedStoreId, $cacheKey);
        if ($store !== null) {
            // Store resolved (db_store exists) — no extra hasTable() guard needed.
            if (!empty($store->timezone)) {
                $candidate = trim((string) $store->timezone);
                if (in_array($candidate, \DateTimeZone::listIdentifiers(), true)) {
                    $timezone = $candidate;
                }
            }
        } elseif (Schema::hasTable('db_store')) {
            try {
                $fallbackStore = function_exists('store_settings') ? store_settings() : DbStore::first();
                if ($fallbackStore && !empty($fallbackStore->timezone)) {
                    $candidate = trim((string) $fallbackStore->timezone);
                    if (in_array($candidate, \DateTimeZone::listIdentifiers(), true)) {
                        $timezone = $candidate;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore any database errors during early boot/migration
            }
        }

        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);

        \App\Support\StoreSettingsCache::$timezone[$cacheKey] = $timezone;
        return $timezone;
    }

    /**
     * Resolve the acting store's currency symbol.
     *
     * This is the single source of truth for the currency symbol used throughout
     * the application. Both the view-shared $currencySymbol and any controller
     * JSON responses (e.g. GlobalSearchController) must call this method so
     * they always produce the same symbol regardless of code path.
     *
     * Falls back to '$' when:
     *  - The db_store / db_currency tables don't exist yet (fresh migrations)
     *  - No store record is found
     *  - The store has no currency_id configured
     *  - Any database error occurs during the lookup
     *
     * @param bool $fresh Force reload from database
     * @param int|null $storeId Acting store id (null = resolve current)
     */
    public static function resolveCurrencySymbol(bool $fresh = false, ?int $storeId = null): string
    {
        $resolvedStoreId = self::resolveCacheStoreId($storeId);
        $cacheKey = $resolvedStoreId !== null ? 's' . $resolvedStoreId : 'default';

        if (!$fresh && array_key_exists($cacheKey, \App\Support\StoreSettingsCache::$currencySymbol)) {
            $memoized = \App\Support\StoreSettingsCache::$currencySymbol[$cacheKey];
            if ($memoized !== null) {
                return $memoized;
            }
        }

        $symbol = '$';

        $store = self::cachedStoreFor($resolvedStoreId, $cacheKey);
        if ($store !== null && !empty($store->currency_id) && Schema::hasTable('db_currency')) {
            try {
                $currency = DbCurrency::find($store->currency_id);
                if ($currency && !empty($currency->symbol)) {
                    $symbol = $currency->symbol;
                }
            } catch (\Throwable $e) {
                // Ignore any database errors during boot (e.g. migrating or broken tables)
            }
        } elseif (Schema::hasTable('db_currency')) {
            try {
                // No store resolved — fall back to the single active currency
                $currency = DbCurrency::where('status', 1)->first();
                if ($currency && !empty($currency->symbol)) {
                    $symbol = $currency->symbol;
                }
            } catch (\Throwable $e) {
                // Ignore any database errors during boot (e.g. migrating or broken tables)
            }
        }

        \App\Support\StoreSettingsCache::$currencySymbol[$cacheKey] = $symbol;
        return $symbol;
    }
}
