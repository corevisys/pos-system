<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsHistoryController extends Controller
{
    public function index()
    {
        $stats = [
            'total_sent' => \App\Models\SmsLog::where('status', 'Sent')->count(),
            'total_failed' => \App\Models\SmsLog::where('status', 'Failed')->count(),
            'scheduled' => \App\Models\SmsCampaign::where('status', 'Scheduled')->count(),
            'sent_today' => \App\Models\SmsLog::whereDate('created_at', now()->today())->count(),
        ];

        $recentActivity = \App\Models\SmsLog::with('customer')->latest()->limit(5)->get();
        $healthStatus = \Illuminate\Support\Facades\Cache::get('sms_health_status', 'Operational');

        // Fetch Balance
        $smsService = app(\App\SMS\Services\SmsService::class);
        $provider = $smsService->getProvider(session('store_id') ?? 1);
        $balance = $provider ? $provider->checkBalance() : 0.0;

        return view('module.sms.sms_history', compact('stats', 'recentActivity', 'healthStatus', 'balance'));
    }
}
