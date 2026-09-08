<?php

namespace App\Http\Controllers;

use App\Models\DbCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = DbCategory::query();

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
        $categories = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DbCategory::count(),
            'active' => DbCategory::where('status', 1)->count(),
            'inactive' => DbCategory::where('status', 0)->count(),
        ];

        return view('module.items.categories_list', compact('categories', 'stats'));
    }

    public function create()
    {
        return view('module.items.add_category');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255|unique:db_category,category_name',
            'category_code' => 'nullable|string|max:255|unique:db_category,category_code',
            'description' => 'nullable|string',
        ]);

        $category = DbCategory::create([
            'category_name' => $request->category_name,
            'category_code' => $request->category_code,
            'description' => $request->description,
            'status' => 1,
            'store_id' => auth()->user()->store_id ?? 1,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $category->id,
                'name' => $category->category_name,
                'message' => 'Category created successfully.'
            ]);
        }

        return redirect()->route('items.categories')->with('success', 'Category created successfully.');
    }

    public function edit(DbCategory $category)
    {
        return view('module.items.edit_category', compact('category'));
    }

    public function update(Request $request, DbCategory $category)
    {
        $request->validate([
            'category_name' => 'required|string|max:255|unique:db_category,category_name,' . $category->id,
            'category_code' => 'nullable|string|max:255|unique:db_category,category_code,' . $category->id,
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        $category->update($request->only(['category_name', 'category_code', 'description', 'status']));

        return redirect()->route('items.categories')->with('success', 'Category updated successfully.');
    }

    public function destroy(DbCategory $category)
    {
        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
