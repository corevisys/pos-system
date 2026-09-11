<?php

namespace App\Http\Controllers;

use App\Models\DbSale;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\User;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Support\Facades\Auth;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCategory;
use App\Models\DbBrand;
use App\Models\DbTax;
use App\Models\DbPaymentType;

class SaleController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function create()
    {
        $customers = DbCustomer::where('status', 1)->select('id', 'customer_name', 'customer_type')->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->select('id', 'warehouse_name')->get();
        $accounts = AcAccount::where('status', 1)->select('id', 'account_name')->get();
        
        $categories = store_scoped_cached_list('db_categories_list', 3600, function () {
            return DbCategory::where('status', 1)->select('id', 'category_name')->get();
        });

        $brands = store_scoped_cached_list('db_brands_list', 3600, function () {
            return DbBrand::where('status', 1)->select('id', 'brand_name')->get();
        });
        
        // Phase 1.4: per-store tax cache key (was one global 'db_taxes_list' key,
        // so a tax added by any store leaked into every store's dropdown until it
        // expired) and store-scoped "current OR null" read.
        $taxStoreId = current_store_id();
        $taxes = Cache::remember('db_taxes_list_' . $taxStoreId, 3600, function () use ($taxStoreId) {
            return DbTax::where('status', 1)
                ->where(fn($w) => $w->where('store_id', $taxStoreId)->orWhereNull('store_id'))
                ->select('id', 'tax_name', 'tax')->get();
        });
        
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->select('id', 'payment_type')->get();
        
        $nextSalesCode = \App\Services\CodeGeneratorService::generate('sales');
        
        return view('module.sales.add', compact('customers', 'warehouses', 'accounts', 'categories', 'brands', 'taxes', 'paymentTypes', 'nextSalesCode'));
    }

    public function store(Request $request)
    {
        return app(PosController::class)->store($request);
    }

    public function edit($id)
    {
        $sale = DbSale::with(['items.item.tax', 'customer', 'warehouse'])->findOrFail($id);
        $customers = DbCustomer::where('status', 1)->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $accounts = AcAccount::where('status', 1)->get();
        $categories = DbCategory::where('status', 1)->get();
        $brands = DbBrand::where('status', 1)->get();
        $taxes = DbTax::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->get();
        $paymentTypes = DbPaymentType::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->get();

        return view('module.sales.edit', compact('sale', 'customers', 'warehouses', 'accounts', 'categories', 'brands', 'taxes', 'paymentTypes'));
    }

    public function update(Request $request, $id)
    {
        $request->merge(['sale_id' => $id]);
        return app(PosController::class)->store($request);
    }

    public function storeEmi(Request $request)
    {
        return app(PosController::class)->storeEmi($request);
    }

    public function index(Request $request)
    {
        $storeId = current_store_id();
        $query = DbSale::with(['customer', 'warehouse', 'items', 'serials', 'payments', 'user', 'emi', 'returnItems', 'returns'])
            ->where('store_id', $storeId);

        // Filters
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->created_by) {
            $query->where('created_by', $request->created_by);
        }
        if ($request->from_date) {
            $query->whereDate('sales_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('sales_date', '<=', $request->to_date);
        }
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('sales_code', 'like', '%' . $request->search . '%')
                  ->orWhere('reference_no', 'like', '%' . $request->search . '%');
            });
        }

        // Print-friendly filtered list (browser print / Save-as-PDF via the print dialog)
        if ($request->export === 'print' || $request->export === 'pdf') {
            $printSales = $query->orderBy('id', 'desc')->get();

            return view('module.sales.list_print', [
                'sales' => $printSales,
                'filteredStats' => [
                    'total_amount' => (float) $printSales->sum('grand_total'),
                    'total_paid' => (float) $printSales->sum('paid_amount'),
                ],
            ]);
        }

        // Export current filtered result set as CSV (shared pattern used by SmsLogController)
        if ($request->export === 'csv') {
            $exportSales = $query->orderBy('id', 'desc')->get();
            $filename = "sales_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportSales) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Due Date', 'Sales Code', 'Ref No.', 'Customer', 'Total', 'Paid', 'Status', 'Created By']);

                foreach ($exportSales as $sale) {
                    fputcsv($file, [
                        date('d-m-Y', strtotime($sale->sales_date)),
                        $sale->due_date ? date('d-m-Y', strtotime($sale->due_date)) : '',
                        $sale->sales_code,
                        $sale->reference_no ?? '',
                        $sale->customer->customer_name ?? 'Walk-in customer',
                        (float) $sale->grand_total,
                        (float) $sale->paid_amount,
                        $sale->payment_status,
                        $sale->user->name ?? 'System',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $sales = $query->orderBy('id', 'desc')->paginate($request->limit ?? 10);

        // Header Stats (Global) — scoped to the current store
        $storeBase = DbSale::where('store_id', $storeId);
        $globalStats = [
            'total_invoices' => (clone $storeBase)->count(),
            'total_amount' => (clone $storeBase)->sum('grand_total'),
            'total_paid' => (clone $storeBase)->sum('paid_amount'),
            'total_due' => (clone $storeBase)->sum('grand_total') - (clone $storeBase)->sum('paid_amount'),
        ];

        // Filtered Stats (for Table Footer)
        $filteredQuery = clone $query->getQuery();
        $filteredStats = [
            'total_amount' => $filteredQuery->sum('grand_total'),
            'total_paid' => $filteredQuery->sum('paid_amount'),
        ];

        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $customers = DbCustomer::all();
        $users = User::all();

        return view('module.sales.list', [
            'sales' => $sales,
            'stats' => $globalStats,
            'filteredStats' => $filteredStats,
            'warehouses' => $warehouses,
            'customers' => $customers,
            'users' => $users
        ]);
    }

    public function show($id)
    {
        $sale = DbSale::with([
            'customer',
            'warehouse',
            'items.item.tax',
            'items.item.unit',
            'items.item.variant',
            'serials',
            'payments.account',
            'user',
            'emi',
            'returns.items.item.variant',
            'returns.payments.account',
            'returns.user',
            'returnItems'
        ])->findOrFail($id);
        
        if ($sale->emi) {
            return redirect()->route('sales.emi.show', $sale->emi->id);
        }

        $store = \App\Models\DbStore::findOrFail($sale->store_id);
        $amount_in_words = $this->convertNumberToWords($sale->grand_total);
        
        return view('module.sales.show', compact('sale', 'store', 'amount_in_words'));
    }

    private function convertNumberToWords($number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = array(
            '0' => '', '1' => 'one', '2' => 'two',
            '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
            '7' => 'seven', '8' => 'eight', '9' => 'nine',
            '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
            '13' => 'thirteen', '14' => 'fourteen',
            '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
            '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
            '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
            '60' => 'sixty', '70' => 'seventy',
            '80' => 'eighty', '90' => 'ninety'
        );
        $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else $str[] = null;
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        
        $points = '';
        if ($decimal > 0) {
            $points = " and " . ($decimal < 21 ? $words[$decimal] : $words[floor($decimal / 10) * 10] . " " . $words[$decimal % 10]) . " Paisa";
        }
        
        return ucfirst(trim($result)) . " Taka Only" . $points;
    }

    public function destroy($id)
    {
        $storeId = current_store_id();

        try {
            DB::beginTransaction();
            $sale = DbSale::with(['items', 'payments', 'emi', 'returnItems'])->findOrFail($id);

            // Block deletion of EMI sales — there is no EMI cancellation flow in the
            // app, so silently relying on the DB cascade would destroy the EMI schedule
            // and payment history with no way to recover it.
            if ($sale->emi) {
                DB::rollBack();
                return back()->with('error', 'This sale has an EMI schedule and cannot be deleted.');
            }

            // Quantities already restored by prior Sales Returns must not be restored again.
            $returnedQtys = $sale->returnItems
                ? $sale->returnItems->groupBy('item_id')->map->sum('return_qty')
                : collect();

            // Restore only the remaining, not-yet-returned quantity per item — SKIP
            // service lines (service_bit=1): their stock was never decremented at
            // checkout, so it must not be incremented back on delete either.
            foreach ($sale->items as $item) {
                $alreadyReturned = (float) ($returnedQtys[$item->item_id] ?? 0);
                $remainingQty = max(0, (float) $item->sales_qty - $alreadyReturned);

                if ($remainingQty <= 0.0001) {
                    continue;
                }

                $dbItem = DbItem::find($item->item_id);
                if ($dbItem && (int) $dbItem->service_bit === 1) {
                    continue;
                }

                // Main stock
                DbItem::where('id', $item->item_id)->increment('stock', $remainingQty);

                // Warehouse stock
                DbWarehouseItem::where('warehouse_id', $sale->warehouse_id)
                    ->where('item_id', $item->item_id)
                    ->increment('available_qty', $remainingQty);

                // Restore Serials — only those still assigned to this sale (returned
                // serials were already freed by the return flow, so sale_id is null).
                DbItemSerial::where('sale_id', $sale->id)
                    ->where('item_id', $item->item_id)
                    ->update(['status' => 0, 'sale_id' => null]);
            }

            // Reverse ledger entries + account balances before deleting payments
            // (mirrors the edit path in PosController@store).
            foreach ($sale->payments as $payment) {
                if ($payment->account_id && $payment->payment > 0) {
                    $acc = AcAccount::find($payment->account_id);
                    if ($acc) {
                        $acc->decrement('balance', $payment->payment);
                    }
                }
                AcTransaction::where('ref_salespayments_id', $payment->id)->delete();
            }

            // Delete payments
            $sale->payments()->delete();
            
            // Delete items
            $sale->items()->delete();

            // Delete sale
            $sale->delete();

            DB::commit();

            \App\Http\Controllers\DashboardController::clearDashboardCache($storeId);

            return back()->with('success', 'Sale deleted successfully and stock restored.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale deletion failed', ['sale_id' => $id, 'error' => $e->getMessage()]);
            return back()->with('error', 'This sale could not be deleted. Please try again.');
        }
    }
    public function emiList(Request $request)
    {
        $storeId = current_store_id();

        // Store scoping (A6): db_emi_sales has no store_id column, so scope through
        // the parent db_sales row — same convention as Sales List/Payments/Returns.
        $query = \App\Models\DbEmiSale::with(['sale', 'customer', 'schedule'])
            ->whereHas('sale', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });

        // Filters
        if ($request->warehouse_id) {
            $query->whereHas('sale', function($q) use ($request) {
                $q->where('warehouse_id', $request->warehouse_id);
            });
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->created_by) {
            $query->whereHas('sale', function($q) use ($request) {
                $q->where('created_by', $request->created_by);
            });
        }
        if ($request->from_date) {
            $query->whereDate('start_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('start_date', '<=', $request->to_date);
        }
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('sale', function($sq) use ($request) {
                    $sq->where('sales_code', 'like', '%' . $request->search . '%');
                })->orWhereHas('customer', function($cq) use ($request) {
                    $cq->where('customer_name', 'like', '%' . $request->search . '%')
                      ->orWhere('mobile', 'like', '%' . $request->search . '%');
                });
            });
        }

        // A8a: Export current filtered result set as CSV / print-friendly list
        // (reuses the established pattern from Sales List / Returns).
        if ($request->export === 'csv' || $request->export === 'print') {
            $exportEmiSales = $query->orderBy('id', 'desc')->get();

            if ($request->export === 'print') {
                return view('module.sales.emi_list_print', [
                    'emiSales' => $exportEmiSales,
                    'globalStats' => [
                        'total_loan' => (float) $exportEmiSales->sum('loan_amount'),
                        'total_payable' => (float) $exportEmiSales->sum('total_payable'),
                    ],
                ]);
            }

            $filename = 'emi_sales_' . now()->format('Y_m_d_H_i_s') . '.csv';
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=$filename",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return response()->stream(function () use ($exportEmiSales) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Start Date', 'Sales Code', 'Customer', 'Loan Amount', 'Total Payable', 'Duration', 'Status']);

                foreach ($exportEmiSales as $emi) {
                    fputcsv($file, [
                        date('d-m-Y', strtotime($emi->start_date)),
                        $emi->sale->sales_code ?? '',
                        $emi->customer->customer_name ?? 'Walk-in',
                        (float) $emi->loan_amount,
                        (float) $emi->total_payable,
                        $emi->duration_months . ' Months',
                        $emi->status,
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $limit = $request->limit ?? 10;
        $emiSales = $query->orderBy('id', 'desc')->paginate($limit);

        // Stats (Global) — store-scoped (A6)
        $storeBase = \App\Models\DbEmiSale::whereHas('sale', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        });
        $globalStats = [
            'total_invoices' => (clone $storeBase)->count(),
            'total_loan' => (clone $storeBase)->sum('loan_amount'),
            'total_payable' => (clone $storeBase)->sum('total_payable'),
            'total_due' => (clone $storeBase)->get()->sum(function ($emi) {
                return (float) $emi->total_payable - (float) $emi->schedule->sum('paid_amount');
            }),
            'total_overdue' => \App\Models\DbEmiSchedule::whereHas('emiSale.sale', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->where('status', '!=', 'Paid')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count(),
        ];

        $warehouses = \App\Models\DbWarehouse::all();
        $customers = \App\Models\DbCustomer::all();
        $users = \App\Models\User::all();

        // A7: preserve active filters across pagination (same convention as Sales List).
        $emiSales->appends(request()->query());

        return view('module.sales.emi_sale_list', compact('emiSales', 'globalStats', 'warehouses', 'customers', 'users'));
    }

    public function emiShow($id)
    {
        $emiSale = \App\Models\DbEmiSale::with(['sale.warehouse', 'sale.items.item', 'sale.serials', 'customer', 'schedule'])->findOrFail($id);
        $accounts = \App\Models\AcAccount::where('status', 1)->get();
        $amount_in_words = $this->convertNumberToWords($emiSale->total_payable);

        // A5: render-time overdue data — computed against today, never stored, so it
        // can't drift. The schedule is already eager-loaded; derive the overdue count.
        $today = now()->toDateString();
        $overdueInstallments = $emiSale->schedule
            ->filter(fn ($s) => $s->status !== 'Paid' && $s->due_date < $today)
            ->values();

        return view('module.sales.emi_details', compact('emiSale', 'accounts', 'amount_in_words', 'overdueInstallments'));
    }

    public function payEmiInstallment(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $schedule = \App\Models\DbEmiSchedule::with('emiSale.sale')->findOrFail($request->schedule_id);
            $payAmount = (float) $request->amount;

            if ($payAmount <= 0) {
                throw new \Exception('Invalid amount');
            }

            // A6: store check — a user must not be able to pay another store's
            // installment by guessing a schedule_id.
            if ($schedule->emiSale->sale->store_id != current_store_id()) {
                throw new \Exception('This installment does not belong to your store.');
            }

            // The ledger write below requires an account — a null account silently
            // skipped the entry before, leaving the payment with no money trail.
            if (!$request->account_id) {
                throw new \Exception('Please select an account to record this EMI installment against.');
            }

            // Upper-bound validation: an installment must never be overpaid, otherwise
            // paid_amount exceeds amount, the status flips to "Paid" and the Remaining
            // Balance on emi_details goes negative (mirrors storePayment's overpay rejection).
            $remainingInstallment = round((float) $schedule->amount - (float) $schedule->paid_amount, 2);
            if ($payAmount > ($remainingInstallment + 0.0001)) {
                throw new \Exception('Payment amount cannot exceed the remaining installment amount (' . format_currency($remainingInstallment) . ').');
            }

            // Update Schedule
            $schedule->paid_amount += $payAmount;
            $schedule->paid_date = date('Y-m-d');
            
            if ($schedule->paid_amount >= $schedule->amount) {
                $schedule->status = 'Paid';
            } else {
                $schedule->status = 'Partial';
            }
            $schedule->save();

            // Create Payment Record
            $payment = \App\Models\DbSalePayment::create([
                'store_id' => Auth::user()->store_id ?? 1,
                'sales_id' => $schedule->emiSale->sale_id,
                'payment_date' => date('Y-m-d'),
                'payment_type' => 'Cash',
                'payment' => $payAmount,
                'payment_note' => 'EMI Installment ' . $schedule->installment_no,
                'created_by' => Auth::id(),
                'account_id' => $request->account_id,
                'customer_id' => $schedule->emiSale->customer_id,
                'emi_schedule_id' => $schedule->id,
            ]);

            // Create Ledger Transaction & Update Account Balance
            if ($payment->account_id && $payment->payment > 0) {
                AcTransaction::create([
                    'store_id' => $payment->store_id ?? 1,
                    'transaction_date' => $payment->payment_date ?? date('Y-m-d'),
                    'transaction_type' => 'SALES PAYMENT',
                    'payment_code' => $payment->payment_type ?? 'Cash',
                    'credit_account_id' => $payment->account_id,
                    'debit_account_id' => null,
                    'debit_amt' => 0,
                    'credit_amt' => $payment->payment,
                    'note' => 'EMI Installment Payment: ' . ($schedule->emiSale->sale->sales_code ?? 'Sale #' . $schedule->emiSale->sale_id),
                    'ref_salespayments_id' => $payment->id,
                    'customer_id' => $payment->customer_id,
                    'created_by' => Auth::id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);

                $acc = AcAccount::find($payment->account_id);
                if ($acc) {
                    $acc->increment('balance', $payment->payment);
                }
            }

            // ROOT-CAUSE FIX (A1): keep the parent sale's paid_amount/payment_status in
            // sync with the installment collected — previously only the schedule + ledger
            // were written, so db_sales.paid_amount stayed at the initial pay forever and
            // Sales List "Due" + dashboard outstanding were permanently wrong.
            $sale = $schedule->emiSale->sale;
            $sale->increment('paid_amount', $payAmount);

            $sale->refresh();
            $paymentStatus = 'Unpaid';
            if ($sale->paid_amount >= $sale->grand_total) {
                $paymentStatus = 'Paid';
            } elseif ($sale->paid_amount > 0) {
                $paymentStatus = 'Partial';
            }
            $sale->update(['payment_status' => $paymentStatus]);

            // Trigger EMI Payment SMS
            $this->smsTriggerService->trigger('EmiPaymentConfirmation', $schedule);

            // Check if all installments are paid to update EMI Sale status
            $pendingCount = \App\Models\DbEmiSchedule::where('emi_sale_id', $schedule->emi_sale_id)
                ->where('status', '!=', 'Paid')
                ->count();
            
            if ($pendingCount == 0) {
                 $schedule->emiSale->update(['status' => 'Completed']);
                 // NOTE: payment_status is already 'Paid' from the recompute above when
                 // all installments are settled (paid_amount >= grand_total); this second
                 // write is kept for parity with the previous behavior.
                 $schedule->emiSale->sale->update(['payment_status' => 'Paid']);
                 
                 // Trigger EMI Completion SMS
                 $this->smsTriggerService->trigger('EmiCompletion', $schedule->emiSale);
            }

            DB::commit();
            return back()->with('success', 'Installment paid successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
    public function paymentsList(Request $request)
    {
        $storeId = current_store_id();

        $query = \App\Models\DbSalePayment::with(['sale', 'customer', 'account', 'user'])
            ->where('store_id', $storeId);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('sale', function ($saleQ) use ($request) {
                    $saleQ->where('sales_code', 'like', '%' . $request->search . '%');
                })->orWhereHas('customer', function ($custQ) use ($request) {
                    $custQ->where('customer_name', 'like', '%' . $request->search . '%')
                          ->orWhere('mobile', 'like', '%' . $request->search . '%');
                });
            });
        }
        
        if ($request->payment_type) {
            $query->where('payment_type', $request->payment_type);
        }

        $payments = $query->orderBy('id', 'desc')->paginate(10);
        
        // Stats — scoped to the current store AND the currently applied filters,
        // so the cards always match the visible table (with no filters applied
        // they equal the store-wide totals).
        $statsQuery = \App\Models\DbSalePayment::where('store_id', $storeId);
        if ($request->search) {
            $statsQuery->where(function ($q) use ($request) {
                $q->whereHas('sale', function ($saleQ) use ($request) {
                    $saleQ->where('sales_code', 'like', '%' . $request->search . '%');
                })->orWhereHas('customer', function ($custQ) use ($request) {
                    $custQ->where('customer_name', 'like', '%' . $request->search . '%')
                          ->orWhere('mobile', 'like', '%' . $request->search . '%');
                });
            });
        }
        if ($request->payment_type) {
            $statsQuery->where('payment_type', $request->payment_type);
        }

        $globalStats = [
             'total_payments' => (clone $statsQuery)->count(),
             'total_amount' => (clone $statsQuery)->sum('payment'),
             'cash_payments' => (clone $statsQuery)->where('payment_type', 'Cash')->sum('payment'),
             'other_payments' => (clone $statsQuery)->where('payment_type', '!=', 'Cash')->sum('payment'),
        ];

        $paymentTypes = \App\Models\DbPaymentType::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->orderBy('payment_type')->get();
        
        return view('module.sales.payments', compact('payments', 'globalStats', 'paymentTypes'));
    }

    public function receivePayment($id)
    {
        $sale = DbSale::with(['customer', 'payments.account', 'warehouse', 'items.item'])->findOrFail($id);
        $accounts = \App\Models\AcAccount::where('status', 1)->get();
        $paymentTypes = \App\Models\DbPaymentType::where('status', 1)
            ->where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->get();
        return view('module.sales.receive_payment', compact('sale', 'accounts', 'paymentTypes'));
    }

    public function storePayment(Request $request)
    {
        $request->validate([
            'sales_id' => 'required|exists:db_sales,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_type' => 'required|string',
            'account_id' => 'required|exists:ac_accounts,id',
        ]);

        $sale = DbSale::findOrFail($request->sales_id);

        // EMI guard: an EMI sale is payable only through its schedule (sales.emi.pay).
        // Allowing the generic "Receive Payment" flow here would create two independent
        // write paths to the same sale and let a double-pay exploit the schedule's
        // remaining balance. Mirrors the destroy() EMI block approach.
        if ($sale->emi) {
            return back()->with('error', 'This sale has an EMI schedule. Use the EMI schedule to record payments for this sale.')->withInput();
        }

        $remainingDue = round((float)$sale->grand_total - (float)$sale->paid_amount, 2);

        if ($remainingDue <= 0) {
            return back()->with('error', 'This invoice is already fully settled.')->withInput();
        }

        if ((float)$request->amount > ($remainingDue + 0.0001)) {
            return back()->with('error', 'Payment amount cannot exceed remaining balance due (' . format_currency($remainingDue) . ').')->withInput();
        }

        try {
            DB::beginTransaction();

            // Create payment record
            $payment = \App\Models\DbSalePayment::create([
                'store_id' => Auth::user()->store_id ?? 1,
                'sales_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'payment_date' => $request->payment_date,
                'payment_type' => $request->payment_type,
                'payment' => $request->amount,
                'payment_note' => $request->payment_note,
                'account_id' => $request->account_id,
                'created_by' => Auth::id(),
                'status' => 1,
            ]);

            // Create Ledger Transaction & Update Account Balance
            if ($payment->account_id && $payment->payment > 0) {
                AcTransaction::create([
                    'store_id' => $payment->store_id ?? 1,
                    'transaction_date' => $payment->payment_date ?? date('Y-m-d'),
                    'transaction_type' => 'SALES PAYMENT',
                    'payment_code' => $payment->payment_type ?? 'Cash',
                    'credit_account_id' => $payment->account_id,
                    'debit_account_id' => null,
                    'debit_amt' => 0,
                    'credit_amt' => $payment->payment,
                    'note' => 'Sales Payment: ' . $sale->sales_code,
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
            $this->smsTriggerService->trigger('PaymentReceived', $payment);

            // Update sale's paid amount
            $sale->increment('paid_amount', $request->amount);

            // Update payment status
            $sale->refresh();
            $paymentStatus = 'Unpaid';
            if ($sale->paid_amount >= $sale->grand_total) {
                $paymentStatus = 'Paid';
            } elseif ($sale->paid_amount > 0) {
                $paymentStatus = 'Partial';
            }
            $sale->update(['payment_status' => $paymentStatus]);

            DB::commit();

            // Invalidate dashboard caches affected by payment
            Cache::forget('dashboard_outstanding_due_s' . current_store_id());
            Cache::forget('dashboard_customers_due_s' . current_store_id());

            return redirect()->route('sales.list')->with('success', 'Payment of ' . format_currency((float)$request->amount) . ' received successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment recording failed', ['sales_id' => $request->sales_id, 'error' => $e->getMessage()]);
            return back()->with('error', 'This payment could not be recorded. Please try again.');
        }
    }

    public function destroyPayment($id)
    {
        try {
            DB::beginTransaction();

            $payment = \App\Models\DbSalePayment::with(['sale', 'emiSchedule'])->findOrFail($id);
            $sale = $payment->sale;

            if (!$sale) {
                throw new \Exception('Payment has no parent sale.');
            }

            $amount = (float)$payment->payment;
            $accountId = $payment->account_id;

            // Guard: reversing this payment must never leave the sale's paid amount negative.
            $newPaid = round((float)$sale->paid_amount - $amount, 2);
            if ($newPaid < 0) {
                DB::rollBack();
                return back()->with('error', 'This payment cannot be deleted because it would make the sale balance negative.');
            }

            // Reverse ledger entry + account balance (mirrors SaleController@destroy and PosController@store)
            if ($accountId && $amount > 0) {
                $acc = AcAccount::find($accountId);
                if ($acc) {
                    $acc->decrement('balance', $amount);
                }
            }
            AcTransaction::where('ref_salespayments_id', $payment->id)->delete();

            // EMI-aware reversal (A3): if this payment settled an EMI installment, the
            // db_emi_schedule row must be wound back too — otherwise the schedule stays
            // "Paid" while the money/ledger is gone, desyncing the EMI details page.
            $schedule = $payment->emiSchedule;
            if ($schedule) {
                $schedule->paid_amount = max(0, round((float)$schedule->paid_amount - $amount, 2));
                $schedule->paid_date = $schedule->paid_amount > 0 ? $schedule->paid_date : null;
                $schedule->status = $schedule->paid_amount >= $schedule->amount
                    ? 'Paid'
                    : ($schedule->paid_amount > 0 ? 'Partial' : 'Pending');
                $schedule->save();

                // Un-complete the parent EMI sale if it had been marked Completed.
                $emiSale = $schedule->emiSale;
                if ($emiSale && $emiSale->status === 'Completed') {
                    $emiSale->update(['status' => 'Active']);
                }
            }

            // Delete the payment row
            $payment->delete();

            // Recompute sale paid_amount and payment_status (same thresholds as storePayment)
            $sale->update(['paid_amount' => max(0, $newPaid)]);
            $sale->refresh();
            $paymentStatus = 'Unpaid';
            if ($sale->paid_amount >= $sale->grand_total) {
                $paymentStatus = 'Paid';
            } elseif ($sale->paid_amount > 0) {
                $paymentStatus = 'Partial';
            }
            $sale->update(['payment_status' => $paymentStatus]);

            DB::commit();

            // Invalidate dashboard caches affected by payment
            Cache::forget('dashboard_outstanding_due_s' . current_store_id());
            Cache::forget('dashboard_customers_due_s' . current_store_id());

            return back()->with('success', 'Payment of ' . format_currency($amount) . ' deleted and sale balance updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment deletion failed', ['payment_id' => $id, 'error' => $e->getMessage()]);
            return back()->with('error', 'This payment could not be deleted. Please try again.');
        }
    }
}
