<?php

namespace App\Http\Controllers;

use App\Models\DbStore;
use Illuminate\Http\Request;

class MultiStoreDashboardController extends Controller
{
    /**
     * Super-Admin-only multi-store overview dashboard.
     *
     * Access is gated by the `multi_store_dashboard_view` permission, which is
     * seeded only for Super Admin (role_id = 1). Regular store users never see
     * this page; a non-super-admin who somehow reaches this route gets 403.
     *
     * The page aggregates stats for ALL stores in one view by reusing the
     * DashboardController::computeStoreStats(int $storeId) helper introduced
     * during Phase 2B. That helper explicitly bypasses the per-request global
     * scope via withoutGlobalScope('store_id') + where('store_id', $storeId),
     * so each store's numbers are always perfectly isolated.
     */
    public function index(Request $request)
    {
        // Permission gate — must have the dedicated slug
        if (!auth()->user()->hasPermission('multi_store_dashboard_view')) {
            abort(403, 'You do not have permission to view the Multi-Store Dashboard.');
        }

        // Load all active stores ordered by id
        $stores = DbStore::orderBy('id')->get();

        // Compute stats for every store — reuse Phase-2B helper
        $storeStats = $stores->map(function (DbStore $store) {
            return [
                'store'  => $store,
                'stats'  => DashboardController::computeStoreStats($store->id),
            ];
        });

        // Network-wide aggregates (sum across all stores)
        $networkTodaySales     = $storeStats->sum(fn($s) => $s['stats']['stats']['today_sales'] ?? 0);
        $networkTodayOrders    = $storeStats->sum(fn($s) => $s['stats']['stats']['today_orders'] ?? 0);
        $networkMonthSales     = $storeStats->sum(fn($s) => $s['stats']['stats']['this_month_sales'] ?? 0);
        $networkMonthPurchases = $storeStats->sum(fn($s) => $s['stats']['stats']['this_month_purchases'] ?? 0);
        $networkOutstandingDue = $storeStats->sum(fn($s) => $s['stats']['stats']['total_outstanding_due'] ?? 0);

        $networkStats = [
            'today_sales'          => $networkTodaySales,
            'today_orders'         => $networkTodayOrders,
            'this_month_sales'     => $networkMonthSales,
            'this_month_purchases' => $networkMonthPurchases,
            'total_outstanding_due'=> $networkOutstandingDue,
            'store_count'          => $stores->count(),
        ];

        return view('multi-store-dashboard', compact('storeStats', 'networkStats'));
    }
}
