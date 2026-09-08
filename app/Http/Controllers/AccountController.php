<?php

namespace App\Http\Controllers;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_view')) {
            abort(403, 'Unauthorized access to view accounts.');
        }

        $storeId = current_store_id();
        $query = AcAccount::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->with(['parent', 'creator'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('account_name', 'like', "%{$search}%")
                  ->orWhere('account_code', 'like', "%{$search}%");
            });
        }

        // Export current filtered/store-scoped result set as CSV / Excel
        if ($request->export === 'csv' || $request->export === 'excel') {
            $exportAccounts = $query->get();
            $filename = "accounts_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportAccounts) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Account Code', 'Account Name', 'Parent Account', 'Balance', 'Created By', 'Status']);

                foreach ($exportAccounts as $account) {
                    fputcsv($file, [
                        $account->account_code,
                        $account->account_name,
                        $account->parent ? $account->parent->account_name : '---',
                        (float) $account->balance,
                        $account->creator ? $account->creator->name : 'System',
                        $account->status == 1 ? 'Active' : 'Inactive',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        // Print/PDF export view (mirrors CustomerController/SaleController print pattern)
        if ($request->export === 'print' || $request->export === 'pdf') {
            $printAccounts = $query->get();

            return view('module.accounts.accounts_list_print', [
                'accounts' => $printAccounts,
                'totalBalance' => (float) $printAccounts->sum('balance'),
            ]);
        }

        // Whitelist page size {10, 25, 50, 100} with fallback to 10
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $accounts = $query->paginate($perPage)->withQueryString();

        return view('module.accounts.accounts_list', compact('accounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_add')) {
            abort(403, 'Unauthorized access to create account.');
        }

        // Fetch parent accounts for the dropdown, scoped to current store
        $parentAccounts = AcAccount::where('store_id', current_store_id())
            ->where('delete_bit', 0)
            ->where('status', 1)
            ->get();
        
        // Generate new Account Number
        $accountNumber = \App\Services\CodeGeneratorService::generate('account');

        return view('module.accounts.add_account', compact('parentAccounts', 'accountNumber'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_add')) {
            abort(403, 'Unauthorized access to create account.');
        }

        $storeId = current_store_id();

        $request->validate([
            'account_name' => 'required|string|max:255',
            'account_number' => [
                'required',
                'string',
                Rule::unique('ac_accounts', 'account_code')->where(fn($q) => $q->where('store_id', $storeId)->where('delete_bit', 0)),
            ],
            'opening_balance' => 'numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $parentId = $request->parent_account;
            $sortCode = '';

            if (empty($parentId)) {
                $parentId = null; // Ensure null in DB
                $maxId = AcAccount::max('id') ?? 0;
                $sortCode = $maxId + 1;
            } else {
                $parentAccount = AcAccount::where('store_id', $storeId)->findOrFail($parentId);
                $siblingCount = AcAccount::where('parent_id', $parentId)->count();
                $sortCode = $parentAccount->sort_code . '.' . ($siblingCount + 1);
            }

            $currentDate = now()->format('Y-m-d');
            $currentTime = now()->format('H:i:s');
            $systemIp = $request->ip();
            $systemName = gethostbyaddr($systemIp) ?: 'unknown';

            $account = new AcAccount();
            $account->count_id = (AcAccount::max('count_id') ?? 0) + 1;
            $account->store_id = $storeId;
            $account->parent_id = $parentId;
            $account->account_name = $request->account_name;
            $account->account_code = $request->account_number;
            $account->sort_code = $sortCode;
            $account->balance = $request->opening_balance ?? 0;
            $account->note = $request->note;
            $account->created_by = auth()->id() ?? 1;
            $account->created_date = $currentDate;
            $account->created_time = $currentTime;
            $account->system_ip = $systemIp;
            $account->system_name = $systemName;
            $account->delete_bit = 0;
            $account->status = 1;
            $account->save();

            // Record Opening Balance Transaction with balanced double-entry contra account
            if ($request->opening_balance > 0) {
                // Phase C: find-or-create the Opening Balance Equity contra account for this
                // store under a lock + (store_id, system_key) unique index + re-select backstop.
                $equityAccount = AcAccount::findOrCreateSystemAccount($storeId, 'opening_balance_equity', 'Opening Balance Equity');

                AcTransaction::create([
                    'store_id' => $storeId,
                    'transaction_date' => $currentDate,
                    'transaction_type' => 'OPENING BALANCE',
                    'debit_account_id' => $equityAccount->id,
                    'credit_account_id' => $account->id,
                    'debit_amt' => $request->opening_balance,
                    'credit_amt' => $request->opening_balance,
                    'note' => 'Opening Balance',
                    'created_by' => auth()->id() ?? 1,
                    'created_date' => $currentDate,
                    'ref_accounts_id' => $account->id,
                ]);
            }

            DB::commit();

            return redirect()->route('accounts.list')->with('success', 'Account created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_edit')) {
            abort(403, 'Unauthorized access to edit account.');
        }

        $storeId = current_store_id();
        $account = AcAccount::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $parentAccounts = AcAccount::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->where('status', 1)
            ->where('id', '!=', $id)
            ->get();

        return view('module.accounts.edit_account', compact('account', 'parentAccounts'));
    }

    /**
     * Update the specified account in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_edit')) {
            abort(403, 'Unauthorized access to edit account.');
        }

        $storeId = current_store_id();
        $account = AcAccount::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->findOrFail($id);

        $request->validate([
            'account_name' => 'required|string|max:255',
            'parent_account' => [
                'nullable',
                Rule::exists('ac_accounts', 'id')->where(fn($q) => $q->where('store_id', $storeId)->where('delete_bit', 0)),
                function ($attribute, $value, $fail) use ($id) {
                    if ($value == $id) {
                        $fail('An account cannot be its own parent.');
                    }
                },
            ],
            'note' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $oldParentId = $account->parent_id;
            $newParentId = !empty($request->parent_account) ? (int)$request->parent_account : null;
            $oldSortCode = $account->sort_code;

            // Editable fields: Account Name and Note
            // NOTE: opening_balance is intentionally read-only and ignored to prevent ledger desync
            $account->account_name = $request->account_name;
            $account->note = $request->note;

            // If Parent Account has changed, recompute hierarchy sort_code and cascade to descendants
            if ($oldParentId !== $newParentId) {
                if (empty($newParentId)) {
                    $maxId = AcAccount::max('id') ?? 0;
                    $newSortCode = (string)($maxId + 1);
                } else {
                    $parentAccount = AcAccount::where('store_id', $storeId)->findOrFail($newParentId);
                    $siblingCount = AcAccount::where('parent_id', $newParentId)->where('id', '!=', $account->id)->count();
                    $newSortCode = $parentAccount->sort_code . '.' . ($siblingCount + 1);
                }

                $account->parent_id = $newParentId;
                $account->sort_code = $newSortCode;
                $account->save();

                // Cascade-update sort_code prefix for existing descendants
                if (!empty($oldSortCode) && $oldSortCode !== $newSortCode) {
                    $descendants = AcAccount::where('sort_code', 'like', $oldSortCode . '.%')->get();
                    foreach ($descendants as $descendant) {
                        $descendant->sort_code = $newSortCode . substr($descendant->sort_code, strlen($oldSortCode));
                        $descendant->save();
                    }
                }
            } else {
                $account->save();
            }

            DB::commit();

            return redirect()->route('accounts.list')->with('success', 'Account updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update account: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Check if an account has any blocking downstream dependencies that prevent deletion.
     *
     * @param int $accountId
     * @return array List of failure reasons
     */
    public function accountHasBlockingDependencies($accountId, int $storeId): array
    {
        $failures = [];
        $account = AcAccount::where('store_id', $storeId)->find($accountId);
        if (!$account) {
            return ['Account not found.'];
        }

        // a) Non-zero balance check
        if ((float)$account->balance != 0.0) {
            $failures[] = 'Account has a non-zero balance (' . number_format($account->balance, 2) . ').';
        }

        // b) Ledger transactions check (ac_transactions)
        $hasTransactions = AcTransaction::where(function($q) use ($accountId) {
            $q->where('debit_account_id', $accountId)
              ->orWhere('credit_account_id', $accountId);
        })->exists();

        if ($hasTransactions) {
            $failures[] = 'Account has associated transaction records in the ledger.';
        }

        // c) Downstream module records checks
        if (Schema::hasTable('ac_moneytransfer')) {
            $hasTransfer = DB::table('ac_moneytransfer')->where(function($q) use ($accountId) {
                $q->where('debit_account_id', $accountId)
                  ->orWhere('credit_account_id', $accountId);
            })->exists();

            if ($hasTransfer) {
                $failures[] = 'Account has associated money transfer records.';
            }
        }

        if (Schema::hasTable('ac_moneydeposits')) {
            $hasDeposit = DB::table('ac_moneydeposits')->where(function($q) use ($accountId) {
                $q->where('debit_account_id', $accountId)
                  ->orWhere('credit_account_id', $accountId);
            })->exists();

            if ($hasDeposit) {
                $failures[] = 'Account has associated deposit records.';
            }
        }

        if (Schema::hasTable('cash_drawer_reconciliations')) {
            $hasReconciliation = DB::table('cash_drawer_reconciliations')->where('account_id', $accountId)->exists();
            if ($hasReconciliation) {
                $failures[] = 'Account has associated cash drawer reconciliation records.';
            }
        }

        if (Schema::hasTable('db_salespayments')) {
            $hasSalesPayment = DB::table('db_salespayments')->where('account_id', $accountId)->exists();
            if ($hasSalesPayment) {
                $failures[] = 'Account has associated sales payment records.';
            }
        }

        if (Schema::hasTable('db_purchasepayments')) {
            $hasPurchasePayment = DB::table('db_purchasepayments')->where('account_id', $accountId)->exists();
            if ($hasPurchasePayment) {
                $failures[] = 'Account has associated purchase payment records.';
            }
        }

        if (Schema::hasTable('db_expense')) {
            $hasExpense = DB::table('db_expense')->where('account_id', $accountId)->exists();
            if ($hasExpense) {
                $failures[] = 'Account has associated expense records.';
            }
        }

        if (Schema::hasTable('db_salespaymentsreturn')) {
            $hasSalesReturnPayment = DB::table('db_salespaymentsreturn')->where('account_id', $accountId)->exists();
            if ($hasSalesReturnPayment) {
                $failures[] = 'Account has associated sales return payment records.';
            }
        }

        if (Schema::hasTable('db_purchasepaymentsreturn')) {
            $hasPurchaseReturnPayment = DB::table('db_purchasepaymentsreturn')->where('account_id', $accountId)->exists();
            if ($hasPurchaseReturnPayment) {
                $failures[] = 'Account has associated purchase return payment records.';
            }
        }

        // d) Active child accounts check
        $hasChildAccounts = AcAccount::where('store_id', $storeId)->where('parent_id', $accountId)->where('delete_bit', 0)->exists();
        if ($hasChildAccounts) {
            $failures[] = 'Account has active child accounts nested under it.';
        }

        return $failures;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_delete')) {
            abort(403, 'Unauthorized access to delete account.');
        }

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — mirror Transfer/Deposit/Reconciliation.
        $account = AcAccount::where('store_id', $storeId)->where('delete_bit', 0)->find($id);
        if (!$account) {
            return redirect()->route('accounts.list')->with('error', 'Account not found.');
        }

        try {
            // Block delete if any downstream blocking dependencies exist
            $blockingFailures = $this->accountHasBlockingDependencies($id, $storeId);
            if (!empty($blockingFailures)) {
                return redirect()->route('accounts.list')->with('error', 'Cannot delete account: ' . implode(' ', $blockingFailures));
            }

            // Atomic conditional transition — prevents concurrent double-delete race.
            $affected = AcAccount::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                return redirect()->route('accounts.list')->with('error', 'Account not found or already deleted.');
            }

            return redirect()->route('accounts.list')->with('success', 'Account deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete account: ' . $e->getMessage());
        }
    }

    /**
     * Remove multiple accounts from storage.
     */
    public function bulkDestroy(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('accounts_delete')) {
            abort(403, 'Unauthorized access to delete accounts.');
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
            $account = AcAccount::where('store_id', $storeId)->find($id);
            if (!$account) {
                continue;
            }

            if ($account->delete_bit == 1) {
                // Already deleted (idempotent)
                continue;
            }

            $blockingFailures = $this->accountHasBlockingDependencies($id, $storeId);
            if (!empty($blockingFailures)) {
                $skipped[] = "{$account->account_name} ({$account->account_code}): " . implode(' ', $blockingFailures);
            } else {
                // Atomic conditional transition — only one concurrent request can flip delete_bit.
                $affected = AcAccount::where('id', $id)
                    ->where('store_id', $storeId)
                    ->where('delete_bit', 0)
                    ->update(['delete_bit' => 1]);

                if ($affected === 1) {
                    $deletedCount++;
                }
            }
        }

        $msg = "Bulk Delete: {$deletedCount} account(s) deleted.";
        if (!empty($skipped)) {
            $msg .= " Skipped " . count($skipped) . " account(s): " . implode('; ', $skipped);
            return redirect()->route('accounts.list')->with('warning', $msg);
        }

        return redirect()->route('accounts.list')->with('success', $msg);
    }
}
