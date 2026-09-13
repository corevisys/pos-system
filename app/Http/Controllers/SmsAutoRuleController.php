<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\SmsAutoRule;
use App\Models\DbSmsTemplate;

class SmsAutoRuleController extends Controller
{
    /**
     * Phase 3: store-scoped rule lookup (IDOR guard).
     *
     * A cross-store rule id resolves to "not found" and 404s, mirroring
     * SmsCampaignController::findActingStoreCampaign(). The StoreScoped trait on
     * SmsAutoRule ALSO applies this filter as a global scope; the explicit
     * where() keeps the protection obvious at the call site (and independent of
     * whether the global scope is ever bypassed), matching the pattern used by
     * VariantController / CategoryController / BrandController.
     */
    private function findActingStoreRule($id): SmsAutoRule
    {
        return SmsAutoRule::where('store_id', current_store_id())->findOrFail($id);
    }

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
            // Phase 5: unique PER STORE — mirrors the category/brand/variant/
            // warehouse convention. Previously globally unique, so Store B could
            // not create a rule for an event_type Store A already used. This
            // matches the plain (store_id, event_type) composite unique added by
            // migration 2026_09_13_000001 — deliberately NOT excluding
            // soft-deleted rows, so validation and the DB constraint agree (a
            // re-create collides with a clean 422 instead of an uncaught 500).
            'event_type' => [
                'required', 'string', 'max:255',
                Rule::unique('sms_auto_rules', 'event_type')
                    ->where('store_id', current_store_id()),
            ],
            'event_source' => 'required|string|max:255',
            'template_id' => 'required|exists:db_smstemplates,id',
            'trigger_time' => 'required|string|in:immediate,before_due,after_due',
            'days_offset' => 'required|integer',
            'cooldown_days' => 'required|integer|min:0'
        ]);

        $data = $request->all();
        $data['is_active'] = true;

        // Phase 1: sms_auto_rules.store_id is NOT NULL (migration
        // 2026_09_12_000004) and is never submitted by the form. Set it
        // explicitly from the acting store instead of relying on
        // mass-assignment from request input, which produced a
        // NOT-NULL constraint violation on every create.
        $data['store_id'] = current_store_id();

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
            // Phase 5: per-store unique; ignore this row on update. Same plain
            // semantics as the composite DB unique (see store() above).
            'event_type' => [
                'required', 'string', 'max:255',
                Rule::unique('sms_auto_rules', 'event_type')
                    ->where('store_id', current_store_id())
                    ->ignore($id),
            ],
            'event_source' => 'required|string|max:255',
            'template_id' => 'required|exists:db_smstemplates,id',
            'trigger_time' => 'required|string|in:immediate,before_due,after_due',
            'days_offset' => 'required|integer',
            'cooldown_days' => 'required|integer|min:0'
        ]);

        // Phase 3: store-scoped lookup (404 on cross-store).
        $rule = $this->findActingStoreRule($id);
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
        // Phase 3: store-scoped lookup (404 on cross-store).
        $rule = $this->findActingStoreRule($id);
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
        // Phase 3: store-scoped lookup (404 on cross-store).
        $rule = $this->findActingStoreRule($id);
        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($rule->event_type);
        }
        $rule->delete();
        return back()->with('success', 'Automation rule decommissioned.');
    }
}
