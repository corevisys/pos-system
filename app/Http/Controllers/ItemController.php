<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbUnit;
use App\Models\DbTax;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Models\DbStore;
use App\Exceptions\DuplicateSerialNumberException;
use App\Services\ItemCreationService;
use App\Services\ItemSerialValidationService;
use Illuminate\Database\QueryException;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\ImportsItems;

class ItemController extends Controller
{
    use ImportsItems;
    public function index(Request $request)
    {
        // Store-scoped base query — cross-store item rows must never appear on this
        // list or its exports. Mirrors SupplierController::index / CustomerController::index.
        $query = DbItem::where('child_bit', 0) // Only list parents or single items
            ->where('store_id', current_store_id());

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('item_type')) {
            if ($request->item_type == 'Items') {
                $query->where('service_bit', 0);
            } elseif ($request->item_type == 'Services') {
                $query->where('service_bit', 1);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'LIKE', "%{$search}%")
                  ->orWhere('item_code', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Print/PDF export view — reuses the SAME filtered, store-scoped query builder
        // as the on-screen list so exports always mirror exactly what the screen shows.
        if ($request->export === 'print' || $request->export === 'pdf') {
            $exportItems = $query->with(['category', 'brand', 'unit'])
                ->orderBy('id', 'desc')
                ->get();

            return view('module.items.items_list_print', [
                'items' => $exportItems,
            ]);
        }

        // CSV export — same shared query builder.
        if ($request->export === 'csv') {
            $exportItems = $query->with(['category', 'brand', 'unit'])
                ->orderBy('id', 'desc')
                ->get();

            $filename = "items_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportItems) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($file, ['Item Code', 'Item Name', 'SKU', 'Barcode', 'Category', 'Brand', 'Unit', 'Stock', 'Sales Price', 'Status']);

                foreach ($exportItems as $i) {
                    fputcsv($file, [
                        $i->item_code,
                        $i->item_name,
                        $i->sku ?? '',
                        $i->custom_barcode ?? '',
                        $i->category->category_name ?? '',
                        $i->brand->brand_name ?? '',
                        $i->unit->unit_name ?? '',
                        (float) $i->stock,
                        (float) $i->sales_price,
                        $i->status == 1 ? 'Active' : 'Inactive',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $items = $query->with(['category', 'brand', 'unit', 'tax'])
                      ->withCount('serials')
                      ->latest()
                      ->paginate($perPage)
                      ->withQueryString();

        $categories = DbCategory::where('status', 1)->where('store_id', current_store_id())->get();
        $brands = DbBrand::where('status', 1)->where('store_id', current_store_id())->get();

        return view('module.items.items_list', compact('items', 'categories', 'brands'));
    }

    public function create()
    {
        $categories = DbCategory::where('status', 1)->where('store_id', current_store_id())->get();
        $brands = DbBrand::where('status', 1)->where('store_id', current_store_id())->get();
        $units = DbUnit::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();

        // Generate Item Code
        $itemCode = \App\Services\CodeGeneratorService::generate('item');

        return view('module.items.add_item', compact('categories', 'brands', 'units', 'taxes', 'warehouses', 'itemCode'));
    }

    public function store(Request $request)
    {
        // 1. Enhanced Validation
        $rules = [
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|exists:db_category,id',
            'brand_id' => 'nullable|exists:db_brands,id',
            'unit_id' => 'required|exists:db_units,id',
            'tax_id' => 'required|exists:db_tax,id',
            'tax_type' => 'required|in:Inclusive,Exclusive',
            'item_group' => 'required|in:Single,Box',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:1000',
            'hsn' => 'nullable|string|max:50',
            'alert_qty' => 'nullable|numeric|min:0',
            'is_serialized' => 'nullable',
            'discount_type' => 'required|in:Percentage,Fixed',
            'discount' => 'nullable|numeric|min:0',
            'mrp' => 'nullable|numeric|min:0',
            'seller_points' => 'nullable|numeric|min:0',
            'warehouse_id' => 'required|exists:db_warehouse,id',
            // Serial arrays are validated as arrays here (shape-level); the per-serial
            // uniqueness/duplicate checks happen in ItemSerialValidationService, whose
            // DuplicateSerialNumberException is mapped to the offending SLN row below.
            'serial_numbers' => 'nullable|array',
            'variant_serials' => 'nullable|array',
        ];

        if ($request->item_group === 'Single') {
            $rules += [
                'sku' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'sku')->where('store_id', current_store_id()), 'regex:/^[A-Za-z0-9\-_\.]+$/'],
                'custom_barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'custom_barcode')->where('store_id', current_store_id()), 'regex:/^[A-Za-z0-9\-_\.]+$/'],
                'price' => 'required|numeric|min:0',
                'purchase_price' => 'required|numeric|min:0',
                'profit_margin' => 'nullable|numeric|min:0',
                'sales_price' => 'required|numeric|min:0',
                'opening_stock' => 'nullable|numeric|min:0',
            ];
        } else {
            $rules += [
                'variants' => 'required|array|min:1',
                'variants.*.name' => 'required|string|max:255',
                'variants.*.sku' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'sku')->where('store_id', current_store_id()), 'regex:/^[A-Za-z0-9\-_\.]+$/'],
                'variants.*.barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'custom_barcode')->where('store_id', current_store_id()), 'regex:/^[A-Za-z0-9\-_\.]+$/'],
                'variants.*.price' => 'required|numeric|min:0',
                'variants.*.purchase_price' => 'required|numeric|min:0',
                'variants.*.profit' => 'nullable|numeric|min:0',
                'variants.*.sales_price' => 'required|numeric|min:0',
                'variants.*.stock' => 'nullable|numeric|min:0',
                'variants.*.hsn' => 'nullable|string|max:50',
            ];
        }

        $messages = [
            'item_name.required' => 'The item name is required.',
            'category_id.required' => 'Please select a category.',
            'unit_id.required' => 'Please select a unit.',
            'tax_id.required' => 'Please select a tax rate.',
            'tax_type.required' => 'Tax Type is required.',
            'item_group.required' => 'Please select an item group.',
            'sku.unique' => 'This SKU is already in use.',
            'sku.regex' => 'SKU may only contain letters, numbers, dash, underscore or dot.',
            'custom_barcode.unique' => 'This barcode is already in use.',
            'custom_barcode.regex' => 'Barcode may only contain letters, numbers, dash, underscore or dot.',
            'price.required' => 'Price is required.',
            'purchase_price.required' => 'Purchase Price is required.',
            'sales_price.required' => 'Sales Price is required.',
            'variants.*.name.required' => 'Variant name is required.',
            'variants.*.price.required' => 'Variant Price is required.',
            'variants.*.purchase_price.required' => 'Variant Purchase Price is required.',
            'variants.*.sales_price.required' => 'Variant Sales Price is required.',
            'variants.*.sku.unique' => 'Variant SKU is already in use.',
            'variants.*.sku.regex' => 'Variant SKU may only contain letters, numbers, dash, underscore or dot.',
            'variants.*.barcode.unique' => 'Variant barcode is already in use.',
            'variants.*.barcode.regex' => 'Variant barcode may only contain letters, numbers, dash, underscore or dot.',
            'warehouse_id.required' => 'A warehouse is required when stock is added.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

        // Serial count-vs-stock enforcement (server-side, inline-attributable to
        // serial_numbers / variant_serials so the blade can highlight the row).
        $validator->after(function ($validator) use ($request) {
            if ($request->item_group === 'Single' && $request->is_serialized == 1) {
                $openingStock = (int) ($request->opening_stock ?? 0);
                $serials = $request->serial_numbers ?? [];
                $filled = count(array_filter($serials, fn($s) => trim((string) $s) !== ''));
                if ($openingStock > 0 && $filled !== $openingStock) {
                    $validator->errors()->add('serial_numbers', "Please provide all {$openingStock} serial numbers (only {$filled} entered).");
                }
            }

            if ($request->item_group === 'Box' && $request->is_serialized == 1) {
                foreach (($request->variants ?? []) as $i => $v) {
                    $stock = (int) ($v['stock'] ?? 0);
                    $serials = $request->variant_serials[$i] ?? [];
                    $filled = count(array_filter($serials, fn($s) => trim((string) $s) !== ''));
                    if ($stock > 0 && $filled !== $stock) {
                        $validator->errors()->add("variant_serials.{$i}", "Variant " . ($i + 1) . ": please provide all {$stock} serial numbers (only {$filled} entered).");
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $validated = $validator->validated();

        // Resolve DNS outside the transaction to avoid holding a DB lock during
        // a potentially slow network call.
        $systemIp   = $request->ip();
        $systemName = $systemIp ? (@gethostbyaddr($systemIp) ?: 'unknown') : 'unknown';

        try {
            DB::beginTransaction();

            $tax     = DbTax::findOrFail($validated['tax_id']);
            $taxRate = (float)$tax->tax;

            // Item Code Generation
            $generateItemCode = function($offset = 1) {
                return \App\Services\CodeGeneratorService::generate('item', $offset);
            };

            // Helper function to prepare base item data
            $prepareItemData = function($request, $validated, $override = []) use ($taxRate, $systemIp, $systemName) {
                $data = [
                    'store_id' => current_store_id(),
                    'item_name' => trim($override['item_name'] ?? $validated['item_name']),
                    'category_id' => $validated['category_id'],
                    'brand_id' => $validated['brand_id'] ?? null,
                    'unit_id' => $validated['unit_id'],
                    'tax_id' => $validated['tax_id'],
                    'tax_type' => $validated['tax_type'],
                    'item_group' => $validated['item_group'],
                    'description' => $validated['description'] ?? null,
                    'hsn' => trim($override['hsn'] ?? $request->hsn),
                    'alert_qty' => $validated['alert_qty'] ?? 0,
                    'is_serialized' => ($request->is_serialized == 1 || ($override['is_serialized'] ?? false)) ? 1 : 0,
                    'discount_type' => $validated['discount_type'],
                    'discount' => (float)($validated['discount'] ?? 0),
                    'mrp' => (float)($validated['mrp'] ?? 0),
                    'seller_points' => (float)($validated['seller_points'] ?? 0),
                    'created_by' => auth()->id(),
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                    'system_ip' => $systemIp,
                    'system_name' => $systemName,
                    'status' => 1,
                    'item_code' => $override['item_code'] ?? null,
                ];

                // Pricing Sanitization & Calculation
                $price = (float)($override['price'] ?? $request->price ?? 0);
                $profitMargin = (float)($override['profit_margin'] ?? $request->profit_margin ?? 0);
                
                // Server-side Purchase Price Calculation
                if ($validated['tax_type'] === 'Exclusive') {
                    $purchasePrice = $price * (1 + $taxRate / 100);
                } else {
                    $purchasePrice = $price;
                }

                $salesPrice = (float)($override['sales_price'] ?? $request->sales_price ?? 0);
                
                // If profit is explicitly provided for a variant/single item, recalculate sales price to be sure
                if ($profitMargin > 0 && ($override['recalculate_sales'] ?? false)) {
                     $salesPrice = $purchasePrice * (1 + $profitMargin / 100);
                }

                $data += [
                    'price' => round($price, 2),
                    'purchase_price' => round($purchasePrice, 2),
                    'profit_margin' => round($profitMargin, 2),
                    'sales_price' => round($salesPrice, 2),
                    'sku' => $override['sku'] ?? $request->sku ?? null,
                    'custom_barcode' => $override['custom_barcode'] ?? $request->custom_barcode ?? null,
                    'stock' => $override['stock'] ?? 0,
                ];

                return $data;
            };

            // Handle Image Upload
            $itemImagePath = null;
            if ($request->hasFile('item_image')) {
                $image = $request->file('item_image');
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/items'), $filename);
                $itemImagePath = 'uploads/items/' . $filename;
            }

            if ($validated['item_group'] === 'Single') {
                // Single-item creation now delegates to the shared service so the main
                // Add Item flow and the purchase screen's Quick-Add flow stay identical.
                $item = app(ItemCreationService::class)->createSingleItem($validated, $request, $itemImagePath);
            } else {
                // Box / Variants logic
                $parentData = $prepareItemData($request, $validated, [
                    'sku' => $request->sku,
                    'custom_barcode' => $request->custom_barcode,
                    'price' => 0, 
                    'sales_price' => 0,
                    'item_code' => $generateItemCode(1)
                ]);
                if ($itemImagePath) $parentData['item_image'] = $itemImagePath;
                $parent = DbItem::create($parentData);

                if ($request->has('variants')) {
                    $variantCounter = 2; // Start child codes from IT-XXXXX+2
                    foreach ($request->variants as $index => $v) {
                        $vData = $prepareItemData($request, $validated, [
                            'item_name' => $validated['item_name'] . '-' . $v['name'],
                            'sku' => $v['sku'] ?? null,
                            'custom_barcode' => $v['barcode'] ?? null,
                            'price' => $v['price'],
                            'profit_margin' => $v['profit'] ?? 0,
                            'sales_price' => $v['sales_price'],
                            'hsn' => $v['hsn'] ?? null,
                            'item_code' => $generateItemCode($variantCounter++),
                            'recalculate_sales' => true,
                            'stock' => (float)($v['stock'] ?? 0)
                        ]);
                        
                        $vData['parent_id'] = $parent->id;
                        $vData['child_bit'] = 1;
                        if ($itemImagePath) $vData['item_image'] = $itemImagePath;

                        $child = DbItem::create($vData);

                        $variantStock = (float)($v['stock'] ?? 0);
                        if ($variantStock > 0) {
                            DbWarehouseItem::create([
                                'store_id' => current_store_id(),
                                'warehouse_id' => $request->warehouse_id,
                                'item_id' => $child->id,
                                'available_qty' => $variantStock
                            ]);

                            // Variant Serial Numbers
                            if ($child->is_serialized == 1 && isset($request->variant_serials[$index])) {
                                // Variant child items are brand-new here, so only an
                                // intra-submission duplicate (same serial typed into two
                                // slots of this variant's modal) can occur — reject cleanly.
                                $variantSerials = app(ItemSerialValidationService::class)
                                    ->validateNewItemSerials($request->variant_serials[$index], $child->item_name);

                                foreach ($variantSerials as $sn) {
                                    DbItemSerial::create([
                                        'store_id' => current_store_id(),
                                        'item_id' => $child->id,
                                        'serial_number' => $sn,
                                        'warehouse_id' => $request->warehouse_id,
                                        'status' => 0,
                                        'source' => 'item_add',
                                        'created_by' => auth()->id()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();
            return redirect()->route('items.list')->with('success', 'Item added successfully.');

        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            return $this->backWithSerialError($e, 'Error adding item: ' . $e->getMessage());
        } catch (QueryException $e) {
            DB::rollBack();
            // Defense-in-depth: DB unique-constraint violation on (item_id, serial_number)
            // raced past the pre-insert check (concurrent submissions) → clean message.
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            return $this->backWithSerialError($e, $msg ?? ('Error adding item: ' . $e->getMessage()));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error adding item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Redirect back with the DuplicateSerialNumberException message AND the
     * offending serial number flashed under a dedicated key, so the Add Item /
     * Edit Item form can render the error inline next to the exact SLN input row
     * instead of only as a top-of-form toast.
     */
    private function backWithSerialError(\Throwable $e, string $fallbackMessage)
    {
        $message = $e instanceof DuplicateSerialNumberException
            ? $e->getMessage()
            : ($fallbackMessage ?: $e->getMessage());

        $serial = null;
        if (preg_match('/Serial ([^\s]+) is/i', $message, $m)) {
            $serial = $m[1];
        } elseif (preg_match('/Serial ([^\s]+) is entered/i', $message, $m)) {
            $serial = $m[1];
        }

        return back()
            ->with('error', $message)
            ->with('serial_error', $serial)
            ->withInput();
    }

    public function show($id)
    {
        $item = DbItem::with(['category', 'brand', 'unit', 'tax', 'serials', 'warehouseItems.warehouse'])->findOrFail($id);
        $totalStock = (float) $item->warehouseItems->sum('available_qty');
        $variants = DbItem::where('parent_id', $id)->withCount('serials')->get();
        return view('module.items.view_item', compact('item', 'variants', 'totalStock'));
    }

    public function edit($id)
    {
        $item = DbItem::with(['serials', 'warehouseItems'])->findOrFail($id);
        $categories = DbCategory::where('status', 1)->where('store_id', current_store_id())->get();
        $brands = DbBrand::where('status', 1)->where('store_id', current_store_id())->get();
        $units = DbUnit::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        
        $variants = DbItem::where('parent_id', $id)->with(['serials', 'parent', 'warehouseItems'])->get();
        
        // Per-warehouse stock map for the item itself (used by Single items)
        $warehouseStocks = $item->warehouseItems->pluck('available_qty', 'warehouse_id')->toArray();
        $hasWarehouseRows = $item->warehouseItems->isNotEmpty();
        
        // Default the warehouse selector to the item's first warehouse row (fall back to
        // the first variant's row for Box parents). The stock field now shows the SELECTED
        // warehouse's quantity, not always the first row's.
        $warehouseItem = $item->warehouseItems->sortBy('id')->first();
        if(!$warehouseItem && $variants->count() > 0) {
            $firstVariant = $variants->first();
            $warehouseItem = $firstVariant->warehouseItems->sortBy('id')->first();
        }
        $warehouse_id = $warehouseItem ? $warehouseItem->warehouse_id : null;
        // Legacy fallback: items with NO warehouse rows keep their existing global
        // db_items.stock as the displayed quantity, so an edit never zeroes them out.
        $current_stock = $warehouseItem ? $warehouseItem->available_qty : (float)($item->stock ?? 0);

        $variantsData = $variants->map(function($v) use ($warehouse_id) {
            // Per-warehouse stock map for this variant (keyed by warehouse_id)
            $vWhStocks = $v->warehouseItems->pluck('available_qty', 'warehouse_id');
            $vHasWarehouseRows = $v->warehouseItems->isNotEmpty();
            return [
                'id' => $v->id,
                'name' => str_replace($v->parent->item_name.'-', '', $v->item_name),
                'sku' => $v->sku,
                'hsn' => $v->hsn,
                'barcode' => $v->custom_barcode,
                'price' => $v->price,
                'purchase_price' => $v->purchase_price,
                'profit' => $v->profit_margin,
                'sales_price' => $v->sales_price,
                'stock' => $vHasWarehouseRows ? (float)($vWhStocks[$warehouse_id] ?? 0) : (float)($v->stock ?? 0),
                'wh_stocks' => $vWhStocks->toArray(),
                'serials' => $v->serials->pluck('serial_number')
            ];
        });

        return view('module.items.edit_item', compact('item', 'categories', 'brands', 'units', 'taxes', 'warehouses', 'variants', 'warehouse_id', 'variantsData', 'current_stock', 'warehouseStocks', 'hasWarehouseRows'));
    }

    public function update(Request $request, $id)
    {
        // Load the existing item FIRST so the change-detection rules can compare the
        // submitted sku / custom_barcode against the stored (possibly legacy) value.
        $item = DbItem::findOrFail($id);

        // Preload existing variant sku/barcode maps for the same legacy comparison.
        $existingVariantSkus = DbItem::where('parent_id', $id)->pluck('sku', 'id')->map(fn ($v) => (string) ($v ?? ''))->all();
        $existingVariantBarcodes = DbItem::where('parent_id', $id)->pluck('custom_barcode', 'id')->map(fn ($v) => (string) ($v ?? ''))->all();

        // Legacy-safe format rule: the SKU/barcode format regex is ONLY enforced when
        // the submitted value actually CHANGES from the stored value. A pre-existing
        // non-conforming value (data entered before this rule existed) can be saved
        // untouched, but a genuinely new/changed value must conform.
        $legacyAwareFormat = function ($storedValue, string $message) {
            return function ($attribute, $value, $fail) use ($storedValue, $message) {
                $new = trim((string) $value);
                $old = trim((string) ($storedValue ?? ''));
                if ($new === '') {
                    return; // nullable — empty is fine
                }
                if ($new === $old) {
                    return; // unchanged legacy value — do not block the save
                }
                if (!preg_match('/^[A-Za-z0-9._-]+$/', $new)) {
                    $fail($message);
                }
            };
        };

        $single = $request->item_group == 'Single';

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|exists:db_category,id',
            'unit_id' => 'required|exists:db_units,id',
            'tax_id' => 'required|exists:db_tax,id',
            'item_group' => 'required|in:Single,Box',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'sku' => $single
                ? ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'sku')->where('store_id', current_store_id())->ignore($id), $legacyAwareFormat($item->sku, 'SKU may only contain letters, numbers, dash, underscore or dot.')]
                : ['nullable', 'string', 'max:100', $legacyAwareFormat($item->sku, 'SKU may only contain letters, numbers, dash, underscore or dot.')],
            'custom_barcode' => $single
                ? ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('db_items', 'custom_barcode')->where('store_id', current_store_id())->ignore($id), $legacyAwareFormat($item->custom_barcode, 'Barcode may only contain letters, numbers, dash, underscore or dot.')]
                : ['nullable', 'string', 'max:100', $legacyAwareFormat($item->custom_barcode, 'Barcode may only contain letters, numbers, dash, underscore or dot.')],
            'price' => $single ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'purchase_price' => $single ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'sales_price' => $single ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'required|exists:db_warehouse,id',

            // Variants validation if Item Group is Box
            'variants' => 'required_if:item_group,Box|array',
            'variants.*.name' => 'required_if:item_group,Box|string|max:255',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.price' => 'required_if:item_group,Box|numeric|min:0',
            'variants.*.purchase_price' => 'required_if:item_group,Box|numeric|min:0',
            'variants.*.sales_price' => 'required_if:item_group,Box|numeric|min:0',
            'variants.*.stock' => 'nullable|numeric|min:0',
        ], [
            'item_name.required' => 'The item name is required.',
            'category_id.required' => 'Please select a category.',
            'unit_id.required' => 'Please select a unit.',
            'tax_id.required' => 'Please select a tax rate.',
            'item_group.required' => 'Please select an item group.',
            'warehouse_id.required' => 'Please select a warehouse.',
            'sku.unique' => 'This SKU is already in use.',
            'custom_barcode.unique' => 'This barcode is already in use.',
        ]);

        // Same legacy-aware format enforcement for variant SKU/barcode (Box items).
        $validator->after(function ($validator) use ($request, $existingVariantSkus, $existingVariantBarcodes) {
            foreach (($request->variants ?? []) as $i => $v) {
                if (!empty($v['sku']) && isset($v['id'])) {
                    $old = (string) ($existingVariantSkus[$v['id']] ?? '');
                    if (trim((string) $v['sku']) !== $old && !preg_match('/^[A-Za-z0-9._-]+$/', trim((string) $v['sku']))) {
                        $validator->errors()->add("variants.{$i}.sku", 'Variant SKU may only contain letters, numbers, dash, underscore or dot.');
                    }
                }
                if (!empty($v['barcode']) && isset($v['id'])) {
                    $old = (string) ($existingVariantBarcodes[$v['id']] ?? '');
                    if (trim((string) $v['barcode']) !== $old && !preg_match('/^[A-Za-z0-9._-]+$/', trim((string) $v['barcode']))) {
                        $validator->errors()->add("variants.{$i}.barcode", 'Variant barcode may only contain letters, numbers, dash, underscore or dot.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        // NOTE: unlike store(), update() deliberately continues reading the original
        // $request below (not $validator->validated()) so fields without validation
        // rules — description, mrp, brand_id, hsn, discount_type, is_serialized, etc. —
        // are preserved exactly as before this change.

        try {
            DB::beginTransaction();

            // $item was already loaded before validation above; reuse that instance so
            // the change-detection rules and the save operate on the same row.
            $data = $request->except(['item_image', 'variants', 'serial_numbers', 'variant_serials']);
            
            // NOTE: 'stock' is intentionally NOT in this list. Global stock is always
            // derived as SUM(db_warehouseitems.available_qty) and must never be taken
            // from a single-warehouse form value (or defaulted to 0) — doing so silently
            // zeroed the parent's stock on Box item edits and corrupted multi-warehouse totals.
            $numericFields = ['price', 'purchase_price', 'sales_price', 'mrp', 'alert_qty', 'profit_margin', 'discount', 'seller_points', 'opening_stock', 'is_serialized'];
            foreach ($numericFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    $data[$field] = 0;
                }
            }

            if ($request->hasFile('item_image')) {
                if ($item->item_image && file_exists(public_path($item->item_image))) {
                    @unlink(public_path($item->item_image));
                }
                $image = $request->file('item_image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/items'), $filename);
                $data['item_image'] = 'uploads/items/' . $filename;
            }

            // Global stock is NEVER set from the form. It is recomputed below as the
            // sum of warehouse rows, and only when warehouse rows exist (legacy items
            // with no warehouse rows keep their existing db_items.stock untouched).
            unset($data['stock'], $data['opening_stock']);

            $item->update($data);

            if ($request->item_group == 'Single') {
                // Only the SELECTED warehouse's row is updated; other warehouses untouched.
                DbWarehouseItem::updateOrCreate(
                    ['store_id' => current_store_id(), 'warehouse_id' => $request->warehouse_id, 'item_id' => $item->id],
                    ['available_qty' => (float)($request->opening_stock ?? 0)]
                );

                if ($request->is_serialized == 1 && $request->has('serial_numbers')) {
                    // Intra-submission duplicates (the same serial typed twice) would
                    // otherwise be inserted twice (currentSerials is a stale snapshot)
                    // and trigger a raw DB unique-constraint error. Reject cleanly first.
                    $cleanedSerials = app(ItemSerialValidationService::class)
                        ->validateEditItemSerials($request->serial_numbers, $item->item_name);

                    $currentSerials = $item->serials()->pluck('serial_number')->toArray();

                    DbItemSerial::where('item_id', $item->id)
                        ->whereNotIn('serial_number', $cleanedSerials)
                        ->where('status', 0)
                        ->delete();

                    foreach ($cleanedSerials as $sn) {
                        if (!in_array($sn, $currentSerials)) {
                            DbItemSerial::create([
                                'store_id' => current_store_id(),
                                'item_id' => $item->id,
                                'serial_number' => $sn,
                                'warehouse_id' => $request->warehouse_id,
                                'status' => 0,
                                'source' => 'item_edit',
                                'created_by' => auth()->id()
                            ]);
                        }
                    }
                } else {
                    DbItemSerial::where('item_id', $item->id)->where('status', 0)->delete();
                }

                // Derive global stock from warehouse rows (if any exist).
                $this->syncGlobalStock($item->id);
            } else {
                $keepVariantIds = [];
                if ($request->has('variants')) {
                    foreach ($request->variants as $index => $v) {
                        $vData = $data;
                        $vData['item_name'] = $data['item_name'] . '-' . $v['name'];
                        $vData['sku'] = $v['sku'] ?? null;
                        $vData['hsn'] = $v['hsn'] ?? null;
                        $vData['custom_barcode'] = $v['barcode'] ?? null;
                        $vData['price'] = $v['price'] ?: 0;
                        $vData['purchase_price'] = $v['purchase_price'] ?: 0;
                        $vData['profit_margin'] = $v['profit'] ?: 0;
                        $vData['sales_price'] = $v['sales_price'] ?: 0;
                        $vData['parent_id'] = $item->id;
                        $vData['child_bit'] = 1;

                        if (isset($v['id']) && !empty($v['id'])) {
                            $child = DbItem::findOrFail($v['id']);
                            $child->update($vData);
                            $keepVariantIds[] = $child->id;
                        } else {
                            $child = DbItem::create($vData);
                            $keepVariantIds[] = $child->id;
                        }

                        // Only the SELECTED warehouse's row is updated for this variant.
                        DbWarehouseItem::updateOrCreate(
                            ['store_id' => current_store_id(), 'warehouse_id' => $request->warehouse_id, 'item_id' => $child->id],
                            ['available_qty' => (float)($v['stock'] ?? 0)]
                        );

                        // Derive this variant's global stock from its own warehouse rows.
                        $this->syncGlobalStock($child->id);

                        if ($request->is_serialized == 1 && isset($request->variant_serials[$index])) {
                            // Reject intra-submission duplicates cleanly before the
                            // reconcile-insert below can insert them twice.
                            $cleanedVSerials = app(ItemSerialValidationService::class)
                                ->validateEditItemSerials($request->variant_serials[$index], $child->item_name);

                            $currentVSerials = $child->serials()->pluck('serial_number')->toArray();

                            DbItemSerial::where('item_id', $child->id)
                                ->whereNotIn('serial_number', $cleanedVSerials)
                                ->where('status', 0)
                                ->delete();
                            
                            foreach ($cleanedVSerials as $sn) {
                                if (!in_array($sn, $currentVSerials)) {
                                    DbItemSerial::create([
                                        'store_id' => current_store_id(),
                                        'item_id' => $child->id,
                                        'serial_number' => $sn,
                                        'warehouse_id' => $request->warehouse_id,
                                        'status' => 0,
                                        'source' => 'item_edit',
                                        'created_by' => auth()->id()
                                    ]);
                                }
                            }
                        } else {
                           DbItemSerial::where('item_id', $child->id)->where('status', 0)->delete();
                        }
                    }
                }
                DbItem::where('parent_id', $item->id)->whereNotIn('id', $keepVariantIds)->delete();
            }

            DB::commit();
            return redirect()->route('items.list')->with('success', 'Item updated successfully.');

        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            return $this->backWithSerialError($e, 'Error updating item: ' . $e->getMessage());
        } catch (QueryException $e) {
            DB::rollBack();
            // Defense-in-depth: DB unique-constraint violation raced past the
            // pre-insert check → translate to a clean per-serial message.
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            return $this->backWithSerialError($e, $msg ?? ('Error updating item: ' . $e->getMessage()));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating item: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to delete a Store-1 item by supplying its id directly. Mirrors the exact
        // fix applied to AccountController::destroy() (and Deposit/Supplier/
        // CashReconciliation): the fetch is scoped by the current store, so a
        // cross-store id resolves to "not found" and is rejected cleanly.
        $item = DbItem::where('store_id', $storeId)->find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        // History guard: deleting a Box parent also cascade-deletes its variants,
        // so check the item AND any child variants (parent_id) for existing history
        // in every table that references db_items.id via an onDelete('cascade') FK
        // (enumerated directly from the migrations — sales items, sales returns,
        // purchase items, purchase returns, quotation items, stock adjustments,
        // stock transfers, stock entries, warehouse stock, and serials regardless
        // of status). Held carts (db_holditems) reference item_id without an FK but
        // are tracked history too. Any match blocks the delete.
        $itemIds = array_merge([$item->id], DbItem::where('parent_id', $item->id)->where('store_id', $storeId)->pluck('id')->all());

        // NOTE: db_warehouseitems (current warehouse stock) is intentionally NOT in
        // the blocking set — it is current inventory state, not historical usage,
        // and the clean-delete flow removes it alongside the item (verified by
        // ItemDeleteStoreScopeTest::test_same_store_delete_removes_variants_and_warehouse_rows).
        $hasHistory = $itemIds
            ? \Illuminate\Support\Facades\DB::table('db_salesitems')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_salesitemsreturn')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_purchaseitems')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_purchaseitemsreturn')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_quotationitems')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_stockadjustmentitems')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_stocktransferitems')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_stockentry')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_item_serials')->whereIn('item_id', $itemIds)->exists()
                || \Illuminate\Support\Facades\DB::table('db_holditems')->whereIn('item_id', $itemIds)->exists()
            : false;

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This item has existing sales/purchase history and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Delete variants if any — scoped to the same store as the parent
            DbItem::where('parent_id', $item->id)
                ->where('store_id', $storeId)
                ->delete();
            
            // Delete stock records — scoped to the same store
            DbWarehouseItem::where('item_id', $item->id)
                ->where('store_id', $storeId)
                ->delete();
            
            // Hard delete (db_items has no delete_bit / SoftDeletes — child rows in
            // sales/purchase/warehouse/serial tables cascade via DB FKs).
            $item->delete();
            
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Lightweight AJAX duplicate check used by the serial-entry modals (Add Item,
     * New/Edit Purchase, Purchase Quick-Add, Stock Adjustment) to warn the user
     * inline while typing/pasting — BEFORE they hit submit-time server validation.
     *
     * Per-item scope: returns whether `serial` is already registered for `item_id`.
     * This is a convenience only; the authoritative enforcement remains the
     * server-side pre-insert check + the UNIQUE(item_id, serial_number) constraint.
     */
    public function checkSerial(Request $request)
    {
        $itemId = (int) $request->get('item_id');
        $serial = trim((string) $request->get('serial'));

        if ($itemId <= 0 || $serial === '') {
            return response()->json(['exists' => false]);
        }

        $exists = DbItemSerial::where('item_id', $itemId)
            ->where('serial_number', $serial)
            ->exists();

        return response()->json([
            'exists' => $exists,
            'item_id' => $itemId,
            'serial' => $serial,
            'message' => $exists ? "Serial {$serial} is already registered for this item." : null,
        ]);
    }

    /**
     * Display the barcode label printing designer and sheet generator.
     */
    public function printLabels(Request $request)
    {
        if (!auth()->user()->hasPermission('items_print_labels')) {
            abort(403, 'Unauthorized access to Print Labels.');
        }

        // Resolve the acting store (the printing user's own store) rather than
        // DbStore::first(), so a Store-B user never sees Store 1's name on a label.
        $store = function_exists('store_settings') && store_settings()
            ? store_settings()
            : DbStore::first();
        $storeName = $store->store_name ?? 'COREVISYS POS';
        $categories = DbCategory::where('status', 1)->where('store_id', current_store_id())->get();
        $brands = DbBrand::where('status', 1)->where('store_id', current_store_id())->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();

        $initialItems = [];
        $itemIds = $request->input('items', []);
        if (is_string($itemIds)) {
            $itemIds = array_filter(explode(',', $itemIds));
        }

        if (!empty($itemIds)) {
            $dbItems = DbItem::with(['category', 'brand'])
                ->whereIn('id', (array)$itemIds)
                ->where('child_bit', 0)
                ->get();

            foreach ($dbItems as $dbItem) {
                $initialItems[] = $this->formatItemForLabel($dbItem);
            }
        }

        return view('module.items.print_labels', compact('storeName', 'categories', 'brands', 'warehouses', 'initialItems'));
    }

    /**
     * Search items for label printing.
     */
    public function searchItems(Request $request)
    {
        if (!auth()->user()->hasPermission('items_print_labels') && !auth()->user()->hasPermission('items_view')) {
            return response()->json([], 403);
        }

        $query = $request->get('query', '');
        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');

        $dbQuery = DbItem::with(['category', 'brand'])->where('child_bit', 0);

        if (!empty($query)) {
            $dbQuery->where(function($q) use ($query) {
                $q->where('item_name', 'LIKE', "%{$query}%")
                  ->orWhere('item_code', 'LIKE', "%{$query}%")
                  ->orWhere('custom_barcode', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            });
        }

        if (!empty($categoryId)) {
            $dbQuery->where('category_id', $categoryId);
        }

        if (!empty($brandId)) {
            $dbQuery->where('brand_id', $brandId);
        }

        $items = $dbQuery->limit(20)->get();

        $formatted = $items->map(function ($item) {
            return $this->formatItemForLabel($item);
        });

        return response()->json($formatted);
    }

    /**
     * Batch fetch items by Category / Brand / Warehouse for bulk label generation.
     */
    public function getBatchItemsForLabels(Request $request)
    {
        if (!auth()->user()->hasPermission('items_print_labels')) {
            return response()->json([], 403);
        }

        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');
        $warehouseId = $request->get('warehouse_id');

        $dbQuery = DbItem::with(['category', 'brand'])->where('child_bit', 0)->where('status', 1);

        if (!empty($categoryId)) {
            $dbQuery->where('category_id', $categoryId);
        }

        if (!empty($brandId)) {
            $dbQuery->where('brand_id', $brandId);
        }

        if (!empty($warehouseId)) {
            $itemIdsInWh = DbWarehouseItem::where('warehouse_id', $warehouseId)
                ->where('available_qty', '>', 0)
                ->pluck('item_id');
            $dbQuery->whereIn('id', $itemIdsInWh);
        }

        $items = $dbQuery->limit(100)->get();

        $formatted = $items->map(function ($item) {
            return $this->formatItemForLabel($item);
        });

        return response()->json($formatted);
    }

    /**
     * Helper to prepare processed stickers data and layout options from request.
     */
    private function prepareLabelData(Request $request): array
    {
        // Same acting-store resolution as printLabels() — the request-provided
        // store_name still overrides when supplied.
        $store = function_exists('store_settings') && store_settings()
            ? store_settings()
            : DbStore::first();
        $storeName = $request->input('store_name', $store->store_name ?? 'COREVISYS POS');
        $preset = $request->input('preset', 'sheet_24');
        $showStore = $request->boolean('show_store', true);
        $showName = $request->boolean('show_name', true);
        $showPrice = $request->boolean('show_price', true);
        $showBarcode = $request->boolean('show_barcode', true);
        $showCode = $request->boolean('show_code', true);

        $rawItems = $request->input('items', []);
        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rawItems = $decoded;
            } else {
                $rawItems = array_map(fn($id) => ['id' => trim($id), 'qty' => 1], array_filter(explode(',', $rawItems)));
            }
        }

        $stickers = [];
        $pngGen = new BarcodeGeneratorPNG();

        foreach ($rawItems as $entry) {
            $itemId = is_array($entry) ? ($entry['id'] ?? null) : $entry;
            $qty = is_array($entry) ? (int)($entry['qty'] ?? 1) : 1;
            if (!$itemId || $qty <= 0) continue;

            $dbItem = DbItem::with(['category', 'brand'])->find($itemId);
            if (!$dbItem) continue;

            $barcodeValue = trim($dbItem->custom_barcode ?: $dbItem->item_code);
            if (empty($barcodeValue)) {
                $barcodeValue = 'IT-' . str_pad((string)$dbItem->id, 5, '0', STR_PAD_LEFT);
            }

            try {
                $barcodePng = 'data:image/png;base64,' . base64_encode($pngGen->getBarcode($barcodeValue, BarcodeGeneratorPNG::TYPE_CODE_128, 1.5, 38));
            } catch (\Throwable $e) {
                $barcodePng = '';
            }

            $stickerData = [
                'id' => $dbItem->id,
                'name' => $dbItem->item_name,
                'code' => $dbItem->item_code,
                'barcode_value' => $barcodeValue,
                'barcode_png' => $barcodePng,
                'price' => (float) $dbItem->sales_price,
                'formatted_price' => \App\Providers\AppServiceProvider::resolveCurrencySymbol() . number_format($dbItem->sales_price, 2),
            ];

            for ($i = 0; $i < $qty; $i++) {
                $stickers[] = $stickerData;
            }
        }

        $cols = 3;
        switch ($preset) {
            case 'sheet_24':
            case 'sheet_30':
                $cols = 3;
                break;
            case 'sheet_12':
                $cols = 2;
                break;
            case 'sheet_40':
                $cols = 4;
                break;
            case 'thermal_roll':
                $cols = 1;
                break;
        }

        return compact('stickers', 'storeName', 'preset', 'showStore', 'showName', 'showPrice', 'showBarcode', 'showCode', 'cols');
    }

    /**
     * Direct print labels view: returns clean printable HTML with base64 PNG barcodes that triggers window.print().
     */
    public function directPrintLabels(Request $request)
    {
        if (!auth()->user()->hasPermission('items_print_labels')) {
            abort(403, 'Unauthorized access to Print Labels.');
        }

        $data = $this->prepareLabelData($request);
        if (empty($data['stickers'])) {
            return back()->with('error', 'No valid items in queue for printing.');
        }

        $data['autoprint'] = true;

        return view('module.items.pdf_labels', $data);
    }

    /**
     * Generate downloadable or streamable server-side PDF labels via DomPDF.
     */
    public function generatePdfLabels(Request $request)
    {
        if (!auth()->user()->hasPermission('items_print_labels')) {
            abort(403, 'Unauthorized access to Print Labels.');
        }

        $data = $this->prepareLabelData($request);
        if (empty($data['stickers'])) {
            return back()->with('error', 'No valid items in queue for PDF label generation.');
        }

        $preset = $data['preset'];
        $paper = 'a4';
        $orientation = 'portrait';
        $customPaper = null;

        if ($preset === 'thermal_roll') {
            // 50mm width x 25mm height in points -> [0, 0, 141.73, 70.87]
            $customPaper = [0, 0, 141.73, 70.87];
        }

        $pdf = Pdf::loadView('module.items.pdf_labels', $data);

        if ($customPaper) {
            $pdf->setPaper($customPaper);
        } else {
            $pdf->setPaper($paper, $orientation);
        }

        $filename = 'Barcode-Labels-' . date('Ymd-His') . '.pdf';

        $mode = $request->input('mode', 'download');
        if ($mode === 'download') {
            return $pdf->download($filename);
        }
        return $pdf->stream($filename);
    }

    /**
     * Helper to build scannable Code128 vector barcode and item payload.
     */
    private function formatItemForLabel(DbItem $item): array
    {
        $svgGen = new BarcodeGeneratorSVG();
        $pngGen = new BarcodeGeneratorPNG();
        $barcodeValue = trim($item->custom_barcode ?: $item->item_code);
        if (empty($barcodeValue)) {
            $barcodeValue = 'IT-' . str_pad((string)$item->id, 5, '0', STR_PAD_LEFT);
        }

        try {
            $barcodeSvg = $svgGen->getBarcode($barcodeValue, BarcodeGeneratorSVG::TYPE_CODE_128, 1.4, 38);
        } catch (\Throwable $e) {
            $barcodeSvg = '';
        }

        try {
            $barcodePng = 'data:image/png;base64,' . base64_encode($pngGen->getBarcode($barcodeValue, BarcodeGeneratorPNG::TYPE_CODE_128, 1.5, 38));
        } catch (\Throwable $e) {
            $barcodePng = '';
        }

        return [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'item_code' => $item->item_code,
            'custom_barcode' => $item->custom_barcode ?? '',
            'barcode_value' => $barcodeValue,
            'barcode_svg' => $barcodeSvg,
            'barcode_png' => $barcodePng,
            'sales_price' => (float) $item->sales_price,
            'stock' => (float) $item->stock,
            'category_name' => $item->category->category_name ?? '',
            'brand_name' => $item->brand->brand_name ?? '',
            'qty' => 1,
        ];
    }

    /**
     * Derive an item's global stock from its warehouse rows.
     *
     * Global db_items.stock is ALWAYS the sum of db_warehouseitems.available_qty across
     * all warehouses for that item. It is never set from a single-warehouse form value.
     *
     * Items with NO warehouse rows (legacy single-column data) keep their existing
     * db_items.stock untouched — this prevents a routine edit from zeroing out or
     * corrupting stock for pre-multi-warehouse records.
     */
    private function syncGlobalStock(int $itemId): void
    {
        $totalStock = (float) DbWarehouseItem::where('item_id', $itemId)->sum('available_qty');
        $hasWarehouseRows = DbWarehouseItem::where('item_id', $itemId)->exists();

        if ($hasWarehouseRows) {
            DbItem::where('id', $itemId)->update(['stock' => $totalStock]);
        }
    }
}
