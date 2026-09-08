<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $window = now()->subMinutes(15);
        
        $total = SmsLog::where('created_at', '>=', $window)->count();
        if ($total < 10) {
            // Not enough traffic to determine health
            Cache::put('sms_health_status', 'Healthy (Low Traffic)', 600);
            return;
        }

        $failed = SmsLog::where('created_at', '>=', $window)->where('status', 'Failed')->count();
        $failureRate = ($failed / $total) * 100;

        if ($failureRate > 20) {
            $status = 'Critical failureRate: ' . round($failureRate, 1) . '%';
            Log::critical('SMS Gateway Health Alert: ' . $status);
            Cache::put('sms_health_status', $status, 600);
            Cache::put('sms_last_critical_alert', now(), 3600);
        } elseif ($failureRate > 5) {
            $status = 'Warning failureRate: ' . round($failureRate, 1) . '%';
            Cache::put('sms_health_status', $status, 600);
        } else {
            Cache::put('sms_health_status', 'Operational', 600);
        }
    }
}
