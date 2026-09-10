<?php

namespace App\Http\Controllers;

use App\Models\DbUnit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('units_view')) {
            abort(403, 'Unauthorized access to view units.');
        }

        // Phase 1 (store scoping): acting store's units PLUS shared (store_id NULL)
        // rows — mirrors PurchaseController/QuotationController read pattern.
        $storeId = current_store_id();
        $query = DbUnit::where(fn($w) => $w->where('store_id', $storeId)->orWhereNull('store_id'));

        // Server-side search (mirrors the Customers/Suppliers list pattern).
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('unit_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $units = $query->orderBy('id', 'asc')->paginate($limit)->withQueryString();

        return view('module.settings.units_list', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('units_add')) {
            abort(403, 'Unauthorized access to add units.');
        }

        $request->validate([
            'unit_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $unit = DbUnit::create([
            'store_id' => current_store_id(),
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
        if (auth()->check() && !auth()->user()->hasPermission('units_edit')) {
            abort(403, 'Unauthorized access to edit units.');
        }

        $request->validate([
            'unit_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $unit = DbUnit::where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->findOrFail($id);
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
    /**
     * Remove the specified resource from storage.
     *
     * Phase 2.4: in-use guard. db_items.unit_id references this row without an
     * FK, so deleting an in-use unit silently orphans every item that uses it.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('units_delete')) {
            abort(403, 'Unauthorized access to delete units.');
        }

        $unit = DbUnit::where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))
            ->findOrFail($id);

        $itemCount = \App\Models\DbItem::where('unit_id', $unit->id)->count();

        if ($itemCount > 0) {
            return redirect()->route('settings.units')
                ->with('error', "This unit cannot be deleted because it is used by {$itemCount} item(s). Reassign those items first or deactivate the unit.");
        }

        $unit->delete();

        return redirect()->route('settings.units')->with('success', 'Unit deleted successfully.');
    }
}
