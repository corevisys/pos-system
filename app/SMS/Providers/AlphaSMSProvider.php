<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;
use Illuminate\Support\Facades\Http;
use App\Models\DbSmsapi;

class AlphaSMSProvider implements SMSProviderInterface
{
    protected string $apiKey;
    protected string $senderId;

    public function __construct(int $storeId = 1)
    {
        $params = DbSmsapi::where('store_id', $storeId)->where('info', 'alpha')->pluck('key_value', 'key');
        $this->apiKey = $params['api_key'] ?? '';
        $this->senderId = $params['sender_id'] ?? '';
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        try {
            $response = Http::get('https://api.sms.net.bd/sendsms', [
                'api_key' => $this->apiKey,
                'sender_id' => $this->senderId,
                'to' => $phone,
                'msg' => $message,
            ]);

            if ($response->successful()) {
                // Alpha SMS usually returns XML or JSON with 'success'
                $data = $response->json();
                if (($data['status'] ?? '') === 'success' || str_contains($response->body(), 'success')) {
                    return SMSResponseDTO::successful(
                        messageId: $data['msg_id'] ?? uniqid('alpha_'),
                        raw: $response->body()
                    );
                }
            }

            return SMSResponseDTO::failed(
                code: 'ALPHA_FAIL',
                message: 'Failed to send via Alpha SMS',
                raw: $response->body()
            );
        } catch (\Exception $e) {
            return SMSResponseDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function checkBalance(): float
    {
        try {
            $response = Http::get('https://api.sms.net.bd/user/balance', [
                'api_key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                \Log::info("Alpha SMS Balance Response: ", (array)$data);
                // The balance is nested inside 'data'
                return (float) ($data['data']['balance'] ?? 0.0);
            }
            \Log::warning("Alpha SMS Balance Failed: " . $response->body());
        } catch (\Exception $e) {
            \Log::error("Alpha SMS Balance Check Failed: " . $e->getMessage());
        }
        return 0.0;
    }
}
