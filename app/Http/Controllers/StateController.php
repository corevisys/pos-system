<?php

namespace App\Http\Controllers;

use App\Models\DbCountry;
use App\Models\DbState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StateController extends Controller
{
    public function index()
    {
        $states = DbState::with('country')->latest()->get();
        $totalStates = $states->count();
        $activeStates = $states->where('status', 1)->count();
        $inactiveStates = $states->where('status', 0)->count();

        return view('module.settings.states_list', compact('states', 'totalStates', 'activeStates', 'inactiveStates'));
    }

    public function create()
    {
        $countries = DbCountry::where('status', 1)->orderBy('country', 'asc')->get();
        return view('module.settings.add_state', compact('countries'));
    }

    public function store(Request $request)
    {
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
        $state = DbState::findOrFail($id);
        $countries = DbCountry::where('status', 1)->orderBy('country', 'asc')->get();
        return view('module.settings.edit_state', compact('state', 'countries'));
    }

    public function update(Request $request, $id)
    {
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

    public function destroy($id)
    {
        $stateRecord = DbState::findOrFail($id);
        $stateRecord->delete();

        return redirect()->route('settings.states')->with('success', 'State deleted successfully.');
    }
}
