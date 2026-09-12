<?php

namespace App\Http\Controllers;

use App\Models\DbPurchase;
use App\Models\DbPurchaseItem;
use App\Models\DbItem;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\DbTax;
use App\Models\DbPaymentType;
use App\Models\AcAccount;
use App\Models\DbCategory;
use App\Models\DbUnit;
use App\Models\DbBrand;
use App\Models\DbWarehouseItem;
use App\Models\DbPurchaseReturn;
use App\Models\DbPurchaseItemReturn;
use App\Models\DbPurchasePaymentReturn;
use App\Models\DbPurchasePayment;
use App\Models\DbItemSerial;
use App\Models\AcTransaction;
use App\Exceptions\DuplicateSerialNumberException;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\SMS\Services\SmsTriggerService;
use App\Services\ItemCreationService;
use App\Services\ItemSerialValidationService;

class PurchaseController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_view')) {
            abort(403, 'Unauthorized access to purchases.');
        }

        $query = DbPurchase::with(['supplier', 'warehouse'])
            ->where('store_id', current_store_id())
            ->latest();

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('purchase_code', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('supplier_name', 'like', "%{$search}%");
                  });
            });
        }

        // Date Range Filter
        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        // Warehouse Filter
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Payment Status Filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Calculate Totals before pagination
        $stats = [
            'total_invoices' => $query->count(),
            'total_amount' => (float)$query->sum('grand_total'),
            'total_paid' => (float)$query->sum('paid_amount'),
        ];
        $stats['total_due'] = max(0, $stats['total_amount'] - $stats['total_paid']);

        $perPage = (int) $request->input('per_page', 10);
        $purchases = $query->paginate($perPage)->withQueryString();

        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $accounts = AcAccount::where('store_id', current_store_id())->where('status', 1)->get();
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();

        return view('module.purchase.purchase_list', compact('purchases', 'warehouses', 'stats', 'accounts', 'paymentTypes'));
    }

    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_add')) {
            abort(403, 'Unauthorized access to create purchases.');
        }

        $suppliers = DbSupplier::where('status', 1)->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $accounts = AcAccount::where('status', 1)->get();
        $categories = DbCategory::where('status', 1)->get();
        $units = DbUnit::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $brands = DbBrand::where('status', 1)->get();

        return view('module.purchase.new_purchase', compact('suppliers', 'warehouses', 'taxes', 'paymentTypes', 'accounts', 'categories', 'units', 'brands'));
    }

    public function searchItems(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_add')) {
            return response()->json([], 403);
        }

        $search = trim($request->get('q'));
        if (empty($search)) {
            return response()->json([]);
        }
        
        $searchLower = strtolower($search);
        
        // 1. Exact Match Priority (Barcode, Item Code, SKU)
        // This ensures that scanning a barcode (which sends an exact string) returns the specific item immediately.
        $exactMatch = DbItem::with('tax')
            ->select('*', 'price as purchase_price')
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
            ->select('*', 'price as purchase_price')
            ->where('status', 1)
            ->where(function ($query) use ($searchLower) {
                $query->whereRaw('LOWER(item_name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(item_code) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(custom_barcode) LIKE ?', ["%{$searchLower}%"]);
            })
            ->limit(20)
            ->get();

        \Log::info('Search Count: ' . $items->count());

        return response()->json($items);
    }

    public function store(StorePurchaseRequest $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_add')) {
            abort(403, 'Unauthorized access to create purchases.');
        }

        try {
            DB::beginTransaction();

            $totalSubtotal = 0;
            $totalTaxAmount = 0;
            $grandTotal = 0;
            $purchaseItemsData = [];

            // 1. Process Cart Items & Calculate Totals
            foreach ($request->cart as $cartItem) {
                // Fetch Item from DB (Locking for update to prevent race conditions on stock could be added here, but skipping for standard implementation)
                $dbItem = DbItem::findOrFail($cartItem['item_id']);
                
                // Validate inputs that shouldn't happen due to Request validation but good for sanity
                $qty = $cartItem['qty'];
                if ($qty <= 0) {
                    throw new \Exception("Invalid quantity for item: " . $dbItem->item_name);
                }

                // Serialized Item Validation
                // Use the is_serialized flag from the cart item if available, otherwise fallback to DB
                $is_serialized = isset($cartItem['is_serialized']) ? (int)$cartItem['is_serialized'] : $dbItem->is_serialized;
                
                if ($is_serialized == 1) {
                    $serials = $cartItem['serials'] ?? [];
                    $serialCount = count($serials);
                    if ($qty != $serialCount) {
                        throw new \Exception("Item '{$dbItem->item_name}' is serialized. Quantity ({$qty}) must match the number of serial numbers provided ({$serialCount}).");
                    }
                }

                // Use price from frontend, fallback to DB if missing
                $price = (float)($cartItem['price'] ?? (($dbItem->purchase_price > 0) ? $dbItem->purchase_price : ($dbItem->price ?? 0)));

                // Tax Handling: Prefer cart tax if provided and valid, otherwise fallback to item default
                $taxId = $cartItem['tax_id'] ?? $dbItem->tax_id;
                $taxType = $cartItem['tax_type'] ?? ($dbItem->tax_type ?? 'Exclusive');
                $taxRate = 0;

                if ($taxId) {
                    $dbTax = DbTax::find($taxId);
                    if ($dbTax) {
                        $taxRate = $dbTax->tax;
                    } else {
                        $taxId = null; // Reset if invalid tax_id provided
                    }
                }

                // Calculate Line Values
                $discount = (float)($cartItem['discount'] ?? 0);
                $lineSubtotalBeforeTax = ($price * $qty) - $discount;
                $lineTaxAmount = 0;

                if ($taxType == 'Inclusive') {
                    // Total already includes tax: Tax = SubtotalAfterDisc - (SubtotalAfterDisc / (1 + Rate/100))
                    $lineTaxAmount = $lineSubtotalBeforeTax - ($lineSubtotalBeforeTax / (1 + ($taxRate / 100)));
                    $lineTotalWithTax = $lineSubtotalBeforeTax;
                } else {
                    // Exclusive: Tax = SubtotalAfterDisc * (Rate/100)
                    $lineTaxAmount = ($lineSubtotalBeforeTax * $taxRate) / 100;
                    $lineTotalWithTax = $lineSubtotalBeforeTax + $lineTaxAmount;
                }

                $totalSubtotal += ($price * $qty);
                $totalTaxAmount += $lineTaxAmount;

                // Tax-inclusive landed unit cost (matches Add Item's purchase_price definition)
                $unitTotalCost = ($qty > 0) ? ($lineTotalWithTax / $qty) : 0;

                // Prepare Item Data for Insertion later
                $purchaseItemsData[] = [
                    'item_id' => $dbItem->id,
                    'qty' => $qty,
                    'price' => $price,
                    'discount_amt' => $discount,
                    'tax_id' => $taxId,
                    'tax_type' => $taxType,
                    'tax_amount' => $lineTaxAmount,
                    'unit_total_cost' => $unitTotalCost,
                    'total_cost' => $lineTotalWithTax,
                    'serials' => $cartItem['serials'] ?? []
                ];

                // Capture the item's stock BEFORE this purchase. Note: $dbItem->increment()
                // does NOT refresh the in-memory model, so $dbItem->stock still holds the
                // pre-purchase value here — do NOT subtract $qty again.
                $oldGlobalStock = (float) $dbItem->stock;

                // Update Stock
                $dbItem->increment('stock', $qty);
                $warehouseItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_id)
                    ->where('item_id', $dbItem->id)
                    ->lockForUpdate()
                    ->first();
                if ($warehouseItem) {
                    $warehouseItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => Auth::user()->store_id ?? 1,
                        'warehouse_id' => $request->warehouse_id,
                        'item_id' => $dbItem->id,
                        'available_qty' => $qty,
                    ]);
                }

                // ============================================================
                // Weighted-average cost update (Bug A fix) — shared helper.
                // purchase_price is stored TAX-INCLUSIVE (matching Add Item), so we
                // average using $unitTotalCost (line total incl. tax / qty), NOT the
                // raw pre-tax cart price. We use the item's global stock BEFORE this
                // purchase as the old quantity on hand. sales_price is recomputed from
                // the item's EXISTING profit_margin; if margin is 0/null, sales_price
                // is left unchanged. 'price' (pre-tax base) is never touched here.
                // ============================================================
                $this->applyPurchaseCostToItem($dbItem, $unitTotalCost, $oldGlobalStock, $qty);
            }

            // 1.b. Per-item serial-number uniqueness check BEFORE any purchase/serial
            // rows are inserted. Every serial in every cart line is validated against
            // its OWN item_id's existing db_item_serials rows (cross-entry-point) and
            // against serials typed multiple times within this same submission.
            // Throws DuplicateSerialNumberException → clean 422 message below.
            app(ItemSerialValidationService::class)->validateSerialsForPurchaseLines($purchaseItemsData);

            // 2. Calculate Purchase Details (Discount, Other Charges)
            
            // Discount
            $discountAmount = 0;
            $discountType = $request->discount_type;
            $discountInput = $request->discount_on_all ?? 0;

            // Calculate Subtotal for Discount Base (usually basic subtotal of items)
            // If Tax is inclusive, Subtotal includes tax. If exclusive, it doesn't.
            // Let's use the sum of line totals (pre-calculation logic)
            // Actually, let's derive Grand Total from the components.
            
            $tempTotal = 0;
            foreach($purchaseItemsData as $pItem) {
                $tempTotal += $pItem['total_cost'];
            }

            if ($discountType == 'Percentage') {
                $discountAmount = ($totalSubtotal * $discountInput) / 100;
            } else {
                $discountAmount = $discountInput;
            }

            // Other Charges
            $otherCharges = $request->other_charges_input ?? 0;
            $otherChargesTaxAmt = 0;
            
            // Other Charges Tax (if applicable)
            if ($request->other_charges_tax_id) {
                $ocTax = DbTax::find($request->other_charges_tax_id);
                if ($ocTax) {
                     // Assuming Other Charges Tax is Exclusive
                     $otherChargesTaxAmt = ($otherCharges * $ocTax->tax) / 100;
                }
            }

            // Round Off
            $roundOff = $request->round_off ?? 0;

            // Final Grand Total
            // Grand Total = Sum(Item Costs) - Discount + Other Charges + Other Charges Tax + Round Off
            $finalGrandTotal = $tempTotal - $discountAmount + $otherCharges + $otherChargesTaxAmt + $roundOff;

            // Prevent Negative Grand Total
            if ($finalGrandTotal < 0) {
                 $finalGrandTotal = 0;
            }

            $amountPaid = (float) ($request->amount_paid ?? 0);
            if ($amountPaid > $finalGrandTotal) {
                throw new \InvalidArgumentException('Payment cannot exceed the purchase grand total.');
            }
            if ($amountPaid > 0 && empty($request->account_id)) {
                throw new \InvalidArgumentException('Please select a Bank / Cash Account when entering a paid amount.');
            }

            // 3. Create Purchase Record
            $purchaseCode = \App\Services\CodeGeneratorService::generate('purchase');
            
            $purchase = DbPurchase::create([
                'store_id' => Auth::user()->store_id ?? 1,
                'warehouse_id' => $request->warehouse_id,
                'purchase_code' => $purchaseCode,
                'reference_no' => $request->reference_no,
                'purchase_date' => $request->purchase_date,
                'purchase_status' => 'Received',
                'supplier_id' => $request->supplier_id,
                
                'other_charges_input' => $otherCharges,
                'other_charges_tax_id' => !empty($request->other_charges_tax_id) ? $request->other_charges_tax_id : null,
                'other_charges_amt' => $otherChargesTaxAmt,
                
                'discount_to_all_input' => $discountInput,
                'discount_to_all_type' => $discountType,
                'tot_discount_to_all_amt' => $discountAmount,
                
                'subtotal' => $totalSubtotal, // Sum of (Price * Qty)
                'round_off' => $roundOff,
                'grand_total' => $finalGrandTotal,
                'purchase_note' => $request->note,
                
                // Payment Status Logic
                'payment_status' => ($request->amount_paid ?? 0) >= $finalGrandTotal 
                                    ? 'Paid' 
                                    : (($request->amount_paid ?? 0) > 0 ? 'Partial' : 'Unpaid'),
                'paid_amount' => $amountPaid,
                
                'created_by' => Auth::id(),
                'system_ip' => $request->ip(),
            ]);

            // 4. Create Purchase Items
            foreach ($purchaseItemsData as $itemData) {
                $pItem = DbPurchaseItem::create([
                    'store_id' => $purchase->store_id,
                    'purchase_id' => $purchase->id,
                    'item_id' => $itemData['item_id'],
                    'purchase_qty' => $itemData['qty'],
                    'price_per_unit' => $itemData['price'],
                    'tax_amt' => $itemData['tax_amount'],
                    'tax_id' => $itemData['tax_id'],
                    'tax_type' => $itemData['tax_type'],
                    'discount_amt' => $itemData['discount_amt'],
                    'unit_total_cost' => $itemData['unit_total_cost'],
                    'total_cost' => $itemData['total_cost'],
                ]);

                // Save Serials
                if (!empty($itemData['serials'])) {
                    foreach ($itemData['serials'] as $sn) {
                        \App\Models\DbItemSerial::create([
                            'store_id' => $purchase->store_id,
                            'purchase_id' => $purchase->id,
                            'item_id' => $itemData['item_id'],
                            'serial_number' => $sn,
                            'warehouse_id' => $purchase->warehouse_id,
                            'status' => 0, // Available
                            'source' => 'purchase',
                            'created_by' => Auth::id()
                        ]);
                    }
                }
            }

            if ($amountPaid > 0) {
                $payment = DbPurchasePayment::create([
                    'store_id' => $purchase->store_id,
                    'purchase_id' => $purchase->id,
                    'payment_date' => $purchase->purchase_date,
                    'payment_type' => $request->payment_type ?? 'Cash',
                    'payment' => $amountPaid,
                    'created_by' => Auth::id(),
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                    'system_ip' => $request->ip(),
                    'system_name' => gethostname(),
                    'status' => 1,
                    'account_id' => $request->account_id,
                    'supplier_id' => $purchase->supplier_id,
                ]);

                if ($payment->account_id) {
                    AcTransaction::create([
                        'store_id' => $purchase->store_id,
                        'transaction_date' => $payment->payment_date,
                        'transaction_type' => 'PURCHASE PAYMENT',
                        'payment_code' => $purchase->purchase_code,
                        'debit_account_id' => $payment->account_id,
                        'credit_account_id' => null,
                        'debit_amt' => $amountPaid,
                        'credit_amt' => 0,
                        'note' => 'Purchase payment: ' . $purchase->purchase_code,
                        'ref_purchasepayments_id' => $payment->id,
                        'supplier_id' => $purchase->supplier_id,
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    AcAccount::whereKey($payment->account_id)->decrement('balance', $amountPaid);
                }
            }

            $payableAmount = max(0, (float) $purchase->grand_total - $amountPaid);
            if ($payableAmount > 0) {
                AcTransaction::create([
                    'store_id' => $purchase->store_id,
                    'transaction_date' => $purchase->purchase_date,
                    'transaction_type' => 'PURCHASE PAYABLE',
                    'payment_code' => $purchase->purchase_code,
                    'credit_amt' => $payableAmount,
                    'note' => 'Supplier payable: ' . $purchase->purchase_code,
                    'supplier_id' => $purchase->supplier_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);
            }

            DB::commit();

            // Invalidate dashboard purchase caches
            Cache::forget('dashboard_month_purchases_s' . current_store_id());

            // Trigger SMS notification
            if ($purchase->supplier_id) {
                $this->smsTriggerService->trigger('PurchaseCreated', $purchase);
            }

            return response()->json(['success' => true, 'message' => 'Purchase saved successfully', 'redirect' => route('purchase.invoice', ['id' => $purchase->id], false)]);

        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            // Clean per-serial message (mirrors the count(serials)==qty rejection style).
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            // Defense-in-depth: concurrent submissions for the same item+serial from
            // two different entry points → translate to a clean per-serial message.
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            if ($msg) {
                \Log::warning('Purchase unique-constraint backstop triggered: ' . $msg);
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            \Log::error("Purchase Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save purchase: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Purchase Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save purchase: ' . $e->getMessage()], 500);
        }
    }

    public function quickStoreItem(Request $request)
    {
        // Quick-create writes a real db_items row, so it is gated on items_add
        // (not a purchase slug) — mirroring ItemController::store.
        if (auth()->check() && !auth()->user()->hasPermission('items_add')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to create items.'], 403);
        }

        // Validation mirrors the main Add Item Single flow (ItemController::store):
        // SKU/barcode uniqueness, and warehouse required whenever opening stock is added
        // (prevents an orphaned db_warehouseitems row with warehouse_id = NULL).
        $validator = \Validator::make($request->all(), [
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|exists:db_category,id',
            'brand_id' => 'nullable|exists:db_brands,id',
            'unit_id' => 'required|exists:db_units,id',
            'tax_id' => 'required|exists:db_tax,id',
            'tax_type' => 'required|in:Inclusive,Exclusive',
            'price' => 'nullable|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:100|unique:db_items,sku',
            'custom_barcode' => 'nullable|string|max:100|unique:db_items,custom_barcode',
            'barcode' => 'nullable|string|max:100|unique:db_items,custom_barcode',
            'alert_qty' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'is_serialized' => 'nullable',
            'opening_stock' => 'nullable|numeric|min:0',
            // A warehouse is required whenever opening stock is being added, regardless
            // of serialization — prevents an orphaned db_warehouseitems row.
            'warehouse_id' => [
                'nullable',
                'exists:db_warehouse,id',
                \Illuminate\Validation\Rule::requiredIf(function () use ($request) {
                    return (float) $request->input('opening_stock', 0) > 0;
                }),
            ],
            'serial_numbers' => 'nullable|array',
        ]);

        if ($validator->fails()) {
             return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            // Delegate to the shared Single-item creation service so this path produces
            // identical items to the main Add Item flow (same defaults, tax-inclusive
            // purchase_price, opening stock → db_warehouseitems, item code via service).
            // Source is tagged distinctly so a serial created here is traceable to the
            // Purchase screen's Quick-Add Item modal, not confused with Add Item.
            $item = app(ItemCreationService::class)
                ->createSingleItem($validated, $request, null, 'purchase_quick_add_item');

            DB::commit();

            $item->load('tax');
            return response()->json(['success' => true, 'item' => $item]);
        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            // Clean per-serial message instead of a raw 500.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            if ($msg) {
                \Log::warning('Quick-Add serial unique-constraint backstop triggered: ' . $msg);
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            \Log::error("Quick Add Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Quick Add Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_edit')) {
            abort(403, 'Unauthorized access to edit purchases.');
        }

        $purchase = DbPurchase::with(['items.item', 'supplier', 'warehouse'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);
        
        if (DbPurchaseReturn::where('purchase_id', $purchase->id)->exists()) {
            return redirect()->route('purchase.list')->with('error', 'Cannot edit this purchase because one or more purchase returns have already been recorded against it.');
        }

        // Manual Eager Load Serials due to Composite Key limitations
        $serials = \App\Models\DbItemSerial::where('purchase_id', $id)->get()->groupBy('item_id');
        foreach($purchase->items as $item) {
            $item->setRelation('serials', $serials->get($item->item_id, collect()));
        }

        $suppliers = DbSupplier::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $warehouses = DbWarehouse::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $accounts = AcAccount::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $categories = DbCategory::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $units = DbUnit::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $brands = DbBrand::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();

        return view('module.purchase.edit_purchase', compact(
            'purchase', 'suppliers', 'warehouses', 'taxes', 
            'paymentTypes', 'accounts', 'categories', 'units', 'brands'
        ));
    }

    public function update(UpdatePurchaseRequest $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_edit')) {
            abort(403, 'Unauthorized access to edit purchases.');
        }

        try {
            DB::beginTransaction();

            $purchase = DbPurchase::where('store_id', current_store_id())->findOrFail($id);

            if (DbPurchaseReturn::where('purchase_id', $purchase->id)->exists()) {
                throw new \InvalidArgumentException('Cannot edit this purchase because one or more purchase returns have already been recorded against it.');
            }
            
            // Capture each item's global stock BEFORE reversing, so the reversal-aware
            // weighted average in the re-add loop below can use the true pre-edit state.
            $stockBeforeReverse = [];
            foreach ($purchase->items as $item) {
                if (!isset($stockBeforeReverse[$item->item_id])) {
                    $stockBeforeReverse[$item->item_id] = (float) ($item->item->stock ?? 0);
                }
            }

            // Adjust stock and revert weighted average cost for existing items (reverse them)
            foreach ($purchase->items as $pItem) {
                $whItem = DbWarehouseItem::where('warehouse_id', $purchase->warehouse_id)
                    ->where('item_id', $pItem->item_id)
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->decrement('available_qty', $pItem->purchase_qty);
                }
                
                $dbItem = $pItem->item;
                if ($dbItem) {
                    $currentStock = (float) $dbItem->stock;
                    $oldPurchasedQty = (float) $pItem->purchase_qty;
                    $remainingStock = max(0, $currentStock - $oldPurchasedQty);

                    // Revert weighted-average cost to pre-purchase basis
                    if ($remainingStock > 0 && $currentStock > 0) {
                        $oldUnitCost = (float) ($pItem->unit_total_cost > 0 ? $pItem->unit_total_cost : $pItem->price_per_unit);
                        $prevTotalCost = ($currentStock * (float) $dbItem->purchase_price) - ($oldPurchasedQty * $oldUnitCost);
                        if ($prevTotalCost > 0) {
                            $dbItem->purchase_price = round($prevTotalCost / $remainingStock, 2);
                        }
                    }
                    $dbItem->stock = $remainingStock;
                    $dbItem->save();
                }
            }

            // Update Purchase Record
            // NOTE: the DB columns are discount_to_all_input / discount_to_all_type;
            // the request fields are discount_on_all / discount_type — map them here.
            $purchase->update([
                'warehouse_id' => $request->warehouse_id,
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'reference_no' => $request->reference_no,
                'note' => $request->note,
                'discount_to_all_input' => $request->discount_on_all ?? 0,
                'discount_to_all_type' => $request->discount_type,
                'other_charges_input' => $request->other_charges_input ?? 0,
                'other_charges_tax_id' => $request->other_charges_tax_id,
            ]);

            // Recalculate Totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;
            $tempTotal = 0; // Σ line total_cost (incl. tax) — the grand-total base, like store()

            // Remove old items and serials
            \App\Models\DbItemSerial::where('purchase_id', $purchase->id)->delete();
            $purchase->items()->delete();

            // Per-item serial-number uniqueness check BEFORE re-inserting. The old
            // purchase's own serial rows are already deleted above, so re-adding them
            // is not mistaken for a cross-entry-point duplicate; but any serial that
            // already exists for the SAME item from ANOTHER entry point (Add Item,
            // another purchase, adjustment, etc.) — or that is typed twice within this
            // submission — is rejected cleanly before the transaction commits.
            app(ItemSerialValidationService::class)
                ->validateSerialsForPurchaseLines($request->items);

            foreach ($request->items as $itemData) {
                $item = DbItem::findOrFail($itemData['item_id']);
                
                $price = (float)$itemData['purchase_price'];
                $qty = (float)$itemData['quantity'];
                
                // Item Tax — conditional Inclusive/Exclusive, mirroring store()
                $taxId = $itemData['tax_id'] ?? null;
                $tax = $taxId ? DbTax::find($taxId) : null;
                $taxPercent = $tax ? $tax->tax : 0;
                $taxType = $itemData['tax_type'] ?? ($item->tax_type ?? 'Exclusive');

                // Item Discount
                $discountInput = (float)($itemData['discount'] ?? 0);
                $discountType = $itemData['discount_type'] ?? 'Fixed';
                $discountAmount = ($discountType === 'Percentage') ? ($price * $discountInput / 100) * $qty : $discountInput * $qty;

                $lineSubtotalAfterDisc = ($price * $qty) - $discountAmount;
                if (strcasecmp($taxType, 'Inclusive') === 0) {
                    // Total already includes tax: Tax = afterDisc - (afterDisc / (1 + rate/100))
                    $taxAmount = $lineSubtotalAfterDisc - ($lineSubtotalAfterDisc / (1 + ($taxPercent / 100)));
                    $lineTotalWithTax = $lineSubtotalAfterDisc;
                } else {
                    // Exclusive: Tax = afterDisc * (rate/100)
                    $taxAmount = ($lineSubtotalAfterDisc * $taxPercent) / 100;
                    $lineTotalWithTax = $lineSubtotalAfterDisc + $taxAmount;
                }

                $unitTotalCost = ($qty > 0) ? ($lineTotalWithTax / $qty) : 0;
                $totalItemCost = $lineTotalWithTax;

                DbPurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'item_id' => $item->id,
                    'purchase_qty' => $qty,
                    'price_per_unit' => $price,
                    'tax_id' => $taxId,
                    'tax_amt' => round($taxAmount, 2),
                    'tax_type' => $taxType,
                    'discount_input' => $discountInput,
                    'discount_type' => $discountType,
                    'discount_amt' => $discountAmount,
                    'unit_total_cost' => round($unitTotalCost, 2),
                    'total_cost' => round($totalItemCost, 2),
                    'status' => 1,
                    'store_id' => current_store_id()
                ]);

                // Save Serials
                if (!empty($itemData['serials'])) {
                    foreach ($itemData['serials'] as $sn) {
                        if(!empty($sn)) {
                            \App\Models\DbItemSerial::create([
                                'store_id' => current_store_id(),
                                'purchase_id' => $purchase->id,
                                'item_id' => $item->id,
                                'serial_number' => $sn,
                                'warehouse_id' => $request->warehouse_id,
                                'status' => 0, // Available
                                'source' => 'purchase_edit',
                                'created_by' => auth()->id()
                            ]);
                        }
                    }
                }

                // Update Stock
                $whItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_id)
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => current_store_id(),
                        'warehouse_id' => $request->warehouse_id,
                        'item_id' => $item->id,
                        'available_qty' => $qty,
                    ]);
                }
                // Effective old stock is the item's on-hand stock after reversal (before this re-add),
                // matching store() where effective old stock is the pre-increment stock.
                $effectiveOldStock = (float) $item->stock;
                $item->increment('stock', $qty);
                $this->applyPurchaseCostToItem($item, $unitTotalCost, $effectiveOldStock, $qty);

                $subtotal += ($price * $qty);
                $totalTax += $taxAmount;
                $totalDiscount += $discountAmount;
                $tempTotal += $totalItemCost;
            }

            // Global (across-all-items) discount — mirror store() exactly.
            $globalDiscountInput = (float) ($request->discount_on_all ?? 0);
            $globalDiscountType = $request->discount_type;
            if ($globalDiscountType === 'Percentage') {
                $globalDiscount = ($subtotal * $globalDiscountInput) / 100;
            } else {
                $globalDiscount = $globalDiscountInput;
            }

            // Other-charges tax (always treated as Exclusive, matching store()).
            $otherChargesInput = (float) ($request->other_charges_input ?? 0);
            $otherTaxAmt = 0;
            if ($request->other_charges_tax_id) {
                $ocTax = DbTax::find($request->other_charges_tax_id);
                if ($ocTax) {
                    $otherTaxAmt = ($otherChargesInput * $ocTax->tax) / 100;
                }
            }

            // Final Grand Total — mirror store() (Σ line total_cost + otherCharges +
            // otherTax - globalDiscount + roundOff), clamped at 0. NOTE: we use $tempTotal
            // (line totals, which already include tax for Exclusive lines and are the
            // after-discount amount for Inclusive lines) — NOT subtotal + totalTax, which
            // would double-count Inclusive tax.
            $grandTotal = $tempTotal + $otherChargesInput + $otherTaxAmt - $globalDiscount + (float)($request->round_off ?? 0);
            if ($grandTotal < 0) {
                $grandTotal = 0;
            }

            // Payment Logic
            $newPayment = (float)($request->amount_paid ?? 0);
            if ($newPayment > 0) {
                if (empty($request->account_id)) {
                    throw new \InvalidArgumentException('Please select a Bank / Cash Account when entering a paid amount.');
                }

                $payment = DbPurchasePayment::create([
                    'store_id' => $purchase->store_id ?? current_store_id(),
                    'purchase_id' => $purchase->id,
                    'payment_date' => $purchase->purchase_date,
                    'payment_type' => $request->payment_type ?? 'Cash',
                    'payment' => $newPayment,
                    'created_by' => Auth::id(),
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                    'system_ip' => $request->ip(),
                    'system_name' => gethostname(),
                    'status' => 1,
                    'account_id' => $request->account_id,
                    'supplier_id' => $purchase->supplier_id,
                ]);

                AcTransaction::create([
                    'store_id' => $purchase->store_id ?? current_store_id(),
                    'transaction_date' => $payment->payment_date,
                    'transaction_type' => 'PURCHASE PAYMENT',
                    'payment_code' => $purchase->purchase_code,
                    'debit_account_id' => $payment->account_id,
                    'credit_account_id' => null,
                    'debit_amt' => $newPayment,
                    'credit_amt' => 0,
                    'note' => 'Purchase payment: ' . $purchase->purchase_code,
                    'ref_purchasepayments_id' => $payment->id,
                    'supplier_id' => $purchase->supplier_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);

                AcAccount::whereKey($payment->account_id)->decrement('balance', $newPayment);
            }

            $totalPaid = (float)$purchase->paid_amount + $newPayment;
            
            $paymentStatus = 'Unpaid';
            if ($totalPaid >= $grandTotal) {
                $paymentStatus = 'Paid';
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'Partial';
            }

            // Sync PURCHASE PAYABLE idempotently:
            AcTransaction::where('payment_code', $purchase->purchase_code)
                ->where('transaction_type', 'PURCHASE PAYABLE')
                ->delete();

            $newPayable = max(0, (float) $grandTotal - $totalPaid);
            if ($newPayable > 0) {
                AcTransaction::create([
                    'store_id' => $purchase->store_id ?? current_store_id(),
                    'transaction_date' => $purchase->purchase_date,
                    'transaction_type' => 'PURCHASE PAYABLE',
                    'payment_code' => $purchase->purchase_code,
                    'credit_amt' => $newPayable,
                    'note' => 'Supplier payable: ' . $purchase->purchase_code,
                    'supplier_id' => $purchase->supplier_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'tot_discount_to_all_amt' => $globalDiscount,
                'other_charges_input' => $request->other_charges_input ?? 0,
                'other_charges_amt' => $otherTaxAmt,
                'round_off' => $request->round_off ?? 0,
                'grand_total' => $grandTotal,
                'paid_amount' => $totalPaid,
                'payment_status' => $paymentStatus,
            ]);

            DB::commit();

            // Invalidate dashboard purchase caches
            Cache::forget('dashboard_month_purchases_s' . current_store_id());
            return response()->json([
                'success' => true,
                'message' => 'Purchase updated successfully',
                'redirect' => route('purchase.invoice', $purchase->id)
            ]);

        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            // Clean per-serial message instead of a raw 500.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            if ($msg) {
                \Log::warning('Purchase edit unique-constraint backstop triggered: ' . $msg);
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply the weighted-average purchase cost to an item and recompute sales_price.
     *
     * Shared by store() and update() so the two paths can never drift.
     *
     * - purchase_price is TAX-INCLUSIVE (matching Add Item), so $unitTotalCost must be
     *   the line's tax-inclusive landed unit cost (lineTotalWithTax / qty).
     * - $effectiveOldStock is the item's on-hand quantity BEFORE this purchase's qty is
     *   added. In store() that is the pre-increment global stock; in update() it is
     *   stockBeforeReverse + oldPurchasedQty (i.e. the stock state entering the edit).
     * - If $effectiveOldStock <= 0 the new cost is simply $unitTotalCost (first stock).
     * - sales_price is recomputed from the item's EXISTING profit_margin so the merchant's
     *   intended margin is preserved as cost fluctuates. If margin is 0/null, sales_price
     *   is left unchanged (a log note is written). 'price' (pre-tax base) is never touched.
     */
    private function applyPurchaseCostToItem(DbItem $item, float $unitTotalCost, float $effectiveOldStock, float $qty): void
    {
        if ($unitTotalCost <= 0) {
            return;
        }

        $oldAvgCost = (float) $item->purchase_price;

        if ($effectiveOldStock > 0) {
            $newAvgCost = ($oldAvgCost * $effectiveOldStock + $unitTotalCost * $qty) / ($effectiveOldStock + $qty);
        } else {
            $newAvgCost = $unitTotalCost; // First stock in → latest cost
        }

        $itemUpdate = ['purchase_price' => round($newAvgCost, 2)];
        // NOTE: 'price' (pre-tax base) is intentionally NOT touched here.

        $profitMargin = (float) $item->profit_margin;
        if ($profitMargin > 0 && (float) $item->sales_price > 0) {
            $itemUpdate['sales_price'] = round($newAvgCost * (1 + $profitMargin / 100), 2);
        } elseif ($profitMargin <= 0) {
            \Log::info("Purchase cost updated: item {$item->id} has no profit_margin set; sales_price left unchanged.");
        }

        $item->update($itemUpdate);
    }

    public function returnList(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_return_view')) {
            abort(403, 'Unauthorized access to purchase returns.');
        }

        $query = DbPurchaseReturn::with(['purchase', 'supplier', 'warehouse'])
            ->where('store_id', current_store_id())
            ->latest();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('return_code', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('purchase', function($pq) use ($search) {
                      $pq->where('purchase_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('supplier', function($sq) use ($search) {
                      $sq->where('supplier_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('return_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('return_date', '<=', $request->to_date);
        }

        $stats = [
            'total_invoices' => $query->count(),
            'total_amount' => (float)$query->sum('grand_total'),
            'total_paid' => (float)$query->sum('paid_amount'),
        ];
        $stats['total_due'] = max(0, $stats['total_amount'] - $stats['total_paid']);

        $perPage = (int) $request->input('per_page', 10);
        $returns = $query->paginate($perPage)->withQueryString();
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();

        return view('module.purchase.purchase_returns_list', compact('returns', 'warehouses', 'stats'));
    }

    public function createReturn($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_return_add')) {
            abort(403, 'Unauthorized access to create purchase returns.');
        }

        $purchase = DbPurchase::with(['items.item', 'supplier', 'warehouse'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);

        $returnedQuantities = DbPurchaseItemReturn::where('purchase_id', $purchase->id)
            ->select('item_id', DB::raw('SUM(return_qty) as returned_qty'))
            ->groupBy('item_id')
            ->pluck('returned_qty', 'item_id');

        $taxes = DbTax::where('status', 1)->get();
        return view('module.purchase.create_purchase_return', compact('purchase', 'taxes', 'returnedQuantities'));
    }

    public function storeReturn(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_return_add')) {
            abort(403, 'Unauthorized access to create purchase returns.');
        }

        try {
            $request->validate([
                'purchase_id' => 'required|integer|exists:db_purchase,id',
                'return_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|integer',
                'items.*.return_qty' => 'required|numeric|min:0',
            ]);
            DB::beginTransaction();

            $purchase = DbPurchase::with('items')->lockForUpdate()->findOrFail($request->purchase_id);
            $purchaseItems = $purchase->items->keyBy('item_id');
            $returnedQuantities = DbPurchaseItemReturn::where('purchase_id', $purchase->id)
                ->select('item_id', DB::raw('SUM(return_qty) as returned_qty'))
                ->groupBy('item_id')
                ->pluck('returned_qty', 'item_id');

            foreach ($request->items as $itemData) {
                if ((float) ($itemData['return_qty'] ?? 0) <= 0) {
                    continue;
                }
                $purchaseItem = $purchaseItems->get($itemData['item_id']);
                $availableQty = (float) ($purchaseItem?->purchase_qty ?? 0) - (float) ($returnedQuantities[$itemData['item_id']] ?? 0);
                if (!$purchaseItem || (float) $itemData['return_qty'] > $availableQty) {
                    throw new \InvalidArgumentException('Return quantity exceeds the remaining purchased quantity.');
                }
            }
            
            $returnCode = \App\Services\CodeGeneratorService::generate('purchase_return');

            $purchaseReturn = DbPurchaseReturn::create([
                'purchase_id' => $purchase->id,
                'return_code' => $returnCode,
                'return_date' => $request->return_date,
                'return_status' => 'Returned',
                'reference_no' => $request->reference_no,
                'supplier_id' => $purchase->supplier_id,
                'warehouse_id' => $purchase->warehouse_id,
                'subtotal' => 0,
                'grand_total' => 0,
                'created_by' => Auth::id(),
                'store_id' => current_store_id()
            ]);

            $subtotal = 0;
            foreach ($request->items as $itemData) {
                if ($itemData['return_qty'] > 0) {
                    $item = DbItem::findOrFail($itemData['item_id']);
                    $purchaseItem = $purchaseItems->get($item->id);
                    $unitPrice = (float) $purchaseItem->price_per_unit;
                    
                    DbPurchaseItemReturn::create([
                        'return_id' => $purchaseReturn->id,
                        'purchase_id' => $purchase->id,
                        'item_id' => $item->id,
                        'return_qty' => $itemData['return_qty'],
                        'price_per_unit' => $unitPrice,
                        'tax_id' => $purchaseItem->tax_id,
                        'unit_total_cost' => $unitPrice,
                        'total_cost' => $unitPrice * $itemData['return_qty'],
                        'status' => 1,
                        'store_id' => current_store_id()
                    ]);

                    $whItem = DbWarehouseItem::where('warehouse_id', $purchase->warehouse_id)
                        ->where('item_id', $item->id)
                        ->first();
                    if ($whItem) {
                        $whItem->decrement('available_qty', $itemData['return_qty']);
                    }
                    $item->decrement('stock', $itemData['return_qty']);

                    // B3: If serialized, mark returned serials as status = 2 (Returned to Supplier)
                    if ($item->is_serialized == 1) {
                        $serialsToReturn = \App\Models\DbItemSerial::where('purchase_id', $purchase->id)
                            ->where('item_id', $item->id)
                            ->where('status', 0)
                            ->take((int) $itemData['return_qty'])
                            ->get();
                        foreach ($serialsToReturn as $s) {
                            $s->update(['status' => 2]);
                        }
                    }

                    $subtotal += ($unitPrice * $itemData['return_qty']);
                }
            }

            $purchaseReturn->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
            ]);

            $originalPayment = DbPurchasePayment::where('purchase_id', $purchase->id)
                ->where('status', 1)
                ->latest('id')
                ->first();
            $refundAmount = min($subtotal, (float) ($purchase->paid_amount ?? 0));
            $paymentReturn = DbPurchasePaymentReturn::create([
                'purchase_id' => $purchase->id,
                'return_id' => $purchaseReturn->id,
                'payment_date' => $purchaseReturn->return_date,
                'payment_type' => $originalPayment?->payment_type ?: ($refundAmount > 0 ? 'Cash' : 'Credit'),
                'payment' => $refundAmount,
                'created_by' => Auth::id(),
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'system_ip' => $request->ip(),
                'system_name' => gethostname(),
                'status' => 1,
                'account_id' => $originalPayment?->account_id,
                'supplier_id' => $purchase->supplier_id,
                'store_id' => current_store_id(),
            ]);

            // B2: Decrement parent purchase paid_amount by refund amount and sync ledger
            if ($refundAmount > 0) {
                $purchase->decrement('paid_amount', $refundAmount);
                $purchase->refresh();
                $purchase->payment_status = $purchase->paid_amount >= $purchase->grand_total ? 'Paid' : ($purchase->paid_amount > 0 ? 'Partial' : 'Unpaid');
                $purchase->save();

                if ($paymentReturn->account_id) {
                    AcTransaction::create([
                        'store_id' => current_store_id(),
                        'transaction_date' => $purchaseReturn->return_date,
                        'transaction_type' => 'PURCHASE RETURN REFUND',
                        'payment_code' => $purchaseReturn->return_code,
                        'credit_account_id' => $paymentReturn->account_id,
                        'debit_account_id' => null,
                        'debit_amt' => 0,
                        'credit_amt' => $refundAmount,
                        'note' => 'Purchase return refund: ' . $purchaseReturn->return_code,
                        'ref_purchasepaymentsreturn_id' => $paymentReturn->id,
                        'supplier_id' => $purchase->supplier_id,
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    AcAccount::whereKey($paymentReturn->account_id)->increment('balance', $refundAmount);
                }
            }

            $payableReduction = max(0, $subtotal - $refundAmount);
            if ($payableReduction > 0) {
                AcTransaction::create([
                    'store_id' => current_store_id(),
                    'transaction_date' => $purchaseReturn->return_date,
                    'transaction_type' => 'PURCHASE RETURN PAYABLE',
                    'payment_code' => $purchaseReturn->return_code,
                    'debit_amt' => $payableReduction,
                    'note' => 'Supplier payable reduction: ' . $purchaseReturn->return_code,
                    'supplier_id' => $purchase->supplier_id,
                    'ref_purchasepaymentsreturn_id' => $paymentReturn->id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Return processed successfully',
                'redirect' => route('purchase.returns')
            ]);

        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function invoice($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_view')) {
            abort(403, 'Unauthorized access to purchase invoices.');
        }

        $purchase = DbPurchase::with(['supplier.country', 'supplier.state', 'warehouse', 'items.item.tax', 'items.item.unit', 'items.serials'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);
            
        return view('module.purchase.purchase_invoice', compact('purchase'));
    }

    public function barcode($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_view')) {
            abort(403, 'Unauthorized access to purchase barcodes.');
        }

        $purchase = DbPurchase::with(['items.item', 'items.serials', 'supplier'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);
        $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
        return view('module.purchase.barcode', compact('purchase', 'generator'));
    }

    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_delete')) {
            abort(403, 'Unauthorized access to delete purchases.');
        }

        try {
            DB::beginTransaction();

            $purchase = DbPurchase::with(['items.item', 'payments'])
                ->where('store_id', current_store_id())
                ->lockForUpdate()
                ->findOrFail($id);

            // C1.a: Block if any purchase return exists
            if (DbPurchaseReturn::where('purchase_id', $purchase->id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete purchase because it has linked purchase returns. Void or delete all returns first.'
                ], 422);
            }

            // C1.e: Block if any serial number created by this purchase has been sold
            $soldSerials = DbItemSerial::where('purchase_id', $purchase->id)
                ->where(function ($q) {
                    $q->where('status', 1)->orWhereNotNull('sale_id');
                })
                ->count();
            if ($soldSerials > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete purchase because {$soldSerials} serialized item(s) from this purchase have already been sold."
                ], 422);
            }

            // C1.b & C1.c: Reverse inventory stock and weighted-average cost
            foreach ($purchase->items as $pItem) {
                $whItem = DbWarehouseItem::where('warehouse_id', $purchase->warehouse_id)
                    ->where('item_id', $pItem->item_id)
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->decrement('available_qty', $pItem->purchase_qty);
                }

                $dbItem = $pItem->item;
                if ($dbItem) {
                    $currentStock = (float) $dbItem->stock;
                    $purchasedQty = (float) $pItem->purchase_qty;
                    $remainingStock = max(0, $currentStock - $purchasedQty);

                    // Reverse weighted-average cost basis
                    if ($remainingStock > 0 && $currentStock > 0) {
                        $lineCost = (float) ($pItem->unit_total_cost > 0 ? $pItem->unit_total_cost : $pItem->price_per_unit);
                        $prevCostTotal = ($currentStock * (float) $dbItem->purchase_price) - ($purchasedQty * $lineCost);
                        if ($prevCostTotal > 0) {
                            $dbItem->purchase_price = round($prevCostTotal / $remainingStock, 2);
                            if ((float) $dbItem->profit_margin > 0) {
                                $dbItem->sales_price = round($dbItem->purchase_price * (1 + (float) $dbItem->profit_margin / 100), 2);
                            }
                        }
                    }
                    $dbItem->stock = $remainingStock;
                    $dbItem->save();
                }
            }

            // C1.d: Reverse payments and ledger transactions
            foreach ($purchase->payments as $payment) {
                if ($payment->account_id && (float) $payment->payment > 0) {
                    AcAccount::whereKey($payment->account_id)->increment('balance', (float) $payment->payment);
                }
                AcTransaction::where('ref_purchasepayments_id', $payment->id)->delete();
                $payment->delete();
            }

            // Delete PURCHASE PAYABLE transactions
            AcTransaction::where('payment_code', $purchase->purchase_code)
                ->where('transaction_type', 'PURCHASE PAYABLE')
                ->delete();

            // Delete unsold serial numbers created by this purchase
            DbItemSerial::where('purchase_id', $purchase->id)->delete();

            // Delete purchase items and purchase
            $purchase->items()->delete();
            $purchase->delete();

            DB::commit();
            Cache::forget('dashboard_month_purchases_s' . current_store_id());

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchase and all associated stock/ledger entries reversed successfully.'
                ]);
            }

            return redirect()->route('purchase.list')->with('success', 'Purchase and all associated stock/ledger entries reversed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->route('purchase.list')->with('error', $e->getMessage());
        }
    }

    public function returnInvoice($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_return_view')) {
            abort(403, 'Unauthorized access to purchase return invoices.');
        }

        $return = DbPurchaseReturn::with(['purchase', 'items.item', 'supplier.country', 'supplier.state', 'warehouse'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);

        $refundPayment = DbPurchasePaymentReturn::where('return_id', $return->id)->first();

        return view('module.purchase.purchase_return_invoice', compact('return', 'refundPayment'));
    }

    public function destroyReturn($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_return_delete')) {
            abort(403, 'Unauthorized access to delete purchase returns.');
        }

        try {
            DB::beginTransaction();

            $return = DbPurchaseReturn::with(['items.item', 'purchase'])
                ->where('store_id', current_store_id())
                ->lockForUpdate()
                ->findOrFail($id);

            $purchase = $return->purchase;

            // 1. Restore inventory stock
            foreach ($return->items as $rItem) {
                $whItem = DbWarehouseItem::where('warehouse_id', $return->warehouse_id)
                    ->where('item_id', $rItem->item_id)
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->increment('available_qty', $rItem->return_qty);
                }

                $dbItem = $rItem->item;
                if ($dbItem) {
                    $dbItem->increment('stock', $rItem->return_qty);
                }

                // Restore serial numbers status from 2 back to 0 (Available)
                if ($dbItem?->is_serialized == 1) {
                    $serialsToRestore = DbItemSerial::where('purchase_id', $purchase->id)
                        ->where('item_id', $rItem->item_id)
                        ->where('status', 2)
                        ->take((int) $rItem->return_qty)
                        ->get();
                    foreach ($serialsToRestore as $s) {
                        $s->update(['status' => 0]);
                    }
                }
            }

            // 2. Reverse refund and ledger
            $paymentReturn = DbPurchasePaymentReturn::where('return_id', $return->id)->first();
            if ($paymentReturn) {
                $refundAmt = (float) $paymentReturn->payment;
                if ($refundAmt > 0) {
                    // Restore purchase paid_amount
                    if ($purchase) {
                        $purchase->increment('paid_amount', $refundAmt);
                        $purchase->refresh();
                        $purchase->payment_status = $purchase->paid_amount >= $purchase->grand_total ? 'Paid' : ($purchase->paid_amount > 0 ? 'Partial' : 'Unpaid');
                        $purchase->save();
                    }

                    // Decrement account balance and delete transaction
                    if ($paymentReturn->account_id) {
                        AcAccount::whereKey($paymentReturn->account_id)->decrement('balance', $refundAmt);
                    }
                    AcTransaction::where('ref_purchasepaymentsreturn_id', $paymentReturn->id)->delete();
                }
                $paymentReturn->delete();
            }

            // Delete PURCHASE RETURN PAYABLE transaction
            AcTransaction::where('payment_code', $return->return_code)
                ->where('transaction_type', 'PURCHASE RETURN PAYABLE')
                ->delete();

            // Delete return items and return record
            $return->items()->delete();
            $return->delete();

            DB::commit();

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchase return and associated stock/ledger entries reversed successfully.'
                ]);
            }

            return redirect()->route('purchase.returns')->with('success', 'Purchase return and associated stock/ledger entries reversed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->route('purchase.returns')->with('error', $e->getMessage());
        }
    }

    public function storePayment(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('purchase_payment_add')) {
            abort(403, 'Unauthorized access to purchase payments.');
        }

        $request->validate([
            'payment_date' => 'required|date',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|string',
            'account_id' => 'required|integer|exists:ac_accounts,id',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $purchase = DbPurchase::where('store_id', current_store_id())->lockForUpdate()->findOrFail($id);

            $remainingDue = max(0, (float) $purchase->grand_total - (float) $purchase->paid_amount);
            $payAmount = (float) $request->payment_amount;

            if ($payAmount > $remainingDue) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Payment amount cannot exceed the remaining due of ' . number_format($remainingDue, 2)
                ], 422);
            }

            $payment = DbPurchasePayment::create([
                'store_id' => current_store_id(),
                'purchase_id' => $purchase->id,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'payment' => $payAmount,
                'payment_note' => $request->note,
                'created_by' => Auth::id(),
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'system_ip' => $request->ip(),
                'system_name' => gethostname(),
                'status' => 1,
                'account_id' => $request->account_id,
                'supplier_id' => $purchase->supplier_id,
            ]);

            AcTransaction::create([
                'store_id' => current_store_id(),
                'transaction_date' => $payment->payment_date,
                'transaction_type' => 'PURCHASE PAYMENT',
                'payment_code' => $purchase->purchase_code,
                'debit_account_id' => $payment->account_id,
                'credit_account_id' => null,
                'debit_amt' => $payAmount,
                'credit_amt' => 0,
                'note' => 'Purchase payment: ' . $purchase->purchase_code . ($request->note ? ' - ' . $request->note : ''),
                'ref_purchasepayments_id' => $payment->id,
                'supplier_id' => $purchase->supplier_id,
                'created_by' => Auth::id() ?? 1,
                'created_date' => date('Y-m-d'),
            ]);

            AcAccount::whereKey($payment->account_id)->decrement('balance', $payAmount);

            $purchase->increment('paid_amount', $payAmount);
            $purchase->refresh();
            $purchase->payment_status = $purchase->paid_amount >= $purchase->grand_total ? 'Paid' : ($purchase->paid_amount > 0 ? 'Partial' : 'Unpaid');
            $purchase->save();

            // Sync remaining PURCHASE PAYABLE
            AcTransaction::where('payment_code', $purchase->purchase_code)
                ->where('transaction_type', 'PURCHASE PAYABLE')
                ->delete();

            $newPayable = max(0, (float) $purchase->grand_total - (float) $purchase->paid_amount);
            if ($newPayable > 0) {
                AcTransaction::create([
                    'store_id' => current_store_id(),
                    'transaction_date' => $purchase->purchase_date,
                    'transaction_type' => 'PURCHASE PAYABLE',
                    'payment_code' => $purchase->purchase_code,
                    'credit_amt' => $newPayable,
                    'note' => 'Supplier payable: ' . $purchase->purchase_code,
                    'supplier_id' => $purchase->supplier_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'redirect' => route('purchase.list')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
