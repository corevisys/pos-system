<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $divisions = [
            ['state' => 'Dhaka', 'state_code' => 'DHA'],
            ['state' => 'Chittagong', 'state_code' => 'CHI'],
            ['state' => 'Rajshahi', 'state_code' => 'RAJ'],
            ['state' => 'Khulna', 'state_code' => 'KHU'],
            ['state' => 'Barisal', 'state_code' => 'BAR'],
            ['state' => 'Sylhet', 'state_code' => 'SYL'],
            ['state' => 'Rangpur', 'state_code' => 'RAN'],
            ['state' => 'Mymensingh', 'state_code' => 'MYM'],
        ];

        foreach ($divisions as $division) {
            DB::table('db_states')->updateOrInsert(
                ['state' => $division['state']],
                [
                    'store_id' => 1,
                    'state_code' => $division['state_code'],
                    'country_id' => 1,
                    'country' => 'Bangladesh',
                    'country_code' => 'BD',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
