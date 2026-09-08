<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Laptop', 'Processor', 'Motherboard', 'RAM', 'SSD', 'Storage', 'Casing', 
            'Power Supply', 'Monitor', 'Printer', 'Mouse', 'Keyboard', 'Headphone', 
            'Networking', 'Webcam', 'UPS'
        ];

        foreach ($categories as $category) {
            DB::table('db_category')->updateOrInsert(
                ['category_name' => $category],
                [
                    'category_code' => strtoupper(substr($category, 0, 3)),
                    'store_id' => 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
