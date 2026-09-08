<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VariantController extends Controller
{
    /**
     * Route-level permission gate, mirroring the AccountController/TransactionController
     * convention used consistently across the app. Slugs are the existing catalog slugs
     * (variant_view / variant_add / variant_edit / variant_delete from PermissionSeeder),
     * never new ones.
     */
    private function authorizeVariant(string $permission, string $message): void
    {
        if (auth()->check() && !auth()->user()->hasPermission($permission)) {
            abort(403, $message);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeVariant('variant_view', 'Unauthorized access to view variants.');

        $storeId = current_store_id();

        $query = DbVariant::where('store_id', $storeId);

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
            'total' => DbVariant::where('store_id', $storeId)->count(),
            'active' => DbVariant::where('store_id', $storeId)->where('status', 1)->count(),
            'inactive' => DbVariant::where('store_id', $storeId)->where('status', 0)->count(),
        ];

        return view('module.items.variants_list', compact('variants', 'stats'));
    }

    public function create()
    {
        $this->authorizeVariant('variant_add', 'Unauthorized access to create variants.');

        return view('module.items.add_variant');
    }

    public function store(Request $request)
    {
        $this->authorizeVariant('variant_add', 'Unauthorized access to create variants.');

        $storeId = current_store_id();

        $request->validate([
            'variant_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_variants', 'variant_name')->where('store_id', $storeId),
            ],
            'variant_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_variants', 'variant_code')->where('store_id', $storeId),
            ],
            'description' => 'nullable|string',
        ]);

        try {
            DbVariant::create([
                'variant_name' => $request->variant_name,
                'variant_code' => $request->variant_code,
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
                Log::warning('Variant unique collision on store', [
                    'variant_name' => $request->variant_name,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A variant with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Variant store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding variant. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Variant store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding variant. Please try again.')->withInput();
        }

        return redirect()->route('items.variants')->with('success', 'Variant created successfully.');
    }

    public function edit($id)
    {
        $this->authorizeVariant('variant_edit', 'Unauthorized access to edit variants.');

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to open the edit form for a Store-1 variant by id.
        $variant = DbVariant::where('store_id', current_store_id())->find($id);
        if (!$variant) {
            abort(404, 'Variant not found.');
        }

        return view('module.items.edit_variant', compact('variant'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeVariant('variant_edit', 'Unauthorized access to edit variants.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to update a Store-1 variant by id.
        $variant = DbVariant::where('store_id', $storeId)->find($id);
        if (!$variant) {
            abort(404, 'Variant not found.');
        }

        $request->validate([
            'variant_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('db_variants', 'variant_name')->where('store_id', $storeId)->ignore($variant->id),
            ],
            'variant_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('db_variants', 'variant_code')->where('store_id', $storeId)->ignore($variant->id),
            ],
            'description' => 'nullable|string',
            'status' => 'required|integer|in:0,1',
        ]);

        try {
            $variant->update($request->only(['variant_name', 'variant_code', 'description', 'status']));
        } catch (\Illuminate\Database\QueryException $e) {
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Variant unique collision on update', [
                    'variant_id' => $id,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'A variant with this name/code already exists for your store — please use a different name or code.')->withInput();
            }
            Log::error('Variant update failed', ['variant_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating variant. Please try again.')->withInput();
        } catch (\Exception $e) {
            Log::error('Variant update failed', ['variant_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating variant. Please try again.')->withInput();
        }

        return redirect()->route('items.variants')->with('success', 'Variant updated successfully.');
    }

    public function destroy($id)
    {
        $this->authorizeVariant('variant_delete', 'Unauthorized access to delete variants.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to delete a Store-1 variant by supplying its id directly.
        $variant = DbVariant::where('store_id', $storeId)->find($id);
        if (!$variant) {
            return redirect()->back()->with('error', 'Variant not found.');
        }

        // Pre-delete usage guard (future-proofing): db_items.variant_id has no FK and
        // is never written by the codebase today, so this count is currently always
        // zero — but if Variants are ever wired into the Item form, deleting an
        // in-use variant must not silently orphan those references.
        $usageCount = DbItem::where('variant_id', $variant->id)
            ->where('store_id', $storeId)
            ->count();

        if ($usageCount > 0) {
            return redirect()->back()->with('error', "This variant is used by {$usageCount} item(s) and cannot be deleted. Deactivate it instead.");
        }

        $variant->delete();
        return redirect()->back()->with('success', 'Variant deleted successfully.');
    }

    /**
     * Toggle a variant's Active/Inactive status from the list.
     * Store-scoped so a Store-2 user cannot flip a Store-1 variant's status.
     */
    public function toggleStatus($id)
    {
        $this->authorizeVariant('variant_edit', 'Unauthorized access to edit variants.');

        $variant = DbVariant::where('store_id', current_store_id())->find($id);
        if (!$variant) {
            return response()->json(['success' => false, 'message' => 'Variant not found.'], 404);
        }

        $variant->status = $variant->status ? 0 : 1;
        $variant->save();

        return response()->json([
            'success' => true,
            'status' => (int) $variant->status,
            'message' => $variant->status ? 'Variant activated.' : 'Variant deactivated.',
        ]);
    }
}
