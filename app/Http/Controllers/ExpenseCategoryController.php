<?php

namespace App\Http\Controllers;

use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Phase 2-style permission gate (view) using the seeded category slug.
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_view')) {
            abort(403, 'Unauthorized access to view expense categories.');
        }

        // Phase 2-style store scoping.
        $query = DbExpenseCategory::where('store_id', current_store_id())
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('category_name', 'like', "%{$search}%")
                    ->orWhere('category_code', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $categories = $query->paginate($perPage)->withQueryString();

        return view('module.expenses.categories_list', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_add')) {
            abort(403, 'Unauthorized access to add expense categories.');
        }

        return view('module.expenses.add_category');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_add')) {
            abort(403, 'Unauthorized access to add expense categories.');
        }

        $storeId = current_store_id();

        $request->validate([
            'category_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_expense_category', 'category_name')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId);
                }),
            ],
            'category_code' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $category = new DbExpenseCategory();
            $category->store_id = $storeId;
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
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_edit')) {
            abort(403, 'Unauthorized access to edit expense categories.');
        }

        $category = DbExpenseCategory::where('store_id', current_store_id())
            ->findOrFail($id);

        return view('module.expenses.edit_category', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_edit')) {
            abort(403, 'Unauthorized access to edit expense categories.');
        }

        $storeId = current_store_id();

        $request->validate([
            'category_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_expense_category', 'category_name')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId);
                })->ignore($id),
            ],
            'category_code' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'status'        => 'required|in:1,0',
        ]);

        try {
            DB::beginTransaction();

            $category = DbExpenseCategory::where('store_id', $storeId)
                ->findOrFail($id);
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
     *
     * Phase 4 (Item 6): block deletion while expenses still reference this
     * category, so expenses are never silently orphaned. Unused categories still
     * delete normally.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('expense_category_delete')) {
            abort(403, 'Unauthorized access to delete expense categories.');
        }

        $storeId = current_store_id();

        try {
            $category = DbExpenseCategory::where('store_id', $storeId)
                ->findOrFail($id);

            // Phase 4 (Item 6): orphan guard — block if any (non-soft-deleted) expense
            // still references this category.
            $inUse = DbExpense::where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->where('category_id', $category->id)
                ->exists();

            if ($inUse) {
                return back()->with('error', 'This category cannot be deleted because it is still used by one or more expenses. Reassign or delete those expenses first.');
            }

            $category->delete();

            return redirect()->route('expenses.categories')->with('success', 'Category deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete category: ' . $e->getMessage());
        }
    }
}
