<?php

namespace App\Http\Controllers;

use App\Models\DbPaymentType;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('payment_types_view')) {
            abort(403, 'Unauthorized access to view payment types.');
        }

        // Phase 1 (store scoping): acting store's types PLUS shared (store_id NULL)
        // rows — mirrors PurchaseController/QuotationController read pattern.
        $storeId = current_store_id();
        $query = DbPaymentType::where(fn($w) => $w->where('store_id', $storeId)->orWhereNull('store_id'));

        // Phase 4.1: server-side search + pagination (Units/Tax list pattern),
        // replacing the decorative dead search box and absent pagination.
        if ($request->filled('search')) {
            $query->where('payment_type', 'like', '%' . $request->input('search') . '%');
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $paymentTypes = $query->orderBy('id', 'asc')->paginate($limit)->withQueryString();

        return view('module.settings.payment_types', compact('paymentTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('payment_types_add')) {
            abort(403, 'Unauthorized access to add payment types.');
        }

        $request->validate([
            'payment_type' => 'required|string|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        DbPaymentType::create([
            'store_id' => current_store_id(),
            'payment_type' => $request->payment_type,
            'status' => $request->status,
        ]);

        return redirect()->route('settings.payment_types')->with('success', 'Payment Type created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('payment_types_edit')) {
            abort(403, 'Unauthorized access to edit payment types.');
        }

        $request->validate([
            'payment_type' => 'required|string|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        $paymentType = DbPaymentType::where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->findOrFail($id);

        // Phase 2.5: server-side CASH guard (the Blade @if can be bypassed by a
        // direct POST). The literal 'Cash' is load-bearing for Expenses/ledger.
        if (strtoupper(trim((string) $paymentType->payment_type)) === 'CASH') {
            return redirect()->route('settings.payment_types')
                ->with('error', 'The CASH payment type is protected and cannot be renamed.');
        }

        $paymentType->update([
            'payment_type' => $request->payment_type,
            'status' => $request->status,
        ]);

        return redirect()->route('settings.payment_types')->with('success', 'Payment Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('payment_types_delete')) {
            abort(403, 'Unauthorized access to delete payment types.');
        }

        $paymentType = DbPaymentType::where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->findOrFail($id);

        // Phase 2.5: server-side CASH guard + reference warning. The type is stored
        // as a string on db_expense/ac_transactions (no FK), so we warn rather than
        // block for those, but the canonical CASH literal is never deletable.
        if (strtoupper(trim((string) $paymentType->payment_type)) === 'CASH') {
            return redirect()->route('settings.payment_types')
                ->with('error', 'The CASH payment type is protected and cannot be deleted.');
        }

        $expenseCount = \App\Models\DbExpense::where('payment_type', $paymentType->payment_type)->count();
        if ($expenseCount > 0) {
            return redirect()->route('settings.payment_types')
                ->with('error', "This payment type cannot be deleted because it is referenced by {$expenseCount} expense(s). Reassign those expenses first.");
        }

        $paymentType->delete();

        return redirect()->route('settings.payment_types')->with('success', 'Payment Type deleted successfully.');
    }
}
