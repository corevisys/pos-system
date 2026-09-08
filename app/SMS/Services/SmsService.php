<?php

namespace App\SMS\Services;

use App\SMS\Helpers\SmsSegmentCalculator;
use App\SMS\DTOs\SMSResponseDTO;
use App\Models\SmsLog;
use App\Models\DbStore;
use App\SMS\Providers\AlphaSMSProvider;
use App\SMS\Providers\HttpSmsProvider;
use App\SMS\Providers\BulkSmsBdProvider;
use App\SMS\Providers\FiveMojoSMSProvider;
use App\SMS\Providers\SslWirelessProvider;
use App\SMS\Services\DuplicatePreventionService;
use Illuminate\Support\Facades\Auth;

class SmsService
{
    /**
     * Get the active provider based on store settings.
     */
    public function getProvider(int $storeId = 1)
    {
        if (config('sms.sandbox', false)) {
            return new \App\SMS\Providers\SandboxSMSProvider($storeId);
        }

        $store = DbStore::find($storeId);
        if (!$store) {
            return null;
        }
        $status = (int) $store->sms_status;

        return match ($status) {
            1 => new HttpSmsProvider($storeId),
            2 => new AlphaSMSProvider($storeId),
            3 => new BulkSmsBdProvider($storeId),
            4 => new FiveMojoSMSProvider($storeId),
            5 => new SslWirelessProvider($storeId),
            default => null,
        };
    }

    /**
     * Replace variables in template content.
     */
    public function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            // Support both {var} and {{var}}
            $content = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $content);
        }
        return $content;
    }

    /**
     * Orchestrate the full sending process for a single message.
     */
    public function sendSingle(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        $storeId = $options['store_id'] ?? 1;
        $provider = $this->getProvider($storeId);

        if (!$provider) {
            return SMSResponseDTO::failed('NO_PROVIDER', 'No SMS provider configured or enabled.');
        }

        // Logic for blacklist, duplicate check, segment calculation before send...
        $stats = SmsSegmentCalculator::calculate($message);
        $hash = DuplicatePreventionService::generateHash($phone, $message);

        if (DuplicatePreventionService::isDuplicate($hash)) {
            return SMSResponseDTO::failed('DUPLICATE', 'Duplicate message detected within time window.');
        }

        // Create log entry as Pending
        $log = SmsLog::create([
            'customer_id' => $options['customer_id'] ?? null,
            'campaign_id' => $options['campaign_id'] ?? null,
            'rule_id' => $options['rule_id'] ?? null,
            'phone' => $phone,
            'message' => $message,
            'encoding' => $stats['encoding'],
            'sms_parts' => $stats['parts'],
            'message_hash' => $hash,
            'status' => 'Pending',
            'sent_by' => Auth::id(),
            'ip_address' => request()->ip(),
        ]);

        // Handover to provider
        $response = $provider->send($phone, $message, $options);

        // Update log with response
        $log->update([
            'status' => $response->success ? 'Sent' : 'Failed',
            'provider_message_id' => $response->provider_message_id,
            'request_id' => $response->request_id,
            'api_response' => $response->raw_response,
            'error_code' => $response->error_code,
            'sent_at' => $response->success ? now() : null,
            'failed_at' => !$response->success ? now() : null,
        ]);

        // Update Daily Stats for high-performance dashboarding
        $this->incrementDailyStats($response->success);

        return $response;
    }

    private function incrementDailyStats(bool $success)
    {
        $today = now()->toDateString();
        $stats = \App\Models\SmsDailyStat::firstOrCreate(['date' => $today]);
        
        if ($success) {
            $stats->increment('total_sent');
            $stats->increment('total_delivered'); // Assuming sent = delivered for now, can be updated via callback
        } else {
            $stats->increment('total_failed');
        }
    }
}
