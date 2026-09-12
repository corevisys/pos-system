<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SmsLogController extends Controller
{
    public function index(Request $request)
    {
        // Store-scoped: SMS logs are now attributable to a store, so one store's
        // audit page must never list another store's messages.
        $query = \App\Models\SmsLog::with('customer')
            ->where('store_id', current_store_id())
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', "%{$request->phone}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('export') && $request->export === 'csv') {
            $logs = $query->get();
            $filename = "audit_logs_" . now()->format('Y_m_d_H_i_s') . ".csv";

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            return response()->stream(function () use ($logs) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Trace ID', 'Date Time', 'Recipient Name', 'Recipient Phone', 'Message Segment', 'Provider', 'Provider Message ID', 'Cost', 'Parts', 'Status']);

                foreach ($logs as $log) {
                    $customerName = $log->customer ? $log->customer->customer_name : 'Unknown';
                    fputcsv($file, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $customerName,
                        $log->phone,
                        $log->message,
                        $log->provider,
                        $log->provider_message_id,
                        $log->cost,
                        $log->sms_parts,
                        $log->status
                    ]);
                }
                fclose($file);
            }, 200, $headers);
        }

        $logs = $query->paginate(25)->withQueryString();
        return view('module.sms.logs', compact('logs'));
    }
}
