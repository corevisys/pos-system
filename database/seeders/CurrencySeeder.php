<?php

namespace Database\Seeders;

use App\Models\DbCurrency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['currency_name' => 'US Dollar', 'currency_code' => 'USD', 'symbol' => '$', 'status' => 0],
            ['currency_name' => 'TAKA', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1],
            ['currency_name' => 'Euro', 'currency_code' => 'EUR', 'symbol' => '€', 'status' => 0],
            ['currency_name' => 'Indian Rupee', 'currency_code' => 'INR', 'symbol' => '₹', 'status' => 0],
        ];

        foreach ($currencies as $curr) {
            DbCurrency::updateOrCreate(
                ['currency_code' => $curr['currency_code']],
                [
                    'currency_name' => $curr['currency_name'],
                    'currency' => $curr['currency_name'],
                    'symbol' => $curr['symbol'],
                    'status' => $curr['status']
                ]
            );
        }
    }
}
