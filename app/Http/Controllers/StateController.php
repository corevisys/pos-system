<?php

namespace App\Http\Controllers;

use App\Models\DbCountry;
use App\Models\DbState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StateController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to view states.');
        }

        $query = DbState::with('country');

        // Server-side search on state + country (mirrors Customers/Suppliers pattern).
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('state', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $states = $query->orderBy('id', 'asc')->paginate($limit)->withQueryString();

        // Statistics are computed over the FULL data set (not the filtered page).
        $totalStates = DbState::count();
        $activeStates = DbState::where('status', 1)->count();
        $inactiveStates = DbState::where('status', 0)->count();

        return view('module.settings.states_list', compact('states', 'totalStates', 'activeStates', 'inactiveStates'));
    }

    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to view states.');
        }

        $countries = DbCountry::where('status', 1)->orderBy('country', 'asc')->get();
        return view('module.settings.add_state', compact('countries'));
    }

    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to add states.');
        }

        $data = $request->validate([
            'state' => 'required|string|max:255',
            'country_id' => 'required|exists:db_country,id',
            'status' => 'required|integer|in:0,1',
        ]);

        $country = DbCountry::find($data['country_id']);
        $data['country'] = $country->country;
        $data['added_on'] = now();

        DbState::create($data);

        return redirect()->route('settings.states')->with('success', 'State created successfully.');
    }

    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to view states.');
        }

        $state = DbState::findOrFail($id);
        $countries = DbCountry::where('status', 1)->orderBy('country', 'asc')->get();
        return view('module.settings.edit_state', compact('state', 'countries'));
    }

    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to edit states.');
        }

        $stateRecord = DbState::findOrFail($id);

        $data = $request->validate([
            'state' => 'required|string|max:255',
            'country_id' => 'required|exists:db_country,id',
            'status' => 'required|integer|in:0,1',
        ]);

        $country = DbCountry::find($data['country_id']);
        $data['country'] = $country->country;

        $stateRecord->update($data);

        return redirect()->route('settings.states')->with('success', 'State updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Phase 2.2: in-use guard. db_customers.state_id / db_suppliers.state_id
     * reference states WITHOUT a DB foreign key, so deleting an in-use state
     * silently orphans those references. Block with counts instead.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('state_view')) {
            abort(403, 'Unauthorized access to delete states.');
        }

        $stateRecord = DbState::findOrFail($id);

        $customerCount = \App\Models\DbCustomer::where('state_id', $stateRecord->id)->count();
        $supplierCount = \App\Models\DbSupplier::where('state_id', $stateRecord->id)->count();

        if ($customerCount > 0 || $supplierCount > 0) {
            $parts = [];
            if ($customerCount > 0) {
                $parts[] = "{$customerCount} customer(s)";
            }
            if ($supplierCount > 0) {
                $parts[] = "{$supplierCount} supplier(s)";
            }
            return redirect()->route('settings.states')
                ->with('error', 'This state cannot be deleted because it is referenced by ' . implode(', ', $parts) . '. Reassign those records first.');
        }

        $stateRecord->delete();

        return redirect()->route('settings.states')->with('success', 'State deleted successfully.');
    }
}
