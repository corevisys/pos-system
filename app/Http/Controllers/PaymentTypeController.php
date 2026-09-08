<?php

namespace App\Http\Controllers;

use App\Models\DbPaymentType;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paymentTypes = DbPaymentType::all();
        return view('module.settings.payment_types', compact('paymentTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
        $request->validate([
            'payment_type' => 'required|string|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        $paymentType = DbPaymentType::findOrFail($id);
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
        $paymentType = DbPaymentType::findOrFail($id);
        $paymentType->delete();

        return redirect()->route('settings.payment_types')->with('success', 'Payment Type deleted successfully.');
    }
}
