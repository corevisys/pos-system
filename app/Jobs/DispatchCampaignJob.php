<?php

namespace App\Jobs;

use App\Http\Controllers\SmsSendController;
use App\Models\SmsCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $campaignId)
    {}

    public function handle(): void
    {
        $campaign = SmsCampaign::findOrFail($this->campaignId);

        $campaign->update([
            'status'     => 'Processing',
            'started_at' => now(),
        ]);

        $filters = $campaign->target_filters ?? [];

        // Resolve message: template content or custom message from filters
        $message = null;
        if ($campaign->template_id && $campaign->template) {
            $message = $campaign->template->content;
        } elseif (!empty($filters['custom_message'])) {
            $message = $filters['custom_message'];
        }

        if (!$message) {
            $campaign->update(['status' => 'Failed', 'completed_at' => now()]);
            return;
        }

        // Resolve recipients using the shared helper
        $recipients = SmsSendController::resolveRecipients($campaign->target_type, $filters)->get();

        $batchId = uniqid('batch_');
        $sent = 0;

        foreach ($recipients as $recipient) {
            // Personalize message with dynamic variables
            $personalizedMessage = $this->personalize($message, $recipient);

            $mobile = $recipient->mobile ?? $recipient->phone ?? null;
            if (!$mobile) continue;

            SendSingleSmsJob::dispatch(
                $mobile,
                $personalizedMessage,
                [
                    // The job runs in a queue with NO request context, so
                    // current_store_id() is unreliable here. Pass the campaign's own
                    // store so every message resolves THAT store's provider.
                    'store_id'    => (int) $campaign->store_id,
                    'campaign_id' => $campaign->id,
                    'customer_id' => $recipient->id ?? null,
                    'batch_id'    => $batchId,
                ]
            )->onQueue('sms');

            $sent++;
        }

        $campaign->update([
            'total_recipients' => $sent,
            'status'           => 'Completed',
            'completed_at'     => now(),
        ]);
    }

    /**
     * Replace template variables like {customer_name}, {due_amount}, etc.
     */
    private function personalize(string $message, $recipient): string
    {
        $replacements = [
            '{customer_name}' => $recipient->customer_name ?? $recipient->supplier_name ?? '',
            '{due_amount}'    => number_format($recipient->sales_due ?? 0, 2),
            '{company_name}'  => config('app.name', 'Corevisys'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
