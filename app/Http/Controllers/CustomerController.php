<?php

namespace App\Http\Controllers;

use App\Models\DbCustomer;
use App\Models\DbCountry;
use App\Models\DbState;
use App\Models\CustomerGuardian;
use App\Models\CustomerGuarantor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\SMS\Services\SmsTriggerService;
use Exception;

class CustomerController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_view')) {
            abort(403, 'Unauthorized access to customers.');
        }

        $query = DbCustomer::where('delete_bit', 0);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        // Simple equality filter on the existing Active/Inactive status flag
        // (mirrors the status filter on Users List / Roles List).
        if ($request->filled('status') && in_array((string) $request->status, ['0', '1'], true)) {
            $query->where('status', (int) $request->status);
        }

        // Print-friendly filtered list (browser print / Save-as-PDF via the print dialog).
        // Mirrors the export pattern established on Sales List / Returns / EMI lists.
        if ($request->export === 'print' || $request->export === 'pdf') {
            $printCustomers = $query->orderBy('id', 'desc')->get();

            return view('module.contacts.customers_list_print', [
                'customers' => $printCustomers,
                'totalOpeningBalance' => (float) $printCustomers->sum('opening_balance'),
                'totalReturnDue' => (float) $printCustomers->sum('sales_return_due'),
            ]);
        }

        // Export current filtered result set as CSV (shared pattern used by Sales List/Returns).
        if ($request->export === 'csv') {
            $exportCustomers = $query->orderBy('id', 'desc')->get();
            $filename = "customers_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportCustomers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Customer Code', 'Customer Name', 'Mobile', 'Email', 'Location', 'Credit Limit', 'Previous Due', 'Return Due', 'Advance', 'Status']);

                foreach ($exportCustomers as $c) {
                    fputcsv($file, [
                        $c->customer_code,
                        $c->customer_name,
                        $c->mobile ?: $c->mobile_primary,
                        $c->email ?? '',
                        $c->city ?? '',
                        (float) $c->credit_limit,
                        (float) $c->opening_balance,
                        (float) $c->sales_return_due,
                        (float) $c->tot_advance,
                        $c->status == 1 ? 'Active' : 'Inactive',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $customers = $query->paginate($limit)->withQueryString();
        return view('module.contacts.customers_list', compact('customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_add')) {
            abort(403, 'Unauthorized access to add customers.');
        }

        $countries = DbCountry::all();
        $states = DbState::all();
        $customer = null;
        return view('module.contacts.add_customer', compact('countries', 'states', 'customer'));
    }

    /**
     * Store a newly created resource in storage (AJAX/Quick Add).
     */
    public function quickStore(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_add')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to add customers.'], 403);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'mobile' => 'required|string|regex:/^\d{11}$/|unique:db_customers,mobile',
            'email' => 'nullable|email|max:255|unique:db_customers,email',
            'customer_type' => 'sometimes|string|in:regular',
        ]);

        // Quick-add is intentionally restricted to 'regular' customers. EMI customers require
        // full KYC (id card, father/mother name, dob, documents, guardian, guarantor, etc.) which
        // this compact modal does not collect; allowing customer_type='emi' here would create an
        // invalid "EMI" customer that later fails the wizard's required_if:customer_type,emi rules.
        $customerType = 'regular';

        DB::beginTransaction();
        try {
            // Generate customer code
            $customer_code = \App\Services\CodeGeneratorService::generate('customer');

            // Create Customer
            $customer = DbCustomer::create([
                'store_id' => auth()->user()->store_id ?? 1,
                'customer_name' => $request->customer_name,
                'customer_type' => $customerType,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'mobile_primary' => $request->mobile,
                'customer_code' => $customer_code,
                'status' => 1,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'created_by' => auth()->id(),
                'credit_limit' => 0,
                'opening_balance' => 0,
                'price_level' => 0,
            ]);

            DB::commit();

            // Trigger SMS notification
            try {
                if ($customer->mobile) {
                    $this->smsTriggerService->trigger('CustomerAdded', $customer);
                }
            } catch (Exception $smsEx) {
                Log::warning('SMS Failure during Quick Add (Customer still created): ' . $smsEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'customer' => $customer
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Quick Add Customer Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating customer: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_add')) {
            abort(403, 'Unauthorized access to add customers.');
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_type' => 'required|string|in:regular,emi',
            'mobile' => 'required|string|regex:/^\d{11}$/|unique:db_customers,mobile',
            'email' => 'nullable|email|max:255|unique:db_customers,email',
            'credit_limit' => 'required|numeric|min:0',
            'opening_balance' => 'nullable|numeric|min:0',
            'price_level_type' => 'nullable|string|in:Increase,Decrease',
            'price_level' => 'nullable|numeric|min:0',
            'vatin' => 'nullable|string|max:255',
            'phone' => 'nullable|string|regex:/^\d{11}$/',
            'attachment_1' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            
            // Basic Info (EMI Required)
            'customer_id_card' => 'required_if:customer_type,emi|nullable|string|max:255',
            'father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'mother_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'dob' => 'required_if:customer_type,emi|nullable|date',
            'mobile_secondary' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'present_address' => 'required_if:customer_type,emi|nullable|string',
            'permanent_address' => 'required_if:customer_type,emi|nullable|string',
            'occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
            'monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
            'workplace_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'workplace_address' => 'required_if:customer_type,emi|nullable|string|max:255',

            // Documents (EMI Required)
            'photo' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'nid_front' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'nid_back' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'job_id_card' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Guardian (EMI Required)
            'g_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'g_relationship' => 'required_if:customer_type,emi|nullable|string|max:255',
            'g_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'g_photo' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'g_nid_front' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'g_nid_back' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Guarantor (EMI Required)
            'gr_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_address' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'gr_occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
            'gr_photo' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_nid_front' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_nid_back' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_job_id' => 'required_if:customer_type,emi|nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Generate customer code
            $customer_code = \App\Services\CodeGeneratorService::generate('customer');

            // Create Customer
            $customer = DbCustomer::create([
                'store_id' => auth()->user()->store_id ?? current_store_id(),
                'customer_name' => $request->customer_name,
                'customer_type' => $request->customer_type,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'phone' => $request->phone,
                'gstin' => $request->gstin,
                'tax_number' => $request->tax_number,
                'vatin' => $request->vatin,
                'credit_limit' => $request->credit_limit,
                'opening_balance' => $request->opening_balance ?? 0,
                'price_level_type' => $request->price_level_type,
                'price_level' => $request->price_level,
                'customer_code' => $customer_code,
                'status' => 1,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'created_by' => Auth::id(),
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city' => $request->city,
                'postcode' => $request->postcode,
                'address' => $request->address,
                'location_link' => $request->location_link,
                'ship_country_id' => $request->ship_country_id,
                'ship_state_id' => $request->ship_state_id,
                'ship_city' => $request->ship_city,
                'ship_postcode' => $request->ship_postcode,
                'ship_address' => $request->ship_address,
                'customer_id_card' => $request->customer_id_card,
                'father_name' => $request->father_name,
                'mother_name' => $request->mother_name,
                'dob' => $request->dob,
                'mobile_primary' => $request->mobile, // Sync legacy mobile to mobile_primary
                'mobile_secondary' => $request->mobile_secondary,
                'present_address' => $request->present_address,
                'permanent_address' => $request->permanent_address,
                'occupation' => $request->occupation,
                'monthly_income' => $request->monthly_income ?? 0,
                'workplace_name' => $request->workplace_name,
                'workplace_address' => $request->workplace_address,
            ]);

            // Handle Customer Files
            $this->handleFileUploads($request, $customer);

            // Create Guardian
            if ($request->g_name || $request->hasFile('g_photo')) {
                $guardian = CustomerGuardian::create([
                    'customer_id' => $customer->id,
                    'name' => $request->g_name,
                    'relationship' => $request->g_relationship,
                    'mobile' => $request->g_mobile,
                ]);
                $this->handleFileUploads($request, $guardian, 'guardian');
            }

            // Create Guarantor
            if ($request->gr_name || $request->hasFile('gr_photo')) {
                $guarantor = CustomerGuarantor::create([
                    'customer_id' => $customer->id,
                    'name' => $request->gr_name,
                    'father_name' => $request->gr_father_name,
                    'address' => $request->gr_address,
                    'mobile' => $request->gr_mobile,
                    'occupation' => $request->gr_occupation,
                    'monthly_income' => $request->gr_monthly_income ?? 0,
                ]);
                $this->handleFileUploads($request, $guarantor, 'guarantor');
            }

            DB::commit();

            // Trigger SMS notification
            if ($customer->mobile || $customer->phone) {
                $this->smsTriggerService->trigger('CustomerAdded', $customer);
            }

            return redirect()->route('contacts.customers.list')->with('success', 'Customer created successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error creating customer: ' . $e->getMessage());
        }
    }

    public function saveStep(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_add')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to save customers.'], 403);
        }

        $step = $request->input('current_step');
        $id = $request->input('id');
        $customerType = $request->input('customer_type', 'regular');

        // Provide schema-safe default values for optional EMI fields to avoid DB constraint errors
        $dbDefaults = [
            'monthly_income' => 0,
            'mobile_secondary' => '',
            'occupation' => '',
            'workplace_name' => '',
            'workplace_address' => '',
            'present_address' => '',
            'permanent_address' => '',
            'customer_id_card' => '',
            'father_name' => '',
            'mother_name' => '',
            'dob' => '2000-01-01', // Safe dummy date for non-EMI
            'email' => '',
            'phone' => '',
            'gstin' => '',
            'tax_number' => '',
            'vatin' => '',
            'location_link' => '',
            'address' => '',
            'ship_address' => '',
        ];

        // On edit, load the current record up-front so a step that omits or blanks a field
        // preserves the existing stored value instead of resetting it to a hardcoded default
        // (previously, e.g., editing only customer_name on a complete EMI customer could wipe
        // monthly_income -> 0 or dob -> '2000-01-01' when the step didn't re-post those values).
        $existingCustomer = null;
        if ($id) {
            $existingCustomer = DbCustomer::find($id);
            if (!$existingCustomer) {
                return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
            }
        }

        // Optional single-value columns that a user may genuinely clear. For these, a request
        // that EXPLICITLY posts an empty value is honored as an intentional clear; for every
        // other field (EMI-mandatory / NOT NULL / not re-posted) the stored value is preserved.
        $optionalClearable = ['email', 'phone', 'gstin', 'tax_number', 'vatin', 'location_link', 'address', 'ship_address'];

        foreach ($dbDefaults as $field => $default) {
            if ($request->filled($field)) {
                continue; // usable incoming value — keep it
            }

            $stored = $existingCustomer ? ($existingCustomer->{$field} ?? null) : null;
            $hasStored = ($stored !== null && $stored !== '');

            if ($hasStored) {
                if (!$request->exists($field) || !in_array($field, $optionalClearable, true)) {
                    // Field not re-posted, or a required/EMI/NOT NULL column whose blank value is
                    // meaningless — preserve the value already on the record.
                    $request->merge([$field => $stored]);
                } else {
                    // Optional field explicitly posted empty -> honor the clear.
                    $request->merge([$field => $default]);
                }
            } else {
                // Nothing stored (create path or genuinely empty) — schema-safe default.
                $request->merge([$field => $default]);
            }
        }

        $rules = [];
        if ($step === 'basic') {
            $rules = [
                'customer_name' => 'required|string|max:255',
                'customer_type' => 'required|string|in:regular,emi',
                'mobile' => 'required|string|regex:/^\d{11}$/|unique:db_customers,mobile' . ($id ? ',' . $id : ''),
                'email' => 'nullable|email|max:255|unique:db_customers,email' . ($id ? ',' . $id : ''),
                'credit_limit' => 'required|numeric|min:0',
                'opening_balance' => 'nullable|numeric|min:0',
                'price_level_type' => 'nullable|string|in:Increase,Decrease',
                'price_level' => 'nullable|numeric|min:0',
                'phone' => 'nullable|string|regex:/^\d{11}$/',
                // Conditional EMI
                'customer_id_card' => 'required_if:customer_type,emi|nullable|string|max:255',
                'father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'mother_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'dob' => 'required_if:customer_type,emi|nullable|date',
                'mobile_secondary' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
                'occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
                'monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
                'workplace_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'workplace_address' => 'required_if:customer_type,emi|nullable|string|max:255',
                'present_address' => 'required_if:customer_type,emi|nullable|string',
                'permanent_address' => 'required_if:customer_type,emi|nullable|string',
            ];
        } elseif ($step === 'documents') {
            $rules = [
                'photo' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'nid_front' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'nid_back' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'job_id_card' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
            ];
        } elseif ($step === 'guardian') {
            $rules = [
                'g_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'g_relationship' => 'required_if:customer_type,emi|nullable|string|max:255',
                'g_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
                'g_photo' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'g_nid_front' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'g_nid_back' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
            ];
        } elseif ($step === 'guarantor') {
            $rules = [
                'gr_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'gr_father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
                'gr_address' => 'required_if:customer_type,emi|nullable|string|max:255',
                'gr_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
                'gr_occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
                'gr_monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
                'gr_photo' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'gr_nid_front' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'gr_nid_back' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
                'gr_job_id' => ($id ? 'nullable' : 'required_if:customer_type,emi') . '|image|mimes:jpg,jpeg,png|max:2048',
            ];
        } elseif ($step === 'advanced') {
            $rules = [
                'attachment_1' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            ];
        }

        $request->validate($rules);

        // Strict check: Regular customers never process guardian/guarantor steps. This guard
        // MUST run before DB::beginTransaction() — returning from inside the open transaction
        // (as it previously did) leaked an uncommitted transaction on every skipped step call.
        if ($id && in_array($step, ['guardian', 'guarantor'], true)) {
            $skipCustomer = DbCustomer::find($id);
            if ($skipCustomer && $skipCustomer->customer_type === 'regular') {
                return response()->json(['success' => true, 'id' => $id, 'skipped' => true]);
            }
        }

        DB::beginTransaction();
        try {
            // Track whether this step just CREATED the customer row. The welcome SMS
            // (CustomerAdded) must fire exactly once — on this create — and never on
            // subsequent step calls or on edit saves.
            $justCreated = false;

            if ($step === 'basic') {
                if ($id) {
                    $customer = DbCustomer::findOrFail($id);
                    $customer->update($request->all());
                } else {
                    $customer_code = \App\Services\CodeGeneratorService::generate('customer');
                    $data = $request->all();
                    $data['store_id'] = auth()->user()->store_id ?? current_store_id();
                    $data['customer_code'] = $customer_code;
                    $data['status'] = 1;
                    $data['created_date'] = date('Y-m-d');
                    $data['created_time'] = date('H:i:s');
                    $data['created_by'] = Auth::id();
                    $customer = DbCustomer::create($data);
                    $justCreated = true;
                }
                $id = $customer->id;
            } else {
                $customer = DbCustomer::findOrFail($id);

                if ($step === 'documents') {
                    $this->handleFileUploads($request, $customer);
                } elseif ($step === 'guardian') {
                    $guardianData = [
                        'name' => $request->g_name,
                        'relationship' => $request->g_relationship,
                        'mobile' => $request->g_mobile,
                    ];
                    $guardian = $customer->guardians()->first();
                    if ($guardian) {
                        $guardian->update($guardianData);
                    } else {
                        $guardian = $customer->guardians()->create($guardianData);
                    }
                    $this->handleFileUploads($request, $guardian, 'guardian');
                } elseif ($step === 'guarantor') {
                    $guarantorData = [
                        'name' => $request->gr_name,
                        'father_name' => $request->gr_father_name,
                        'address' => $request->gr_address,
                        'mobile' => $request->gr_mobile,
                        'occupation' => $request->gr_occupation,
                        'monthly_income' => $request->gr_monthly_income ?? 0,
                    ];
                    $guarantor = $customer->guarantors()->first();
                    if ($guarantor) {
                        $guarantor->update($guarantorData);
                    } else {
                        $guarantor = $customer->guarantors()->create($guarantorData);
                    }
                    $this->handleFileUploads($request, $guarantor, 'guarantor');
                } elseif ($step === 'advanced') {
                    $customer->update($request->all());
                    $this->handleFileUploads($request, $customer);
                }
            }

            DB::commit();

            // Fire the welcome SMS exactly once — only on the step that actually created the
            // row (first 'basic' step call without an id). Subsequent document/guardian/etc.
            // step calls and every edit save must NOT re-trigger it.
            if ($justCreated && ($customer->mobile || $customer->phone)) {
                try {
                    $this->smsTriggerService->trigger('CustomerAdded', $customer);
                } catch (Exception $smsEx) {
                    Log::warning('SMS Failure during Wizard Add (Customer still created): ' . $smsEx->getMessage());
                }
            }

            // Flash success message on the final step before the frontend redirects
            if ($step === 'advanced') {
                session()->flash('success', 'Customer saved successfully.');
            }
            
            return response()->json(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_edit')) {
            abort(403, 'Unauthorized access to edit customers.');
        }

        $customer = DbCustomer::with(['guardians', 'guarantors'])->find($id);
        if (!$customer) {
            return redirect()->route('contacts.customers.list')->with('error', 'Customer not found.');
        }
        $countries = DbCountry::all();
        $states = DbState::all();
        return view('module.contacts.add_customer', compact('customer', 'countries', 'states'));
    }

    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_edit')) {
            abort(403, 'Unauthorized access to edit customers.');
        }

        $customer = DbCustomer::find($id);
        if (!$customer) {
            return redirect()->route('contacts.customers.list')->with('error', 'Customer not found.');
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_type' => 'required|string|in:regular,emi',
            'mobile' => 'required|string|regex:/^\d{11}$/|unique:db_customers,mobile,' . $id,
            'email' => 'nullable|email|max:255|unique:db_customers,email,' . $id,
            'credit_limit' => 'required|numeric|min:0',
            'opening_balance' => 'nullable|numeric|min:0',
            'price_level_type' => 'nullable|string|in:Increase,Decrease',
            'price_level' => 'nullable|numeric|min:0',
            'phone' => 'nullable|string|regex:/^\d{11}$/',
            'attachment_1' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',

            // Basic Info (EMI Required)
            'customer_id_card' => 'required_if:customer_type,emi|nullable|string|max:255',
            'father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'mother_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'dob' => 'required_if:customer_type,emi|nullable|date',
            'mobile_secondary' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'present_address' => 'required_if:customer_type,emi|nullable|string',
            'permanent_address' => 'required_if:customer_type,emi|nullable|string',
            'occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
            'monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
            'workplace_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'workplace_address' => 'required_if:customer_type,emi|nullable|string|max:255',

            // Files are nullable on update because they might already exist
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'nid_front' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'nid_back' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'job_id_card' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Guardian
            'g_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'g_relationship' => 'required_if:customer_type,emi|nullable|string|max:255',
            'g_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'g_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'g_nid_front' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'g_nid_back' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Guarantor
            'gr_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_father_name' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_address' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_mobile' => 'required_if:customer_type,emi|nullable|string|regex:/^\d{11}$/',
            'gr_occupation' => 'required_if:customer_type,emi|nullable|string|max:255',
            'gr_monthly_income' => 'required_if:customer_type,emi|nullable|numeric|min:0',
            'gr_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_nid_front' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_nid_back' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'gr_job_id' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $customer->update($request->only([
                'customer_name', 'customer_type', 'mobile', 'email', 'phone', 'gstin', 'tax_number', 
                'vatin', 'credit_limit', 'opening_balance', 'price_level_type', 'price_level',
                'country_id', 'state_id', 'city', 'postcode', 'address', 'location_link',
                'ship_country_id', 'ship_state_id', 'ship_city', 'ship_postcode', 'ship_address',
                'customer_id_card', 'father_name', 'mother_name', 'dob', 'mobile_primary', 
                'mobile_secondary', 'present_address', 'permanent_address', 'occupation', 
                'monthly_income', 'workplace_name', 'workplace_address'
            ]));

            // Sync mobile to mobile_primary if provided
            if ($request->has('mobile')) {
                $customer->update(['mobile_primary' => $request->mobile]);
            }

            $this->handleFileUploads($request, $customer);

            // Update/Create Guardian (Primary)
            $guardianData = [
                'name' => $request->g_name,
                'relationship' => $request->g_relationship,
                'mobile' => $request->g_mobile,
            ];
            $guardian = $customer->guardians()->first();
            if ($guardian) {
                $guardian->update($guardianData);
            } elseif ($request->g_name) {
                $guardian = $customer->guardians()->create($guardianData);
            }
            if ($guardian) $this->handleFileUploads($request, $guardian, 'guardian');

            // Update/Create Guarantor (Primary)
            $guarantorData = [
                'name' => $request->gr_name,
                'father_name' => $request->gr_father_name,
                'address' => $request->gr_address,
                'mobile' => $request->gr_mobile,
                'occupation' => $request->gr_occupation,
                'monthly_income' => $request->gr_monthly_income ?? 0,
            ];
            $guarantor = $customer->guarantors()->first();
            if ($guarantor) {
                $guarantor->update($guarantorData);
            } elseif ($request->gr_name) {
                $guarantor = $customer->guarantors()->create($guarantorData);
            }
            if ($guarantor) $this->handleFileUploads($request, $guarantor, 'guarantor');

            DB::commit();
            return redirect()->route('contacts.customers.list')->with('success', 'Customer updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error updating customer: ' . $e->getMessage());
        }
    }

    /**
     * Handle file uploads for Customer, Guardian, or Guarantor.
     */
    private function handleFileUploads(Request $request, $model, $type = 'customer')
    {
        $fields = [];
        if ($type === 'customer') {
            $fields = ['attachment_1', 'photo', 'nid_front', 'nid_back', 'job_id_card'];
            $prefix = '';
        } elseif ($type === 'guardian') {
            $fields = ['nid_front', 'nid_back', 'photo'];
            $prefix = 'g_';
        } elseif ($type === 'guarantor') {
            $fields = ['photo', 'nid_front', 'nid_back', 'job_id'];
            $prefix = 'gr_';
        }

        $customerId = ($type === 'customer') ? $model->id : $model->customer_id;
        $pathPrefix = "customers/{$customerId}/" . ($type !== 'customer' ? "{$type}/" : "");

        foreach ($fields as $field) {
            $requestField = $prefix . $field;
            if ($request->hasFile($requestField)) {
                // Delete old file if exists
                if ($model->$field && Storage::disk('public')->exists($model->$field)) {
                    Storage::disk('public')->delete($model->$field);
                }
                // Store new file
                $path = $request->file($requestField)->store($pathPrefix, 'public');
                $model->update([$field => $path]);
            }
        }
    }

    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('customers_delete')) {
            abort(403, 'Unauthorized access to delete customers.');
        }

        $customer = DbCustomer::withCount(['sales', 'emiSales', 'payments'])->find($id);

        if (!$customer) {
            return redirect()->route('contacts.customers.list')->with('error', 'Customer not found.');
        }

        // Block deletion when financial history exists. An unguarded delete here would
        // trigger DB-level onDelete('cascade') on db_emi_sales/db_emi_schedule and
        // db_salespayments, silently destroying financial records with no recovery path.
        if ($customer->sales_count > 0 || $customer->emi_sales_count > 0 || $customer->payments_count > 0) {
            return back()->with('error', 'This customer has sales/payment history and cannot be deleted. Deactivate the customer instead.');
        }

        // Soft delete via the app-wide delete_bit flag only. DbCustomer also uses the
        // SoftDeletes trait, but delete_bit is the flag every existing query filters on
        // (index(), SmsSendController, etc.), so we intentionally do NOT call ->delete()
        // here — the previous double-soft-delete (delete_bit = 1 AND ->delete()) was
        // redundant and could hide rows from any withTrashed() recovery path.
        $customer->delete_bit = 1;
        $customer->save();

        return redirect()->route('contacts.customers.list')->with('success', 'Customer deleted successfully.');
    }

    /**
     * Show the view for importing customers.
     */
    public function import()
    {
        if (auth()->check() && !auth()->user()->hasPermission('import_customers')) {
            abort(403, 'Unauthorized access to import customers.');
        }

        return view('module.contacts.import_customers');
    }

    /**
     * Download a sample CSV template for customer import.
     */
    public function importTemplate()
    {
        if (auth()->check() && !auth()->user()->hasPermission('import_customers')) {
            abort(403, 'Unauthorized access to the customer import template.');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="customers_import_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Customer Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Previous Due',
            'Credit Limit',
            'Country Name',
            'State Name',
            'Postcode',
            'Address',
            'Location Link',
            'Shipping Country Name',
            'Shipping State Name',
            'Shipping Postcode',
            'Shipping Address',
            'Shipping Location Link',
        ];

        $sampleRows = [
            [
                'John Doe',
                '01711000001',
                'john@example.com',
                '01711000001',
                'GST12345',
                'TAX12345',
                '500.00',
                '5000.00',
                'Bangladesh',
                'Dhaka',
                '1200',
                '123 Main Street',
                'https://maps.google.com',
                'Bangladesh',
                'Dhaka',
                '1200',
                '123 Main Street',
                'https://maps.google.com',
            ],
            [
                'Walk-in Customer',
                '01711000002',
                'walkin@example.com',
                '',
                '',
                '',
                '0.00',
                '-1',
                'Bangladesh',
                'Dhaka',
                '1200',
                '456 High Street',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];

        $callback = function () use ($columns, $sampleRows) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns);
            foreach ($sampleRows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Process uploaded CSV file to import customers.
     */
    public function importStore(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('import_customers')) {
            abort(403, 'Unauthorized access to import customers.');
        }

        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'import_file.required' => 'Please select a CSV file to import.',
            'import_file.mimes' => 'The file must be in CSV format.',
            'import_file.max' => 'The file size must not exceed 5MB.',
        ]);

        $uploadedFile = $request->file('import_file');
        $filePath = $uploadedFile->getRealPath();
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            return back()->with('error', 'Unable to open the uploaded file.');
        }

        $importedCount = 0;
        $skippedRows = [];
        $seenMobiles = [];
        $seenEmails = [];
        $rowNum = 1;

        try {
            // Read header row and strip UTF-8 BOM if present
            $rawHeader = fgetcsv($handle);
            if (!$rawHeader || empty(array_filter($rawHeader))) {
                return back()->with('error', 'The uploaded CSV file is empty or missing headers.');
            }

            $rawHeader[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $rawHeader[0]);
            
            // Map header column names to indexes
            $colMap = [];
            foreach ($rawHeader as $index => $colName) {
                $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $colName)));
                $colMap[$clean] = $index;
            }

            // Quick helper to get row value by multiple possible aliases
            $getValue = function (array $row, array $aliases) use ($colMap): string {
                foreach ($aliases as $alias) {
                    if (isset($colMap[$alias]) && isset($row[$colMap[$alias]])) {
                        return trim((string) $row[$colMap[$alias]]);
                    }
                }
                return '';
            };

            // Pre-load country and state lookups
            $countries = DbCountry::pluck('id', DB::raw('LOWER(country)'))->toArray();
            $states = DbState::pluck('id', DB::raw('LOWER(state)'))->toArray();

            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                // Skip completely empty rows
                if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                    continue;
                }

                $customerName = $getValue($row, ['customername', 'name', 'customer']);
                if ($customerName === '') {
                    $skippedRows[] = "Row {$rowNum}: Customer Name is required.";
                    continue;
                }

                $mobile = $getValue($row, ['mobile', 'mobilenumber', 'mobilephone']);
                if ($mobile !== '') {
                    if (in_array($mobile, $seenMobiles, true) || DbCustomer::where('mobile', $mobile)->where('delete_bit', 0)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: Customer with mobile '{$mobile}' already exists.";
                        continue;
                    }
                    $seenMobiles[] = $mobile;
                }

                $email = $getValue($row, ['email', 'emailaddress']);
                if ($email !== '') {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $skippedRows[] = "Row {$rowNum}: Invalid email format '{$email}'.";
                        continue;
                    }
                    $emailLower = strtolower($email);
                    if (in_array($emailLower, $seenEmails, true) || DbCustomer::where('email', $email)->where('delete_bit', 0)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: Customer with email '{$email}' already exists.";
                        continue;
                    }
                    $seenEmails[] = $emailLower;
                }

                $phone = $getValue($row, ['phone', 'phonenumber']);
                $gstin = $getValue($row, ['gstnumber', 'gstin', 'gst']);
                $taxNumber = $getValue($row, ['taxnumber', 'tax', 'taxno']);
                $previousDue = $getValue($row, ['previousdue', 'openingbalance', 'balance', 'due']);
                $creditLimit = $getValue($row, ['creditlimit', 'limit']);
                $countryName = strtolower($getValue($row, ['countryname', 'country']));
                $stateName = strtolower($getValue($row, ['statename', 'state']));
                $postcode = $getValue($row, ['postcode', 'zip', 'postalcode']);
                $address = $getValue($row, ['address', 'streetaddress']);
                $locationLink = $getValue($row, ['locationlink', 'location']);
                
                $shipCountryName = strtolower($getValue($row, ['shippingcountryname', 'shippingcountry', 'shipcountry']));
                $shipStateName = strtolower($getValue($row, ['shippingstatename', 'shippingstate', 'shipstate']));
                $shipPostcode = $getValue($row, ['shippingpostcode', 'shippingzip', 'shippostcode']);
                $shipAddress = $getValue($row, ['shippingaddress', 'shipaddress']);

                $countryId = $countries[$countryName] ?? null;
                $stateId = $states[$stateName] ?? null;
                $shipCountryId = $countries[$shipCountryName] ?? null;
                $shipStateId = $states[$shipStateName] ?? null;

                $customerCode = \App\Services\CodeGeneratorService::generate('customer');

                DbCustomer::create([
                    'store_id' => auth()->user()->store_id ?? current_store_id(),
                    'customer_name' => $customerName,
                    'customer_type' => 'regular',
                    'customer_code' => $customerCode,
                    'mobile' => $mobile !== '' ? $mobile : null,
                    'mobile_primary' => $mobile !== '' ? $mobile : null,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'gstin' => $gstin !== '' ? $gstin : null,
                    'tax_number' => $taxNumber !== '' ? $taxNumber : null,
                    'opening_balance' => is_numeric($previousDue) ? (float)$previousDue : 0,
                    'credit_limit' => is_numeric($creditLimit) ? (float)$creditLimit : 0,
                    'country_id' => $countryId,
                    'state_id' => $stateId,
                    'postcode' => $postcode !== '' ? $postcode : null,
                    'address' => $address !== '' ? $address : null,
                    'location_link' => $locationLink !== '' ? $locationLink : null,
                    'ship_country_id' => $shipCountryId,
                    'ship_state_id' => $shipStateId,
                    'ship_postcode' => $shipPostcode !== '' ? $shipPostcode : null,
                    'ship_address' => $shipAddress !== '' ? $shipAddress : null,
                    'status' => 1,
                    'delete_bit' => 0,
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                    'created_by' => Auth::id(),
                    'store_id' => function_exists('store_settings') && store_settings() ? store_settings()->id : null,
                ]);

                $importedCount++;
            }

            DB::commit();

            return redirect()->route('contacts.customers.import')->with('import_summary', [
                'imported' => $importedCount,
                'skipped' => count($skippedRows),
                'errors' => $skippedRows,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Customer bulk import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Import failed due to a system error: ' . $e->getMessage());
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
