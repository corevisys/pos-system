<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['unit_name' => 'Piece', 'description' => 'Piece'],
            ['unit_name' => 'Kilogram', 'description' => 'Kilogram'],
            ['unit_name' => 'Gram', 'description' => 'Gram'],
            ['unit_name' => 'Litre', 'description' => 'Litre'],
            ['unit_name' => 'Millilitre', 'description' => 'Millilitre'],
            ['unit_name' => 'Box', 'description' => 'Box'],
            ['unit_name' => 'Packet', 'description' => 'Packet'],
            ['unit_name' => 'Dozen', 'description' => 'Dozen'],
        ];

        foreach ($units as $unit) {
            DB::table('db_units')->updateOrInsert(
                ['unit_name' => $unit['unit_name']],
                [
                    'description' => $unit['description'],
                    'store_id' => 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
