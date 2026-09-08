<?php

namespace App\Http\Controllers;

use App\Models\DbBrand;
use App\Models\DbItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    /**
     * Route-level permission gate, mirroring the AccountController/TransactionController
     * convention used consistently across the app. Slugs are the existing catalog slugs
     * (brand_view / brand_add / brand_edit / brand_delete from PermissionSeeder),
     * never new ones.
     */
    private function authorizeBrand(string $permission, string $message): void
    {
        if (auth()->check() && !auth()->user()->hasPermission($permission)) {
            abort(403, $message);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeBrand('brand_view', 'Unauthorized access to view brands.');

        $storeId = current_store_id();

        $query = DbBrand::where('store_id', $storeId);

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
            'total' => DbBrand::where('store_id', $storeId)->count(),
            'active' => DbBrand::where('store_id', $storeId)->where('status', 1)->count(),
            'inactive' => DbBrand::where('store_id', $storeId)->where('status', 0)->count(),
        ];

        return view('module.items.brands_list', compact('brands', 'stats'));
    }

    public function create()
    {
        $this->authorizeBrand('brand_add', 'Unauthorized access to create brands.');

        return view('module.items.add_brand');
    }

    public function store(Request $request)
    {
        $this->authorizeBrand('brand_add', 'Unauthorized access to create brands.');

        $storeId = current_store_id();

        $request->validate([
            'brand_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_brands', 'brand_name')->where('store_id', $storeId),
            ],
            'brand_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_brands', 'brand_code')->where('store_id', $storeId),
            ],
            'description' => 'nullable|string',
        ]);

        try {
            $brand = DbBrand::create([
                'brand_name' => $request->brand_name,
                'brand_code' => $request->brand_code,
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
                Log::warning('Brand unique collision on store', [
                    'brand_name' => $request->brand_name,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A brand with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Brand store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding brand. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Brand store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding brand. Please try again.')->withInput();
        }

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

    public function edit($id)
    {
        $this->authorizeBrand('brand_edit', 'Unauthorized access to edit brands.');

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to open the edit form for a Store-1 brand by id.
        $brand = DbBrand::where('store_id', current_store_id())->find($id);
        if (!$brand) {
            abort(404, 'Brand not found.');
        }

        return view('module.items.edit_brand', compact('brand'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeBrand('brand_edit', 'Unauthorized access to edit brands.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to update a Store-1 brand by id.
        $brand = DbBrand::where('store_id', $storeId)->find($id);
        if (!$brand) {
            abort(404, 'Brand not found.');
        }

        $request->validate([
            'brand_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_brands', 'brand_name')->where('store_id', $storeId)->ignore($brand->id),
            ],
            'brand_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_brands', 'brand_code')->where('store_id', $storeId)->ignore($brand->id),
            ],
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        try {
            $brand->update($request->only(['brand_name', 'brand_code', 'description', 'status']));
        } catch (\Illuminate\Database\QueryException $e) {
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Brand unique collision on update', [
                    'brand_id' => $id,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A brand with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Brand update failed', ['brand_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating brand. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Brand update failed', ['brand_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating brand. Please try again.')->withInput();
        }

        return redirect()->route('items.brands')->with('success', 'Brand updated successfully.');
    }

    public function destroy($id)
    {
        $this->authorizeBrand('brand_delete', 'Unauthorized access to delete brands.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to delete a Store-1 brand by supplying its id directly.
        $brand = DbBrand::where('store_id', $storeId)->find($id);
        if (!$brand) {
            return redirect()->back()->with('error', 'Brand not found.');
        }

        // Pre-delete usage guard: db_items.brand_id references this row via an
        // ON DELETE SET NULL FK, so deleting an in-use brand silently orphans the
        // brand reference on every Item/Service that used it. Block instead, with
        // the count of affected rows (mirrors ItemController::destroy()'s guard tone).
        $usageCount = DbItem::where('brand_id', $brand->id)
            ->where('store_id', $storeId)
            ->count();

        if ($usageCount > 0) {
            return redirect()->back()->with('error', "This brand is used by {$usageCount} item(s)/service(s) and cannot be deleted. Deactivate it instead.");
        }

        $brand->delete();

        return redirect()->back()->with('success', 'Brand deleted successfully.');
    }

    /**
     * Toggle a brand's Active/Inactive status from the list.
     * Store-scoped so a Store-2 user cannot flip a Store-1 brand's status.
     */
    public function toggleStatus($id)
    {
        $this->authorizeBrand('brand_edit', 'Unauthorized access to edit brands.');

        $brand = DbBrand::where('store_id', current_store_id())->find($id);
        if (!$brand) {
            return response()->json(['success' => false, 'message' => 'Brand not found.'], 404);
        }

        $brand->status = $brand->status ? 0 : 1;
        $brand->save();

        return response()->json([
            'success' => true,
            'status' => (int) $brand->status,
            'message' => $brand->status ? 'Brand activated.' : 'Brand deactivated.',
        ]);
    }
}
