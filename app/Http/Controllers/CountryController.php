<?php

namespace App\Http\Controllers;

use App\Models\DbCountry;
use App\Models\DbState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to view countries.');
        }

        $query = DbCountry::query();

        // Server-side search (mirrors Customers/Suppliers list pattern).
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('country', 'like', "%{$search}%");
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $countries = $query->orderBy('id', 'asc')->paginate($limit)->withQueryString();

        // Statistics are computed over the FULL data set (not the filtered page).
        $totalCountries = DbCountry::count();
        $activeCountries = DbCountry::where('status', 1)->count();
        $inactiveCountries = DbCountry::where('status', 0)->count();

        return view('module.settings.countries_list', compact('countries', 'totalCountries', 'activeCountries', 'inactiveCountries'));
    }

    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to view countries.');
        }

        return view('module.settings.add_country');
    }

    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to add countries.');
        }

        $data = $request->validate([
            'country' => 'required|string|max:255|unique:db_country,country',
            'status' => 'required|integer|in:0,1',
        ]);

        $data['added_on'] = now();

        DbCountry::create($data);

        return redirect()->route('settings.countries')->with('success', 'Country created successfully.');
    }

    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to view countries.');
        }

        $country = DbCountry::findOrFail($id);
        return view('module.settings.edit_country', compact('country'));
    }

    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to edit countries.');
        }

        $country = DbCountry::findOrFail($id);

        $data = $request->validate([
            'country' => 'required|string|max:255|unique:db_country,country,' . $id,
            'status' => 'required|integer|in:0,1',
        ]);

        $country->update($data);

        return redirect()->route('settings.countries')->with('success', 'Country updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Phase 2.1: in-use guard (Expenses/Warehouse convention). Deleting a
     * Country cascade-deletes its States (db_states.country_id ON DELETE
     * CASCADE) and nulls customers/suppliers.country_id, so block while any
     * dependent row exists and report the counts.
     */
    public function destroy($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('country_view')) {
            abort(403, 'Unauthorized access to delete countries.');
        }

        $country = DbCountry::findOrFail($id);

        $stateCount = DbState::where('country_id', $country->id)->count();
        $customerCount = \App\Models\DbCustomer::where('country_id', $country->id)->count();
        $supplierCount = \App\Models\DbSupplier::where('country_id', $country->id)->count();

        if ($stateCount > 0 || $customerCount > 0 || $supplierCount > 0) {
            $parts = [];
            if ($stateCount > 0) {
                $parts[] = "{$stateCount} state(s)";
            }
            if ($customerCount > 0) {
                $parts[] = "{$customerCount} customer(s)";
            }
            if ($supplierCount > 0) {
                $parts[] = "{$supplierCount} supplier(s)";
            }
            return redirect()->route('settings.countries')
                ->with('error', 'This country cannot be deleted because it is referenced by ' . implode(', ', $parts) . '. Reassign or remove those records first (deleting would cascade-delete its states).');
        }

        $country->delete();

        return redirect()->route('settings.countries')->with('success', 'Country deleted successfully.');
    }
}
