<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DbCurrency;
use App\Models\DbLanguage;
use App\Models\DbStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreSettingsController extends Controller
{
    /**
     * Resolve the acting store from the authenticated user's store_id.
     *
     * There is no route-model binding on this page, so this explicit, scoped
     * lookup IS the store-scoping guard: a user can only ever read/write their
     * own store's settings, never DbStore::first().
     */
    private function resolveActingStore(): DbStore
    {
        $storeId = (int) auth()->user()->store_id;

        if (empty($storeId)) {
            abort(403, 'No store is associated with your account.');
        }

        $store = DbStore::where('id', $storeId)->first();

        if (!$store) {
            abort(404, 'Your store was not found.');
        }

        return $store;
    }

    public function edit()
    {
        // Established in-controller gate pattern (mirrors WarehouseController:82,
        // ExpenseController:154, DepositController:22). A hidden sidebar link is
        // not access control.
        if (auth()->check() && !auth()->user()->hasPermission('store_settings_view')) {
            abort(403, 'Unauthorized access to store settings.');
        }

        $store = $this->resolveActingStore();

        $languages = DB::table('db_languages')->get();
        $currencies = DB::table('db_currency')->get();
        $countries = DB::table('db_country')->get();

        $states = collect();
        if ($store->country) {
            $states = DB::table('db_states')->where('country', $store->country)->get();
        }

        $timezones = collect(\DateTimeZone::listIdentifiers())->map(function ($tz) {
            return ['id' => $tz, 'name' => $tz];
        });

        // Minimal read-only activity surface for the acting store. Explicitly
        // scoped with forStore() (the audit table intentionally has NO silent
        // global scope) so one store's trail is never shown to another.
        $actingStoreId = (int) $store->id;

        $activityQuery = ActivityLog::query()->forStore($actingStoreId);

        if ($actionFilter = request('action')) {
            $activityQuery->where('action', $actionFilter);
        }
        if ($fromDate = request('from_date')) {
            $activityQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = request('to_date')) {
            $activityQuery->whereDate('created_at', '<=', $toDate);
        }

        $activities = $activityQuery->latest('id')->paginate(15, ['*'], 'activity_page')->withQueryString();

        $activityActions = ActivityLog::query()
            ->forStore($actingStoreId)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('module.settings.store', compact(
            'store',
            'languages',
            'currencies',
            'countries',
            'states',
            'timezones',
            'activities',
            'activityActions'
        ));
    }

    public function getStates($country_id)
    {
        $country = DB::table('db_country')->where('id', $country_id)->first();
        if (!$country) {
            return response()->json([]);
        }
        $states = DB::table('db_states')->where('country', $country->country)->get();
        return response()->json($states);
    }

    public function update(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('store_settings_edit')) {
            abort(403, 'Unauthorized access to edit store settings.');
        }

        $store = $this->resolveActingStore();
        $actingStoreId = (int) $store->id;

        $data = $request->validate([
            'store_name' => 'required|string|max:255',
            'mobile' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gst_no' => 'nullable|string|max:50',
            'vat_no' => 'nullable|string|max:50',
            'pan_no' => 'nullable|string|max:50',
            'store_website' => 'nullable|string|max:255',
            'bank_details' => 'nullable|string',
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'required|string|max:255',
            'postcode' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            // System Settings
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'date_format' => 'nullable|string',
            'time_format' => 'nullable|string',
            'currency_id' => 'required|integer|exists:db_currency,id',
            'currency_placement' => 'nullable|string',
            'language_id' => 'nullable|integer|exists:db_languages,id',
            'round_off' => 'nullable|integer',
            'decimals' => 'nullable|integer',
            'qty_decimals' => 'nullable|integer',

            // Sales Tab
            // NOTE: sales_discount, sales_invoice_format_id, pos_invoice_format_id
            // and mrp_column were previously validated here with NO form control
            // and NO consumer anywhere in the codebase (dead columns). The phantom
            // rules were removed rather than adding UI for dead fields.
            'number_to_words' => 'nullable|integer',
            'change_return' => 'nullable|integer',
            'previous_balance_bit' => 'nullable|integer',
            'sales_invoice_footer_text' => 'nullable|string',
            't_and_c_status' => 'nullable|integer',
            'invoice_terms' => 'nullable|string',

            // Prefixes Tab
            'category_init' => 'nullable|string|max:50',
            'supplier_init' => 'nullable|string|max:50',
            'purchase_return_init' => 'nullable|string|max:50',
            'sales_init' => 'nullable|string|max:50',
            'expense_init' => 'nullable|string|max:50',
            'item_init' => 'nullable|string|max:50',
            'purchase_init' => 'nullable|string|max:50',
            'customer_init' => 'nullable|string|max:50',
            'sales_return_init' => 'nullable|string|max:50',
            'accounts_init' => 'nullable|string|max:50',
            'journal_init' => 'nullable|string|max:50',
            'quotation_init' => 'nullable|string|max:50',
            'money_transfer_init' => 'nullable|string|max:50',
            'sales_payment_init' => 'nullable|string|max:50',
            'sales_return_payment_init' => 'nullable|string|max:50',
            'purchase_payment_init' => 'nullable|string|max:50',
            'purchase_return_payment_init' => 'nullable|string|max:50',
            'expense_payment_init' => 'nullable|string|max:50',
            'cust_advance_init' => 'nullable|string|max:50',
        ]);

        $selectedCurrencyId = !empty($data['currency_id']) ? (int) $data['currency_id'] : null;
        $currencyNeedsActivation = $selectedCurrencyId && (
            (int) $store->currency_id !== $selectedCurrencyId ||
            DbCurrency::where('id', $selectedCurrencyId)->where('status', 1)->doesntExist()
        );

        $selectedLanguageId = !empty($data['language_id']) ? (int) $data['language_id'] : null;
        $languageNeedsActivation = $selectedLanguageId && (
            (int) $store->language_id !== $selectedLanguageId ||
            DbLanguage::where('id', $selectedLanguageId)->where('status', 1)->doesntExist()
        );

        // Snapshot the CURRENT values of the submitted fields BEFORE the write so
        // a genuine old→new diff can be logged. Only keys present in $data are
        // snapshotted, so unchanged fields never produce log noise.
        $before = $store->only(array_keys($data));

        DB::transaction(function () use (
            $store,
            $data,
            $request,
            $selectedCurrencyId,
            $currencyNeedsActivation,
            $selectedLanguageId,
            $languageNeedsActivation,
            $actingStoreId,
            $before
        ) {
            if ($request->hasFile('logo')) {
                if ($store->store_logo) {
                    Storage::disk('public')->delete($store->store_logo);
                }
                $data['store_logo'] = $request->file('logo')->store('logos', 'public');
            }

            $store->update($data);

            if ($currencyNeedsActivation) {
                DbCurrency::activateCurrency($selectedCurrencyId, $actingStoreId);
            }

            if ($languageNeedsActivation) {
                DbLanguage::activateLanguage($selectedLanguageId, $actingStoreId);
            }

            // Activity trail: record ONLY fields whose value actually changed.
            // A submission that posts identical values must not create a row.
            $this->recordSettingsActivity($request, $store, $before, array_keys($data));

            store_settings(true, $actingStoreId);
        });

        return redirect()->route('settings.store')->with('success', 'Store settings updated successfully.');
    }

    /**
     * Persist one activity_logs row describing which store-settings fields
     * actually changed (old → new). No row is written when nothing changed.
     *
     * Comparisons are normalised to strings so a DB-native int (e.g. 1) and a
     * form-posted string (e.g. "1") do not register as a spurious change.
     *
     * @param  array<string,mixed>  $before    Field values captured before the write.
     * @param  array<int,string>    $fieldNames Keys present in the submitted payload.
     */
    private function recordSettingsActivity(Request $request, DbStore $store, array $before, array $fieldNames): void
    {
        $old = [];
        $new = [];

        foreach ($fieldNames as $field) {
            // `logo` is the transient file-input value (an UploadedFile); the
            // persisted value lives in `store_logo`, so skip the raw input key.
            if ($field === 'logo') {
                continue;
            }

            $oldValue = $before[$field] ?? null;
            $newValue = $store->{$field};

            if ($this->settingValuesDiffer($oldValue, $newValue)) {
                $old[$field] = $oldValue;
                $new[$field] = $newValue;
            }
        }

        if (empty($new)) {
            // Nothing actually changed — do not create an audit row.
            return;
        }

        ActivityLog::create([
            'store_id' => $store->id,
            'user_id' => auth()->id(),
            'action' => 'settings_updated',
            'entity_type' => DbStore::class,
            'entity_id' => $store->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Whether a store-settings value materially changed between the pre-write
     * snapshot and the persisted value, ignoring int/string representation.
     */
    private function settingValuesDiffer(mixed $old, mixed $new): bool
    {
        $normalise = function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            return trim((string) $value);
        };

        $oldNormalised = $normalise($old);
        $newNormalised = $normalise($new);

        // Treat null and empty-string as equivalent (both mean "no value"), so a
        // nullable field left blank does not flood the log on every save.
        $oldEmpty = $oldNormalised === null || $oldNormalised === '';
        $newEmpty = $newNormalised === null || $newNormalised === '';

        if ($oldEmpty && $newEmpty) {
            return false;
        }

        return $oldNormalised !== $newNormalised;
    }
}
