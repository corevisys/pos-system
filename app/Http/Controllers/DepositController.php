<?php

namespace App\Http\Controllers;

use App\Models\AcAccount;
use App\Models\AcMoneyDeposit;
use App\Models\AcTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepositController extends Controller
{
    /**
     * Item 2.1, 2.2, 3.2, 3.3, 4.2: List deposits with store scoping, permission checks,
     * pagination query preservation, per_page whitelist, and CSV/PDF export.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_view')) {
            abort(403, 'Unauthorized access to money deposits.');
        }

        $storeId = current_store_id();

        $query = AcMoneyDeposit::with(['debitAccount', 'creditAccount', 'creator'])
            ->where('store_id', $storeId)
            ->where('delete_bit', 0);

        // Date Filter
        if ($request->filled('deposit_date')) {
            $query->where('deposit_date', $request->deposit_date);
        }

        // Debit Account Filter
        if ($request->filled('debit_account_id')) {
            $query->where('debit_account_id', $request->debit_account_id);
        }

        // Credit Account Filter
        if ($request->filled('credit_account_id')) {
            $query->where('credit_account_id', $request->credit_account_id);
        }

        // Search Filter (reference_no, note, or account names)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('debitAccount', function ($sq) use ($search) {
                        $sq->where('account_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creditAccount', function ($sq) use ($search) {
                        $sq->where('account_name', 'like', "%{$search}%");
                    });
            });
        }

        // Item 4.2: Handle CSV/Excel Export
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $allDeposits = $query->latest('id')->get();
            $filename = 'deposit_list_' . date('Y_m_d_His') . '.csv';

            return response()->stream(function () use ($allDeposits) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, ['Deposit Date', 'Reference No', 'Debit Account', 'Credit Account', 'Amount', 'Creator', 'Note']);

                foreach ($allDeposits as $dep) {
                    fputcsv($handle, [
                        $dep->deposit_date,
                        $dep->reference_no ?? '',
                        $dep->debitAccount ? $dep->debitAccount->account_name : 'External',
                        $dep->creditAccount ? $dep->creditAccount->account_name : '',
                        number_format((float) $dep->amount, 2, '.', ''),
                        $dep->creator ? $dep->creator->name : 'System',
                        $dep->note ?? '',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Item 4.2: Handle PDF/Print Export
        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $allDeposits = $query->latest('id')->get();
            $currencySymbol = function_exists('currency') ? currency() : '$';
            return view('module.accounts.deposit_list_print', [
                'deposits' => $allDeposits,
                'currencySymbol' => $currencySymbol,
            ]);
        }

        // Item 3.3: Whitelist per_page to {10, 25, 50, 100}
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Item 3.2: Query string preserved on pagination
        $deposits = $query->latest('id')->paginate($perPage)->withQueryString();

        // Item 2.1: Store-scoped active accounts for filter dropdowns
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name')
            ->get();

        $currencySymbol = function_exists('currency') ? currency() : '$';

        return view('module.accounts.deposit_list', compact('deposits', 'accounts', 'currencySymbol'));
    }

    /**
     * Item 2.1, 2.2: Create view with store scoping and permission check.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_add')) {
            abort(403, 'Unauthorized access to add money deposits.');
        }

        $storeId = current_store_id();

        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name')
            ->get();

        $currencySymbol = function_exists('currency') ? currency() : '$';

        return view('module.accounts.add_deposit', compact('accounts', 'currencySymbol'));
    }

    /**
     * Item 1.1, 1.2, 2.1, 2.2: Balanced double-entry ledger creation with store-scoped
     * contra account, row-level locking, and overdraft protection on source account.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_add')) {
            abort(403, 'Unauthorized access to add money deposits.');
        }

        $storeId = current_store_id();

        $request->validate([
            'deposit_date' => 'required|date',
            'credit_account_id' => [
                'required',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'debit_account_id' => [
                'nullable',
                'different:credit_account_id',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ], [
            'debit_account_id.different' => 'The debit account and credit account must be different.',
        ]);

        // Resolve DNS outside the transaction to avoid holding a lock during a
        // potentially slow network call.
        $systemIp   = $request->ip() ?? '127.0.0.1';
        $systemName = $systemIp ? (@gethostbyaddr($systemIp) ?: 'unknown') : 'unknown';

        try {
            DB::beginTransaction();

            $currentDate = now()->format('Y-m-d');
            $currentTime = now()->format('H:i:s');

            // Item 1.2: Lock destination account
            $creditAcc = AcAccount::where('id', $request->credit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->firstOrFail();

            // Item 1.1 (a) & (b): Handle internal-source vs external deposit
            if ($request->filled('debit_account_id')) {
                // Internal-source deposit: Lock source account and enforce zero-overdraft hard block
                $debitAcc = AcAccount::where('id', $request->debit_account_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((float) $request->amount > (float) $debitAcc->balance) {
                    DB::rollBack();
                    throw ValidationException::withMessages([
                        'amount' => 'Deposit amount cannot exceed the source account available balance of ' . format_currency((float) $debitAcc->balance) . '.',
                    ]);
                }

                // Deduct source balance
                $debitAcc->balance -= $request->amount;
                $debitAcc->save();

                $contraOrDebitId = $debitAcc->id;
            } else {
                // External deposit: Find or auto-create store-scoped "External Deposit Clearing" contra account
                $clearingAcc = $this->getOrCreateExternalDepositClearingAccount($storeId);
                $contraOrDebitId = $clearingAcc->id;
            }

            // Increment destination account balance
            $creditAcc->balance += $request->amount;
            $creditAcc->save();

            // Create deposit record
            $deposit = AcMoneyDeposit::create([
                'store_id' => $storeId,
                'deposit_date' => $request->deposit_date,
                'debit_account_id' => $request->debit_account_id ?: null,
                'credit_account_id' => $creditAcc->id,
                'amount' => $request->amount,
                'note' => $request->note,
                'reference_no' => $request->reference_no,
                'created_date' => $currentDate,
                'created_time' => $currentTime,
                'created_by' => Auth::id() ?: 1,
                'system_ip' => $systemIp,
                'system_name' => $systemName,
                'status' => 1,
                'delete_bit' => 0,
            ]);

            // Item 1.1: Write TWO balanced double-entry rows in ac_transactions
            // Row 1: Source / Contra Debit Row
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->deposit_date,
                'transaction_type' => 'DEPOSIT',
                'debit_account_id' => $contraOrDebitId,
                'credit_account_id' => $creditAcc->id,
                'debit_amt' => $request->amount,
                'credit_amt' => 0,
                'note' => $request->note ?: 'Money Deposit (Debit/Source)',
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            // Row 2: Destination Credit Row (Preserves downstream reporting and Cash Reconciliation contracts)
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->deposit_date,
                'transaction_type' => 'DEPOSIT',
                'debit_account_id' => $contraOrDebitId,
                'credit_account_id' => $creditAcc->id,
                'debit_amt' => 0,
                'credit_amt' => $request->amount,
                'note' => $request->note ?: 'Money Deposit (Credit/Destination)',
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            DB::commit();

            return redirect()->route('accounts.deposit')->with('success', 'Deposit added successfully with balanced double-entry ledger records.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error adding deposit: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Item 4.1: Render edit deposit view with store scoping and permission check.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_edit')) {
            abort(403, 'Unauthorized access to edit money deposits.');
        }

        $storeId = current_store_id();

        $deposit = AcMoneyDeposit::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name')
            ->get();

        $currencySymbol = function_exists('currency') ? currency() : '$';

        return view('module.accounts.edit_deposit', compact('deposit', 'accounts', 'currencySymbol'));
    }

    /**
     * Item 4.1: Update deposit with audit-safe non-destructive ledger reversals and reapply flow.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_edit')) {
            abort(403, 'Unauthorized access to edit money deposits.');
        }

        $storeId = current_store_id();

        $deposit = AcMoneyDeposit::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $request->validate([
            'deposit_date' => 'required|date',
            'credit_account_id' => [
                'required',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'debit_account_id' => [
                'nullable',
                'different:credit_account_id',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
            'reference_no' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ], [
            'debit_account_id.different' => 'The debit account and credit account must be different.',
        ]);

        $oldDebitId = $deposit->debit_account_id ? (int) $deposit->debit_account_id : null;
        $oldCreditId = (int) $deposit->credit_account_id;
        $oldAmount = (float) $deposit->amount;

        $newDebitId = $request->filled('debit_account_id') ? (int) $request->debit_account_id : null;
        $newCreditId = (int) $request->credit_account_id;
        $newAmount = (float) $request->amount;

        // If financial parameters are untouched, update metadata only
        if ($oldDebitId === $newDebitId && $oldCreditId === $newCreditId && abs($oldAmount - $newAmount) < 0.0001) {
            $deposit->update([
                'deposit_date' => $request->deposit_date,
                'reference_no' => $request->reference_no,
                'note' => $request->note,
            ]);

            return redirect()->route('accounts.deposit')->with('success', 'Deposit updated successfully.');
        }

        try {
            DB::beginTransaction();

            $clearingAcc = $this->getOrCreateExternalDepositClearingAccount($storeId);

            $oldSourceAccountId = $oldDebitId ?: $clearingAcc->id;
            $newSourceAccountId = $newDebitId ?: $clearingAcc->id;

            // Collect all involved accounts and lock them in sorted ascending order to prevent deadlocks
            $involvedIds = array_values(array_unique(array_filter([
                $oldSourceAccountId,
                $oldCreditId,
                $newSourceAccountId,
                $newCreditId,
            ])));
            sort($involvedIds);

            $lockedAccounts = AcAccount::where('store_id', $storeId)
                ->whereIn('id', $involvedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 1. Solvency check on old destination account reversal
            $oldCreditAcc = $lockedAccounts->get($oldCreditId);
            if (!$oldCreditAcc || (float) $oldCreditAcc->balance < $oldAmount) {
                DB::rollBack();
                $destName = $oldCreditAcc ? $oldCreditAcc->account_name : "Account #{$oldCreditId}";
                $destBal = $oldCreditAcc ? format_currency((float) $oldCreditAcc->balance) : '0.00';
                return back()->with('error', "Cannot update deposit: Original destination account {$destName} does not have sufficient balance ({$destBal}) to reverse the original deposited amount (" . format_currency($oldAmount) . ").")->withInput();
            }

            // Simulate post-reversal balances
            $effectiveBalances = [];
            foreach ($lockedAccounts as $accId => $acc) {
                $effectiveBalances[$accId] = (float) $acc->balance;
            }

            $effectiveBalances[$oldCreditId] -= $oldAmount;
            if ($oldDebitId && isset($effectiveBalances[$oldDebitId])) {
                $effectiveBalances[$oldDebitId] += $oldAmount;
            }

            // 2. Overdraft check on new source account (when internal source is used)
            if ($newDebitId) {
                if (($effectiveBalances[$newDebitId] ?? 0) < $newAmount) {
                    DB::rollBack();
                    $srcName = $lockedAccounts->get($newDebitId)->account_name;
                    throw ValidationException::withMessages([
                        'amount' => "Deposit amount cannot exceed the source account available balance of " . format_currency((float) $effectiveBalances[$newDebitId]) . ".",
                    ]);
                }
            }

            // Apply new effect to simulated balances
            if ($newDebitId) {
                $effectiveBalances[$newDebitId] -= $newAmount;
            }
            $effectiveBalances[$newCreditId] += $newAmount;

            // Commit simulated balance changes to real accounts (skip clearing contra account balance)
            foreach ($lockedAccounts as $accId => $acc) {
                if ($accId !== $clearingAcc->id) {
                    $acc->balance = $effectiveBalances[$accId];
                    $acc->save();
                }
            }

            $currentDate = now()->format('Y-m-d');

            // Write 2 REVERSAL rows for old effect
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'DEPOSIT REVERSAL',
                'debit_account_id' => $oldSourceAccountId,
                'credit_account_id' => $oldCreditId,
                'debit_amt' => 0,
                'credit_amt' => $oldAmount,
                'note' => 'Deposit Reversal (Edit Adjustment - Refund Source): ' . $deposit->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'DEPOSIT REVERSAL',
                'debit_account_id' => $oldSourceAccountId,
                'credit_account_id' => $oldCreditId,
                'debit_amt' => $oldAmount,
                'credit_amt' => 0,
                'note' => 'Deposit Reversal (Edit Adjustment - Deduct Destination): ' . $deposit->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            // Write 2 NEW FORWARD rows for new effect
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->deposit_date,
                'transaction_type' => 'DEPOSIT',
                'debit_account_id' => $newSourceAccountId,
                'credit_account_id' => $newCreditId,
                'debit_amt' => $newAmount,
                'credit_amt' => 0,
                'note' => 'Money Deposit (Revised - Source): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->deposit_date,
                'transaction_type' => 'DEPOSIT',
                'debit_account_id' => $newSourceAccountId,
                'credit_account_id' => $newCreditId,
                'debit_amt' => 0,
                'credit_amt' => $newAmount,
                'note' => 'Money Deposit (Revised - Destination): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            // Update deposit record
            $deposit->update([
                'deposit_date' => $request->deposit_date,
                'debit_account_id' => $newDebitId,
                'credit_account_id' => $newCreditId,
                'amount' => $newAmount,
                'reference_no' => $request->reference_no,
                'note' => $request->note,
            ]);

            DB::commit();

            return redirect()->route('accounts.deposit')->with('success', 'Deposit updated successfully with balanced ledger adjustments.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating deposit: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Item 1.3: Solvency-checked delete reversal with soft delete, atomic conditional transition,
     * and non-destructive ledger reversals.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_delete')) {
            abort(403, 'Unauthorized access to delete money deposits.');
        }

        $storeId = current_store_id();

        // Item 2.1: Store-scoped lookup (IDOR protection)
        $deposit = AcMoneyDeposit::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->find($id);

        if (!$deposit) {
            return redirect()->route('accounts.deposit')->with('error', 'Money deposit not found.');
        }

        try {
            DB::beginTransaction();

            // Item 1.3: Atomic conditional state transition eliminates concurrent double-delete race
            $affected = AcMoneyDeposit::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                DB::rollBack();
                return redirect()->route('accounts.deposit')->with('error', 'Money deposit not found or already deleted.');
            }

            // Lock destination account
            $creditAcc = AcAccount::where('id', $deposit->credit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$creditAcc) {
                DB::rollBack();
                AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
                return back()->with('error', 'Destination account not found for this deposit.');
            }

            // Item 1.3 (d): Solvency check on destination account
            if ((float) $creditAcc->balance < (float) $deposit->amount) {
                DB::rollBack();
                AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
                return back()->with('error', "Cannot delete deposit: Destination account {$creditAcc->account_name} does not have sufficient balance (" . format_currency((float) $creditAcc->balance) . ") to reverse the deposited amount (" . format_currency((float) $deposit->amount) . ").");
            }

            // Reverse destination balance
            $creditAcc->balance -= $deposit->amount;
            $creditAcc->save();

            // Reverse source balance if internal source was used
            if ($deposit->debit_account_id) {
                $debitAcc = AcAccount::where('id', $deposit->debit_account_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                if ($debitAcc) {
                    $debitAcc->balance += $deposit->amount;
                    $debitAcc->save();
                }
                $contraOrDebitId = $deposit->debit_account_id;
            } else {
                $clearingAcc = $this->getOrCreateExternalDepositClearingAccount($storeId);
                $contraOrDebitId = $clearingAcc->id;
            }

            $currentDate = now()->format('Y-m-d');

            // Item 1.3 (e): Write TWO non-destructive DEPOSIT REVERSAL rows in ac_transactions
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'DEPOSIT REVERSAL',
                'debit_account_id' => $contraOrDebitId,
                'credit_account_id' => $deposit->credit_account_id,
                'debit_amt' => 0,
                'credit_amt' => $deposit->amount,
                'note' => 'Money Deposit Reversal (Refund Source): ' . $deposit->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'DEPOSIT REVERSAL',
                'debit_account_id' => $contraOrDebitId,
                'credit_account_id' => $deposit->credit_account_id,
                'debit_amt' => $deposit->amount,
                'credit_amt' => 0,
                'note' => 'Money Deposit Reversal (Deduct Destination): ' . $deposit->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneydeposits_id' => $deposit->id,
            ]);

            DB::commit();

            return redirect()->route('accounts.deposit')->with('success', 'Deposit deleted and reversed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
            return back()->with('error', 'Error deleting deposit: ' . $e->getMessage());
        }
    }

    /**
     * Item 4.3: Bulk delete with independent per-deposit solvency check and soft-delete reversal.
     */
    public function bulkDestroy(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_deposit_delete')) {
            abort(403, 'Unauthorized access to delete money deposits.');
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $storeId = current_store_id();
        $ids = $request->input('ids', []);
        $deletedCount = 0;
        $skipped = [];

        foreach ($ids as $id) {
            // IDOR protection: query store-scoped deposit
            $deposit = AcMoneyDeposit::where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->find($id);

            if (!$deposit) {
                continue;
            }

            try {
                DB::beginTransaction();

                // Atomic conditional transition
                $affected = AcMoneyDeposit::where('id', $id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->update(['delete_bit' => 1]);

                if ($affected !== 1) {
                    DB::rollBack();
                    continue;
                }

                $creditAcc = AcAccount::where('id', $deposit->credit_account_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                if (!$creditAcc) {
                    DB::rollBack();
                    AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
                    $skipped[] = "Ref #{$deposit->reference_no}: Destination account not found.";
                    continue;
                }

                // Solvency Check on Destination Account
                if ((float) $creditAcc->balance < (float) $deposit->amount) {
                    DB::rollBack();
                    AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
                    $refStr = $deposit->reference_no ? "Ref #{$deposit->reference_no}" : "ID #{$deposit->id}";
                    $skipped[] = "{$refStr}: Destination account {$creditAcc->account_name} has insufficient balance (" . format_currency((float) $creditAcc->balance) . ") to reverse deposit amount (" . format_currency((float) $deposit->amount) . ").";
                    continue;
                }

                // Balance reversal
                $creditAcc->balance -= $deposit->amount;
                $creditAcc->save();

                if ($deposit->debit_account_id) {
                    $debitAcc = AcAccount::where('id', $deposit->debit_account_id)
                        ->where('store_id', $storeId)
                        ->lockForUpdate()
                        ->first();

                    if ($debitAcc) {
                        $debitAcc->balance += $deposit->amount;
                        $debitAcc->save();
                    }
                    $contraOrDebitId = $deposit->debit_account_id;
                } else {
                    $clearingAcc = $this->getOrCreateExternalDepositClearingAccount($storeId);
                    $contraOrDebitId = $clearingAcc->id;
                }

                $currentDate = now()->format('Y-m-d');

                // Insert 2 reversal rows in ac_transactions
                AcTransaction::create([
                    'store_id' => $storeId,
                    'transaction_date' => $currentDate,
                    'transaction_type' => 'DEPOSIT REVERSAL',
                    'debit_account_id' => $contraOrDebitId,
                    'credit_account_id' => $deposit->credit_account_id,
                    'debit_amt' => 0,
                    'credit_amt' => $deposit->amount,
                    'note' => 'Money Deposit Reversal (Bulk - Refund Source): ' . $deposit->note,
                    'created_by' => Auth::id() ?: 1,
                    'created_date' => $currentDate,
                    'ref_moneydeposits_id' => $deposit->id,
                ]);

                AcTransaction::create([
                    'store_id' => $storeId,
                    'transaction_date' => $currentDate,
                    'transaction_type' => 'DEPOSIT REVERSAL',
                    'debit_account_id' => $contraOrDebitId,
                    'credit_account_id' => $deposit->credit_account_id,
                    'debit_amt' => $deposit->amount,
                    'credit_amt' => 0,
                    'note' => 'Money Deposit Reversal (Bulk - Deduct Destination): ' . $deposit->note,
                    'created_by' => Auth::id() ?: 1,
                    'created_date' => $currentDate,
                    'ref_moneydeposits_id' => $deposit->id,
                ]);

                DB::commit();
                $deletedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                AcMoneyDeposit::where('id', $id)->where('store_id', $storeId)->update(['delete_bit' => 0]);
                $skipped[] = "ID #{$deposit->id}: Error during reversal: " . $e->getMessage();
            }
        }

        $msg = "Bulk Delete: {$deletedCount} deposit(s) deleted and reversed.";
        if (!empty($skipped)) {
            $msg .= " Skipped " . count($skipped) . " deposit(s): " . implode('; ', $skipped);
            return redirect()->route('accounts.deposit')->with('warning', $msg);
        }

        return redirect()->route('accounts.deposit')->with('success', $msg);
    }

    /**
     * Item 1.1 (b): Find or auto-create store-scoped "External Deposit Clearing" contra account.
     */
    protected function getOrCreateExternalDepositClearingAccount(int $storeId): AcAccount
    {
        // Phase C: shared static find-or-create with lockForUpdate + unique index +
        // re-select backstop (see AcAccount::findOrCreateSystemAccount).
        return AcAccount::findOrCreateSystemAccount($storeId, 'external_deposit_clearing', 'External Deposit Clearing');
    }
}
