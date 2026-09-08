<?php

namespace App\Http\Controllers;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\User;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Item 1.1, 1.2, 1.3, 2.1, 3.1, 3.2, 4.1, 4.2, 4.3:
     * Multi-store scoped transaction journal with dual-permission gate, eager-loaded creator,
     * dynamic transaction types, extended search, pagination query preservation, per_page whitelist,
     * and CSV/PDF export.
     */
    public function index(Request $request)
    {
        // Item 1.3: Dual permission check honoring both modern and legacy slugs
        if (auth()->check() && !auth()->user()->hasPermission('accounts_cash_transactions') && !auth()->user()->hasPermission('cash_transactions')) {
            abort(403, 'Unauthorized access to cash transactions.');
        }

        // Item 1.1: Store-scoped base query
        $storeId = current_store_id();

        // Item 2.1: Eager-load creator relationship alongside debitAccount and creditAccount to eliminate N+1
        $query = AcTransaction::with(['debitAccount', 'creditAccount', 'creator'])
            ->where('store_id', $storeId);

        // Date Filter
        if ($request->filled('transaction_date')) {
            $query->where('transaction_date', $request->transaction_date);
        }

        // Item 3.1: Transaction Type Filter
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Account Filter (involving either debit or credit side)
        if ($request->filled('account_id')) {
            $accountId = $request->account_id;
            $query->where(function ($q) use ($accountId) {
                $q->where('debit_account_id', $accountId)
                    ->orWhere('credit_account_id', $accountId);
            });
        }

        // User / Creator Filter
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Item 3.2: Extended Search matching note, payment_code, and account names
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                    ->orWhere('payment_code', 'like', "%{$search}%")
                    ->orWhereHas('debitAccount', function ($sq) use ($search) {
                        $sq->where('account_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creditAccount', function ($sq) use ($search) {
                        $sq->where('account_name', 'like', "%{$search}%");
                    });
            });
        }

        // Item 4.3: Handle CSV/Excel Export (reusing the same store-scoped, filtered query)
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $allTransactions = $query->latest('id')->get();
            $filename = 'cash_transactions_' . date('Y_m_d_His') . '.csv';

            return response()->stream(function () use ($allTransactions) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, ['Date', 'Type', 'Payment Code', 'Debit Account', 'Credit Account', 'Note', 'Debit Amount', 'Credit Amount', 'User']);

                foreach ($allTransactions as $tr) {
                    fputcsv($handle, [
                        $tr->transaction_date,
                        $tr->transaction_type,
                        $tr->payment_code ?? 'CASH',
                        $tr->debitAccount ? $tr->debitAccount->account_name : '',
                        $tr->creditAccount ? $tr->creditAccount->account_name : '',
                        $tr->note ?? '',
                        number_format((float) $tr->debit_amt, 2, '.', ''),
                        number_format((float) $tr->credit_amt, 2, '.', ''),
                        $tr->creator ? $tr->creator->name : 'System',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Item 4.3: Handle PDF/Print Export
        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $allTransactions = $query->latest('id')->get();
            $currencySymbol = function_exists('currency') ? currency() : '$';
            return view('module.accounts.cash_transactions_print', [
                'transactions' => $allTransactions,
                'currencySymbol' => $currencySymbol,
            ]);
        }

        // Item 4.2: Whitelist per_page to {10, 25, 50, 100}
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Item 4.1: Append ->withQueryString()
        $transactions = $query->latest('id')->paginate($perPage)->withQueryString();

        // Item 1.2: Store-scoped accounts & users for filter dropdowns
        $accounts = AcAccount::where('store_id', $storeId)
            ->where('status', 1)
            ->where('delete_bit', 0)->where('is_system', 0)
            ->orderBy('account_name')
            ->get();

        $users = User::where('store_id', $storeId)
            ->orderBy('name')
            ->get();

        // Item 3.1: Dynamic transaction types for the current store
        $transactionTypes = AcTransaction::where('store_id', $storeId)
            ->whereNotNull('transaction_type')
            ->where('transaction_type', '!=', '')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type');

        $currencySymbol = function_exists('currency') ? currency() : '$';

        return view('module.accounts.cash_transactions', compact('transactions', 'accounts', 'users', 'transactionTypes', 'currencySymbol'));
    }
}
