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
            // The 3-letter prefix code generation collides for distinct brands that
            // share a prefix (e.g. "Gigabyte" and "Gigasonic" both -> "GIG"). Since the
            // per-store composite unique index (store_id, brand_code) added by
            // 2026_09_08_000001 now enforces code uniqueness at the DB level, derive a
            // collision-free code: use the prefix, and append a numeric suffix when the
            // code is already taken within store 1.
            $baseCode = strtoupper(substr($brand, 0, 3));
            $brandCode = $baseCode;
            $suffix = 2;
            // Exclude this brand's own row so re-seeding never re-suffixes a code
            // that the brand itself already owns (keeps codes stable across seeds).
            while (DB::table('db_brands')
                ->where('store_id', 1)
                ->where('brand_code', $brandCode)
                ->where('brand_name', '!=', $brand)
                ->exists()
            ) {
                $brandCode = $baseCode . $suffix;
                $suffix++;
            }

            DB::table('db_brands')->updateOrInsert(
                ['brand_name' => $brand],
                [
                    'brand_code' => $brandCode,
                    'store_id' => 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
