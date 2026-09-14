<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Primary keys are pinned explicitly. StoreSeeder references language_id = 1
     * (English), so the language rows must land on stable ids regardless of the
     * connection's auto-increment state. Without pinned ids, a shared MySQL test
     * database (whose auto-increment counter is non-transactional and drifts upward
     * across RefreshDatabase rollbacks) would place English on a different id and
     * every store insert would fail the db_store.language_id foreign key.
     */
    public function run(): void
    {
        $languages = [
            ['id' => 1, 'language' => 'English', 'status' => 1],
            ['id' => 2, 'language' => 'Bangla', 'status' => 0],
        ];

        foreach ($languages as $lang) {
            DB::table('db_languages')->updateOrInsert(
                ['id' => $lang['id']],
                $lang
            );
        }
    }
}
