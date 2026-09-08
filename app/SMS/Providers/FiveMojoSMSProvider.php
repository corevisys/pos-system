<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;
use Illuminate\Support\Facades\Http;
use App\Models\DbFivemojo;

class FiveMojoSMSProvider implements SMSProviderInterface
{
    protected string $url;
    protected string $token;
    protected string $instanceId;

    public function __construct(int $storeId = 1)
    {
        $config = DbFivemojo::where('store_id', $storeId)->first();
        $this->url = $config->url ?? '';
        $this->token = $config->token ?? '';
        $this->instanceId = $config->instance_id ?? '';
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        try {
            $response = Http::post("{$this->url}/api/send", [
                'token' => $this->token,
                'instance_id' => $this->instanceId,
                'number' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'success') {
                    return SMSResponseDTO::successful(
                        messageId: $data['id'] ?? uniqid('fm_'),
                        raw: $data
                    );
                }
            }

            return SMSResponseDTO::failed(
                code: 'FIVEMOJO_FAIL',
                message: $response->json()['message'] ?? 'Failed to send via FiveMojo',
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
