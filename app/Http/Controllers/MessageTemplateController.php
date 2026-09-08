<?php

namespace App\Http\Controllers;

use App\Models\DbSmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = DbSmsTemplate::where('undelete_bit', 0)->get();
        return view('module.messaging.messaging_templates', compact('templates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('module.messaging.add_template');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'template_name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        try {
            DbSmsTemplate::create([
                'template_name' => $request->template_name,
                'content' => $request->content,
                'status' => $request->status ?? 1,
                'store_id' => session('store_id') ?? 1, // Fallback to 1 if not set
            ]);

            return redirect()->route('messaging.templates')->with('success', 'Template Created Successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error while creating template: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $template = DbSmsTemplate::findOrFail($id);
        return view('module.messaging.edit_template', compact('template'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'template_name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        try {
            $template = DbSmsTemplate::findOrFail($id);
            $template->update([
                'template_name' => $request->template_name,
                'content' => $request->content,
                'status' => $request->status ?? $template->status,
            ]);

            return redirect()->route('messaging.templates')->with('success', 'Template Updated Successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Error while updating template: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $template = DbSmsTemplate::findOrFail($id);
            $template->update(['undelete_bit' => 1]); // Soft delete pattern in this app

            return response()->json(['status' => 'success', 'message' => 'Template Deleted Successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
