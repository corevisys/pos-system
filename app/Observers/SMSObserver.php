<?php

namespace App\Observers;

use App\Models\DbSale;
use App\SMS\Services\SmsTriggerService;

class SMSObserver
{
    /**
     * Handle the DbSale "created" event.
     */
    public function created(DbSale $sale): void
    {
        try {
            if ($sale->customer_id) {
                app(SmsTriggerService::class)->trigger('InvoiceCreated', $sale);
            }
        } catch (\Throwable $e) {
            \Log::error('SMS Dispatch Error in Observer: ' . $e->getMessage());
        }
    }
}

