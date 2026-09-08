<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            'HP', 'Dell', 'Asus', 'Lenovo', 'Acer', 'MSI', 'Apple', 'Intel', 'AMD', 'Gigabyte', 
            'Corsair', 'Kingston', 'Samsung', 'WD', 'Antec', 'Cooler Master', 'LG', 'AOC', 
            'Gigasonic', 'Canon', 'Epson', 'Brother', 'Pantum', 'Logitech', 'A4Tech', 
            'Redragon', 'Fantech', 'Sony', 'TP-Link', 'Tenda', 'Sandisk', 'Havit', 
            'Power Guard', 'APC', 'MaxGreen', 'DigitalX', 'Marsriva'
        ];

        foreach ($brands as $brand) {
            DB::table('db_brands')->updateOrInsert(
                ['brand_name' => $brand],
                [
                    'brand_code' => strtoupper(substr($brand, 0, 3)),
                    'store_id' => 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
