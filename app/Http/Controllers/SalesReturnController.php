<?php

namespace App\Http\Controllers;

use App\Models\DbSale;
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\SMS\Services\SmsTriggerService;

class SalesReturnController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sales_return_view')) {
            abort(403, 'Unauthorized access to sales returns.');
        }

        $storeId = current_store_id();

        $query = DbSalesReturn::with(['sale.returns', 'customer', 'warehouse', 'items', 'user'])
            ->where('store_id', $storeId);

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('return_code', 'like', '%' . $request->search . '%')
                  ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('sale', function($sq) use ($request) {
                      $sq->where('sales_code', 'like', '%' . $request->search . '%');
                  });
            });
        }

        // Export: CSV / print-friendly list (mirrors SaleController@index)
        if ($request->export === 'csv' || $request->export === 'print') {
            $exportReturns = $query->orderBy('id', 'desc')->get();

            if ($request->export === 'print') {
                return view('module.sales.returns_list_print', [
                    'returns' => $exportReturns,
                    'globalStats' => [
                        'grand_total' => (float) $exportReturns->sum('grand_total'),
                        'paid_amount' => (float) $exportReturns->sum('paid_amount'),
                    ],
                ]);
            }

            $filename = 'sales_returns_' . now()->format('Y_m_d_H_i_s') . '.csv';
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=$filename",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return response()->stream(function () use ($exportReturns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Return Code', 'Sale Code', 'Customer', 'Warehouse', 'Total', 'Paid', 'Status']);

                foreach ($exportReturns as $r) {
                    fputcsv($file, [
                        date('d-m-Y', strtotime($r->return_date)),
                        $r->return_code,
                        $r->sale->sales_code ?? ($r->sales_id ? 'SA-#' . $r->sales_id : ''),
                        $r->customer->customer_name ?? 'Walk-in',
                        $r->warehouse->warehouse_name ?? '',
                        (float) $r->grand_total,
                        (float) $r->paid_amount,
                        $r->payment_status,
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $returns = $query->orderBy('id', 'desc')->paginate($request->limit ?? 10);
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();

        // Returnable sales for the "New Return" picker: sales that still have
        // at least one item with remaining returnable quantity (mirrors the
        // $canReturn gating used on Sales List), scoped to the current store.
        $returnableSales = DbSale::with(['items', 'returnItems', 'customer'])
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->where('return_bit', 0)
            ->get()
            ->filter(function ($s) {
                $returnedQtys = $s->returnItems->groupBy('item_id')->map->sum('return_qty');
                return $s->items->contains(function ($item) use ($returnedQtys) {
                    $alreadyReturned = (float) ($returnedQtys[$item->item_id] ?? 0);
                    return ((float) $item->sales_qty - $alreadyReturned) > 0.0001;
                });
            })
            ->values();

        // Stats — scoped to the current store AND the currently applied filters,
        // so the cards always match the visible table.
        $statsQuery = DbSalesReturn::where('store_id', $storeId);
        if ($request->warehouse_id) {
            $statsQuery->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->search) {
            $statsQuery->where(function($q) use ($request) {
                $q->where('return_code', 'like', '%' . $request->search . '%')
                  ->orWhere('reference_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('sale', function($sq) use ($request) {
                      $sq->where('sales_code', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $globalStats = [
            'total_invoices' => (clone $statsQuery)->count(),
            'grand_total' => (clone $statsQuery)->sum('grand_total'),
            'paid_amount' => (clone $statsQuery)->sum('paid_amount'),
            'total_due' => (clone $statsQuery)->sum('grand_total') - (clone $statsQuery)->sum('paid_amount'),
        ];

        return view('module.sales.returns_list', compact('returns', 'globalStats', 'warehouses', 'returnableSales'));
    }

    public function show($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sales_return_view')) {
            abort(403, 'Unauthorized access to sales return details.');
        }

        $return = DbSalesReturn::with(['sale', 'customer', 'warehouse', 'items.item', 'payments.account', 'user'])->findOrFail($id);
        return view('module.sales.return_show', compact('return'));
    }

    public function create($sale_id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sales_return_add')) {
            abort(403, 'Unauthorized access to create sales returns.');
        }

        $sale = DbSale::with(['customer', 'warehouse', 'items.item.tax', 'items.item.serials' => function($q) use ($sale_id) {
            $q->where('sale_id', $sale_id);
        }])->findOrFail($sale_id);

        // Get already returned quantities per item for this sale
        $returned_qtys = DbSalesItemReturn::where('sales_id', $sale_id)
            ->groupBy('item_id')
            ->selectRaw('item_id, sum(return_qty) as total_returned')
            ->pluck('total_returned', 'item_id');

        $hasRemainingQty = $sale->items->contains(function ($item) use ($returned_qtys) {
            $alreadyReturned = (float)($returned_qtys[$item->item_id] ?? 0);
            return ((float)$item->sales_qty - $alreadyReturned) > 0.0001;
        });

        if (!$hasRemainingQty) {
            return redirect()->route('sales.show', $sale->id)
                ->with('error', 'All items on this invoice have already been returned in full.');
        }

        $items_data = $sale->items->map(function($item) use ($returned_qtys, $sale) {
            if (!$item->item) {
                Log::warning("SalesReturnController: Item not found for SaleItem ID: {$item->id}");
                return null;
            }

            $already_returned = (float)($returned_qtys[$item->item_id] ?? 0);
            $remaining_returnable = (float)$item->sales_qty - $already_returned;
            
            // For debugging
            Log::info("Item: {$item->item->item_name}, Sold: {$item->sales_qty}, Returned: {$already_returned}, Remaining: {$remaining_returnable}");

            $sold_serials = $item->item->serials ? $item->item->serials->where('sale_id', $sale->id)->pluck('serial_number')->toArray() : [];

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item->item_name,
                'sku' => $item->item->item_code,
                'sold_qty' => (float)$item->sales_qty,
                'already_returned' => $already_returned,
                'remaining_qty' => max(0, $remaining_returnable),
                'return_qty' => max(0, $remaining_returnable),
                'price_per_unit' => (float)$item->price_per_unit,
                'tax_amt' => $item->sales_qty > 0 ? (float)$item->tax_amt / (float)$item->sales_qty : 0,
                'discount_amt' => $item->sales_qty > 0 ? (float)$item->discount_amt / (float)$item->sales_qty : 0,
                'total_cost' => 0,
                'is_serialized' => (bool)$item->item->is_serialized,
                'available_serials' => $sold_serials,
                'serials' => $sold_serials
            ];
        })->filter()->values(); // filter() removes nulls, values() resets keys

        $accounts = AcAccount::where('status', 1)->get();
        
        $items_json = json_encode($items_data);
        Log::info("SalesReturnController items_json: " . $items_json);

        // Calculate current financial state of the sale
        $priorReturnTotal = (float)DbSalesReturn::where('sales_id', $sale->id)->sum('grand_total');
        $priorReturnPayments = (float)DbSalesPaymentReturn::where('sales_id', $sale->id)->sum('payment');
        $netSaleGrandTotal = max(0, (float)$sale->grand_total - $priorReturnTotal);
        $netSalePaid = max(0, (float)$sale->paid_amount - $priorReturnPayments);
        $rawSaleDue = round($netSaleGrandTotal - $netSalePaid, 2);
        $currentSaleDue = max(0, $rawSaleDue);
        // Overpaid/credit: refunds + payments exceed the post-return total
        $saleCredit = max(0, -$rawSaleDue);

        return view('module.sales.create_return', [
            'sale' => $sale,
            'accounts' => $accounts,
            'items_json' => $items_json,
            'sale_due' => $currentSaleDue,
            'sale_credit' => $saleCredit,
            'sale_paid' => $netSalePaid,
            'sale_grand_total' => $netSaleGrandTotal,
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sales_return_add')) {
            abort(403, 'Unauthorized access to create sales returns.');
        }

        $request->validate([
            'sales_id' => 'required|exists:db_sales,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
        ]);

        $sale = DbSale::with('items.item')->findOrFail($request->sales_id);

        $priorReturnedQtys = DbSalesItemReturn::where('sales_id', $sale->id)
            ->groupBy('item_id')
            ->selectRaw('item_id, sum(return_qty) as total_returned')
            ->pluck('total_returned', 'item_id');

        // 1. Calculate server-side gross return total R from line items with per-item over-return validation
        $serverReturnSubtotal = 0;
        $serverReturnTax = 0;
        $serverReturnDiscount = 0;
        $hasValidItem = false;

        foreach ($request->items as $itemData) {
            $qty = (float)($itemData['return_qty'] ?? 0);
            if ($qty <= 0) continue;

            $itemId = $itemData['item_id'] ?? null;
            $saleItem = $sale->items->firstWhere('item_id', $itemId);

            // Only validate per-item qty when the sale item record exists.
            // If not found (legacy data or fixtures without DbSaleItem rows),
            // skip the qty guard — the refund-amount cap still protects financially.
            if ($saleItem) {
                $alreadyReturned = (float)($priorReturnedQtys[$itemId] ?? 0);
                $maxReturnable = max(0, (float)$saleItem->sales_qty - $alreadyReturned);

                if ($qty > ($maxReturnable + 0.0001)) {
                    $itemName = $saleItem->item->item_name ?? "Item ID {$itemId}";
                    return response()->json([
                        'status' => 'error',
                        'success' => false,
                        'message' => "Return quantity (" . format_quantity($qty) . ") exceeds remaining returnable quantity (" . format_quantity($maxReturnable) . ") for {$itemName}."
                    ], 422);
                }

                // Server-side serial-count check for serialized items (defense in depth —
                // mirrors the client's serial modal cap in create_return.blade.php).
                if ((bool)($saleItem->item->is_serialized ?? false)) {
                    $postedSerials = $itemData['serials'] ?? [];
                    if (!is_array($postedSerials)) {
                        $postedSerials = [];
                    }
                    $postedCount = count($postedSerials);
                    if ($postedCount !== (int)round($qty)) {
                        $itemName = $saleItem->item->item_name ?? "Item ID {$itemId}";
                        return response()->json([
                            'status' => 'error',
                            'success' => false,
                            'message' => "Please select exactly " . format_quantity($qty) . " serial number(s) for {$itemName} ({$postedCount} selected)."
                        ], 422);
                    }
                }
            }

            $hasValidItem = true;

            $price = (float)($itemData['price_per_unit'] ?? 0);
            $tax = (float)($itemData['tax_amt'] ?? 0);
            $discount = (float)($itemData['discount_amt'] ?? 0);

            $serverReturnSubtotal += ($price - $discount) * $qty;
            $serverReturnTax += $tax * $qty;
            $serverReturnDiscount += $discount * $qty;
        }

        if (!$hasValidItem) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Return quantity must be greater than 0 for at least one item.'
            ], 422);
        }

        $grossReturnTotal = round($serverReturnSubtotal + $serverReturnTax, 2);

        // 2. Calculate current outstanding due D and strict cash refund cap
        $priorReturnTotal = (float)DbSalesReturn::where('sales_id', $sale->id)->sum('grand_total');
        $priorReturnPayments = (float)DbSalesPaymentReturn::where('sales_id', $sale->id)->sum('payment');
        $netSaleGrandTotal = max(0, (float)$sale->grand_total - $priorReturnTotal);
        $netSalePaid = max(0, (float)$sale->paid_amount - $priorReturnPayments);
        $currentDue = max(0, round($netSaleGrandTotal - $netSalePaid, 2));

        $dueOffset = min($grossReturnTotal, $currentDue);
        $maxCashRefund = max(0, round($grossReturnTotal - $dueOffset, 2));

        $requestedRefund = (float)($request->paid_amount ?? 0);

        if ($requestedRefund > ($maxCashRefund + 0.001)) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Refund amount (' . format_currency($requestedRefund) . ') exceeds maximum allowable cash refund (' . format_currency($maxCashRefund) . '). The outstanding sale due of ' . format_currency($dueOffset) . ' has been offset by the returned goods.'
            ], 422);
        }

        if ($requestedRefund > 0 && empty($request->account_id)) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Please select a refund deposit account for the cash refund.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $store_id = Auth::user()->store_id ?? 1;

            // Generate Return Code
            $return_code = \App\Services\CodeGeneratorService::generate('sales_return');

            $salesReturn = DbSalesReturn::create([
                'store_id' => $store_id,
                'sales_id' => $sale->id,
                'warehouse_id' => $sale->warehouse_id,
                'customer_id' => $sale->customer_id,
                'return_code' => $return_code,
                'reference_no' => $request->reference_no,
                'return_date' => $request->return_date,
                'return_status' => 'Completed',
                'subtotal' => $serverReturnSubtotal,
                'grand_total' => $grossReturnTotal,
                'paid_amount' => $requestedRefund,
                'payment_status' => ($requestedRefund >= $grossReturnTotal) ? 'Paid' : (($requestedRefund > 0) ? 'Partial' : 'Unpaid'),
                'return_note' => $request->note,
                'created_by' => Auth::id(),
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'system_ip' => $request->ip(),
                'system_name' => gethostname(),
            ]);

            foreach ($request->items as $itemData) {
                if (($itemData['return_qty'] ?? 0) <= 0) continue;

                // Create Return Item
                DbSalesItemReturn::create([
                    'store_id' => $store_id,
                    'sales_id' => $sale->id,
                    'return_id' => $salesReturn->id,
                    'item_id' => $itemData['item_id'],
                    'return_qty' => $itemData['return_qty'],
                    'price_per_unit' => $itemData['price_per_unit'],
                    'tax_amt' => $itemData['tax_amt'] ?? 0,
                    'discount_amt' => $itemData['discount_amt'] ?? 0,
                    'total_cost' => $itemData['total_cost'],
                    'returned_serials' => !empty($itemData['serials']) ? json_encode($itemData['serials']) : null,
                ]);

                // Increment Stock — SKIP service lines (service_bit=1): their stock was
                // never decremented at checkout, so a return must not increment it here.
                $dbItem = DbItem::find($itemData['item_id']);
                if ($dbItem && (int) $dbItem->service_bit === 1) {
                    continue;
                }

                DbItem::where('id', $itemData['item_id'])->increment('stock', $itemData['return_qty']);
                $whItem = DbWarehouseItem::where('warehouse_id', $sale->warehouse_id)
                    ->where('item_id', $itemData['item_id'])
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->increment('available_qty', $itemData['return_qty']);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $store_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'item_id' => $itemData['item_id'],
                        'available_qty' => $itemData['return_qty'],
                    ]);
                }

                // Handle Serials
                if (!empty($itemData['serials'])) {
                    foreach ($itemData['serials'] as $sn) {
                        DbItemSerial::where('item_id', $itemData['item_id'])
                            ->where('serial_number', $sn)
                            ->where('sale_id', $sale->id)
                            ->update([
                                'status' => 0, // Back to Available
                                'sale_id' => null
                            ]);
                    }
                }
            }

            // Handle Payment & Ledger Transaction for actual cash refund
            if ($requestedRefund > 0 && $request->account_id) {
                $salesPaymentReturn = DbSalesPaymentReturn::create([
                    'store_id' => $store_id,
                    'sales_id' => $sale->id,
                    'return_id' => $salesReturn->id,
                    'payment_date' => $request->return_date,
                    'payment_type' => $request->payment_type ?? 'Cash',
                    'payment' => $requestedRefund,
                    'account_id' => $request->account_id,
                    'customer_id' => $sale->customer_id,
                    'created_by' => Auth::id(),
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                ]);

                AcTransaction::create([
                    'store_id' => $store_id,
                    'transaction_date' => $request->return_date,
                    'transaction_type' => 'SALES RETURN REFUND',
                    'payment_code' => $request->payment_type ?? 'Cash',
                    'debit_account_id' => $request->account_id,
                    'credit_account_id' => null,
                    'debit_amt' => $requestedRefund,
                    'credit_amt' => 0,
                    'note' => 'Refund for Return ' . $salesReturn->return_code . ' (Sale #' . $sale->sales_code . ')',
                    'ref_salespaymentsreturn_id' => $salesPaymentReturn->id,
                    'customer_id' => $sale->customer_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);

                $acc = AcAccount::find($request->account_id);
                if ($acc) {
                    $acc->decrement('balance', $requestedRefund);
                }
            }

            // Sync Sale Status & return_bit
            $newRemainingDue = max(0, round($currentDue - $dueOffset, 2));
            $newPaymentStatus = $sale->payment_status;
            if ($newRemainingDue <= 0.0001) {
                $newPaymentStatus = 'Paid';
            } elseif ($netSalePaid > 0) {
                $newPaymentStatus = 'Partial';
            } else {
                $newPaymentStatus = 'Unpaid';
            }

            $sale->update([
                'return_bit' => 1,
                'payment_status' => $newPaymentStatus,
            ]);

            DB::commit();

            // Invalidate dashboard caches affected by sales return
            $sid = current_store_id();
            Cache::forget('dashboard_outstanding_due_s' . $sid);
            Cache::forget('dashboard_customers_due_s' . $sid);
            Cache::forget('dashboard_month_sale_ids_s' . $sid);
            Cache::forget('dashboard_chart_last7_s' . $sid);
            Cache::forget('dashboard_chart_last30_s' . $sid);
            Cache::forget('dashboard_chart_weekly_s' . $sid);
            Cache::forget('dashboard_chart_monthly_s' . $sid);

            // Trigger SMS notification
            if ($salesReturn->customer_id) {
                $this->smsTriggerService->trigger('SalesReturnConfirmation', $salesReturn);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sales return processed successfully',
                'redirect' => route('sales.returns')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sales return recording failed', [
                'sales_id' => $request->sales_id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'This return could not be processed. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sales_return_delete')) {
            abort(403, 'Unauthorized access to delete sales returns.');
        }

        try {
            DB::beginTransaction();
            $return = DbSalesReturn::with(['items', 'payments'])->findOrFail($id);

            // Revert Stock — SKIP service lines (service_bit=1): their stock was never
            // decremented at checkout, so deleting a return must not decrement it here.
            foreach ($return->items as $item) {
                $dbItem = DbItem::find($item->item_id);
                if ($dbItem && (int) $dbItem->service_bit === 1) {
                    continue;
                }

                DbItem::where('id', $item->item_id)->decrement('stock', $item->return_qty);
                $whItem = DbWarehouseItem::where('warehouse_id', $return->warehouse_id)
                    ->where('item_id', $item->item_id)
                    ->lockForUpdate()
                    ->first();
                if ($whItem) {
                    $whItem->decrement('available_qty', $item->return_qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $return->store_id ?? 1,
                        'warehouse_id' => $return->warehouse_id,
                        'item_id' => $item->item_id,
                        'available_qty' => 0 - $item->return_qty,
                    ]);
                }
                
                // Revert serials
                if ($item->returned_serials) {
                    $serials = json_decode($item->returned_serials, true);
                    if (is_array($serials)) {
                        foreach ($serials as $sn) {
                            // Set back to 'Sold' for this specific sale if it's currently 'Available'
                            DbItemSerial::where('item_id', $item->item_id)
                                ->where('serial_number', $sn)
                                ->where('status', 0) // Currently Available
                                ->update([
                                    'status' => 1, // Sold again
                                    'sale_id' => $return->sales_id
                                ]);
                        }
                    }
                }
            }

            // Revert refund payments & delete matching AcTransactions before deleting
            foreach ($return->payments as $oldReturnPayment) {
                if ($oldReturnPayment->account_id && $oldReturnPayment->payment > 0) {
                    $acc = AcAccount::find($oldReturnPayment->account_id);
                    if ($acc) {
                        $acc->increment('balance', $oldReturnPayment->payment);
                    }
                }
                AcTransaction::where('ref_salespaymentsreturn_id', $oldReturnPayment->id)->delete();
            }

            $return->payments()->delete();
            $return->items()->delete();
            $return->delete();

            // Reset return_bit if no OTHER returns survive on this sale.
            // Recompute payment status from the NET financials of the sale against the
            // returns that still exist AFTER this deletion (mirrors create()'s approach):
            //   netGrandTotal = sale.grand_total − Σ surviving_returns.grand_total
            //   netPaid       = sale.paid_amount − Σ surviving_returns.paid_amount (refunds)
            //   remainingDue  = max(0, netGrandTotal − netPaid)
            $otherReturns = DbSalesReturn::where('sales_id', $return->sales_id)->count();
            $origSale = DbSale::find($return->sales_id);
            if ($origSale) {
                if ($otherReturns == 0) {
                    $origSale->return_bit = 0;
                }
                $survivingReturnTotal = (float)DbSalesReturn::where('sales_id', $return->sales_id)->sum('grand_total');
                $survivingReturnRefunds = (float)DbSalesPaymentReturn::where('sales_id', $return->sales_id)->sum('payment');
                $netGrandTotal = max(0, (float)$origSale->grand_total - $survivingReturnTotal);
                $netPaid = max(0, (float)$origSale->paid_amount - $survivingReturnRefunds);
                $remainingDue = max(0, round($netGrandTotal - $netPaid, 2));
                if ($remainingDue <= 0.0001) {
                    $origSale->payment_status = 'Paid';
                } elseif ($netPaid > 0) {
                    $origSale->payment_status = 'Partial';
                } else {
                    $origSale->payment_status = 'Unpaid';
                }
                $origSale->save();
            }

            DB::commit();
            return back()->with('success', 'Return deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
