<?php

namespace App\Http\Controllers;

use App\Models\DbStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SmtpSettingsController extends Controller
{
    public function index()
    {
        $store_id = session('store_id') ?? 1;
        $store = DbStore::findOrFail($store_id);

        return view('module.settings.smtp', compact('store'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'smtp_host' => 'required',
            'smtp_port' => 'required',
            'smtp_user' => 'required',
            'smtp_pass' => 'required',
        ]);

        $store_id = session('store_id') ?? 1;
        $store = DbStore::findOrFail($store_id);

        $store->update([
            'smtp_host' => $request->smtp_host,
            'smtp_port' => $request->smtp_port,
            'smtp_user' => $request->smtp_user,
            'smtp_pass' => $request->smtp_pass,
            'smtp_status' => $request->smtp_status ?? 0,
        ]);

        return back()->with('success', 'SMTP Settings Updated Successfully');
    }

    public function testSmtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $store_id = session('store_id') ?? 1;
        $store = DbStore::findOrFail($store_id);

        if ($store->smtp_status != 1) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'SMTP is disabled. Please enable it first.']);
        }

        try {
            // Temporarily set mail config
            Config::set('mail.mailers.smtp.host', $store->smtp_host);
            Config::set('mail.mailers.smtp.port', $store->smtp_port);
            Config::set('mail.mailers.smtp.username', $store->smtp_user);
            Config::set('mail.mailers.smtp.password', $store->smtp_pass);
            Config::set('mail.from.address', $store->smtp_user);
            Config::set('mail.from.name', $store->store_name ?? 'LaravelPOS');

            Mail::raw('This is a test email to verify SMTP configuration.', function ($message) use ($request, $store) {
                $message->to($request->email)
                        ->subject('SMTP Test Connection');
            });

            return response()->json(['status' => 'success', 'success' => true, 'message' => 'Test email sent successfully!']);
        } catch (\Exception $e) {
            Log::error("SMTP Test Failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
        }
    }
}
