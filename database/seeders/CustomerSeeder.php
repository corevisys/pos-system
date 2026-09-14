<?php

namespace Database\Seeders;

use App\Services\CustomerIdentityResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'customer_name' => 'John Doe',
                'mobile' => '01716755768',
                'email' => 'john@example.com',
                'address' => 'Mirpur, Dhaka',
                'city' => 'Dhaka',
            ],
            [
                'customer_name' => 'Abir Hasan',
                'mobile' => '01716755768',
                'email' => 'abir@example.com',
                'address' => 'Agrabad, Chittagong',
                'city' => 'Chittagong',
            ],
            [
                'customer_name' => 'Walk-in Customer',
                'mobile' => '01716755768',
                'email' => 'walkin@example.com',
                'address' => 'Store Front',
                'city' => 'Dhaka',
            ],
        ];

        foreach ($customers as $index => $customer) {
            // Phase 5 — demo rows go through the shared-identity resolver so seed data
            // never produces orphaned or duplicated identities.
            $identity = CustomerIdentityResolver::resolveOrCreate($customer['mobile'], [
                'name' => $customer['customer_name'],
                'email' => $customer['email'],
            ]);

            DB::table('db_customers')->updateOrInsert(
                ['email' => $customer['email']],
                [
                    'store_id' => 1,
                    'customer_code' => 'CUS' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'customer_identity_id' => $identity?->id,
                    'customer_name' => $customer['customer_name'],
                    'mobile' => $customer['mobile'],
                    'address' => $customer['address'],
                    'city' => $customer['city'],
                    'country_id' => 1, // Bangladesh
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'delete_bit' => 0,
                ]
            );
        }
    }
}
