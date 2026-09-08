<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SmsAutoRule;
use App\Models\DbSmsTemplate;

class SmsAutoRuleController extends Controller
{
    public function index()
    {
        $templates = DbSmsTemplate::where('status', 1)->get();
        $rules = SmsAutoRule::with('template')->latest()->get();
        return view('module.sms.auto_rules', compact('rules', 'templates'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'rule_name' => 'required|string|max:255',
            'event_type' => 'required|string|max:255|unique:sms_auto_rules,event_type,NULL,id,deleted_at,NULL',
            'event_source' => 'required|string|max:255',
            'template_id' => 'required|exists:db_smstemplates,id',
            'trigger_time' => 'required|string|in:immediate,before_due,after_due',
            'days_offset' => 'required|integer',
            'cooldown_days' => 'required|integer|min:0'
        ]);

        $data = $request->all();
        $data['is_active'] = true;
        
        $rule = SmsAutoRule::create($data);
        
        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($request->event_type);
        }
        return back()->with('success', 'Automation protocol deployed.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'rule_name' => 'required|string|max:255',
            'event_type' => 'required|string|max:255|unique:sms_auto_rules,event_type,' . $id . ',id,deleted_at,NULL',
            'event_source' => 'required|string|max:255',
            'template_id' => 'required|exists:db_smstemplates,id',
            'trigger_time' => 'required|string|in:immediate,before_due,after_due',
            'days_offset' => 'required|integer',
            'cooldown_days' => 'required|integer|min:0'
        ]);

        $rule = SmsAutoRule::findOrFail($id);
        $oldEvent = $rule->event_type;
        
        $data = $request->except('is_active');

        $rule->update($data);

        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($oldEvent);
            if($oldEvent !== $rule->event_type) {
                \App\SMS\Services\RuleResolverService::clearCache($rule->event_type);
            }
        }
        return back()->with('success', 'Automation rule updated.');
    }

    public function toggleStatus($id)
    {
        $rule = SmsAutoRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($rule->event_type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'is_active' => $rule->is_active
        ]);
    }

    public function destroy($id)
    {
        $rule = SmsAutoRule::findOrFail($id);
        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($rule->event_type);
        }
        $rule->delete();
        return back()->with('success', 'Automation rule decommissioned.');
    }
}
