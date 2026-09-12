<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\EmiReminderJob)->dailyAt('09:00');
Schedule::job(new \App\Jobs\SmsHealthCheckJob)->everyFifteenMinutes();

// Process Scheduled SMS Campaigns
Schedule::call(function () {
    $campaigns = \App\Models\SmsCampaign::where('status', 'Scheduled')
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->get();

    // Deliberately spans ALL stores — that is correct for a scheduler. Each
    // DispatchCampaignJob resolves the campaign's OWN store_id for provider
    // selection, so per-store routing is preserved inside the job.
    foreach ($campaigns as $campaign) {
        \App\Jobs\DispatchCampaignJob::dispatch($campaign->id)->onQueue('sms');
    }
})->everyMinute();
// SMS Auto-Trigger Scheduled Rules
Schedule::command('sms:process-scheduled-rules')->dailyAt('09:00');

// Database Backup Schedule
Schedule::command('backup:run')->dailyAt('00:00');
Schedule::command('backup:clean')->dailyAt('01:00');
