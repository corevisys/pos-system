<?php

namespace App\Http\Controllers;

use App\Models\DbCustomer;
use App\Models\DbEmiSale;
use App\Models\DbSupplier;
use App\Models\SmsCampaign;
use Illuminate\Http\Request;

class SmsSendController extends Controller
{
    /**
     * Permission gate — bulk/direct SMS sending (seeded `send_sms` slug),
     * mirroring the established inline hasPermission()+abort(403) convention.
     */
    private function gateSendAccess(): void
    {
        if (auth()->check() && !auth()->user()->hasPermission('send_sms')) {
            abort(403, 'Unauthorized access to send SMS.');
        }
    }

    public function index()
    {
        $this->gateSendAccess();

        return view('module.sms.send');
    }

    /**
     * Returns the count of recipients for the given targeting filters.
     * Used by the frontend to show live target count.
     */
    public function getCount(Request $request)
    {
        $this->gateSendAccess();

        $target = $request->input('target', 'all');
        $filters = $request->all();
        
        // Ensure customer_ids is an array if target is single
        if ($target === 'single' && !is_array($request->input('customer_ids'))) {
            $filters['customer_ids'] = $request->input('customer_ids') ? [$request->input('customer_ids')] : [];
        }

        $query = $this->resolveRecipients($target, $filters, current_store_id());

        return response()->json([
            'count' => $query->count(),
        ]);
    }

    /**
     * Search customers for single selection.
     */
    public function searchCustomers(Request $request)
    {
        $this->gateSendAccess();

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
        $this->gateSendAccess();

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

        $targetCount = $this->resolveRecipients($request->target, $request->all(), current_store_id())->count();
        $isScheduled = $request->boolean('is_scheduled');
        $scheduledAt = $isScheduled ? $request->input('scheduled_at') : null;

        $campaign = SmsCampaign::create([
            'store_id'         => current_store_id(),
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
     *
     * @param int|null $storeId  Acting store. Passed EXPLICITLY (never inferred
     *   from current_store_id() inside this helper) because the queued path
     *   (DispatchCampaignJob::handle) runs with NO auth context — there
     *   current_store_id() silently falls back to the default store and the
     *   StoreScoped model scopes do not apply at all (auth()->check() is false).
     *   Falls back to current_store_id() for direct web callers.
     */
    public static function resolveRecipients(string $targetType, array $filters = [], ?int $storeId = null)
    {
        $storeId = $storeId ?? current_store_id();
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
            // Phase 6: db_emi_sales has NO store_id column, so scope through the
            // parent db_sales row — the exact convention established by
            // SaleController::emiList() (SaleController.php:411-416) and the
            // ReportController EMI interest query. Without this, a Store-B blast
            // pulled every store's active-EMI customers.
            $emiCustomerIds = DbEmiSale::where('status', 'Active')
                ->whereHas('sale', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
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
