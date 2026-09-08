<?php

namespace App\Http\Controllers;

use App\Models\DbCustomer;
use App\Models\DbEmiSale;
use App\Models\DbSupplier;
use App\Models\SmsCampaign;
use Illuminate\Http\Request;

class SmsSendController extends Controller
{
    public function index()
    {
        return view('module.sms.send');
    }

    /**
     * Returns the count of recipients for the given targeting filters.
     * Used by the frontend to show live target count.
     */
    public function getCount(Request $request)
    {
        $target = $request->input('target', 'all');
        $filters = $request->all();
        
        // Ensure customer_ids is an array if target is single
        if ($target === 'single' && !is_array($request->input('customer_ids'))) {
            $filters['customer_ids'] = $request->input('customer_ids') ? [$request->input('customer_ids')] : [];
        }

        $query = $this->resolveRecipients($target, $filters);

        return response()->json([
            'count' => $query->count(),
        ]);
    }

    /**
     * Search customers for single selection.
     */
    public function searchCustomers(Request $request)
    {
        $q = $request->get('q');
        $customers = DbCustomer::where('status', 1)
            ->where('delete_bit', 0)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->where(function($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                      ->orWhere('mobile', 'like', "%{$q}%")
                      ->orWhere('city', 'like', "%{$q}%")
                      ->orWhere('customer_code', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'customer_name', 'mobile', 'customer_code']);

        return response()->json($customers);
    }

    /**
     * Process the broadcast: create a campaign and dispatch the queue job.
     */
    public function process(Request $request)
    {
        $request->validate([
            'message'      => 'required|string',
            'target'       => 'required|string',
            'is_scheduled' => 'nullable|boolean',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        // Build filters JSON for the job to use later
        $filters = [
            'min_due'  => $request->input('min_due'),
            'area'     => $request->input('area'),
            'customer_ids' => $request->input('customer_ids') ? (is_array($request->input('customer_ids')) ? $request->input('customer_ids') : [$request->input('customer_ids')]) : [],
        ];

        $targetCount = $this->resolveRecipients($request->target, $request->all())->count();
        $isScheduled = $request->boolean('is_scheduled');
        $scheduledAt = $isScheduled ? $request->input('scheduled_at') : null;

        $campaign = SmsCampaign::create([
            'name'             => ($isScheduled ? 'Scheduled' : 'Broadcast') . ' – ' . now()->format('d M Y, h:i A'),
            'target_type'      => $request->target,
            'target_filters'   => $filters,
            'template_id'      => $request->template_id ?: null,
            'status'           => 'Scheduled',
            'total_recipients' => $targetCount,
            'scheduled_at'     => $scheduledAt,
            'estimated_cost'   => 0,
            'created_by'       => auth()->id(),
        ]);

        // If no template, store the custom message in filters
        if (!$request->template_id && $request->message) {
            $campaign->update([
                'target_filters' => array_merge($filters, ['custom_message' => $request->message]),
            ]);
        }

        // Dispatch the 2-Tier Queue job immediately if not scheduled for later
        if (!$isScheduled) {
            \App\Jobs\DispatchCampaignJob::dispatch($campaign->id)->onQueue('sms');
        }

        $message = $isScheduled ? 'Campaign scheduled successfully!' : 'Campaign dispatched successfully!';

        return response()->json([
            'status'        => 'success',
            'success'       => true,
            'message'       => $message,
            'campaign_link' => route('sms.campaigns'),
            'campaign_id'   => $campaign->id,
        ]);
    }

    /**
     * Shared helper: build a query for recipients based on target_type & filters.
     * Returns a Builder that can be counted or iterated.
     */
    public static function resolveRecipients(string $targetType, array $filters = [])
    {
        $minDue = $filters['min_due'] ?? null;
        $area   = $filters['area'] ?? null;
        $customerIds = $filters['customer_ids'] ?? [];

        if ($targetType === 'suppliers') {
            return DbSupplier::query()
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->when($area, fn($q) => $q->where('city', 'like', "%{$area}%"));
        }

        if ($targetType === 'custom' && count($customerIds) > 0) {
            return DbCustomer::query()
                ->whereIn('id', $customerIds)
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '');
        }

        if ($targetType === 'single' && count($customerIds) > 0) {
            return DbCustomer::query()
                ->where('id', $customerIds[0])
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '');
        }

        // Customer-based targets
        $query = DbCustomer::query()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '');

        if ($targetType === 'due') {
            $query->where('sales_due', '>', $minDue ?: 0);
        }

        if ($targetType === 'emi') {
            $emiCustomerIds = DbEmiSale::where('status', 'Active')
                ->pluck('customer_id')
                ->unique();
            $query->whereIn('id', $emiCustomerIds);
        }

        if ($area) {
            $query->where(function ($q) use ($area) {
                $q->where('city', 'like', "%{$area}%")
                  ->orWhere('address', 'like', "%{$area}%");
            });
        }

        return $query;
    }
}
