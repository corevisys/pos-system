<?php

namespace Database\Seeders;

use App\Models\DbCustomer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EndToEndCouponSeeder extends Seeder
{
    public function run(): void
    {
        $customer = DbCustomer::where('email', 'john@example.com')->first();

        if (!$customer) {
            return;
        }

        foreach (['E2EJOHN01', 'E2EJOHN02', 'E2EJOHN03', 'E2EJOHN04', 'E2EJOHN05'] as $code) {
            DB::table('db_customer_coupons')->updateOrInsert(
                ['code' => $code],
                [
                    'store_id' => $customer->store_id ?? 1,
                    'customer_id' => $customer->id,
                    'name' => '20% Off Verification Voucher ' . $code,
                    'description' => 'Seeded customer coupon for end-to-end verification.',
                    'value' => 20.00,
                    'type' => 'Percentage',
                    'expire_date' => now()->addYear()->toDateString(),
                    'status' => 1,
                    'created_by' => 1,
                    'created_date' => now()->toDateString(),
                    'created_time' => now()->toTimeString(),
                    'system_name' => gethostname(),
                    'system_ip' => '127.0.0.1',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}