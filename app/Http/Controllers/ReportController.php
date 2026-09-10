<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DbPurchase;
use App\Models\DbPurchaseItem;
use App\Models\DbPurchaseReturn;
use App\Models\DbPurchaseItemReturn;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\DbStockAdjustmentItems;
use App\Models\DbStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DbCustomer;
use App\Models\DbSalePayment;
use App\Models\DbPurchasePayment;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbCustAdvance;
use App\Models\DbSupplier;
use App\Models\DbItem;
use App\Models\User;
use App\Models\DbWarehouse;
use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbWarehouseItem;
use App\Models\CashDrawerReconciliation;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function profitLoss()
    {
        // Resolve the acting store (the reporting user's own store) rather than
        // DbStore::first(), so a Store-B user's P&L header shows Store B, not Store 1.
        $store = function_exists('store_settings') && store_settings()
            ? store_settings()
            : DbStore::first();
        return view('module.reports.profit_loss', compact('store'));
    }

    public function getProfitLossData(Request $request)
    {
        try {
            // 1. Parse dates
            // Assuming format like 'January 8, 2026 - February 6, 2026'
            // or we might pass start and end separately. Let's assume start and end separately as 'Y-m-d' for easier API, 
            // the frontend will format it.
            
            $startDate = null;
            $endDate = null;
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
            } else {
                // Default to last 30 days if no date provided
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
            }

            // Define base query conditions
            $dateCondition = function($query, $dateColumn) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    return $query->whereBetween($dateColumn, [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                }
                return $query;
            };

            // Initialize response structure
            $data = [
                'purchases' => [
                    'totalPurchase' => 0,
                    'purchaseTax' => 0,
                    'otherCharges' => 0,
                    'discount' => 0,
                    'paidAmount' => 0,
                    'due' => 0,
                    'grandTotal' => 0,
                ],
                'purchaseReturns' => [
                    'totalReturn' => 0,
                    'returnTax' => 0,
                    'otherCharges' => 0,
                    'discount' => 0,
                    'paidAmount' => 0,
                    'due' => 0,
                    'grandTotal' => 0,
                ],
                'sales' => [
                    'totalSales' => 0,
                    'salesTax' => 0,
                    'otherCharges' => 0,
                    'discount' => 0,
                    'couponDiscount' => 0,
                    'paidAmount' => 0,
                    'due' => 0,
                    'grandTotal' => 0,
                ],
                'salesReturns' => [
                    'totalReturn' => 0,
                    'returnTax' => 0,
                    'otherCharges' => 0,
                    'couponDiscount' => 0,
                    'discount' => 0,
                    'paidAmount' => 0,
                    'due' => 0,
                    'grandTotal' => 0,
                ],
                'expenses' => [
                    'total' => 0
                ],
                'inventory' => [
                    'openingStock' => 0
                ],
                'summary' => [
                    'grossProfit' => 0,
                    'netProfit' => 0
                ]
            ];

            // --- 1. Opening Stock ---
            // Based on Old POS logic: SUM(adjustment_qty * purchase_price) from db_items & db_stockadjustmentitems
            // We'll calculate it from DbStockAdjustmentItems if adjustments are used for opening stock, or we might need an alternative if this represents current inventory value.
            // Let's implement a basic version that matches old POS:
             $openingStockQuery = DB::table('db_items')
                ->join('db_stockadjustmentitems', 'db_items.id', '=', 'db_stockadjustmentitems.item_id')
                ->select(DB::raw('SUM(db_stockadjustmentitems.adjustment_qty * db_items.purchase_price) as total'));
             
             // Optionally apply date range to opening stock if needed, or leave it absolute. Usually, opening stock is absolute up to the start date.
             // We'll take the sum as old pos did.
             $data['inventory']['openingStock'] = $openingStockQuery->value('total') ?? 0;


            // --- 2. Purchases ---
            $purchasesQuery = DbPurchase::query();
            $purchasesQuery = $dateCondition($purchasesQuery, 'purchase_date');
            
            $purchases = $purchasesQuery->get();
            $purchaseIds = $purchases->pluck('id');

            $data['purchases']['totalPurchase'] = $purchases->sum('grand_total');
            $data['purchases']['otherCharges'] = $purchases->sum('other_charges_amt');
            $data['purchases']['discount'] = $purchases->sum('tot_discount_to_all_amt');
            $data['purchases']['paidAmount'] = $purchases->sum('paid_amount');
            
            // Item level details (Tax & Discount)
            if ($purchaseIds->isNotEmpty()) {
                $purchaseItems = DB::table('db_purchaseitems')
                    ->whereIn('purchase_id', $purchaseIds)
                    ->select(DB::raw('SUM(tax_amt) as tax_amt'), DB::raw('SUM(discount_amt) as discount_amt'))
                    ->first();
                
                $data['purchases']['purchaseTax'] = $purchaseItems->tax_amt ?? 0;
                $data['purchases']['discount'] += $purchaseItems->discount_amt ?? 0;
            }

            // Adjust total purchase by subtracting tax as per old pos logic
            $data['purchases']['grandTotal'] = $data['purchases']['totalPurchase'];
            $data['purchases']['totalPurchase'] -= $data['purchases']['purchaseTax'];
            $data['purchases']['due'] = $data['purchases']['grandTotal'] - $data['purchases']['paidAmount'];


            // --- 3. Purchase Returns ---
            $purchaseReturnsQuery = DbPurchaseReturn::query();
            $purchaseReturnsQuery = $dateCondition($purchaseReturnsQuery, 'return_date');

            $purchaseReturns = $purchaseReturnsQuery->get();
            $purchaseReturnIds = $purchaseReturns->pluck('id');

            $data['purchaseReturns']['totalReturn'] = $purchaseReturns->sum('grand_total');
            $data['purchaseReturns']['otherCharges'] = $purchaseReturns->sum('other_charges_amt');
            $data['purchaseReturns']['discount'] = $purchaseReturns->sum('tot_discount_to_all_amt');
            $data['purchaseReturns']['paidAmount'] = $purchaseReturns->sum('paid_amount');

            if ($purchaseReturnIds->isNotEmpty()) {
                $purchaseReturnItems = DB::table('db_purchaseitemsreturn')
                    ->whereIn('return_id', $purchaseReturnIds)
                    ->select(DB::raw('SUM(tax_amt) as tax_amt'), DB::raw('SUM(discount_amt) as discount_amt'))
                    ->first();
                
                $data['purchaseReturns']['returnTax'] = $purchaseReturnItems->tax_amt ?? 0;
                $data['purchaseReturns']['discount'] += $purchaseReturnItems->discount_amt ?? 0;
            }

            $data['purchaseReturns']['grandTotal'] = $data['purchaseReturns']['totalReturn'];
            $data['purchaseReturns']['totalReturn'] -= $data['purchaseReturns']['returnTax'];
            $data['purchaseReturns']['due'] = $data['purchaseReturns']['grandTotal'] - $data['purchaseReturns']['paidAmount'];


            // --- 4. Sales ---
            $salesQuery = DbSale::where(function($query) {
                $query->where('sales_status', 'Final')->orWhere(function($subq) {
                    $subq->whereNull('sales_status')->where('status', 1);
                });
            });
            $salesQuery = $dateCondition($salesQuery, 'sales_date');

            $sales = $salesQuery->get();
            $salesIds = $sales->pluck('id');

            $data['sales']['totalSales'] = $sales->sum('grand_total');
            $data['sales']['otherCharges'] = $sales->sum('other_charges_amt');
            $data['sales']['discount'] = $sales->sum('tot_discount_to_all_amt');
            $data['sales']['couponDiscount'] = $sales->sum('coupon_amt');
            $data['sales']['paidAmount'] = $sales->sum('paid_amount');

            $salesItemDetails = [
                 'purchase_price' => 0,
                 'sales_price' => 0,
                 'tax_amt' => 0
            ];

            if ($salesIds->isNotEmpty()) {
                $salesItems = DB::table('db_salesitems')
                    ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                    ->whereIn('sales_id', $salesIds)
                    ->select(
                        DB::raw('SUM(db_salesitems.tax_amt) as tax_amt'), 
                        DB::raw('SUM(db_salesitems.discount_amt) as discount_amt'),
                        DB::raw('SUM(db_salesitems.sales_qty * COALESCE(NULLIF(db_salesitems.purchase_price, 0), db_items.purchase_price)) as purchase_price'),
                        DB::raw('SUM(db_salesitems.total_cost) as total_cost')
                    )
                    ->first();

                $data['sales']['salesTax'] = $salesItems->tax_amt ?? 0;
                $data['sales']['discount'] += $salesItems->discount_amt ?? 0;

                $salesItemDetails['purchase_price'] = $salesItems->purchase_price ?? 0;
                $salesItemDetails['sales_price'] = $salesItems->total_cost ?? 0;
                $salesItemDetails['tax_amt'] = $salesItems->tax_amt ?? 0;
            }

            $data['sales']['grandTotal'] = $data['sales']['totalSales'];
            $data['sales']['totalSales'] -= ($data['sales']['salesTax'] + $data['sales']['couponDiscount']);
            $data['sales']['due'] = $data['sales']['grandTotal'] - $data['sales']['paidAmount'];


            // --- 5. Sales Returns ---
            $salesReturnsQuery = DbSalesReturn::query();
            $salesReturnsQuery = $dateCondition($salesReturnsQuery, 'return_date');

            $salesReturns = $salesReturnsQuery->get();
            $salesReturnIds = $salesReturns->pluck('id');

            $data['salesReturns']['totalReturn'] = $salesReturns->sum('grand_total');
            $data['salesReturns']['otherCharges'] = $salesReturns->sum('other_charges_amt');
            $data['salesReturns']['discount'] = $salesReturns->sum('tot_discount_to_all_amt');
            $data['salesReturns']['couponDiscount'] = $salesReturns->sum('coupon_amt');
            $data['salesReturns']['paidAmount'] = $salesReturns->sum('paid_amount');

            $salesReturnItemDetails = [
                 'purchase_price' => 0,
                 'return_price' => 0,
                 'tax_amt' => 0
            ];


            if ($salesReturnIds->isNotEmpty()) {
                $salesReturnItems = DB::table('db_salesitemsreturn')
                    ->join('db_items', 'db_salesitemsreturn.item_id', '=', 'db_items.id')
                    ->whereIn('return_id', $salesReturnIds)
                    ->select(
                        DB::raw('SUM(db_salesitemsreturn.tax_amt) as tax_amt'), 
                        DB::raw('SUM(db_salesitemsreturn.discount_amt) as discount_amt'),
                        DB::raw('SUM(db_salesitemsreturn.return_qty * COALESCE(NULLIF(db_salesitemsreturn.purchase_price, 0), db_items.purchase_price)) as purchase_price'),
                        DB::raw('SUM(db_salesitemsreturn.total_cost) as total_cost')
                    )
                    ->first();

                $data['salesReturns']['returnTax'] = $salesReturnItems->tax_amt ?? 0;
                $data['salesReturns']['discount'] += $salesReturnItems->discount_amt ?? 0;

                $salesReturnItemDetails['purchase_price'] = $salesReturnItems->purchase_price ?? 0;
                $salesReturnItemDetails['return_price'] = $salesReturnItems->total_cost ?? 0;
                $salesReturnItemDetails['tax_amt'] = $salesReturnItems->tax_amt ?? 0;
            }

            $data['salesReturns']['grandTotal'] = $data['salesReturns']['totalReturn'];
            $data['salesReturns']['totalReturn'] -= $data['salesReturns']['returnTax'];
            $data['salesReturns']['due'] = $data['salesReturns']['grandTotal'] - $data['salesReturns']['paidAmount'];


            // --- 6. Expenses ---
            // Exclude soft-deleted (delete_bit=1) expenses (Phase 1 soft-delete).
            $expensesQuery = DbExpense::query()->where('delete_bit', 0);
            $expensesQuery = $dateCondition($expensesQuery, 'expense_date');
            
            $data['expenses']['total'] = $expensesQuery->sum('expense_amt');


            // --- 7. Gross & Net Profit Calculation ---
            // Deduct invoice-level discounts and coupon discounts from sales revenue
            $salesDiscounts = (float) $sales->sum('tot_discount_to_all_amt') + (float) $sales->sum('coupon_amt');
            $salesNetRevenue = max(0, (float)$salesItemDetails['sales_price'] - $salesDiscounts);

            $returnDiscounts = (float) $salesReturns->sum('tot_discount_to_all_amt') + (float) $salesReturns->sum('coupon_amt');
            $returnNetRevenue = max(0, (float)$salesReturnItemDetails['return_price'] - $returnDiscounts);

            // 1. Revenue (Sales)
            $revenue = $salesNetRevenue - $returnNetRevenue;
            
            // 2. Cost of Goods Sold (COGS)
            $cogs = $salesItemDetails['purchase_price'] - $salesReturnItemDetails['purchase_price'];
            
            // Gross Profit = Revenue (Sales) - Cost of Goods Sold (COGS)
            $data['summary']['grossProfit'] = $revenue - $cogs;
            
            // 3. Operating Expenses
            $operatingExpenses = $data['expenses']['total'];
            
            // 4. Interest (EMI processing fee collected from customers)
            $interest = 0;
            if ($salesIds->isNotEmpty()) {
                $interest = DB::table('db_emi_sales')
                    ->whereIn('sale_id', $salesIds)
                    ->sum('processing_fee');
            }
            
            // 5. Tax
            $tax = $salesItemDetails['tax_amt'] - $salesReturnItemDetails['tax_amt'];
            
            // Net Profit = Gross Profit - Operating Expenses + Interest (Income) - Tax
            $data['summary']['netProfit'] = $data['summary']['grossProfit'] - $operatingExpenses + $interest - $tax;


            // Format all numbers to 2 decimal places before sending to frontend
            return response()->json([
                'status' => 'success',
                'data' => $this->formatNumericValues($data)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function salesPayment()
    {
        $customers = DbCustomer::where('status', 1)->get();
        return view('module.reports.sales_payment', compact('customers'));
    }

    public function getSalesPaymentData(Request $request)
    {
        try {
            $customerId = $request->customer_id;
            
            $startDate = null;
            $endDate = null;
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
            } else {
                // Default to last 30 days if no date
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
            }

            if (!$customerId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer is required'
                ], 400);
            }

            $customer = DbCustomer::find($customerId);
            if (!$customer) {
                 return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            // Calculate Previous Due (Before Start Date)
            // 1. Opening Balance
            $openingBalance = $customer->opening_balance ?? 0;

            // 2. Sales before start date
            $salesBefore = DbSale::where('customer_id', $customerId)
                ->where(function($query) {
                    $query->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->where('sales_date', '<', $startDate->format('Y-m-d'))
                ->sum('grand_total');

            // 3. Sales Returns before start date
            $returnsBefore = DbSalesReturn::where('customer_id', $customerId)
                ->where('return_date', '<', $startDate->format('Y-m-d'))
                ->sum('grand_total');

            // 4. Payments Received before start date
            $paymentsBefore = DbSalePayment::where('customer_id', $customerId)
                ->where('payment_date', '<', $startDate->format('Y-m-d'))
                ->sum('payment');

            // 5. Payments Returned (Refunded) before start date
            $refundsBefore = DbSalesPaymentReturn::where('customer_id', $customerId)
                ->where('payment_date', '<', $startDate->format('Y-m-d'))
                ->sum('payment');
                
            // 6. Advances before start date
            $advancesBefore = DbCustAdvance::where('customer_id', $customerId)
                ->where('payment_date', '<', $startDate->format('Y-m-d'))
                ->sum('amount');

            // Previous Due calculation
            $previousDue = $openingBalance + $salesBefore - $returnsBefore - $paymentsBefore + $refundsBefore - $advancesBefore;


            // Fetch Records within the date range
            $records = [];

            // 1. Array of Sales
            $sales = DbSale::where('customer_id', $customerId)
                ->where(function($query) {
                    $query->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();
            
            foreach ($sales as $sale) {
                $records[] = [
                    'id' => 'sale_' . $sale->id,
                    'date' => Carbon::parse($sale->sales_date)->format('Y-m-d'),
                    'invoice' => $sale->sales_code,
                    'refBill' => $sale->reference_no ?? '',
                    'description' => 'Sales',
                    'billAmt' => $sale->grand_total,
                    'receive' => 0,
                    'type' => 'sale',
                    'timestamp' => Carbon::parse($sale->created_time ?? ($sale->sales_date . ' 00:00:00'))->timestamp
                ];
            }

            // 2. Array of Sales Returns
            $returns = DbSalesReturn::where('customer_id', $customerId)
                ->whereBetween('return_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            foreach ($returns as $ret) {
                 $records[] = [
                    'id' => 'return_' . $ret->id,
                    'date' => Carbon::parse($ret->return_date)->format('Y-m-d'),
                    'invoice' => $ret->return_code,
                    'refBill' => $ret->reference_no ?? '',
                    'description' => 'Sales Return',
                    'billAmt' => 0,
                    'receive' => $ret->grand_total,
                    'type' => 'return',
                    'timestamp' => Carbon::parse($ret->created_time ?? ($ret->return_date . ' 00:00:00'))->timestamp
                ];
            }

            // 3. Array of Payments
            $payments = DbSalePayment::where('customer_id', $customerId)
                 ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                 ->with('sale')
                 ->get();

            foreach ($payments as $payment) {
                $description = 'Payment Received';
                if ($payment->payment_type) {
                    $description .= ' (' . $payment->payment_type . ')';
                }
                
                $records[] = [
                    'id' => 'payment_' . $payment->id,
                    'date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                    'invoice' => $payment->payment_code,
                    'refBill' => $payment->sale ? $payment->sale->sales_code : '',
                    'description' => $description,
                    'billAmt' => 0,
                    'receive' => $payment->payment,
                    'type' => 'payment',
                    'timestamp' => Carbon::parse($payment->created_time ?? ($payment->payment_date . ' 00:00:00'))->timestamp
                ];
            }

            // 4. Array of Refunds (Return Payments)
             $refunds = DbSalesPaymentReturn::where('customer_id', $customerId)
                 ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                 ->with('return')
                 ->get();

            foreach ($refunds as $refund) {
                $description = 'Payment Refunded';
                if ($refund->payment_type) {
                    $description .= ' (' . $refund->payment_type . ')';
                }
                
                $records[] = [
                    'id' => 'refund_' . $refund->id,
                    'date' => Carbon::parse($refund->payment_date)->format('Y-m-d'),
                    'invoice' => $refund->payment_code,
                    'refBill' => $refund->return ? $refund->return->return_code : '',
                    'description' => $description,
                    'billAmt' => $refund->payment, // Refund increases due back
                    'receive' => 0,
                    'type' => 'refund',
                    'timestamp' => Carbon::parse($refund->created_time ?? ($refund->payment_date . ' 00:00:00'))->timestamp
                ];
            }

            // 5. Array of Advances
             $advances = DbCustAdvance::where('customer_id', $customerId)
                 ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                 ->get();

             foreach ($advances as $advance) {
                $records[] = [
                    'id' => 'advance_' . $advance->id,
                    'date' => Carbon::parse($advance->payment_date)->format('Y-m-d'),
                    'invoice' => $advance->payment_code,
                    'refBill' => '',
                    'description' => 'Advance Payment',
                    'billAmt' => 0,
                    'receive' => $advance->amount,
                    'type' => 'advance',
                    'timestamp' => Carbon::parse($advance->created_time ?? ($advance->payment_date . ' 00:00:00'))->timestamp
                ];
            }

            // Sort combining timestamp and date
            usort($records, function($a, $b) {
                if ($a['date'] == $b['date']) {
                    return $a['timestamp'] <=> $b['timestamp'];
                }
                return strtotime($a['date']) <=> strtotime($b['date']);
            });

            // Calculate running balance
            $runningBalance = $previousDue;
            foreach ($records as &$record) {
                // Balance = previous + bill - receive
                $runningBalance = $runningBalance + $record['billAmt'] - $record['receive'];
                $record['total'] = number_format((float)$runningBalance, 2, '.', ',');
                
                // Format numbers for display
                $record['billAmt'] = number_format((float)$record['billAmt'], 2, '.', ',');
                $record['receive'] = number_format((float)$record['receive'], 2, '.', ',');
            }


            $customerInfo = [
                'name' => $customer->customer_name,
                'mobile' => $customer->mobile,
                'address' => $customer->address,
                'previousDue' => number_format((float)$previousDue, 2, '.', ',')
            ];

            return response()->json([
                'status' => 'success',
                'customerInfo' => $customerInfo,
                'records' => array_values($records),
                'totalBillAmt' => array_sum(array_column($records, 'billAmt')),
                'totalReceive' => array_sum(array_column($records, 'receive'))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function customerOrders()
    {
        $customers = DbCustomer::where('status', 1)->get();
        return view('module.reports.customer_orders', compact('customers'));
    }

    public function getCustomerOrdersData(Request $request)
    {
        try {
            $customerId = $request->customer_id;
            
            $tillDate = Carbon::now()->endOfDay();
            if ($request->has('till_date') && $request->till_date) {
                $tillDate = Carbon::parse($request->till_date)->endOfDay();
            }

            if (!$customerId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer is required'
                ], 400);
            }

            $customer = DbCustomer::find($customerId);
            if (!$customer) {
                 return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            // Fetch sales up to till_date
            $sales = DbSale::where('customer_id', $customerId)
                ->where(function($query) {
                    $query->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->where('sales_date', '<=', $tillDate->format('Y-m-d'))
                ->orderBy('sales_date', 'desc')
                ->get();

            $records = [];
            $now = Carbon::now()->startOfDay();

            foreach ($sales as $sale) {
                $saleDate = Carbon::parse($sale->sales_date)->startOfDay();
                $inDays = $now->diffInDays($saleDate, false); // Negative if in past
                $inDays = abs($inDays); // Usually presented as positive "days ago"

                $records[] = [
                    'id' => $sale->id,
                    'name' => $customer->customer_name,
                    'date' => $saleDate->format('d-m-Y'),
                    'orderId' => $sale->sales_code,
                    'inDays' => $inDays
                ];
            }

            return response()->json([
                'status' => 'success',
                'orders' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function gstr1()
    {
        return view('module.reports.gstr1');
    }

    public function getGstr1Data(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

            $sales = DbSale::with(['customer', 'items'])
                ->where(function($query) {
                    $query->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('sales_date', 'asc')
                ->get();

            $records = [];
            foreach ($sales as $sale) {
                $taxAmt = $sale->items->sum('tax_amt');
                $taxableAmt = $sale->subtotal;
                $cgst = '0.00';
                $sgst = '0.00';
                $igst = '0.00';
                
                $records[] = [
                    'id' => $sale->id,
                    'invoice' => $sale->sales_code,
                    'date' => Carbon::parse($sale->sales_date)->format('d-m-Y'),
                    'customer' => $sale->customer ? $sale->customer->customer_name : 'Walk-in customer',
                    'gst' => $sale->customer ? $sale->customer->gstin : '',
                    'rate' => number_format((float)$taxableAmt, 2, '.', ''),
                    'tax' => number_format((float)$taxAmt, 2, '.', ''),
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'igst' => $igst,
                    'total' => number_format((float)$sale->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function gstr2()
    {
        return view('module.reports.gstr2');
    }

    public function getGstr2Data(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

            $purchases = DbPurchase::with(['supplier', 'items'])
                ->where(function($query) {
                    $query->where('purchase_status', 'Received')->orWhere(function($subq) {
                        $subq->whereNull('purchase_status')->where('status', 1);
                    });
                })
                ->whereBetween('purchase_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('purchase_date', 'asc')
                ->get();

            $records = [];
            foreach ($purchases as $purchase) {
                $taxAmt = $purchase->items->sum('tax_amt');
                $taxableAmt = $purchase->subtotal;
                $cgst = '0.00';
                $sgst = '0.00';
                $igst = '0.00';
                
                $records[] = [
                    'id' => $purchase->id,
                    'invoice' => $purchase->purchase_code,
                    'date' => Carbon::parse($purchase->purchase_date)->format('d-m-Y'),
                    'supplier' => $purchase->supplier ? $purchase->supplier->supplier_name : 'Unknown Supplier',
                    'gst' => $purchase->supplier ? $purchase->supplier->gstin : '',
                    'rate' => number_format((float)$taxableAmt, 2, '.', ''),
                    'tax' => number_format((float)$taxAmt, 2, '.', ''),
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'igst' => $igst,
                    'total' => number_format((float)$purchase->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function salesGst()
    {
        $customers = DbCustomer::where('status', 1)->get();
        return view('module.reports.sales_gst', compact('customers'));
    }

    public function getSalesGstData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            $customerId = $request->customer_id;

            $query = DbSale::with(['customer', 'items.item'])
                ->where(function($q) {
                    $q->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($customerId && $customerId !== 'all') {
                $query->where('customer_id', $customerId);
            }

            $sales = $query->orderBy('sales_date', 'asc')->get();

            $records = [];
            foreach ($sales as $sale) {
                // To keep it simple, we aggregate the invoice totals.
                // You could loop through $sale->items to get line-by-line tax if needed.
                $taxAmt = $sale->items->sum('tax_amt');
                $taxableAmt = $sale->subtotal;
                
                $records[] = [
                    'id' => $sale->id,
                    'invoice' => $sale->sales_code,
                    'customer' => $sale->customer ? $sale->customer->customer_name : 'Walk-in customer',
                    'gstNo' => $sale->customer ? $sale->customer->gstin : '',
                    'date' => Carbon::parse($sale->sales_date)->format('d-m-Y'),
                    'qty' => number_format((float)$sale->items->sum('sales_qty'), 2, '.', ''),
                    'amount' => number_format((float)($taxableAmt + $taxAmt), 2, '.', ''), // Gross
                    'taxable' => number_format((float)$taxableAmt, 2, '.', ''),
                    'tax' => number_format((float)$taxAmt, 2, '.', ''),
                    'total' => number_format((float)$sale->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function purchaseGst()
    {
        $suppliers = DbSupplier::where('status', 1)->get();
        return view('module.reports.purchase_gst', compact('suppliers'));
    }

    public function getPurchaseGstData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            $supplierId = $request->supplier_id;

            $query = DbPurchase::with(['supplier', 'items.item'])
                ->where(function($q) {
                    $q->where('purchase_status', 'Received')->orWhere(function($subq) {
                        $subq->whereNull('purchase_status')->where('status', 1);
                    });
                })
                ->whereBetween('purchase_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            $purchases = $query->orderBy('purchase_date', 'asc')->get();

            $records = [];
            foreach ($purchases as $purchase) {
                $taxAmt = $purchase->items->sum('tax_amt');
                $taxableAmt = $purchase->subtotal;
                
                $records[] = [
                    'id' => $purchase->id,
                    'invoice' => $purchase->purchase_code,
                    'supplier' => $purchase->supplier ? $purchase->supplier->supplier_name : 'Unknown Supplier',
                    'gstNo' => $purchase->supplier ? $purchase->supplier->gstin : '',
                    'date' => Carbon::parse($purchase->purchase_date)->format('d-m-Y'),
                    'qty' => number_format((float)$purchase->items->sum('purchase_qty'), 2, '.', ''),
                    'amount' => number_format((float)($taxableAmt + $taxAmt), 2, '.', ''), // Gross
                    'taxable' => number_format((float)$taxableAmt, 2, '.', ''),
                    'tax' => number_format((float)$taxAmt, 2, '.', ''),
                    'total' => number_format((float)$purchase->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function salesTax()
    {
        return view('module.reports.sales_tax');
    }

    public function getSalesTaxData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

            $sales = DbSale::with(['customer', 'items'])
                ->where(function($q) {
                    $q->where('sales_status', 'Final')->orWhere(function($subq) {
                        $subq->whereNull('sales_status')->where('status', 1);
                    });
                })
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('sales_date', 'asc')
                ->get();

            $records = [];
            foreach ($sales as $sale) {
                $taxAmt = $sale->items->sum('tax_amt');
                $taxableAmt = $sale->subtotal;
                
                $records[] = [
                    'id' => $sale->id,
                    'invoice' => $sale->sales_code,
                    'date' => Carbon::parse($sale->sales_date)->format('d-m-Y'),
                    'customer' => $sale->customer ? $sale->customer->customer_name : 'Walk-in customer',
                    'taxNumber' => $sale->customer ? $sale->customer->gstin : '',
                    'rate' => number_format((float)$taxableAmt, 2, '.', ''), // Taxable amount
                    'taxAmount' => number_format((float)$taxAmt, 2, '.', ''),
                    'total' => number_format((float)$sale->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function purchaseTax()
    {
        return view('module.reports.purchase_tax');
    }

    public function getPurchaseTaxData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

            $purchases = DbPurchase::with(['supplier', 'items'])
                ->where(function($q) {
                    $q->where('purchase_status', 'Received')->orWhere(function($subq) {
                        $subq->whereNull('purchase_status')->where('status', 1);
                    });
                })
                ->whereBetween('purchase_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('purchase_date', 'asc')
                ->get();

            $records = [];
            foreach ($purchases as $purchase) {
                $taxAmt = $purchase->items->sum('tax_amt');
                $taxableAmt = $purchase->subtotal;
                
                $records[] = [
                    'id' => $purchase->id,
                    'invoice' => $purchase->purchase_code,
                    'date' => Carbon::parse($purchase->purchase_date)->format('d-m-Y'),
                    'customer' => $purchase->supplier ? $purchase->supplier->supplier_name : 'Unknown Supplier', // Kept 'customer' key to match alpine loop structure initially
                    'taxNumber' => $purchase->supplier ? $purchase->supplier->gstin : '',
                    'rate' => number_format((float)$taxableAmt, 2, '.', ''), // Taxable amount
                    'taxAmount' => number_format((float)$taxAmt, 2, '.', ''),
                    'total' => number_format((float)$purchase->grand_total, 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function supplierItems()
    {
        $items = DbItem::where('status', 1)->get();
        $suppliers = DbSupplier::where('status', 1)->get();
        
        return view('module.reports.supplier_items', compact('items', 'suppliers'));
    }

    public function getSupplierItemsData(Request $request)
    {
        try {
            $itemId = $request->item_id;
            $supplierId = $request->supplier_id;

            $query = DbPurchaseItem::with(['purchase.supplier', 'purchase.warehouse', 'item'])
                ->whereHas('purchase', function($q) {
                    $q->where('purchase_status', 'Received')->orWhere(function($subq) {
                        $subq->whereNull('purchase_status')->where('status', 1);
                    });
                });

            if ($itemId && $itemId !== 'all') {
                $query->where('item_id', $itemId);
            }

            if ($supplierId && $supplierId !== 'all') {
                $query->whereHas('purchase', function($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                });
            }

            $purchaseItems = $query->get();

            $records = [];
            foreach ($purchaseItems as $pi) {
                // Ensure parent purchase and item exist before adding to records
                if (!$pi->purchase || !$pi->item) {
                    continue; 
                }

                $records[] = [
                    'id' => $pi->id,
                    'warehouse' => $pi->purchase->warehouse ? $pi->purchase->warehouse->warehouse_name : 'N/A',
                    'invoice' => $pi->purchase->purchase_code,
                    'date' => Carbon::parse($pi->purchase->purchase_date)->format('d-m-Y'),
                    'supplier' => $pi->purchase->supplier ? $pi->purchase->supplier->supplier_name : 'Unknown Supplier',
                    'code' => $pi->item->item_code,
                    'item' => $pi->item->item_name,
                    'price' => number_format((float)$pi->price_per_unit, 2, '.', '')
                ];
            }

            // Sort by date descending natively inside PHP before returning
            usort($records, function($a, $b) {
                return Carbon::createFromFormat('d-m-Y', $b['date'])->timestamp - Carbon::createFromFormat('d-m-Y', $a['date'])->timestamp;
            });

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function salesReport()
    {
        $customers = DbCustomer::where('status', 1)->get();
        return view('module.reports.sales', compact('customers'));
    }

    public function getSalesReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $customerId = $request->customer_id;
            $paymentStatus = $request->payment_status;

            $query = DbSale::with(['customer', 'warehouse'])
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($customerId && $customerId !== 'all') {
                $query->where('customer_id', $customerId);
            }

            if ($paymentStatus && $paymentStatus !== 'all') {
                // Map the frontend status text back to logic if needed, 
                // but if using 'Paid', 'Partial', 'Due' directly from frontend:
                $query->where('payment_status', $paymentStatus);
            }

            $sales = $query->orderBy('sales_date', 'asc')->get();

            $records = [];
            foreach ($sales as $sale) {
                $records[] = [
                    'id' => $sale->id,
                    'date' => Carbon::parse($sale->sales_date)->format('Y-m-d'),
                    'invoice' => $sale->sales_code,
                    'customer' => $sale->customer ? $sale->customer->customer_name : 'Walk-in customer',
                    'location' => $sale->warehouse ? $sale->warehouse->warehouse_name : 'Main Store',
                    'status' => $sale->payment_status ?: 'Due',
                    'method' => 'Cash', // Placeholder, multi-payment system makes this tricky
                    'total' => number_format((float)$sale->grand_total, 2, '.', ''),
                    'paid' => number_format((float)$sale->paid_amount, 2, '.', ''),
                    'due' => number_format((float)($sale->grand_total - $sale->paid_amount), 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function salesReturnReport()
    {
        $customers = DbCustomer::where('status', 1)->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        return view('module.reports.sales_return', compact('customers', 'warehouses'));
    }

    public function getSalesReturnReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $customerId = $request->customer_id;
            $warehouseId = $request->warehouse_id;

            $query = DbSalesReturn::with(['customer', 'warehouse', 'sale'])
                ->whereBetween('return_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($customerId && $customerId !== 'all') {
                $query->where('customer_id', $customerId);
            }

            if ($warehouseId && $warehouseId !== 'all') {
                $query->where('warehouse_id', $warehouseId);
            }

            $returns = $query->orderBy('return_date', 'asc')->get();

            $records = [];
            foreach ($returns as $ret) {
                $records[] = [
                    'id' => $ret->id,
                    'date' => Carbon::parse($ret->return_date)->format('d-m-Y'),
                    'invoice' => $ret->return_code,
                    'salesCode' => $ret->sale ? $ret->sale->sales_code : 'N/A',
                    'customer' => $ret->customer ? $ret->customer->customer_name : 'Walk-in customer',
                    'warehouse' => $ret->warehouse ? $ret->warehouse->warehouse_name : 'Main Warehouse',
                    'total' => number_format((float)$ret->grand_total, 2, '.', ''),
                    'paid' => number_format((float)$ret->paid_amount, 2, '.', ''),
                    'due' => number_format((float)($ret->grand_total - $ret->paid_amount), 2, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sellerPointsReport()
    {
        $users = User::where('status', 1)->get();
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $items = DbItem::where('status', 1)->get();
        return view('module.reports.seller_points', compact('users', 'warehouses', 'items'));
    }

    public function getSellerPointsReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $userId = $request->user_id;
            $warehouseId = $request->warehouse_id;
            $itemId = $request->item_id;

            $query = DbSaleItem::with(['sale.customer', 'sale.user', 'item'])
                ->whereHas('sale', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                });

            if ($userId && $userId !== 'all') {
                $query->whereHas('sale', function($q) use ($userId) {
                    $q->where('created_by', $userId);
                });
            }

            if ($warehouseId && $warehouseId !== 'all') {
                $query->whereHas('sale', function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            }

            if ($itemId && $itemId !== 'all') {
                $query->where('item_id', $itemId);
            }

            $saleItems = $query->orderBy('id', 'desc')->get();

            $records = [];
            foreach ($saleItems as $sItem) {
                $records[] = [
                    'id' => $sItem->id,
                    'invoice' => $sItem->sale ? $sItem->sale->sales_code : 'N/A',
                    'date' => $sItem->sale ? Carbon::parse($sItem->sale->sales_date)->format('d-m-Y') : 'N/A',
                    'customer' => ($sItem->sale && $sItem->sale->customer) ? $sItem->sale->customer->customer_name : 'Walk-in Customer',
                    'item' => $sItem->item ? $sItem->item->item_name : 'N/A',
                    'quantity' => number_format((float)$sItem->sales_qty, 2, '.', ''),
                    'seller' => ($sItem->sale && $sItem->sale->user) ? $sItem->sale->user->username : 'N/A',
                    'points' => number_format((float)$sItem->seller_points, 1, '.', '')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function purchaseReport()
    {
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $suppliers = DbSupplier::where('status', 1)->get();
        return view('module.reports.purchase', compact('warehouses', 'suppliers'));
    }

    public function getPurchaseReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $warehouseId = $request->warehouse_id;
            $supplierId = $request->supplier_id;
            $viewAccountPayable = $request->view_account_payable === 'true';

            $query = DbPurchase::with(['warehouse', 'supplier', 'warehouseStore'])
                ->whereBetween('purchase_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($warehouseId && $warehouseId !== 'all') {
                $query->where('warehouse_id', $warehouseId);
            }

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            if ($viewAccountPayable) {
                $query->whereRaw('grand_total > paid_amount');
            }

            $purchases = $query->orderBy('purchase_date', 'asc')->get();

            $records = [];
            foreach ($purchases as $pur) {
                $records[] = [
                    'id' => $pur->id,
                    'date' => Carbon::parse($pur->purchase_date)->format('d-m-Y'),
                    'invoice' => $pur->purchase_code,
                    'warehouse' => $pur->warehouse ? $pur->warehouse->warehouse_name : 'Main Branch',
                    'supplier' => $pur->supplier ? $pur->supplier->supplier_name : 'N/A',
                    'supplierId' => $pur->supplier ? $pur->supplier->supplier_code : 'N/A',
                    'total' => number_format((float)$pur->grand_total, 2, '.', ','),
                    'paid' => number_format((float)$pur->paid_amount, 2, '.', ','),
                    'due' => number_format((float)($pur->grand_total - $pur->paid_amount), 2, '.', ',')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function purchaseReturnReport()
    {
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $suppliers = DbSupplier::where('status', 1)->get();
        return view('module.reports.purchase_return', compact('warehouses', 'suppliers'));
    }

    public function getPurchaseReturnReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $warehouseId = $request->warehouse_id;
            $supplierId = $request->supplier_id;

            $query = DbPurchaseReturn::with(['warehouse', 'supplier', 'purchase'])
                ->whereBetween('return_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($warehouseId && $warehouseId !== 'all') {
                $query->where('warehouse_id', $warehouseId);
            }

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            $returns = $query->orderBy('return_date', 'asc')->get();

            $records = [];
            foreach ($returns as $ret) {
                $records[] = [
                    'id' => $ret->id,
                    'date' => Carbon::parse($ret->return_date)->format('d-m-Y'),
                    'invoice' => $ret->return_code,
                    'warehouse' => $ret->warehouse ? $ret->warehouse->warehouse_name : 'Main Branch',
                    'purchaseCode' => $ret->purchase ? $ret->purchase->purchase_code : 'N/A',
                    'supplier' => $ret->supplier ? $ret->supplier->supplier_name : 'N/A',
                    'total' => number_format((float)$ret->grand_total, 2, '.', ','),
                    'paid' => number_format((float)$ret->paid_amount, 2, '.', ','),
                    'due' => number_format((float)($ret->grand_total - $ret->paid_amount), 2, '.', ',')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function expenseReport()
    {
        $categories = DbExpenseCategory::where('status', 1)->get();
        return view('module.reports.expense', compact('categories'));
    }

    public function getExpenseReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $categoryId = $request->category_id;

            $query = DbExpense::with(['category'])
                ->where('delete_bit', 0)
                ->whereBetween('expense_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($categoryId && $categoryId !== 'all') {
                $query->where('category_id', $categoryId);
            }

            $expenses = $query->orderBy('expense_date', 'asc')->get();

            $records = [];
            foreach ($expenses as $exp) {
                $records[] = [
                    'id' => $exp->id,
                    'code' => $exp->expense_code,
                    'date' => Carbon::parse($exp->expense_date)->format('d-m-Y'),
                    'category' => $exp->category ? $exp->category->category_name : 'N/A',
                    'reference' => $exp->reference_no ?: 'N/A',
                    'expenseFor' => $exp->expense_for ?: 'N/A',
                    'amount' => number_format((float)$exp->expense_amt, 2, '.', ','),
                    'note' => $exp->note ?: '',
                    'createdBy' => $exp->created_by ?: 'System'
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function stockReport()
    {
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $categories = DbCategory::where('status', 1)->get();
        $brands = DbBrand::where('status', 1)->get();
        return view('module.reports.stock', compact('warehouses', 'categories', 'brands'));
    }

    public function getStockReportData(Request $request)
    {
        try {
            $viewType = $request->view_type ?: 'item-wise';
            $warehouseId = $request->warehouse_id;
            $categoryId = $request->category_id;
            $brandId = $request->brand_id;

            if ($viewType === 'item-wise') {
                $query = DbItem::with(['category', 'brand', 'warehouseItems']);

                if ($categoryId && $categoryId !== 'all') {
                    $query->where('category_id', $categoryId);
                }
                if ($brandId && $brandId !== 'all') {
                    $query->where('brand_id', $brandId);
                }

                $items = $query->get();
                $records = [];

                foreach ($items as $item) {
                    $stock = 0;
                    if ($warehouseId && $warehouseId !== 'all') {
                        $whItem = $item->warehouseItems()->where('warehouse_id', $warehouseId)->first();
                        $stock = $whItem ? $whItem->available_qty : 0;
                    } else {
                        $stock = $item->warehouseItems()->sum('available_qty');
                    }

                    if ($stock <= 0 && $request->hide_zero == 'true') continue;

                    $records[] = [
                        'id' => $item->id,
                        'code' => $item->item_code,
                        'name' => $item->item_name,
                        'brand' => $item->brand ? $item->brand->brand_name : 'No Brand',
                        'category' => $item->category ? $item->category->category_name : 'No Category',
                        'unitPrice' => number_format((float)$item->purchase_price, 2, '.', ','),
                        'salesPrice' => number_format((float)$item->sales_price, 2, '.', ','),
                        'stock' => number_format((float)$stock, 2, '.', ','),
                        'value' => number_format((float)($stock * $item->purchase_price), 2, '.', ',')
                    ];
                }
            } else {
                // Brand Wise
                $query = DbBrand::with(['items.warehouseItems']);
                
                if ($brandId && $brandId !== 'all') {
                    $query->where('id', $brandId);
                }

                $brands = $query->get();
                $records = [];

                foreach ($brands as $brand) {
                    $totalStock = 0;
                    $totalValue = 0;

                    foreach ($brand->items as $item) {
                        if ($categoryId && $categoryId !== 'all' && $item->category_id != $categoryId) continue;

                        $stock = 0;
                        if ($warehouseId && $warehouseId !== 'all') {
                            $whItem = $item->warehouseItems()->where('warehouse_id', $warehouseId)->first();
                            $stock = $whItem ? $whItem->available_qty : 0;
                        } else {
                            $stock = $item->warehouseItems()->sum('available_qty');
                        }

                        $totalStock += $stock;
                        $totalValue += ($stock * $item->purchase_price);
                    }

                    if ($totalStock <= 0 && $request->hide_zero == 'true') continue;

                    $records[] = [
                        'id' => $brand->id,
                        'brand' => $brand->brand_name,
                        'stock' => number_format((float)$totalStock, 2, '.', ','),
                        'value' => number_format((float)$totalValue, 2, '.', ',')
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() . ' line: ' . $e->getLine()
            ], 500);
        }
    }

    public function salesItemReport()
    {
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $categories = DbCategory::where('status', 1)->get();
        $items = DbItem::where('status', 1)->where('child_bit', 0)->get();
        return view('module.reports.sales_item', compact('warehouses', 'categories', 'items'));
    }

    public function getSalesItemReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $warehouseId = $request->warehouse_id;
            $categoryId = $request->category_id;
            $itemId = $request->item_id;
            $itemType = $request->item_type; // 'all', 'standard', 'service'

            $query = DbSaleItem::with(['sale.customer', 'item.category'])
                ->whereHas('sale', function($q) use ($startDate, $endDate, $warehouseId) {
                    $q->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                    if ($warehouseId && $warehouseId !== 'all') {
                        $q->where('warehouse_id', $warehouseId);
                    }
                });

            if ($categoryId && $categoryId !== 'all') {
                $query->whereHas('item', function($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            }

            if ($itemId && $itemId !== 'all') {
                $query->where('item_id', $itemId);
            }

            if ($itemType && $itemType !== 'all') {
                $serviceBit = ($itemType === 'service') ? 1 : 0;
                $query->whereHas('item', function($q) use ($serviceBit) {
                    $q->where('service_bit', $serviceBit);
                });
            }

            $salesItems = $query->orderBy('id', 'desc')->get();

            $records = [];
            foreach ($salesItems as $si) {
                $records[] = [
                    'id' => $si->id,
                    'invoice' => $si->sale ? $si->sale->sales_code : 'N/A',
                    'date' => $si->sale ? Carbon::parse($si->sale->sales_date)->format('d-m-Y') : 'N/A',
                    'customer' => ($si->sale && $si->sale->customer) ? $si->sale->customer->customer_name : 'Walk-in Customer',
                    'itemName' => $si->item ? $si->item->item_name : 'Deleted Item',
                    'category' => ($si->item && $si->item->category) ? $si->item->category->category_name : 'N/A',
                    'quantity' => number_format((float)$si->sales_qty, 2, '.', ','),
                    'unitPrice' => number_format((float)$si->price_per_unit, 2, '.', ','),
                    'total' => number_format((float)$si->total_cost, 2, '.', ',')
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() . ' line: ' . $e->getLine()
            ], 500);
        }
    }

    public function returnItemsReport()
    {
        // Phase 4: store-scoped + active-only warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->where('delete_bit', 0)->get();
        $items = DbItem::where('status', 1)->where('child_bit', 0)->get();
        return view('module.reports.return_items', compact('warehouses', 'items'));
    }

    public function getReturnItemsReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $warehouseId = $request->warehouse_id;
            $itemId = $request->item_id;

            $query = DbSalesItemReturn::with(['return.customer', 'item'])
                ->whereHas('return', function($q) use ($startDate, $endDate, $warehouseId) {
                    $q->whereBetween('return_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                    if ($warehouseId && $warehouseId !== 'all') {
                        $q->where('warehouse_id', $warehouseId);
                    }
                });

            if ($itemId && $itemId !== 'all') {
                $query->where('item_id', $itemId);
            }

            $returnItems = $query->orderBy('id', 'desc')->get();

            $records = [];
            foreach ($returnItems as $ri) {
                $records[] = [
                    'id' => $ri->id,
                    'invoice' => $ri->return ? $ri->return->return_code : 'N/A',
                    'date' => $ri->return ? Carbon::parse($ri->return->return_date)->format('d-m-Y') : 'N/A',
                    'customer' => ($ri->return && $ri->return->customer) ? $ri->return->customer->customer_name : 'Walk-in Customer',
                    'itemName' => $ri->item ? $ri->item->item_name : 'Deleted Item',
                    'quantity' => number_format((float)$ri->return_qty, 2, '.', ','),
                    'total' => number_format((float)$ri->total_cost, 2, '.', ','),
                    'status' => $ri->return ? $ri->return->return_status : 'N/A'
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() . ' line: ' . $e->getLine()
            ], 500);
        }
    }

    public function purchasePaymentsReport()
    {
        $suppliers = DbSupplier::where('status', 1)->get();
        $users = User::all();
        return view('module.reports.purchase_payments', compact('suppliers', 'users'));
    }

    public function getPurchasePaymentsReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $supplierId = $request->supplier_id;
            $paymentType = $request->payment_type;
            $userId = $request->user_id;

            $query = DbPurchasePayment::with(['purchase', 'supplier', 'user'])
                ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            if ($paymentType && $paymentType !== 'all') {
                $query->where('payment_type', $paymentType);
            }

            if ($userId && $userId !== 'all') {
                $query->where('created_by', $userId);
            }

            $payments = $query->orderBy('payment_date', 'desc')->get();

            $records = [];
            foreach ($payments as $pay) {
                $records[] = [
                    'id' => $pay->id,
                    'invoice' => $pay->payment_code ?? 'N/A',
                    'date' => Carbon::parse($pay->payment_date)->format('d-m-Y'),
                    'purchase_code' => $pay->purchase ? $pay->purchase->purchase_code : 'N/A',
                    'supplier' => $pay->supplier ? $pay->supplier->supplier_name : 'N/A',
                    'supplierId' => $pay->supplier ? $pay->supplier->supplier_code : 'N/A',
                    'type' => $pay->payment_type ?? 'N/A',
                    'amount' => number_format((float)$pay->payment, 2, '.', ','),
                    'user' => $pay->user ? $pay->user->username : 'System',
                    'note' => $pay->payment_note ?? ''
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() . ' line: ' . $e->getLine()
            ], 500);
        }
    }

    public function salesPaymentsReport()
    {
        $customers = DbCustomer::where('status', 1)->get();
        $users = User::all();
        return view('module.reports.sales_payments', compact('customers', 'users'));
    }

    public function getSalesPaymentsReportData(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $customerId = $request->customer_id;
            $paymentType = $request->payment_type;
            $userId = $request->user_id;

            $query = DbSalePayment::with(['sale', 'customer', 'user'])
                ->whereBetween('payment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($customerId && $customerId !== 'all') {
                $query->where('customer_id', $customerId);
            }

            if ($paymentType && $paymentType !== 'all') {
                $query->where('payment_type', $paymentType);
            }

            if ($userId && $userId !== 'all') {
                $query->where('created_by', $userId);
            }

            $payments = $query->orderBy('payment_date', 'desc')->get();

            $records = [];
            foreach ($payments as $pay) {
                $records[] = [
                    'id' => $pay->id,
                    'invoice' => $pay->payment_code ?? 'N/A',
                    'date' => Carbon::parse($pay->payment_date)->format('d-m-Y'),
                    'sale_code' => $pay->sale ? $pay->sale->sales_code : 'N/A',
                    'customer' => $pay->customer ? $pay->customer->customer_name : 'Walk-in Customer',
                    'customerMobile' => $pay->customer ? $pay->customer->mobile : 'N/A',
                    'type' => $pay->payment_type ?? 'N/A',
                    'amount' => number_format((float)$pay->payment, 2, '.', ','),
                    'user' => $pay->user ? $pay->user->username : 'System',
                    'note' => $pay->payment_note ?? ''
                ];
            }

            return response()->json([
                'status' => 'success',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() . ' line: ' . $e->getLine()
            ], 500);
        }
    }

    private function formatNumericValues($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->formatNumericValues($value);
            } elseif (is_numeric($value)) {
                $value = number_format((float)$value, 2, '.', ',');
            }
        }
        return $array;
    }

    public function salesSummary()
    {
        // Cache dropdown lists for 3600 seconds as per established pattern
        // Phase 4: store-scoped + active-only + per-store cache key (no cross-store leak).
        $storeId = current_store_id();
        $warehouses = Cache::remember('db_warehouses_list_s' . $storeId, 3600, function () use ($storeId) {
            return DbWarehouse::where('store_id', $storeId)->where('status', 1)->where('delete_bit', 0)->select('id', 'warehouse_name')->get();
        });

        $categories = Cache::remember('db_categories_list', 3600, function () {
            return DbCategory::where('status', 1)->select('id', 'category_name')->get();
        });

        $customers = Cache::remember('db_customers_summary_list', 3600, function () {
            return DbCustomer::where('status', 1)->select('id', 'customer_name', 'customer_code')->get();
        });

        return view('module.reports.sales_summary', compact('warehouses', 'categories', 'customers'));
    }

    public function getSalesSummaryData(Request $request)
    {
        try {
            $startDate = $request->start_date 
                ? Carbon::parse($request->start_date)->startOfDay() 
                : Carbon::now()->startOfMonth()->startOfDay();
            $endDate = $request->end_date 
                ? Carbon::parse($request->end_date)->endOfDay() 
                : Carbon::now()->endOfDay();

            $warehouseId = $request->warehouse_id;
            $customerId = $request->customer_id;
            $paymentStatus = $request->payment_status;

            // Base Sales Query with date range & optional filters
            $salesQuery = DbSale::query()
                ->where(function ($q) {
                    $q->where('sales_status', 'Final')
                      ->orWhereNull('sales_status');
                })
                ->where('status', 1)
                ->whereBetween('sales_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($warehouseId && $warehouseId !== 'all') {
                $salesQuery->where('warehouse_id', $warehouseId);
            }
            if ($customerId && $customerId !== 'all') {
                $salesQuery->where('customer_id', $customerId);
            }
            if ($paymentStatus && $paymentStatus !== 'all') {
                $salesQuery->where('payment_status', $paymentStatus);
            }

            $sales = $salesQuery->with(['customer', 'warehouse'])->get();
            $salesIds = $sales->pluck('id');

            $returnsQuery = DB::table('db_salesreturn')
                ->whereIn('sales_id', $salesIds)
                ->select(
                    'sales_id',
                    DB::raw('COALESCE(SUM(grand_total), 0) as total_return'),
                    DB::raw('COALESCE(SUM(paid_amount), 0) as total_refunded')
                )
                ->groupBy('sales_id');
            $returnsBySale = (clone $returnsQuery)->get()
                ->keyBy('sales_id');

            $effectiveDue = function ($sale) use ($returnsBySale) {
                $returns = $returnsBySale->get($sale->id);
                $netTotal = (float) $sale->grand_total - (float) ($returns->total_return ?? 0);
                $netPaid = (float) $sale->paid_amount - (float) ($returns->total_refunded ?? 0);

                return max(0, $netTotal - $netPaid);
            };

            // 1. Overview Calculations
            $totalSales = (float) $sales->sum('grand_total');
            $totalOrders = $sales->count();
            $totalPaid = (float) $sales->sum('paid_amount');
            $totalDue = (float) $sales->sum($effectiveDue);
            $avgOrderValue = $totalOrders > 0 ? ($totalSales / $totalOrders) : 0;
            $invoiceDiscounts = (float) $sales->sum('tot_discount_to_all_amt') + (float) $sales->sum('coupon_amt');

            $lineItemStats = DB::table('db_salesitems')
                ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                ->whereIn('db_salesitems.sales_id', $salesIds)
                ->select(
                    DB::raw('COALESCE(SUM(db_salesitems.discount_amt), 0) as item_discount'),
                    DB::raw('COALESCE(SUM(db_salesitems.tax_amt), 0) as total_tax'),
                    DB::raw('COALESCE(SUM(db_salesitems.sales_qty * COALESCE(NULLIF(db_salesitems.purchase_price, 0), db_items.purchase_price, 0)), 0) as total_cost')
                )
                ->first();

            $totalItemDiscount = (float) ($lineItemStats->item_discount ?? 0);
            $totalDiscount = $totalItemDiscount + $invoiceDiscounts;
            $totalTax = (float) ($lineItemStats->total_tax ?? 0);
            $totalCost = (float) ($lineItemStats->total_cost ?? 0);
            $totalProfit = $totalSales - $totalCost;

            // 2. Payment Status Breakdown
            $paymentStatusStats = [
                'Paid' => ['count' => 0, 'amount' => 0],
                'Partial' => ['count' => 0, 'amount' => 0],
                'Unpaid' => ['count' => 0, 'amount' => 0],
            ];
            foreach ($sales as $sale) {
                $statusKey = in_array($sale->payment_status, ['Paid', 'Partial', 'Unpaid']) ? $sale->payment_status : 'Unpaid';
                $paymentStatusStats[$statusKey]['count']++;
                $paymentStatusStats[$statusKey]['amount'] += (float) $sale->grand_total;
            }

            // 3. Payment Method Breakdown (from db_salespayments)
            $paymentMethods = DB::table('db_salespayments')
                ->whereIn('sales_id', $salesIds)
                ->select('payment_type', DB::raw('SUM(payment) as total_paid'), DB::raw('COUNT(*) as txn_count'))
                ->groupBy('payment_type')
                ->get()
                ->map(function ($pm) {
                    return [
                        'method' => $pm->payment_type ?: 'Cash',
                        'amount' => (float) $pm->total_paid,
                        'count' => (int) $pm->txn_count
                    ];
                });

            // 4. Warehouse-wise Summary
            $warehouseWise = DB::table('db_sales')
                ->leftJoin('db_warehouse', 'db_sales.warehouse_id', '=', 'db_warehouse.id')
                ->whereIn('db_sales.id', $salesIds)
                ->select(
                    'db_sales.warehouse_id',
                    DB::raw("COALESCE(db_warehouse.warehouse_name, 'Default Warehouse') as warehouse_name"),
                    DB::raw('COUNT(db_sales.id) as total_orders'),
                    DB::raw('SUM(db_sales.grand_total) as total_sales'),
                    DB::raw('SUM(db_sales.paid_amount) as total_paid'),
                    DB::raw('SUM((db_sales.grand_total - COALESCE(ret.total_return, 0)) - (db_sales.paid_amount - COALESCE(ret.total_refunded, 0))) as total_due')
                )
                ->leftJoinSub($returnsQuery, 'ret', function ($join) {
                        $join->on('db_sales.id', '=', 'ret.sales_id');
                    })
                ->groupBy('db_sales.warehouse_id', 'db_warehouse.warehouse_name')
                ->get()
                ->map(function ($wh) {
                    return [
                        'warehouse_id' => $wh->warehouse_id,
                        'warehouse_name' => $wh->warehouse_name,
                        'total_orders' => (int) $wh->total_orders,
                        'total_sales' => (float) $wh->total_sales,
                        'total_paid' => (float) $wh->total_paid,
                        'total_due' => (float) $wh->total_due,
                    ];
                });

            // 5. Top Selling Products (Top 10)
            $topProducts = DB::table('db_salesitems')
                ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                ->whereIn('db_salesitems.sales_id', $salesIds)
                ->select(
                    'db_items.id as item_id',
                    'db_items.item_name',
                    'db_items.item_code',
                    DB::raw('SUM(db_salesitems.sales_qty) as total_qty'),
                    DB::raw('SUM(db_salesitems.total_cost) as total_revenue'),
                    DB::raw('SUM(db_salesitems.sales_qty * COALESCE(NULLIF(db_salesitems.purchase_price, 0), db_items.purchase_price, 0)) as total_cost')
                )
                ->groupBy('db_items.id', 'db_items.item_name', 'db_items.item_code')
                ->orderByDesc('total_qty')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $profit = (float) $item->total_revenue - (float) $item->total_cost;
                    return [
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'item_code' => $item->item_code,
                        'qty' => (float) $item->total_qty,
                        'revenue' => (float) $item->total_revenue,
                        'cost' => (float) $item->total_cost,
                        'profit' => $profit,
                    ];
                });

            // 6. Category-wise Sales Breakdown
            $categoryWise = DB::table('db_salesitems')
                ->join('db_items', 'db_salesitems.item_id', '=', 'db_items.id')
                ->leftJoin('db_category', 'db_items.category_id', '=', 'db_category.id')
                ->whereIn('db_salesitems.sales_id', $salesIds)
                ->select(
                    DB::raw("COALESCE(db_category.category_name, 'Uncategorized') as category_name"),
                    DB::raw('SUM(db_salesitems.sales_qty) as total_qty'),
                    DB::raw('SUM(db_salesitems.total_cost) as total_sales')
                )
                ->groupBy('db_category.category_name')
                ->orderByDesc('total_sales')
                ->get()
                ->map(function ($cat) {
                    return [
                        'category_name' => $cat->category_name,
                        'total_qty' => (float) $cat->total_qty,
                        'total_sales' => (float) $cat->total_sales,
                    ];
                });

            // 7. Customer Summary (Top Customers & Outstanding Due List)
            $customerStats = DB::table('db_sales')
                ->leftJoin('db_customers', 'db_sales.customer_id', '=', 'db_customers.id')
                ->whereIn('db_sales.id', $salesIds)
                ->select(
                    'db_sales.customer_id',
                    DB::raw("COALESCE(db_customers.customer_name, 'Walk-in Customer') as customer_name"),
                    DB::raw("COALESCE(db_customers.mobile, 'N/A') as mobile"),
                    DB::raw('COUNT(db_sales.id) as orders_count'),
                    DB::raw('SUM(db_sales.grand_total) as total_spent'),
                    DB::raw('SUM(db_sales.paid_amount) as total_paid'),
                    DB::raw('SUM((db_sales.grand_total - COALESCE(ret.total_return, 0)) - (db_sales.paid_amount - COALESCE(ret.total_refunded, 0))) as total_due')
                )
                ->leftJoinSub($returnsQuery, 'ret', function ($join) {
                        $join->on('db_sales.id', '=', 'ret.sales_id');
                    })
                ->groupBy('db_sales.customer_id', 'db_customers.customer_name', 'db_customers.mobile')
                ->orderByDesc('total_spent')
                ->get()
                ->map(function ($cust) {
                    return [
                        'customer_id' => $cust->customer_id,
                        'customer_name' => $cust->customer_name,
                        'mobile' => $cust->mobile,
                        'orders_count' => (int) $cust->orders_count,
                        'total_spent' => (float) $cust->total_spent,
                        'total_paid' => (float) $cust->total_paid,
                        'total_due' => (float) $cust->total_due,
                    ];
                });

            $topCustomers = $customerStats->take(5)->values();
            $customersWithDue = $customerStats->filter(fn($c) => (float) $c['total_due'] > 0)->values();

            // 8. Daily Trend (Date-wise aggregations for Chart.js Line Chart)
            $dailyTrend = DB::table('db_sales')
                ->whereIn('id', $salesIds)
                ->select(
                    'sales_date',
                    DB::raw('COUNT(id) as orders'),
                    DB::raw('SUM(grand_total) as total_sales'),
                    DB::raw('SUM(paid_amount) as total_paid')
                )
                ->groupBy('sales_date')
                ->orderBy('sales_date', 'asc')
                ->get()
                ->map(function ($row) {
                    return [
                        'date' => Carbon::parse($row->sales_date)->format('Y-m-d'),
                        'formatted_date' => Carbon::parse($row->sales_date)->format('M d, Y'),
                        'orders' => (int) $row->orders,
                        'sales' => (float) $row->total_sales,
                        'paid' => (float) $row->total_paid,
                    ];
                });

            // Detailed Invoices for Table View
            $invoices = $sales->map(function ($sale) use ($effectiveDue) {
                return [
                    'id' => $sale->id,
                    'sales_code' => $sale->sales_code,
                    'sales_date' => Carbon::parse($sale->sales_date)->format('Y-m-d'),
                    'customer' => $sale->customer ? $sale->customer->customer_name : 'Walk-in Customer',
                    'warehouse' => $sale->warehouse ? $sale->warehouse->warehouse_name : 'Default',
                    'grand_total' => (float) $sale->grand_total,
                    'paid_amount' => (float) $sale->paid_amount,
                    'due_amount' => $effectiveDue($sale),
                    'payment_status' => $sale->payment_status ?: 'Unpaid',
                ];
            });

            return response()->json([
                'status' => 'success',
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_orders' => $totalOrders,
                    'total_paid' => $totalPaid,
                    'total_due' => $totalDue,
                    'total_discount' => $totalDiscount,
                    'total_tax' => $totalTax,
                    'total_cost' => $totalCost,
                    'total_profit' => $totalProfit,
                    'avg_order_value' => $avgOrderValue,
                ],
                'breakdowns' => [
                    'payment_status' => $paymentStatusStats,
                    'payment_methods' => $paymentMethods,
                    'warehouse_wise' => $warehouseWise,
                    'top_products' => $topProducts,
                    'category_wise' => $categoryWise,
                    'top_customers' => $topCustomers,
                    'customers_with_due' => $customersWithDue,
                ],
                'charts' => [
                    'daily_trend' => $dailyTrend,
                    'payment_methods' => $paymentMethods,
                    'category_wise' => $categoryWise,
                ],
                'data' => $invoices,
            ]);

        } catch (\Exception $e) {
            Log::error('Sales Summary Report Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating sales summary: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cashReconciliationReport()
    {
        // Phase 4: store-scoped + active-only + per-store cache key.
        $storeId = current_store_id();
        $warehouses = Cache::remember('db_warehouses_list_s' . $storeId, 3600, function () use ($storeId) {
            return DbWarehouse::where('store_id', $storeId)->where('status', 1)->where('delete_bit', 0)->select('id', 'warehouse_name')->get();
        });
        $accounts = Cache::remember('db_accounts_list', 3600, function () {
            return AcAccount::where('status', 1)->where('delete_bit', 0)->select('id', 'account_name', 'account_code')->get();
        });
        $users = User::select('id', 'username', 'first_name', 'last_name')->get();

        return view('module.reports.cash_reconciliation', compact('warehouses', 'accounts', 'users'));
    }

    public function getCashReconciliationData(Request $request)
    {
        try {
            $query = CashDrawerReconciliation::with(['account', 'warehouse', 'user'])->orderBy('reconciliation_date', 'desc');

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereDate('reconciliation_date', '>=', $request->start_date)
                      ->whereDate('reconciliation_date', '<=', $request->end_date);
            } elseif ($request->filled('start_date')) {
                $query->whereDate('reconciliation_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->whereDate('reconciliation_date', '<=', $request->end_date);
            }

            $records = $query->get();

            $totalExpected = (float) $records->sum('expected_closing_balance');
            $totalCounted = (float) $records->sum('counted_amount');
            $totalVariance = (float) $records->sum('variance');
            $totalShortage = (float) $records->where('variance', '<', 0)->sum(fn($r) => abs($r->variance));
            $totalOverage = (float) $records->where('variance', '>', 0)->sum('variance');
            $totalReconciledCount = $records->count();
            $balancedCount = $records->where('variance', 0)->count();

            $dailyTrend = $records->groupBy(fn($r) => Carbon::parse($r->reconciliation_date)->format('Y-m-d'))
                ->map(function ($group, $date) {
                    return [
                        'date' => $date,
                        'formatted_date' => Carbon::parse($date)->format('M d, Y'),
                        'expected' => (float) $group->sum('expected_closing_balance'),
                        'counted' => (float) $group->sum('counted_amount'),
                        'variance' => (float) $group->sum('variance'),
                    ];
                })->values();

            $data = $records->map(function ($r) {
                return [
                    'id' => $r->id,
                    'code' => $r->reconciliation_code,
                    'date' => Carbon::parse($r->reconciliation_date)->format('Y-m-d'),
                    'account' => $r->account ? $r->account->account_name : 'N/A',
                    'warehouse' => $r->warehouse ? $r->warehouse->warehouse_name : 'All / Store',
                    'user' => $r->user ? ($r->user->full_name ?: $r->user->username) : 'System',
                    'opening_balance' => (float) $r->opening_balance,
                    'cash_sales' => (float) $r->cash_sales_amount,
                    'cash_refunds' => (float) $r->cash_refunds_amount,
                    'cash_expenses' => (float) $r->cash_expenses_amount,
                    'expected' => (float) $r->expected_closing_balance,
                    'counted' => (float) $r->counted_amount,
                    'variance' => (float) $r->variance,
                    'status' => $r->status,
                    'notes' => $r->notes ?? '',
                ];
            });

            return response()->json([
                'status' => 'success',
                'summary' => [
                    'total_reconciliations' => $totalReconciledCount,
                    'balanced_count' => $balancedCount,
                    'total_expected' => $totalExpected,
                    'total_counted' => $totalCounted,
                    'total_variance' => $totalVariance,
                    'total_shortage' => $totalShortage,
                    'total_overage' => $totalOverage,
                ],
                'charts' => [
                    'daily_trend' => $dailyTrend,
                ],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Cash Reconciliation Report Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating cash reconciliation report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cashFlowReport()
    {
        $accounts = Cache::remember('db_accounts_list', 3600, function () {
            return AcAccount::where('status', 1)->where('delete_bit', 0)->select('id', 'account_name', 'account_code')->get();
        });

        return view('module.reports.cash_flow', compact('accounts'));
    }

    public function getCashFlowReportData(Request $request)
    {
        try {
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();
            
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $endDate->format('Y-m-d');

            $accountId = $request->filled('account_id') && $request->account_id !== 'all' ? (int) $request->account_id : null;

            // 1. Fetch accounts in scope
            $accountsQuery = AcAccount::where('delete_bit', 0);
            if ($accountId) {
                $accountsQuery->where('id', $accountId);
            } else {
                $accountsQuery->where('status', 1);
            }
            $accounts = $accountsQuery->get();
            $accountIds = $accounts->pluck('id')->toArray();

            if (empty($accountIds)) {
                return response()->json([
                    'status' => 'success',
                    'period' => [
                        'start_date' => $startDateStr,
                        'end_date' => $endDateStr,
                        'formatted_range' => $startDate->format('M d, Y') . ' — ' . $endDate->format('M d, Y'),
                        'is_consolidated' => is_null($accountId),
                        'selected_account' => 'None',
                    ],
                    'summary' => [
                        'opening_balance' => 0,
                        'total_inflow' => 0,
                        'total_outflow' => 0,
                        'net_cash_flow' => 0,
                        'closing_balance' => 0,
                        'is_reconciled' => true,
                        'reconciliation_diff' => 0,
                    ],
                    'operating_activities' => [
                        'sales_payments' => 0,
                        'sales_refunds' => 0,
                        'expenses' => 0,
                        'cash_overage' => 0,
                        'cash_shortage' => 0,
                        'net_drawer_adjustment' => 0,
                        'net_operating_cash_flow' => 0,
                        'by_payment_method' => [],
                        'by_expense_category' => [],
                    ],
                    'financing_activities' => [
                        'deposits' => 0,
                        'opening_balances' => 0,
                        'transfers_in' => 0,
                        'transfers_out' => 0,
                        'net_internal_transfers' => 0,
                        'net_financing_cash_flow' => 0,
                        'is_consolidated' => is_null($accountId),
                    ],
                    'other_activities' => [
                        'other_credits' => 0,
                        'other_debits' => 0,
                        'net_other_cash_flow' => 0,
                    ],
                    'unlinked_purchases' => [
                        'direct_purchases_paid' => 0,
                        'note' => 'Purchase payments are currently recorded in the Purchase subsystem and not yet connected to the general ledger accounts.',
                    ],
                    'account_breakdown' => [],
                    'charts' => [
                        'daily_trend' => [],
                    ],
                    'data' => [],
                ]);
            }

            // 2. Fetch all post-start-date transactions involving accounts in scope
            $postStartTransactions = AcTransaction::with(['debitAccount', 'creditAccount'])
                ->whereDate('transaction_date', '>=', $startDateStr)
                ->where(function ($q) use ($accountIds) {
                    $q->whereIn('credit_account_id', $accountIds)
                      ->orWhereIn('debit_account_id', $accountIds);
                })
                ->get();

            // In-period transactions
            $inPeriodTransactions = $postStartTransactions->filter(function ($tx) use ($endDateStr) {
                return Carbon::parse($tx->transaction_date)->format('Y-m-d') <= $endDateStr;
            });

            // 3. Compute per-account historical figures
            $accountBreakdown = [];
            $totalOpeningBalance = 0;
            $totalInflow = 0;
            $totalOutflow = 0;
            $totalClosingBalance = 0;

            foreach ($accounts as $acc) {
                $currBal = (float) $acc->balance;
                
                // Credits and Debits for this account on or after start_date
                $creditsPostStart = (float) $postStartTransactions->where('credit_account_id', $acc->id)->sum('credit_amt');
                $debitsPostStart = (float) $postStartTransactions->where('debit_account_id', $acc->id)->sum('debit_amt');
                $netPostStart = $creditsPostStart - $debitsPostStart;

                $openBal = round($currBal - $netPostStart, 2);

                // Credits and Debits in period
                $creditsPeriod = (float) $inPeriodTransactions->where('credit_account_id', $acc->id)->sum('credit_amt');
                $debitsPeriod = (float) $inPeriodTransactions->where('debit_account_id', $acc->id)->sum('debit_amt');
                $netPeriod = round($creditsPeriod - $debitsPeriod, 2);
                $closeBal = round($openBal + $netPeriod, 2);

                $totalOpeningBalance += $openBal;
                $totalInflow += $creditsPeriod;
                $totalOutflow += $debitsPeriod;
                $totalClosingBalance += $closeBal;

                $accountBreakdown[] = [
                    'id' => $acc->id,
                    'account_name' => $acc->account_name,
                    'account_code' => $acc->account_code,
                    'opening_balance' => $openBal,
                    'total_inflow' => $creditsPeriod,
                    'total_outflow' => $debitsPeriod,
                    'net_change' => $netPeriod,
                    'closing_balance' => $closeBal,
                    'current_balance' => $currBal,
                ];
            }

            // Add percentage shares to account breakdown
            foreach ($accountBreakdown as &$item) {
                $item['share_percentage'] = $totalClosingBalance > 0 
                    ? round(($item['closing_balance'] / $totalClosingBalance) * 100, 1) 
                    : 0;
            }
            unset($item);

            // 4. Operating Activities in Period
            // a. Sales Payments
            $salesPaymentsQuery = $inPeriodTransactions->where('transaction_type', 'SALES PAYMENT')
                ->whereIn('credit_account_id', $accountIds);
            $salesPaymentsTotal = (float) $salesPaymentsQuery->sum('credit_amt');

            // Payment Method breakdown for Sales Payments
            $salesPaymentsByMethod = DbSalePayment::whereDate('payment_date', '>=', $startDateStr)
                ->whereDate('payment_date', '<=', $endDateStr)
                ->whereIn('account_id', $accountIds)
                ->select('payment_type', DB::raw('SUM(payment) as total_amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('payment_type')
                ->get()
                ->map(function ($row) {
                    return [
                        'method' => $row->payment_type ?: 'Other',
                        'amount' => (float) $row->total_amount,
                        'count' => (int) $row->count,
                    ];
                });

            // b. Sales Return Refunds
            $salesRefundsQuery = $inPeriodTransactions->where('transaction_type', 'SALES RETURN REFUND')
                ->whereIn('debit_account_id', $accountIds);
            $salesRefundsTotal = (float) $salesRefundsQuery->sum('debit_amt');

            // c. Operating Expenses
            $expensesQuery = $inPeriodTransactions->where('transaction_type', 'EXPENSE')
                ->whereIn('debit_account_id', $accountIds);
            $expensesTotal = (float) $expensesQuery->sum('debit_amt');

            $purchasePaymentsQuery = $inPeriodTransactions->where('transaction_type', 'PURCHASE PAYMENT')
                ->whereIn('debit_account_id', $accountIds);
            $purchasePaymentsTotal = (float) $purchasePaymentsQuery->sum('debit_amt');

            // Expense Category breakdown
            $expensesByCategory = DbExpense::where('delete_bit', 0)
                ->whereDate('expense_date', '>=', $startDateStr)
                ->whereDate('expense_date', '<=', $endDateStr)
                ->whereIn('account_id', $accountIds)
                ->join('db_expense_category', 'db_expense.category_id', '=', 'db_expense_category.id')
                ->select('db_expense_category.category_name', DB::raw('SUM(db_expense.expense_amt) as total_amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('db_expense_category.category_name')
                ->get()
                ->map(function ($row) {
                    return [
                        'category' => $row->category_name,
                        'amount' => (float) $row->total_amount,
                        'count' => (int) $row->count,
                    ];
                });

            // d. Cash Drawer Adjustments (Option C Hybrid)
            $cashOveragesQuery = $inPeriodTransactions->where('transaction_type', 'CASH OVERAGE')
                ->whereIn('credit_account_id', $accountIds);
            $cashOveragesTotal = (float) $cashOveragesQuery->sum('credit_amt');

            $cashShortagesQuery = $inPeriodTransactions->where('transaction_type', 'CASH SHORTAGE')
                ->whereIn('debit_account_id', $accountIds);
            $cashShortagesTotal = (float) $cashShortagesQuery->sum('debit_amt');

            $netDrawerAdjustment = round($cashOveragesTotal - $cashShortagesTotal, 2);

            $netOperatingCashFlow = round(
                $salesPaymentsTotal - $salesRefundsTotal - $expensesTotal - $purchasePaymentsTotal + $netDrawerAdjustment,
                2
            );

            // 5. Financing & Internal Movement Activities in Period
            // a. Account Deposits
            $depositsQuery = $inPeriodTransactions->where('transaction_type', 'DEPOSIT')
                ->whereIn('credit_account_id', $accountIds);
            $depositsTotal = (float) $depositsQuery->sum('credit_amt');

            // b. Opening Balance Creations (if any account was created during this period with initial opening balance)
            $openingBalanceTxQuery = $inPeriodTransactions->where('transaction_type', 'OPENING BALANCE')
                ->whereIn('credit_account_id', $accountIds);
            $openingBalanceTxTotal = (float) $openingBalanceTxQuery->sum('credit_amt');

            // c. Transfers In and Out
            $transfersInQuery = $inPeriodTransactions->where('transaction_type', 'TRANSFER')
                ->whereIn('credit_account_id', $accountIds);
            $transfersInTotal = (float) $transfersInQuery->sum('credit_amt');

            $transfersOutQuery = $inPeriodTransactions->where('transaction_type', 'TRANSFER')
                ->whereIn('debit_account_id', $accountIds);
            $transfersOutTotal = (float) $transfersOutQuery->sum('debit_amt');

            $isConsolidated = is_null($accountId);
            $netInternalTransfers = round($transfersInTotal - $transfersOutTotal, 2);

            $netFinancingCashFlow = round(
                $depositsTotal + $openingBalanceTxTotal + $netInternalTransfers,
                2
            );

            // 6. Other / Unclassified Transactions (if any)
            $knownTypes = ['SALES PAYMENT', 'SALES RETURN REFUND', 'EXPENSE', 'PURCHASE PAYMENT', 'CASH OVERAGE', 'CASH SHORTAGE', 'DEPOSIT', 'OPENING BALANCE', 'TRANSFER'];
            $otherCreditsQuery = $inPeriodTransactions->whereNotIn('transaction_type', $knownTypes)
                ->whereIn('credit_account_id', $accountIds);
            $otherCreditsTotal = (float) $otherCreditsQuery->sum('credit_amt');

            $otherDebitsQuery = $inPeriodTransactions->whereNotIn('transaction_type', $knownTypes)
                ->whereIn('debit_account_id', $accountIds);
            $otherDebitsTotal = (float) $otherDebitsQuery->sum('debit_amt');

            $netOtherCashFlow = round($otherCreditsTotal - $otherDebitsTotal, 2);

            // 7. Summary Totals & Reconciliation
            $consolidatedTotalInflow = round($salesPaymentsTotal + $cashOveragesTotal + $depositsTotal + $openingBalanceTxTotal + $transfersInTotal + $otherCreditsTotal, 2);
            $consolidatedTotalOutflow = round($salesRefundsTotal + $expensesTotal + $purchasePaymentsTotal + $cashShortagesTotal + $transfersOutTotal + $otherDebitsTotal, 2);
            
            $netCashFlow = round($netOperatingCashFlow + $netFinancingCashFlow + $netOtherCashFlow, 2);
            
            // Exact reconciliation check:
            // Opening Balance + Net Cash Flow = Closing Balance
            $calculatedClosing = round($totalOpeningBalance + $netCashFlow, 2);
            $reconciliationDiff = round($calculatedClosing - $totalClosingBalance, 2);
            $isReconciled = (abs($reconciliationDiff) < 0.01);

            // 8. Daily Trend Chart Data
            $dateRange = new \DatePeriod(
                $startDate->copy()->startOfDay(),
                new \DateInterval('P1D'),
                $endDate->copy()->addDay()->startOfDay()
            );

            $dailyGroups = $inPeriodTransactions->groupBy(function ($tx) {
                return Carbon::parse($tx->transaction_date)->format('Y-m-d');
            });

            $dailyTrend = [];
            $runningBalance = $totalOpeningBalance;

            foreach ($dateRange as $dt) {
                $dStr = $dt->format('Y-m-d');
                $txs = $dailyGroups->get($dStr, collect());

                $dayInflow = 0;
                $dayOutflow = 0;

                foreach ($txs as $t) {
                    if (in_array($t->credit_account_id, $accountIds)) {
                        $dayInflow += (float) $t->credit_amt;
                    }
                    if (in_array($t->debit_account_id, $accountIds)) {
                        $dayOutflow += (float) $t->debit_amt;
                    }
                }

                $dayNet = round($dayInflow - $dayOutflow, 2);
                $runningBalance = round($runningBalance + $dayNet, 2);

                $dailyTrend[] = [
                    'date' => $dStr,
                    'formatted_date' => $dt->format('M d, Y'),
                    'inflow' => round($dayInflow, 2),
                    'outflow' => round($dayOutflow, 2),
                    'net' => $dayNet,
                    'running_balance' => $runningBalance,
                ];
            }

            // 9. Detailed Transactions in Period (for drill-down table)
            $detailedTransactions = $inPeriodTransactions->sortByDesc('transaction_date')->values()->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'date' => Carbon::parse($tx->transaction_date)->format('Y-m-d'),
                    'type' => $tx->transaction_type,
                    'payment_code' => $tx->payment_code ?: ('TX-' . str_pad($tx->id, 5, '0', STR_PAD_LEFT)),
                    'debit_account' => $tx->debitAccount ? $tx->debitAccount->account_name : null,
                    'credit_account' => $tx->creditAccount ? $tx->creditAccount->account_name : null,
                    'debit_amt' => (float) $tx->debit_amt,
                    'credit_amt' => (float) $tx->credit_amt,
                    'note' => $tx->note ?? '',
                ];
            });

            // 10. Informational: Direct Purchase Paid Total during period (Unlinked to accounts)
            $purchasesPaidTotal = (float) DbPurchase::whereDate('purchase_date', '>=', $startDateStr)
                ->whereDate('purchase_date', '<=', $endDateStr)
                ->sum('paid_amount');

            return response()->json([
                'status' => 'success',
                'period' => [
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'formatted_range' => $startDate->format('M d, Y') . ' — ' . $endDate->format('M d, Y'),
                    'is_consolidated' => $isConsolidated,
                    'selected_account' => $accountId ? ($accounts->first()->account_name ?? 'Specific Account') : 'All Accounts (Consolidated)',
                ],
                'summary' => [
                    'opening_balance' => round($totalOpeningBalance, 2),
                    'total_inflow' => $consolidatedTotalInflow,
                    'total_outflow' => $consolidatedTotalOutflow,
                    'net_cash_flow' => $netCashFlow,
                    'closing_balance' => round($totalClosingBalance, 2),
                    'is_reconciled' => $isReconciled,
                    'reconciliation_diff' => $reconciliationDiff,
                ],
                'operating_activities' => [
                    'sales_payments' => $salesPaymentsTotal,
                    'sales_refunds' => $salesRefundsTotal,
                    'expenses' => $expensesTotal,
                    'purchase_payments' => $purchasePaymentsTotal,
                    'cash_overage' => $cashOveragesTotal,
                    'cash_shortage' => $cashShortagesTotal,
                    'net_drawer_adjustment' => $netDrawerAdjustment,
                    'net_operating_cash_flow' => $netOperatingCashFlow,
                    'by_payment_method' => $salesPaymentsByMethod,
                    'by_expense_category' => $expensesByCategory,
                ],
                'financing_activities' => [
                    'deposits' => $depositsTotal,
                    'opening_balances' => $openingBalanceTxTotal,
                    'transfers_in' => $transfersInTotal,
                    'transfers_out' => $transfersOutTotal,
                    'net_internal_transfers' => $netInternalTransfers,
                    'net_financing_cash_flow' => $netFinancingCashFlow,
                    'is_consolidated' => $isConsolidated,
                ],
                'other_activities' => [
                    'other_credits' => $otherCreditsTotal,
                    'other_debits' => $otherDebitsTotal,
                    'net_other_cash_flow' => $netOtherCashFlow,
                ],
                'unlinked_purchases' => [
                    'direct_purchases_paid' => $purchasesPaidTotal,
                    'note' => 'Purchase payments are currently recorded in the Purchase subsystem and not yet connected to the general ledger accounts.',
                ],
                'account_breakdown' => $accountBreakdown,
                'charts' => [
                    'daily_trend' => $dailyTrend,
                ],
                'data' => $detailedTransactions,
            ]);

        } catch (\Exception $e) {
            Log::error('Cash Flow Statement Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating cash flow statement: ' . $e->getMessage()
            ], 500);
        }
    }
}
