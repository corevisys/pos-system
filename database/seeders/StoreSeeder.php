<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('db_store')->updateOrInsert(
            ['id' => 1],
            [
                'store_code' => 'ST001',
                'store_name' => 'Corevisys POS',
                'store_website' => 'https://test.corevisys.com',
                'mobile' => '0123456789',
                'email' => 'admin@corevisys.com',
                'country' => 'Bangladesh',
                'state' => 'Dhaka',
                'city' => 'Dhaka',
                'address' => 'Dhaka, Bangladesh',
                'postcode' => '1200',
                
                // Prefixes
                'category_init' => 'CT',
                'item_init' => 'IT',
                'supplier_init' => 'SUP',
                'purchase_init' => 'PU',
                'purchase_return_init' => 'PR',
                'customer_init' => 'CU',
                'sales_init' => 'SA',
                'sales_return_init' => 'SR',
                'expense_init' => 'EX',
                'accounts_init' => 'AC',
                'journal_init' => 'JR',
                'cust_advance_init' => 'CA',
                'quotation_init' => 'QU',
                'money_transfer_init' => 'MT',
                'sales_payment_init' => 'SP',
                'sales_return_payment_init' => 'SRP',
                'purchase_payment_init' => 'PP',
                'purchase_return_payment_init' => 'PRP',
                'expense_payment_init' => 'EP',

                // System Settings
                'timezone' => 'Asia/Dhaka',
                'date_format' => 'd-m-Y',
                'time_format' => 'h:i A',
                'decimals' => 2,
                'qty_decimals' => 2,
                'currency_placement' => 'Left',
                
                // Sales Settings
                'sales_discount' => 0,
                'invoice_view' => 1,
                'sales_invoice_footer_text' => 'Thank you for shopping with us!',
                'round_off' => 1,
                'mrp_column' => 1,
                'previous_balance_bit' => 1,
                'number_to_words' => 1,
                
                'status' => 1,
                'language_id' => 1, // English
                'currency_id' => 2, // BDT
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
