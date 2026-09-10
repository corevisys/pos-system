<?php

namespace App\Http\Controllers;

use App\Models\DbLanguage;
use App\Models\DbStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to view languages.');
        }

        $query = DbLanguage::orderBy('status', 'desc')->orderBy('language', 'asc');

        // Server-side search (mirrors Customers/Suppliers list pattern).
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('language', 'like', "%{$search}%");
        }

        $limit = in_array((int) $request->input('limit', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('limit', 10)
            : 10;

        $languages = $query->paginate($limit)->withQueryString();

        // activeLanguage is derived from the FULL result set, not the current page,
        // so the banner stays correct even when the active row is on another page.
        $activeLanguage = DbLanguage::where('status', 1)->first();

        return view('module.settings.languages.index', compact('languages', 'activeLanguage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to view languages.');
        }

        return view('module.settings.languages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to add languages.');
        }

        $request->validate([
            'language' => 'required|string|unique:db_languages,language|max:255',
            'status' => 'required|integer|in:0,1',
        ]);

        $shouldActivate = ((int) $request->status === 1) || (DbLanguage::count() === 0);

        DB::transaction(function () use ($request, $shouldActivate, &$language) {
            $language = DbLanguage::create([
                'language' => $request->language,
                'status' => 0,
            ]);

            if ($shouldActivate) {
                DbLanguage::activateLanguage($language->id);
                $language->refresh();
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Language added successfully.',
                'language' => $language,
            ]);
        }

        return redirect()->route('settings.languages.index')->with('success', 'Language added successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to view languages.');
        }

        $language = DbLanguage::findOrFail($id);
        return view('module.settings.languages.edit', compact('language'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to edit languages.');
        }

        $language = DbLanguage::findOrFail($id);
        $newStatus = (int) $request->status;

        $request->validate([
            'language' => 'required|string|max:255|unique:db_languages,language,' . $id,
            'status' => 'required|integer|in:0,1',
        ]);

        // Disallow direct deactivation of the currently active language
        if ($language->status == 1 && $newStatus === 0) {
            $errorMessage = 'Cannot deactivate the active language. Activate a different language instead, which will automatically deactivate this one.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }
            return redirect()->route('settings.languages.index')->with('error', $errorMessage);
        }

        DB::transaction(function () use ($language, $request, $newStatus) {
            $language->update([
                'language' => $request->language,
                'status' => $newStatus,
            ]);

            if ($newStatus === 1) {
                DbLanguage::activateLanguage($language->id);
                $language->refresh();
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Language updated successfully.',
                'language' => $language,
            ]);
        }

        return redirect()->route('settings.languages.index')->with('success', 'Language updated successfully.');
    }

    /**
     * Atomically activate a language and deactivate all others.
     */
    public function activate(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to activate languages.');
        }

        $language = DbLanguage::activateLanguage((int) $id);

        $message = "Language '{$language->language}' activated successfully as the system language.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => $message,
                'language' => $language,
            ]);
        }

        return redirect()->route('settings.languages.index')->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        if (auth()->check() && !auth()->user()->hasPermission('language_view')) {
            abort(403, 'Unauthorized access to delete languages.');
        }

        $language = DbLanguage::findOrFail($id);

        if ($language->status == 1) {
            $errorMessage = 'Cannot delete the active language. Activate a different language first.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }
            return redirect()->route('settings.languages.index')->with('error', $errorMessage);
        }

        $language->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Language deleted successfully.',
            ]);
        }

        return redirect()->route('settings.languages.index')->with('success', 'Language deleted successfully.');
    }
}
