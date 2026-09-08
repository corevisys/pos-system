<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_name' => 'Star Tech & Engineering Ltd',
                'mobile' => '09678002003',
                'email' => 'info@startech.com.bd',
                'address' => 'Multiplan Center, Dhaka',
                'city' => 'Dhaka',
            ],
            [
                'supplier_name' => 'Computer Village',
                'mobile' => '01755655655',
                'email' => 'sales@computervillage.com.bd',
                'address' => 'Agrabad, Chittagong',
                'city' => 'Chittagong',
            ],
            [
                'supplier_name' => 'Ryans IT Care',
                'mobile' => '09638442121',
                'email' => 'info@ryansit.com',
                'address' => 'IDB Bhaban, Dhaka',
                'city' => 'Dhaka',
            ],
        ];

        foreach ($suppliers as $index => $supplier) {
            DB::table('db_suppliers')->updateOrInsert(
                ['email' => $supplier['email']],
                [
                    'store_id' => 1,
                    'supplier_code' => 'SUP' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'supplier_name' => $supplier['supplier_name'],
                    'mobile' => $supplier['mobile'],
                    'address' => $supplier['address'],
                    'city' => $supplier['city'],
                    'country_id' => 1, // Bangladesh
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
