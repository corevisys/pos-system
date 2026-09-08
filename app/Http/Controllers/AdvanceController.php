<?php

namespace App\Http\Controllers;

use App\Models\DbCustAdvance;
use App\Models\DbCustomer;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdvanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DbCustAdvance::with('customer')
            ->where('store_id', current_store_id())
            ->orderBy('id', 'desc');

        if ($request->filled('date')) {
            $query->where('payment_date', $request->date);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $advances = $query->paginate(10);
        $customers = DbCustomer::select('id', 'customer_name', 'customer_code')->get();

        return view('module.advance.advance_list', compact('advances', 'customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = DbCustomer::select('id', 'customer_name', 'customer_code')->get();
        $accounts = AcAccount::where('status', 1)->where('delete_bit', 0)->get();
        return view('module.advance.add_advance', compact('customers', 'accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'customer_id' => 'required|exists:db_customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|string',
            'account_id' => 'required|exists:ac_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $storeId = current_store_id();

            // Generate Payment Code
            $count_id = (DbCustAdvance::where('store_id', $storeId)->max('count_id') ?? 0) + 1;
            $payment_code = \App\Services\CodeGeneratorService::generate('customer_advance');

            $advance = new DbCustAdvance();
            $advance->store_id = $storeId;
            $advance->count_id = $count_id;
            $advance->payment_code = $payment_code;
            $advance->payment_date = $request->payment_date;
            $advance->customer_id = $request->customer_id;
            $advance->amount = $request->amount;
            $advance->payment_type = $request->payment_type;
            $advance->account_id = $request->account_id;
            $advance->note = $request->note;
            $advance->created_by = auth()->id() ?? 1;
            $advance->created_date = date('Y-m-d');
            $advance->created_time = date('H:i:s');
            $advance->system_ip = $request->ip();
            $advance->system_name = gethostname();
            $advance->status = 1;
            $advance->save();

            // Update Customer Total Advance
            $customer = DbCustomer::find($request->customer_id);
            if ($customer) {
                $customer->tot_advance += $request->amount;
                $customer->save();
            }

            AcTransaction::create([
                'store_id' => $advance->store_id,
                'transaction_date' => $advance->payment_date,
                'transaction_type' => 'CUSTOMER ADVANCE',
                'payment_code' => $advance->payment_type,
                'credit_account_id' => $advance->account_id,
                'credit_amt' => $advance->amount,
                'note' => 'Customer Advance: ' . $advance->payment_code,
                'customer_id' => $advance->customer_id,
                'short_code' => $advance->payment_code,
                'created_by' => auth()->id() ?? 1,
                'created_date' => date('Y-m-d'),
            ]);

            AcAccount::whereKey($advance->account_id)->increment('balance', $advance->amount);

            DB::commit();

            return redirect()->route('advance.list')->with('success', 'Advance payment added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to add advance payment: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $advance = DbCustAdvance::where('store_id', current_store_id())->findOrFail($id);
        $customers = DbCustomer::select('id', 'customer_name', 'customer_code')->get();
        $accounts = AcAccount::where('status', 1)->where('delete_bit', 0)->get();
        return view('module.advance.add_advance', compact('advance', 'customers', 'accounts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'customer_id' => 'required|exists:db_customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|string',
            'account_id' => 'required|exists:ac_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $advance = DbCustAdvance::where('store_id', current_store_id())->findOrFail($id);
            $oldAmount = (float) $advance->amount;
            $newAmount = (float) $request->amount;
            $oldCustomerId = $advance->customer_id;
            $newCustomerId = $request->customer_id;
            $oldAccountId = $advance->account_id;
            $newAccountId = $request->account_id;

            // 1. Customer advance balance adjustment with underflow protection
            $oldCustomer = DbCustomer::find($oldCustomerId);
            if ($oldCustomer) {
                if ($oldCustomerId == $newCustomerId) {
                    $difference = $newAmount - $oldAmount;
                    if ($difference < 0 && (float) $oldCustomer->tot_advance < abs($difference)) {
                        DB::rollBack();
                        return back()->with('error', 'Cannot reduce advance amount. Customer has already consumed this advance in sales (available advance: ' . format_currency($oldCustomer->tot_advance) . ').')->withInput();
                    }
                    $oldCustomer->tot_advance += $difference;
                    $oldCustomer->save();
                } else {
                    if ((float) $oldCustomer->tot_advance < $oldAmount) {
                        DB::rollBack();
                        return back()->with('error', 'Cannot change customer. Original customer has already consumed this advance in sales (available advance: ' . format_currency($oldCustomer->tot_advance) . ').')->withInput();
                    }
                    $oldCustomer->tot_advance -= $oldAmount;
                    $oldCustomer->save();

                    $newCustomer = DbCustomer::find($newCustomerId);
                    if ($newCustomer) {
                        $newCustomer->tot_advance += $newAmount;
                        $newCustomer->save();
                    }
                }
            } else {
                $newCustomer = DbCustomer::find($newCustomerId);
                if ($newCustomer) {
                    $newCustomer->tot_advance += $newAmount;
                    $newCustomer->save();
                }
            }

            // 2. AcTransaction & AcAccount balance synchronization
            $transaction = AcTransaction::where('transaction_type', 'CUSTOMER ADVANCE')
                ->where('store_id', $advance->store_id)
                ->where(function($q) use ($advance) {
                    $q->where('short_code', $advance->payment_code)
                      ->orWhere('note', 'Customer Advance: ' . $advance->payment_code);
                })
                ->first();

            if ($transaction) {
                // Revert old account balance
                if ($transaction->credit_account_id) {
                    AcAccount::whereKey($transaction->credit_account_id)->decrement('balance', $transaction->credit_amt);
                }

                // Update transaction
                $transaction->transaction_date = $request->payment_date;
                $transaction->payment_code = $request->payment_type;
                $transaction->credit_account_id = $newAccountId;
                $transaction->credit_amt = $newAmount;
                $transaction->customer_id = $newCustomerId;
                $transaction->short_code = $advance->payment_code;
                $transaction->note = 'Customer Advance: ' . $advance->payment_code;
                $transaction->save();

                // Increment new account balance
                AcAccount::whereKey($newAccountId)->increment('balance', $newAmount);
            } else {
                // Create missing transaction and increment new account
                AcTransaction::create([
                    'store_id' => $advance->store_id,
                    'transaction_date' => $request->payment_date,
                    'transaction_type' => 'CUSTOMER ADVANCE',
                    'payment_code' => $request->payment_type,
                    'credit_account_id' => $newAccountId,
                    'credit_amt' => $newAmount,
                    'note' => 'Customer Advance: ' . $advance->payment_code,
                    'customer_id' => $newCustomerId,
                    'short_code' => $advance->payment_code,
                    'created_by' => auth()->id() ?? 1,
                    'created_date' => date('Y-m-d'),
                ]);

                AcAccount::whereKey($newAccountId)->increment('balance', $newAmount);
            }

            // 3. Update advance record
            $advance->payment_date = $request->payment_date;
            $advance->customer_id = $newCustomerId;
            $advance->amount = $newAmount;
            $advance->payment_type = $request->payment_type;
            $advance->account_id = $newAccountId;
            $advance->note = $request->note;
            $advance->save();

            DB::commit();

            return redirect()->route('advance.list')->with('success', 'Advance payment updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update advance payment: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $advance = DbCustAdvance::where('store_id', current_store_id())->findOrFail($id);
            $customer = DbCustomer::find($advance->customer_id);

            // Check if advance has already been consumed against sales
            if ($customer && (float) $customer->tot_advance < (float) $advance->amount) {
                DB::rollBack();
                return back()->with('error', 'Cannot delete advance payment. This advance has already been consumed against sales (available advance: ' . format_currency($customer->tot_advance) . ').');
            }

            // Revert customer advance balance
            if ($customer) {
                $customer->tot_advance -= $advance->amount;
                $customer->save();
            }

            // Revert AcTransaction and AcAccount balance
            $transaction = AcTransaction::where('transaction_type', 'CUSTOMER ADVANCE')
                ->where('store_id', $advance->store_id)
                ->where(function($q) use ($advance) {
                    $q->where('short_code', $advance->payment_code)
                      ->orWhere('note', 'Customer Advance: ' . $advance->payment_code);
                })
                ->first();

            if ($transaction) {
                if ($transaction->credit_account_id) {
                    AcAccount::whereKey($transaction->credit_account_id)->decrement('balance', $transaction->credit_amt);
                }
                $transaction->delete();
            }

            $advance->delete();

            DB::commit();

            return redirect()->route('advance.list')->with('success', 'Advance payment deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete advance payment: ' . $e->getMessage());
        }
    }
}
