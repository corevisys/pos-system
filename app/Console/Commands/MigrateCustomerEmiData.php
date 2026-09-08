<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DbCustomer;
use App\Models\CustomerGuardian;
use App\Models\CustomerGuarantor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateCustomerEmiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-customer-emi-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Guardian and Guarantor data from db_customers to relational tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Customer EMI Data Migration...');
        
        $customers = DbCustomer::all();
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($customers as $customer) {
                // Guardian Data Migration
                if ($customer->g_name || $customer->g_mobile) {
                    $guardianExists = CustomerGuardian::where('customer_id', $customer->id)->exists();
                    if (!$guardianExists) {
                        CustomerGuardian::create([
                            'customer_id' => $customer->id,
                            'name' => $customer->g_name,
                            'relationship' => $customer->g_relationship,
                            'mobile' => $customer->g_mobile,
                            'nid_front' => $customer->g_nid_front,
                            'nid_back' => $customer->g_nid_back,
                            'photo' => $customer->g_photo,
                        ]);
                    }
                }

                // Guarantor Data Migration
                if ($customer->gr_name || $customer->gr_mobile) {
                    $guarantorExists = CustomerGuarantor::where('customer_id', $customer->id)->exists();
                    if (!$guarantorExists) {
                        CustomerGuarantor::create([
                            'customer_id' => $customer->id,
                            'name' => $customer->gr_name,
                            'father_name' => $customer->gr_father_name,
                            'address' => $customer->gr_address,
                            'mobile' => $customer->gr_mobile,
                            'occupation' => $customer->gr_occupation,
                            'monthly_income' => $customer->gr_monthly_income,
                            'photo' => $customer->gr_photo,
                            'nid_front' => $customer->gr_nid_front,
                            'nid_back' => $customer->gr_nid_back,
                            'job_id' => $customer->gr_job_id,
                        ]);
                    }
                }
                $count++;
            }

            DB::commit();
            $this->info("Successfully processed $count customers.");
            Log::info("Customer EMI normalization completed. $count records processed.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Migration failed: ' . $e->getMessage());
            Log::error('Customer EMI normalization failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
