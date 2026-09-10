<?php

namespace App\Http\Controllers;

use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Models\DbSale;
use App\Models\DbStockTransfer;
use App\Models\DbPurchase;
use App\Models\DbQuotation;
use App\Models\DbStockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    /**
     * Phase 1: aggregate the warehouse's stock like index() does (total_items /
     * available_qty / worth) — the exact withCount/withSum/subselect shape.
     */
    protected function warehouseStock(DbWarehouse $warehouse): array
    {
        $row = DbWarehouse::query()
            ->where('id', $warehouse->id)
            ->withCount('warehouseItems as total_items')
            ->withSum('warehouseItems as available_qty', 'available_qty')
            ->addSelect([
                'worth' => DbWarehouseItem::selectRaw('SUM(available_qty * db_items.purchase_price)')
                    ->join('db_items', 'db_items.id', '=', 'db_warehouseitems.item_id')
                    ->whereColumn('db_warehouseitems.warehouse_id', 'db_warehouse.id'),
            ])
            ->first();

        return [
            'total_items' => (int) ($row->total_items ?? 0),
            'available_qty' => (float) ($row->available_qty ?? 0),
            'worth' => (float) ($row->worth ?? 0),
        ];
    }

    /**
     * Phase 1: find every table still referencing this warehouse_id (dangling-ref
     * guard). Returns table labels for any with rows; empty array = safe.
     */
    protected function referencingTables(int $warehouseId): array
    {
        $refs = [];

        if (DbWarehouseItem::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'warehouse stock rows (db_warehouseitems)';
        }
        if (DbSale::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'sales (db_sales)';
        }
        if (DbItemSerial::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'item serials (db_item_serials)';
        }
        if (DbStockTransfer::where('warehouse_from', $warehouseId)->orWhere('warehouse_to', $warehouseId)->exists()) {
            $refs[] = 'stock transfers (db_stocktransfer)';
        }
        if (DbPurchase::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'purchases (db_purchase)';
        }
        if (DbQuotation::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'quotations (db_quotation)';
        }
        if (DbStockAdjustment::where('warehouse_id', $warehouseId)->exists()) {
            $refs[] = 'stock adjustments (db_stockadjustment)';
        }

        return $refs;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Phase 3: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_view')) {
            abort(403, 'Unauthorized access to view warehouses.');
        }

        $storeId = current_store_id();

        // Phase 2: store-scoped, soft-delete-aware base query.
        $query = DbWarehouse::query()
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->withCount('warehouseItems as total_items')
            ->withSum('warehouseItems as available_qty', 'available_qty')
            ->addSelect([
                'worth' => DbWarehouseItem::selectRaw('SUM(available_qty * db_items.purchase_price)')
                    ->join('db_items', 'db_items.id', '=', 'db_warehouseitems.item_id')
                    ->whereColumn('db_warehouseitems.warehouse_id', 'db_warehouse.id'),
            ]);

        if ($request->filled('search')) {
            $query->search($request->search, $storeId);
        }

        if ($request->filled('status')) {
            $query->filterStatus($request->status, $storeId);
        }

        // Phase 6: CSV export (Deposit/Expenses store-scoped convention).
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $all = $query->orderBy('id', 'desc')->get();
            $filename = 'warehouse_list_' . date('Y_m_d_His') . '.csv';
            return response()->stream(function () use ($all) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, ['Warehouse Name', 'Mobile', 'Email', 'Status', 'Total Items', 'Available Qty', 'Worth']);
                foreach ($all as $wh) {
                    fputcsv($handle, [
                        $wh->warehouse_name ?? '',
                        $wh->mobile ?? '',
                        $wh->email ?? '',
                        $wh->status == 1 ? 'Active' : 'Inactive',
                        (int) ($wh->total_items ?? 0),
                        number_format((float) ($wh->available_qty ?? 0), 2, '.', ''),
                        number_format((float) ($wh->worth ?? 0), 2, '.', ''),
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $all = $query->orderBy('id', 'desc')->get();
            return view('module.warehouse.warehouse_list_print', ['warehouses' => $all]);
        }

        // Phase 6: per-page whitelist.
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $warehouses = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();

        // Phase 2: store-scoped stats; Phase 6: surfaced as cards.
        $stats = [
            'total' => DbWarehouse::where('store_id', $storeId)->where('delete_bit', 0)->count(),
            'active' => DbWarehouse::where('store_id', $storeId)->where('delete_bit', 0)->where('status', 1)->count(),
            'inactive' => DbWarehouse::where('store_id', $storeId)->where('delete_bit', 0)->where('status', 0)->count(),
        ];

        return view('module.warehouse.warehouse_list', compact('warehouses', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Phase 3: permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_add')) {
            abort(403, 'Unauthorized access to add warehouses.');
        }

        return view('module.warehouse.add_warehouse');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Phase 3: permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_add')) {
            abort(403, 'Unauthorized access to add warehouses.');
        }

        $storeId = current_store_id();

        // Phase 5: per-store unique warehouse_name (reuses the categories pattern).
        $request->validate([
            'warehouse_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_warehouse', 'warehouse_name')->where(function ($q) use ($storeId) {
                    return $q->where('store_id', $storeId)->where('delete_bit', 0);
                }),
            ],
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        DbWarehouse::create([
            'warehouse_name' => $request->warehouse_name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'status' => 1,
            'store_id' => $storeId,
            'created_date' => now(),
            'delete_bit' => 0,
        ]);

        return redirect()->route('warehouse.list')->with('success', 'Warehouse created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DbWarehouse $warehouse)
    {
        // Phase 3: permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_edit')) {
            abort(403, 'Unauthorized access to edit warehouses.');
        }

        // Phase 2: store-scoped lookup (404 on cross-store).
        $warehouse = $this->scopedWarehouse($warehouse);

        return view('module.warehouse.edit_warehouse', compact('warehouse'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DbWarehouse $warehouse)
    {
        // Phase 3: permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_edit')) {
            abort(403, 'Unauthorized access to edit warehouses.');
        }

        $storeId = current_store_id();

        // Phase 2: store-scoped lookup (404 on cross-store).
        $warehouse = $this->scopedWarehouse($warehouse);

        // Phase 5: per-store unique warehouse_name (ignore self).
        $request->validate([
            'warehouse_name' => [
                'required', 'string', 'max:255',
                Rule::unique('db_warehouse', 'warehouse_name')
                    ->where(function ($q) use ($storeId) {
                        return $q->where('store_id', $storeId)->where('delete_bit', 0);
                    })
                    ->ignore($warehouse->id),
            ],
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        $warehouse->update($request->only(['warehouse_name', 'mobile', 'email', 'status']));

        return redirect()->route('warehouse.list')->with('success', 'Warehouse updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Phase 1: block when stock > 0; block when any historical table still
     * references this warehouse; otherwise soft-delete (delete_bit=1).
     * Phase 2: store-scoped lookup. Phase 3: permission gate.
     */
    public function destroy(DbWarehouse $warehouse)
    {
        // Phase 3: permission gate (delete).
        if (auth()->check() && !auth()->user()->hasPermission('warehouse_delete')) {
            abort(403, 'Unauthorized access to delete warehouses.');
        }

        $storeId = current_store_id();

        // Phase 2: store-scoped lookup (404 on cross-store).
        $warehouse = $this->scopedWarehouse($warehouse);

        // Phase 1: stock guard (reuse the exact index() aggregate shape).
        $stock = $this->warehouseStock($warehouse);
        if ($stock['total_items'] > 0 || $stock['available_qty'] > 0) {
            return back()->with('error', 'This warehouse cannot be deleted because it still holds ' . $stock['total_items'] . ' item(s) / ' . number_format($stock['available_qty'], 2) . ' quantity (worth ' . format_currency($stock['worth']) . '). Reassign or remove the stock first.');
        }

        // Phase 1: dangling-reference guard (naming the tables).
        $refs = $this->referencingTables($warehouse->id);
        if (!empty($refs)) {
            return back()->with('error', 'This warehouse cannot be deleted because the following records still reference it: ' . implode(', ', $refs) . '.');
        }

        // Phase 1 default: soft-delete (audit trail).
        $warehouse->update(['delete_bit' => 1]);

        return redirect()->route('warehouse.list')->with('success', 'Warehouse deleted successfully.');
    }

    /**
     * Phase 2: store-scoped lookup for route-model-bound warehouses.
     */
    protected function scopedWarehouse(DbWarehouse $warehouse): DbWarehouse
    {
        $found = DbWarehouse::where('store_id', current_store_id())
            ->where('delete_bit', 0)
            ->find($warehouse->id);

        abort_unless($found, 404, 'Warehouse not found.');

        return $found;
    }
}
