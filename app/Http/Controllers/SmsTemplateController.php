<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        $templates = \App\Models\DbSmsTemplate::all();
        return view('module.sms.templates', compact('templates'));
    }

    public function create()
    {
        return view('module.sms.template_form');
    }

    public function store(Request $request)
    {
        $request->validate(['template_name' => 'required', 'content' => 'required']);
        \App\Models\DbSmsTemplate::create($request->all() + ['store_id' => current_store_id()]);
        return redirect()->route('sms.templates')->with('success', 'Template engineered successfully.');
    }

    public function edit($id)
    {
        $template = \App\Models\DbSmsTemplate::findOrFail($id);
        return view('module.sms.template_form', compact('template'));
    }

    public function update(Request $request, $id)
    {
        $request->validate(['template_name' => 'required', 'content' => 'required']);
        $template = \App\Models\DbSmsTemplate::findOrFail($id);
        $template->update($request->all());
        return redirect()->route('sms.templates')->with('success', 'Template synchronized.');
    }

    public function destroy($id)
    {
        \App\Models\DbSmsTemplate::findOrFail($id)->delete();
        return back()->with('success', 'Template decommissioned.');
    }
}
