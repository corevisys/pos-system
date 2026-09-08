<?php

namespace App\Http\Controllers;

use App\Models\DbCategory;
use App\Models\DbItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Route-level permission gate, mirroring the AccountController/TransactionController
     * convention used consistently across the app. Slugs are the existing catalog slugs
     * (items_category_view / items_category_add / items_category_edit /
     * items_category_delete from PermissionSeeder), never new ones.
     */
    private function authorizeCategory(string $permission, string $message): void
    {
        if (auth()->check() && !auth()->user()->hasPermission($permission)) {
            abort(403, $message);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeCategory('items_category_view', 'Unauthorized access to view categories.');

        $storeId = current_store_id();

        $query = DbCategory::where('store_id', $storeId);

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
            'total' => DbCategory::where('store_id', $storeId)->count(),
            'active' => DbCategory::where('store_id', $storeId)->where('status', 1)->count(),
            'inactive' => DbCategory::where('store_id', $storeId)->where('status', 0)->count(),
        ];

        return view('module.items.categories_list', compact('categories', 'stats'));
    }

    public function create()
    {
        $this->authorizeCategory('items_category_add', 'Unauthorized access to create categories.');

        return view('module.items.add_category');
    }

    public function store(Request $request)
    {
        $this->authorizeCategory('items_category_add', 'Unauthorized access to create categories.');

        $storeId = current_store_id();

        $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_category', 'category_name')->where('store_id', $storeId),
            ],
            'category_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_category', 'category_code')->where('store_id', $storeId),
            ],
            'description' => 'nullable|string',
        ]);

        try {
            $category = DbCategory::create([
                'category_name' => $request->category_name,
                'category_code' => $request->category_code,
                'description' => $request->description,
                'status' => 1,
                'store_id' => $storeId,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique-violation detection matching the app-wide convention (ServiceController):
            // MySQL driverCode 1062, SQLite driverCode 19, or sqlstate 23000.
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Category unique collision on store', [
                    'category_name' => $request->category_name,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A category with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Category store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding category. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Category store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding category. Please try again.')->withInput();
        }

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

    public function edit($id)
    {
        $this->authorizeCategory('items_category_edit', 'Unauthorized access to edit categories.');

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to open the edit form for a Store-1 category by id.
        $category = DbCategory::where('store_id', current_store_id())->find($id);
        if (!$category) {
            abort(404, 'Category not found.');
        }

        return view('module.items.edit_category', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeCategory('items_category_edit', 'Unauthorized access to edit categories.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to update a Store-1 category by id.
        $category = DbCategory::where('store_id', $storeId)->find($id);
        if (!$category) {
            abort(404, 'Category not found.');
        }

        $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_category', 'category_name')->where('store_id', $storeId)->ignore($category->id),
            ],
            'category_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_category', 'category_code')->where('store_id', $storeId)->ignore($category->id),
            ],
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        try {
            $category->update($request->only(['category_name', 'category_code', 'description', 'status']));
        } catch (\Illuminate\Database\QueryException $e) {
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Category unique collision on update', [
                    'category_id' => $id,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A category with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Category update failed', ['category_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating category. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Category update failed', ['category_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating category. Please try again.')->withInput();
        }

        return redirect()->route('items.categories')->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $this->authorizeCategory('items_category_delete', 'Unauthorized access to delete categories.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to delete a Store-1 category by supplying its id directly.
        $category = DbCategory::where('store_id', $storeId)->find($id);
        if (!$category) {
            return redirect()->back()->with('error', 'Category not found.');
        }

        // Pre-delete usage guard: db_items.category_id references this row via an
        // ON DELETE SET NULL FK, so deleting an in-use category silently orphans the
        // category reference on every Item/Service that used it. Block instead, with
        // the count of affected rows (mirrors ItemController::destroy()'s guard tone).
        $usageCount = DbItem::where('category_id', $category->id)
            ->where('store_id', $storeId)
            ->count();

        if ($usageCount > 0) {
            return redirect()->back()->with('error', "This category is used by {$usageCount} item(s)/service(s) and cannot be deleted. Deactivate it instead.");
        }

        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    /**
     * Toggle a category's Active/Inactive status from the list.
     * Store-scoped so a Store-2 user cannot flip a Store-1 category's status.
     */
    public function toggleStatus($id)
    {
        $this->authorizeCategory('items_category_edit', 'Unauthorized access to edit categories.');

        $category = DbCategory::where('store_id', current_store_id())->find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $category->status = $category->status ? 0 : 1;
        $category->save();

        return response()->json([
            'success' => true,
            'status' => (int) $category->status,
            'message' => $category->status ? 'Category activated.' : 'Category deactivated.',
        ]);
    }
}
