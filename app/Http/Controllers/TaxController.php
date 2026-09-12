<?php

namespace App\Http\Controllers;

use App\Models\DbTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    /**
     * G5 fix: the POS/Sale dropdown reads a per-store cache key
     * ('db_taxes_list_{store_id}', 1-hour TTL). Any tax write must bust the
     * acting store's key so the new/edited/deleted tax appears immediately for
     * that store, without waiting for expiry — while other stores' keys remain
     * untouched.
     */
    private function forgetTaxDropdownCache(int $storeId): void
    {
        Cache::forget('db_taxes_list_' . $storeId);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('tax_view')) {
            abort(403, 'Unauthorized access to view taxes.');
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        // Phase 1 (store scoping): the acting store's rows PLUS explicitly-shared
        // (store_id NULL) rows. Mirrors the PurchaseController/QuotationController
        // "current store OR null" read pattern so a Store-2 user never sees
        // Store-1's tax rates.
        $storeId = current_store_id();
        $storeScoped = function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        };

        // Full individual-tax collection — contract consumed by the Tax Group
        // add/edit modals as the sub-tax checkbox source (must never be paginated).
        $allTaxes = DbTax::where('group_bit', 0)->where($storeScoped)->orderBy('tax_name', 'asc')->get();

        $taxesQuery = DbTax::where('group_bit', 0)->where($storeScoped);
        $taxGroupsQuery = DbTax::where('group_bit', 1)->where($storeScoped);

        // Server-side search across both lists (mirrors Customers/Suppliers pattern).
        if ($request->filled('search')) {
            $search = $request->input('search');
            $taxesQuery->where('tax_name', 'like', "%{$search}%");
            $taxGroupsQuery->where('tax_name', 'like', "%{$search}%");
        }

        // Independent paginators on one page use distinct page query params.
        $taxes = $taxesQuery->orderBy('id', 'asc')->paginate($limit, ['*'], 'page')->withQueryString();
        $taxGroups = $taxGroupsQuery->orderBy('id', 'asc')->paginate($limit, ['*'], 'gpage')->withQueryString();

        // Phase 1.6: resolve sub-tax names ONCE for the current page's groups
        // instead of running an unscoped DbTax::whereIn() inside the Blade row
        // loop (N+1 + cross-store leak).
        $groupSubtaxIds = $taxGroups->getCollection()
            ->pluck('subtax_ids')
            ->filter()
            ->flatMap(fn($ids) => explode(',', (string) $ids))
            ->filter()
            ->unique()
            ->values();
        $subtaxNames = DbTax::whereIn('id', $groupSubtaxIds)
            ->where($storeScoped)
            ->pluck('tax_name', 'id');

        return view('module.settings.tax_list', compact('taxes', 'taxGroups', 'allTaxes', 'subtaxNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('tax_add')) {
            abort(403, 'Unauthorized access to add taxes.');
        }

        $request->validate([
            'tax_name' => 'required|string|max:255',
            'group_bit' => 'required|integer|in:0,1',
            'status' => 'required|integer|in:0,1',
            'tax' => 'nullable|numeric|min:0',
            'subtax_ids_array' => 'nullable|array',
        ]);

        $storeId = current_store_id();
        $tax_rate = $request->tax ?? 0;
        $subtax_ids = null;

        if ($request->group_bit == 1 && $request->has('subtax_ids_array')) {
            $subtax_ids = implode(',', $request->subtax_ids_array);
            // Phase 1.5: constrain the group sum to the acting store's (or
            // shared/null) sub-tax rows only — never sum another store's rates.
            $tax_rate = DbTax::whereIn('id', $request->subtax_ids_array)
                ->where('store_id', $storeId)
                ->sum('tax');
        }

        $tax = DbTax::create([
            'store_id' => $storeId,
            'tax_name' => $request->tax_name,
            'tax' => $tax_rate,
            'group_bit' => $request->group_bit,
            'subtax_ids' => $subtax_ids,
            'status' => $request->status,
        ]);

        // G5: refresh the acting store's tax dropdown immediately.
        $this->forgetTaxDropdownCache($storeId);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $tax->id,
                'name' => $tax->tax_name . ' (' . $tax->tax . '%)',
                'message' => 'Tax created successfully.'
            ]);
        }

        return redirect()->route('settings.tax')->with('success', 'Tax created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('tax_edit')) {
            abort(403, 'Unauthorized access to edit taxes.');
        }

        $request->validate([
            'tax_name' => 'required|string|max:255',
            'status' => 'required|integer|in:0,1',
            'tax' => 'nullable|numeric|min:0',
            'subtax_ids_array' => 'nullable|array',
        ]);

        $storeId = current_store_id();
        $tax = DbTax::where('store_id', $storeId)->findOrFail($id);
        $tax_rate = $request->tax ?? 0;
        $subtax_ids = null;

        if ($tax->group_bit == 1 && $request->has('subtax_ids_array')) {
            $subtax_ids = implode(',', $request->subtax_ids_array);
            $tax_rate = DbTax::whereIn('id', $request->subtax_ids_array)
                ->where('store_id', $storeId)
                ->sum('tax');
        }

        $tax->update([
            'tax_name' => $request->tax_name,
            'tax' => ($tax->group_bit == 1) ? $tax_rate : $request->tax,
            'status' => $request->status,
            'subtax_ids' => $subtax_ids,
        ]);

        // G5: refresh the acting store's tax dropdown immediately.
        $this->forgetTaxDropdownCache($storeId);

        return redirect()->route('settings.tax')->with('success', 'Tax updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage.
     *
     * Phase 2.3: in-use guard. A tax may be referenced by db_items.tax_id,
     * *_items.tax_id on sales/purchases/quotations/holds/returns, and the
     * other_charges_tax_id columns. Historical *_items.tax_amt is a snapshot
     * and is NEVER rewritten here (Protected Region) — we only block the delete.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('tax_delete')) {
            abort(403, 'Unauthorized access to delete taxes.');
        }

        $tax = DbTax::where('store_id', current_store_id())->findOrFail($id);

        $refCounts = [
            'items' => \App\Models\DbItem::where('tax_id', $tax->id)->count(),
            'sale line(s)' => \App\Models\DbSaleItem::where('tax_id', $tax->id)->count(),
            'purchase line(s)' => \App\Models\DbPurchaseItem::where('tax_id', $tax->id)->count(),
            'quotation line(s)' => \App\Models\DbQuotationItem::where('tax_id', $tax->id)->count(),
            'hold line(s)' => \App\Models\DbHoldItem::where('tax_id', $tax->id)->count(),
            'sales return line(s)' => \App\Models\DbSalesItemReturn::where('tax_id', $tax->id)->count(),
            'purchase return line(s)' => \App\Models\DbPurchaseItemReturn::where('tax_id', $tax->id)->count(),
            'purchase other-charges' => \App\Models\DbPurchase::where('other_charges_tax_id', $tax->id)->count(),
            'quotation other-charges' => \App\Models\DbQuotation::where('other_charges_tax_id', $tax->id)->count(),
            'sale other-charges' => \App\Models\DbSale::where('other_charges_tax_id', $tax->id)->count(),
            'sales return other-charges' => \App\Models\DbSalesReturn::where('other_charges_tax_id', $tax->id)->count(),
            'purchase return other-charges' => \App\Models\DbPurchaseReturn::where('other_charges_tax_id', $tax->id)->count(),
        ];

        $inUse = array_filter($refCounts, fn($c) => $c > 0);
        if (!empty($inUse)) {
            $parts = [];
            foreach ($inUse as $label => $count) {
                $parts[] = "{$count} {$label}";
            }
            return redirect()->route('settings.tax')
                ->with('error', 'This tax cannot be deleted because it is referenced by ' . implode(', ', $parts) . '. Deactivate it instead (historical tax_amt is never rewritten).');
        }

        $tax->delete();

        // G5: refresh the acting store's tax dropdown immediately.
        $this->forgetTaxDropdownCache(current_store_id());

        return redirect()->route('settings.tax')->with('success', 'Tax deleted successfully.');
    }
}
