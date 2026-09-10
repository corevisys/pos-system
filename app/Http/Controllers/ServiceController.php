<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbTax;
use App\Models\DbWarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ServiceController extends Controller
{
    /**
     * Route-level permission gate, mirroring the AccountController/TransactionController
     * convention used consistently across the app. Slugs are the existing catalog slugs
     * (services_view / services_add / services_edit / services_delete from
     * PermissionSeeder), never new ones.
     */
    private function authorizeService(string $permission, string $message): void
    {
        if (auth()->check() && !auth()->user()->hasPermission($permission)) {
            abort(403, $message);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeService('services_view', 'Unauthorized access to view services.');

        // Store-scoped base query — a Store-A user must never see Store-B's services.
        // Mirrors ItemController::index()'s pattern exactly.
        $query = DbItem::where('service_bit', 1)
            ->where('store_id', current_store_id());

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'LIKE', "%{$search}%")
                  ->orWhere('item_code', 'LIKE', "%{$search}%");
            });
        }

        // Print/PDF export view — reuses the SAME filtered, store-scoped query builder
        // as the on-screen list so exports always mirror exactly what the screen shows.
        if ($request->export === 'print' || $request->export === 'pdf') {
            $exportServices = $query->with(['category', 'tax'])
                ->orderBy('id', 'desc')
                ->get();

            return view('module.items.services_list_print', [
                'services' => $exportServices,
            ]);
        }

        // CSV export — same shared query builder.
        if ($request->export === 'csv') {
            $exportServices = $query->with(['category', 'tax'])
                ->orderBy('id', 'desc')
                ->get();

            $filename = "services_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportServices) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($file, ['Item Code', 'Service Name', 'Category', 'Base Price', 'Sales Price', 'Status']);

                foreach ($exportServices as $s) {
                    fputcsv($file, [
                        $s->item_code,
                        $s->item_name,
                        $s->category->category_name ?? '',
                        (float) $s->price,
                        (float) $s->sales_price,
                        $s->status == 1 ? 'Active' : 'Inactive',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $services = $query->with(['category', 'tax'])
                          ->latest()
                          ->paginate($perPage)
                          ->withQueryString();

        $categories = DbCategory::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();

        return view('module.items.services_list', compact('services', 'categories'));
    }

    public function create()
    {
        $this->authorizeService('services_add', 'Unauthorized access to create services.');

        $categories = DbCategory::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();

        // Generate Service Item Code
        $itemCode = \App\Services\CodeGeneratorService::generate('item');

        return view('module.items.add_service', compact('categories', 'taxes', 'itemCode'));
    }

    public function store(Request $request)
    {
        $this->authorizeService('services_add', 'Unauthorized access to create services.');

        $storeId = current_store_id();

        $request->validate([
            'item_name' => 'required',
            'category_id' => 'required|exists:db_category,id',
            'price' => 'required|numeric',
            'tax_id' => 'nullable|exists:db_tax,id',
            'tax_type' => 'required',
            'sales_price' => 'required|numeric',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Store-scoped category/tax guard — a crafted cross-store category/tax id
        // must not be writable onto this store's service row. Mirrors the same
        // store-scoped dropdown/lookup convention Quotation/Purchase established.
        $category = DbCategory::where('id', $request->category_id)
            ->where('store_id', $storeId)
            ->first();
        if (!$category) {
            return back()->with('error', 'Selected category is not available for your store.')->withInput();
        }
        if ($request->filled('tax_id')) {
            $tax = DbTax::where('id', $request->tax_id)
                ->where('store_id', $storeId)
                ->first();
            if (!$tax) {
                return back()->with('error', 'Selected tax is not available for your store.')->withInput();
            }
        }

        try {
            DB::beginTransaction();

            $data = $request->all();
            $data['service_bit'] = 1;
            $data['store_id'] = $storeId;
            $data['status'] = 1;
            $data['created_by'] = auth()->id();
            $data['created_date'] = date('Y-m-d');
            $data['created_time'] = date('H:i:s');
            $data['system_ip'] = $request->ip();
            $data['system_name'] = gethostbyaddr($request->ip());
            
            // Generate Code if not provided
            if (!$request->filled('item_code')) {
                $data['item_code'] = \App\Services\CodeGeneratorService::generate('item');
            }

            // Persist uploaded image (mirrors ItemController::store() image handling)
            if ($request->hasFile('item_image')) {
                $image = $request->file('item_image');
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/items'), $filename);
                $data['item_image'] = 'uploads/items/' . $filename;
            }

            DbItem::create($data);

            DB::commit();
            return redirect()->route('items.service.list')->with('success', 'Service added successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Unique-violation detection matching the app-wide convention (AcAccount):
            // MySQL driverCode 1062, SQLite driverCode 19, or sqlstate 23000.
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Service item_code unique collision on store', [
                    'item_code' => $data['item_code'] ?? null,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'This item code is already in use — please try again or enter a different code.')->withInput();
            }
            Log::error('Service store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding service. Please try again.')->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service store failed', ['store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error adding service. Please try again.')->withInput();
        }
    }

    public function edit($id)
    {
        $this->authorizeService('services_view', 'Unauthorized access to view services.');

        // Store-scoped lookup — a Store-2 user must never be able to open the edit
        // form for a Store-1 service by id (IDOR). Mirrors the store-scoped lookup
        // convention used across the app.
        $service = DbItem::where('service_bit', 1)
            ->where('store_id', current_store_id())
            ->findOrFail($id);

        $categories = DbCategory::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();
        $taxes = DbTax::where('status', 1)
            ->where('store_id', current_store_id())
            ->get();

        // History count for the informational edit-after-sale warning (warn, don't block).
        $salesHistoryCount = DB::table('db_salesitems')->where('item_id', $service->id)->count()
            + DB::table('db_quotationitems')->where('item_id', $service->id)->count();

        return view('module.items.edit_service', compact('service', 'categories', 'taxes', 'salesHistoryCount'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeService('services_edit', 'Unauthorized access to edit services.');

        $storeId = current_store_id();

        $request->validate([
            'item_name' => 'required',
            'category_id' => 'required|exists:db_category,id',
            'price' => 'required|numeric',
            'tax_id' => 'nullable|exists:db_tax,id',
            'tax_type' => 'required',
            'sales_price' => 'required|numeric',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to update a Store-1 service by id.
        $service = DbItem::where('service_bit', 1)
            ->where('store_id', $storeId)
            ->first();
        if (!$service) {
            abort(404, 'Service not found.');
        }

        // Store-scoped category/tax guard — reject crafted cross-store references.
        $category = DbCategory::where('id', $request->category_id)
            ->where('store_id', $storeId)
            ->first();
        if (!$category) {
            return back()->with('error', 'Selected category is not available for your store.')->withInput();
        }
        if ($request->filled('tax_id')) {
            $tax = DbTax::where('id', $request->tax_id)
                ->where('store_id', $storeId)
                ->first();
            if (!$tax) {
                return back()->with('error', 'Selected tax is not available for your store.')->withInput();
            }
        }

        try {
            DB::beginTransaction();

            $data = $request->except(['item_image', 'item_code']);

            // Persist uploaded image (mirrors ItemController::update() image handling:
            // replace the existing image file if a new one is chosen).
            if ($request->hasFile('item_image')) {
                if ($service->item_image && file_exists(public_path($service->item_image))) {
                    @unlink(public_path($service->item_image));
                }
                $image = $request->file('item_image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/items'), $filename);
                $data['item_image'] = 'uploads/items/' . $filename;
            }

            $service->update($data);

            DB::commit();
            return redirect()->route('items.service.list')->with('success', 'Service updated successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Unique-violation detection matching the app-wide convention (AcAccount):
            // MySQL driverCode 1062, SQLite driverCode 19, or sqlstate 23000.
            $driverCode = $e->errorInfo[1] ?? null;
            $sqlState = $e->errorInfo[0] ?? null;
            $isUniqueViolation =
                $driverCode === 1062 ||
                $driverCode === 19 ||
                $sqlState === '23000' ||
                str_contains(strtolower($e->getMessage()), 'unique');

            if ($isUniqueViolation) {
                Log::warning('Service item_code unique collision on update', [
                    'service_id' => $id,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'This item code is already in use — please try again or enter a different code.')->withInput();
            }
            Log::error('Service update failed', ['service_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating service. Please try again.')->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service update failed', ['service_id' => $id, 'store_id' => $storeId, 'error' => $e->getMessage()]);
            return back()->with('error', 'Error updating service. Please try again.')->withInput();
        }
    }

    public function destroy($id)
    {
        $this->authorizeService('services_delete', 'Unauthorized access to delete services.');

        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — a Store-2 user must never be able
        // to delete a Store-1 service by supplying its id directly. Mirrors the exact
        // fix applied to ItemController::destroy(): the fetch is scoped by the current
        // store, so a cross-store id resolves to "not found" and is rejected cleanly.
        $service = DbItem::where('service_bit', 1)
            ->where('store_id', $storeId)
            ->find($id);
        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.'], 404);
        }

        // History guard: services are sellable catalog rows (db_items with service_bit=1)
        // that flow into sales, quotation, hold and stock-tracking tables via onDelete('cascade')
        // FKs on item_id. Deleting a service that has any such history silently cascades those
        // rows away, corrupting historical invoices/ledgers. Enumerate the exact same blocking
        // set ItemController::destroy() uses (db_warehouseitems is intentionally excluded — it
        // is current inventory state, not historical usage, and is cleaned up on delete).
        $hasHistory = DB::table('db_salesitems')->where('item_id', $service->id)->exists()
            || DB::table('db_salesitemsreturn')->where('item_id', $service->id)->exists()
            || DB::table('db_purchaseitems')->where('item_id', $service->id)->exists()
            || DB::table('db_purchaseitemsreturn')->where('item_id', $service->id)->exists()
            || DB::table('db_quotationitems')->where('item_id', $service->id)->exists()
            || DB::table('db_stockadjustmentitems')->where('item_id', $service->id)->exists()
            || DB::table('db_stocktransferitems')->where('item_id', $service->id)->exists()
            || DB::table('db_stockentry')->where('item_id', $service->id)->exists()
            || DB::table('db_item_serials')->where('item_id', $service->id)->exists()
            || DB::table('db_holditems')->where('item_id', $service->id)->exists();

        if ($hasHistory) {
            return response()->json([
                'success' => false,
                'message' => 'This service has existing sales/order history and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Delete any stock records — scoped to the same store
            DbWarehouseItem::where('item_id', $service->id)
                ->where('store_id', $storeId)
                ->delete();

            // Hard delete (db_items has no delete_bit / SoftDeletes — child rows cascade via DB FKs).
            $service->delete();

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service delete failed', ['service_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred while deleting the service.']);
        }
    }

    /**
     * Toggle a service's Active/Inactive status from the list row-action dropdown.
     * Store-scoped so a Store-2 user cannot flip a Store-1 service's status.
     */
    public function toggleStatus($id)
    {
        $this->authorizeService('services_edit', 'Unauthorized access to edit services.');

        $storeId = current_store_id();

        $service = DbItem::where('service_bit', 1)
            ->where('store_id', $storeId)
            ->first();
        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.'], 404);
        }

        $service->status = $service->status ? 0 : 1;
        $service->save();

        return response()->json([
            'success' => true,
            'status' => (int) $service->status,
            'message' => $service->status ? 'Service activated.' : 'Service deactivated.',
        ]);
    }
}
