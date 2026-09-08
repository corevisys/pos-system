<?php

namespace App\Http\Controllers;

use App\Models\DbBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = DbBrand::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->status);
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = $request->get('per_page', 10);
        $brands = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DbBrand::count(),
            'active' => DbBrand::where('status', 1)->count(),
            'inactive' => DbBrand::where('status', 0)->count(),
        ];

        return view('module.items.brands_list', compact('brands', 'stats'));
    }

    public function create()
    {
        return view('module.items.add_brand');
    }

    public function store(Request $request)
    {
        $request->validate([
            'brand_name' => 'required|string|max:255|unique:db_brands,brand_name',
            'brand_code' => 'nullable|string|max:255|unique:db_brands,brand_code',
            'description' => 'nullable|string',
        ]);

        $brand = DbBrand::create([
            'brand_name' => $request->brand_name,
            'brand_code' => $request->brand_code,
            'description' => $request->description,
            'status' => 1,
            'store_id' => auth()->user()->store_id ?? 1,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $brand->id,
                'name' => $brand->brand_name,
                'message' => 'Brand created successfully.'
            ]);
        }

        return redirect()->route('items.brands')->with('success', 'Brand created successfully.');
    }

    public function edit(DbBrand $brand)
    {
        return view('module.items.edit_brand', compact('brand'));
    }

    public function update(Request $request, DbBrand $brand)
    {
        $request->validate([
            'brand_name' => 'required|string|max:255|unique:db_brands,brand_name,' . $brand->id,
            'brand_code' => 'nullable|string|max:255|unique:db_brands,brand_code,' . $brand->id,
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $brand->update($request->only(['brand_name', 'brand_code', 'description', 'status']));

        return redirect()->route('items.brands')->with('success', 'Brand updated successfully.');
    }

    public function destroy(DbBrand $brand)
    {
        $brand->delete();

        return redirect()->back()->with('success', 'Brand deleted successfully.');
    }
}
