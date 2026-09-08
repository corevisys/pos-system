<?php

namespace App\Http\Controllers;

use App\Models\DbCountry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    public function index()
    {
        $countries = DbCountry::latest()->get();
        $totalCountries = $countries->count();
        $activeCountries = $countries->where('status', 1)->count();
        $inactiveCountries = $countries->where('status', 0)->count();

        return view('module.settings.countries_list', compact('countries', 'totalCountries', 'activeCountries', 'inactiveCountries'));
    }

    public function create()
    {
        return view('module.settings.add_country');
    }

    public function store(Request $request)
    {
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
        $country = DbCountry::findOrFail($id);
        return view('module.settings.edit_country', compact('country'));
    }

    public function update(Request $request, $id)
    {
        $country = DbCountry::findOrFail($id);

        $data = $request->validate([
            'country' => 'required|string|max:255|unique:db_country,country,' . $id,
            'status' => 'required|integer|in:0,1',
        ]);

        $country->update($data);

        return redirect()->route('settings.countries')->with('success', 'Country updated successfully.');
    }

    public function destroy($id)
    {
        $country = DbCountry::findOrFail($id);
        $country->delete();

        return redirect()->route('settings.countries')->with('success', 'Country deleted successfully.');
    }
}
