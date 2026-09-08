<?php

namespace Database\Seeders;

use App\Models\DbPaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentTypes = [            
            ['payment_type' => 'CASH', 'status' => 1],
            ['payment_type' => 'EMI', 'status' => 1],
            ['payment_type' => 'Bkash', 'status' => 1],
            ['payment_type' => 'Nagad', 'status' => 1],
            ['payment_type' => 'Rocket', 'status' => 1],
            ['payment_type' => 'Upay', 'status' => 1],
            ['payment_type' => 'CARD', 'status' => 1],
            ['payment_type' => 'BANK TRANSFER', 'status' => 1],
        ];

        foreach ($paymentTypes as $type) {
            DbPaymentType::firstOrCreate(
                ['payment_type' => $type['payment_type']],
                ['store_id' => 1, 'status' => $type['status']]
            );
        }
    }
}
