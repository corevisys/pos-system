<?php

namespace App\SMS\DTOs;

class SMSResponseDTO
{
    public function __construct(
        public bool $success,
        public ?string $provider_message_id = null,
        public ?string $error_code = null,
        public ?string $error_message = null,
        public mixed $raw_response = null,
        public ?float $balance_after = null,
        public ?string $request_id = null
    ) {}

    public static function successful(string $messageId, mixed $raw = null, ?float $balance = null, ?string $requestId = null): self
    {
        return new self(
            success: true,
            provider_message_id: $messageId,
            raw_response: $raw,
            balance_after: $balance,
            request_id: $requestId
        );
    }

    public static function failed(string $code, string $message, mixed $raw = null): self
    {
        return new self(
            success: false,
            error_code: $code,
            error_message: $message,
            raw_response: $raw
        );
    }
}
