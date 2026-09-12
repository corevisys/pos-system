<?php

namespace App\Http\Controllers;

use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\CashDrawerReconciliation;
use App\Models\DbPaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Normalized payment-type option list.
     *
     * Reuses the app's canonical payment-type list (db_paymenttypes, status=1) but
     * canonicalizes any CASH-cased entry to the exact literal 'Cash'. The Cash
     * Reconciliation protected contract (getCalculationBreakdown) filters
     * db_expense.payment_type === 'Cash', so 'Cash' must be the stored value for
     * cash expenses. No new payment-type values are invented.
     */
    protected function paymentTypeOptions(): array
    {
        $types = DbPaymentType::where('status', 1)->pluck('payment_type')
            ->filter()
            ->map(function ($t) {
                $t = trim((string) $t);
                return strtoupper($t) === 'CASH' ? 'Cash' : $t;
            })
            ->unique()
            ->values()
            ->all();

        // Guarantee the cash contract value is always present and selectable.
        if (!in_array('Cash', $types, true)) {
            array_unshift($types, 'Cash');
        }

        return $types;
    }

    /**
     * Phase 2 (Item 4): Does this expense fall inside an already-closed/adjusted
     * cash reconciliation period? A reconciliation is "closed" when its status is
     * Reconciled or Adjusted (status 'Open' drawers are still live and must remain
     * editable). Only account-linked expenses can affect a reconciliation's
     * expected-balance calculation, so the check is scoped by account_id.
     */
    protected function isInClosedReconciliationPeriod(int $storeId, ?int $accountId, string $expenseDate): bool
    {
        if (!$accountId) {
            return false;
        }

        return CashDrawerReconciliation::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->where('account_id', $accountId)
            ->whereIn('status', ['Reconciled', 'Adjusted'])
            ->where(function ($q) use ($expenseDate) {
                $q->whereDate('reconciliation_date', $expenseDate)
                    ->orWhere(function ($q2) use ($expenseDate) {
                        $q2->whereNotNull('period_start')
                            ->whereNotNull('period_end')
                            ->whereDate('period_start', '<=', $expenseDate)
                            ->whereDate('period_end', '>=', $expenseDate);
                    });
            })
            ->exists();
    }

    /**
     * Phase 1: Reusable reversal of an expense's ledger/balance effect.
     *
     * Non-destructive: keeps the original EXPENSE row and inserts an offsetting
     * 'EXPENSE REVERSAL' row (mirrors DepositController's 'DEPOSIT REVERSAL'
     * convention with ref_expense_id), and re-increments the account balance by
     * the original amount. Must be called inside an open DB transaction.
     */
    protected function reverseExpenseLedger(DbExpense $expense, int $storeId, string $reason = 'Expense Reversal'): void
    {
        if (!$expense->account_id || (float) $expense->expense_amt <= 0) {
            return;
        }

        $account = AcAccount::where('id', $expense->account_id)
            ->where('store_id', $storeId)
            ->first();

        AcTransaction::create([
            'store_id' => $storeId,
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_type' => 'EXPENSE REVERSAL',
            'payment_code' => $expense->payment_type ?? 'Cash',
            'debit_account_id' => null,
            'credit_account_id' => $expense->account_id,
            'debit_amt' => 0,
            'credit_amt' => $expense->expense_amt,
            'note' => $reason . ': ' . $expense->expense_for . ' (' . $expense->expense_code . ')',
            'ref_expense_id' => $expense->id,
            'created_by' => Auth::id() ?? 1,
            'created_date' => now()->format('Y-m-d'),
        ]);

        if ($account) {
            $account->increment('balance', $expense->expense_amt);
        }
    }

    /**
     * Phase 1: Reusable forward application of an expense's ledger/balance effect.
     * Must be called inside an open DB transaction.
     */
    protected function applyExpenseLedger(DbExpense $expense, int $storeId): void
    {
        if (!$expense->account_id || (float) $expense->expense_amt <= 0) {
            return;
        }

        AcTransaction::create([
            'store_id' => $storeId,
            'transaction_date' => $expense->expense_date,
            'transaction_type' => 'EXPENSE',
            'payment_code' => $expense->payment_type ?? 'Cash',
            'debit_account_id' => $expense->account_id,
            'credit_account_id' => null,
            'debit_amt' => $expense->expense_amt,
            'credit_amt' => 0,
            'note' => 'Expense: ' . $expense->expense_for . ' (' . $expense->expense_code . ')',
            'ref_expense_id' => $expense->id,
            'created_by' => Auth::id() ?? 1,
            'created_date' => now()->format('Y-m-d'),
        ]);

        $account = AcAccount::where('id', $expense->account_id)
            ->where('store_id', $storeId)
            ->first();

        if ($account) {
            $account->decrement('balance', $expense->expense_amt);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Phase 2 (Item 2): route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('expense_view')) {
            abort(403, 'Unauthorized access to view expenses.');
        }

        // Phase 2 (Item 3): store-scoped, soft-delete-aware base query.
        $query = DbExpense::with(['category', 'account'])
            ->where('store_id', current_store_id())
            ->where('delete_bit', 0)
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('expense_code', 'like', "%{$search}%")
                    ->orWhere('expense_for', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        // Phase 4 (Item 7): footer total over the FULL filtered set, not the page.
        $totalExpenses = (float) (clone $query)->sum('expense_amt');

        // Phase 6 (Item 10): CSV export reusing the Deposit store-scoped convention.
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $all = $query->get();
            $filename = 'expense_list_' . date('Y_m_d_His') . '.csv';
            return response()->stream(function () use ($all) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, ['Date', 'Code', 'Category', 'Reference No', 'Expense For', 'Amount', 'Payment Type', 'Account', 'Note']);
                foreach ($all as $e) {
                    fputcsv($handle, [
                        $e->expense_date,
                        $e->expense_code ?? '',
                        $e->category ? $e->category->category_name : 'N/A',
                        $e->reference_no ?? '',
                        $e->expense_for ?? '',
                        number_format((float) $e->expense_amt, 2, '.', ''),
                        $e->payment_type ?? '',
                        $e->account ? $e->account->account_name : '',
                        $e->note ?? '',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $all = $query->get();
            return view('module.expenses.expenses_list_print', [
                'expenses' => $all,
                'totalExpenses' => (float) $all->sum('expense_amt'),
            ]);
        }

        // Phase 6 (Item 10): per-page whitelist.
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $expenses = $query->paginate($perPage)->withQueryString();

        return view('module.expenses.expenses_list', compact('expenses', 'totalExpenses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Phase 2 (Item 2): permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('expense_add')) {
            abort(403, 'Unauthorized access to add expenses.');
        }

        $storeId = current_store_id();
        // Categories: own store OR legacy rows created before multi-store (store_id NULL).
        $categories = DbExpenseCategory::where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->where('status', 1)
            ->orderBy('category_name')
            ->get();
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)
            ->orderBy('account_name')
            ->get();
        $paymentTypes = $this->paymentTypeOptions();

        return view('module.expenses.create_expense', compact('categories', 'accounts', 'paymentTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Phase 2 (Item 2): permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('expense_add')) {
            abort(403, 'Unauthorized access to add expenses.');
        }

        $storeId = current_store_id();

        // Phase 3 (Item 5): real payment_type with enum validation. 'Cash' remains
        // the default so the Cash Reconciliation contract is unaffected.
        $validPaymentTypes = $this->paymentTypeOptions();

        $request->validate([
            'expense_date' => 'required|date',
            'category_id'  => [
                'required',
                Rule::exists('db_expense_category', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId);
                }),
            ],
            'expense_for'  => 'required|string|max:255',
            'expense_amt'  => 'required|numeric|min:0',
            'payment_type' => ['nullable', 'string', Rule::in($validPaymentTypes)],
            'account_id'   => [
                'nullable',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
        ]);

        try {
            DB::beginTransaction();

            $count_id = (DbExpense::where('store_id', $storeId)->max('count_id') ?? 0) + 1;
            $expense_code = \App\Services\CodeGeneratorService::generate('expense');

            $expense = new DbExpense();
            $expense->store_id = $storeId;
            $expense->count_id = $count_id;
            $expense->expense_code = $expense_code;
            $expense->expense_date = $request->expense_date;
            $expense->category_id = $request->category_id;
            $expense->reference_no = $request->reference_no;
            $expense->expense_for = $request->expense_for;
            $expense->expense_amt = $request->expense_amt;
            $expense->payment_type = $request->payment_type ?? 'Cash';
            $expense->account_id = $request->account_id ?: null;
            $expense->note = $request->note;
            $expense->created_by = auth()->id() ?? 1;
            $expense->created_date = date('Y-m-d');
            $expense->created_time = date('H:i:s');
            $expense->system_ip = $request->ip();
            $expense->system_name = gethostname();
            $expense->status = 1;
            $expense->delete_bit = 0;
            $expense->ledger_version = 0;
            $expense->save();

            // Phase 1: forward ledger/balance effect (single source of truth).
            $this->applyExpenseLedger($expense, $storeId);

            DB::commit();

            return redirect()->route('expenses.list')->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create expense: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Phase 5 (Item 8): Show the edit form.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_edit')) {
            abort(403, 'Unauthorized access to edit expenses.');
        }

        $storeId = current_store_id();
        $expense = DbExpense::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        // Categories: own store OR legacy rows created before multi-store (store_id NULL).
        $categories = DbExpenseCategory::where(function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->where('status', 1)
            ->orderBy('category_name')
            ->get();
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)
            ->orderBy('account_name')
            ->get();
        $paymentTypes = $this->paymentTypeOptions();

        return view('module.expenses.edit_expense', compact('expense', 'categories', 'accounts', 'paymentTypes'));
    }

    /**
     * Phase 5 (Item 8): Update — reverse OLD ledger effect then apply NEW effect,
     * atomically, with permission + store scope + closed-period lock + double-submit
     * guard (optimistic-lock atomic claim on ledger_version).
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_edit')) {
            abort(403, 'Unauthorized access to edit expenses.');
        }

        $storeId = current_store_id();

        $expense = DbExpense::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $validPaymentTypes = $this->paymentTypeOptions();

        $request->validate([
            'expense_date' => 'required|date',
            'category_id'  => [
                'required',
                Rule::exists('db_expense_category', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId);
                }),
            ],
            'expense_for'  => 'required|string|max:255',
            'expense_amt'  => 'required|numeric|min:0',
            'payment_type' => ['nullable', 'string', Rule::in($validPaymentTypes)],
            'account_id'   => [
                'nullable',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
        ]);

        $newAccountId = $request->account_id ? (int) $request->account_id : null;

        // Phase 2 (Item 4) applied to update: block if the ORIGINAL or NEW date/account
        // falls inside an already-closed reconciliation period.
        if ($this->isInClosedReconciliationPeriod($storeId, $expense->account_id ? (int) $expense->account_id : null, (string) $expense->expense_date)
            || $this->isInClosedReconciliationPeriod($storeId, $newAccountId, (string) $request->expense_date)) {
            return back()->with('error', 'This expense cannot be edited because its original or new date/account falls inside an already closed/adjusted cash reconciliation period.')->withInput();
        }

        try {
            DB::beginTransaction();

            // Double-submit guard: atomic claim. Only the request that still sees the
            // expected ledger_version may proceed; a concurrent duplicate sees 0 rows
            // affected and aborts without applying a second ledger effect.
            $expectedVersion = (int) $expense->ledger_version;
            $claimed = DbExpense::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->where('ledger_version', $expectedVersion)
                ->update(['ledger_version' => $expectedVersion + 1]);

            if ($claimed !== 1) {
                DB::rollBack();
                return back()->with('error', 'This expense was modified by another request. Please reload and try again.')->withInput();
            }

            // 1. Reverse OLD ledger/balance effect (reuses Phase 1 helper exactly).
            $this->reverseExpenseLedger($expense, $storeId, 'Expense Reversal (Edit Adjustment)');

            // 2. Persist edited fields.
            $expense->expense_date = $request->expense_date;
            $expense->category_id = $request->category_id;
            $expense->reference_no = $request->reference_no;
            $expense->expense_for = $request->expense_for;
            $expense->expense_amt = $request->expense_amt;
            $expense->payment_type = $request->payment_type ?? 'Cash';
            $expense->account_id = $newAccountId;
            $expense->note = $request->note;
            $expense->save();

            // 3. Apply NEW forward ledger/balance effect.
            $this->applyExpenseLedger($expense, $storeId);

            DB::commit();

            return redirect()->route('expenses.list')->with('success', 'Expense updated successfully with ledger adjustments.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update expense: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * Phase 1: reverse ledger/balance then soft-delete (delete_bit=1).
     * Phase 2: permission gate, store scoping, closed-period lock, atomic transition.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_delete')) {
            abort(403, 'Unauthorized access to delete expenses.');
        }

        $storeId = current_store_id();

        // Phase 2 (Item 3): store-scoped lookup.
        $expense = DbExpense::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->find($id);

        if (!$expense) {
            return back()->with('error', 'Expense not found.');
        }

        // Phase 2 (Item 4): block delete inside an already-closed reconciliation period.
        if ($this->isInClosedReconciliationPeriod($storeId, $expense->account_id ? (int) $expense->account_id : null, (string) $expense->expense_date)) {
            return back()->with('error', 'This expense cannot be deleted because its date/account falls inside an already closed/adjusted cash reconciliation period.');
        }

        try {
            DB::beginTransaction();

            // Atomic conditional transition eliminates concurrent double-delete.
            $affected = DbExpense::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                DB::rollBack();
                return back()->with('error', 'This expense was already deleted or is being processed.');
            }

            // Phase 1: reverse the ledger/balance effect (non-destructive).
            $this->reverseExpenseLedger($expense, $storeId, 'Expense Reversal (Delete)');

            DB::commit();

            return redirect()->route('expenses.list')->with('success', 'Expense deleted and reversed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            DbExpense::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
            return back()->with('error', 'Failed to delete expense: ' . $e->getMessage());
        }
    }
}
