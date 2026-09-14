<?php

namespace App\Services;

use App\Models\CustomerIdentity;
use Illuminate\Database\QueryException;

/**
 * Phase 5.2 — resolve (or create) the shared cross-store customer identity.
 *
 * This is a SANCTIONED cross-store read: CustomerIdentity is deliberately not
 * StoreScoped, so this service is the one place customer identity data crosses a
 * store boundary. It only ever returns identity-level fields (phone/name) — never
 * another store's db_customers row, dues or sales history.
 *
 * Phone is the natural key for "same person". It is normalized to one canonical
 * form on write (see normalizePhone) so '+8801…', '8801…' and '01…' resolve to the
 * same identity instead of creating duplicate people.
 */
class CustomerIdentityResolver
{
    /**
     * Resolve an existing identity by phone or create a new one.
     *
     * @param string|null $phone
     * @param array{name?: string|null, email?: string|null, nid?: string|null} $attributes
     * @return CustomerIdentity|null null when no usable phone was supplied
     */
    public static function resolveOrCreate(?string $phone, array $attributes = []): ?CustomerIdentity
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }

        $existing = CustomerIdentity::where('phone', $normalized)->first();
        if ($existing) {
            // Fill in identity details we now know but did not before (never
            // overwrite existing non-null values with nulls).
            $updates = [];
            foreach (['name', 'email', 'nid'] as $field) {
                if (empty($existing->{$field}) && !empty($attributes[$field])) {
                    $updates[$field] = $attributes[$field];
                }
            }
            if ($updates) {
                $existing->fill($updates)->save();
            }

            return $existing;
        }

        try {
            return CustomerIdentity::create([
                'phone' => $normalized,
                'name'  => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'nid'   => $attributes['nid'] ?? null,
            ]);
        } catch (QueryException $e) {
            // Lost a create race (unique phone) — re-select the winner.
            $winner = CustomerIdentity::where('phone', $normalized)->first();
            if ($winner) {
                return $winner;
            }
            throw $e;
        }
    }

    /**
     * Canonicalize a phone number for identity matching.
     *
     * Returns null for empty/blank input. Keeps a leading '+' when present and
     * strips every other non-digit character, so spacing/dashes/parentheses do not
     * fragment an identity. (The app's own validation enforces 11-digit local
     * numbers; this only ensures the SAME person normalizes to the SAME key.)
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        return $hasPlus ? '+' . $digits : $digits;
    }
}
