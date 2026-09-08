<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\SMS\Services\SmsTriggerService;
use App\Models\SmsAutoRule;
use App\Models\DbEmiSchedule;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbCoupon;

class ProcessScheduledSmsRules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:process-scheduled-rules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled SMS automation rules (EMI, Birthdays, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(SmsTriggerService $smsTriggerService)
    {
        $this->info('Starting scheduled SMS rule processing...');

        $rules = SmsAutoRule::where('is_active', true)
            ->where('trigger_time', '!=', 'immediate')
            ->get();

        foreach ($rules as $rule) {
            $this->info("Processing rule: {$rule->rule_name} ({$rule->event_type})");
            $this->processScheduledRule($rule, $smsTriggerService);
        }

        $this->info('Scheduled SMS processing completed.');
    }

    protected function processScheduledRule($rule, $smsTriggerService)
    {
        $today = now()->toDateString();
        $offset = $rule->days_offset;

        switch ($rule->event_type) {
            case 'EmiDue':
            case 'EmiOverdue':
                // For EMI, we look at the due_date in DbEmiSchedule
                // If offset is -1, it means 1 day before due_date. 
                // Target Date + Offset = Today  => Target Date = Today - Offset
                $targetDate = now()->subDays($offset)->toDateString();
                $items = DbEmiSchedule::where('due_date', $targetDate)
                    ->where('status', '!=', 'Paid')
                    ->get();
                break;

            case 'CustomerBirthday':
                // Offset is usually 0. Birthday matches today's month and day.
                // We'll ignore the year.
                $month = now()->subDays($offset)->format('m');
                $day = now()->subDays($offset)->format('d');
                $items = DbCustomer::whereMonth('dob', $month)
                    ->whereDay('dob', $day)
                    ->get();
                break;

            case 'ServiceDueReminder':
                // Assuming Sale has service_date
                $targetDate = now()->subDays($offset)->toDateString();
                $items = DbSale::where('service_date', $targetDate)->get();
                break;

            case 'CouponExpiry':
                $targetDate = now()->subDays($offset)->toDateString();
                $items = DbCoupon::where('expire_date', $targetDate)->get();
                break;

            default:
                $items = collect();
                break;
        }

        foreach ($items as $item) {
            // Respect cooldown if needed
            // For now, we manually trigger
            $smsTriggerService->processRule($rule, $item);
        }
    }
}
