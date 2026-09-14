<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentStore;
use App\Models\DbStore;
use Illuminate\Http\Request;

/**
 * Phase 2.4 — the Owner's store selector.
 *
 * Only cross-store identities (Owner / Developer) may switch the acting store.
 * A branch admin is binary-bound to its own store and must never be able to select
 * another one, so they are rejected here (and the value would be ignored by
 * SetCurrentStore's canActAs() check anyway — defence in depth).
 *
 * The chosen store is persisted in the session under the key SetCurrentStore reads.
 */
class StoreSelectorController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user && method_exists($user, 'canViewAllStores') && $user->canViewAllStores(),
            403,
            'Only the Owner may switch stores.'
        );

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:db_store,id'],
        ]);

        $request->session()->put(SetCurrentStore::SESSION_KEY, (int) $validated['store_id']);

        return redirect()->back()->with('success', 'Acting store switched.');
    }

    public function clear(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user && method_exists($user, 'canViewAllStores') && $user->canViewAllStores(),
            403,
            'Only the Owner may switch stores.'
        );

        $request->session()->forget(SetCurrentStore::SESSION_KEY);

        return redirect()->back()->with('success', 'Reverted to your default store.');
    }
}
