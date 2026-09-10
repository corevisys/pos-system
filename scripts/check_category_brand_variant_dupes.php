<?php
// Collision check for the 2026_09_08_000001 migration safety pass.
// Usage: php scripts/check_category_brand_variant_dupes.php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'db_category' => ['category_name', 'category_code'],
    'db_brands'   => ['brand_name', 'brand_code'],
    'db_variants' => ['variant_name', 'variant_code'],
];

$foundAny = false;
foreach ($tables as $table => $cols) {
    foreach ($cols as $col) {
        $dupes = DB::select(
            "SELECT store_id, {$col} AS val, COUNT(*) AS cnt
             FROM {$table}
             WHERE {$col} IS NOT NULL AND {$col} <> ''
             GROUP BY store_id, {$col}
             HAVING COUNT(*) > 1"
        );
        if (count($dupes) > 0) {
            $foundAny = true;
            echo "[DUP] {$table}.{$col}: " . json_encode($dupes) . PHP_EOL;
        } else {
            echo "[OK ] {$table}.{$col}: CLEAN" . PHP_EOL;
        }
    }
}

// Also report total row counts so a "CLEAN with 0 rows" is distinguishable.
foreach (array_keys($tables) as $table) {
    $total = DB::table($table)->count();
    echo "[INF] {$table}: {$total} total rows" . PHP_EOL;
}

echo $foundAny ? "RESULT:COLLISIONS_FOUND" : "RESULT:CLEAN";
