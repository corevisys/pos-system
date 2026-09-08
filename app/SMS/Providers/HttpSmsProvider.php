<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;
use Illuminate\Support\Facades\Http;
use App\Models\DbSmsapi;

class HttpSmsProvider implements SMSProviderInterface
{
    protected string $baseUrl = '';
    protected string $mobileKey = 'mobiles';
    protected string $messageKey = 'message';
    protected array $params = [];

    public function __construct(int $storeId = 1)
    {
        $allParams = DbSmsapi::where('store_id', $storeId)->where('info', 'http')->pluck('key_value', 'key')->toArray();
        
        if (isset($allParams['base_url'])) {
            $this->baseUrl = $allParams['base_url'];
            unset($allParams['base_url']);
        }

        if (isset($allParams['mobile_key'])) {
            $this->mobileKey = $allParams['mobile_key'];
            unset($allParams['mobile_key']);
        }

        if (isset($allParams['message_key'])) {
            $this->messageKey = $allParams['message_key'];
            unset($allParams['message_key']);
        }

        $this->params = $allParams;
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        if (empty($this->baseUrl)) {
            return SMSResponseDTO::failed('MISSING_CONFIG', 'Base URL not configured for HTTP API.');
        }

        try {
            $queryParams = array_merge($this->params, [
                $this->mobileKey => $phone,
                $this->messageKey => $message,
            ]);

            $response = Http::get($this->baseUrl, $queryParams);

            if ($response->successful()) {
                return SMSResponseDTO::successful(
                    messageId: uniqid('http_'),
                    raw: $response->body()
                );
            }

            return SMSResponseDTO::failed(
                code: 'HTTP_FAIL',
                message: 'Failed to send via HTTP API',
                raw: $response->body()
            );
        } catch (\Exception $e) {
            return SMSResponseDTO::failed('EXCEPTION', $e->getMessage());
        }
    }

    public function checkBalance(): float
    {
        return 0.0;
    }
}
