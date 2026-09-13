<?php

namespace App\Http\Controllers;

use App\Models\DbCustomerCoupon;
use App\Models\DbCoupon;
use App\Models\DbCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Permission gate (view) — seeded customerCouponView slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponView')) {
            abort(403, 'Unauthorized access to customer coupons.');
        }

        $query = DbCustomerCoupon::with('customer')->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('customer_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status') && $request->status !== '') {
            $query->where('status', (int)$request->status);
        }

        $perPage = in_array((int)$request->get('per_page', 10), [10, 25, 50, 100], true)
            ? (int)$request->get('per_page', 10)
            : 10;

        $coupons = $query->paginate($perPage);

        return view('module.coupons.customer_coupons_list', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Permission gate (add) — seeded customerCouponAdd slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponAdd')) {
            abort(403, 'Unauthorized access to add customer coupons.');
        }

        $customers = DbCustomer::select('id', 'customer_name', 'customer_code')->get();
        return view('module.coupons.create_customer_coupon', compact('customers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Permission gate (add) — seeded customerCouponAdd slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponAdd')) {
            abort(403, 'Unauthorized access to add customer coupons.');
        }

        $request->validate([
            'customer_id' => ['required', \Illuminate\Validation\Rule::exists('db_customers', 'id')->where('store_id', current_store_id())],
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:db_customer_coupons,code',
            'type'        => 'required|in:Percentage,Fixed',
            'value'       => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'Percentage' && $value > 100) {
                        $fail('Percentage discount value cannot exceed 100%.');
                    }
                },
            ],
            'expire_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        // Cross-table uniqueness check against db_coupons
        $code = trim($request->code);
        if (DbCoupon::whereRaw('LOWER(TRIM(code)) = ?', [strtolower($code)])->exists()) {
            throw ValidationException::withMessages([
                'code' => "The coupon code '{$code}' is already in use by a master campaign coupon.",
            ]);
        }

        try {
            DB::beginTransaction();

            $coupon = new DbCustomerCoupon();
            $coupon->store_id = current_store_id();
            $coupon->customer_id = $request->customer_id;
            $coupon->name = $request->name;
            $coupon->code = $code;
            $coupon->value = $request->value ?? 0;
            $coupon->type = $request->type;
            $coupon->expire_date = $request->expire_date;
            $coupon->description = $request->description;
            $coupon->status = 1; // Active
            $coupon->created_by = auth()->id() ?? 1;
            $coupon->created_date = date('Y-m-d');
            $coupon->created_time = date('H:i:s');
            $coupon->system_ip = $request->ip();
            $coupon->system_name = gethostname();
            $coupon->save();

            DB::commit();

            return redirect()->route('coupons.customer.list')->with('success', 'Customer coupon created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create coupon: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        // Permission gate (edit) — seeded customerCouponEdit slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponEdit')) {
            abort(403, 'Unauthorized access to edit customer coupons.');
        }

        $coupon = DbCustomerCoupon::findOrFail($id);
        $customers = DbCustomer::select('id', 'customer_name', 'customer_code')->get();
        return view('module.coupons.edit_customer_coupon', compact('coupon', 'customers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Permission gate (edit) — seeded customerCouponEdit slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponEdit')) {
            abort(403, 'Unauthorized access to edit customer coupons.');
        }

        $coupon = DbCustomerCoupon::findOrFail($id);

        $request->validate([
            'customer_id' => ['required', \Illuminate\Validation\Rule::exists('db_customers', 'id')->where('store_id', current_store_id())],
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:db_customer_coupons,code,' . $coupon->id,
            'type'        => 'required|in:Percentage,Fixed',
            'value'       => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'Percentage' && $value > 100) {
                        $fail('Percentage discount value cannot exceed 100%.');
                    }
                },
            ],
            'expire_date' => 'nullable|date',
            'status'      => 'required|in:0,1',
            'description' => 'nullable|string',
        ]);

        $code = trim($request->code);
        if (DbCoupon::whereRaw('LOWER(TRIM(code)) = ?', [strtolower($code)])->exists()) {
            throw ValidationException::withMessages([
                'code' => "The coupon code '{$code}' is already in use by a master campaign coupon.",
            ]);
        }

        try {
            DB::beginTransaction();

            $coupon->customer_id = $request->customer_id;
            $coupon->name = $request->name;
            $coupon->code = $code;
            $coupon->value = $request->value ?? 0;
            $coupon->type = $request->type;
            $coupon->expire_date = $request->expire_date;
            $coupon->status = (int)$request->status;
            $coupon->description = $request->description;
            $coupon->save();

            DB::commit();

            return redirect()->route('coupons.customer.list')->with('success', 'Customer coupon updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update coupon: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Permission gate (delete) — seeded customerCouponDelete slug.
        if (auth()->check() && !auth()->user()->hasPermission('customerCouponDelete')) {
            abort(403, 'Unauthorized access to delete customer coupons.');
        }

        try {
            $coupon = DbCustomerCoupon::findOrFail($id);
            $coupon->delete();

            return redirect()->route('coupons.customer.list')->with('success', 'Customer coupon deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete coupon: ' . $e->getMessage());
        }
    }
}
