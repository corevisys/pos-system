<?php

namespace App\Jobs;

use App\SMS\Services\SmsService;
use App\Models\SmsLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

class SendSingleSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $phone,
        protected string $message,
        protected array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SmsService $smsService): void
    {
        $provider = $this->options['provider_name'] ?? 'default';

        RateLimiter::attempt(
            "sms_send_{$provider}",
            config("sms.throttle.{$provider}", 5),
            function () use ($smsService) {
                $smsService->sendSingle($this->phone, $this->message, $this->options);
            },
            1 // decay seconds
        );
    }
}
