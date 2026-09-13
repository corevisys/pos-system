<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        // Permission gate (view) — seeded sms_template_view slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_view')) {
            abort(403, 'Unauthorized access to SMS templates.');
        }

        $templates = \App\Models\DbSmsTemplate::all();
        return view('module.sms.templates', compact('templates'));
    }

    public function create()
    {
        // Permission gate (edit) — seeded sms_template_edit slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_edit')) {
            abort(403, 'Unauthorized access to edit SMS templates.');
        }

        return view('module.sms.template_form');
    }

    public function store(Request $request)
    {
        // Permission gate (edit) — seeded sms_template_edit slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_edit')) {
            abort(403, 'Unauthorized access to edit SMS templates.');
        }

        $request->validate(['template_name' => 'required', 'content' => 'required']);
        \App\Models\DbSmsTemplate::create($request->all() + ['store_id' => current_store_id()]);
        return redirect()->route('sms.templates')->with('success', 'Template engineered successfully.');
    }

    public function edit($id)
    {
        // Permission gate (edit) — seeded sms_template_edit slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_edit')) {
            abort(403, 'Unauthorized access to edit SMS templates.');
        }

        $template = \App\Models\DbSmsTemplate::findOrFail($id);
        return view('module.sms.template_form', compact('template'));
    }

    public function update(Request $request, $id)
    {
        // Permission gate (edit) — seeded sms_template_edit slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_edit')) {
            abort(403, 'Unauthorized access to edit SMS templates.');
        }

        $request->validate(['template_name' => 'required', 'content' => 'required']);
        $template = \App\Models\DbSmsTemplate::findOrFail($id);
        $template->update($request->all());
        return redirect()->route('sms.templates')->with('success', 'Template synchronized.');
    }

    public function destroy($id)
    {
        // Permission gate (edit) — seeded sms_template_edit slug.
        if (auth()->check() && !auth()->user()->hasPermission('sms_template_edit')) {
            abort(403, 'Unauthorized access to edit SMS templates.');
        }

        \App\Models\DbSmsTemplate::findOrFail($id)->delete();
        return back()->with('success', 'Template decommissioned.');
    }
}
