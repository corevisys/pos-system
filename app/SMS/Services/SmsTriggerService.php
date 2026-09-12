<?php

namespace App\SMS\Services;

use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbCustomer;
use Illuminate\Support\Facades\Log;

class SmsTriggerService
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Trigger an automated SMS event.
     */
    public function trigger(string $eventType, $model)
    {
        try {
            // Resolve the rule set for the TRIGGERING MODEL's store, not the acting
            // request's — a store-2 sale must evaluate store-2's rules (and thereby
            // send through store-2's provider/template). Falling back to
            // current_store_id() keeps CLI/queue callers working.
            $storeId = is_object($model) && !empty($model->store_id)
                ? (int) $model->store_id
                : null;

            $rules = RuleResolverService::resolve($eventType, $storeId);

            if ($rules->isEmpty()) {
                return;
            }

            foreach ($rules as $rule) {
                $this->processRule($rule, $model);
            }
        } catch (\Exception $e) {
            Log::error("SMS Trigger Error [{$eventType}]: " . $e->getMessage());
        }
    }

    /**
     * Check if item stock levels are below threshold and trigger alerts.
     */
    public function checkStockAlerts($itemId, $warehouseId = null)
    {
        $item = \App\Models\DbItem::find($itemId);
        if (!$item || $item->alert_qty <= 0) {
            return;
        }

        // Global Low Stock
        if ($item->stock <= $item->alert_qty) {
            $this->trigger('LowStock', $item);
        }

        // Warehouse Specific Low Stock
        if ($warehouseId) {
            $whItem = \App\Models\DbWarehouseItem::where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->first();
            
            if ($whItem && $whItem->available_qty <= $item->alert_qty) {
                $this->trigger('WarehouseLowStock', $whItem);
            }
        }
    }

    /**
     * Process a specific rule and send SMS.
     */
    public function processRule($rule, $model)
    {
        // For scheduled rules, we skip the immediate check if called by the cron job
        // But for safety, we'll just expose it and let the caller decide.
        
        $data = $this->mapVariables($rule->event_type, $model);
        $phone = $this->resolvePhone($rule->event_type, $model);

        if (!$phone) {
            return;
        }

        // A rule without its own store is a data-integrity error, not something to
        // silently route to Store 1. Every rule is created per store (the migration
        // duplicates them per active store), so a null here means a bad row.
        if (empty($rule->store_id)) {
            Log::error('SMS rule has no store_id — refusing to send.', [
                'rule_id' => $rule->id ?? null,
                'event_type' => $rule->event_type ?? null,
            ]);
            return;
        }

        if (!$rule->template) {
            Log::error('SMS rule has no resolvable template for its store — refusing to send.', [
                'rule_id' => $rule->id,
                'store_id' => $rule->store_id,
                'template_id' => $rule->template_id,
            ]);
            return;
        }

        $content = $this->smsService->replaceVariables($rule->template->content, $data);

        $this->smsService->sendSingle($phone, $content, [
            'store_id' => (int) $rule->store_id,
            'rule_id' => $rule->id,
            'customer_id' => $this->resolveCustomerId($rule->event_type, $model),
        ]);

        $rule->update(['last_executed_at' => now()]);
    }

    protected function resolveCustomerId($eventType, $model)
    {
        if (isset($model->customer_id)) return $model->customer_id;
        if (isset($model->customer)) return $model->customer->id;
        return null;
    }

    /**
     * Map model data to template variables based on event type.
     */
    protected function mapVariables(string $eventType, $model): array
    {
        $data = [];

        switch ($eventType) {
            case 'InvoiceCreated':
                if ($model instanceof DbSale) {
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'customer_name' => $model->customer->customer_name ?? 'Customer',
                        'invoice_no' => $model->sales_code,
                        'total_amount' => number_format($model->grand_total, 2),
                        'due_amount' => number_format($model->grand_total - $model->paid_amount, 2),
                        'store_name' => $store ? ($store->store_name ?? 'Our Store') : 'Our Store',
                    ];
                }
                break;
            
            case 'CustomerAdded':
                if ($model instanceof DbCustomer) {
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'customer_name' => $model->customer_name,
                        'customer_code' => $model->customer_code,
                        'store_name' => $store ? ($store->store_name ?? 'Our Store') : 'Our Store',
                    ];
                }
                break;

            case 'PaymentReceived':
                if ($model instanceof \App\Models\DbSalePayment) {
                    $sale = $model->sale;
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'customer_name' => $sale->customer->customer_name ?? 'Customer',
                        'payment_amount' => number_format($model->payment, 2),
                        'invoice_no' => $sale->sales_code ?? 'N/A',
                        'store_name' => $store ? ($store->store_name ?? 'Our Store') : 'Our Store',
                    ];
                }
                break;
            
            case 'EmiPaymentConfirmation':
                if ($model instanceof \App\Models\DbEmiSchedule) {
                    $sale = $model->emiSale->sale;
                    $store = DbStore::find($model->store_id ?? 1);
                    
                    $nextInstallment = \App\Models\DbEmiSchedule::where('emi_sale_id', $model->emi_sale_id)
                        ->where('status', '!=', 'Paid')
                        ->where('installment_no', '>', $model->installment_no)
                        ->orderBy('installment_no', 'asc')
                        ->first();

                    $data = [
                        'customer_name' => $sale->customer->customer_name ?? 'Customer',
                        'payment_amount' => number_format($model->amount, 2),
                        'paid_amount' => number_format($model->paid_amount, 2),
                        'invoice_no' => $sale->sales_code ?? 'N/A',
                        'store_name' => $store ? ($store->store_name ?? 'Our Store') : 'Our Store',
                        'next_due_date' => $nextInstallment->due_date ?? 'Fully Paid',
                    ];
                }
                break;

            case 'EmiCompletion':
                if ($model instanceof \App\Models\DbEmiSale) {
                    $sale = $model->sale;
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'customer_name' => $sale->customer->customer_name ?? 'Customer',
                        'invoice_no' => $sale->sales_code ?? 'N/A',
                        'store_name' => $store->store_name ?? 'Our Store',
                    ];
                }
                break;
            
            case 'SalesReturnConfirmation':
                if ($model instanceof \App\Models\DbSalesReturn) {
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'customer_name' => $model->customer->customer_name ?? 'Customer',
                        'invoice_no' => $model->sale->sales_code ?? 'N/A',
                        'return_no' => $model->return_code ?? 'N/A',
                        'amount' => number_format($model->grand_total, 2),
                        'grand_total' => number_format($model->grand_total, 2),
                        'paid_amount' => number_format($model->paid_amount ?? 0, 2),
                        'store_name' => $store->store_name ?? 'Our Store',
                    ];
                }
                break;

            case 'PurchaseCreated':
                if ($model instanceof \App\Models\DbPurchase) {
                    $store = DbStore::find($model->store_id ?? 1);
                    $data = [
                        'supplier_name' => $model->supplier->supplier_name ?? 'Supplier',
                        'purchase_no' => $model->purchase_code,
                        'total' => number_format($model->grand_total, 2),
                        'store_name' => $store->store_name ?? 'Our Store',
                    ];
                }
                break;

            case 'LowStock':
            case 'WarehouseLowStock':
                if ($model instanceof \App\Models\DbItem || $model instanceof \App\Models\DbWarehouseItem) {
                    $item = ($model instanceof \App\Models\DbItem) ? $model : $model->item;
                    $warehouse = ($model instanceof \App\Models\DbWarehouseItem) ? $model->warehouse : null;
                    $data = [
                        'item_name' => $item->item_name,
                        'warehouse_name' => $warehouse->warehouse_name ?? 'Global',
                        'current_qty' => ($model instanceof \App\Models\DbItem) ? $model->stock : $model->available_qty,
                    ];
                }
                break;

            case 'StockAdjustmentAlert':
                if ($model instanceof \App\Models\DbStockAdjustmentItems) {
                    $data = [
                        'item_name' => $model->item->item_name ?? 'Item',
                        'adjusted_qty' => $model->adjustment_qty,
                    ];
                }
                break;

            case 'EmiDue':
            case 'EmiOverdue':
                if ($model instanceof \App\Models\DbEmiSchedule) {
                    $sale = $model->emiSale->sale;
                    $data = [
                        'customer_name' => $sale->customer->customer_name ?? 'Customer',
                        'emi_amount' => number_format($model->amount, 2),
                        'invoice_no' => $sale->sales_code ?? 'N/A',
                        'due_date' => $model->due_date,
                    ];
                }
                break;

            case 'ServiceDueReminder':
                if ($model instanceof DbSale) {
                    $data = [
                        'customer_name' => $model->customer->customer_name ?? 'Customer',
                        'invoice_no' => $model->sales_code,
                        'service_date' => $model->service_date ?? 'N/A',
                        'store_name' => $model->store->store_name ?? 'Our Store',
                    ];
                }
                break;

            case 'CustomerBirthday':
                if ($model instanceof DbCustomer) {
                    $data = [
                        'customer_name' => $model->customer_name,
                        'store_name' => 'Our Store',
                    ];
                }
                break;

            case 'FestivalCampaign':
                $data = $model; 
                break;

            case 'CouponExpiry':
                if ($model instanceof \App\Models\DbCoupon) {
                    $data = [
                        'coupon_code' => $model->code,
                        'expiry_date' => $model->expire_date,
                    ];
                }
                break;

            case 'EodSummary':
                $data = $model;
                break;

            case 'LargeTransactionAlert':
                if ($model instanceof DbSale) {
                    $data = [
                        'amount' => number_format($model->grand_total, 2),
                        'invoice_no' => $model->sales_code,
                    ];
                }
                break;

            case 'BackupCompletedAlert':
                $data = [
                    'date_time' => now()->toDateTimeString(),
                ];
                break;
            
            // Add more cases here as needed
        }

        return $data;
    }

    /**
     * Resolve the recipient phone number based on event type and model.
     */
    protected function resolvePhone(string $eventType, $model): ?string
    {
        switch ($eventType) {
            case 'InvoiceCreated':
                return $model->customer->mobile ?? $model->customer->phone ?? null;
            
            case 'CustomerAdded':
                return $model->mobile ?? $model->phone ?? null;

            case 'PaymentReceived':
                return $model->customer->mobile ?? $model->customer->phone ?? null;

            case 'EmiPaymentConfirmation':
                $sale = $model->emiSale->sale;
                return $sale->customer->mobile ?? $sale->customer->phone ?? null;

            case 'EmiCompletion':
                $sale = $model->sale;
                return $sale->customer->mobile ?? $sale->customer->phone ?? null;

            case 'SalesReturnConfirmation':
                return $model->customer->mobile ?? $model->customer->phone ?? null;

            case 'PurchaseCreated':
                return $model->supplier->mobile ?? $model->supplier->phone ?? null;

            case 'CustomerBirthday':
                return $model->mobile ?? $model->phone ?? null;

            case 'EmiDue':
            case 'EmiOverdue':
                $sale = $model->emiSale->sale;
                return $sale->customer->mobile ?? $sale->customer->phone ?? null;

            case 'ServiceDueReminder':
            case 'LargeTransactionAlert':
                return $model->customer->mobile ?? $model->customer->phone ?? null;

            case 'CouponExpiry':
                // Coupon expiry usually goes to the store admin so they can decide on re-engagement
                return $this->resolveStoreContact($model);

            case 'LowStock':
            case 'WarehouseLowStock':
            case 'StockAdjustmentAlert':
            case 'EodSummary':
            case 'BackupCompletedAlert':
                // These usually go to the admin/store phone — resolve the RELEVANT
                // store's contact (the model's own store_id, else the acting
                // store, else the first store) rather than always store #1.
                return $this->resolveStoreContact($model);
        }

        return null;
    }

    /**
     * Resolve the store contact (mobile ?? phone) for admin-type alert events.
     *
     * Preference order for the store:
     * 1. The triggering model's own store_id (DbCoupon/DbItem/DbWarehouseItem/
     *    DbStockAdjustmentItems all carry store_id), so a Store-B alert routes to
     *    Store B's contact, never silently to Store 1.
     * 2. The acting store (authenticated user's store) via store_settings().
     * 3. The first db_store row (legacy single-store behaviour).
     */
    protected function resolveStoreContact($model): ?string
    {
        $storeId = null;

        if (is_object($model) && !empty($model->store_id)) {
            $storeId = (int) $model->store_id;
        } elseif (function_exists('store_settings')) {
            $acting = store_settings();
            if ($acting && !empty($acting->id)) {
                $storeId = (int) $acting->id;
            }
        }

        $store = $storeId
            ? DbStore::find($storeId)
            : DbStore::query()->orderBy('id')->first();

        return $store ? ($store->mobile ?? $store->phone ?? null) : null;
    }
}
