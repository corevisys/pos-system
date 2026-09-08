<?php

namespace App\SMS\Providers;

use App\SMS\Contracts\SMSProviderInterface;
use App\SMS\DTOs\SMSResponseDTO;

class SandboxSMSProvider implements SMSProviderInterface
{
    protected $storeId;

    public function __construct(int $storeId)
    {
        $this->storeId = $storeId;
    }

    public function send(string $phone, string $message, array $options = []): SMSResponseDTO
    {
        // Simulate progress
        return new SMSResponseDTO(
            success: true,
            provider_message_id: 'SANDBOX-' . uniqid(),
            request_id: 'REQ-' . uniqid(),
            raw_response: json_encode(['status' => 'success', 'mode' => 'sandbox']),
            error_code: null
        );
    }

    public function checkBalance(): float
    {
        return 999.99;
    }
}
