<?php

namespace App\Services;

use App\Exceptions\DuplicateSerialNumberException;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use Illuminate\Support\Collection;

/**
 * Shared per-item serial-number uniqueness validation.
 *
 * Every entry point that can create DbItemSerial rows MUST go through these
 * helpers so the pre-insert duplicate check behaves identically everywhere:
 *   - Add Item / Purchase Quick-Add Item (new item) → validateNewItemSerials()
 *   - Edit Item (existing item, reconciliation model) → validateEditItemSerials()
 *   - New Purchase / Edit Purchase → validateSerialsForPurchaseLines()
 *   - Stock Adjustment → validateSerialsForAdjustmentLines()
 *
 * Rules enforced (per item_id — NOT global: the same serial MAY be used for a
 * different item):
 *   1. No serial may already exist in db_item_serials for the same item_id.
 *   2. No serial may be repeated within the same submission.
 *
 * Helpers return the cleaned serial list (trimmed, empties dropped) and throw
 * DuplicateSerialNumberException on the first offending serial, which the
 * controller catch blocks surface as a clean per-serial message instead of a
 * raw SQL unique-constraint exception.
 */
class ItemSerialValidationService
{
    /**
     * Validate serials supplied while creating a BRAND-NEW item (its item_id
     * does not exist yet, so no db_item_serials rows can exist for it). Only
     * intra-submission duplicates can be rejected here — e.g. the same serial
     * typed into two opening-stock slots.
     *
     * @param array|Collection|null $serials
     * @return array<string> cleaned serials
     */
    public function validateNewItemSerials($serials, ?string $itemName = null): array
    {
        $cleaned = $this->clean($serials);
        $this->assertNoIntraSubmissionDuplicates($cleaned, $itemName);
        return $cleaned;
    }

    /**
     * Validate the serial list submitted through the Edit Item form.
     *
     * Edit Item uses a reconciliation model: the form is pre-populated with ALL
     * current serials for the item (including Sold / Returned rows), the update
     * deletes Available rows that were removed, and it only ever INSERTS a serial
     * that is not already present for the item (guarded by a fresh DB read inside
     * the transaction). Consequently a serial that legitimately already exists for
     * the item must NOT be rejected here — only serials repeated within the same
     * submission would otherwise trigger a raw duplicate INSERT (and a raw SQL
     * unique-constraint error today).
     *
     * @param array|Collection|null $serials
     * @return array<string> cleaned serials
     */
    public function validateEditItemSerials($serials, ?string $itemName = null): array
    {
        $cleaned = $this->clean($serials);
        $this->assertNoIntraSubmissionDuplicates($cleaned, $itemName);
        return $cleaned;
    }

    /**
     * Validate serials being registered against an EXISTING item_id (the shared
     * per-item cross-entry-point check). Rejects:
     *   - serials already registered for this item by any other entry point
     *     (Add Item / another Purchase / Stock Adjustment / etc.), regardless of
     *     status — a serial may exist only once per item;
     *   - the same serial repeated within this submission.
     *
     * @param int  $itemId
     * @param array|Collection|null $serials
     * @param string|null $itemName for a friendlier message
     * @return array<string> cleaned serials
     */
    public function validateSerialsForItem(int $itemId, $serials, ?string $itemName = null): array
    {
        $cleaned = $this->clean($serials);

        $this->assertNoIntraSubmissionDuplicates($cleaned, $itemName);

        if (count($cleaned) === 0) {
            return $cleaned;
        }

        $existing = DbItemSerial::where('item_id', $itemId)->pluck('serial_number')->all();
        $this->assertNotAlreadyRegistered($cleaned, $existing, $itemName);

        return $cleaned;
    }

