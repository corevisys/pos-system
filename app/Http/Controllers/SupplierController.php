<?php

namespace App\Http\Controllers;

use App\Models\DbSupplier;
use App\Models\DbCountry;
use App\Models\DbState;
use App\Models\AcTransaction;
use App\Models\DbPurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Store-scoped base query — cross-store supplier rows must never appear on this list.
        $query = DbSupplier::where('delete_bit', 0)
            ->where('store_id', current_store_id());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                  ->orWhere('supplier_code', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array((string) $request->status, ['0', '1'], true)) {
            $query->where('status', (int) $request->status);
        }

        // 'Account Payable Only' filter flag — resolved once, applied to every branch
        // (page, CSV, print/PDF) so exports mirror exactly what the screen shows.
        $accountPayableOnly = filter_var($request->input('account_payable'), FILTER_VALIDATE_BOOLEAN);

        // Account-Payable-only is applied IN SQL (bounded + indexed) rather than by loading
        // every supplier in the store into memory to filter in PHP. Live purchase due =
        // opening_balance + purchase payables − purchase payments − purchase-return payables,
        // computed via two indexed scalar subqueries on ac_transactions. The WHERE runs on the
        // DB before LIMIT/OFFSET, so a store with thousands of suppliers never materializes
        // the full set for this screen.
        if ($accountPayableOnly) {
            $storeId = current_store_id();
            $query->whereRaw(
                '(COALESCE(db_suppliers.opening_balance, 0)'
                . ' + COALESCE((SELECT SUM(ac_transactions.credit_amt) FROM ac_transactions'
                . ' WHERE ac_transactions.supplier_id = db_suppliers.id'
                . ' AND ac_transactions.store_id = ?'
                . " AND ac_transactions.transaction_type = 'PURCHASE PAYABLE'), 0)"
                . ' - COALESCE((SELECT SUM(ac_transactions.debit_amt) FROM ac_transactions'
                . ' WHERE ac_transactions.supplier_id = db_suppliers.id'
                . ' AND ac_transactions.store_id = ?'
                . " AND ac_transactions.transaction_type IN ('PURCHASE PAYMENT', 'PURCHASE RETURN PAYABLE')), 0)) > 0",
                [$storeId, $storeId]
            );
        }

        // Print/PDF export view. Exports legitimately materialize the full filtered result
        // (they are file downloads, not the on-screen list) — the account_payable predicate
        // above already narrowed the query, and the attach+PHP-filter below is kept as a
        // harmless belt-and-braces so exports always mirror the on-screen filter.
        if ($request->export === 'print' || $request->export === 'pdf') {
            $printSuppliers = $query->orderBy('id', 'desc')->get();
            $this->attachLiveDues($printSuppliers);
            if ($accountPayableOnly) {
                $printSuppliers = $printSuppliers->filter(fn($s) => (float) ($s->live_purchase_due ?? 0) > 0)->values();
            }

            return view('module.contacts.suppliers_list_print', [
                'suppliers' => $printSuppliers,
                'totalOpeningBalance' => (float) $printSuppliers->sum('opening_balance'),
                'totalPurchaseDue' => (float) $printSuppliers->sum('live_purchase_due'),
                'totalReturnDue' => (float) $printSuppliers->sum('live_return_due'),
            ]);
        }

        // CSV export
        if ($request->export === 'csv') {
            $exportSuppliers = $query->orderBy('id', 'desc')->get();
            $this->attachLiveDues($exportSuppliers);
            if ($accountPayableOnly) {
                $exportSuppliers = $exportSuppliers->filter(fn($s) => (float) ($s->live_purchase_due ?? 0) > 0)->values();
            }
            $filename = "suppliers_list_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($exportSuppliers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Supplier Code', 'Supplier Name', 'Mobile', 'Email', 'Location', 'Previous Bal', 'Purchase Due', 'Return Due', 'Status']);

                foreach ($exportSuppliers as $s) {
                    fputcsv($file, [
                        $s->supplier_code,
                        $s->supplier_name,
                        $s->mobile ?? '',
                        $s->email ?? '',
                        $s->city ?? '',
                        (float) $s->opening_balance,
                        (float) $s->live_purchase_due,
                        (float) $s->live_return_due,
                        $s->status == 1 ? 'Active' : 'Inactive',
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50], true)
            ? (int) $request->input('limit', 10)
            : 10;

        // Single shared paginated path for BOTH the plain list and the account-payable-only
        // list (the WHERE above did the computed filter in SQL). Live dues are then attached
        // only for the CURRENT PAGE's supplier IDs (bounded WHERE IN), so no full-store
        // in-memory materialization happens on either path.
        $suppliers = $query->latest()->paginate($limit)->withQueryString();
        $this->attachLiveDues($suppliers);

        return view('module.contacts.suppliers_list', compact('suppliers'));
    }

    /**
     * Compute and attach live ledger dues for a collection/paginator of suppliers.
     */
    private function attachLiveDues($suppliers)
    {
        $supplierIds = $suppliers->pluck('id')->filter()->toArray();
        if (empty($supplierIds)) {
            return;
        }

        $storeId = current_store_id();

        // Store-scoped ledger sums — AcTransaction and DbPurchaseReturn carry store_id and are
        // filtered to the current store so store-2 ledger rows can never inflate store-1 dues.
        $payables = AcTransaction::whereIn('supplier_id', $supplierIds)
            ->where('store_id', $storeId)
            ->where('transaction_type', 'PURCHASE PAYABLE')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(credit_amt) as total_payable')
            ->pluck('total_payable', 'supplier_id');

        $payments = AcTransaction::whereIn('supplier_id', $supplierIds)
            ->where('store_id', $storeId)
            ->whereIn('transaction_type', ['PURCHASE PAYMENT', 'PURCHASE RETURN PAYABLE'])
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(debit_amt) as total_paid')
            ->pluck('total_paid', 'supplier_id');

        $returnDues = DbPurchaseReturn::whereIn('supplier_id', $supplierIds)
            ->where('store_id', $storeId)
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(grand_total - paid_amount) as total_return_due')
            ->pluck('total_return_due', 'supplier_id');

        foreach ($suppliers as $s) {
            $totalPayable = (float) ($payables[$s->id] ?? 0);
            $totalPaid = (float) ($payments[$s->id] ?? 0);
            $s->live_purchase_due = max(0.0, (float) $s->opening_balance + $totalPayable - $totalPaid);
            $s->live_return_due = max(0.0, (float) ($returnDues[$s->id] ?? 0));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $countries = DbCountry::all();
        $states = DbState::all();
        $currencySymbol = \App\Providers\AppServiceProvider::resolveCurrencySymbol();
        return view('module.contacts.add_supplier', compact('countries', 'states', 'currencySymbol'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $storeId = current_store_id();

        // PER-STORE uniqueness (Phase 4): mobile/email are unique only within the current
        // store — the same phone number may belong to a supplier in another branch.
        $validated = $this->validateSupplierRequest($request, $storeId, null);
        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        // Generate supplier code
        $supplier_code = \App\Services\CodeGeneratorService::generate('supplier');

        $supplierData = [
            'store_id' => $storeId,
            'supplier_name' => $request->supplier_name,
            'mobile' => $request->mobile ?: null,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'gstin' => $request->gstin,
            'tax_number' => $request->tax_number,
            'vatin' => $request->vatin,
            'opening_balance' => $request->opening_balance ?? 0,
            'supplier_code' => $supplier_code,
            'status' => 1,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_by' => Auth::id(),
            // Location
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'city' => $request->city,
            'postcode' => $request->postcode,
            'address' => $request->address,
            'location_link' => $request->location_link,
        ];

        // Handle attachment if uploaded
        if ($request->hasFile('attachment_1')) {
            $path = $request->file('attachment_1')->store('suppliers', 'public');
            $supplierData['attachment_1'] = $path;
        }

        try {
            $supplier = DbSupplier::create($supplierData);
        } catch (\Illuminate\Database\QueryException $e) {
            // Defense-in-depth backstop: a genuine concurrent insert can slip past the
            // validator and trip the per-store unique index. Translate the DB error back
            // into the same clean message the validator produces.
            Log::error('Supplier create unique violation: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->supplierUniqueViolationResponse($request, $e, 'Supplier could not be saved due to a system error.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully.',
                'id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name
            ]);
        }

        return redirect()->route('contacts.suppliers.list')->with('success', 'Supplier created successfully.');
    }

    public function edit($id)
    {
        // Store-scoped lookup (IDOR protection) — only this store's suppliers may be edited.
        $supplier = DbSupplier::where('store_id', current_store_id())->where('delete_bit', 0)->find($id);
        if (!$supplier) {
            return redirect()->route('contacts.suppliers.list')->with('error', 'Supplier not found');
        }
        $countries = DbCountry::all();
        $states = DbState::all();
        $currencySymbol = \App\Providers\AppServiceProvider::resolveCurrencySymbol();
        return view('module.contacts.add_supplier', compact('supplier', 'countries', 'states', 'currencySymbol'));
    }

    public function update(Request $request, $id)
    {
        $storeId = current_store_id();

        // PER-STORE uniqueness with ignore-self. The ignore is scoped to (store_id = this
        // store AND id != current), so updating a supplier keeps its own phone/email while
        // still blocking a same-store duplicate owned by a DIFFERENT supplier.
        $validated = $this->validateSupplierRequest($request, $storeId, $id);
        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        // Store-scoped lookup (IDOR protection).
        $supplier = DbSupplier::where('store_id', $storeId)->where('delete_bit', 0)->find($id);
        if (!$supplier) {
            return redirect()->route('contacts.suppliers.list')->with('error', 'Supplier not found');
        }

        $supplierData = [
            'supplier_name' => $request->supplier_name,
            'mobile' => $request->mobile ?: null,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'gstin' => $request->gstin,
            'tax_number' => $request->tax_number,
            'vatin' => $request->vatin,
            'opening_balance' => $request->opening_balance ?? 0,
            // Location
            'country_id' => $request->country_id,
            'state_id' => $request->state_id,
            'city' => $request->city,
            'postcode' => $request->postcode,
            'address' => $request->address,
            'location_link' => $request->location_link,
        ];

        if ($request->hasFile('attachment_1')) {
            $path = $request->file('attachment_1')->store('suppliers', 'public');
            $supplierData['attachment_1'] = $path;
        }

        try {
            $supplier->update($supplierData);
        } catch (\Illuminate\Database\QueryException $e) {
            // Defense-in-depth backstop: same-store concurrent duplicate insert/update.
            Log::error('Supplier update unique violation: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->supplierUniqueViolationResponse($request, $e, 'Supplier could not be saved due to a system error.');
        }

        return redirect()->route('contacts.suppliers.list')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Shared validation for supplier create/update. Enforces PER-STORE uniqueness for
     * mobile/email (same value in another store is allowed). For AJAX/JSON requests a
     * failed validation is translated into the unified quick-add JSON shape:
     *   {success:false, message, errors}
     * so the purchase quick-add modal (which reads data.success / data.message) never
     * receives the framework's bare {message, errors} payload.
     */
    private function validateSupplierRequest(Request $request, int $storeId, ?int $ignoreId): ?\Illuminate\Http\JsonResponse
    {
        $rules = [
            'supplier_name' => 'required|string|max:255',
            'mobile' => [
                'nullable',
                'regex:/^\d{11}$/',
                Rule::unique('db_suppliers', 'mobile')->where(fn($q) => $q->where('store_id', $storeId)),
            ],
            'phone' => ['nullable', 'regex:/^\d{11}$/'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('db_suppliers', 'email')->where(fn($q) => $q->where('store_id', $storeId)),
            ],
            'vatin' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'attachment_1' => 'nullable|file|max:2048|mimes:jpg,jpeg,png,webp,pdf'
        ];

        if ($ignoreId !== null) {
            foreach (['mobile', 'email'] as $field) {
                $rules[$field][count($rules[$field]) - 1]->ignore($ignoreId);
            }
        }

        $messages = [
            'mobile.unique' => 'A supplier with this phone number already exists.',
            'email.unique' => 'A supplier with this email address already exists.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                $message = $validator->errors()->first('mobile')
                    ?? $validator->errors()->first('email')
                    ?? $validator->errors()->first();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return null;
    }

    /**
     * Translate a DB unique-violation on mobile/email into the same clean message the
     * validator produces, preserving the AJAX quick-add JSON shape {success:false, message}.
     * Non-unique QueryExceptions are logged and surfaced with a generic message.
     */
    private function supplierUniqueViolationResponse(Request $request, \Illuminate\Database\QueryException $e, string $genericMessage)
    {
        $raw = strtolower((string) $e->getMessage());
        $isMobile = str_contains($raw, 'mobile') || str_contains($raw, 'db_suppliers_store_mobile_unique');
        $isEmail = str_contains($raw, 'email') || str_contains($raw, 'db_suppliers_store_email_unique');

        if ($isMobile || $isEmail) {
            $cleanMessage = $isMobile
                ? 'A supplier with this phone number already exists.'
                : 'A supplier with this email address already exists.';
        } else {
            $cleanMessage = $genericMessage;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $cleanMessage,
            ], 422);
        }

        return back()->withInput()->withErrors(['mobile' => $cleanMessage]);
    }

    public function destroy($id)
    {
        $storeId = current_store_id();

        // Store-scoped lookup (IDOR protection) — mirrors Account/Transfer/Deposit/Reconciliation.
        $supplier = DbSupplier::where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->withCount(['purchases', 'purchaseReturns', 'purchasePayments', 'transactions'])
            ->find($id);

        if (!$supplier) {
            return redirect()->route('contacts.suppliers.list')->with('error', 'Supplier not found.');
        }

        // Block deletion when financial history exists. Mirrors CustomerController::destroy(),
        // but breaks the guard down per record type so the operator sees exactly which
        // history is attached (count + type) instead of a generic message.
        if ($supplier->purchases_count > 0 || $supplier->purchase_returns_count > 0 || $supplier->purchase_payments_count > 0 || $supplier->transactions_count > 0) {
            $parts = [];
            if ($supplier->purchases_count > 0) {
                $parts[] = "{$supplier->purchases_count} purchase(s)";
            }
            if ($supplier->purchase_returns_count > 0) {
                $parts[] = "{$supplier->purchase_returns_count} purchase return(s)";
            }
            if ($supplier->purchase_payments_count > 0) {
                $parts[] = "{$supplier->purchase_payments_count} payment(s)";
            }
            if ($supplier->transactions_count > 0) {
                $parts[] = "{$supplier->transactions_count} ledger transaction(s)";
            }

            return back()->with('error', 'This supplier has ' . implode(', ', $parts) . ' and cannot be deleted. Deactivate the supplier instead.');
        }

        // Atomic conditional transition — prevents a concurrent double-delete race. Only the
        // request that flips delete_bit 0 → 1 wins; the loser observes affected === 0.
        $affected = DbSupplier::where('id', $id)
            ->where('store_id', $storeId)
            ->where('delete_bit', 0)
            ->update(['delete_bit' => 1]);

        if ($affected !== 1) {
            return redirect()->route('contacts.suppliers.list')->with('error', 'Supplier not found or already deleted.');
        }

        // Soft delete via the app-wide delete_bit flag ONLY (mirror CustomerController::destroy()).
        // We intentionally do NOT call ->delete(): delete_bit is the flag every existing query
        // filters on, and a redundant Eloquent soft-delete would hide rows from withTrashed()
        // recovery paths.
        return redirect()->route('contacts.suppliers.list')->with('success', 'Supplier deleted successfully.');
    }

    /**
     * Show the view for importing suppliers.
     */
    public function import()
    {
        return view('module.contacts.import_suppliers');
    }

    /**
     * Download a sample CSV template for supplier import.
     */
    public function importTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="suppliers_import_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Supplier Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Country Name',
            'State Name',
            'Postcode',
            'Address',
            'Opening Balance',
        ];

        $sampleRows = [
            [
                'Apex Suppliers Ltd',
                '01811000001',
                'apex@example.com',
                '01811000001',
                'GST55443',
                'TAX55443',
                'Bangladesh',
                'Dhaka',
                '1200',
                'Plot 45, Commercial Area',
                '1500.00',
            ],
            [
                'Global Imports Co',
                '01811000002',
                'global@example.com',
                '',
                '',
                '',
                'Bangladesh',
                'Chittagong',
                '4000',
                'Port Road',
                '0.00',
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
     * Process uploaded CSV file to import suppliers.
     */
    public function importStore(Request $request)
    {
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

        // Phase 4: duplicates are only meaningful WITHIN the current store — cross-store
        // suppliers sharing a phone/email are allowed, so every duplicate check below is
        // additionally filtered by store_id.
        $storeId = current_store_id();

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

                $supplierName = $getValue($row, ['suppliername', 'name', 'supplier']);
                if ($supplierName === '') {
                    $skippedRows[] = "Row {$rowNum}: Supplier Name is required.";
                    continue;
                }

                $mobile = $getValue($row, ['mobile', 'mobilenumber', 'mobilephone']);
                if ($mobile !== '') {
                    if (in_array($mobile, $seenMobiles, true) || DbSupplier::where('store_id', $storeId)->where('mobile', $mobile)->where('delete_bit', 0)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: Supplier with mobile '{$mobile}' already exists in this store.";
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
                    if (in_array($emailLower, $seenEmails, true) || DbSupplier::where('store_id', $storeId)->where('email', $email)->where('delete_bit', 0)->exists()) {
                        $skippedRows[] = "Row {$rowNum}: Supplier with email '{$email}' already exists in this store.";
                        continue;
                    }
                    $seenEmails[] = $emailLower;
                }

                $phone = $getValue($row, ['phone', 'phonenumber']);
                $gstin = $getValue($row, ['gstnumber', 'gstin', 'gst']);
                $taxNumber = $getValue($row, ['taxnumber', 'tax', 'taxno']);
                $countryName = strtolower($getValue($row, ['countryname', 'country']));
                $stateName = strtolower($getValue($row, ['statename', 'state']));
                $postcode = $getValue($row, ['postcode', 'zip', 'postalcode']);
                $address = $getValue($row, ['address', 'streetaddress']);
                $openingBalance = $getValue($row, ['openingbalance', 'balance', 'previousdue', 'due']);

                $countryId = $countries[$countryName] ?? null;
                $stateId = $states[$stateName] ?? null;

                $supplierCode = \App\Services\CodeGeneratorService::generate('supplier');

                try {
                    DbSupplier::create([
                        'supplier_name' => $supplierName,
                        'supplier_code' => $supplierCode,
                        'mobile' => $mobile !== '' ? $mobile : null,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone !== '' ? $phone : null,
                        'gstin' => $gstin !== '' ? $gstin : null,
                        'tax_number' => $taxNumber !== '' ? $taxNumber : null,
                        'opening_balance' => is_numeric($openingBalance) ? (float)$openingBalance : 0,
                        'country_id' => $countryId,
                        'state_id' => $stateId,
                        'postcode' => $postcode !== '' ? $postcode : null,
                        'address' => $address !== '' ? $address : null,
                        'status' => 1,
                        'delete_bit' => 0,
                        'created_date' => date('Y-m-d'),
                        'created_time' => date('H:i:s'),
                        'created_by' => Auth::id(),
                        'store_id' => $storeId,
                    ]);

                    $importedCount++;
                } catch (\Illuminate\Database\QueryException $qe) {
                    if ($qe->getCode() == 23000) {
                        $skippedRows[] = "Row {$rowNum}: Duplicate record detected for supplier '{$supplierName}'.";
                    } else {
                        throw $qe;
                    }
                }
            }

            DB::commit();

            return redirect()->route('contacts.suppliers.import')->with('import_summary', [
                'imported' => $importedCount,
                'skipped' => count($skippedRows),
                'errors' => $skippedRows,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Supplier bulk import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Import failed due to a system error: ' . $e->getMessage());
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
