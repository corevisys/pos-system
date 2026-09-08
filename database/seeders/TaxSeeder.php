<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            // VAT
            ['tax_name' => 'VAT 15% (Standard)', 'tax' => 15.00],
            ['tax_name' => 'VAT 7.5% (Some sectors)', 'tax' => 7.50],
            ['tax_name' => 'VAT 5% (Reduced rate)', 'tax' => 5.00],
            ['tax_name' => 'VAT 0% (Zero Rated)', 'tax' => 0.00],
            ['tax_name' => 'VAT Exempt', 'tax' => 0.00],

            // Turnover Tax
            ['tax_name' => 'Turnover Tax 4%', 'tax' => 4.00],

            // Supplementary Duty (SD)
            ['tax_name' => 'SD 10%', 'tax' => 10.00],
            ['tax_name' => 'SD 20%', 'tax' => 20.00],
            ['tax_name' => 'SD 30%', 'tax' => 30.00],

            // Withholding Tax (WHT)
            ['tax_name' => 'WHT 5%', 'tax' => 5.00],
            ['tax_name' => 'WHT 10%', 'tax' => 10.00],
        ];

        foreach ($taxes as $tax) {
            DB::table('db_tax')->updateOrInsert(
                ['tax_name' => $tax['tax_name']],
                [
                    'tax' => $tax['tax'],
                    'store_id' => 1,
                    'group_bit' => 0,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
