<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Primary keys are pinned explicitly. StoreSeeder references currency_id = 2
     * (BDT), so the currency rows must land on stable ids regardless of the
     * connection's auto-increment state. Without pinned ids, a shared MySQL test
     * database (whose auto-increment counter is non-transactional and drifts upward
     * across RefreshDatabase rollbacks) would place BDT on a different id and every
     * store insert would fail the db_store.currency_id foreign key.
     *
     * Written through the query builder (not the Eloquent model) so the explicit
     * 'id' is honoured in production as well, where mass-assignment guards are in
     * force and 'id' is not fillable.
     */
    public function run(): void
    {
        $currencies = [
            ['id' => 1, 'currency_name' => 'US Dollar',    'currency' => 'US Dollar',    'currency_code' => 'USD', 'symbol' => '$', 'status' => 0],
            ['id' => 2, 'currency_name' => 'TAKA',         'currency' => 'TAKA',         'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1],
            ['id' => 3, 'currency_name' => 'Euro',         'currency' => 'Euro',         'currency_code' => 'EUR', 'symbol' => '€', 'status' => 0],
            ['id' => 4, 'currency_name' => 'Indian Rupee', 'currency' => 'Indian Rupee', 'currency_code' => 'INR', 'symbol' => '₹', 'status' => 0],
        ];

        foreach ($currencies as $curr) {
            DB::table('db_currency')->updateOrInsert(
                ['id' => $curr['id']],
                $curr
            );
        }
    }
}
