<?php

namespace App\Http\Controllers;

use App\Models\DbCoupon;
use App\Models\DbCustomerCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Permission gate (view) — seeded discountCouponView slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponView')) {
            abort(403, 'Unauthorized access to coupons.');
        }

        $query = DbCoupon::withCount('customerCoupons')->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== '') {
            $query->where('status', (int)$request->status);
        }

        $perPage = in_array((int)$request->get('per_page', 10), [10, 25, 50, 100], true)
            ? (int)$request->get('per_page', 10)
            : 10;

        $coupons = $query->paginate($perPage);

        return view('module.coupons.coupons_master', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Permission gate (add) — seeded discountCouponAdd slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponAdd')) {
            abort(403, 'Unauthorized access to add coupons.');
        }

        return view('module.coupons.create_coupon');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Permission gate (add) — seeded discountCouponAdd slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponAdd')) {
            abort(403, 'Unauthorized access to add coupons.');
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:db_coupons,code',
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
            'expire_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        // Cross-table uniqueness check against db_customer_coupons
        $code = trim($request->code);
        if (DbCustomerCoupon::whereRaw('LOWER(TRIM(code)) = ?', [strtolower($code)])->exists()) {
            throw ValidationException::withMessages([
                'code' => "The coupon code '{$code}' is already in use by a customer voucher.",
            ]);
        }

        try {
            DB::beginTransaction();

            $coupon = new DbCoupon();
            $coupon->store_id = current_store_id();
            $coupon->name = $request->name;
            $coupon->code = $code;
            $coupon->value = $request->value;
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

            return redirect()->route('coupons.master')->with('success', 'Coupon created successfully.');
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
        // Permission gate (edit) — seeded discountCouponEdit slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponEdit')) {
            abort(403, 'Unauthorized access to edit coupons.');
        }

        $coupon = DbCoupon::findOrFail($id);
        return view('module.coupons.edit_coupon', compact('coupon'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Permission gate (edit) — seeded discountCouponEdit slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponEdit')) {
            abort(403, 'Unauthorized access to edit coupons.');
        }

        $coupon = DbCoupon::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:db_coupons,code,' . $coupon->id,
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
            'expire_date' => 'required|date',
            'status'      => 'required|in:0,1',
            'description' => 'nullable|string',
        ]);

        $code = trim($request->code);
        if (DbCustomerCoupon::whereRaw('LOWER(TRIM(code)) = ?', [strtolower($code)])->exists()) {
            throw ValidationException::withMessages([
                'code' => "The coupon code '{$code}' is already in use by a customer voucher.",
            ]);
        }

        try {
            DB::beginTransaction();

            $coupon->name = $request->name;
            $coupon->code = $code;
            $coupon->value = $request->value;
            $coupon->type = $request->type;
            $coupon->expire_date = $request->expire_date;
            $coupon->status = (int)$request->status;
            $coupon->description = $request->description;
            $coupon->save();

            DB::commit();

            return redirect()->route('coupons.master')->with('success', 'Coupon updated successfully.');
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
        // Permission gate (delete) — seeded discountCouponDelete slug.
        if (auth()->check() && !auth()->user()->hasPermission('discountCouponDelete')) {
            abort(403, 'Unauthorized access to delete coupons.');
        }

        try {
            $coupon = DbCoupon::findOrFail($id);
            $coupon->delete();

            return redirect()->route('coupons.master')->with('success', 'Coupon deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete coupon: ' . $e->getMessage());
        }
    }

    /**
     * Validate a coupon code for checkout (POS and Add Sale).
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'customer_id' => 'nullable',
        ]);

        $code = strtoupper(trim($request->code));
        $subtotal = (float) $request->subtotal;
        $customerId = $request->customer_id;
        if ($customerId === 'Walk-in customer' || $customerId === 'Walk-in Customer' || $customerId === '') {
            $customerId = null;
        }

        $today = date('Y-m-d');

        // 1. Check DbCustomerCoupon first (customer-specific code)
        $normalizedCode = strtolower($code);
        $customerCoupon = DbCustomerCoupon::whereRaw('LOWER(TRIM(code)) = ?', [$normalizedCode])->first();
        if ($customerCoupon) {
            if ($customerCoupon->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'This customer coupon has already been used or is inactive.'
                ], 422);
            }
            if ($customerCoupon->expire_date && $customerCoupon->expire_date < $today) {
                return response()->json([
                    'success' => false,
                    'message' => "This coupon expired on {$customerCoupon->expire_date}."
                ], 422);
            }
            if (!$customerId || (int)$customerCoupon->customer_id !== (int)$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'This coupon is exclusive to a specific customer. Please select that customer first.'
                ], 422);
            }

            $discountAmt = 0;
            if (strtolower($customerCoupon->type) === 'percentage') {
                $discountAmt = round(($subtotal * (float)$customerCoupon->value) / 100, 2);
            } else {
                $discountAmt = (float)$customerCoupon->value;
            }
            $discountAmt = min($discountAmt, $subtotal);

            return response()->json([
                'success' => true,
                'coupon_id' => $customerCoupon->coupon_id ?? $customerCoupon->id,
                'customer_coupon_id' => $customerCoupon->id,
                'is_customer_coupon' => true,
                'code' => $customerCoupon->code,
                'name' => $customerCoupon->name,
                'type' => $customerCoupon->type,
                'value' => (float)$customerCoupon->value,
                'discount_amount' => $discountAmt,
                'message' => "Customer coupon '{$customerCoupon->code}' applied successfully!"
            ]);
        }

        // 2. Check DbCoupon (Master general promotional coupon)
        $coupon = DbCoupon::whereRaw('LOWER(TRIM(code)) = ?', [$normalizedCode])->first();
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.'
            ], 404);
        }

        if ($coupon->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is inactive.'
            ], 422);
        }

        if ($coupon->expire_date && $coupon->expire_date < $today) {
            return response()->json([
                'success' => false,
                'message' => "This coupon expired on {$coupon->expire_date}."
            ], 422);
        }

        $discountAmt = 0;
        if (strtolower($coupon->type) === 'percentage') {
            $discountAmt = round(($subtotal * (float)$coupon->value) / 100, 2);
        } else {
            $discountAmt = (float)$coupon->value;
        }
        $discountAmt = min($discountAmt, $subtotal);

        return response()->json([
            'success' => true,
            'coupon_id' => $coupon->id,
            'customer_coupon_id' => null,
            'is_customer_coupon' => false,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'type' => $coupon->type,
            'value' => (float)$coupon->value,
            'discount_amount' => $discountAmt,
            'message' => "Coupon '{$coupon->code}' applied successfully!"
        ]);
    }
}
