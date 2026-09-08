<?php

namespace App\Http\Controllers;

use App\Models\DbVariant;
use Illuminate\Http\Request;

class VariantController extends Controller
{
    public function index(Request $request)
    {
        $query = DbVariant::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->status);
        }

        $sortField = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $variants = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DbVariant::count(),
            'active' => DbVariant::where('status', 1)->count(),
            'inactive' => DbVariant::where('status', 0)->count(),
        ];

        return view('module.items.variants_list', compact('variants', 'stats'));
    }

    public function create()
    {
        return view('module.items.add_variant');
    }

    public function store(Request $request)
    {
        $request->validate([
            'variant_name' => 'required|string|max:255|unique:db_variants,variant_name',
            'variant_code' => 'nullable|string|max:255|unique:db_variants,variant_code',
            'description' => 'nullable|string',
        ]);

        DbVariant::create([
            'variant_name' => $request->variant_name,
            'variant_code' => $request->variant_code,
            'description' => $request->description,
            'status' => 1,
            'store_id' => auth()->user()->store_id,
        ]);

        return redirect()->route('items.variants')->with('success', 'Variant created successfully.');
    }

    public function edit(DbVariant $variant)
    {
        return view('module.items.edit_variant', compact('variant'));
    }

    public function update(Request $request, DbVariant $variant)
    {
        $request->validate([
            'variant_name' => 'required|string|max:255|unique:db_variants,variant_name,' . $variant->id,
            'variant_code' => 'nullable|string|max:255|unique:db_variants,variant_code,' . $variant->id,
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $variant->update($request->only(['variant_name', 'variant_code', 'description', 'status']));

        return redirect()->route('items.variants')->with('success', 'Variant updated successfully.');
    }

    public function destroy(DbVariant $variant)
    {
        $variant->delete();
        return redirect()->back()->with('success', 'Variant deleted successfully.');
    }
}
