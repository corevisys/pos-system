<?php

namespace App\Http\Controllers;

use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbCustomer;
use App\Models\DbItem;
use App\Models\DbExpense;
use App\Models\DbPurchase;
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public static function clearDashboardCache(?int $storeId = null): void
    {
        $storeId = $storeId ?? current_store_id();
        Cache::forget('dashboard_outstanding_due_s' . $storeId);
        Cache::forget('dashboard_month_sale_ids_s' . $storeId);
        Cache::forget('dashboard_customers_due_s' . $storeId);
        Cache::forget('dashboard_month_purchases_s' . $storeId);
        Cache::forget('dashboard_chart_last7_s' . $storeId);
        Cache::forget('dashboard_chart_last30_s' . $storeId);
        Cache::forget('dashboard_chart_weekly_s' . $storeId);
        Cache::forget('dashboard_chart_monthly_s' . $storeId);
    }

    public function index()
    {
        $storeId = current_store_id();
        $data = self::computeStoreStats($storeId);

        return view('dashboard', $data);
    }

    public static function computeStoreStats(int $storeId): array
    {
        $today         = Carbon::today()->format('Y-m-d');
        $startOfMonth  = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endOfMonth    = Carbon::now()->endOfMonth()->format('Y-m-d');
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');

        // -----------------------------------------------------------------------
        // 1. TODAY's KPIs
        // -----------------------------------------------------------------------
        $todaySales = DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->whereDate('sales_date', $today)
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('sales_status', 'Final')->orWhereNull('sales_status');
            })
            ->get(['id', 'tot_discount_to_all_amt', 'coupon_amt', 'grand_total']);

        $todaySalesIds = $todaySales->pluck('id');

        $todayRevenue  = 0;
        $todayCogs     = 0;
        $todayTax      = 0;
        $todayInterest = 0;

        if ($todaySalesIds->isNotEmpty()) {
            $salesData = DB::table('db_salesitems')
                ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                ->whereIn('sales_id', $todaySalesIds)
                ->select(
                    DB::raw('COALESCE(SUM(db_salesitems.total_cost), 0) as revenue'),
                    DB::raw('COALESCE(SUM(db_salesitems.sales_qty * COALESCE(NULLIF(db_salesitems.purchase_price, 0), db_items.purchase_price, 0)), 0) as cogs'),
                    DB::raw('COALESCE(SUM(db_salesitems.tax_amt), 0) as tax')
                )->first();

            $todayRevenue  = (float) ($salesData->revenue ?? 0);
            $todayCogs     = (float) ($salesData->cogs    ?? 0);
            $todayTax      = (float) ($salesData->tax     ?? 0);
            $todayInterest = (float) DB::table('db_emi_sales')->whereIn('sale_id', $todaySalesIds)->sum('processing_fee');

            // Deduct invoice-level discounts and coupon amounts
            $todayInvoiceDiscounts = (float) $todaySales->sum(function ($s) {
                return (float) ($s->tot_discount_to_all_amt ?? 0) + (float) ($s->coupon_amt ?? 0);
            });
            $todayRevenue = max(0, $todayRevenue - $todayInvoiceDiscounts);
        }

        // Exclude soft-deleted (delete_bit=1) expenses so deleted rows are not re-counted.
        $todayExpenses = (float) DbExpense::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->where('delete_bit', 0)->whereDate('expense_date', $today)->sum('expense_amt');

        // Sales returns today
        $todayReturnData = DB::table('db_salesitemsreturn')
            ->join('db_items', 'db_salesitemsreturn.item_id', '=', 'db_items.id')
            ->join('db_salesreturn', 'db_salesitemsreturn.return_id', '=', 'db_salesreturn.id')
            ->where('db_salesreturn.store_id', $storeId)
            ->whereDate('db_salesreturn.return_date', $today)
            ->select(
                DB::raw('COALESCE(SUM(db_salesitemsreturn.total_cost), 0) as revenue'),
                DB::raw('COALESCE(SUM(db_salesitemsreturn.return_qty * COALESCE(NULLIF(db_salesitemsreturn.purchase_price, 0), db_items.purchase_price, 0)), 0) as cogs'),
                DB::raw('COALESCE(SUM(db_salesitemsreturn.tax_amt), 0) as tax')
            )->first();

        $todayReturnRevenue = (float) ($todayReturnData->revenue ?? 0);
        $todayReturnCogs    = (float) ($todayReturnData->cogs    ?? 0);
        $todayReturnTax     = (float) ($todayReturnData->tax     ?? 0);

        $grossProfit = ($todayRevenue - $todayReturnRevenue) - ($todayCogs - $todayReturnCogs);
        $todayNetProfit = $grossProfit - $todayExpenses + $todayInterest - ($todayTax - $todayReturnTax);

        $todaySalesTotal  = (float) $todaySales->sum('grand_total');
        $todayOrdersCount = (int)   $todaySales->count();

        // -----------------------------------------------------------------------
        // Shared Returns Subquery for Net Balance Due Calculations
        // Net Due = (grand_total - returns.grand_total) - (paid_amount - returns.paid_amount)
        // -----------------------------------------------------------------------
        $returnsSubquery = DB::table('db_salesreturn')
            ->where('store_id', $storeId)
            ->select(
                'sales_id',
                DB::raw('COALESCE(SUM(grand_total), 0) as total_return'),
                DB::raw('COALESCE(SUM(paid_amount), 0) as total_refunded')
            )
            ->groupBy('sales_id');

        $rawDueExpr = '(db_sales.grand_total - COALESCE(ret.total_return, 0)) - (db_sales.paid_amount - COALESCE(ret.total_refunded, 0))';

        // -----------------------------------------------------------------------
        // 2. TOTAL OUTSTANDING DUE (store-wide, all time)
        // -----------------------------------------------------------------------
        $totalOutstandingDue = Cache::remember('dashboard_outstanding_due_s' . $storeId, 300, function () use ($returnsSubquery, $rawDueExpr, $storeId) {
            $result = DB::table('db_sales')
                ->leftJoinSub($returnsSubquery, 'ret', function ($join) {
                    $join->on('db_sales.id', '=', 'ret.sales_id');
                })
                ->where('db_sales.store_id', $storeId)
                ->where('db_sales.status', 1)
                ->where(function ($q) {
                    $q->where('db_sales.sales_status', 'Final')->orWhereNull('db_sales.sales_status');
                })
                ->selectRaw("SUM(CASE WHEN {$rawDueExpr} > 0.0001 THEN {$rawDueExpr} ELSE 0 END) as total_due")
                ->value('total_due');

            return (float) ($result ?? 0);
        });

        // -----------------------------------------------------------------------
        // 3. THIS MONTH'S SALES + % change vs last month
        // -----------------------------------------------------------------------
        $thisMonthSales = (float) DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->whereBetween('sales_date', [$startOfMonth, $endOfMonth])
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('sales_status', 'Final')->orWhereNull('sales_status');
            })
            ->sum('grand_total');

        $lastMonthSales = (float) DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->whereBetween('sales_date', [$startOfLastMonth, $endOfLastMonth])
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('sales_status', 'Final')->orWhereNull('sales_status');
            })
            ->sum('grand_total');

        $monthChangePercent = null;
        if ($lastMonthSales > 0) {
            $monthChangePercent = round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1);
        }

        // -----------------------------------------------------------------------
        // 4. SALES TREND CHART — last 7 days (default on page load)
        // -----------------------------------------------------------------------
        $sevenDaysAgo = Carbon::today()->subDays(6)->format('Y-m-d');
        $salesGrouped7 = DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->where('sales_date', '>=', $sevenDaysAgo)
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('sales_status', 'Final')->orWhereNull('sales_status');
            })
            ->groupBy('sales_date')
            ->select('sales_date', DB::raw('SUM(grand_total) as total'))
            ->pluck('total', 'sales_date');

        $trendLabels = [];
        $trendValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $trendLabels[] = $date->format('D, M d');
            $trendValues[] = (float) ($salesGrouped7[$dateKey] ?? 0);
        }

        // -----------------------------------------------------------------------
        // 5. LOW STOCK ALERT — items where stock <= alert_qty (alert_qty exists on db_items)
        // -----------------------------------------------------------------------
        $lowStockItems = DbItem::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->whereRaw('stock <= alert_qty')
            ->where('status', 1)
            ->where('service_bit', '!=', 1)
            ->select('id', 'item_name', 'sku', 'item_code', 'stock', 'alert_qty')
            ->orderBy('stock', 'asc')
            ->limit(8)
            ->get();

        // -----------------------------------------------------------------------
        // 6. TOP 5 SELLING PRODUCTS — this month by qty
        // -----------------------------------------------------------------------
        $monthSalesIds = Cache::remember('dashboard_month_sale_ids_s' . $storeId, 300, function () use ($storeId, $startOfMonth, $endOfMonth) {
            return DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
                ->whereBetween('sales_date', [$startOfMonth, $endOfMonth])
                ->where(function ($q) {
                    $q->where('sales_status', 'Final')->orWhereNull('sales_status');
                })
                ->where('status', 1)
                ->pluck('id');
        });

        $topProducts = [];
        if ($monthSalesIds->isNotEmpty()) {
            $topProducts = DB::table('db_salesitems')
                ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                ->whereIn('db_salesitems.sales_id', $monthSalesIds)
                ->select(
                    'db_items.id as item_id',
                    'db_items.item_name',
                    'db_items.item_code',
                    DB::raw('SUM(db_salesitems.sales_qty) as total_qty'),
                    DB::raw('SUM(db_salesitems.total_cost) as total_revenue')
                )
                ->groupBy('db_items.id', 'db_items.item_name', 'db_items.item_code')
                ->orderByDesc('total_qty')
                ->limit(5)
                ->get();
        }

        // -----------------------------------------------------------------------
        // 7. RECENT TRANSACTIONS — last 8 sales
        // -----------------------------------------------------------------------
        $recentTransactions = DbSale::withoutGlobalScope('store_id')->where('store_id', $storeId)
            ->with('customer')
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('sales_status', 'Final')->orWhereNull('sales_status');
            })
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        // -----------------------------------------------------------------------
        // 8. PAYMENT METHOD BREAKDOWN — this month (reuses getSalesSummaryData pattern)
        // -----------------------------------------------------------------------
        $paymentMethods = [];
        if ($monthSalesIds->isNotEmpty()) {
            $paymentMethods = DB::table('db_salespayments')
                ->whereIn('sales_id', $monthSalesIds)
                ->select(
                    'payment_type',
                    DB::raw('SUM(payment) as total_paid'),
                    DB::raw('COUNT(*) as txn_count')
                )
                ->groupBy('payment_type')
                ->get()
                ->map(function ($pm) {
                    return [
                        'method' => $pm->payment_type ?: 'Cash',
                        'amount' => (float) $pm->total_paid,
                        'count'  => (int)   $pm->txn_count,
                    ];
                })
                ->toArray();
        }

        // -----------------------------------------------------------------------
        // 9. CUSTOMERS WITH OUTSTANDING DUES — top 5 (accounting for returns)
        // -----------------------------------------------------------------------
        $customersWithDue = Cache::remember('dashboard_customers_due_s' . $storeId, 300, function () use ($returnsSubquery, $rawDueExpr, $storeId) {
            return DB::table('db_sales')
                ->leftJoin('db_customers', 'db_sales.customer_id', '=', 'db_customers.id')
                ->leftJoinSub($returnsSubquery, 'ret', function ($join) {
                    $join->on('db_sales.id', '=', 'ret.sales_id');
                })
                ->where('db_sales.store_id', $storeId)
                ->where('db_sales.status', 1)
                ->where(function ($q) {
                    $q->where('db_sales.sales_status', 'Final')->orWhereNull('db_sales.sales_status');
                })
                ->select(
                    'db_sales.customer_id',
                    DB::raw("COALESCE(db_customers.customer_name, 'Walk-in Customer') as customer_name"),
                    DB::raw("COALESCE(db_customers.mobile, 'N/A') as mobile"),
                    DB::raw("COUNT(CASE WHEN {$rawDueExpr} > 0.0001 THEN db_sales.id ELSE NULL END) as orders_count"),
                    DB::raw("SUM(CASE WHEN {$rawDueExpr} > 0.0001 THEN {$rawDueExpr} ELSE 0 END) as total_due")
                )
                ->groupBy('db_sales.customer_id', 'db_customers.customer_name', 'db_customers.mobile')
                ->havingRaw("SUM(CASE WHEN {$rawDueExpr} > 0.0001 THEN {$rawDueExpr} ELSE 0 END) > 0.0001")
                ->orderByDesc('total_due')
                ->limit(5)
                ->get();
        });

        // -----------------------------------------------------------------------
        // 10. PURCHASES VS SALES — this month
        // -----------------------------------------------------------------------
        $thisMonthPurchases = Cache::remember('dashboard_month_purchases_s' . $storeId, 300, function () use ($storeId, $startOfMonth, $endOfMonth) {
            return (float) DbPurchase::withoutGlobalScope('store_id')->where('store_id', $storeId)
                ->whereBetween('purchase_date', [$startOfMonth, $endOfMonth])->sum('grand_total');
        });

        // -----------------------------------------------------------------------
        // BUILD STATS ARRAY
        // -----------------------------------------------------------------------
        $stats = [
            'today_sales'          => $todaySalesTotal,
            'today_orders'         => $todayOrdersCount,
            'today_net_profit'     => $todayNetProfit,
            'total_outstanding_due'=> $totalOutstandingDue,
            'this_month_sales'     => $thisMonthSales,
            'last_month_sales'     => $lastMonthSales,
            'month_change_percent' => $monthChangePercent,
            'this_month_purchases' => $thisMonthPurchases,
        ];

        $chartData = [
            'labels' => $trendLabels,
            'values' => $trendValues,
        ];

        return [
            'stats'              => $stats,
            'chartData'          => $chartData,
            'lowStockItems'      => $lowStockItems,
            'topProducts'        => $topProducts,
            'recentTransactions' => $recentTransactions,
            'paymentMethods'     => $paymentMethods,
            'customersWithDue'   => $customersWithDue,
        ];
    }

    /**
     * AJAX endpoint for chart period toggle (last7 / last30 / daily / weekly / monthly).
     * Cached per period for 5 minutes.
     */
    public function getDashboardData(Request $request)
    {
        $period = $request->get('period', 'last7');
        $storeId = current_store_id();
        $cacheKey = 'dashboard_chart_' . $period . '_s' . $storeId;

        $chartData = Cache::remember($cacheKey, 300, function () use ($period) {
            $labels = [];
            $values = [];

            if ($period === 'last30') {
                $startDate = Carbon::today()->subDays(29)->format('Y-m-d');
                $salesGrouped = DbSale::where('sales_date', '>=', $startDate)
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->where('sales_status', 'Final')->orWhereNull('sales_status');
                    })
                    ->groupBy('sales_date')
                    ->select('sales_date', DB::raw('SUM(grand_total) as total'))
                    ->pluck('total', 'sales_date');

                for ($i = 29; $i >= 0; $i--) {
                    $date     = Carbon::today()->subDays($i);
                    $labels[] = $date->format('M d');
                    $values[] = (float) ($salesGrouped[$date->format('Y-m-d')] ?? 0);
                }
            } elseif ($period === 'last7' || $period === 'daily') {
                $startDate = Carbon::today()->subDays(6)->format('Y-m-d');
                $salesGrouped = DbSale::where('sales_date', '>=', $startDate)
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->where('sales_status', 'Final')->orWhereNull('sales_status');
                    })
                    ->groupBy('sales_date')
                    ->select('sales_date', DB::raw('SUM(grand_total) as total'))
                    ->pluck('total', 'sales_date');

                for ($i = 6; $i >= 0; $i--) {
                    $date     = Carbon::today()->subDays($i);
                    $labels[] = $date->format('D, M d');
                    $values[] = (float) ($salesGrouped[$date->format('Y-m-d')] ?? 0);
                }
            } elseif ($period === 'weekly') {
                $startRange = Carbon::now()->subWeeks(3)->startOfWeek()->format('Y-m-d');
                $endRange   = Carbon::now()->endOfWeek()->format('Y-m-d');
                $sales = DbSale::whereBetween('sales_date', [$startRange, $endRange])
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->where('sales_status', 'Final')->orWhereNull('sales_status');
                    })
                    ->get(['sales_date', 'grand_total']);

                for ($i = 3; $i >= 0; $i--) {
                    $start    = Carbon::now()->subWeeks($i)->startOfWeek()->format('Y-m-d');
                    $end      = Carbon::now()->subWeeks($i)->endOfWeek()->format('Y-m-d');
                    $labels[] = 'Week ' . Carbon::now()->subWeeks($i)->startOfWeek()->weekOfYear;
                    $values[] = (float) $sales->filter(fn($s) => $s->sales_date >= $start && $s->sales_date <= $end)->sum('grand_total');
                }
            } else {
                // monthly (last 6 months) — avoid whereYear/whereMonth, use indexed date range
                $startRange = Carbon::now()->subMonths(5)->startOfMonth()->format('Y-m-d');
                $endRange   = Carbon::now()->endOfMonth()->format('Y-m-d');
                $sales = DbSale::whereBetween('sales_date', [$startRange, $endRange])
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->where('sales_status', 'Final')->orWhereNull('sales_status');
                    })
                    ->get(['sales_date', 'grand_total']);

                for ($i = 5; $i >= 0; $i--) {
                    $monthObj   = Carbon::now()->subMonths($i);
                    $mStart     = $monthObj->copy()->startOfMonth()->format('Y-m-d');
                    $mEnd       = $monthObj->copy()->endOfMonth()->format('Y-m-d');
                    $labels[]   = $monthObj->format('M Y');
                    $values[]   = (float) $sales->filter(fn($s) => $s->sales_date >= $mStart && $s->sales_date <= $mEnd)->sum('grand_total');
                }
            }

            return compact('labels', 'values');
        });

        return response()->json($chartData);
    }

    /**
     * Legacy chart-data endpoint — kept for backward compatibility.
     * Delegates to getDashboardData().
     */
    public function getChartData(Request $request)
    {
        return $this->getDashboardData($request);
    }
}
