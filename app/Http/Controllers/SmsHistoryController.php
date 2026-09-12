<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsHistoryController extends Controller
{
    public function index()
    {
        $storeId = current_store_id();

        // Every figure below is scoped to the acting store: campaigns and logs now
        // carry store_id, so a store's SMS page no longer shows network-wide totals.
        $stats = [
            'total_sent' => \App\Models\SmsLog::where('store_id', $storeId)->where('status', 'Sent')->count(),
            'total_failed' => \App\Models\SmsLog::where('store_id', $storeId)->where('status', 'Failed')->count(),
            'scheduled' => \App\Models\SmsCampaign::where('store_id', $storeId)->where('status', 'Scheduled')->count(),
            'sent_today' => \App\Models\SmsLog::where('store_id', $storeId)->whereDate('created_at', now()->today())->count(),
        ];

        $recentActivity = \App\Models\SmsLog::with('customer')
            ->where('store_id', $storeId)
            ->latest()
            ->limit(5)
            ->get();
        $healthStatus = \Illuminate\Support\Facades\Cache::get('sms_health_status', 'Operational');

        // Fetch Balance
        $smsService = app(\App\SMS\Services\SmsService::class);
        // Resolve the acting store from the authenticated user rather than a
        // session key (which is never set), so a multi-store deployment never
        // shows store #1's provider balance.
        $provider = $smsService->getProvider(current_store_id());
        $balance = $provider ? $provider->checkBalance() : 0.0;

        return view('module.sms.sms_history', compact('stats', 'recentActivity', 'healthStatus', 'balance'));
    }
}
