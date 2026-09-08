<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            ['language' => 'English', 'status' => 1],
            ['language' => 'Bangla', 'status' => 0],
        ];

        foreach ($languages as $lang) {
            DB::table('db_languages')->updateOrInsert(
                ['language' => $lang['language']],
                $lang
            );
        }
    }
}
