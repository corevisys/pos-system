<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DbEmiSchedule;
use App\Models\SmsAutoRule;
use App\SMS\Services\SmsService;
use Carbon\Carbon;

class EmiReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        
        // Find installments due tomorrow that are not fully paid
        $schedules = DbEmiSchedule::with(['emiSale.sale.customer', 'emiSale.sale.store'])
            ->where('due_date', $tomorrow)
            ->where('status', '!=', 'Paid')
            ->get();

        $rule = SmsAutoRule::where('event_type', 'EmiReminder')->where('status', 1)->first();
        if (!$rule) return;

        $smsService = new SmsService();

        foreach ($schedules as $schedule) {
            $customer = $schedule->emiSale->sale->customer;
            $store = $schedule->emiSale->sale->store;

            if (!$customer || !$customer->mobile) continue;

            $message = $smsService->replaceVariables($rule->template->content, [
                'customer_name' => $customer->customer_name,
                'emi_amount' => $schedule->amount,
                'emi_date' => $schedule->due_date,
                'sales_id' => $schedule->emiSale->sale->sales_code,
                'store_name' => $store->store_name ?? 'Our Store',
            ]);

            SendSingleSmsJob::dispatch($customer->mobile, $message, [
                'rule_id' => $rule->id,
                'customer_id' => $customer->id,
                'store_id' => $store->id ?? 1
            ])->onQueue('sms');
        }
    }
}
