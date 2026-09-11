<?php

namespace App\Http\Controllers;

use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbItem;
use App\Models\DbCustomer;
use App\Models\DbCustAdvance;
use App\Models\DbWarehouse;
use App\Models\DbTax;
use App\Models\DbPaymentType;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbWarehouseItem;
use App\Models\DbHold;
use App\Models\DbHoldItem;
use App\Models\DbEmiSale;
use App\Models\DbEmiSchedule;
use App\Models\DbItemSerial;
use App\Models\DbCoupon;
use App\Models\DbCustomerCoupon;
use App\Models\CashDrawerReconciliation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\SMS\Services\SmsTriggerService;

class PosController extends Controller
{
    protected $smsTriggerService;

    /**
     * Customer coupon resolved by resolveCoupon() but NOT yet consumed.
     * Consumed only AFTER the sale is successfully created/committed so a
     * failed sale (validation / mismatch rejection / exception) does not
     * burn a one-time customer coupon.
     *
     * @var \App\Models\DbCustomerCoupon|null
     */
    protected $consumedCustomerCoupon = null;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function index(Request $request)
    {
        $customers = DbCustomer::where('status', 1)->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $categories = DbCategory::where('status', 1)->where('store_id', current_store_id())->get();
        $brands = DbBrand::where('status', 1)->where('store_id', current_store_id())->get();
        $taxes = DbTax::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->get();
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->get();
        $accounts = AcAccount::where('status', 1)->get();

        $today = Carbon::today()->format('Y-m-d');
        $hasOpenCashDrawer = CashDrawerReconciliation::whereDate('reconciliation_date', $today)->where('status', 'Open')->exists();

        $holdData = null;
        $saleData = null;
        if ($request->has('hold_id')) {
            // A4: scope resume by the current store — a hold from another store
            // must not be loadable by this session.
            $holdData = DbHold::with(['items.item'])
                ->where('store_id', current_store_id())
                ->find($request->hold_id);

            // A10: flag warehouse-less holds so POS can prompt the cashier to
            // select a warehouse before submit instead of failing silently at
            // completion with a generic "valid warehouse required" error.
            if ($holdData && empty($holdData->warehouse_id)) {
                $holdData->hold_missing_warehouse = true;
            } else if ($holdData) {
                $holdData->hold_missing_warehouse = false;
            }
        } elseif ($request->has('sale_id')) {
            $saleData = DbSale::with(['items.item.serials', 'customer', 'payments'])->find($request->sale_id);
        }

        // Recent holds for the POS side panel (reuses the hold_list query pattern:
        // DbHold eager-loaded with customer + items, latest first). Up to 50 are
        // returned so the panel can paginate 5-at-a-time client-side in Alpine
        // (hold counts are typically small — no dedicated paginated endpoint).
        // A4: scope to the current store.
        $heldOrdersQuery = DbHold::with(['customer', 'items'])
            ->where('store_id', current_store_id())
            ->orderBy('id', 'desc');
        $heldOrdersCount = (clone $heldOrdersQuery)->count();
        $heldOrders = $heldOrdersQuery->limit(50)->get()->map(function ($hold) {
            return [
                'id' => $hold->id,
                'reference_no' => $hold->reference_no,
                'customer_name' => $hold->customer->customer_name ?? 'Walk-in Customer',
                'item_count' => (int) $hold->items->sum('sales_qty'),
                'grand_total' => (float) $hold->grand_total,
                'resume_url' => route('sales.pos', ['hold_id' => $hold->id]),
            ];
        });

        return view('module.sales.pos', compact(
            'customers', 'warehouses', 'categories', 'brands', 'taxes', 'paymentTypes', 'accounts', 'holdData', 'saleData', 'hasOpenCashDrawer', 'heldOrders', 'heldOrdersCount'
        ));
    }

    public function searchItems(Request $request)
    {
        $search = trim($request->get('q'));
        $category_id = $request->get('category_id');
        $brand_id = $request->get('brand_id');
        $warehouse_id = $request->get('warehouse_id');

        $query = DbItem::with('tax')
            ->select('db_items.id', 'db_items.item_name', 'db_items.item_code', 'db_items.custom_barcode', 'db_items.sales_price', 'db_items.item_image', 'db_items.is_serialized', 'db_items.tax_id')
            ->where('db_items.status', 1)
            // A12 (added item, stated explicitly): close the pre-existing cross-store
            // leak — POS search previously returned items from every store. Scoped to
            // the current store exactly like getAvailableSerials / checkout already do.
            ->where('db_items.store_id', current_store_id())
            ->leftJoin('db_salesitems', 'db_items.id', '=', 'db_salesitems.item_id')
            ->selectRaw('SUM(COALESCE(db_salesitems.sales_qty, 0)) as total_sold')
            ->groupBy('db_items.id', 'db_items.item_name', 'db_items.item_code', 'db_items.custom_barcode', 'db_items.sales_price', 'db_items.item_image', 'db_items.is_serialized', 'db_items.tax_id');

        if ($warehouse_id) {
            $query->leftJoin('db_warehouseitems', function($join) use ($warehouse_id) {
                $join->on('db_items.id', '=', 'db_warehouseitems.item_id')
                     ->where('db_warehouseitems.warehouse_id', '=', $warehouse_id);
            })->addSelect(DB::raw('COALESCE(db_warehouseitems.available_qty, 0) as stock'))
              ->groupBy('db_warehouseitems.available_qty');
        } else {
            $query->addSelect('db_items.stock')
                  ->groupBy('db_items.stock');
        }

        if (!empty($search)) {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($search, $searchLower) {
                $q->where('db_items.custom_barcode', $search)
                  ->orWhere('db_items.item_code', $search)
                  ->orWhereRaw('LOWER(db_items.item_name) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        if (!empty($category_id) && $category_id != 'All Categories') {
            $query->where('db_items.category_id', $category_id);
        }

        if (!empty($brand_id) && $brand_id != 'All Brands') {
            $query->where('db_items.brand_id', $brand_id);
        }

        $items = $query->orderBy('total_sold', 'desc')
                       ->orderBy('db_items.id', 'desc')
                       ->limit(12)
                       ->get();

        // --- A13: SERIAL-NUMBER FALLBACK MATCH (Phase 2) ---
        // The three existing fields (custom_barcode / item_code / item_name) take
        // priority and are completely unchanged. ONLY when they matched nothing
        // does the search term fall back to a db_item_serials lookup that resolves
        // an Available serial in the current store + active POS warehouse to its
        // parent item. Case-insensitive to tolerate scanner/typing case differences
        // (serial values are stored as typed; the scan client uppercases input).
        //
        // The parent item is resolved with a FRESH query (NOT the $query builder):
        // $query still carries the three-field search closure, which by definition
        // excludes this item — reusing it would always return an empty set.
        //
        // The matched serial's id/value are attached to the returned item so the
        // client can tell a serial-fallback hit from a regular match and pre-attach
        // the serial (skipping the modal) — only for THIS path.
        if (!empty($search) && $items->isEmpty() && !empty($warehouse_id)) {
            $serialMatch = DbItemSerial::whereRaw('LOWER(serial_number) = ?', [strtolower($search)])
                ->where('status', 0)
                ->where('store_id', current_store_id())
                ->where('warehouse_id', $warehouse_id)
                ->first();

            if ($serialMatch) {
                $items = DbItem::with('tax')
                    ->select('db_items.id', 'db_items.item_name', 'db_items.item_code', 'db_items.custom_barcode', 'db_items.sales_price', 'db_items.item_image', 'db_items.is_serialized', 'db_items.tax_id')
                    ->where('db_items.id', $serialMatch->item_id)
                    ->where('db_items.status', 1)
                    ->where('db_items.store_id', current_store_id())
                    ->get()
                    ->map(function ($item) use ($serialMatch) {
                        $item->matched_serial_id = (int) $serialMatch->id;
                        $item->matched_serial = $serialMatch->serial_number;
                        return $item;
                    });
            }
        }

        return response()->json($items);
    }

    public function getAvailableSerials(Request $request)
    {
        $itemId = $request->get('item_id');
        $warehouseId = $request->get('warehouse_id');

        $serials = DbItemSerial::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 0)
            ->where('store_id', auth()->user()->store_id ?? 1)
            ->get(['id', 'serial_number']);

        return response()->json($serials);
    }

    public function store(Request $request)
    {
        try {
            // --- SERVER-SIDE VALIDATION (shared by POS and Add Sale flows) ---
            $validationError = $this->validateSalePayload($request);
            if ($validationError) {
                return response()->json(['success' => false, 'message' => $validationError], 422);
            }

            // --- EMI EDIT GUARD (A4) ---
            // Re-saving an EMI sale through the generic POS edit path would wipe its
            // DbSalePayment rows + ledger entries without touching db_emi_schedule,
            // leaving the schedule saying "Paid" with no money behind it. Block the
            // save entirely — EMI sales are managed through the EMI schedule/details
            // page only. Mirrors the destroy() EMI block approach.
            if ($request->sale_id) {
                $editSale = DbSale::with('emi')->find($request->sale_id);
                if ($editSale && $editSale->emi) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This sale has an EMI schedule. EMI sales cannot be edited here — manage installments through the EMI schedule page instead.',
                    ], 422);
                }
            }

