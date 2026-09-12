<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\DuplicateSerialNumberException;
use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk CSV Import Items feature.
 *
 * Mirrors CustomerController::import()/importStore()/importTemplate() and
 * SupplierController's equivalents for overall structure (file validation,
 * fgetcsv() parsing, per-row loop, partial-success-with-skipped-rows policy,
 * per-row error collection, and `import_summary` reporting).
 *
 * CRITICAL (protected region): every row that is created goes through
 * ItemCreationService::createSingleItem() with source = 'item_import' — the SAME
 * validated path Add Item and Purchase Quick-Add use. There is NO raw
 * DbItem::create() / bulk insert in this code. That is what applies the
 * SKU/barcode format rules, per-item serial uniqueness and store scoping
 * automatically, without reimplementing them here.
 *
 * Atomicity & error-reporting policy (confirmed to match Customers/Suppliers):
 * PARTIAL SUCCESS WITH SKIPPED ROWS. Each bad row is appended to $skippedRows
 * ("Row N: reason") and skipped; good rows still commit. A system-level
 * \Throwable rolls the entire import back. Result reported via the shared
 * `import_summary` session shape (imported / skipped / errors).
 */
trait ImportsItems
{
    /**
     * Show the view for importing items (bulk CSV create).
     *
     * Gated by the same items_import_items permission the sidebar, global search
     * and keyboard shortcuts check (ItemController permission-gate style, see
     * printLabels()). The warehouse dropdown is store-scoped (a Store-2 user never
     * sees Store-1 warehouses), mirroring create()/edit().
     */
    public function import()
    {
        if (!auth()->user()->hasPermission('items_import_items')) {
            abort(403, 'Unauthorized access to Import Items.');
        }

        $storeId = current_store_id();

        $categories = DbCategory::where('status', 1)->where('store_id', $storeId)->get();
        $brands = DbBrand::where('status', 1)->where('store_id', $storeId)->get();
        $units = DbUnit::where('status', 1)
            ->where('store_id', $storeId)
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', $storeId)
            ->get();
        $warehouses = DbWarehouse::where('status', 1)->where('store_id', $storeId)->get();

        return view('module.items.import_items', compact('categories', 'brands', 'units', 'taxes', 'warehouses'));
    }

