<?php

namespace App\Http\Controllers;

use App\Models\DbUnit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $units = DbUnit::all();
        return view('module.settings.units_list', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $unit = DbUnit::create([
            'store_id' => auth()->user()->store_id ?? 1,
            'unit_name' => $request->unit_name,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $unit->id,
                'name' => $unit->unit_name,
                'message' => 'Unit created successfully.'
            ]);
        }

        return redirect()->route('settings.units')->with('success', 'Unit created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'unit_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $unit = DbUnit::findOrFail($id);
        $unit->update([
            'unit_name' => $request->unit_name,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        return redirect()->route('settings.units')->with('success', 'Unit updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $unit = DbUnit::findOrFail($id);
        $unit->delete();

        return redirect()->route('settings.units')->with('success', 'Unit deleted successfully.');
    }
}
