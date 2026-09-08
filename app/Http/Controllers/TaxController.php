<?php

namespace App\Http\Controllers;

use App\Models\DbTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $taxes = DbTax::where('group_bit', 0)->get();
        $taxGroups = DbTax::where('group_bit', 1)->get();
        
        return view('module.settings.tax_list', compact('taxes', 'taxGroups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tax_name' => 'required|string|max:255',
            'group_bit' => 'required|integer|in:0,1',
            'status' => 'required|integer|in:0,1',
            'tax' => 'nullable|numeric|min:0',
            'subtax_ids_array' => 'nullable|array',
        ]);

        $tax_rate = $request->tax ?? 0;
        $subtax_ids = null;

        if ($request->group_bit == 1 && $request->has('subtax_ids_array')) {
            $subtax_ids = implode(',', $request->subtax_ids_array);
            $tax_rate = DbTax::whereIn('id', $request->subtax_ids_array)->sum('tax');
        }

        $tax = DbTax::create([
            'store_id' => auth()->user()->store_id ?? 1,
            'tax_name' => $request->tax_name,
            'tax' => $tax_rate,
            'group_bit' => $request->group_bit,
            'subtax_ids' => $subtax_ids,
            'status' => $request->status,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $tax->id,
                'name' => $tax->tax_name . ' (' . $tax->tax . '%)',
                'message' => 'Tax created successfully.'
            ]);
        }

        return redirect()->route('settings.tax')->with('success', 'Tax created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'tax_name' => 'required|string|max:255',
            'status' => 'required|integer|in:0,1',
            'tax' => 'nullable|numeric|min:0',
            'subtax_ids_array' => 'nullable|array',
        ]);

        $tax = DbTax::findOrFail($id);
        $tax_rate = $request->tax ?? 0;
        $subtax_ids = null;

        if ($tax->group_bit == 1 && $request->has('subtax_ids_array')) {
            $subtax_ids = implode(',', $request->subtax_ids_array);
            $tax_rate = DbTax::whereIn('id', $request->subtax_ids_array)->sum('tax');
        }

        $tax->update([
            'tax_name' => $request->tax_name,
            'tax' => ($tax->group_bit == 1) ? $tax_rate : $request->tax,
            'status' => $request->status,
            'subtax_ids' => $subtax_ids,
        ]);

        return redirect()->route('settings.tax')->with('success', 'Tax updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tax = DbTax::findOrFail($id);
        $tax->delete();

        return redirect()->route('settings.tax')->with('success', 'Tax deleted successfully.');
    }
}
