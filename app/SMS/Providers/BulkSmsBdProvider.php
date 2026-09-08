<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;
use Illuminate\Support\Facades\Http;
use App\Models\DbSmsapi;

class BulkSmsBdProvider implements SMSProviderInterface
{
    protected string $apiKey;
    protected string $type;
    protected string $senderId;

    public function __construct(int $storeId = 1)
    {
        $params = DbSmsapi::where('store_id', $storeId)->where('info', 'bulksms')->pluck('key_value', 'key');
        $this->apiKey = $params['api_key'] ?? '';
        $this->type = $params['type'] ?? 'text';
        $this->senderId = $params['senderid'] ?? '';
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        try {
            $response = Http::get('http://bulksmsbd.net/api/smsapi', [
                'api_key' => $this->apiKey,
                'type' => $this->type,
                'number' => $phone,
                'senderid' => $this->senderId,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Bulksmsbd returns JSON with response_code 202 for success
                if (($data['response_code'] ?? '') == 202) {
                    return SMSResponseDTO::successful(
                        messageId: $data['success_id'] ?? uniqid('bsbd_'),
                        raw: $data
                    );
                }
            }

            return SMSResponseDTO::failed(
                code: 'BULKSMSBD_FAIL',
                message: $response->json()['error_message'] ?? 'Failed to send via BulkSmsBd',
                raw: $response->body()
            );
        } catch (\Exception $e) {
            return SMSResponseDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function checkBalance(): float
    {
        try {
            $response = Http::get('http://bulksmsbd.net/api/getBalanceApi', [
                'api_key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['balance'] ?? 0.0);
            }
        } catch (\Exception $e) {
            \Log::error("BulkSmsBd Balance Check Failed: " . $e->getMessage());
        }
        return 0.0;
    }
}
