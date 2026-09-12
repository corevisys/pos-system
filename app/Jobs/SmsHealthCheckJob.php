<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DbStore;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $window = now()->subMinutes(15);

        // JUDGEMENT CALL: health is computed PER STORE as well as globally.
        //
        // Reasoning: each store has its OWN provider credentials
        // (db_smsapi / db_fivemojo are per-store), so one branch's gateway can be
        // failing while another is perfectly healthy. A single global figure would
        // mask that. The global key is still written because it is the meaningful
        // infrastructure-level signal (and drives the existing critical alert).
        $storeIds = DbStore::where('status', 1)->orderBy('id')->pluck('id')->all();

        foreach ($storeIds as $storeId) {
            $this->evaluate('sms_health_status_s' . $storeId, $window, (int) $storeId);
        }

        // Global aggregate across all stores (legacy key, still written).
        $this->evaluate('sms_health_status', $window, null);
    }

    /**
     * Compute and cache the health status for one store ($storeId) or for the
     * whole network ($storeId = null) over the given window.
     */
    private function evaluate(string $cacheKey, $window, ?int $storeId): void
    {
        $base = SmsLog::where('created_at', '>=', $window);
        if ($storeId !== null) {
            $base->where('store_id', $storeId);
        }

        $total = (clone $base)->count();
        if ($total < 10) {
            // Not enough traffic to determine health
            Cache::put($cacheKey, 'Healthy (Low Traffic)', 600);
            return;
        }

        $failed = (clone $base)->where('status', 'Failed')->count();
        $failureRate = ($failed / $total) * 100;

        $scope = $storeId !== null ? "store {$storeId}" : 'network';

        if ($failureRate > 20) {
            $status = 'Critical failureRate: ' . round($failureRate, 1) . '%';
            Log::critical("SMS Gateway Health Alert ({$scope}): " . $status);
            Cache::put($cacheKey, $status, 600);
            if ($storeId === null) {
                Cache::put('sms_last_critical_alert', now(), 3600);
            }
        } elseif ($failureRate > 5) {
            Cache::put($cacheKey, 'Warning failureRate: ' . round($failureRate, 1) . '%', 600);
        } else {
            Cache::put($cacheKey, 'Operational', 600);
        }
    }
}