    /**
     * Download a sample CSV template for item import.
     *
     * 22 columns: the 21 documented columns plus the new optional "Serial Numbers"
     * column (pipe-delimited — SN001|SN002 — one serial per stock unit for
     * serialized items). Mirrors CustomerController::importTemplate() /
     * SupplierController::importTemplate() (UTF-8 BOM, streamed CSV response).
     */
    public function importTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="items_import_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Item Name',
            'Category Name',
            'SKU',
            'HSN',
            'Unit Name',
            'Alert Quantity',
            'Brand Name',
            'Lot Number',
            'Price Before Tax',
            'Price After Tax',
            'Tax Name',
            'Tax Value',
            'Tax Type',
            'Sales Price',
            'Opening Stock',
            'Barcode',
            'Seller Points',
            'Description',
            'Discount Type',
            'Discount',
            'MRP',
            'Serial Numbers',
        ];

        $sampleRows = [
            [
                'Gaming Laptop 15"', 'Laptop', 'LAP-001', '84713000', 'Piece', '5',
                'HP', 'LOT-2026-01', '50000.00', '57500.00', 'VAT 15% (Standard)',
                '15', 'Inclusive', '65000.00', '2', 'LAP001BC', '10', 'Imported gaming laptop',
                'Percentage', '5', '70000.00', 'SN-LAP-001|SN-LAP-002',
            ],
            [
                'Wireless Mouse', 'Mouse', 'MOU-001', '84716000', 'Piece', '10',
                'Logitech', '', '800.00', '920.00', 'VAT 15% (Standard)',
                '15', 'Exclusive', '1200.00', '0', 'MOU001BC', '0', '',
                'Fixed', '50', '1300.00', '',
            ],
        ];

        $callback = function () use ($columns, $sampleRows) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);
            foreach ($sampleRows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Process uploaded CSV file to import items.
     */
    public function importStore(Request $request)
    {
        if (!auth()->user()->hasPermission('items_import_items')) {
            abort(403, 'Unauthorized access to Import Items.');
        }

        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'import_file.required' => 'Please select a CSV file to import.',
            'import_file.mimes' => 'The file must be in CSV format.',
            'import_file.max' => 'The file size must not exceed 5MB.',
        ]);

        $uploadedFile = $request->file('import_file');
        $filePath = $uploadedFile->getRealPath();
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            return back()->with('error', 'Unable to open the uploaded file.');
        }

        $storeId = current_store_id();
        $warehouseId = (int) $request->input('warehouse_id');

        $importedCount = 0;
        $skippedRows = [];
        $rowNum = 1;

        try {
            // Read header row and strip UTF-8 BOM if present
            $rawHeader = fgetcsv($handle);
            if (!$rawHeader || empty(array_filter($rawHeader))) {
                return back()->with('error', 'The uploaded CSV file is empty or missing headers.');
            }

            $rawHeader[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $rawHeader[0]);

            // Map header column names to indexes
            $colMap = [];
            foreach ($rawHeader as $index => $colName) {
                $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $colName)));
                $colMap[$clean] = $index;
            }

            // Quick helper to get row value by multiple possible aliases
            $getValue = function (array $row, array $aliases) use ($colMap): string {
                foreach ($aliases as $alias) {
                    if (isset($colMap[$alias]) && isset($row[$colMap[$alias]])) {
                        return trim((string) $row[$colMap[$alias]]);
                    }
                }
                return '';
            };

            // Pre-load lookups for the current store (case-insensitive). Units and
            // taxes are store-scoped in their seeders (store_id=1) and the Add Item
            // form loads them unscoped, so match by name the same way.
            $units = DbUnit::where('status', 1)
                ->where('store_id', $storeId)
                ->get()->keyBy(fn($u) => strtolower(trim((string) $u->unit_name)));
            $taxes = DbTax::where('status', 1)
                ->where('store_id', $storeId)
                ->get()->keyBy(fn($t) => strtolower(trim((string) $t->tax_name)));

            // Auto-create resolution caches (lowercased name => model). The cache is
            // updated as new category/brand rows are created so the same new name
            // referenced by N rows is created exactly once per import.
            $categoryCache = DbCategory::where('store_id', $storeId)->get()->keyBy(fn($c) => strtolower(trim((string) $c->category_name)));
            $brandCache = DbBrand::where('store_id', $storeId)->get()->keyBy(fn($b) => strtolower(trim((string) $b->brand_name)));

            $resolveCategory = function (string $name) use ($storeId, &$categoryCache): ?int {
                $key = strtolower(trim($name));
                if ($key === '') {
                    return null;
                }
                if (isset($categoryCache[$key])) {
                    return (int) $categoryCache[$key]->id;
                }
                try {
                    $category = DbCategory::create([
                        'category_name' => trim($name),
                        'category_code' => strtoupper(substr(trim($name), 0, 3)),
                        'description' => null,
                        'status' => 1,
                        'store_id' => $storeId,
                    ]);
                } catch (\Illuminate\Database\QueryException $qe) {
                    // Race-safe backstop: a concurrent import / Add Item created this
                    // category between our SELECT and INSERT — resolve to it instead
                    // of crashing (respects db_category_store_category_name_unique).
                    $category = DbCategory::where('store_id', $storeId)
                        ->where('category_name', trim($name))
                        ->first();
                    if (!$category) {
                        throw $qe;
                    }
                }
                $categoryCache[$key] = $category;
                return (int) $category->id;
            };

            $resolveBrand = function (string $name) use ($storeId, &$brandCache): ?int {
                $key = strtolower(trim($name));
                if ($key === '') {
                    return null;
                }
                if (isset($brandCache[$key])) {
                    return (int) $brandCache[$key]->id;
                }
                try {
                    $brand = DbBrand::create([
                        'store_id' => $storeId,
                        'brand_name' => trim($name),
                        'brand_code' => strtoupper(substr(trim($name), 0, 3)),
                        'description' => null,
                        'status' => 1,
                    ]);
                } catch (\Illuminate\Database\QueryException $qe) {
                    $brand = DbBrand::where('store_id', $storeId)
                        ->where('brand_name', trim($name))
                        ->first();
                    if (!$brand) {
                        throw $qe;
                    }
                }
                $brandCache[$key] = $brand;
                return (int) $brand->id;
            };

            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                // Skip completely empty rows
                if (empty(array_filter($row, fn($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                $itemName = $getValue($row, ['itemname', 'name', 'item']);
                if ($itemName === '') {
                    $skippedRows[] = "Row {$rowNum}: Item Name is required.";
                    continue;
                }

                $categoryName = $getValue($row, ['categoryname', 'category']);
                if ($categoryName === '') {
                    $skippedRows[] = "Row {$rowNum}: Category Name is required.";
                    continue;
                }
                $categoryId = $resolveCategory($categoryName);

                $brandName = $getValue($row, ['brandname', 'brand']);
                $brandId = $brandName === '' ? null : $resolveBrand($brandName);

                $unitName = $getValue($row, ['unitname', 'unit']);
                if ($unitName === '') {
                    $skippedRows[] = "Row {$rowNum}: Unit Name is required.";
                    continue;
                }
                $unit = $units[strtolower($unitName)] ?? null;
                if (!$unit) {
                    $skippedRows[] = "Row {$rowNum}: Unit '{$unitName}' was not found. Add it under Settings > Units first.";
                    continue;
                }

                $taxName = $getValue($row, ['taxname', 'tax']);
                $taxValueRaw = $getValue($row, ['taxvalue', 'taxpercent', 'taxrate']);
                if ($taxName === '') {
                    $skippedRows[] = "Row {$rowNum}: Tax Name is required.";
                    continue;
                }
                $tax = $taxes[strtolower($taxName)] ?? null;
                if (!$tax) {
                    $skippedRows[] = "Row {$rowNum}: Tax '{$taxName}' was not found. Add it under Settings > Tax first.";
                    continue;
                }
                // Tax Value is informational (documented on the page); the authoritative
                // rate comes from the matched DbTax row. A mismatched declared value is
                // rejected to catch copy/paste errors early.
                if ($taxValueRaw !== '') {
                    $parsedTaxValue = (float) $taxValueRaw;
                    if ((float) $tax->tax !== $parsedTaxValue) {
                        $skippedRows[] = "Row {$rowNum}: Tax Value '{$taxValueRaw}' does not match the rate ({$tax->tax}%) for tax '{$taxName}'.";
                        continue;
                    }
                }

                $taxTypeRaw = strtolower($getValue($row, ['taxtype', 'type']));
                if ($taxTypeRaw !== '' && !in_array($taxTypeRaw, ['inclusive', 'exclusive'], true)) {
                    $skippedRows[] = "Row {$rowNum}: Tax Type must be 'Inclusive' or 'Exclusive'.";
                    continue;
                }
                $taxType = $taxTypeRaw === '' ? 'Inclusive' : ucfirst($taxTypeRaw);

                $priceRaw = $getValue($row, ['pricebeforetax', 'price', 'pricewithouttax']);
                $purchasePriceRaw = $getValue($row, ['priceaftertax', 'purchaseprice', 'pricewithtax']);
                $salesPriceRaw = $getValue($row, ['salesprice', 'sellingprice']);
                $openingStockRaw = $getValue($row, ['openingstock', 'stock']);

                $price = $priceRaw === '' ? 0 : (float) $priceRaw;
                $purchasePrice = $purchasePriceRaw === '' ? 0 : (float) $purchasePriceRaw;
                $salesPrice = $salesPriceRaw === '' ? 0 : (float) $salesPriceRaw;

                if ($price < 0 || $purchasePrice < 0 || $salesPrice < 0) {
                    $skippedRows[] = "Row {$rowNum}: Prices must be zero or positive.";
                    continue;
                }

                // Required-field policy mirrors the Add Item validation: price (w/o tax),
                // purchase price (with tax) and sales price are all required in the
                // Single-item flow. Accept when either price* is provided; the service
                // derives the sibling from the other + tax (same as Add Item).
                if ($priceRaw === '' && $purchasePriceRaw === '') {
                    $skippedRows[] = "Row {$rowNum}: Price Before Tax or Price After Tax is required.";
                    continue;
                }
                if ($salesPriceRaw === '') {
                    $skippedRows[] = "Row {$rowNum}: Sales Price is required.";
                    continue;
                }

                $serialsRaw = $getValue($row, ['serialnumbers', 'serials']);
                $serialList = [];
                if ($serialsRaw !== '') {
                    $serialList = array_values(array_filter(array_map(
                        fn($s) => trim((string) $s),
                        explode('|', $serialsRaw)
                    ), fn($s) => $s !== ''));
                }

                $openingStock = $openingStockRaw === '' ? 0 : (float) $openingStockRaw;
                $isSerialized = ($openingStock > 0 && count($serialList) > 0) ? 1 : 0;

                if (count($serialList) > 0 && $openingStock <= 0) {
                    $skippedRows[] = "Row {$rowNum}: Serial numbers were provided but Opening Stock is 0. Serialized items need opening stock equal to the serial count.";
                    continue;
                }

                if ($isSerialized === 1) {
                    $stockInt = (int) $openingStock;
                    if (count($serialList) !== $stockInt) {
                        $skippedRows[] = "Row {$rowNum}: Please provide all {$stockInt} serial numbers (only " . count($serialList) . " entered).";
                        continue;
                    }
                }

                // SKU / Barcode format validation: the Add Item form enforces
                // regex:/^[A-Za-z0-9\-_\.]+$/ on both. Because the import routes through
                // ItemCreationService (same as Add Item), the authoritative check lives
                // here too so a malformed value is rejected with a clear row message.
                $sku = $getValue($row, ['sku', 'itemcode']);
                $barcode = $getValue($row, ['barcode', 'custombarcode']);
                if ($sku !== '' && !preg_match('/^[A-Za-z0-9\-_\.]+$/', $sku)) {
                    $skippedRows[] = "Row {$rowNum}: SKU '{$sku}' may only contain letters, numbers, dash, underscore or dot.";
                    continue;
                }
                if ($barcode !== '' && !preg_match('/^[A-Za-z0-9\-_\.]+$/', $barcode)) {
                    $skippedRows[] = "Row {$rowNum}: Barcode '{$barcode}' may only contain letters, numbers, dash, underscore or dot.";
                    continue;
                }

                // Duplicate SKU / Barcode detection:
                //   1. intra-file (the same SKU/barcode appearing in two rows), and
                //   2. against existing items (the Add Item unique validation rejects a
                //      SKU/barcode already present in db_items — there is no DB unique
                //      index on these columns, so the import must pre-check to avoid
                //      silently creating a duplicate the Add Item form would have blocked).
                if ($sku !== '') {
                    if (isset($seenSkus[$sku])) {
                        $skippedRows[] = "Row {$rowNum}: SKU '{$sku}' is already used in this file (Row {$seenSkus[$sku]}).";
                        continue;
                    }
                    if (DbItem::where('sku', $sku)->where('store_id', $storeId)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: SKU '{$sku}' is already in use.";
                        continue;
                    }
                }
                if ($barcode !== '') {
                    if (isset($seenBarcodes[$barcode])) {
                        $skippedRows[] = "Row {$rowNum}: Barcode '{$barcode}' is already used in this file (Row {$seenBarcodes[$barcode]}).";
                        continue;
                    }
                    if (DbItem::where('custom_barcode', $barcode)->where('store_id', $storeId)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: Barcode '{$barcode}' is already in use.";
                        continue;
                    }
                }

                // Intra-file + against-existing serial detection for serialized rows.
                // Serial uniqueness is enforced per item at the DB level, but serials
                // are resolved at POS by store+warehouse VALUE lookup (PosController::
                // searchItems), so re-using a serial value across two rows in one file
                // (even for different items) would create an ambiguous scan. Reject it
                // with the row number of the earlier use.
                if ($isSerialized === 1) {
                    foreach ($serialList as $sn) {
                        if (isset($seenSerials[$sn])) {
                            $skippedRows[] = "Row {$rowNum}: Serial {$sn} is already used in this file (Row {$seenSerials[$sn]}).";
                            continue 2;
                        }
                        if (DbItemSerial::where('serial_number', $sn)->where('store_id', $storeId)->exists()) {
                            $skippedRows[] = "Row {$rowNum}: Serial {$sn} is already registered for an item in this store.";
                            continue 2;
                        }
                    }
                }

                $hsn = $getValue($row, ['hsn']);
                $alertQtyRaw = $getValue($row, ['alertquantity', 'alertqty']);
                $discountTypeRaw = strtolower($getValue($row, ['discounttype', 'discounttype2']));
                if ($discountTypeRaw !== '' && !in_array($discountTypeRaw, ['percentage', 'fixed'], true)) {
                    $skippedRows[] = "Row {$rowNum}: Discount Type must be 'Percentage' or 'Fixed'.";
                    continue;
                }
                $discountType = $discountTypeRaw === '' ? 'Fixed' : ucfirst($discountTypeRaw);
                $discountRaw = $getValue($row, ['discount']);
                $mrpRaw = $getValue($row, ['mrp']);
                $sellerPointsRaw = $getValue($row, ['sellerpoints', 'points']);
                $description = $getValue($row, ['description']);

                // Build the exact payload ItemCreationService::createSingleItem() expects.
                // The service derives tax-inclusive purchase_price from price+tax when
                // price>0; when only purchase_price is provided it derives the pre-tax
                // price (mirroring the Purchase Quick-Add flow). Serial numbers are passed
                // as an array via $request->serial_numbers so the service's own
                // ItemSerialValidationService::validateNewItemSerials() runs on them
                // (intra-row duplicates rejected with the established per-serial message).
                // A warehouse is required whenever opening stock is being added
                // (mirrors the Add Item rule; without it the service would create an
                // orphaned db_warehouseitems row with warehouse_id = NULL). The
                // dropdown on the page is store-scoped, so the posted id is validated
                // to belong to this store as defense-in-depth.
                if ($openingStock > 0) {
                    if ($warehouseId <= 0) {
                        $skippedRows[] = "Row {$rowNum}: A warehouse is required when stock is added.";
                        continue;
                    }
                    if (!DbWarehouse::where('id', $warehouseId)->where('store_id', $storeId)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: The selected warehouse is not available for this store.";
                        continue;
                    }
                }

                $validated = [
                    'item_name' => trim($itemName),
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'unit_id' => (int) $unit->id,
                    'tax_id' => $tax ? (int) $tax->id : null,
                    'tax_type' => $taxType,
                    'description' => $description !== '' ? $description : null,
                    'hsn' => $hsn,
                    'alert_qty' => $alertQtyRaw === '' ? 0 : (float) $alertQtyRaw,
                    'is_serialized' => $isSerialized,
                    'discount_type' => $discountType,
                    'discount' => $discountRaw === '' ? 0 : (float) $discountRaw,
                    'mrp' => $mrpRaw === '' ? 0 : (float) $mrpRaw,
                    'seller_points' => $sellerPointsRaw === '' ? 0 : (float) $sellerPointsRaw,
                    'sku' => $sku !== '' ? $sku : null,
                    'custom_barcode' => $barcode !== '' ? $barcode : null,
                    'price' => $price,
                    'purchase_price' => $purchasePrice,
                    'sales_price' => $salesPrice,
                    'opening_stock' => $openingStock,
                    'warehouse_id' => $warehouseId,
                ];

                // Set the request fields the service reads directly
                // ($request->is_serialized / $request->serial_numbers / $request->hsn /
                // $request->warehouse_id / $request->price etc.), keeping this path
                // byte-for-byte identical to how ItemController::store() calls it.
                $request->merge([
                    'is_serialized' => $isSerialized,
                    'serial_numbers' => $isSerialized === 1 ? $serialList : [],
                    'warehouse_id' => $warehouseId,
                ]);

                try {
                    $item = app(\App\Services\ItemCreationService::class)
                        ->createSingleItem($validated, $request, null, 'item_import');
                    $importedCount++;

                    // Track used values so a later row referencing them is rejected
                    // with the row number of the first use.
                    if ($sku !== '') {
                        $seenSkus[$sku] = $rowNum;
                    }
                    if ($barcode !== '') {
                        $seenBarcodes[$barcode] = $rowNum;
                    }
                    foreach ($serialList as $sn) {
                        $seenSerials[$sn] = $rowNum;
                    }
                } catch (DuplicateSerialNumberException $e) {
                    $skippedRows[] = "Row {$rowNum}: {$e->getMessage()}";
                } catch (\Illuminate\Database\QueryException $qe) {
                    $msg = app(\App\Services\ItemSerialValidationService::class)
                        ->translateDuplicateSerialQueryException($qe);
                    if ($msg !== null) {
                        $skippedRows[] = "Row {$rowNum}: {$msg}";
                    } else {
                        throw $qe;
                    }
                }
            }

            DB::commit();

            return redirect()->route('items.import')->with('import_summary', [
                'imported' => $importedCount,
                'skipped' => count($skippedRows),
                'errors' => $skippedRows,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Item bulk import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Import failed due to a system error: ' . $e->getMessage());
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
