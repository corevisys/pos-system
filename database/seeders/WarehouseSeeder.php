<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'store_id' => 1,
                'warehouse_type' => 'System',
                'warehouse_name' => 'Main Warehouse',
                'mobile' => '01716755768',
                'email' => 'warehouse@example.com',
                'status' => 1,
                'created_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'store_id' => 1,
                'warehouse_type' => 'System',
                'warehouse_name' => 'Store Front',
                'mobile' => '01716755768',
                'email' => 'storefront@example.com',
                'status' => 1,
                'created_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($warehouses as $warehouse) {
            DB::table('db_warehouse')->updateOrInsert(
                ['warehouse_name' => $warehouse['warehouse_name'], 'store_id' => $warehouse['store_id']],
                $warehouse
            );
        }
    }
}
