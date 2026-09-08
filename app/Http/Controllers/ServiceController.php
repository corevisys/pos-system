<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = DbItem::where('service_bit', 1);

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

        $services = $query->with(['category', 'tax'])
                          ->latest()
                          ->paginate(10);

        $categories = DbCategory::where('status', 1)->get();

        return view('module.items.services_list', compact('services', 'categories'));
    }

    public function create()
    {
        $categories = DbCategory::where('status', 1)->get();
        $taxes = DbTax::where('status', 1)->get();

        // Generate Service Item Code
        $itemCode = \App\Services\CodeGeneratorService::generate('item');

        return view('module.items.add_service', compact('categories', 'taxes', 'itemCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_name' => 'required',
            'category_id' => 'required',
            'price' => 'required|numeric',
            'tax_id' => 'nullable',
            'tax_type' => 'required',
            'sales_price' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $data = $request->all();
            $data['service_bit'] = 1;
            $data['store_id'] = 1; // Default
            $data['created_by'] = auth()->id();
            $data['created_date'] = date('Y-m-d');
            $data['created_time'] = date('H:i:s');
            $data['system_ip'] = $request->ip();
            $data['system_name'] = gethostbyaddr($request->ip());
            
            // Generate Code if not provided
            if (!$request->filled('item_code')) {
                $data['item_code'] = \App\Services\CodeGeneratorService::generate('item');
            }

            DbItem::create($data);

            DB::commit();
            return redirect()->route('items.service.list')->with('success', 'Service added successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error adding service: ' . $e->getMessage())->withInput();
        }
    }

    public function edit($id)
    {
        $service = DbItem::where('service_bit', 1)->findOrFail($id);
        $categories = DbCategory::where('status', 1)->get();
        $taxes = DbTax::where('status', 1)->get();

        return view('module.items.edit_service', compact('service', 'categories', 'taxes'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'item_name' => 'required',
            'category_id' => 'required',
            'price' => 'required|numeric',
            'tax_id' => 'nullable',
            'tax_type' => 'required',
            'sales_price' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $service = DbItem::where('service_bit', 1)->findOrFail($id);
            $service->update($request->all());

            DB::commit();
            return redirect()->route('items.service.list')->with('success', 'Service updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating service: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $service = DbItem::where('service_bit', 1)->findOrFail($id);
            $service->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
