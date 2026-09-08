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
    public function index()
    {
        $languages = DbLanguage::orderBy('status', 'desc')->orderBy('language', 'asc')->get();
        $activeLanguage = $languages->firstWhere('status', 1);
        return view('module.settings.languages.index', compact('languages', 'activeLanguage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('module.settings.languages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
        $language = DbLanguage::findOrFail($id);
        return view('module.settings.languages.edit', compact('language'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
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
