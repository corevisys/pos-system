<?php
// Post-migration verification for 2026_09_08_000001.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$expected = [
    'db_category_store_category_name_unique',
    'db_category_store_category_code_unique',
    'db_brands_store_brand_name_unique',
    'db_brands_store_brand_code_unique',
    'db_variants_store_variant_name_unique',
    'db_variants_store_variant_code_unique',
];

$indexes = DB::select("SHOW INDEX FROM db_category");
$indexNames = array_column($indexes, 'Key_name');
$indexNames = array_merge($indexNames, array_column(DB::select("SHOW INDEX FROM db_brands"), 'Key_name'));
$indexNames = array_merge($indexNames, array_column(DB::select("SHOW INDEX FROM db_variants"), 'Key_name'));

foreach ($expected as $idx) {
    echo (in_array($idx, $indexNames) ? "[OK ] {$idx}" : "[MISS] {$idx}") . PHP_EOL;
}

// DB-level duplicate rejection: insert duplicate category_name in store 1.
$catCount = DB::table('db_category')->count();
try {
    DB::table('db_category')->insert([
        'category_name' => 'Laptop', // already seeded in store 1
        'store_id' => 1,
        'status' => 1,
    ]);
    echo "DUPLICATE_INSERT:UNEXPECTEDLY_SUCCEEDED" . PHP_EOL;
} catch (\Illuminate\Database\QueryException $e) {
    echo "DUPLICATE_CATEGORY_INSERT:REJECTED (" . (str_contains(strtolower($e->getMessage()), 'unique') ? 'unique-violation' : 'other') . ")" . PHP_EOL;
}
$catCountAfter = DB::table('db_category')->count();
echo "category rows before={$catCount} after={$catCountAfter}" . PHP_EOL;

// Same-store duplicate via brand_code, cross-store duplicate allowed:
try {
    DB::table('db_brands')->insert([
        'brand_name' => 'TempBrand',
        'brand_code' => 'GIG', // taken in store 1
        'store_id' => 1,
        'status' => 1,
    ]);
    echo "DUPLICATE_BRAND_INSERT:UNEXPECTEDLY_SUCCEEDED" . PHP_EOL;
} catch (\Illuminate\Database\QueryException $e) {
    echo "DUPLICATE_BRAND_INSERT:REJECTED (" . (str_contains(strtolower($e->getMessage()), 'unique') ? 'unique-violation' : 'other') . ")" . PHP_EOL;
}

// Cross-store same name must still be ALLOWED (per-store scoping).
DB::table('db_brands')->insert([
    'brand_name' => 'Gigabyte',
    'brand_code' => 'GIG',
    'store_id' => 2,
    'status' => 1,
]);
echo "CROSS_STORE_BRAND_INSERT:ALLOWED" . PHP_EOL;
DB::table('db_brands')->where('store_id', 2)->where('brand_name', 'Gigabyte')->delete();
