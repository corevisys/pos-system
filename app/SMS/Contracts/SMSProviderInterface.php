<?php

namespace App\SMS\Contracts;

use App\SMS\DTOs\SMSResponseDTO;

interface SMSProviderInterface
{
    /**
     * Send a single SMS.
     * 
     * @param string $phone
     * @param string $message
     * @param array $options (e.g., campaign_id, rule_id, batch_id)
     * @return SMSResponseDTO
     */
    public function send(string $phone, string $message, array $options = []): SMSResponseDTO;

    /**
     * Check provider balance.
     * 
     * @return float
     */
    public function checkBalance(): float;
}
