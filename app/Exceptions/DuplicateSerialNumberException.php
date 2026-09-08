<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised whenever a per-item serial-number uniqueness rule is violated at any
 * entry point that registers a DbItemSerial row (Add/Edit Item, New/Edit
 * Purchase, Purchase Quick-Add Item, Stock Adjustment).
 *
 * Extends RuntimeException so every existing controller catch (which typically
 * catches \Exception or \InvalidArgumentException) surfaces the clean, per-serial
 * message instead of a raw SQL exception.
 */
class DuplicateSerialNumberException extends RuntimeException
{
    public static function alreadyRegistered(string $serialNumber, ?string $itemName = null): self
    {
        $item = $itemName ? " \"{$itemName}\"" : '';
        return new self("Serial {$serialNumber} is already registered for this item{$item}.");
    }

    public static function duplicatedWithinSubmission(string $serialNumber, ?string $itemName = null): self
    {
        $item = $itemName ? " \"{$itemName}\"" : '';
        return new self("Serial {$serialNumber} is entered more than once for this item{$item}. Please correct it before saving.");
    }
}
