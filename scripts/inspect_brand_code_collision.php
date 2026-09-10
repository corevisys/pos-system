<?php
// Inspect the db_brands brand_code collision rows for the migration safety pass.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('db_brands')
    ->where('brand_code', 'GIG')
    ->orderBy('id')
    ->get(['id', 'store_id', 'brand_code', 'brand_name', 'status', 'created_at', 'updated_at']);

echo "db_brands rows with brand_code = 'GIG':" . PHP_EOL;
foreach ($rows as $r) {
    echo json_encode($r) . PHP_EOL;
}

// Also check whether any db_items reference these brand ids (usage before any rename).
$ids = $rows->pluck('id');
echo "Referenced by db_items:" . PHP_EOL;
foreach ($ids as $id) {
    $cnt = DB::table('db_items')->where('brand_id', $id)->count();
    echo "  brand_id {$id}: {$cnt} item(s)" . PHP_EOL;
}
