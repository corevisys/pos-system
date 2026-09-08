<?php

namespace App\SMS\Services;

class DuplicatePreventionService
{
    /**
     * Generate a unique hash for a message to a specific phone.
     * window = phone + message + date_window (e.g. 10 mins window)
     */
    public static function generateHash(string $phone, string $message, int $windowMinutes = 10): string
    {
        $timeWindow = floor(time() / ($windowMinutes * 60));
        return md5(trim($phone) . trim($message) . $timeWindow);
    }

    /**
     * Check if a similar message was sent recently using the hash.
     * Implementation would check SmsLog table for the hash.
     */
    public static function isDuplicate(string $hash): bool
    {
        return \App\Models\SmsLog::where('message_hash', $hash)
            ->where('created_at', '>', now()->subMinutes(10))
            ->exists();
    }
}
