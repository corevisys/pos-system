<?php

namespace App\Http\Controllers;

use App\Models\DbQuotation;
use App\Models\DbQuotationItem;
use App\Models\DbItem;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbTax;
use App\Models\DbPaymentType;
use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbUnit;
use App\Models\DbBrand;
use App\Models\DbWarehouseItem;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $storeId = current_store_id();
        $query = DbQuotation::where('store_id', $storeId)->with(['customer', 'warehouse', 'sale'])->latest();

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('quotation_code', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($sq) use ($search) {
                      $sq->where('customer_name', 'like', "%{$search}%");
                  });
            });
        }

        // Warehouse Filter
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Calculate Totals before pagination
        $stats = [
            'total_quotations' => (clone $query)->count(),
            'total_amount' => (float)(clone $query)->sum('grand_total'),
        ];

        $perPage = $request->input('per_page', 10);
        $quotations = $query->paginate($perPage)->withQueryString();

        $warehouses = DbWarehouse::where('store_id', $storeId)->where('status', 1)->get();

        return view('module.quotation.quotation_list', compact('quotations', 'warehouses', 'stats'));
    }

    public function create()
    {
        $storeId = current_store_id();
        $customers = DbCustomer::where('store_id', $storeId)->where('status', 1)->get();
        $warehouses = DbWarehouse::where('store_id', $storeId)->where('status', 1)->get();
        $taxes = DbTax::where('store_id', $storeId)->where('status', 1)->get();
        $categories = DbCategory::where('store_id', $storeId)->where('status', 1)->get();
        $units = DbUnit::where('store_id', $storeId)->where('status', 1)->get();
        $brands = DbBrand::where('store_id', $storeId)->where('status', 1)->get();

        return view('module.quotation.new_quotation', compact('customers', 'warehouses', 'taxes', 'categories', 'units', 'brands'));
    }

    public function searchItems(Request $request)
    {
        $search = trim($request->get('q'));
        if (empty($search)) {
            return response()->json([]);
        }
        
        $storeId = current_store_id();
        $searchLower = strtolower($search);
        
        // 1. Exact Match Priority
        $exactMatch = DbItem::with('tax')
            ->where('store_id', $storeId)
            ->where('status', 1) 
            ->where(function ($query) use ($search) {
                $query->where('custom_barcode', $search)
                      ->orWhere('item_code', $search)
                      ->orWhere('sku', $search);
            })
            ->first();

        if ($exactMatch) {
            return response()->json([$exactMatch]);
        }

        // 2. Fuzzy Search Fallback
        $items = DbItem::with('tax')
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->where(function ($query) use ($searchLower) {
                $query->whereRaw('LOWER(item_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(item_code) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(custom_barcode) LIKE ?', ["%{$searchLower}%"]);
            })
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        // Simple validation, can be expanded to a FormRequest later
        $request->validate([
            'customer_id' => 'required',
            'warehouse_id' => 'required',
            'quotation_date' => 'required|date',
            'cart' => 'required|array|min:1',
        ]);

        try {
            DB::beginTransaction();

            $totalSubtotal = 0;
            $totalTaxAmount = 0;
            $quotationItemsData = [];

            foreach ($request->cart as $cartItem) {
                $dbItem = DbItem::findOrFail($cartItem['item_id']);
                
                $qty = floatval($cartItem['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new \Exception("Invalid quantity for item: " . $dbItem->item_name);
                }

                // Phase A1: Honor user-entered custom / negotiated price (with fallback to catalog sales_price)
                $price = (isset($cartItem['price']) && is_numeric($cartItem['price']) && floatval($cartItem['price']) >= 0)
                    ? floatval($cartItem['price'])
                    : floatval($dbItem->sales_price);

                $taxId = $dbItem->tax_id;
                $taxRate = 0;
                $taxType = $dbItem->tax_type ?: 'Inclusive';

                if ($taxId) {
                    $dbTax = DbTax::find($taxId);
                    if ($dbTax) {
                        $taxRate = floatval($dbTax->tax);
                    }
                }

                // Phase A2: Apply item-level line discount before tax
                $lineTotalBase = $price * $qty;
                $lineDiscount = (isset($cartItem['discount']) && is_numeric($cartItem['discount']))
                    ? max(0, min(floatval($cartItem['discount']), $lineTotalBase))
                    : 0;
                $taxableBase = max(0, $lineTotalBase - $lineDiscount);

                // Phase A3: Accurate Inclusive/Exclusive Tax calculation
                if ($taxType == 'Inclusive') {
                    $lineTaxAmount = $taxRate > 0 ? ($taxableBase * $taxRate) / (100 + $taxRate) : 0;
                    $lineTotalWithTax = $taxableBase;
                    $unitTotalCost = $qty > 0 ? ($lineTotalWithTax / $qty) : $price;
                } else {
                    $lineTaxAmount = $taxRate > 0 ? ($taxableBase * $taxRate) / 100 : 0;
                    $lineTotalWithTax = $taxableBase + $lineTaxAmount;
                    $unitTotalCost = $qty > 0 ? ($lineTotalWithTax / $qty) : $price;
                }

                $totalSubtotal += $lineTotalBase;
                $totalTaxAmount += $lineTaxAmount;

                $quotationItemsData[] = [
                    'item_id' => $dbItem->id,
                    'quotation_qty' => $qty,
                    'price_per_unit' => $price,
                    'tax_id' => $taxId,
                    'tax_type' => $taxType,
                    'tax_amt' => round($lineTaxAmount, 2),
                    'discount_type' => 'Fixed',
                    'discount_input' => $lineDiscount,
                    'discount_amt' => $lineDiscount,
                    'unit_total_cost' => round($unitTotalCost, 2),
                    'total_cost' => round($lineTotalWithTax, 2),
                ];
            }

            // Phase A4: Global Discount Base Parity (Percentage applies to subtotal, matching PosController)
            $totalItemsCost = array_sum(array_column($quotationItemsData, 'total_cost'));
            $discountAmount = 0;
            $discountType = $request->discount_type;
            $discountInput = floatval($request->discount_on_all ?? 0);

            if ($discountType == 'Percentage' || $discountType == 'percent') {
                $discountAmount = ($totalSubtotal * $discountInput) / 100;
            } else {
                $discountAmount = $discountInput;
            }
            $discountAmount = min($discountAmount, $totalItemsCost);

            // Other Charges
            $otherCharges = floatval($request->other_charges_input ?? 0);
            $otherChargesTaxAmt = 0;
            if ($request->other_charges_tax_id) {
                $ocTax = DbTax::find($request->other_charges_tax_id);
                if ($ocTax) {
                     $otherChargesTaxAmt = ($otherCharges * floatval($ocTax->tax)) / 100;
                }
            }

            $roundOff = floatval($request->round_off ?? 0);
            $finalGrandTotal = $totalItemsCost - $discountAmount + $otherCharges + $otherChargesTaxAmt + $roundOff;

            if ($finalGrandTotal < 0) {
                 $finalGrandTotal = 0;
            }

            $quotationCode = \App\Services\CodeGeneratorService::generate('quotation');

            $quotation = DbQuotation::create([
                'store_id' => current_store_id(),
                'warehouse_id' => $request->warehouse_id,
                'quotation_code' => $quotationCode,
                'reference_no' => $request->reference_no,
                'quotation_date' => $request->quotation_date,
                'expire_date' => $request->expire_date,
                'quotation_status' => 'Quoted',
                'customer_id' => $request->customer_id,
                
                'other_charges_input' => $otherCharges,
                'other_charges_tax_id' => $request->other_charges_tax_id,
                'other_charges_amt' => $otherChargesTaxAmt,
                
                'discount_to_all_input' => $discountInput,
                'discount_to_all_type' => $discountType,
                'tot_discount_to_all_amt' => $discountAmount,
                
                'subtotal' => $totalSubtotal,
                'round_off' => $roundOff,
                'grand_total' => $finalGrandTotal,
                'quotation_note' => $request->note,
                
                'created_by' => Auth::id(),
                'system_ip' => $request->ip(),
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
            ]);

            foreach ($quotationItemsData as $itemData) {
                DbQuotationItem::create(array_merge($itemData, [
                    'store_id' => $quotation->store_id,
                    'quotation_id' => $quotation->id,
                    'quotation_status' => $quotation->quotation_status,
                ]));
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Quotation saved successfully', 'redirect' => route('quotation.invoice', ['id' => $quotation->id], false)]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save quotation: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $storeId = current_store_id();
        $quotation = DbQuotation::with(['items.item.tax', 'customer', 'warehouse'])
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->firstOrFail();

        if ($quotation->quotation_status === 'Converted') {
            return redirect()->route('quotation.invoice', $quotation->id)->with('error', 'Converted quotations cannot be edited.');
        }

        $customers = DbCustomer::where('store_id', $storeId)->where('status', 1)->get();
        $warehouses = DbWarehouse::where('store_id', $storeId)->where('status', 1)->get();
        $taxes = DbTax::where('store_id', $storeId)->where('status', 1)->get();
        $categories = DbCategory::where('store_id', $storeId)->where('status', 1)->get();
        $units = DbUnit::where('store_id', $storeId)->where('status', 1)->get();
        $brands = DbBrand::where('store_id', $storeId)->where('status', 1)->get();

        return view('module.quotation.edit_quotation', compact(
            'quotation', 'customers', 'warehouses', 'taxes', 
            'categories', 'units', 'brands'
        ));
    }

    public function invoice($id)
    {
        $storeId = current_store_id();
        $quotation = DbQuotation::with(['customer.country', 'customer.state', 'warehouse', 'items.item.tax', 'items.item.unit', 'sale'])
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->firstOrFail();
            
        return view('module.quotation.quotation_invoice', compact('quotation'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'customer_id' => 'required',
            'warehouse_id' => 'required',
            'quotation_date' => 'required|date',
            'cart' => 'required|array|min:1',
        ]);

        try {
            $storeId = current_store_id();
            $quotation = DbQuotation::where('id', $id)->where('store_id', $storeId)->first();
            if (!$quotation) {
                return response()->json(['success' => false, 'message' => 'Quotation not found.'], 404);
            }

            if ($quotation->quotation_status === 'Converted') {
                return response()->json(['success' => false, 'message' => 'Cannot modify a quotation that has already been converted to a sale.'], 422);
            }

            DB::beginTransaction();

            $totalSubtotal = 0;
            $totalTaxAmount = 0;
            $quotationItemsData = [];

            foreach ($request->cart as $cartItem) {
                $dbItem = DbItem::findOrFail($cartItem['item_id']);
                
                $qty = floatval($cartItem['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new \Exception("Invalid quantity for item: " . $dbItem->item_name);
                }

                // Phase A1: Honor user-entered custom / negotiated price (with fallback to catalog sales_price)
                $price = (isset($cartItem['price']) && is_numeric($cartItem['price']) && floatval($cartItem['price']) >= 0)
                    ? floatval($cartItem['price'])
                    : floatval($dbItem->sales_price);

                $taxId = $dbItem->tax_id;
                $taxRate = 0;
                $taxType = $dbItem->tax_type ?: 'Inclusive';

                if ($taxId) {
                    $dbTax = DbTax::find($taxId);
                    if ($dbTax) {
                        $taxRate = floatval($dbTax->tax);
                    }
                }

                // Phase A2: Apply item-level line discount before tax
                $lineTotalBase = $price * $qty;
                $lineDiscount = (isset($cartItem['discount']) && is_numeric($cartItem['discount']))
                    ? max(0, min(floatval($cartItem['discount']), $lineTotalBase))
                    : 0;
                $taxableBase = max(0, $lineTotalBase - $lineDiscount);

                // Phase A3: Accurate Inclusive/Exclusive Tax calculation
                if ($taxType == 'Inclusive') {
                    $lineTaxAmount = $taxRate > 0 ? ($taxableBase * $taxRate) / (100 + $taxRate) : 0;
                    $lineTotalWithTax = $taxableBase;
                    $unitTotalCost = $qty > 0 ? ($lineTotalWithTax / $qty) : $price;
                } else {
                    $lineTaxAmount = $taxRate > 0 ? ($taxableBase * $taxRate) / 100 : 0;
                    $lineTotalWithTax = $taxableBase + $lineTaxAmount;
                    $unitTotalCost = $qty > 0 ? ($lineTotalWithTax / $qty) : $price;
                }

                $totalSubtotal += $lineTotalBase;
                $totalTaxAmount += $lineTaxAmount;

                $quotationItemsData[] = [
                    'item_id' => $dbItem->id,
                    'quotation_qty' => $qty,
                    'price_per_unit' => $price,
                    'tax_id' => $taxId,
                    'tax_type' => $taxType,
                    'tax_amt' => round($lineTaxAmount, 2),
                    'discount_type' => 'Fixed',
                    'discount_input' => $lineDiscount,
                    'discount_amt' => $lineDiscount,
                    'unit_total_cost' => round($unitTotalCost, 2),
                    'total_cost' => round($lineTotalWithTax, 2),
                ];
            }

            // Phase A4: Global Discount Base Parity (Percentage applies to subtotal, matching PosController)
            $totalItemsCost = array_sum(array_column($quotationItemsData, 'total_cost'));
            $discountAmount = 0;
            $discountType = $request->discount_type;
            $discountInput = floatval($request->discount_on_all ?? 0);

            if ($discountType == 'Percentage' || $discountType == 'percent') {
                $discountAmount = ($totalSubtotal * $discountInput) / 100;
            } else {
                $discountAmount = $discountInput;
            }
            $discountAmount = min($discountAmount, $totalItemsCost);

            // Other Charges
            $otherCharges = floatval($request->other_charges_input ?? 0);
            $otherChargesTaxAmt = 0;
            if ($request->other_charges_tax_id) {
                $ocTax = DbTax::find($request->other_charges_tax_id);
                if ($ocTax) {
                     $otherChargesTaxAmt = ($otherCharges * floatval($ocTax->tax)) / 100;
                }
            }

            $roundOff = floatval($request->round_off ?? 0);
            $finalGrandTotal = $totalItemsCost - $discountAmount + $otherCharges + $otherChargesTaxAmt + $roundOff;

            if ($finalGrandTotal < 0) {
                 $finalGrandTotal = 0;
            }

            $quotation->update([
                'warehouse_id' => $request->warehouse_id,
                'reference_no' => $request->reference_no,
                'quotation_date' => $request->quotation_date,
                'expire_date' => $request->expire_date,
                'customer_id' => $request->customer_id,
                
                'other_charges_input' => $otherCharges,
                'other_charges_tax_id' => $request->other_charges_tax_id,
                'other_charges_amt' => $otherChargesTaxAmt,
                
                'discount_to_all_input' => $discountInput,
                'discount_to_all_type' => $discountType,
                'tot_discount_to_all_amt' => $discountAmount,
                
                'subtotal' => $totalSubtotal,
                'round_off' => $roundOff,
                'grand_total' => $finalGrandTotal,
                'quotation_note' => $request->note,
            ]);

            // Delete old items and re-create
            $quotation->items()->delete();

            foreach ($quotationItemsData as $itemData) {
                DbQuotationItem::create(array_merge($itemData, [
                    'store_id' => $quotation->store_id,
                    'quotation_id' => $quotation->id,
                    'quotation_status' => $quotation->quotation_status,
                ]));
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Quotation updated successfully', 'redirect' => route('quotation.invoice', ['id' => $quotation->id], false)]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json(['success' => false, 'message' => 'Failed to update quotation: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $storeId = current_store_id();
            $quotation = DbQuotation::where('id', $id)->where('store_id', $storeId)->first();
            if (!$quotation) {
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Quotation not found.'], 404);
                }
                abort(404, 'Quotation not found.');
            }

            if ($quotation->quotation_status === 'Converted') {
                if (request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Cannot delete a quotation that has already been converted to a sale.'], 422);
                }
                return redirect()->back()->with('error', 'Cannot delete a quotation that has already been converted to a sale.');
            }

            DB::beginTransaction();
            $quotation->items()->delete();
            $quotation->delete();
            DB::commit();

            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Quotation deleted successfully']);
            }
            return redirect()->route('quotation.list')->with('success', 'Quotation deleted successfully');
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Quoted,Accepted,Rejected',
        ]);

        $storeId = current_store_id();
        $quotation = DbQuotation::where('id', $id)->where('store_id', $storeId)->first();
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => 'Quotation not found.'], 404);
        }

        if ($quotation->quotation_status === 'Converted') {
            return response()->json(['success' => false, 'message' => 'Cannot change status of a converted quotation.'], 422);
        }

        $quotation->update([
            'quotation_status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quotation status updated to ' . $request->status,
            'status' => $request->status,
        ]);
    }

    public function convertToSale(Request $request, $id)
    {
        try {
            $storeId = current_store_id();
            DB::beginTransaction();

            $quotation = DbQuotation::where('id', $id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$quotation) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found.',
                ], 404);
            }

            // C1-ii Guard: Double conversion attempt rejection
            if ($quotation->quotation_status === 'Converted') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This quotation has already been converted to a sale.',
                ], 422);
            }

            // Expiry Guard
            if ($quotation->isExpired()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This quotation has expired and cannot be converted to a sale.',
                ], 422);
            }

            $quotationItems = $quotation->items()->with('item')->get();
            if ($quotationItems->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot convert quotation with no line items.',
                ], 422);
            }

            // Stock Availability Verification for all items
            foreach ($quotationItems as $qItem) {
                $whItem = DbWarehouseItem::where('warehouse_id', $quotation->warehouse_id)
                    ->where('item_id', $qItem->item_id)
                    ->lockForUpdate()
                    ->first();

                $availableQty = $whItem ? floatval($whItem->available_qty) : 0;
                $requiredQty = floatval($qItem->quotation_qty);

                if ($availableQty < $requiredQty) {
                    DB::rollBack();
                    $itemName = $qItem->item ? $qItem->item->item_name : "Item #{$qItem->item_id}";
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient warehouse stock for {$itemName}. Required: {$requiredQty}, Available: {$availableQty}.",
                    ], 422);
                }
            }

            // Generate Sales Code
            $salesCode = \App\Services\CodeGeneratorService::generate('sales');

            // Create DbSale
            $sale = DbSale::create([
                'store_id' => $storeId,
                'warehouse_id' => $quotation->warehouse_id,
                'sales_code' => $salesCode,
                'sales_date' => date('Y-m-d'),
                'due_date' => $quotation->expire_date,
                'reference_no' => $quotation->reference_no,
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'subtotal' => $quotation->subtotal,
                'grand_total' => $quotation->grand_total,
                'round_off' => $quotation->round_off ?? 0,
                'paid_amount' => 0,
                'payment_status' => 'Unpaid',
                'other_charges_input' => $quotation->other_charges_input ?? 0,
                'other_charges_amt' => $quotation->other_charges_amt ?? 0,
                'discount_to_all_input' => $quotation->discount_to_all_input ?? 0,
                'discount_to_all_type' => $quotation->discount_to_all_type ?? 'Fixed',
                'tot_discount_to_all_amt' => $quotation->tot_discount_to_all_amt ?? 0,
                'sales_note' => $quotation->quotation_note,
                'created_by' => Auth::id(),
                'system_ip' => $request->ip(),
                'status' => 1,
                'pos' => 0,
            ]);

            // Create DbSaleItem and decrement stock
            foreach ($quotationItems as $qItem) {
                DbSaleItem::create([
                    'store_id' => $storeId,
                    'sales_id' => $sale->id,
                    'item_id' => $qItem->item_id,
                    'sales_qty' => $qItem->quotation_qty,
                    'price_per_unit' => $qItem->price_per_unit,
                    'tax_type' => $qItem->tax_type,
                    'tax_id' => $qItem->tax_id,
                    'tax_amt' => $qItem->tax_amt,
                    'discount_type' => $qItem->discount_type,
                    'discount_input' => $qItem->discount_input,
                    'discount_amt' => $qItem->discount_amt,
                    'unit_total_cost' => $qItem->unit_total_cost,
                    'total_cost' => $qItem->total_cost,
                    'status' => 1,
                ]);

                // Decrement stock in catalog and warehouse
                $dbItem = DbItem::find($qItem->item_id);
                if ($dbItem) {
                    $dbItem->decrement('stock', $qItem->quotation_qty);
                }

                $whItem = DbWarehouseItem::where('warehouse_id', $quotation->warehouse_id)
                    ->where('item_id', $qItem->item_id)
                    ->first();
                if ($whItem) {
                    $whItem->decrement('available_qty', $qItem->quotation_qty);
                }

                try {
                    if (class_exists(\App\Services\SmsTriggerService::class)) {
                        app(\App\Services\SmsTriggerService::class)->checkStockAlerts($qItem->item_id, $quotation->warehouse_id);
                    }
                } catch (\Throwable $t) {
                    // Alert check failure should not block transaction
                }
            }

            // Mark Quotation status as Converted atomically
            $quotation->update([
                'quotation_status' => 'Converted',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Quotation successfully converted to Sale {$sale->sales_code}.",
                'sale_id' => $sale->id,
                'sales_code' => $sale->sales_code,
                'redirect' => route('sales.invoice', ['id' => $sale->id], false),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