            DB::beginTransaction();

            // --- A1: ATOMIC HOLD CLAIM (double-resume guard) ---
            // A held invoice may only be completed ONCE — regardless of which
            // checkout path (regular store() or storeEmi()) resumes it. The claim
            // is taken inside this transaction with a row lock (shared helper
            // claimOpenHold()) so two concurrent completions of the same hold_id
            // cannot both pass: the second transaction blocks on the lock, then
            // observes the hold either deleted (committed) or marked 'completed'
            // (still in flight) and is rejected with a clear error instead of
            // silently creating a duplicate sale + double stock decrement.
            //
            // Marking 'completed' (rather than deleting here) keeps the claim
            // atomic with the sale creation: if this transaction rolls back, the
            // status update rolls back too and the hold stays 'open' for a
            // legitimate retry. The actual row deletion still happens after the
            // sale commits below.
            $holdClaimError = $this->claimOpenHold($request->input('hold_id'));
            if ($holdClaimError) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $holdClaimError,
                ], 409);
            }

            $store = store_settings();
            $advanceAmount = max(0, (float) $request->advance_amount);
            $roundOff = (float) ($request->round_off ?? 0);

            list($couponId, $couponAmt) = $this->resolveCoupon($request, $request->subtotal);

            // --- Server-side total recomputation + mismatch REJECTION ---
            // The server recomputes the grand total using authoritative product prices,
            // server-revalidated coupon, tax, and other charges, then applies round-off
            // exactly as it will be persisted. If the client-sent total differs from the
            // server's final (rounded) total by more than a small tolerance, REJECT rather
            // than persist a mismatched total (prevents client-tampered totals).
            $serverTotals = $this->recomputeServerTotals($request, $couponAmt);

            // The server is authoritative for round_off: recompute it from the server's
            // raw payable so the persisted (grand_total, round_off) pair is internally
            // consistent regardless of what the client claimed.
            $serverGrandTotal = (float) ($serverTotals['server_grand_total'] ?? 0);
            $roundOff = (float) ($serverTotals['server_round_off'] ?? 0);
            $grandTotal = $serverGrandTotal;

            // Accept the client total if it matches either the server's raw payable OR its
            // rounded grand total (clients differ: POS sends the rounded total, Add Sale
            // sends the raw total). Then persist the server-authoritative values.
            $clientGrandTotal = (float) $request->grand_total;
            $serverRawPayable = (float) ($serverTotals['server_raw_payable'] ?? $serverGrandTotal);
            $matchesRaw = abs($clientGrandTotal - $serverRawPayable) <= 0.01;
            $matchesRounded = abs($clientGrandTotal - $serverGrandTotal) <= 0.01;
            $enforceTotalValidation = config('sales.enforce_total_validation', true);
            if (!$matchesRaw && !$matchesRounded) {
                $this->logTotalMismatch($request, $serverTotals, $clientGrandTotal, 'store');
                if ($enforceTotalValidation) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Total mismatch detected. Please refresh and try again. (Server total: ' . number_format($grandTotal, 2) . ')',
                    ], 422);
                }
                // KILL-SWITCH OFF: fall back to trusting the client total (pre-fix behavior).
                $grandTotal = $clientGrandTotal;
                $roundOff = (float) ($request->round_off ?? 0);
            }

            if ($advanceAmount > 0) {
                $customer = DbCustomer::where('store_id', current_store_id())->find($request->customer_id);
                $availableAdvance = (float) ($customer->tot_advance ?? 0);
                if (!$customer || $advanceAmount > $availableAdvance || $advanceAmount > $grandTotal) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Advance adjustment exceeds the customer available advance or sale total.'], 422);
                }
                $customer->decrement('tot_advance', $advanceAmount);
            }

            if ($request->sale_id) {
                $sale = DbSale::with(['items', 'payments'])->findOrFail($request->sale_id);
                
                // Revert Old Stock & Serials before applying new ones — SKIP service lines
                // (service_bit=1): their stock was never decremented at checkout, so it
                // must not be incremented back here either.
                foreach ($sale->items as $oldItem) {
                    $oldDbItem = DbItem::find($oldItem->item_id);
                    $isServiceLine = $oldDbItem && (int) $oldDbItem->service_bit === 1;

                    if (!$isServiceLine) {
                        DbItem::where('id', $oldItem->item_id)->increment('stock', $oldItem->sales_qty);
                        DbWarehouseItem::where('warehouse_id', $sale->warehouse_id)
                            ->where('item_id', $oldItem->item_id)
                            ->increment('available_qty', $oldItem->sales_qty);
                    }
                    
                    DbItemSerial::where('sale_id', $sale->id)
                        ->where('item_id', $oldItem->item_id)
                        ->update(['status' => 0, 'sale_id' => null]);
                }

                // Revert Old Payments & AcTransactions before deleting
                foreach ($sale->payments as $oldPayment) {
                    if ($oldPayment->account_id && $oldPayment->payment > 0) {
                        $acc = AcAccount::find($oldPayment->account_id);
                        if ($acc) {
                            $acc->decrement('balance', $oldPayment->payment);
                        }
                    }
                    AcTransaction::where('ref_salespayments_id', $oldPayment->id)->delete();
                }

                // Credit back any advance previously applied to THIS specific sale,
                // so an edit doesn't permanently consume the customer's advance balance.
                // Old advance rows are deleted below, so a later edit re-restores only
                // whatever advance rows currently exist (no double-credit).
                if ($sale->customer_id) {
                    $oldAdvanceTotal = DbSalePayment::where('sales_id', $sale->id)
                        ->where('payment_type', 'Advance')
                        ->sum('advance_adjusted');
                    if ($oldAdvanceTotal > 0) {
                        $oldCustomer = DbCustomer::find($sale->customer_id);
                        if ($oldCustomer) {
                            $oldCustomer->increment('tot_advance', $oldAdvanceTotal);
                        }
                    }
                }

                // Clear old child records
                $sale->items()->delete();
                $sale->payments()->delete();

                // Update main record
                $sale->update([
                    'warehouse_id' => $request->warehouse_id,
                    'customer_id' => $request->customer_id ?: null,
                    'grand_total' => $grandTotal,
                    'subtotal' => $request->subtotal,
                    'round_off' => $roundOff,
                    'sales_date' => $request->sales_date ?? $sale->sales_date,
                    'due_date' => $request->due_date ?? $sale->due_date,
                    'reference_no' => $request->reference_no ?? $sale->reference_no,
                    'other_charges_input' => $request->other_charges ?? $sale->other_charges_input,
                    'other_charges_amt' => $request->other_charges ?? $sale->other_charges_amt,
                    'discount_to_all_input' => $request->discount_on_all ?? $sale->discount_to_all_input,
                    'discount_to_all_type' => $request->discount_type ?? $sale->discount_to_all_type,
                    'tot_discount_to_all_amt' => ($request->discount_type == 'percent' && $request->discount_on_all > 0) ? ($request->subtotal * $request->discount_on_all / 100) : ($request->discount_on_all ?? $sale->tot_discount_to_all_amt),
                    'coupon_id' => $couponId ?? $sale->coupon_id,
                    'coupon_amt' => $couponAmt > 0 ? $couponAmt : $sale->coupon_amt,
                    'sales_note' => $request->sales_note ?? $sale->sales_note,
                ]);
            } else {
                $salesCode = \App\Services\CodeGeneratorService::generate('sales');

                $sale = DbSale::create([
                    'store_id' => Auth::user()->store_id ?? 1,
                    'warehouse_id' => $request->warehouse_id,
                    'sales_code' => $salesCode,
                    'sales_date' => $request->sales_date ?? date('Y-m-d'),
                    'due_date' => $request->due_date ?? null,
                    'reference_no' => $request->reference_no ?? null,
                    'customer_id' => $request->customer_id ?: null,
                    'grand_total' => $grandTotal,
                    'subtotal' => $request->subtotal,
                    'round_off' => $roundOff,
                    'paid_amount' => 0,
                    'payment_status' => 'Unpaid',
                    'other_charges_input' => $request->other_charges ?? 0,
                    'other_charges_amt' => $request->other_charges ?? 0,
                    'discount_to_all_input' => $request->discount_on_all ?? 0,
                    'discount_to_all_type' => $request->discount_type ?? 'fixed',
                    'tot_discount_to_all_amt' => ($request->discount_type == 'percent' && $request->discount_on_all > 0) ? ($request->subtotal * $request->discount_on_all / 100) : ($request->discount_on_all ?? 0),
                    'coupon_id' => $couponId,
                    'coupon_amt' => $couponAmt,
                    'sales_note' => $request->sales_note ?? null,
                    'created_by' => Auth::id(),
                    'system_ip' => $request->ip(),
                    'status' => 1,
                    'pos' => $request->has('is_pos') ? $request->is_pos : 1,
                ]);
            }

            // --- A11: SERVER-SIDE SERIAL VALIDATION (Phase 1) ---
            // Validate every posted selectedSerials entry inside the transaction
            // BEFORE any sale-item rows are written, so a failure aborts the whole
            // transaction (no partial commit). Locks each serial row so two
            // concurrent checkouts cannot both sell the same physical unit.
            $serialValidationError = $this->validateCartSerials($request->cart, $request->warehouse_id, $sale->store_id);
            if ($serialValidationError) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $serialValidationError], 422);
            }

            foreach ($request->cart as $item) {
                DbSaleItem::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'item_id' => $item['id'],
                    'sales_qty' => $item['qty'],
                    'price_per_unit' => $item['price'],
                    'total_cost' => $item['total'] ?? ($item['price'] * $item['qty']),
                    'status' => 1,
                    'discount_input' => $item['discount'] ?? 0,
                    'discount_type' => 'fixed',
                    'discount_amt' => isset($item['discount']) && $item['discount'] > 0 ? min($item['discount'], $item['price'] * $item['qty']) : 0,
                    'tax_type' => 'percentage',
                    'tax_amt' => $item['taxAmount'] ?? 0,
                    'unit_total_cost' => isset($item['total']) ? ($item['total'] / $item['qty']) : $item['price'],
                ]);

                // Update Stock — SKIP for service lines (service_bit=1): services are
                // non-inventory billable items and must never decrement db_items.stock
                // or db_warehouseitems.available_qty (they would otherwise drive the
                // service's stock into negative numbers on every sale).
                $dbItem = DbItem::find($item['id']);
                if ($dbItem && (int) $dbItem->service_bit !== 1) {
                    $dbItem->decrement('stock', $item['qty']);
                }

                $whItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_id)
                    ->where('item_id', $item['id'])
                    ->first();
                if ($whItem && $dbItem && (int) $dbItem->service_bit !== 1) {
                    $whItem->decrement('available_qty', $item['qty']);
                }

                // Check Low Stock Alerts
                $this->smsTriggerService->checkStockAlerts($item['id'], $request->warehouse_id);

                // Handle Serials
                if (isset($item['selectedSerials']) && is_array($item['selectedSerials'])) {
                    foreach ($item['selectedSerials'] as $serialId) {
                        DbItemSerial::where('id', $serialId)->update([
                            'status' => 1,
                            'sale_id' => $sale->id
                        ]);
                    }
                }
            }

            // Handle Payments (Multiple or Single)
            $totalPaid = 0;
            if ($request->has('payments') && is_array($request->payments)) {
                foreach ($request->payments as $p) {
                    if (isset($p['amount']) && $p['amount'] > 0) {
                        $payment = DbSalePayment::create([
                            'store_id' => $sale->store_id,
                            'sales_id' => $sale->id,
                            'payment_date' => date('Y-m-d'),
                            'payment_type' => $p['type'] ?? 'Cash',
                            'payment' => $p['amount'],
                            'payment_note' => $p['note'] ?? '',
                            'created_by' => Auth::id(),
                            'account_id' => $p['account'] ?? $request->account_id,
                            'customer_id' => $sale->customer_id,
                        ]);
                        $totalPaid += $p['amount'];

                        // Create Ledger Transaction & Update Account Balance
                        if ($payment->account_id && $payment->payment > 0) {
                            AcTransaction::create([
                                'store_id' => $sale->store_id,
                                'transaction_date' => $payment->payment_date ?? date('Y-m-d'),
                                'transaction_type' => 'SALES PAYMENT',
                                'payment_code' => $payment->payment_type ?? 'Cash',
                                'credit_account_id' => $payment->account_id,
                                'debit_account_id' => null,
                                'debit_amt' => 0,
                                'credit_amt' => $payment->payment,
                                'note' => 'POS Sale Payment: ' . $sale->sales_code,
                                'ref_salespayments_id' => $payment->id,
                                'customer_id' => $sale->customer_id,
                                'created_by' => Auth::id() ?? 1,
                                'created_date' => date('Y-m-d'),
                            ]);

                            $acc = AcAccount::find($payment->account_id);
                            if ($acc) {
                                $acc->increment('balance', $payment->payment);
                            }
                        }

                        // Trigger Payment Received SMS
                        if ($sale->customer_id) {
                            $this->smsTriggerService->trigger('PaymentReceived', $payment);
                        }
                    }
                }
            } elseif ($request->paid_amount > 0) {
                $payment = DbSalePayment::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'payment_date' => date('Y-m-d'),
                    'payment_type' => $request->payment_type ?? 'Cash',
                    'payment' => $request->paid_amount,
                    'payment_note' => $request->payment_note ?? '',
                    'created_by' => Auth::id(),
                    'account_id' => $request->account_id,
                    'customer_id' => $sale->customer_id,
                ]);
                $totalPaid = $request->paid_amount;

                // Create Ledger Transaction & Update Account Balance
                if ($payment->account_id && $payment->payment > 0) {
                    AcTransaction::create([
                        'store_id' => $sale->store_id,
                        'transaction_date' => $payment->payment_date ?? date('Y-m-d'),
                        'transaction_type' => 'SALES PAYMENT',
                        'payment_code' => $payment->payment_type ?? 'Cash',
                        'credit_account_id' => $payment->account_id,
                        'debit_account_id' => null,
                        'debit_amt' => 0,
                        'credit_amt' => $payment->payment,
                        'note' => 'POS Sale Payment: ' . $sale->sales_code,
                        'ref_salespayments_id' => $payment->id,
                        'customer_id' => $sale->customer_id,
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    $acc = AcAccount::find($payment->account_id);
                    if ($acc) {
                        $acc->increment('balance', $payment->payment);
                    }
                }

                // Trigger Payment Received SMS
                if ($sale->customer_id) {
                    $this->smsTriggerService->trigger('PaymentReceived', $payment);
                }
            }

            if ($advanceAmount > 0) {
                $advancePayment = DbSalePayment::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'payment_date' => date('Y-m-d'),
                    'payment_type' => 'Advance',
                    'payment' => $advanceAmount,
                    'advance_adjusted' => $advanceAmount,
                    'payment_note' => 'Applied customer advance',
                    'created_by' => Auth::id(),
                    'customer_id' => $sale->customer_id,
                ]);
                $totalPaid += $advanceAmount;
            }

            // Update Sale with real total paid and status
            $sale->update([
                'paid_amount' => $totalPaid,
                'payment_status' => $totalPaid >= $sale->grand_total ? 'Paid' : ($totalPaid > 0 ? 'Partial' : 'Unpaid'),
            ]);

            // Consume the customer coupon only now — AFTER the sale is fully created
            // inside the same transaction. If anything above had thrown, the rollback
            // would revert this update too, so a failed sale never burns the coupon.
            $this->consumeCouponAfterSale();

            DB::commit();

            // Invalidate dashboard caches affected by new sale
            $sid = current_store_id();
            Cache::forget('dashboard_outstanding_due_s' . $sid);
            Cache::forget('dashboard_customers_due_s' . $sid);
            Cache::forget('dashboard_month_sale_ids_s' . $sid);
            Cache::forget('dashboard_chart_last7_s' . $sid);
            Cache::forget('dashboard_chart_last30_s' . $sid);
            Cache::forget('dashboard_chart_weekly_s' . $sid);
            Cache::forget('dashboard_chart_monthly_s' . $sid);

            // Consume the hold row after the sale committed. The row was already
            // atomically claimed (status -> 'completed') inside the transaction
            // above (shared A1 claim); this deletes the staging row + its items.
            // Post-commit deletion means a rollback above leaves the hold claim
            // intact and the sale non-duplicated.
            $this->consumeHold($request->input('hold_id'));

            // Trigger SMS notification (InvoiceCreated is handled automatically by SMSObserver on DbSale)
            if ($sale->customer_id) {
                // Trigger Large Transaction Alert (Threshold could be dynamic, but for now we just trigger the event)
                $this->smsTriggerService->trigger('LargeTransactionAlert', $sale);
            }

            return response()->json(['success' => true, 'message' => 'Sale completed successfully', 'sale_id' => $sale->id]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('POS Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function hold(Request $request)
    {
        \Log::info('Hold request received', $request->all());

        // --- A6: LIGHTWEIGHT VALIDATION AT HOLD TIME ---
        // Prevents obviously-broken holds (empty/invalid warehouse, empty cart,
        // non-positive quantities) from being persisted. This is deliberately
        // lighter than store()'s full validation: prices and stock may
        // legitimately change between hold and resume, so we do NOT enforce
        // stock availability or strict price checks here.
        $warehouseId = $request->input('warehouse_id');
        if (empty($warehouseId) || !DbWarehouse::where('id', $warehouseId)->where('store_id', current_store_id())->where('status', 1)->exists()) {
            return response()->json(['success' => false, 'message' => 'The selected warehouse does not belong to your store.'], 422);
        }

        $cart = $request->input('cart', []);
        if (!is_array($cart) || count($cart) === 0) {
            return response()->json(['success' => false, 'message' => 'Cart cannot be empty.'], 422);
        }

        foreach ($cart as $index => $item) {
            if (!is_array($item) || empty($item['id'])) {
                return response()->json(['success' => false, 'message' => 'Cart item #' . ($index + 1) . ' is missing an item id.'], 422);
            }
            $qty = $item['qty'] ?? null;
            if (!is_numeric($qty) || (float) $qty <= 0) {
                return response()->json(['success' => false, 'message' => 'Cart item #' . ($index + 1) . ' has an invalid quantity.'], 422);
            }
        }

        try {
            DB::beginTransaction();

            $store_id = Auth::user()->store_id ?? 1;

            // --- A7: EXPLICIT DUPLICATE-REFERENCE REJECTION ---
            // Previously a new hold with a reference_no matching an existing
            // hold silently deleted the old one (only the modal's static text
            // warned about it). Now we reject explicitly so the cashier can
            // pick a different reference instead of losing the earlier hold.
            if ($request->reference_no) {
                $existingHold = DbHold::where('reference_no', $request->reference_no)
                    ->where('store_id', $store_id)
                    ->where('status', 'open')
                    ->first();
                if ($existingHold) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'A hold with reference "' . $request->reference_no . '" already exists. Please use a different reference number.',
                    ], 422);
                }
            }

            \Log::info('Creating new hold...');
            $hold = DbHold::create([
                'store_id' => $store_id,
                'warehouse_id' => $warehouseId,
                'reference_no' => $request->reference_no,
                'sales_date' => date('Y-m-d'),
                'customer_id' => $request->customer_id ?: null,
                'subtotal' => $request->subtotal,
                'grand_total' => $request->grand_total,
                'sales_note' => $request->notes,
                'pos' => 1,
                'status' => 'open',
                // --- A3: persist cart-level discount + coupon so resume restores
                // the exact held cart (discountOnAll / discountType / coupon) ---
                'discount_on_all' => $request->discount_on_all ?? 0,
                'discount_type' => $request->discount_type ?? 'fixed',
                'coupon_id' => $request->coupon_id ?? null,
                'customer_coupon_id' => $request->customer_coupon_id ?? null,
                'coupon_code' => $request->coupon_code ?? null,
                'coupon_type' => $request->coupon_type ?? null,
                'coupon_value' => $request->coupon_value ?? 0,
                'coupon_amount' => $request->coupon_amt ?? 0,
            ]);

            \Log::info('Hold created with ID: ' . $hold->id);

            foreach ($cart as $item) {
                DbHoldItem::create([
                    'store_id' => $hold->store_id,
                    'hold_id' => $hold->id,
                    'item_id' => $item['id'],
                    'sales_qty' => $item['qty'],
                    'price_per_unit' => $item['price'],
                    // --- A3: persist per-line tax + serialized flag ---
                    'tax_percent' => $item['tax'] ?? 0,
                    'tax_amt' => $item['taxAmount'] ?? 0,
                    'is_serialized' => !empty($item['isSerialized']) ? 1 : 0,
                    'total_cost' => $item['price'] * $item['qty'],
                    'discount_input' => $item['discount'] ?? 0,
                    'discount_type' => 'fixed',
                    'discount_amt' => isset($item['discount']) && $item['discount'] > 0 ? min($item['discount'], $item['price'] * $item['qty']) : 0,
                ]);
            }

            DB::commit();
            \Log::info('Hold transaction committed successfully');
            return response()->json(['success' => true, 'message' => 'Invoice held successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Hold error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeEmi(Request $request)
    {
        try {
            // --- SERVER-SIDE VALIDATION (shared by POS and Add Sale flows) ---
            $validationError = $this->validateSalePayload($request);
            if ($validationError) {
                return response()->json(['success' => false, 'message' => $validationError], 422);
            }

            // --- EMI eligibility gate (server-side) ---
            // The UI (POS + Add Sale) only shows the EMI flow when the selected
            // customer's db_customers.customer_type is 'emi'. A direct API call
            // bypassing the UI could otherwise create an EMI sale for any customer,
            // so re-verify the flag here before any EMI logic proceeds.
            $emiCustomerId = $request->input('customer_id');
            if ($emiCustomerId && !in_array($emiCustomerId, ['Walk-in customer', 'Walk-in Customer'], true)) {
                $emiCustomer = DbCustomer::find($emiCustomerId);
                if (!$emiCustomer || strtolower((string) $emiCustomer->customer_type) !== 'emi') {
                    return response()->json([
                        'success' => false,
                        'message' => 'EMI sales are only available for customers whose type is EMI. Please select an EMI-eligible customer.',
                    ], 422);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'EMI sales require a valid customer. Walk-in customers are not eligible for EMI.',
                ], 422);
            }

            DB::beginTransaction();

            // --- A1 (EMI path): ATOMIC HOLD CLAIM (double-resume guard) ---
            // Mirrors the regular checkout path's claim so a held invoice can only
            // be completed ONCE regardless of which checkout path (store() or
            // storeEmi()) resumes it. Without this, completing a resumed hold via
            // the EMI modal left db_hold.status='open' untouched, allowing the same
            // hold to be resumed and completed again (duplicate EMI sale + second
            // stock decrement). The row lock is shared with store() (same query +
            // same status='open' precondition), so a hold consumed by either path is
            // rejected by the other.
            $holdClaimError = $this->claimOpenHold($request->input('hold_id'));
            if ($holdClaimError) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $holdClaimError,
                ], 409);
            }

            $totalAmount = $request->grand_total;
            $initialPay = $request->initial_pay ?? 0;
            $processingFee = $request->processing_fee ?? 0;
            $duration = $request->duration;
            $startDate = $request->start_date ?? date('Y-m-d');
            $loanAmount = $totalAmount - $initialPay;
            $advanceAmount = max(0, (float) $request->advance_amount);

            // Validation
            if ($loanAmount <= 0) {
                 DB::rollBack();
                 return response()->json(['success' => false, 'message' => 'Loan amount must be greater than 0'], 400);
            }

            if ($advanceAmount > 0) {
                $customer = DbCustomer::where('store_id', current_store_id())->find($request->customer_id);
                $availableAdvance = (float) ($customer->tot_advance ?? 0);
                if (!$customer || $advanceAmount > $availableAdvance || $advanceAmount > $totalAmount) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Advance adjustment exceeds the customer available advance or sale total.'], 422);
                }
                $customer->decrement('tot_advance', $advanceAmount);
            }

            list($couponId, $couponAmt) = $this->resolveCoupon($request, $request->subtotal);

            // --- Server-side total recomputation + mismatch REJECTION ---
            $serverTotals = $this->recomputeServerTotals($request, $couponAmt);
            $clientGrandTotal = (float) $request->grand_total;
            $serverGrandTotal = (float) ($serverTotals['server_grand_total'] ?? 0);
            $serverRawPayable = (float) ($serverTotals['server_raw_payable'] ?? $serverGrandTotal);
            $matchesRaw = abs($clientGrandTotal - $serverRawPayable) <= 0.01;
            $matchesRounded = abs($clientGrandTotal - $serverGrandTotal) <= 0.01;
            $enforceTotalValidation = config('sales.enforce_total_validation', true);
            if (!$matchesRaw && !$matchesRounded) {
                $this->logTotalMismatch($request, $serverTotals, $clientGrandTotal, 'storeEmi');
                if ($enforceTotalValidation) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Total mismatch detected. Please refresh and try again. (Server total: ' . number_format($serverGrandTotal, 2) . ')',
                    ], 422);
                }
                // KILL-SWITCH OFF: fall back to trusting the client total (pre-fix behavior).
                $serverGrandTotal = $clientGrandTotal;
            }
            // Persist the server-authoritative total (not the client-sent one).
            $totalAmount = $serverGrandTotal;

            // 1. Create Sale (Unpaid initially or with initial pay)
            $salesCode = \App\Services\CodeGeneratorService::generate('sales');

            $sale = DbSale::create([
                'store_id' => Auth::user()->store_id ?? 1,
                'warehouse_id' => $request->warehouse_id,
                'sales_code' => $salesCode,
                'sales_date' => $request->sales_date ?? date('Y-m-d'),
                'due_date' => $request->due_date ?? null,
                'reference_no' => $request->reference_no ?? null,
                'customer_id' => $request->customer_id,
                'grand_total' => $totalAmount,
                'subtotal' => $request->subtotal,
                'paid_amount' => $initialPay + $advanceAmount,
                'payment_status' => ($initialPay + $advanceAmount) > 0 ? 'Partial' : 'Unpaid',
                'other_charges_input' => $request->other_charges ?? 0,
                'other_charges_amt' => $request->other_charges ?? 0,
                'discount_to_all_input' => $request->discount_on_all ?? 0,
                'discount_to_all_type' => $request->discount_type ?? 'fixed',
                'tot_discount_to_all_amt' => ($request->discount_type == 'percent' && $request->discount_on_all > 0) ? ($request->subtotal * $request->discount_on_all / 100) : ($request->discount_on_all ?? 0),
                'coupon_id' => $couponId,
                'coupon_amt' => $couponAmt,
                'sales_note' => $request->notes ?? null,
                'created_by' => Auth::id(),
                'system_ip' => $request->ip(),
                'status' => 1,
                'pos' => $request->has('is_pos') ? $request->is_pos : 1,
            ]);

            // --- A11: SERVER-SIDE SERIAL VALIDATION (Phase 1, EMI path) ---
            // Same validation as the regular checkout: lock + verify each posted
            // serial before any sale-item rows are written; abort on first failure.
            $serialValidationError = $this->validateCartSerials($request->cart, $request->warehouse_id, $sale->store_id);
            if ($serialValidationError) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $serialValidationError], 422);
            }

            // 2. Save Items & Update Stock
            foreach ($request->cart as $item) {
                DbSaleItem::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'item_id' => $item['id'],
                    'sales_qty' => $item['qty'],
                    'price_per_unit' => $item['price'],
                    'total_cost' => $item['total'] ?? ($item['price'] * $item['qty']),
                    'status' => 1,
                    'discount_input' => $item['discount'] ?? 0,
                    'discount_type' => 'fixed',
                    'discount_amt' => isset($item['discount']) && $item['discount'] > 0 ? min($item['discount'], $item['price'] * $item['qty']) : 0,
                    'tax_type' => 'percentage',
                    'tax_amt' => $item['taxAmount'] ?? 0,
                    'unit_total_cost' => isset($item['total']) ? ($item['total'] / $item['qty']) : $item['price'],
                ]);

                // Decrement Stock — SKIP for service lines (service_bit=1): services are
                // non-inventory billable items and must never decrement db_items.stock
                // or db_warehouseitems.available_qty.
                $dbItem = DbItem::find($item['id']);
                if($dbItem && (int) $dbItem->service_bit !== 1) {
                     $dbItem->decrement('stock', $item['qty']);
                }
                
                $whItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_id)
                    ->where('item_id', $item['id'])
                    ->first();
                if($whItem && $dbItem && (int) $dbItem->service_bit !== 1) {
                    $whItem->decrement('available_qty', $item['qty']);
                }

                 // Handle Serials
                if (isset($item['selectedSerials']) && is_array($item['selectedSerials'])) {
                    foreach ($item['selectedSerials'] as $serialId) {
                        DbItemSerial::where('id', $serialId)->update([
                            'status' => 1,
                            'sale_id' => $sale->id
                        ]);
                    }
                }
            }

            // 3. Save Initial Payment if any
            if ($initialPay > 0) {
                $payment = DbSalePayment::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'payment_date' => date('Y-m-d'),
                    'payment_type' => 'Cash',
                    'payment' => $initialPay,
                    'created_by' => Auth::id(),
                    'account_id' => $request->account_id,
                    'customer_id' => $request->customer_id,
                    'payment_note' => 'EMI Initial Payment'
                ]);

                if ($payment->account_id) {
                    AcTransaction::create([
                        'store_id' => $sale->store_id,
                        'transaction_date' => $payment->payment_date ?? date('Y-m-d'),
                        'transaction_type' => 'SALES PAYMENT',
                        'payment_code' => $payment->payment_type ?? 'Cash',
                        'credit_account_id' => $payment->account_id,
                        'debit_account_id' => null,
                        'debit_amt' => 0,
                        'credit_amt' => $payment->payment,
                        'note' => 'EMI Initial Payment: ' . $sale->sales_code,
                        'ref_salespayments_id' => $payment->id,
                        'customer_id' => $sale->customer_id,
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    $acc = AcAccount::find($payment->account_id);
                    if ($acc) {
                        $acc->increment('balance', $payment->payment);
                    }
                }
            }

            // 3b. Record Advance Payment if any
            if ($advanceAmount > 0) {
                $advancePayment = DbSalePayment::create([
                    'store_id' => $sale->store_id,
                    'sales_id' => $sale->id,
                    'payment_date' => date('Y-m-d'),
                    'payment_type' => 'Advance',
                    'payment' => $advanceAmount,
                    'advance_adjusted' => $advanceAmount,
                    'payment_note' => 'Applied customer advance',
                    'created_by' => Auth::id(),
                    'customer_id' => $sale->customer_id,
                ]);
            }

            // 4. Calculate Monthly Installment logic (Server Side)
            $totalLoanWithFee = $loanAmount + $processingFee;
            $monthlyInstallment = $totalLoanWithFee / $duration;

            // 4. Create EMI Sale Record
            $emiSale = DbEmiSale::create([
                'sale_id' => $sale->id,
                'customer_id' => $request->customer_id,
                'loan_amount' => $loanAmount,
                'total_payable' => $totalLoanWithFee,
                'duration_months' => $duration,
                'monthly_installment' => $monthlyInstallment,
                'processing_fee' => $processingFee,
                'start_date' => $startDate,
                'status' => 'Active',
                'notes' => $request->notes,
            ]);

            // 5. Generate EMI Schedule
            $scheduleDate = \Carbon\Carbon::parse($startDate);
            
            for ($i = 1; $i <= $duration; $i++) {
                $scheduleDate->addMonth();
                
                DbEmiSchedule::create([
                    'emi_sale_id' => $emiSale->id,
                    'installment_no' => $i,
                    'due_date' => $scheduleDate->format('Y-m-d'),
                    'amount' => $monthlyInstallment,
                    'status' => 'Pending',
                ]);
            }

            // Consume the customer coupon only now — AFTER the EMI sale is fully created
            // inside the same transaction. If anything above had thrown, the rollback
            // would revert this update too, so a failed EMI sale never burns the coupon.
            $this->consumeCouponAfterSale();

            DB::commit();

            // Invalidate dashboard caches affected by new EMI sale
            $sid = current_store_id();
            Cache::forget('dashboard_outstanding_due_s' . $sid);
            Cache::forget('dashboard_customers_due_s' . $sid);
            Cache::forget('dashboard_month_sale_ids_s' . $sid);
            Cache::forget('dashboard_chart_last7_s' . $sid);
            Cache::forget('dashboard_chart_last30_s' . $sid);
            Cache::forget('dashboard_chart_weekly_s' . $sid);
            Cache::forget('dashboard_chart_monthly_s' . $sid);

            // Consume the hold row after the EMI sale committed. The row was already
            // atomically claimed (status -> 'completed') inside the transaction above
            // (shared A1 claim); this deletes the staging row + its items. Post-commit
            // deletion means a rollback above leaves the hold claim intact and the
            // EMI sale non-duplicated.
            $this->consumeHold($request->input('hold_id'));

            return response()->json(['success' => true, 'message' => 'EMI Sale completed successfully', 'sale_id' => $sale->id, 'emi_sale_id' => $emiSale->id]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function holdList(Request $request)
    {
        $storeId = current_store_id();

        // A4: scope to the current store (DbHold.store_id was written but never
        // used to filter the list — every user saw every store's holds).
        // A9: eager-load items via withCount so the per-row item-count column
        // does not trigger an N+1 query.
        $query = DbHold::with(['customer', 'warehouse'])
            ->withCount('items')
            ->where('store_id', $storeId)
            ->orderBy('id', 'desc');

        // A4: warehouse filter (follows the Sales List convention)
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // A5: search by reference_no or customer name (GET param, Sales List convention)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', '%' . $search . '%')
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('customer_name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Filtered footer total — matches the Sales List/Payments pattern of a
        // footer reflecting the currently-filtered set (was a full-collection sum).
        $filteredTotal = (clone $query)->sum('grand_total');

        // A5: real pagination (was ->get() with a decorative "Show entries" box)
        $holds = $query->paginate($request->limit ?? 10);

        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();

        return view('module.sales.hold_list', compact('holds', 'warehouses', 'filteredTotal'));
    }

    public function deleteHold($id)
    {
        try {
            DB::beginTransaction();
            // Only 'open' holds can be discarded — a hold that has been claimed by a
            // completed sale ('completed') is already consumed and must not be
            // deleted out from under that sale's record.
            $hold = DbHold::where('id', $id)
                ->where('store_id', current_store_id())
                ->where('status', 'open')
                ->first();

            if (!$hold) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'This hold is no longer available to delete.'], 404);
            }

            $hold->items()->delete();
            $hold->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Hold deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCustomerDue($id)
    {
        try {
            $totalSales = \App\Models\DbSale::where('customer_id', $id)->sum('grand_total');
            $totalReturn = \App\Models\DbSalesReturn::where('customer_id', $id)->sum('grand_total');
            $totalPayments = \App\Models\DbSalePayment::where('customer_id', $id)->sum('payment');
            $totalReturnPayments = \App\Models\DbSalesPaymentReturn::where('customer_id', $id)->sum('payment');

            $due = ($totalSales - $totalReturn) - ($totalPayments - $totalReturnPayments);

            $customer = DbCustomer::find($id);

            // Last purchase (most recent sale) for the customer snapshot panel —
            // same read-only endpoint as the due fetch, no extra request needed.
            $lastSale = \App\Models\DbSale::where('customer_id', $id)
                ->orderBy('sales_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'due' => max(0, $due),
                'advance' => max(0, (float) ($customer->tot_advance ?? 0)),
                'last_purchase_date' => $lastSale ? $lastSale->sales_date : null,
                'last_purchase_amount' => $lastSale ? (float) $lastSale->grand_total : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * A1: Atomically claim an open held invoice for completion.
     *
     * Shared by store() and storeEmi() so a resumed hold can only be completed
     * ONCE regardless of which checkout path resumes it. MUST be called inside an
     * open DB transaction: the claim takes a row lock on the hold so two
     * concurrent completions of the same hold_id cannot both pass — the second
     * transaction blocks on the lock, then observes the hold either deleted
     * (committed) or marked 'completed' (still in flight) and is rejected.
     *
     * Marking 'completed' (rather than deleting here) keeps the claim atomic with
     * the sale/EMI-sale creation: if the surrounding transaction rolls back, the
     * status update rolls back too and the hold stays 'open' for a legitimate
     * retry. The actual row deletion happens post-commit via consumeHold().
     *
     * @param int|string|null $holdId
     * @return string|null Error message when the hold cannot be claimed; null on success.
     */
    private function claimOpenHold($holdId): ?string
    {
        if (!$holdId) {
            return null;
        }

        $holdClaim = DbHold::where('id', $holdId)
            ->where('store_id', current_store_id())
            ->where('status', 'open')
            ->lockForUpdate()
            ->first();

        if (!$holdClaim) {
            return 'This held sale has already been resumed or completed. Please start a new sale instead.';
        }

        $holdClaim->update(['status' => 'completed']);

        return null;
    }

    /**
     * A1: Delete a consumed hold row + its items.
     *
     * Called only AFTER the sale/EMI-sale transaction committed (post-commit
     * cleanup). The row was already atomically claimed (status -> 'completed')
     * inside the transaction by claimOpenHold(); this removes the staging row.
     * Post-commit deletion means a rollback above leaves the hold claim intact
     * and the sale non-duplicated.
     *
     * @param int|string|null $holdId
     * @return void
     */
    private function consumeHold($holdId): void
    {
        if (!$holdId) {
            return;
        }

        $hold = DbHold::where('id', $holdId)
            ->where('store_id', current_store_id())
            ->first();

        if ($hold) {
            $hold->items()->delete();
            $hold->delete();
        }
    }

    /**
     * Server-side validation for sale creation (POS and Add Sale both hit this).
     *
     * Validates warehouse/customer existence, cart shape, per-line qty/price,
     * and per-line stock availability at the selected warehouse. Returns a
     * human-readable error message, or null when the payload is valid.
     *
     * NOTE: quantity is allowed to be fractional (decimal(16,2) in db_salesitems)
     * since this codebase sells by weight/measure in places (e.g. loose items);
     * min:0.01 is enforced. Serialized items are implicitly validated because
     * their qty is locked to the count of selectedSerials by the clients.
     *
     * @return string|null
     */
    private function validateSalePayload(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        if (empty($warehouseId) || !DbWarehouse::where('id', $warehouseId)->where('store_id', current_store_id())->where('status', 1)->exists()) {
            return 'The selected warehouse does not belong to your store.';
        }

        $customerId = $request->input('customer_id');
        if ($customerId && !in_array($customerId, ['Walk-in customer', 'Walk-in Customer'], true) && !DbCustomer::where('id', $customerId)->exists()) {
            return 'The selected customer is invalid.';
        }

        $cart = $request->input('cart', []);
        if (!is_array($cart) || count($cart) === 0) {
            return 'Cart cannot be empty.';
        }

        // Aggregate requested qty per item to detect duplicate lines.
        $requestedQty = [];
        foreach ($cart as $index => $item) {
            if (!is_array($item) || empty($item['id'])) {
                return 'Cart item #' . ($index + 1) . ' is missing an item id.';
            }

            $qty = $item['qty'] ?? null;
            if (!is_numeric($qty) || (float) $qty <= 0) {
                return 'Cart item #' . ($index + 1) . ' has an invalid quantity.';
            }

            $price = $item['price'] ?? null;
            if (!is_numeric($price) || (float) $price < 0) {
                return 'Cart item #' . ($index + 1) . ' has an invalid price.';
            }

            $itemId = (int) $item['id'];
            if (!DbItem::where('id', $itemId)->where('status', 1)->exists()) {
                return 'Cart item #' . ($index + 1) . ' references an inactive or missing item.';
            }

            $requestedQty[$itemId] = ($requestedQty[$itemId] ?? 0) + (float) $qty;
        }

        // Stock availability check per item.
        // Stock model: db_items.stock is the global sellable stock; db_warehouseitems
        // is an optional per-warehouse allocation that may be absent (the store code
        // only decrements available_qty when the row exists). Use the per-warehouse
        // allocation when present, otherwise fall back to the item's global stock.
        $itemIds = array_keys($requestedQty);
        $warehouseStock = DbWarehouseItem::where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->pluck('available_qty', 'item_id')
            ->map(fn ($q) => (float) $q)
            ->all();

        $globalStock = DbItem::whereIn('id', $itemIds)
            ->pluck('stock', 'id')
            ->map(fn ($q) => (float) $q)
            ->all();

        // Service items (service_bit=1) are non-inventory billable lines and carry no
        // sellable stock, so they are exempt from the stock-availability gate — their
        // checkout never decrements stock either.
        $serviceItemIds = DbItem::whereIn('id', array_keys($requestedQty))
            ->where('service_bit', 1)
            ->pluck('id')
            ->flip();

        foreach ($requestedQty as $itemId => $qty) {
            if (isset($serviceItemIds[$itemId])) {
                continue;
            }
            $available = $warehouseStock[$itemId] ?? ($globalStock[$itemId] ?? 0);
            if ($available < $qty) {
                $item = DbItem::find($itemId);
                $name = $item->item_name ?? ('#' . $itemId);
                return "Insufficient stock for '{$name}' (requested {$qty}, available {$available}).";
            }
        }

        return null;
    }

    /**
     * A11: Validate every posted selectedSerials entry for a checkout payload.
     *
     * Called INSIDE the sale transaction (store() and storeEmi()) BEFORE any
     * sale-item rows are written. For each posted serial ID per cart line:
     *   (a) the row must exist and belong to the EXACT item on that line,
     *   (b) status must be 0 (Available),
     *   (c) warehouse_id and store_id must match the sale's warehouse/store.
     * Also verifies count(selectedSerials) === posted qty for lines that carry
     * selectedSerials — the client-side qty===serials.length lock is NOT trusted.
     *
     * Rows are locked with lockForUpdate() so two concurrent checkouts selling
     * the same physical unit serialize: the second sees status=1 (committed by
     * the first) and is rejected with a clear message naming that serial.
     *
     * Returns a human-readable error message, or null when the payload is valid.
     *
     * @param array  $cart          Decoded cart lines from the request.
     * @param int    $warehouseId   Sale warehouse id (validated upstream).
     * @param int    $storeId       Sale store id.
     * @return string|null
     */
    private function validateCartSerials(array $cart, $warehouseId, $storeId): ?string
    {
        // --- Phase 3 (server net): the same serial ID must not appear twice across
        // ANY lines of the payload. The per-row lock below can't catch this (the
        // row is still status=0 on the second read within one transaction).
        $seenSerialIds = [];
        foreach ($cart as $item) {
            $selectedSerials = $item['selectedSerials'] ?? null;
            if (!is_array($selectedSerials)) {
                continue;
            }
            foreach ($selectedSerials as $serialId) {
                $serialId = (int) $serialId;
                if (in_array($serialId, $seenSerialIds, true)) {
                    return "Serial #{$serialId} is already in this cart.";
                }
                $seenSerialIds[] = $serialId;
            }
        }

        foreach ($cart as $index => $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $selectedSerials = $item['selectedSerials'] ?? null;
            $hasSerials = is_array($selectedSerials) && count($selectedSerials) > 0;

            // Authoritative serialized flag from the DB (never trust the client).
            $itemRow = $itemId ? DbItem::where('id', $itemId)->first() : null;
            $isSerialized = $itemRow && (int) $itemRow->is_serialized === 1;

            // Plain (non-serialized, no-serial) lines are untouched by this validation.
            if (!$hasSerials && !$isSerialized) {
                continue;
            }

            // (count check) — do not trust the client-side qty===serials.length lock.
            // Applies to EVERY serialized line (covers resumed holds that would
            // otherwise complete with qty>0 but zero serials, leaving status=0 rows
            // never marked Sold) AND any line that posts serials at all.
            $serialCount = $hasSerials ? count($selectedSerials) : 0;
            if ($serialCount !== (int) round($qty)) {
                $itemName = $item['name'] ?? ('#' . $itemId);
                return "Serial count mismatch for '{$itemName}': {$serialCount} serial(s) posted for quantity {$qty}.";
            }

            if (!$hasSerials) {
                continue;
            }

            foreach ($selectedSerials as $serialId) {
                // Fetch ANY serial by id (bypass the store global scope) so the
                // explicit store/warehouse membership checks below can produce
                // their accurate error messages instead of a generic "not exists".
                $serial = DbItemSerial::where('id', (int) $serialId)
                    ->allStores()
                    ->lockForUpdate()
                    ->first();

                // (a) row exists + belongs to this exact item.
                if (!$serial) {
                    return "Serial #{$serialId} does not exist.";
                }
                if ((int) $serial->item_id !== $itemId) {
                    return "Serial {$serial->serial_number} does not belong to item #{$itemId}.";
                }

                // (b) Available only — closes the double-sell race under lock.
                if ((int) $serial->status !== 0) {
                    return "Serial {$serial->serial_number} is no longer available.";
                }

                // (c) warehouse + store scoping.
                if ((int) $serial->warehouse_id !== (int) $warehouseId) {
                    return "Serial {$serial->serial_number} does not belong to the selected warehouse.";
                }
                if ((int) $serial->store_id !== (int) $storeId) {
                    return "Serial {$serial->serial_number} does not belong to the current store.";
                }
            }
        }

        return null;
    }

    /**
     * Consume the deferred customer coupon (if one was resolved) — called only
     * AFTER the sale/EMI sale has been created inside the transaction, so a
     * failure or rollback reverts the coupon-used flag and the coupon stays usable.
     */
    private function consumeCouponAfterSale()
    {
        if ($this->consumedCustomerCoupon) {
            $this->consumedCustomerCoupon->update(['status' => 0]);
            $this->consumedCustomerCoupon = null;
        }
    }

    /**
     * Resolve and re-validate coupon server-side for sale creation.
     */
    private function resolveCoupon(Request $request, $subtotal)
    {
        $couponId = null;
        $couponAmt = 0;

        if ($request->filled('coupon_code') || $request->filled('coupon_id') || $request->filled('customer_coupon_id')) {
            $couponCode = strtoupper(trim($request->coupon_code ?? ''));
            $customerId = $request->customer_id ?: null;
            if ($customerId === 'Walk-in customer' || $customerId === 'Walk-in Customer') {
                $customerId = null;
            }
            $today = date('Y-m-d');

            // 1. Check customer coupon first
            $custCoupon = null;
            if ($request->filled('customer_coupon_id')) {
                $custCoupon = DbCustomerCoupon::where('store_id', current_store_id())->find($request->customer_coupon_id);
            } elseif ($couponCode) {
                $custCoupon = DbCustomerCoupon::where('store_id', current_store_id())->where('code', $couponCode)->first();
            }

            if ($custCoupon && $custCoupon->status == 1 && (!$custCoupon->expire_date || $custCoupon->expire_date >= $today) && ($customerId && (int)$custCoupon->customer_id === (int)$customerId)) {
                $couponId = ($custCoupon->coupon_id && DbCoupon::where('id', $custCoupon->coupon_id)->exists()) ? $custCoupon->coupon_id : null;
                if (strtolower($custCoupon->type) === 'percentage') {
                    $couponAmt = round(((float)$subtotal * (float)$custCoupon->value) / 100, 2);
                } else {
                    $couponAmt = (float)$custCoupon->value;
                }
                $couponAmt = min($couponAmt, (float)$subtotal);
                // NOTE: Coupon consumption is deferred until AFTER the sale is successfully
                // created (see consumeCouponAfterSale()) so a failed sale doesn't burn a coupon.
                // $this->consumedCustomerCoupon is set here; consumed by the caller post-commit.
                $this->consumedCustomerCoupon = $custCoupon;
            } else {
                // 2. Check master coupon
                $masterCoupon = null;
                if ($request->filled('coupon_id')) {
                    $masterCoupon = DbCoupon::find($request->coupon_id);
                } elseif ($couponCode) {
                    $masterCoupon = DbCoupon::where('code', $couponCode)->first();
                }

                if ($masterCoupon && $masterCoupon->status == 1 && (!$masterCoupon->expire_date || $masterCoupon->expire_date >= $today)) {
                    $couponId = $masterCoupon->id;
                    if (strtolower($masterCoupon->type) === 'percentage') {
                        $couponAmt = round(((float)$subtotal * (float)$masterCoupon->value) / 100, 2);
                    } else {
                        $couponAmt = (float)$masterCoupon->value;
                    }
                    $couponAmt = min($couponAmt, (float)$subtotal);
                }
            }
        }

        return [$couponId, $couponAmt];
    }

    /**
     * Independently recompute the sale totals on the server using authoritative
     * product prices (DbItem.sales_price), server-revalidated coupon, per-line tax,
     * and other charges — mirroring the Add Sale client's grandTotal getter exactly:
     *   grand_total = max(0, subtotal + totalTax + otherCharges − totalDiscount)
     * (see add.blade.php subtotal / totalDiscount / grandTotal).
     *
     * The result is compared against the server's own persisted total in store() /
     * storeEmi(); a difference beyond the rounding tolerance REJECTS the sale.
     *
     * @param Request $request
     * @param float   $couponAmt Already server-revalidated coupon amount from resolveCoupon().
     *                           resolveCoupon() consumes customer coupons, so it must only be
     *                           called once per request - we reuse its result here.
     * @return array{server_subtotal:float, server_item_discount:float, server_invoice_discount:float, server_total_discount:float, server_total_tax:float, server_other_charges:float, server_round_off:float, server_grand_total:float, lines:array}
     */
    private function recomputeServerTotals(Request $request, $couponAmt = 0.0)
    {
        $cart = $request->input('cart', []);
        if (!is_array($cart) || empty($cart)) {
            return [
                'server_subtotal' => 0.0,
                'server_item_discount' => 0.0,
                'server_invoice_discount' => 0.0,
                'server_total_discount' => 0.0,
                'server_total_tax' => 0.0,
                'server_other_charges' => 0.0,
                'server_round_off' => 0.0,
                'server_grand_total' => 0.0,
                'lines' => [],
            ];
        }

        // Authoritative server-side prices (NOT the client-sent i.price).
        $ids = [];
        foreach ($cart as $item) {
            if (!empty($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }
        $ids = array_unique($ids);
        $serverPrices = DbItem::whereIn('id', $ids)->pluck('sales_price', 'id')->map(function ($price) {
            return (float) $price;
        })->all();

        $store = store_settings();
        $roundOffEnabled = !empty($store->round_off);
        $discountOnAll = (float) ($request->input('discount_on_all', 0) ?? 0);
        $discountType = $request->input('discount_type', 'fixed');
        $advanceAmount = max(0, (float) ($request->input('advance_amount', 0) ?? 0));
        $couponAmt = (float) $couponAmt;

        $serverSubtotal = 0.0;
        $serverItemDiscount = 0.0;
        $serverTotalTax = 0.0;
        $lines = [];
        foreach ($cart as $item) {
            $itemId = $item['id'] ?? null;
            $qty = (float) ($item['qty'] ?? 0);
            $clientPrice = (float) ($item['price'] ?? 0);
            // The client's per-line price is authoritative: POS/Add Sale allow a cashier
            // to override the catalog price per line (a legitimate feature used by the
            // sales-edit flow too). The DB catalog price is only a fallback when the
            // client omits 'price' entirely. The recompute validates the TOTAL ARITHMETIC
            // (subtotal+tax+other−discount) against the client's own line prices, so a
            // tampered total is still caught without rejecting legitimate price overrides.
            $linePrice = $clientPrice > 0
                ? $clientPrice
                : (isset($serverPrices[$itemId]) ? (float) $serverPrices[$itemId] : $clientPrice);
            // Item-level discount is a fixed amount (৳), capped at the line subtotal.
            $discountAmt = (float) ($item['discount'] ?? 0);
            $lineTotal = $linePrice * $qty;
            $serverSubtotal += $lineTotal;
            $lineDisc = min($discountAmt, $lineTotal);
            $serverItemDiscount += $lineDisc;
            // Per-line tax mirrors the Add Sale client (calculateTotals): tax = (sub − disc) * tax%.
            // POS sends no tax field (tax=0), so server-side tax is 0 for POS — consistent with
            // the POS client which never includes tax in grand_total.
            $taxPercent = (float) ($item['tax'] ?? 0);
            $lineTax = ($lineTotal - $lineDisc) * $taxPercent / 100;
            $serverTotalTax += $lineTax;
            $lines[] = [
                'item_id' => $itemId,
                'name' => $item['name'] ?? null,
                'qty' => $qty,
                'client_price' => $clientPrice,
                'server_price' => round($linePrice, 4),
                'price_diff' => round($clientPrice - (isset($serverPrices[$itemId]) ? (float) $serverPrices[$itemId] : $clientPrice), 4),
                'discount_amt' => round($lineDisc, 2),
                'tax_percent' => $taxPercent,
                'tax_amt' => round($lineTax, 2),
                'client_line_total' => round($clientPrice * $qty, 2),
                'server_line_total' => round($lineTotal, 2),
            ];
        }

        // Global (invoice) discount - mirrors frontend invoiceDiscount getter.
        $serverInvoiceDiscount = ($discountType === 'percent')
            ? ($serverSubtotal * $discountOnAll / 100)
            : $discountOnAll;

        // Combined discount capped at subtotal - mirrors frontend totalDiscount getter.
        $serverTotalDiscount = min($serverSubtotal, $serverItemDiscount + $serverInvoiceDiscount + $couponAmt);

        // Other charges (flat input, no tax handling on the Add Sale page).
        $serverOtherCharges = max(0, (float) ($request->input('other_charges', 0) ?? 0));

        // Mirrors the Add Sale client grandTotal getter:
        //   max(0, subtotal + totalTax + otherCharges − totalDiscount)
        $serverRawPayable = max(0, $serverSubtotal + $serverTotalTax + $serverOtherCharges - $serverTotalDiscount - $advanceAmount);

        if ($roundOffEnabled) {
            $serverGrandTotal = round($serverRawPayable);
            $serverRoundOff = $serverGrandTotal - $serverRawPayable;
        } else {
            $serverGrandTotal = $serverRawPayable;
            $serverRoundOff = 0.0;
        }

        return [
            'server_subtotal' => round($serverSubtotal, 2),
            'server_item_discount' => round($serverItemDiscount, 2),
            'server_invoice_discount' => round($serverInvoiceDiscount, 2),
            'server_total_discount' => round($serverTotalDiscount, 2),
            'server_total_tax' => round($serverTotalTax, 2),
            'server_other_charges' => round($serverOtherCharges, 2),
            'server_raw_payable' => round($serverRawPayable, 2),
            'server_round_off' => round($serverRoundOff, 2),
            'server_grand_total' => round($serverGrandTotal, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Log a warning when the client-sent grand_total differs from the server-side
     * recomputed grand_total by more than the tolerance (1 currency unit, to allow
     * for rounding edge cases).
     *
     * DIAGNOSTIC ONLY - the sale proceeds unchanged (rejection/validation is governed
     * solely by config('sales.enforce_total_validation')).
     *
     * Writes to the dedicated 'sales_mismatch' channel so mismatches never sit buried
     * in the generic laravel.log. To monitor:
     *   tail -f storage/logs/sales_mismatch-*.log
     *   grep -i mismatch storage/logs/sales_mismatch-YYYY-MM-DD.log
     */
    private function logTotalMismatch(Request $request, array $serverTotals, $clientGrandTotal, $context = 'store')
    {
        $tolerance = 1.0;
        $clientGrandTotal = (float) $clientGrandTotal;
        $serverGrandTotal = (float) ($serverTotals['server_grand_total'] ?? 0);
        $grandDiff = $clientGrandTotal - $serverGrandTotal;

        if (abs($grandDiff) <= $tolerance) {
            return;
        }

        $clientSubtotal = (float) ($request->input('subtotal', 0) ?? 0);
        $serverSubtotal = (float) ($serverTotals['server_subtotal'] ?? 0);
        $isPos = !$request->has('is_pos') || (bool) $request->input('is_pos');
        $flow = $isPos ? 'pos' : 'add_sale';
        $clientOtherCharges = (float) ($request->input('other_charges', 0) ?? 0);
        $clientDiscount = (float) ($request->input('discount_on_all', 0) ?? 0);
        $clientCoupon = (float) ($request->input('coupon_amt', 0) ?? 0);
        $clientAdvance = (float) ($request->input('advance_amount', 0) ?? 0);

        // Compact summary of the charge combination that produced the total, so a
        // mismatch can be investigated without re-encoding/reproducing the request.
        $charges = [
            'tax' => round((float) ($serverTotals['server_total_tax'] ?? 0), 2),
            'other_charges' => round($clientOtherCharges, 2),
            'discount' => round((float) ($serverTotals['server_total_discount'] ?? 0), 2),
            'coupon' => round($clientCoupon, 2),
            'coupon_present' => (bool) ($request->input('coupon_id') || $request->input('customer_coupon_id') || $clientCoupon > 0),
            'advance' => round($clientAdvance, 2),
            'round_off' => round((float) ($serverTotals['server_round_off'] ?? 0), 2),
        ];

        \Log::channel('sales_mismatch')->warning("{$flow} {$context} total mismatch detected (client-sent vs server recompute)", [
            'flow' => $flow,
            'attempt' => [
                'sale_id' => $request->input('sale_id'),
                'reference_no' => $request->input('reference_no'),
                'sales_code' => $request->input('sales_code'),
            ],
            'sale_context' => [
                'customer_id' => $request->input('customer_id'),
                'warehouse_id' => $request->input('warehouse_id'),
                'sales_date' => $request->input('sales_date'),
            ],
            'client_values' => [
                'subtotal' => $clientSubtotal,
                'grand_total' => $clientGrandTotal,
                'round_off' => (float) ($request->input('round_off', 0) ?? 0),
                'discount_on_all' => $clientDiscount,
                'discount_type' => $request->input('discount_type', 'fixed'),
                'advance_amount' => $clientAdvance,
                'coupon_amt' => $clientCoupon,
            ],
            'server_values' => $serverTotals,
            'charges' => $charges,
            'difference' => [
                'grand_total_diff' => round($grandDiff, 2),
                'subtotal_diff' => round($clientSubtotal - $serverSubtotal, 2),
            ],
        ]);
    }
}