    /**
     * Batch wrapper for Purchase store()/update(): each cart/line item's serials
     * are validated against that line's own item_id before the transaction
     * commits. Rejects the whole submission on the first offending serial.
     *
     * NOTE: For update(), the caller must invoke this AFTER the purchase's own
     * serial rows have been deleted (the existing update() flow already deletes
     * them before re-inserting), so re-adding this purchase's own serials is not
     * mistaken for a cross-entry-point duplicate.
     *
     * @param array<int, array{item_id: int, serials?: array}> $lines
     */
    public function validateSerialsForPurchaseLines(array $lines): void
    {
        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $serials = $line['serials'] ?? [];
            if (!is_array($serials) || count($serials) === 0) {
                continue;
            }
            $itemName = optional(DbItem::find($itemId))->item_name;
            $this->validateSerialsForItem($itemId, $serials, $itemName);
        }
    }

    /**
     * Batch wrapper for Stock Adjustment store()/update(): same per-item check
     * per adjusted line. Because an adjustment may list the SAME item across
     * multiple lines, serials are aggregated per item_id across ALL lines so a
     * serial typed twice into two different lines of the same item is rejected as
     * an intra-submission duplicate, not silently collapsed by updateOrCreate.
     *
     * update() must run this AFTER its revert step deletes the adjustment's own
     * serial rows, so re-adding them is not flagged.
     *
     * @param array<int, array{item_id: int, serials?: array}> $items
     */
    public function validateSerialsForAdjustmentLines(array $items): void
    {
        $aggregated = []; // item_id => ['serials' => [...], 'names' => ...]
        foreach ($items as $itemData) {
            $itemId = (int) ($itemData['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $serials = $itemData['serials'] ?? [];
            if (!is_array($serials) || count($serials) === 0) {
                continue;
            }
            $aggregated[$itemId][] = $serials;
        }

        foreach ($aggregated as $itemId => $serialGroups) {
            $flattened = [];
            foreach ($serialGroups as $group) {
                foreach ($group as $sn) {
                    $flattened[] = $sn;
                }
            }
            $itemName = optional(DbItem::find($itemId))->item_name;
            $this->validateSerialsForItem($itemId, $flattened, $itemName);
        }
    }

    /**
     * Detect & translate a DB-level unique-constraint violation on
     * (item_id, serial_number) into the clean per-serial message. This is the
     * defense-in-depth backstop for a race between two concurrent submissions
     * (possibly from two DIFFERENT entry points) for the same item+serial.
     *
     * SQLite:  UNIQUE constraint failed: db_item_serials.serial_number
     * MySQL:   Duplicate entry 'SN-123' for key 'db_item_serials.uq_db_item_serials_item_serial'
     */
    public function translateDuplicateSerialQueryException(\Throwable $e): ?string
    {
        $raw = (string) $e->getMessage();

        $isUniqueViolation =
            str_contains($raw, 'uq_db_item_serials_item_serial')
            || str_contains($raw, 'db_item_serials_item_id_serial_number_unique')
            || preg_match('/UNIQUE constraint failed: db_item_serials\.(item_id, )?serial_number/i', $raw);

        if (!$isUniqueViolation) {
            return null;
        }

        $serial = null;
        if (preg_match("/Duplicate entry '([^']+)'/i", $raw, $m)) {
            $serial = $m[1];
        }

        return $serial
            ? "Serial {$serial} is already registered for this item."
            : 'This serial number is already registered for this item. It cannot be used twice for the same item.';
    }

    /**
     * @return array<string>
     */
    protected function clean($serials): array
    {
        if ($serials === null) {
            return [];
        }
        if ($serials instanceof Collection) {
            $serials = $serials->all();
        }
        if (!is_array($serials)) {
            return [];
        }

        $cleaned = [];
        foreach ($serials as $sn) {
            $sn = trim((string) $sn);
            if ($sn !== '') {
                $cleaned[] = $sn;
            }
        }
        return $cleaned;
    }

    /**
     * @param array<string> $cleaned
     */
    protected function assertNoIntraSubmissionDuplicates(array $cleaned, ?string $itemName): void
    {
        $seen = [];
        foreach ($cleaned as $sn) {
            if (isset($seen[$sn])) {
                throw DuplicateSerialNumberException::duplicatedWithinSubmission($sn, $itemName);
            }
            $seen[$sn] = true;
        }
    }

    /**
     * @param array<string> $cleaned
     * @param array<int|string> $existing
     */
    protected function assertNotAlreadyRegistered(array $cleaned, array $existing, ?string $itemName): void
    {
        if (empty($existing)) {
            return;
        }
        $existingSet = array_flip(array_map('strval', $existing));
        foreach ($cleaned as $sn) {
            if (isset($existingSet[$sn])) {
                throw DuplicateSerialNumberException::alreadyRegistered($sn, $itemName);
            }
        }
    }
}
