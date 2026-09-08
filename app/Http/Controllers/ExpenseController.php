<?php

namespace App\Http\Controllers;

use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DbExpense::with(['category', 'account'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('expense_code', 'like', "%{$search}%")
                  ->orWhere('expense_for', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        $expenses = $query->paginate(10);

        return view('module.expenses.expenses_list', compact('expenses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = DbExpenseCategory::where('status', 1)->get();
        // Assuming we want to list accounts, if AcAccount exists. 
        // If not, we might need to create it or just use an empty collection.
        // I created AcAccount model, so this should work if the table exists (it was in migration).
        $accounts = AcAccount::where('status', 1)->get(); 

        return view('module.expenses.create_expense', compact('categories', 'accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category_id'  => 'required|exists:db_expense_category,id',
            'expense_for'  => 'required|string|max:255',
            'expense_amt'  => 'required|numeric|min:0',
            'account_id'   => 'nullable|exists:ac_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $count_id = (DbExpense::max('count_id') ?? 0) + 1;
            $expense_code = \App\Services\CodeGeneratorService::generate('expense');

            $expense = new DbExpense();
            $expense->store_id = current_store_id(); // Resolved from auth user or store settings
            $expense->count_id = $count_id;
            $expense->expense_code = $expense_code;
            $expense->expense_date = $request->expense_date;
            $expense->category_id = $request->category_id;
            $expense->reference_no = $request->reference_no;
            $expense->expense_for = $request->expense_for;
            $expense->expense_amt = $request->expense_amt;
            $expense->payment_type = $request->payment_type ?? 'Cash'; // e.g., Cash, Bank
            $expense->account_id = $request->account_id;
            $expense->note = $request->note;
            $expense->created_by = auth()->id() ?? 1;
            $expense->created_date = date('Y-m-d');
            $expense->created_time = date('H:i:s');
            $expense->system_ip = $request->ip();
            $expense->system_name = gethostname();
            $expense->status = 1;
            $expense->save();

            // Create Ledger Transaction & Decrement Account Balance
            if ($expense->account_id && $expense->expense_amt > 0) {
                AcTransaction::create([
                    'store_id' => $expense->store_id ?? 1,
                    'transaction_date' => $expense->expense_date,
                    'transaction_type' => 'EXPENSE',
                    'payment_code' => $expense->payment_type ?? 'Cash',
                    'debit_account_id' => $expense->account_id,
                    'credit_account_id' => null,
                    'debit_amt' => $expense->expense_amt,
                    'credit_amt' => 0,
                    'note' => 'Expense: ' . $expense->expense_for . ' (' . $expense->expense_code . ')',
                    'ref_expense_id' => $expense->id,
                    'created_by' => auth()->id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);

                $acc = AcAccount::find($expense->account_id);
                if ($acc) {
                    $acc->decrement('balance', $expense->expense_amt);
                }
            }

            DB::commit();

            return redirect()->route('expenses.list')->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create expense: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $expense = DbExpense::findOrFail($id);

            // Revert Account Balances and Delete Matching AcTransactions
            $transactions = AcTransaction::where('ref_expense_id', $expense->id)->get();
            foreach ($transactions as $tx) {
                if ($tx->debit_account_id && $tx->debit_amt > 0) {
                    $acc = AcAccount::find($tx->debit_account_id);
                    if ($acc) {
                        $acc->increment('balance', $tx->debit_amt);
                    }
                }
            }
            AcTransaction::where('ref_expense_id', $expense->id)->delete();

            $expense->delete();

            DB::commit();

            return redirect()->route('expenses.list')->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete expense: ' . $e->getMessage());
        }
    }
}

