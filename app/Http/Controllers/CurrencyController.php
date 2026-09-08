<?php

namespace App\Http\Controllers;

use App\Models\DbCurrency;
use App\Models\DbStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currencies = DbCurrency::orderBy('status', 'desc')->orderBy('currency_name', 'asc')->get();
        $activeCurrency = $currencies->firstWhere('status', 1);
        return view('module.settings.currency_list', compact('currencies', 'activeCurrency'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'currency_name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:10',
            'symbol' => 'required|string|max:10',
            'status' => 'required|integer|in:0,1',
        ]);

        $shouldActivate = ((int) $request->status === 1) || (DbCurrency::count() === 0);

        DB::transaction(function () use ($request, $shouldActivate, &$currency) {
            $currency = DbCurrency::create([
                'currency_name' => $request->currency_name,
                'currency_code' => $request->currency_code,
                'currency' => $request->currency_name,
                'symbol' => $request->symbol,
                'status' => 0,
            ]);

            if ($shouldActivate) {
                DbCurrency::activateCurrency($currency->id);
                $currency->refresh();
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Currency created successfully.',
                'currency' => $currency,
            ]);
        }

        return redirect()->route('settings.currency')->with('success', 'Currency created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'currency_name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:10',
            'symbol' => 'required|string|max:10',
            'status' => 'required|integer|in:0,1',
        ]);

        $currency = DbCurrency::findOrFail($id);
        $newStatus = (int) $request->status;

        // Disallow direct deactivation of the currently active currency
        if ($currency->status == 1 && $newStatus === 0) {
            $errorMessage = 'Cannot deactivate the active currency. Activate a different currency instead, which will automatically deactivate this one.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }
            return redirect()->route('settings.currency')->with('error', $errorMessage);
        }

        DB::transaction(function () use ($currency, $request, $newStatus) {
            $currency->update([
                'currency_name' => $request->currency_name,
                'currency_code' => $request->currency_code,
                'currency' => $request->currency_name,
                'symbol' => $request->symbol,
                'status' => $newStatus,
            ]);

            if ($newStatus === 1) {
                DbCurrency::activateCurrency($currency->id);
                $currency->refresh();
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Currency updated successfully.',
                'currency' => $currency,
            ]);
        }

        return redirect()->route('settings.currency')->with('success', 'Currency updated successfully.');
    }

    /**
     * Atomically activate a currency and deactivate all others.
     */
    public function activate(Request $request, $id)
    {
        $currency = DbCurrency::activateCurrency((int) $id);

        $message = "Currency '{$currency->currency_name}' activated successfully as the system currency.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => $message,
                'currency' => $currency,
            ]);
        }

        return redirect()->route('settings.currency')->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $currency = DbCurrency::findOrFail($id);

        if ($currency->status == 1) {
            $errorMessage = 'Cannot delete the active currency. Activate a different currency first.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }
            return redirect()->route('settings.currency')->with('error', $errorMessage);
        }

        $currency->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Currency deleted successfully.',
            ]);
        }

        return redirect()->route('settings.currency')->with('success', 'Currency deleted successfully.');
    }
}
