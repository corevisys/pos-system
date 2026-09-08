<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('db_country')->updateOrInsert(
            ['id' => 1],
            [
                'country' => 'Bangladesh',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
