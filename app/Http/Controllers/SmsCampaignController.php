<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsCampaignController extends Controller
{
    public function index()
    {
        return view('module.sms.campaigns');
    }

    /**
     * Store-scoped campaign lookup (IDOR guard) — a cross-store campaign id 404s.
     */
    private function findActingStoreCampaign($id): \App\Models\SmsCampaign
    {
        return \App\Models\SmsCampaign::where('store_id', current_store_id())->findOrFail($id);
    }

    public function edit($id)
    {
        $campaign = $this->findActingStoreCampaign($id);
        
        if ($campaign->status === 'Completed') {
            return back()->with('error', 'Completed campaigns cannot be edited.');
        }

        return view('module.sms.edit_campaign', compact('campaign'));
    }

    public function update(Request $request, $id)
    {
        $campaign = $this->findActingStoreCampaign($id);
        
        if ($campaign->status === 'Completed') {
            return back()->with('error', 'Completed campaigns cannot be updated.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign->update([
            'name' => $request->name,
            'scheduled_at' => $request->scheduled_at,
        ]);

        // Update custom message in target_filters if not using template
        if (!$campaign->template_id) {
            $filters = $campaign->target_filters;
            $filters['custom_message'] = $request->message;
            $campaign->update(['target_filters' => $filters]);
        }

        return redirect()->route('sms.campaigns')->with('success', 'Campaign updated successfully.');
    }

    public function destroy($id)
    {
        $campaign = $this->findActingStoreCampaign($id);
        $campaign->delete();

        return back()->with('success', 'Campaign deleted successfully.');
    }

    public function cancel($id)
    {
        $campaign = $this->findActingStoreCampaign($id);
        if ($campaign->status === 'Processing' || $campaign->status === 'Scheduled') {
            $campaign->update(['status' => 'Cancelled']);
            // logic to kill/cancel jobs if needed
            return back()->with('success', 'Campaign transmission terminated.');
        }
        return back()->with('error', 'Campaign cannot be cancelled in its current state.');
    }
}
