<?php

namespace App\Http\Controllers;

use App\Models\DbSmsapi;
use App\Models\DbFivemojo;
use App\Models\DbStore;
use App\Models\SmsAutoRule;
use App\Models\DbSmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsSettingsController extends Controller
{
    public function index()
    {
        // Resolve the acting store (auth user's store_id, else default/1) rather
        // than session('store_id') ?? 1 — that session key is never set, so the
        // old code always edited store #1's config in a multi-store deployment.
        $store_id = current_store_id();
        $store = DbStore::findOrFail($store_id);

        // Load HTTP Params
        $httpParams = DbSmsapi::where('store_id', $store_id)->where('info', 'http')->get();
        
        // Load Alpha Params
        $alphaParams = DbSmsapi::where('store_id', $store_id)->where('info', 'alpha')->get();
        
        // Load Bulksmsbd Params
        $bulksmsParams = DbSmsapi::where('store_id', $store_id)->where('info', 'bulksms')->get();

        // Load SSLWireless Params
        $sslParams = DbSmsapi::where('store_id', $store_id)->where('info', 'ssl')->get();

        // Load FiveMojo
        $fivemojo = DbFivemojo::where('store_id', $store_id)->first();

        return view('module.settings.sms_api', compact('store', 'httpParams', 'alphaParams', 'bulksmsParams', 'sslParams', 'fivemojo'));
    }

    public function update(Request $request)
    {
        $store_id = current_store_id();
        
        try {
            DB::beginTransaction();

            // Update Store SMS Status
            $store = DbStore::findOrFail($store_id);
            $store->sms_status = $request->sms_status;
            $store->save();

            // Save HTTP Params
            DbSmsapi::where('store_id', $store_id)->where('info', 'http')->delete();
            if ($request->has('params')) {
                foreach ($request->params as $param) {
                    if (!empty($param['key'])) {
                        DbSmsapi::create([
                            'store_id' => $store_id,
                            'info' => 'http',
                            'key' => $param['key'],
                            'key_value' => $param['value'] ?? '',
                        ]);
                    }
                }
            }

            // Save Alpha Params
            DbSmsapi::where('store_id', $store_id)->where('info', 'alpha')->delete();
            if ($request->has('alphaParams')) {
                foreach ($request->alphaParams as $param) {
                    if (!empty($param['key'])) {
                        DbSmsapi::create([
                            'store_id' => $store_id,
                            'info' => 'alpha',
                            'key' => $param['key'],
                            'key_value' => $param['value'] ?? '',
                        ]);
                    }
                }
            }

            // Save Bulksmsbd Params
            DbSmsapi::where('store_id', $store_id)->where('info', 'bulksms')->delete();
            if ($request->has('bulksmsParams')) {
                foreach ($request->bulksmsParams as $param) {
                    if (!empty($param['key'])) {
                        DbSmsapi::create([
                            'store_id' => $store_id,
                            'info' => 'bulksms',
                            'key' => $param['key'],
                            'key_value' => $param['value'] ?? '',
                        ]);
                    }
                }
            }

            // Save SSLWireless Params
            DbSmsapi::where('store_id', $store_id)->where('info', 'ssl')->delete();
            if ($request->has('sslParams')) {
                foreach ($request->sslParams as $param) {
                    if (!empty($param['key'])) {
                        DbSmsapi::create([
                            'store_id' => $store_id,
                            'info' => 'ssl',
                            'key' => $param['key'],
                            'key_value' => $param['value'] ?? '',
                        ]);
                    }
                }
            }

            // Save FiveMojo
            $fivemojo = DbFivemojo::updateOrCreate(
                ['store_id' => $store_id],
                [
                    'instance_id' => $request->fivemojo_instance_id,
                    'token' => $request->fivemojo_token,
                    'status' => ($request->sms_status == 4) ? 1 : 0,
                ]
            );

            DB::commit();
            return back()->with('success', 'SMS Settings Updated Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function testSms(Request $request)
    {
        $request->validate([
            'mobile' => 'required',
            'message' => 'required',
        ]);

        $store_id = current_store_id();
        $store = DbStore::findOrFail($store_id);
        $sms_status = $store->sms_status;

        if ($sms_status == 0) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'SMS is disabled. Please enable it in Settings.']);
        }

        try {
            $smsService = app(\App\SMS\Services\SmsService::class);
            $response = $smsService->sendSingle($request->mobile, $request->message, [
                'store_id' => $store_id
            ]);

            if ($response->success) {
                return response()->json(['status' => 'success', 'success' => true, 'message' => 'Test request sent successfully!']);
            } else {
                return response()->json(['status' => 'error', 'success' => false, 'message' => 'Failed: ' . $response->error_message]);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function autoSettings()
    {
        $store_id = current_store_id();
        $store = DbStore::findOrFail($store_id);

        $eventTypes = [
            'InvoiceCreated' => 'Invoice Created',
            'PaymentReceived' => 'Payment Received',
            'SalesReturnConfirmation' => 'Sales Return Confirmation',
            'PurchaseCreated' => 'Purchase Created',
            'EmiDue' => 'EMI Due',
            'EmiOverdue' => 'EMI Overdue',
            'EmiPaymentConfirmation' => 'EMI Payment Confirmation',
            'EmiCompletion' => 'EMI Completion',
            'ServiceDueReminder' => 'Service Due Reminder',
            'LowStock' => 'Low Stock',
            'WarehouseLowStock' => 'Warehouse Low Stock',
            'StockAdjustmentAlert' => 'Stock Adjustment Alert',
            'CustomerBirthday' => 'Birthday',
            'FestivalCampaign' => 'Festival Campaign',
            'CouponExpiry' => 'Coupon Expiry',
            'WinbackMessage' => 'Win-back Message',
            'CustomerAdded' => 'Customer Added',
            'EodSummary' => 'EOD Summary',
            'LargeTransactionAlert' => 'Large Transaction Alert',
            'BackupCompletedAlert' => 'Backup Completed Alert',
        ];

        $categories = [
            '🔹 Core Transaction' => ['InvoiceCreated', 'PaymentReceived', 'SalesReturnConfirmation', 'PurchaseCreated'],
            '🔹 EMI Control' => ['EmiDue', 'EmiOverdue', 'EmiPaymentConfirmation', 'EmiCompletion', 'ServiceDueReminder'],
            '🔹 Inventory' => ['LowStock', 'WarehouseLowStock', 'StockAdjustmentAlert'],
            '🔹 CRM' => ['CustomerBirthday', 'FestivalCampaign', 'CouponExpiry', 'WinbackMessage', 'CustomerAdded'],
            '🔹 Admin Intelligence' => ['EodSummary', 'LargeTransactionAlert', 'BackupCompletedAlert']
        ];

        // Phase 2/4: store-scoped (SmsAutoRule now uses the StoreScoped trait, and
        // this explicit filter keeps the isolation obvious at the call site).
        $rules = SmsAutoRule::where('store_id', current_store_id())
            ->whereIn('event_type', array_keys($eventTypes))
            ->with('template')
            ->get()
            ->groupBy('event_type');

        // Prepare initial statuses for Alpine.js
        $eventStatuses = [];
        foreach ($eventTypes as $type => $label) {
            $eventStatuses[$type] = $rules->has($type) ? $rules->get($type)->contains('is_active', true) : false;
        }

        return view('module.messaging.sms_settings', compact('store', 'eventTypes', 'categories', 'rules', 'eventStatuses'));
    }

    public function updateAutoStatus(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string',
            'is_active' => 'required|boolean'
        ]);

        // Phase 4: WITHOUT the store filter this flipped the SAME event_type on
        // EVERY store (the query only filtered by event_type). Scope it to the
        // acting store; after Phase 5's per-store unique there is exactly ONE
        // matching row per store per event_type.
        $rules = SmsAutoRule::where('store_id', current_store_id())
            ->where('event_type', $request->event_type)
            ->get();

        if ($rules->isEmpty()) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'No rule found for this event. Please create one in Auto Triggers first.']);
        }

        foreach ($rules as $rule) {
            $rule->is_active = $request->is_active;
            $rule->save();
        }

        if (class_exists('\App\SMS\Services\RuleResolverService')) {
            \App\SMS\Services\RuleResolverService::clearCache($request->event_type);
        }

        return response()->json(['status' => 'success', 'success' => true, 'message' => 'Status updated successfully.']);
    }
}
