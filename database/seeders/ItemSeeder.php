<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            // [Name, Brand, Category, Purchase, Sales, Stock]
            ['HP 15s Core i5 12th Gen Laptop', 'HP', 'Laptop', 65000, 72000, 15],
            ['Dell Inspiron 15 3520', 'Dell', 'Laptop', 68000, 76000, 12],
            ['Asus VivoBook 15 X1502', 'Asus', 'Laptop', 62000, 69000, 10],
            ['Lenovo IdeaPad 3 15ALC6', 'Lenovo', 'Laptop', 58000, 65000, 18],
            ['Acer Aspire 5 A515', 'Acer', 'Laptop', 60000, 67000, 9],
            ['MSI Modern 14 C12M', 'MSI', 'Laptop', 72000, 81000, 7],
            ['Apple MacBook Air M1', 'Apple', 'Laptop', 92000, 105000, 5],
            ['HP Pavilion 14', 'HP', 'Laptop', 78000, 86000, 8],
            ['Dell Vostro 15 3510', 'Dell', 'Laptop', 64000, 71000, 14],
            ['Asus TUF F15 Gaming', 'Asus', 'Laptop', 98000, 112000, 6],

            ['Intel Core i5 12400', 'Intel', 'Processor', 18000, 21000, 25],
            ['AMD Ryzen 5 5600X', 'AMD', 'Processor', 19000, 22500, 20],
            ['Gigabyte B660M DS3H', 'Gigabyte', 'Motherboard', 12500, 15000, 16],
            ['MSI B550M Pro-VDH', 'MSI', 'Motherboard', 11500, 14000, 18],
            ['Corsair 8GB DDR4 3200MHz', 'Corsair', 'RAM', 2500, 3200, 50],
            ['Kingston 16GB DDR4 3200MHz', 'Kingston', 'RAM', 4800, 5800, 40],
            ['Samsung 980 500GB NVMe SSD', 'Samsung', 'SSD', 5500, 6800, 35],
            ['Western Digital 1TB HDD', 'WD', 'Storage', 3200, 3900, 45],
            ['Antec NX292 Case', 'Antec', 'Casing', 4200, 5200, 22],
            ['Cooler Master 550W PSU', 'Cooler Master', 'Power Supply', 4800, 6000, 30],

            ['LG 22MK430H 22" IPS Monitor', 'LG', 'Monitor', 11500, 13500, 14],
            ['Samsung LF24T350 24" IPS', 'Samsung', 'Monitor', 13500, 15500, 12],
            ['Asus VA24EHE 24" IPS', 'Asus', 'Monitor', 14000, 16500, 10],
            ['AOC 24B2XH 24" IPS', 'AOC', 'Monitor', 13000, 15000, 9],
            ['Gigasonic 19" LED Monitor', 'Gigasonic', 'Monitor', 7500, 9000, 20],

            ['HP LaserJet Pro M404dn', 'HP', 'Printer', 22000, 26000, 6],
            ['Canon LBP 2900', 'Canon', 'Printer', 16500, 19500, 10],
            ['Epson L3250 Ink Tank', 'Epson', 'Printer', 18500, 22000, 8],
            ['Brother DCP-T420W', 'Brother', 'Printer', 17500, 21000, 7],
            ['Pantum P2500W', 'Pantum', 'Printer', 13500, 16000, 11],

            ['Logitech G102 Mouse', 'Logitech', 'Mouse', 1200, 1600, 60],
            ['A4Tech Bloody V5 Mouse', 'A4Tech', 'Mouse', 1500, 1900, 40],
            ['Redragon K552 Keyboard', 'Redragon', 'Keyboard', 3200, 4000, 30],
            ['Logitech K120 Keyboard', 'Logitech', 'Keyboard', 700, 1000, 70],
            ['Fantech HG11 Headset', 'Fantech', 'Headphone', 1800, 2300, 35],
            ['Sony WH-CH510 Headphone', 'Sony', 'Headphone', 4200, 5000, 18],
            ['TP-Link Archer C6 Router', 'TP-Link', 'Networking', 3800, 4500, 22],
            ['Tenda F3 Router', 'Tenda', 'Networking', 1800, 2300, 28],
            ['Sandisk 64GB Pen Drive', 'Sandisk', 'Storage', 700, 1000, 80],
            ['Havit Webcam HV-HN01', 'Havit', 'Webcam', 2200, 2800, 25],

            ['Power Guard 650VA UPS', 'Power Guard', 'UPS', 3500, 4200, 15],
            ['APC 850VA UPS', 'APC', 'UPS', 7800, 9200, 9],
            ['MaxGreen 1000VA UPS', 'MaxGreen', 'UPS', 6500, 7800, 11],
            ['DigitalX 1200VA UPS', 'DigitalX', 'UPS', 8200, 9800, 6],
            ['Marsriva 1000VA UPS', 'Marsriva', 'UPS', 7000, 8500, 8],
        ];

        foreach ($items as $data) {
            $item_name = $data[0];
            $brand_name = $data[1];
            $category_name = $data[2];
            $purchase_price = $data[3];
            $sales_price = $data[4];
            $stock = $data[5];

            $category_id = DB::table('db_category')->where('category_name', $category_name)->value('id');
            $brand_id = DB::table('db_brands')->where('brand_name', $brand_name)->value('id');

            DB::table('db_items')->updateOrInsert(
                ['item_name' => $item_name],
                [
                    'store_id' => 1,
                    'item_code' => strtoupper(substr($item_name, 0, 3)) . rand(1000, 9999),
                    'category_id' => $category_id,
                    'brand_id' => $brand_id,
                    'unit_id' => 1, // Piece
                    'purchase_price' => $purchase_price,
                    'sales_price' => $sales_price,
                    'price' => $sales_price,
                    'mrp' => $sales_price,
                    'stock' => $stock,
                    'status' => 1,
                    'tax_id' => 1, // VAT 15%
                    'tax_type' => 'Exclusive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
