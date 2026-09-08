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
     * Resolve and configure the active store's timezone.
     *
     * Sets date_default_timezone_set() and config(['app.timezone' => $timezone]).
     * Falls back to 'Asia/Dhaka' when:
     *  - db_store table does not exist yet (fresh migrations)
     *  - db_store has no timezone configured or is null/empty
     *  - The configured timezone string is not a valid PHP timezone identifier
     */
    public static function configureStoreTimezone(bool $fresh = false): string
    {
        if (!$fresh && \App\Support\StoreSettingsCache::$timezone !== null) {
            return \App\Support\StoreSettingsCache::$timezone;
        }

        $fallbackTz = 'Asia/Dhaka';
        $timezone = $fallbackTz;

        if (Schema::hasTable('db_store')) {
            try {
                $store = function_exists('store_settings') ? store_settings() : DbStore::first();
                if ($store && !empty($store->timezone)) {
                    $candidate = trim((string) $store->timezone);
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

        \App\Support\StoreSettingsCache::$timezone = $timezone;
        return $timezone;
    }

    /**
     * Resolve the active store's currency symbol.
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
     */
    public static function resolveCurrencySymbol(bool $fresh = false): string
    {
        if (!$fresh && \App\Support\StoreSettingsCache::$currencySymbol !== null) {
            return \App\Support\StoreSettingsCache::$currencySymbol;
        }

        $symbol = '$';

        if (Schema::hasTable('db_currency')) {
            try {
                $currency = null;
                if (Schema::hasTable('db_store')) {
                    $store = function_exists('store_settings') ? store_settings() : DbStore::first();
                    if ($store && $store->currency_id) {
                        $currency = DbCurrency::find($store->currency_id);
                    }
                }
                
                if (!$currency) {
                    $currency = DbCurrency::where('status', 1)->first();
                }

                if ($currency && !empty($currency->symbol)) {
                    $symbol = $currency->symbol;
                }
            } catch (\Throwable $e) {
                // Ignore any database errors during boot (e.g. migrating or broken tables)
            }
        }

        \App\Support\StoreSettingsCache::$currencySymbol = $symbol;
        return $symbol;
    }
}
