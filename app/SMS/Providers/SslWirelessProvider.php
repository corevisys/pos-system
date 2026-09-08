<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;
use Illuminate\Support\Facades\Http;
use App\Models\DbSmsapi;
use Illuminate\Support\Facades\Log;

class SslWirelessProvider implements SMSProviderInterface
{
    protected string $apiToken;
    protected string $sid;

    public function __construct(int $storeId = 1)
    {
        $params = DbSmsapi::where('store_id', $storeId)->where('info', 'ssl')->pluck('key_value', 'key');
        $this->apiToken = $params['api_token'] ?? '';
        $this->sid = $params['sid'] ?? '';
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        if (empty($this->apiToken) || empty($this->sid)) {
            return SMSResponseDTO::failed('CONFIG_ERROR', 'SSLWireless API Token or SID is missing.');
        }

        try {
            $csmsId = $options['csms_id'] ?? uniqid('ssl_');
            
            $response = Http::post('https://smsplus.sslwireless.com/api/v3/send-sms', [
                'api_token' => $this->apiToken,
                'sid' => $this->sid,
                'msisdn' => $phone,
                'sms' => $message,
                'csms_id' => $csmsId,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'SUCCESS') {
                return SMSResponseDTO::successful(
                    messageId: $data['reference_id'] ?? $csmsId,
                    raw: $response->body()
                );
            }

            return SMSResponseDTO::failed(
                code: $data['status'] ?? 'SSL_FAIL',
                message: $data['error_message'] ?? ($data['status_code'] ?? 'Unknown Error'),
                raw: $response->body()
            );
        } catch (\Exception $e) {
            Log::error("SSLWireless Send Failed: " . $e->getMessage());
            return SMSResponseDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function checkBalance(): float
    {
        try {
            $response = Http::get('https://smsplus.sslwireless.com/api/v3/balance', [
                'api_token' => $this->apiToken,
                'sid' => $this->sid,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['balance'] ?? 0.0);
            }
        } catch (\Exception $e) {
            Log::error("SSLWireless Balance Check Failed: " . $e->getMessage());
        }
        return 0.0;
    }
}
