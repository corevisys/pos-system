<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('db_sitesettings')->updateOrInsert(
            ['id' => 1],
            [
                'site_name' => 'Corevisys POS',
                'version' => '1.0.0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
