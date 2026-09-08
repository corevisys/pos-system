<?php

namespace App\Http\Controllers;

use App\Models\DbWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = DbWarehouse::query()
            ->withCount('warehouseItems as total_items')
            ->withSum('warehouseItems as available_qty', 'available_qty')
            ->addSelect([
                'worth' => \App\Models\DbWarehouseItem::selectRaw('SUM(available_qty * db_items.purchase_price)')
                    ->join('db_items', 'db_items.id', '=', 'db_warehouseitems.item_id')
                    ->whereColumn('db_warehouseitems.warehouse_id', 'db_warehouse.id')
            ]);

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
        $warehouses = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => DbWarehouse::count(),
            'active' => DbWarehouse::where('status', 1)->count(),
            'inactive' => DbWarehouse::where('status', 0)->count(),
        ];

        return view('module.warehouse.warehouse_list', compact('warehouses', 'stats'));
    }

    public function create()
    {
        return view('module.warehouse.add_warehouse');
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_name' => 'required|string|max:255|unique:db_warehouse,warehouse_name',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        DbWarehouse::create([
            'warehouse_name' => $request->warehouse_name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'status' => 1,
            'store_id' => auth()->user()->store_id,
            'created_date' => now(),
        ]);

        return redirect()->route('warehouse.list')->with('success', 'Warehouse created successfully.');
    }

    public function edit(DbWarehouse $warehouse)
    {
        return view('module.warehouse.edit_warehouse', compact('warehouse'));
    }

    public function update(Request $request, DbWarehouse $warehouse)
    {
        $request->validate([
            'warehouse_name' => 'required|string|max:255|unique:db_warehouse,warehouse_name,' . $warehouse->id,
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        $warehouse->update($request->only(['warehouse_name', 'mobile', 'email', 'status']));

        return redirect()->route('warehouse.list')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(DbWarehouse $warehouse)
    {
        $warehouse->delete();

        return redirect()->route('warehouse.list')->with('success', 'Warehouse deleted successfully.');
    }
}
