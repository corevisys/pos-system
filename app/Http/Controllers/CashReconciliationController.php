<?php

namespace App\Http\Controllers;

use App\Models\CashDrawerReconciliation;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbWarehouse;
use App\Models\DbSalePayment;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashReconciliationController extends Controller
{
    /**
     * Display a listing of reconciliations.
     */
    public function index(Request $request)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_view')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        // Phase 1 (Item 1.1): Store-scoped base query excluding soft-deleted records
        $storeId = current_store_id();
        $query = CashDrawerReconciliation::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['account', 'warehouse', 'user', 'opener', 'closer'])
            ->orderBy('reconciliation_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('reconciliation_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('reconciliation_date')) {
            $query->whereDate('reconciliation_date', $request->reconciliation_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Phase 4 (Item 4.2): CSV Export
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $records = $query->get();
            $filename = 'cash_reconciliation_' . date('Y_m_d_His') . '.csv';

            return response()->stream(function () use ($records) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
                fputcsv($handle, ['Code', 'Date', 'Account', 'Warehouse', 'Opener', 'Closer', 'Starting Float', 'Expected Closing', 'Counted Amount', 'Variance', 'Status']);

                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r->reconciliation_code,
                        $r->reconciliation_date ? $r->reconciliation_date->format('Y-m-d') : '',
                        $r->account ? $r->account->account_name : 'N/A',
                        $r->warehouse ? $r->warehouse->warehouse_name : 'All Warehouses',
                        $r->opener ? $r->opener->name : ($r->user ? $r->user->name : 'N/A'),
                        $r->closer ? $r->closer->name : 'N/A',
                        number_format((float) $r->opening_balance, 2, '.', ''),
                        number_format((float) $r->expected_closing_balance, 2, '.', ''),
                        number_format((float) $r->counted_amount, 2, '.', ''),
                        number_format((float) $r->variance, 2, '.', ''),
                        $r->status,
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Phase 4 (Item 4.2): PDF / Printable Export
        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $records = $query->get();
            $currencySymbol = function_exists('currency') ? currency() : '$';
            return view('module.accounts.reconciliation.reconciliation_list_print', [
                'reconciliations' => $records,
                'currencySymbol' => $currencySymbol,
            ]);
        }

        // Phase 5 (Item 5.2): Whitelist per_page with fallback to 15
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $reconciliations = $query->paginate($perPage)->withQueryString();

        // Phase 1 (Item 1.1): Store-scoped dropdowns
        $warehouses = DbWarehouse::where('store_id', $storeId)->where('status', 1)->get();
        $accounts = AcAccount::where('store_id', $storeId)->where('status', 1)->where('delete_bit', 0)->where('is_system', 0)->get();

        return view('module.accounts.reconciliation.index', compact('reconciliations', 'warehouses', 'accounts'));
    }

    /**
     * Show the form for opening a cash drawer (Morning Action).
     */
    public function openForm()
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        // Phase 1 (Item 1.1): Store-scoped dropdowns
        $storeId = current_store_id();
        $warehouses = DbWarehouse::where('store_id', $storeId)->where('status', 1)->get();
        $accounts = AcAccount::where('store_id', $storeId)->where('status', 1)->where('delete_bit', 0)->where('is_system', 0)->get();

        return view('module.accounts.reconciliation.open', compact('warehouses', 'accounts'));
    }

    /**
     * Backward-compatible alias for openForm.
     */
    public function create()
    {
        return $this->openForm();
    }

    /**
     * Store a newly opened cash drawer (Morning Action).
     */
    public function openDrawer(Request $request)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $request->validate([
            'reconciliation_date' => 'required|date',
            'account_id' => 'required|exists:ac_accounts,id',
            'warehouse_id' => 'nullable|exists:db_warehouse,id',
            'opening_balance' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ]);

        $storeId = current_store_id();

        try {
            DB::beginTransaction();

            // Phase 3 (Item 3.5): Concurrency Guard — lock the account row to prevent race conditions on opening
            $account = AcAccount::where('id', $request->account_id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->lockForUpdate()
                ->firstOrFail();

            // Phase 1 (Item 1.1): Store-scoped global open drawer check
            $openDrawerQuery = CashDrawerReconciliation::where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->where('account_id', $request->account_id)
                ->where('status', 'Open');

            if ($request->filled('warehouse_id')) {
                $openDrawerQuery->where(function ($q) use ($request) {
                    $q->where('warehouse_id', $request->warehouse_id)
                        ->orWhereNull('warehouse_id');
                });
            }

            $existingOpenDrawer = $openDrawerQuery->first();
            if ($existingOpenDrawer) {
                DB::rollBack();
                $dateStr = Carbon::parse($existingOpenDrawer->reconciliation_date)->format('Y-m-d');
                $whName = $existingOpenDrawer->warehouse ? $existingOpenDrawer->warehouse->warehouse_name : 'All Warehouses';
                return back()->with('error', "A cash drawer for this account ({$whName}) is already open from {$dateStr} ({$existingOpenDrawer->reconciliation_code}). Please close it before opening a new drawer.")->withInput();
            }

            // Phase 1 (Item 1.1): Store-scoped duplicate check for exact same date, account, and warehouse
            $duplicateQuery = CashDrawerReconciliation::where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->whereDate('reconciliation_date', $request->reconciliation_date)
                ->where('account_id', $request->account_id);

            if ($request->filled('warehouse_id')) {
                $duplicateQuery->where('warehouse_id', $request->warehouse_id);
            } else {
                $duplicateQuery->whereNull('warehouse_id');
            }

            if ($duplicateQuery->exists()) {
                DB::rollBack();
                return back()->with('error', 'A cash reconciliation record already exists for this date, account, and warehouse.')->withInput();
            }

            $breakdown = $this->getCalculationBreakdown($request->account_id, $request->reconciliation_date, $request->warehouse_id);

            $isInitial = (bool) ($breakdown['is_first_reconciliation'] ?? false);
            $systemOpeningBalance = (float) $breakdown['system_opening_balance'];
            $confirmedOpeningBalance = (float) $request->opening_balance;
            $openingVariance = $isInitial ? 0.00 : round($confirmedOpeningBalance - $systemOpeningBalance, 2);

            // Require opening note if there is an unexplained float discrepancy (non-initial)
            if (!$isInitial && $openingVariance != 0 && empty(trim($request->opening_notes ?? ''))) {
                DB::rollBack();
                return back()->with('error', 'Please provide an explanation note for the opening float difference before opening the drawer.')->withInput();
            }

            // Generate unique reconciliation code via CodeGeneratorService (store-scoped, row-locked)
            $code = \App\Services\CodeGeneratorService::generate('reconciliation');

            $reconciliation = CashDrawerReconciliation::create([
                'reconciliation_code' => $code,
                'store_id' => $storeId,
                'warehouse_id' => $request->warehouse_id,
                'account_id' => $request->account_id,
                'user_id' => Auth::id() ?? 1,
                'opened_by' => Auth::id() ?? 1,
                'opened_at' => Carbon::now(),
                'reconciliation_date' => $request->reconciliation_date,
                'system_opening_balance' => $systemOpeningBalance,
                'opening_balance' => $confirmedOpeningBalance,
                'opening_variance' => $openingVariance,
                'is_initial' => $isInitial,
                'opening_notes' => $request->opening_notes,
                'cash_sales_amount' => 0.00,
                'cash_refunds_amount' => 0.00,
                'cash_expenses_amount' => 0.00,
                'cash_deposits_amount' => 0.00,
                'cash_transfers_in' => 0.00,
                'cash_transfers_out' => 0.00,
                'expected_closing_balance' => 0.00,
                'counted_amount' => 0.00,
                'variance' => 0.00,
                'status' => 'Open',
                'delete_bit' => 0,
            ]);

            DB::commit();

            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('success', 'Cash drawer opened successfully with confirmed starting float.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to open cash drawer: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Backward-compatible single-step store or openDrawer.
     */
    public function store(Request $request)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $storeId = current_store_id();

        // If counted_amount is provided, handle complete single-step reconciliation (legacy compatibility)
        if ($request->has('counted_amount')) {
            $request->validate([
                'reconciliation_date' => 'required|date',
                'account_id' => 'required|exists:ac_accounts,id',
                'warehouse_id' => 'nullable|exists:db_warehouse,id',
                'opening_balance' => 'required|numeric|min:0',
                'opening_notes' => 'nullable|string',
                'counted_amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
                'denominations' => 'nullable|array',
                'post_adjustment' => 'nullable|boolean',
            ]);

            try {
                DB::beginTransaction();

                // Lock account to serialize single-step reconciliation
                $account = AcAccount::where('id', $request->account_id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Store-scoped active drawer check
                $openDrawerQuery = CashDrawerReconciliation::where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->where('account_id', $request->account_id)
                    ->where('status', 'Open');

                if ($request->filled('warehouse_id')) {
                    $openDrawerQuery->where(function ($q) use ($request) {
                        $q->where('warehouse_id', $request->warehouse_id)
                            ->orWhereNull('warehouse_id');
                    });
                }

                $existingOpenDrawer = $openDrawerQuery->first();
                if ($existingOpenDrawer) {
                    DB::rollBack();
                    $dateStr = Carbon::parse($existingOpenDrawer->reconciliation_date)->format('Y-m-d');
                    $whName = $existingOpenDrawer->warehouse ? $existingOpenDrawer->warehouse->warehouse_name : 'All Warehouses';
                    return back()->with('error', "A cash drawer for this account ({$whName}) is already open from {$dateStr} ({$existingOpenDrawer->reconciliation_code}). Please close it before opening a new drawer.")->withInput();
                }

                // Store-scoped duplicate date check
                $duplicateQuery = CashDrawerReconciliation::where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->whereDate('reconciliation_date', $request->reconciliation_date)
                    ->where('account_id', $request->account_id);

                if ($request->filled('warehouse_id')) {
                    $duplicateQuery->where('warehouse_id', $request->warehouse_id);
                } else {
                    $duplicateQuery->whereNull('warehouse_id');
                }

                if ($duplicateQuery->exists()) {
                    DB::rollBack();
                    return back()->with('error', 'A cash reconciliation has already been completed for this date, account, and warehouse.')->withInput();
                }

                $breakdown = $this->getCalculationBreakdown($request->account_id, $request->reconciliation_date, $request->warehouse_id);

                $isInitial = (bool) ($breakdown['is_first_reconciliation'] ?? false);
                $systemOpeningBalance = (float) $breakdown['system_opening_balance'];
                $confirmedOpeningBalance = (float) $request->opening_balance;
                $openingVariance = $isInitial ? 0.00 : round($confirmedOpeningBalance - $systemOpeningBalance, 2);

                $expectedBalance = round(
                    $confirmedOpeningBalance
                    + (float) $breakdown['cash_sales_amount']
                    + (float) $breakdown['cash_deposits_amount']
                    + (float) $breakdown['cash_transfers_in']
                    - (float) $breakdown['cash_refunds_amount']
                    - (float) $breakdown['cash_expenses_amount']
                    - (float) $breakdown['cash_transfers_out'],
                    2
                );

                $countedAmount = (float) $request->counted_amount;
                $variance = round($countedAmount - $expectedBalance, 2);

                // Generate unique reconciliation code via CodeGeneratorService (store-scoped, row-locked)
                $code = \App\Services\CodeGeneratorService::generate('reconciliation');

                $status = 'Reconciled';
                $adjustmentTransactionId = null;

                if ($request->boolean('post_adjustment') && $variance != 0) {
                    if (!Auth::user()->hasPermission('cash_reconciliation_adjust') && !Auth::user()->isSuperAdmin()) {
                        DB::rollBack();
                        return back()->with('error', 'You do not have permission to post cash variance adjustments to the account ledger.')->withInput();
                    }

                    if ($variance < 0) {
                        $absVariance = abs($variance);
                        $adjTransaction = AcTransaction::create([
                            'store_id' => $storeId,
                            'transaction_date' => $request->reconciliation_date,
                            'transaction_type' => 'CASH SHORTAGE',
                            'payment_code' => 'Cash',
                            'debit_account_id' => $account->id,
                            'credit_account_id' => null,
                            'debit_amt' => $absVariance,
                            'credit_amt' => 0,
                            'note' => 'Cash Shortage Adjustment for ' . $code . ' (Counted: ' . number_format($countedAmount, 2) . ', Expected: ' . number_format($expectedBalance, 2) . ')',
                            'created_by' => Auth::id() ?? 1,
                            'created_date' => date('Y-m-d'),
                        ]);

                        $account->decrement('balance', $absVariance);
                        $adjustmentTransactionId = $adjTransaction->id;
                        $status = 'Adjusted';
                    } elseif ($variance > 0) {
                        $adjTransaction = AcTransaction::create([
                            'store_id' => $storeId,
                            'transaction_date' => $request->reconciliation_date,
                            'transaction_type' => 'CASH OVERAGE',
                            'payment_code' => 'Cash',
                            'credit_account_id' => $account->id,
                            'debit_account_id' => null,
                            'credit_amt' => $variance,
                            'debit_amt' => 0,
                            'note' => 'Cash Overage Adjustment for ' . $code . ' (Counted: ' . number_format($countedAmount, 2) . ', Expected: ' . number_format($expectedBalance, 2) . ')',
                            'created_by' => Auth::id() ?? 1,
                            'created_date' => date('Y-m-d'),
                        ]);

                        $account->increment('balance', $variance);
                        $adjustmentTransactionId = $adjTransaction->id;
                        $status = 'Adjusted';
                    }
                }

                $reconciliation = CashDrawerReconciliation::create([
                    'reconciliation_code' => $code,
                    'store_id' => $storeId,
                    'warehouse_id' => $request->warehouse_id,
                    'account_id' => $request->account_id,
                    'user_id' => Auth::id() ?? 1,
                    'opened_by' => Auth::id() ?? 1,
                    'opened_at' => Carbon::now(),
                    'closed_by' => Auth::id() ?? 1,
                    'closed_at' => Carbon::now(),
                    'reconciliation_date' => $request->reconciliation_date,
                    'system_opening_balance' => $systemOpeningBalance,
                    'opening_balance' => $confirmedOpeningBalance,
                    'opening_variance' => $openingVariance,
                    'is_initial' => $isInitial,
                    'opening_notes' => $request->opening_notes,
                    'cash_sales_amount' => $breakdown['cash_sales_amount'],
                    'cash_refunds_amount' => $breakdown['cash_refunds_amount'],
                    'cash_expenses_amount' => $breakdown['cash_expenses_amount'],
                    'cash_deposits_amount' => $breakdown['cash_deposits_amount'],
                    'cash_transfers_in' => $breakdown['cash_transfers_in'],
                    'cash_transfers_out' => $breakdown['cash_transfers_out'],
                    'expected_closing_balance' => $expectedBalance,
                    'counted_amount' => $countedAmount,
                    'variance' => $variance,
                    'denominations' => $request->denominations,
                    'status' => $status,
                    'adjustment_transaction_id' => $adjustmentTransactionId,
                    'notes' => $request->notes,
                    'delete_bit' => 0,
                ]);

                DB::commit();

                return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                    ->with('success', 'Daily cash drawer reconciliation saved successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Failed to save reconciliation: ' . $e->getMessage())->withInput();
            }
        }

        return $this->openDrawer($request);
    }

    /**
     * Show the form for closing a cash drawer (Evening Action).
     */
    public function closeForm($id)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        // Phase 1 (Item 1.2): Store-scoped lookup
        $storeId = current_store_id();
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['account', 'warehouse', 'opener', 'user'])
            ->firstOrFail();

        if ($reconciliation->status !== 'Open') {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'This cash drawer is already closed and reconciled.');
        }

        // Q2 Restriction: Opener = Closer only (strict enforcement)
        if (Auth::id() !== $reconciliation->opened_by && !Auth::user()->isSuperAdmin()) {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Unauthorized: Only ' . ($reconciliation->opener->name ?? 'the user who opened this drawer') . ' can close it.');
        }

        $breakdown = $this->getCalculationBreakdown($reconciliation->account_id, $reconciliation->reconciliation_date, $reconciliation->warehouse_id);

        $liveExpectedClosing = round(
            (float) $reconciliation->opening_balance
            + (float) $breakdown['cash_sales_amount']
            + (float) $breakdown['cash_deposits_amount']
            + (float) $breakdown['cash_transfers_in']
            - (float) $breakdown['cash_refunds_amount']
            - (float) $breakdown['cash_expenses_amount']
            - (float) $breakdown['cash_transfers_out'],
            2
        );

        return view('module.accounts.reconciliation.close', compact('reconciliation', 'breakdown', 'liveExpectedClosing'));
    }

    /**
     * Store the closing count and reconcile the cash drawer (Evening Action).
     */
    public function closeDrawer(Request $request, $id)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $storeId = current_store_id();

        // Phase 1 (Item 1.2): Store-scoped lookup
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['account', 'opener'])
            ->firstOrFail();

        if ($reconciliation->status !== 'Open') {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'This cash drawer is already closed.');
        }

        // Q2 Restriction: Opener = Closer only (strict enforcement)
        if (Auth::id() !== $reconciliation->opened_by && !Auth::user()->isSuperAdmin()) {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Unauthorized: Only ' . ($reconciliation->opener->name ?? 'the user who opened this drawer') . ' can close it.');
        }

        $request->validate([
            'counted_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'denominations' => 'nullable|array',
            'post_adjustment' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $breakdown = $this->getCalculationBreakdown($reconciliation->account_id, $reconciliation->reconciliation_date, $reconciliation->warehouse_id);

            // Calculate expected closing balance based on confirmed starting float
            $expectedBalance = round(
                (float) $reconciliation->opening_balance
                + (float) $breakdown['cash_sales_amount']
                + (float) $breakdown['cash_deposits_amount']
                + (float) $breakdown['cash_transfers_in']
                - (float) $breakdown['cash_refunds_amount']
                - (float) $breakdown['cash_expenses_amount']
                - (float) $breakdown['cash_transfers_out'],
                2
            );

            $countedAmount = (float) $request->counted_amount;
            $variance = round($countedAmount - $expectedBalance, 2);

            $status = 'Reconciled';
            $adjustmentTransactionId = null;

            // Option C Hybrid Variance Adjustment: Only if elevated permission is held and requested
            if ($request->boolean('post_adjustment') && $variance != 0) {
                if (!Auth::user()->hasPermission('cash_reconciliation_adjust') && !Auth::user()->isSuperAdmin()) {
                    DB::rollBack();
                    return back()->with('error', 'You do not have permission to post cash variance adjustments to the account ledger.')->withInput();
                }

                $account = AcAccount::where('id', $reconciliation->account_id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($variance < 0) {
                    // Shortage (Deficit): Cash missing -> Debit account / Cash Shortage
                    $absVariance = abs($variance);
                    $adjTransaction = AcTransaction::create([
                        'store_id' => $storeId,
                        'transaction_date' => $reconciliation->reconciliation_date,
                        'transaction_type' => 'CASH SHORTAGE',
                        'payment_code' => 'Cash',
                        'debit_account_id' => $account->id,
                        'credit_account_id' => null,
                        'debit_amt' => $absVariance,
                        'credit_amt' => 0,
                        'note' => 'Cash Shortage Adjustment for ' . $reconciliation->reconciliation_code . ' (Counted: ' . number_format($countedAmount, 2) . ', Expected: ' . number_format($expectedBalance, 2) . ')',
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    $account->decrement('balance', $absVariance);
                    $adjustmentTransactionId = $adjTransaction->id;
                    $status = 'Adjusted';
                } elseif ($variance > 0) {
                    // Overage (Surplus): Excess cash -> Credit account / Cash Overage
                    $adjTransaction = AcTransaction::create([
                        'store_id' => $storeId,
                        'transaction_date' => $reconciliation->reconciliation_date,
                        'transaction_type' => 'CASH OVERAGE',
                        'payment_code' => 'Cash',
                        'credit_account_id' => $account->id,
                        'debit_account_id' => null,
                        'credit_amt' => $variance,
                        'debit_amt' => 0,
                        'note' => 'Cash Overage Adjustment for ' . $reconciliation->reconciliation_code . ' (Counted: ' . number_format($countedAmount, 2) . ', Expected: ' . number_format($expectedBalance, 2) . ')',
                        'created_by' => Auth::id() ?? 1,
                        'created_date' => date('Y-m-d'),
                    ]);

                    $account->increment('balance', $variance);
                    $adjustmentTransactionId = $adjTransaction->id;
                    $status = 'Adjusted';
                }
            }

            $reconciliation->update([
                'closed_by' => Auth::id() ?? 1,
                'closed_at' => Carbon::now(),
                'cash_sales_amount' => $breakdown['cash_sales_amount'],
                'cash_refunds_amount' => $breakdown['cash_refunds_amount'],
                'cash_expenses_amount' => $breakdown['cash_expenses_amount'],
                'cash_deposits_amount' => $breakdown['cash_deposits_amount'],
                'cash_transfers_in' => $breakdown['cash_transfers_in'],
                'cash_transfers_out' => $breakdown['cash_transfers_out'],
                'expected_closing_balance' => $expectedBalance,
                'counted_amount' => $countedAmount,
                'variance' => $variance,
                'denominations' => $request->denominations,
                'status' => $status,
                'adjustment_transaction_id' => $adjustmentTransactionId,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('success', 'Daily cash drawer closed and reconciled successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to close reconciliation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Phase 4 (Item 4.1): Show form to edit a Reconciled record.
     * Guarded default: Allowed ONLY when status = 'Reconciled'.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $storeId = current_store_id();
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['account', 'warehouse', 'opener', 'closer'])
            ->firstOrFail();

        // Opener / Closer / Admin restriction
        if (Auth::id() !== $reconciliation->opened_by && Auth::id() !== $reconciliation->closed_by && !Auth::user()->isSuperAdmin()) {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Unauthorized: Only the opener, closer, or a Super Admin can edit this reconciliation.');
        }

        // Status restriction
        if ($reconciliation->status === 'Adjusted') {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Reconciliations with posted ledger adjustments cannot be directly edited. To undo adjustments, delete the record.');
        }

        if ($reconciliation->status === 'Open') {
            return redirect()->route('accounts.cash-reconciliation.close-form', $reconciliation->id)
                ->with('error', 'This cash drawer is still open. Please complete the closing procedure instead of editing.');
        }

        return view('module.accounts.reconciliation.edit', compact('reconciliation'));
    }

    /**
     * Phase 4 (Item 4.1): Update a Reconciled record (counted_amount and notes only).
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_add')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $storeId = current_store_id();
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->firstOrFail();

        // Opener / Closer / Admin restriction
        if (Auth::id() !== $reconciliation->opened_by && Auth::id() !== $reconciliation->closed_by && !Auth::user()->isSuperAdmin()) {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Unauthorized: Only the opener, closer, or a Super Admin can edit this reconciliation.');
        }

        // Status restriction
        if ($reconciliation->status === 'Adjusted') {
            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('error', 'Reconciliations with posted ledger adjustments cannot be directly edited.');
        }

        if ($reconciliation->status === 'Open') {
            return redirect()->route('accounts.cash-reconciliation.close-form', $reconciliation->id)
                ->with('error', 'This cash drawer is still open. Please complete the closing procedure.');
        }

        $request->validate([
            'counted_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'denominations' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $newCounted = (float) $request->counted_amount;
            $newVariance = round($newCounted - (float) $reconciliation->expected_closing_balance, 2);

            $reconciliation->update([
                'counted_amount' => $newCounted,
                'variance' => $newVariance,
                'notes' => $request->notes,
                'denominations' => $request->denominations,
            ]);

            DB::commit();

            return redirect()->route('accounts.cash-reconciliation.show', $reconciliation->id)
                ->with('success', 'Reconciliation count updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update reconciliation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Calculate expected cash breakdown for a specific account, warehouse, and date.
     */
    public function calculateExpected(Request $request)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_view')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $request->validate([
            'account_id' => 'required|exists:ac_accounts,id',
            'reconciliation_date' => 'required|date',
            'warehouse_id' => 'nullable|exists:db_warehouse,id',
        ]);

        $accountId = $request->account_id;
        $date = $request->reconciliation_date;
        $warehouseId = $request->warehouse_id;

        $breakdown = $this->getCalculationBreakdown($accountId, $date, $warehouseId);

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * Display the specified reconciliation details (Context-Aware: Open vs Closed).
     */
    public function show($id)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_view')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        // Phase 1 (Item 1.2): Store-scoped lookup
        $storeId = current_store_id();
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with([
                'account', 'warehouse', 'user', 'opener', 'closer', 'adjustmentTransaction'
            ])->firstOrFail();

        $liveExpectedClosing = 0.00;
        $breakdown = [];
        $isOpener = Auth::id() === $reconciliation->opened_by || (Auth::user() && Auth::user()->isSuperAdmin());

        if ($reconciliation->status === 'Open') {
            $breakdown = $this->getCalculationBreakdown($reconciliation->account_id, $reconciliation->reconciliation_date, $reconciliation->warehouse_id);
            $liveExpectedClosing = round(
                (float) $reconciliation->opening_balance
                + (float) $breakdown['cash_sales_amount']
                + (float) $breakdown['cash_deposits_amount']
                + (float) $breakdown['cash_transfers_in']
                - (float) $breakdown['cash_refunds_amount']
                - (float) $breakdown['cash_expenses_amount']
                - (float) $breakdown['cash_transfers_out'],
                2
            );
        }

        return view('module.accounts.reconciliation.show', compact('reconciliation', 'liveExpectedClosing', 'breakdown', 'isOpener'));
    }

    /**
     * Remove the specified reconciliation and reverse any ledger adjustment non-destructively.
     */
    public function destroy($id)
    {
        // Phase 2: Permission Gate
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_delete')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $storeId = current_store_id();

        // Phase 1 (Item 1.2): Store-scoped lookup (404 on cross-store or deleted access)
        $reconciliation = CashDrawerReconciliation::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Phase 3 (Item 3.2): Atomic conditional state transition (delete_bit = 0 -> 1)
            $affected = CashDrawerReconciliation::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                DB::rollBack();
                return back()->with('error', 'This reconciliation was already deleted or is being processed.');
            }

            // Phase 3 (Item 3.3 & 3.4): Non-destructive reversal of ledger adjustments with Solvency Guard
            if ($reconciliation->adjustment_transaction_id) {
                $tx = AcTransaction::where('id', $reconciliation->adjustment_transaction_id)
                    ->where('store_id', $storeId)
                    ->first();

                if ($tx) {
                    if ($tx->transaction_type === 'CASH SHORTAGE' && $tx->debit_account_id) {
                        // Shortage originally reduced balance. Reversal refunds/credits the account.
                        $acc = AcAccount::where('id', $tx->debit_account_id)
                            ->where('store_id', $storeId)
                            ->lockForUpdate()
                            ->firstOrFail();

                        AcTransaction::create([
                            'store_id' => $storeId,
                            'transaction_date' => date('Y-m-d'),
                            'transaction_type' => 'CASH SHORTAGE REVERSAL',
                            'payment_code' => 'Cash',
                            'credit_account_id' => $acc->id,
                            'debit_account_id' => null,
                            'credit_amt' => $tx->debit_amt,
                            'debit_amt' => 0,
                            'note' => 'Reversal of Cash Shortage for Reconciliation ' . $reconciliation->reconciliation_code,
                            'created_by' => Auth::id() ?? 1,
                            'created_date' => date('Y-m-d'),
                        ]);

                        $acc->increment('balance', $tx->debit_amt);
                    } elseif ($tx->transaction_type === 'CASH OVERAGE' && $tx->credit_account_id) {
                        // Overage originally increased balance. Reversal deducts/debits the account.
                        $acc = AcAccount::where('id', $tx->credit_account_id)
                            ->where('store_id', $storeId)
                            ->lockForUpdate()
                            ->firstOrFail();

                        // Item 3.3: Solvency Guard on Overage Reversal
                        if ($acc->balance < $tx->credit_amt) {
                            DB::rollBack();
                            return back()->with('error', "Cannot delete reconciliation: account '{$acc->account_name}' has insufficient balance (" . format_currency($acc->balance) . ") to reverse cash overage adjustment of " . format_currency($tx->credit_amt) . ".");
                        }

                        AcTransaction::create([
                            'store_id' => $storeId,
                            'transaction_date' => date('Y-m-d'),
                            'transaction_type' => 'CASH OVERAGE REVERSAL',
                            'payment_code' => 'Cash',
                            'debit_account_id' => $acc->id,
                            'credit_account_id' => null,
                            'debit_amt' => $tx->credit_amt,
                            'credit_amt' => 0,
                            'note' => 'Reversal of Cash Overage for Reconciliation ' . $reconciliation->reconciliation_code,
                            'created_by' => Auth::id() ?? 1,
                            'created_date' => date('Y-m-d'),
                        ]);

                        $acc->decrement('balance', $tx->credit_amt);
                    }
                }
            }

            DB::commit();

            return redirect()->route('accounts.cash-reconciliation.index')
                ->with('success', 'Reconciliation record deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete reconciliation: ' . $e->getMessage());
        }
    }

    /**
     * Phase 4 (Item 4.3): Bulk Delete with individual atomic solvency checks and skip-with-report.
     */
    public function bulkDestroy(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('cash_reconciliation_delete')) {
            abort(403, 'Unauthorized access to cash reconciliation.');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $storeId = current_store_id();
        $requestedIds = $request->ids;

        $deletedCount = 0;
        $skippedMessages = [];

        foreach ($requestedIds as $id) {
            try {
                DB::beginTransaction();

                $reconciliation = CashDrawerReconciliation::where('id', $id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->first();

                if (!$reconciliation) {
                    DB::rollBack();
                    continue; // Skip already deleted or cross-store ID
                }

                // Check solvency BEFORE atomic transition
                if ($reconciliation->adjustment_transaction_id) {
                    $tx = AcTransaction::where('id', $reconciliation->adjustment_transaction_id)
                        ->where('store_id', $storeId)
                        ->first();

                    if ($tx && $tx->transaction_type === 'CASH OVERAGE' && $tx->credit_account_id) {
                        $acc = AcAccount::where('id', $tx->credit_account_id)
                            ->where('store_id', $storeId)
                            ->lockForUpdate()
                            ->first();

                        if ($acc && $acc->balance < $tx->credit_amt) {
                            DB::rollBack();
                            $skippedMessages[] = "{$reconciliation->reconciliation_code} skipped (account '{$acc->account_name}' balance " . format_currency($acc->balance) . " is insufficient to reverse overage of " . format_currency($tx->credit_amt) . ")";
                            continue;
                        }
                    }
                }

                // Atomic conditional update
                $affected = CashDrawerReconciliation::where('id', $id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->update(['delete_bit' => 1]);

                if ($affected !== 1) {
                    DB::rollBack();
                    continue;
                }

                // Process reversal
                if ($reconciliation->adjustment_transaction_id) {
                    $tx = AcTransaction::where('id', $reconciliation->adjustment_transaction_id)
                        ->where('store_id', $storeId)
                        ->first();

                    if ($tx) {
                        if ($tx->transaction_type === 'CASH SHORTAGE' && $tx->debit_account_id) {
                            $acc = AcAccount::where('id', $tx->debit_account_id)->where('store_id', $storeId)->lockForUpdate()->first();
                            if ($acc) {
                                AcTransaction::create([
                                    'store_id' => $storeId,
                                    'transaction_date' => date('Y-m-d'),
                                    'transaction_type' => 'CASH SHORTAGE REVERSAL',
                                    'payment_code' => 'Cash',
                                    'credit_account_id' => $acc->id,
                                    'debit_account_id' => null,
                                    'credit_amt' => $tx->debit_amt,
                                    'debit_amt' => 0,
                                    'note' => 'Bulk Reversal of Cash Shortage for ' . $reconciliation->reconciliation_code,
                                    'created_by' => Auth::id() ?? 1,
                                    'created_date' => date('Y-m-d'),
                                ]);
                                $acc->increment('balance', $tx->debit_amt);
                            }
                        } elseif ($tx->transaction_type === 'CASH OVERAGE' && $tx->credit_account_id) {
                            $acc = AcAccount::where('id', $tx->credit_account_id)->where('store_id', $storeId)->lockForUpdate()->first();
                            if ($acc) {
                                AcTransaction::create([
                                    'store_id' => $storeId,
                                    'transaction_date' => date('Y-m-d'),
                                    'transaction_type' => 'CASH OVERAGE REVERSAL',
                                    'payment_code' => 'Cash',
                                    'debit_account_id' => $acc->id,
                                    'credit_account_id' => null,
                                    'debit_amt' => $tx->credit_amt,
                                    'credit_amt' => 0,
                                    'note' => 'Bulk Reversal of Cash Overage for ' . $reconciliation->reconciliation_code,
                                    'created_by' => Auth::id() ?? 1,
                                    'created_date' => date('Y-m-d'),
                                ]);
                                $acc->decrement('balance', $tx->credit_amt);
                            }
                        }
                    }
                }

                DB::commit();
                $deletedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
            }
        }

        $responseMsg = "{$deletedCount} reconciliation(s) deleted successfully.";
        if (count($skippedMessages) > 0) {
            $responseMsg .= " " . implode('; ', $skippedMessages);
            return redirect()->route('accounts.cash-reconciliation.index')->with('warning', $responseMsg);
        }

        return redirect()->route('accounts.cash-reconciliation.index')->with('success', $responseMsg);
    }

    /**
     * Compute expected cash breakdown from all 5 data sources.
     * Phase 1 (Item 1.1): Every constituent query is strictly store-scoped by current_store_id().
     */
    private function getCalculationBreakdown($accountId, $date, $warehouseId = null): array
    {
        $storeId = current_store_id();

        // 1. Opening Balance: Check previous day's counted closing balance for this account
        $prevReconQuery = CashDrawerReconciliation::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->where('account_id', $accountId)
            ->where('reconciliation_date', '<', $date)
            ->whereIn('status', ['Reconciled', 'Adjusted'])
            ->orderBy('reconciliation_date', 'desc')
            ->orderBy('id', 'desc');

        if ($warehouseId) {
            $prevReconQuery->where('warehouse_id', $warehouseId);
        }

        $prevRecon = $prevReconQuery->first();
        $isFirstReconciliation = false;

        if ($prevRecon) {
            $systemOpeningBalance = (float) $prevRecon->counted_amount;
        } else {
            // First ever reconciliation for this account/warehouse
            $isFirstReconciliation = true;
            $systemOpeningBalance = 0.00;
        }

        // 2. Cash Sales Payments
        $salesPaymentsQuery = DbSalePayment::where('store_id', $storeId)
            ->where('payment_type', 'Cash')
            ->where('account_id', $accountId)
            ->whereDate('payment_date', $date);

        if ($warehouseId) {
            $salesPaymentsQuery->whereHas('sale', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }
        $cashSales = (float) $salesPaymentsQuery->sum('payment');

        // 3. Cash Sales Refunds (Sales Returns)
        $salesReturnsQuery = DbSalesPaymentReturn::where('store_id', $storeId)
            ->where('payment_type', 'Cash')
            ->where('account_id', $accountId)
            ->whereDate('payment_date', $date);

        if ($warehouseId) {
            $salesReturnsQuery->whereHas('sale', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }
        $cashRefunds = (float) $salesReturnsQuery->sum('payment');

        // 4. Cash Expenses
        // PROTECTED CONTRACT: filter shape (store_id + payment_type='Cash' +
        // account_id + expense_date) must be preserved. delete_bit=0 is added
        // because db_expense now uses soft-delete (Phase 1) and a deleted
        // expense must never be counted.
        $cashExpenses = (float) DbExpense::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->where('payment_type', 'Cash')
            ->where('account_id', $accountId)
            ->whereDate('expense_date', $date)
            ->sum('expense_amt');

        // 5. Cash Deposits In
        $cashDeposits = (float) AcTransaction::where('store_id', $storeId)
            ->where('credit_account_id', $accountId)
            ->where('transaction_type', 'DEPOSIT')
            ->whereDate('transaction_date', $date)
            ->sum('credit_amt');

        // 6. Cash Transfers In
        $cashTransfersIn = (float) AcTransaction::where('store_id', $storeId)
            ->where('credit_account_id', $accountId)
            ->where('transaction_type', 'TRANSFER')
            ->whereDate('transaction_date', $date)
            ->sum('credit_amt');

        // 7. Cash Transfers Out
        $cashTransfersOut = (float) AcTransaction::where('store_id', $storeId)
            ->where('debit_account_id', $accountId)
            ->where('transaction_type', 'TRANSFER')
            ->whereDate('transaction_date', $date)
            ->sum('debit_amt');

        // Suggested Expected Closing Balance Formula
        $expectedClosingBalance = round(
            $systemOpeningBalance + $cashSales + $cashDeposits + $cashTransfersIn - $cashRefunds - $cashExpenses - $cashTransfersOut,
            2
        );

        // Account Ledger Balance
        $account = AcAccount::where('id', $accountId)->where('store_id', $storeId)->where('delete_bit', 0)->first();
        $accountBalance = $account ? (float) $account->balance : 0.00;

        // 8. Active Open Drawer Check
        $openDrawerQuery = CashDrawerReconciliation::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['warehouse', 'opener'])
            ->where('account_id', $accountId)
            ->where('status', 'Open');

        if ($warehouseId) {
            $openDrawerQuery->where(function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->orWhereNull('warehouse_id');
            });
        }

        $existingOpenDrawer = $openDrawerQuery->first();
        $openDrawerData = null;
        if ($existingOpenDrawer) {
            $openDrawerData = [
                'id' => $existingOpenDrawer->id,
                'code' => $existingOpenDrawer->reconciliation_code,
                'date' => Carbon::parse($existingOpenDrawer->reconciliation_date)->format('Y-m-d'),
                'warehouse_name' => $existingOpenDrawer->warehouse ? $existingOpenDrawer->warehouse->warehouse_name : 'All Warehouses',
                'opener_name' => $existingOpenDrawer->opener->name ?? 'Staff',
                'close_url' => route('accounts.cash-reconciliation.close-form', $existingOpenDrawer->id),
            ];
        }

        return [
            'is_first_reconciliation' => $isFirstReconciliation,
            'account_balance' => round($accountBalance, 2),
            'system_opening_balance' => round($systemOpeningBalance, 2),
            'opening_balance' => round($systemOpeningBalance, 2),
            'cash_sales_amount' => round($cashSales, 2),
            'cash_refunds_amount' => round($cashRefunds, 2),
            'cash_expenses_amount' => round($cashExpenses, 2),
            'cash_deposits_amount' => round($cashDeposits, 2),
            'cash_transfers_in' => round($cashTransfersIn, 2),
            'cash_transfers_out' => round($cashTransfersOut, 2),
            'expected_closing_balance' => $expectedClosingBalance,
            'open_drawer' => $openDrawerData,
        ];
    }
}
