<?php

namespace App\SMS\Helpers;

class SmsSegmentCalculator
{
    public const GSM7_SINGLE_LIMIT = 160;
    public const GSM7_MULTI_LIMIT = 153;
    public const UNICODE_SINGLE_LIMIT = 70;
    public const UNICODE_MULTI_LIMIT = 67;

    protected const GSM7_CHARS = '/^[A-Za-z0-9 \r\n@£$¥èéùìòÇ\fØø\ÅåΔ_ΦΓΛΩΠΨΣΘΞ\^\{\}\\\\[\\]~|€!"#%&\'()*+,\-.\/:;<=>?]*$/';

    /**
     * Detect encoding and calculate segments.
     */
    public static function calculate(string $message): array
    {
        $message = trim($message);
        $isUnicode = !preg_match(self::GSM7_CHARS, $message);
        $length = mb_strlen($message);

        if ($isUnicode) {
            $limit = ($length <= self::UNICODE_SINGLE_LIMIT) ? self::UNICODE_SINGLE_LIMIT : self::UNICODE_MULTI_LIMIT;
        } else {
            $limit = ($length <= self::GSM7_SINGLE_LIMIT) ? self::GSM7_SINGLE_LIMIT : self::GSM7_MULTI_LIMIT;
        }

        $parts = (int) ceil($length / $limit);

        return [
            'length' => $length,
            'parts' => max(1, $parts),
            'encoding' => $isUnicode ? 'Unicode' : 'GSM-7'
        ];
    }
}
