<?php

namespace App\Http\Controllers;

use App\Models\SmsBlacklist;
use Illuminate\Http\Request;

/**
 * Minimal per-store SMS blacklist CRUD (list / add / delete).
 *
 * Deliberately mirrors the PaymentType/Tax simplicity the rest of this module
 * family uses: one paginated list with a server-side search and a small inline
 * add form, no edit screen (a blacklist entry has no meaningful mutable fields
 * beyond its reason — delete + re-add is the honest operation).
 *
 * Every query is scoped to the acting store: a number opted out by store 1 must
 * not be manageable (or even visible) from store 2.
 */
class SmsBlacklistController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sms_blacklist_view')) {
            abort(403, 'Unauthorized access to SMS blacklist.');
        }

        $storeId = current_store_id();

        $query = SmsBlacklist::where('store_id', $storeId);

        if ($request->filled('search')) {
            $query->where('phone', 'like', '%' . $request->input('search') . '%');
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $blacklists = $query->orderBy('id', 'desc')->paginate($limit)->withQueryString();

        return view('module.sms.blacklist', compact('blacklists'));
    }

    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sms_blacklist_add')) {
            abort(403, 'Unauthorized access to add to the SMS blacklist.');
        }

        $storeId = current_store_id();

        $request->validate([
            'phone' => [
                'required', 'string', 'max:255',
                // Per-store uniqueness — the DB has a (store_id, phone) composite
                // unique; this surfaces a friendly error before the constraint fires.
                \Illuminate\Validation\Rule::unique('sms_blacklists', 'phone')->where('store_id', $storeId),
            ],
            'reason' => 'nullable|string|max:255',
        ], [
            'phone.unique' => 'This number is already blacklisted for your store.',
        ]);

        SmsBlacklist::create([
            'store_id' => $storeId,
            'phone' => trim($request->phone),
            'reason' => $request->reason,
            'added_by' => auth()->id(),
        ]);

        return redirect()->route('sms.blacklist')->with('success', 'Number added to the blacklist.');
    }

    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('sms_blacklist_delete')) {
            abort(403, 'Unauthorized access to remove from the SMS blacklist.');
        }

        // Store-scoped lookup (IDOR protection) — another store's entry 404s.
        $entry = SmsBlacklist::where('store_id', current_store_id())->findOrFail($id);
        $entry->delete();

        return redirect()->route('sms.blacklist')->with('success', 'Number removed from the blacklist.');
    }
}
