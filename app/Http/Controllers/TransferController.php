<?php

namespace App\Http\Controllers;

use App\Models\AcAccount;
use App\Models\AcMoneyTransfer;
use App\Models\AcTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    /**
     * Item 2.1 & 2.2: List money transfers with store scoping and permission check.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_view')) {
            abort(403, 'Unauthorized access to view money transfers.');
        }

        $storeId = current_store_id();
        $query = AcMoneyTransfer::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['debitAccount', 'creditAccount', 'creator']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transfer_code', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if ($request->filled('transfer_date')) {
            $query->where('transfer_date', $request->transfer_date);
        }

        if ($request->filled('debit_account_id')) {
            $query->where('debit_account_id', $request->debit_account_id);
        }

        if ($request->filled('credit_account_id')) {
            $query->where('credit_account_id', $request->credit_account_id);
        }

        // Item 4.2: Export to CSV / Excel
        if ($request->export === 'csv' || $request->export === 'excel') {
            $allTransfers = $query->latest('id')->get();
            $fileName = 'money_transfers_' . date('Y-m-d_His') . '.csv';

            return response()->stream(function () use ($allTransfers) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'Transfer Code',
                    'Date',
                    'Reference No',
                    'Debit Acc (From)',
                    'Credit Acc (To)',
                    'Amount',
                    'Creator',
                    'Note',
                ]);

                foreach ($allTransfers as $tr) {
                    fputcsv($handle, [
                        $tr->transfer_code,
                        $tr->transfer_date,
                        $tr->reference_no ?? '',
                        $tr->debitAccount ? $tr->debitAccount->account_name : '',
                        $tr->creditAccount ? $tr->creditAccount->account_name : '',
                        $tr->amount,
                        $tr->creator ? $tr->creator->name : 'System',
                        $tr->note ?? '',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        // Item 4.2: Export to Print / PDF View
        if ($request->export === 'print' || $request->export === 'pdf') {
            $printTransfers = $query->latest('id')->get();

            return view('module.accounts.money_transfer_list_print', [
                'transfers' => $printTransfers,
                'totalAmount' => (float) $printTransfers->sum('amount'),
            ]);
        }

        // Item 3.4: Page size control with whitelist
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Item 3.3: Pagination preserves active query parameters
        $transfers = $query->latest('id')->paginate($perPage)->withQueryString();

        // Item 2.1: Store-scoped accounts for filter dropdowns
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name', 'asc')
            ->get();

        return view('module.accounts.money_transfer_list', compact('transfers', 'accounts'));
    }

    /**
     * Item 2.1 & 2.2: Show form to create money transfer.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_add')) {
            abort(403, 'Unauthorized access to create money transfers.');
        }

        $storeId = current_store_id();
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name', 'asc')
            ->get();

        // Generate Transfer Code
        $transferCode = \App\Services\CodeGeneratorService::generate('money_transfer');

        return view('module.accounts.add_transfer', compact('accounts', 'transferCode'));
    }

    /**
     * Item 1.1, 2.1, 2.2, 3.1: Store money transfer with TOCTOU lock and store scoping.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_add')) {
            abort(403, 'Unauthorized access to create money transfers.');
        }

        $storeId = current_store_id();

        $request->validate([
            'transfer_date' => 'required|date',
            'transfer_code' => [
                'required',
                'string',
                Rule::unique('ac_moneytransfer', 'transfer_code')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId);
                }),
            ],
            'debit_account_id' => [
                'required',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'credit_account_id' => [
                'required',
                'different:debit_account_id',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
        ]);

        // Resolve DNS outside the transaction to avoid holding a lock during a
        // potentially slow network call.
        $systemIp   = $request->ip();
        $systemName = $systemIp ? (@gethostbyaddr($systemIp) ?: 'unknown') : 'unknown';

        try {
            DB::beginTransaction();

            // ITEM 1.1: Lock source account for update INSIDE the transaction
            $sourceAccount = AcAccount::where('id', $request->debit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$sourceAccount || (float) $request->amount > (float) $sourceAccount->balance) {
                DB::rollBack();
                throw ValidationException::withMessages([
                    'amount' => 'Transfer amount cannot exceed the source account available balance of ' . format_currency((float) ($sourceAccount->balance ?? 0)) . '.',
                ]);
            }

            // Lock destination account
            $destAccount = AcAccount::where('id', $request->credit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            $currentDate = now()->format('Y-m-d');
            $currentTime = now()->format('H:i:s');
            // $systemIp / $systemName resolved before beginTransaction (see above).

            $transfer = AcMoneyTransfer::create([
                'store_id' => $storeId,
                'transfer_code' => $request->transfer_code,
                'transfer_date' => $request->transfer_date,
                'debit_account_id' => $request->debit_account_id,
                'credit_account_id' => $request->credit_account_id,
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

            // DEBIT Source Account (From)
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->transfer_date,
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $request->debit_account_id,
                'credit_account_id' => $request->credit_account_id,
                'debit_amt' => $request->amount,
                'credit_amt' => 0,
                'note' => 'Money Transfer (From): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            // CREDIT Destination Account (To)
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->transfer_date,
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $request->debit_account_id,
                'credit_account_id' => $request->credit_account_id,
                'debit_amt' => 0,
                'credit_amt' => $request->amount,
                'note' => 'Money Transfer (To): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            // Update Balances using locked instances
            $sourceAccount->balance -= $request->amount;
            $sourceAccount->save();

            $destAccount->balance += $request->amount;
            $destAccount->save();

            DB::commit();

            return redirect()->route('accounts.transfer')->with('success', 'Money Transfer successful.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error in transfer: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Item 4.1: Show form to edit an existing money transfer.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_edit')) {
            abort(403, 'Unauthorized access to edit money transfers.');
        }

        $storeId = current_store_id();
        $transfer = AcMoneyTransfer::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name', 'asc')
            ->get();

        return view('module.accounts.edit_transfer', compact('transfer', 'accounts'));
    }

    /**
     * Item 4.1: Update an existing money transfer with non-destructive ledger reversal + new forward entries.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_edit')) {
            abort(403, 'Unauthorized access to edit money transfers.');
        }

        $storeId = current_store_id();
        $transfer = AcMoneyTransfer::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $request->validate([
            'transfer_date' => 'required|date',
            'debit_account_id' => [
                'required',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'credit_account_id' => [
                'required',
                'different:debit_account_id',
                Rule::exists('ac_accounts', 'id')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
        ]);

        $oldDebitId = (int) $transfer->debit_account_id;
        $oldCreditId = (int) $transfer->credit_account_id;
        $oldAmount = (float) $transfer->amount;

        $newDebitId = (int) $request->debit_account_id;
        $newCreditId = (int) $request->credit_account_id;
        $newAmount = (float) $request->amount;

        // If financial parameters haven't changed, simply update metadata without ledger mutation
        if ($oldDebitId === $newDebitId && $oldCreditId === $newCreditId && abs($oldAmount - $newAmount) < 0.0001) {
            $transfer->update([
                'transfer_date' => $request->transfer_date,
                'reference_no' => $request->reference_no,
                'note' => $request->note,
            ]);

            return redirect()->route('accounts.transfer')->with('success', 'Money Transfer updated successfully.');
        }

        try {
            DB::beginTransaction();

            // Collect all involved accounts and lock them
            $involvedIds = array_values(array_unique([$oldDebitId, $oldCreditId, $newDebitId, $newCreditId]));
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
                return back()->with('error', "Cannot update transfer: Original destination account {$destName} does not have sufficient balance ({$destBal}) to reverse the original transferred amount (" . format_currency($oldAmount) . ").")->withInput();
            }

            // Temporarily calculate post-reversal balances
            $effectiveBalances = [];
            foreach ($lockedAccounts as $accId => $acc) {
                $effectiveBalances[$accId] = (float) $acc->balance;
            }

            // Apply old reversal to simulated balances
            $effectiveBalances[$oldDebitId] += $oldAmount;
            $effectiveBalances[$oldCreditId] -= $oldAmount;

            // 2. Solvency check on new source account for new amount
            if (($effectiveBalances[$newDebitId] ?? 0) < $newAmount) {
                DB::rollBack();
                $srcName = $lockedAccounts->get($newDebitId)->account_name;
                throw ValidationException::withMessages([
                    'amount' => "Transfer amount cannot exceed the source account available balance of " . format_currency((float) $effectiveBalances[$newDebitId]) . ".",
                ]);
            }

            // Apply new effect to simulated balances
            $effectiveBalances[$newDebitId] -= $newAmount;
            $effectiveBalances[$newCreditId] += $newAmount;

            // Commit balance changes to locked Eloquent instances
            foreach ($lockedAccounts as $accId => $acc) {
                $acc->balance = $effectiveBalances[$accId];
                $acc->save();
            }

            $currentDate = now()->format('Y-m-d');

            // Write 2 REVERSAL rows for old effect
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'TRANSFER REVERSAL',
                'debit_account_id' => $oldDebitId,
                'credit_account_id' => $oldCreditId,
                'debit_amt' => 0,
                'credit_amt' => $oldAmount,
                'note' => 'Money Transfer Reversal (Edit Adjustment - Refund Source): ' . $transfer->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'TRANSFER REVERSAL',
                'debit_account_id' => $oldDebitId,
                'credit_account_id' => $oldCreditId,
                'debit_amt' => $oldAmount,
                'credit_amt' => 0,
                'note' => 'Money Transfer Reversal (Edit Adjustment - Deduct Destination): ' . $transfer->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            // Write 2 NEW FORWARD rows for new effect
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->transfer_date,
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $newDebitId,
                'credit_account_id' => $newCreditId,
                'debit_amt' => $newAmount,
                'credit_amt' => 0,
                'note' => 'Money Transfer (From - Revised): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $request->transfer_date,
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $newDebitId,
                'credit_account_id' => $newCreditId,
                'debit_amt' => 0,
                'credit_amt' => $newAmount,
                'note' => 'Money Transfer (To - Revised): ' . $request->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            // Update transfer record
            $transfer->update([
                'transfer_date' => $request->transfer_date,
                'debit_account_id' => $newDebitId,
                'credit_account_id' => $newCreditId,
                'amount' => $newAmount,
                'reference_no' => $request->reference_no,
                'note' => $request->note,
            ]);

            DB::commit();

            return redirect()->route('accounts.transfer')->with('success', 'Money Transfer updated successfully with balanced ledger adjustments.');
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating transfer: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Item 1.2, 2.1, 2.2: Solvency-checked delete reversal with soft delete and non-destructive ledger records.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_delete')) {
            abort(403, 'Unauthorized access to delete money transfers.');
        }

        $storeId = current_store_id();

        // Item 2.1: Store-scoped lookup (IDOR protection)
        $transfer = AcMoneyTransfer::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->find($id);

        if (!$transfer) {
            return redirect()->route('accounts.transfer')->with('error', 'Money transfer not found.');
        }

        try {
            DB::beginTransaction();

            // Concurrency Fix: Atomic conditional update prevents concurrent double-refund race
            $affected = AcMoneyTransfer::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                DB::rollBack();
                return redirect()->route('accounts.transfer')->with('error', 'Money transfer not found or already deleted.');
            }

            // Lock both accounts
            $debitAcc = AcAccount::where('id', $transfer->debit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            $creditAcc = AcAccount::where('id', $transfer->credit_account_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$debitAcc || !$creditAcc) {
                DB::rollBack();
                return back()->with('error', 'Associated account(s) not found for this transfer.');
            }

            // Item 1.2 (c): Solvency check on destination account
            if ((float) $creditAcc->balance < (float) $transfer->amount) {
                DB::rollBack();
                return back()->with('error', "Cannot delete transfer: Destination account {$creditAcc->account_name} does not have sufficient balance (" . format_currency((float) $creditAcc->balance) . ") to reverse the transferred amount (" . format_currency((float) $transfer->amount) . ").");
            }

            // Item 1.2 (d): Balance reversal
            $debitAcc->balance += $transfer->amount;
            $debitAcc->save();

            $creditAcc->balance -= $transfer->amount;
            $creditAcc->save();

            $currentDate = now()->format('Y-m-d');

            // Item 1.2 (d): Insert TWO NEW reversal rows in ac_transactions
            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'TRANSFER REVERSAL',
                'debit_account_id' => $transfer->debit_account_id,
                'credit_account_id' => $transfer->credit_account_id,
                'debit_amt' => 0,
                'credit_amt' => $transfer->amount,
                'note' => 'Money Transfer Reversal (Refund Source): ' . $transfer->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            AcTransaction::create([
                'store_id' => $storeId,
                'transaction_date' => $currentDate,
                'transaction_type' => 'TRANSFER REVERSAL',
                'debit_account_id' => $transfer->debit_account_id,
                'credit_account_id' => $transfer->credit_account_id,
                'debit_amt' => $transfer->amount,
                'credit_amt' => 0,
                'note' => 'Money Transfer Reversal (Deduct Destination): ' . $transfer->note,
                'created_by' => Auth::id() ?: 1,
                'created_date' => $currentDate,
                'ref_moneytransfer_id' => $transfer->id,
            ]);

            DB::commit();

            return redirect()->route('accounts.transfer')->with('success', 'Transfer deleted and reversed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error deleting transfer: ' . $e->getMessage());
        }
    }

    /**
     * Item 4.3: Bulk delete with independent per-transfer solvency check and soft-delete reversal.
     */
    public function bulkDestroy(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('money_transfer_delete')) {
            abort(403, 'Unauthorized access to delete money transfers.');
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
            // IDOR protection: only find transfers belonging to current store
            $transfer = AcMoneyTransfer::where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->find($id);

            if (!$transfer) {
                // Ignore unknown or foreign store transfer IDs silently
                continue;
            }

            try {
                DB::beginTransaction();

                // Concurrency Fix: Atomic conditional update prevents concurrent double-refund race
                $affected = AcMoneyTransfer::where('id', $id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->update(['delete_bit' => 1]);

                if ($affected !== 1) {
                    DB::rollBack();
                    continue;
                }

                $debitAcc = AcAccount::where('id', $transfer->debit_account_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                $creditAcc = AcAccount::where('id', $transfer->credit_account_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                if (!$debitAcc || !$creditAcc) {
                    DB::rollBack();
                    $skipped[] = "{$transfer->transfer_code}: Associated accounts not found.";
                    continue;
                }

                // Solvency Check on Destination Account
                if ((float) $creditAcc->balance < (float) $transfer->amount) {
                    DB::rollBack();
                    $skipped[] = "{$transfer->transfer_code}: Destination account {$creditAcc->account_name} has insufficient balance (" . format_currency((float) $creditAcc->balance) . ") to reverse transfer amount (" . format_currency((float) $transfer->amount) . ").";
                    continue;
                }

                // Balance reversal
                $debitAcc->balance += $transfer->amount;
                $debitAcc->save();

                $creditAcc->balance -= $transfer->amount;
                $creditAcc->save();

                $currentDate = now()->format('Y-m-d');

                // Insert 2 reversal rows in ac_transactions
                AcTransaction::create([
                    'store_id' => $storeId,
                    'transaction_date' => $currentDate,
                    'transaction_type' => 'TRANSFER REVERSAL',
                    'debit_account_id' => $transfer->debit_account_id,
                    'credit_account_id' => $transfer->credit_account_id,
                    'debit_amt' => 0,
                    'credit_amt' => $transfer->amount,
                    'note' => 'Money Transfer Reversal (Bulk - Refund Source): ' . $transfer->note,
                    'created_by' => Auth::id() ?: 1,
                    'created_date' => $currentDate,
                    'ref_moneytransfer_id' => $transfer->id,
                ]);

                AcTransaction::create([
                    'store_id' => $storeId,
                    'transaction_date' => $currentDate,
                    'transaction_type' => 'TRANSFER REVERSAL',
                    'debit_account_id' => $transfer->debit_account_id,
                    'credit_account_id' => $transfer->credit_account_id,
                    'debit_amt' => $transfer->amount,
                    'credit_amt' => 0,
                    'note' => 'Money Transfer Reversal (Bulk - Deduct Destination): ' . $transfer->note,
                    'created_by' => Auth::id() ?: 1,
                    'created_date' => $currentDate,
                    'ref_moneytransfer_id' => $transfer->id,
                ]);

                DB::commit();
                $deletedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $skipped[] = "{$transfer->transfer_code}: Error during reversal: " . $e->getMessage();
            }
        }

        $msg = "Bulk Delete: {$deletedCount} transfer(s) deleted and reversed.";
        if (!empty($skipped)) {
            $msg .= " Skipped " . count($skipped) . " transfer(s): " . implode('; ', $skipped);
            return redirect()->route('accounts.transfer')->with('warning', $msg);
        }

        return redirect()->route('accounts.transfer')->with('success', $msg);
    }
}
