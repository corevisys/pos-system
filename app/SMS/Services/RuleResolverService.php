<?php

namespace App\SMS\Services;

use App\Models\SmsAutoRule;
use Illuminate\Support\Facades\Cache;

class RuleResolverService
{
    /**
     * Resolve active rules for a given event type.
     * Uses caching to prevent DB hits on every event.
     */
    public static function resolve(string $eventType, ?int $storeId = null)
    {
        $storeId = $storeId ?? current_store_id();
        return Cache::remember("sms_rules_{$eventType}_s{$storeId}", 3600, function () use ($eventType) {
            return SmsAutoRule::where('event_type', $eventType)
                ->where('is_active', true)
                ->with('template')
                ->get();
        });
    }

    /**
     * Clear cache for rules.
     */
    public static function clearCache(string $eventType = null, ?int $storeId = null)
    {
        $storeId = $storeId ?? current_store_id();
        if ($eventType) {
            Cache::forget("sms_rules_{$eventType}_s{$storeId}");
            Cache::forget("sms_rules_{$eventType}");
        } else {
            // Logic to clear all SMS rule caches if needed
        }
    }
}
