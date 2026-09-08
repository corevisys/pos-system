<?php

namespace App\Http\Controllers;

use App\Models\DbExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DbExpenseCategory::orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('category_name', 'like', "%{$search}%")
                  ->orWhere('category_code', 'like', "%{$search}%");
        }

        $categories = $query->paginate(10);

        return view('module.expenses.categories_list', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('module.expenses.add_category');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255|unique:db_expense_category,category_name',
            'category_code' => 'nullable|string|max:255|unique:db_expense_category,category_code',
            'description'   => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $category = new DbExpenseCategory();
            $category->store_id = current_store_id();
            $category->category_name = $request->category_name;
            $category->category_code = $request->category_code;
            $category->description = $request->description;
            $category->created_by = auth()->id() ?? 1;
            $category->status = $request->status ?? 1;
            $category->save();

            DB::commit();

            return redirect()->route('expenses.categories')->with('success', 'Category created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create category: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $category = DbExpenseCategory::findOrFail($id);
        return view('module.expenses.edit_category', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'required|string|max:255|unique:db_expense_category,category_name,' . $id,
            'category_code' => 'nullable|string|max:255|unique:db_expense_category,category_code,' . $id,
            'description'   => 'nullable|string',
            'status'        => 'required|in:1,0',
        ]);

        try {
            DB::beginTransaction();

            $category = DbExpenseCategory::findOrFail($id);
            $category->category_name = $request->category_name;
            $category->category_code = $request->category_code;
            $category->description = $request->description;
            $category->status = $request->status;
            $category->save();

            DB::commit();

            return redirect()->route('expenses.categories')->with('success', 'Category updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update category: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $category = DbExpenseCategory::findOrFail($id);
            $category->delete();

            return redirect()->route('expenses.categories')->with('success', 'Category deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete category: ' . $e->getMessage());
        }
    }
}
