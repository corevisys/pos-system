<?php

namespace App\Services;

use App\Exceptions\DuplicateSerialNumberException;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbTax;
use App\Models\DbWarehouseItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Shared Single-item creation logic, used by BOTH the main Add Item flow
 * (ItemController::store) and the purchase screen's Quick-Add Item flow
 * (PurchaseController::quickStoreItem). Keeping this in one place prevents the
 * two paths from drifting apart.
 */
class ItemCreationService
{
    protected ItemSerialValidationService $serialValidator;

    public function __construct(ItemSerialValidationService $serialValidator)
    {
        $this->serialValidator = $serialValidator;
    }

    /**
     * Create a Single item with the same behavior as the main Add Item flow:
     * - defaults for discount_type, discount, mrp, seller_points, hsn, profit_margin
     * - tax-inclusive purchase_price (price * (1 + tax)) for Exclusive-tax items
     * - opening stock routed into a db_warehouseitems row (never into db_items)
     * - serial numbers when serialized (uniqueness-validated + source-tagged)
     * - item_code generated via CodeGeneratorService
     *
     * NOTE: Caller must wrap this in a DB transaction if it needs rollback semantics.
     * The quick-add flow calls it inside its own transaction; ItemController::store
     * calls it inside its transaction.
     *
     * @param array $validated Validated input (the single-item rules already applied).
     * @param \Illuminate\Http\Request $request Raw request (for ip/serial_numbers/warehouse_id).
     * @param string|null $itemImagePath Optional uploaded image path.
     * @param string $source DbItemSerial.source tag identifying the entry point.
     */
    public function createSingleItem(array $validated, Request $request, ?string $itemImagePath = null, string $source = 'item_add'): DbItem
    {
        return CodeGeneratorService::executeWithRetry(function () use ($validated, $request, $itemImagePath, $source) {
            try {
                return $this->createSingleItemInner($validated, $request, $itemImagePath, $source);
            } catch (QueryException $e) {
                // Defense-in-depth: translate a DB unique-constraint violation into a
                // clean per-serial message (protects against a concurrent-submission
                // race for the same item+serial from two different entry points).
                $message = $this->serialValidator->translateDuplicateSerialQueryException($e);
                if ($message !== null) {
                    throw new DuplicateSerialNumberException($message);
                }
                throw $e;
            }
        });
    }

    /**
     * Core implementation. Kept private so the QueryException → clean-message
     * translation above wraps every serial insert uniformly.
     */
    private function createSingleItemInner(array $validated, Request $request, ?string $itemImagePath, string $source): DbItem
    {
        $tax     = DbTax::findOrFail($validated['tax_id']);
        $taxRate = (float) $tax->tax;

        // Resolve DNS outside the DB write to avoid holding a lock during a
        // blocking network call. @gethostbyaddr returns the IP unchanged on failure.
        $systemIp   = $request->ip() ?: '127.0.0.1';
        $systemName = $systemIp ? (@gethostbyaddr($systemIp) ?: 'unknown') : 'unknown';

        $data = [
            'store_id'     => current_store_id(),
            'item_name'    => trim($validated['item_name'] ?? ''),
            'category_id'  => $validated['category_id'],
            'brand_id'     => $validated['brand_id'] ?? null,
            'unit_id'      => $validated['unit_id'],
            'tax_id'       => $validated['tax_id'],
            'tax_type'     => $validated['tax_type'],
            'item_group'   => 'Single',
            'description'  => $validated['description'] ?? null,
            'hsn'          => trim($request->hsn ?? $validated['hsn'] ?? ''),
            'alert_qty'    => (float) ($validated['alert_qty'] ?? 0),
            'is_serialized'=> ($request->is_serialized == 1 || ($validated['is_serialized'] ?? false)) ? 1 : 0,
            'discount_type'=> $validated['discount_type'] ?? 'Fixed',
            'discount'     => (float) ($validated['discount'] ?? 0),
            'mrp'          => (float) ($validated['mrp'] ?? 0),
            'seller_points'=> (float) ($validated['seller_points'] ?? 0),
            'created_by'   => auth()->id(),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'system_ip'    => $systemIp,
            'system_name'  => $systemName,
            'status'       => 1,
            'item_code'    => CodeGeneratorService::generate('item'),
        ];

        // Pricing — mirror the main flow exactly.
        //
        // Main Add Item flow: user enters $price (pre-tax "Price w/o Tax"); purchase_price
        // is derived tax-inclusive. Quick-Add flow: user enters purchase_price (the landed
        // cost) and there is no separate price field; we derive the pre-tax $price from it
        // so both flows store a consistent (price = pre-tax, purchase_price = tax-inclusive)
        // relationship.
        $price = (float) ($validated['price'] ?? $request->price ?? 0);
        $enteredPurchasePrice = (float) ($validated['purchase_price'] ?? $request->purchase_price ?? 0);
        $profitMargin = (float) ($validated['profit_margin'] ?? $request->profit_margin ?? 0);

        if ($price > 0) {
            if ($validated['tax_type'] === 'Exclusive') {
                $purchasePrice = $price * (1 + $taxRate / 100);
            } else {
                $purchasePrice = $price;
            }
        } else {
            // Quick-add: purchase_price is the tax-inclusive landed cost.
            $purchasePrice = $enteredPurchasePrice;
            if ($validated['tax_type'] === 'Exclusive') {
                $price = ($taxRate > 0) ? ($purchasePrice / (1 + $taxRate / 100)) : $purchasePrice;
            } else {
                $price = $purchasePrice;
            }
        }

        $salesPrice = (float) ($validated['sales_price'] ?? $request->sales_price ?? 0);

        $data += [
            'price' => round($price, 2),
            'purchase_price' => round($purchasePrice, 2),
            'profit_margin' => round($profitMargin, 2),
            'sales_price' => round($salesPrice, 2),
            'sku' => $validated['sku'] ?? $request->sku ?? null,
            'custom_barcode' => $validated['custom_barcode'] ?? $request->custom_barcode ?? $request->barcode ?? null,
            'stock' => (float) ($validated['opening_stock'] ?? 0),
        ];

        if ($itemImagePath) {
            $data['item_image'] = $itemImagePath;
        }

        $item = DbItem::create($data);

        // Opening Stock → db_warehouseitems row (never written to db_items).
        $openingStock = (float) ($validated['opening_stock'] ?? 0);
        if ($openingStock > 0) {
            DbWarehouseItem::create([
                'store_id' => current_store_id(),
                'warehouse_id' => $request->warehouse_id,
                'item_id' => $item->id,
                'available_qty' => $openingStock,
            ]);

            // Serial Numbers — validate BEFORE insert so a duplicate serial in the
            // submission (typed twice into two slots) is rejected cleanly. The item
            // is brand-new, so no rows exist for it yet; only intra-submission
            // duplicates can occur here.
            if ($item->is_serialized == 1 && $request->has('serial_numbers')) {
                $serials = $this->serialValidator->validateNewItemSerials($request->serial_numbers, $item->item_name);

                foreach ($serials as $sn) {
                    DbItemSerial::create([
                        'store_id' => current_store_id(),
                        'item_id' => $item->id,
                        'serial_number' => $sn,
                        'status' => 0, // Available
                        'source' => $source,
                        'warehouse_id' => $request->warehouse_id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }

        return $item;
    }
}
