<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 3টি আলাদা Store তৈরি করা হবে — প্রতিটির জন্য আলাদা Admin User থাকবে।
     */
    public function run(): void
    {
        $stores = [
            // ── Store 1 : Dhaka ──────────────────────────────────────────────
            [
                'id'            => 1,
                'store_code'    => 'ST001',
                'store_name'    => 'Corevisys POS - Dhaka',
                'store_website' => 'https://dhaka.corevisys.com',
                'mobile'        => '01711000001',
                'email'         => 'dhaka@corevisys.com',
                'country'       => 'Bangladesh',
                'state'         => 'Dhaka',
                'city'          => 'Dhaka',
                'address'       => 'Motijheel, Dhaka, Bangladesh',
                'postcode'      => '1000',
            ],
            // ── Store 2 : Chittagong ─────────────────────────────────────────
            [
                'id'            => 2,
                'store_code'    => 'ST002',
                'store_name'    => 'Corevisys POS - Chittagong',
                'store_website' => 'https://ctg.corevisys.com',
                'mobile'        => '01711000002',
                'email'         => 'chittagong@corevisys.com',
                'country'       => 'Bangladesh',
                'state'         => 'Chittagong',
                'city'          => 'Chittagong',
                'address'       => 'Agrabad, Chittagong, Bangladesh',
                'postcode'      => '4100',
            ],
            // ── Store 3 : Sylhet ─────────────────────────────────────────────
            [
                'id'            => 3,
                'store_code'    => 'ST003',
                'store_name'    => 'Corevisys POS - Sylhet',
                'store_website' => 'https://sylhet.corevisys.com',
                'mobile'        => '01711000003',
                'email'         => 'sylhet@corevisys.com',
                'country'       => 'Bangladesh',
                'state'         => 'Sylhet',
                'city'          => 'Sylhet',
                'address'       => 'Zindabazar, Sylhet, Bangladesh',
                'postcode'      => '3100',
            ],
        ];

        // প্রতিটি Store-এর জন্য সাধারণ Prefix ও System Settings একই রাখা হয়েছে
        $commonData = [
            // Prefixes
            'category_init'                => 'CT',
            'item_init'                    => 'IT',
            'supplier_init'                => 'SUP',
            'purchase_init'                => 'PU',
            'purchase_return_init'         => 'PR',
            'customer_init'                => 'CU',
            'sales_init'                   => 'SA',
            'sales_return_init'            => 'SR',
            'expense_init'                 => 'EX',
            'accounts_init'                => 'AC',
            'journal_init'                 => 'JR',
            'cust_advance_init'            => 'CA',
            'quotation_init'               => 'QU',
            'money_transfer_init'          => 'MT',
            'sales_payment_init'           => 'SP',
            'sales_return_payment_init'    => 'SRP',
            'purchase_payment_init'        => 'PP',
            'purchase_return_payment_init' => 'PRP',
            'expense_payment_init'         => 'EP',

            // System Settings
            'timezone'           => 'Asia/Dhaka',
            'date_format'        => 'd-m-Y',
            'time_format'        => 'h:i a',
            'decimals'           => 2,
            'qty_decimals'       => 2,
            'currency_placement' => 'before',

            // Sales Settings
            'sales_discount'            => 0,
            'invoice_view'              => 1,
            'sales_invoice_footer_text' => 'Thank you for shopping with us!',
            'round_off'                 => 1,
            'mrp_column'                => 1,
            'previous_balance_bit'      => 1,
            'number_to_words'           => 1,

            'status'      => 1,
            'language_id' => 1, // English
            'currency_id' => 2, // BDT
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        foreach ($stores as $store) {
            DB::table('db_store')->updateOrInsert(
                ['id' => $store['id']],
                array_merge($commonData, $store)
            );
        }
    }
}
