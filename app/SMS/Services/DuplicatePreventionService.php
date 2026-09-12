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
     *
     * Suppression is PER STORE: the same phone+message sent by store 1 and store 2
     * within the window are NOT duplicates of each other. Scoping the lookup by
     * store_id is sufficient — the hash itself does not need to change, because
     * store_id is stored on every SmsLog row and is part of the query.
     *
     * @param string   $hash
     * @param int|null $storeId  Null = global lookup (legacy/CLI callers).
     */
    public static function isDuplicate(string $hash, ?int $storeId = null): bool
    {
        $query = \App\Models\SmsLog::where('message_hash', $hash)
            ->where('created_at', '>', now()->subMinutes(10));

        if (!empty($storeId)) {
            $query->where('store_id', $storeId);
        }

        return $query->exists();
    }
}
