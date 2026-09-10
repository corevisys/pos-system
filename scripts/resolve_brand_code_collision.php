<?php
// Resolve the db_brands brand_code collision before running the
// 2026_09_08_000001 migration (which adds (store_id, brand_code) unique).
//
// Collision: Gigabyte (id 10) and Gigasonic (id 19) both carry brand_code 'GIG'
// in store 1 (both derived from the 3-letter prefix). Both brands are real and
// each is referenced by 1 db_items row — do NOT merge. Rename the later-created
// brand's code to the same collision-free scheme the fixed BrandSeeder now
// generates: prefix + numeric suffix (GIG -> GIG2).
//
// Usage: php scripts/resolve_brand_code_collision.php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$gigabyte = DB::table('db_brands')->where('id', 10)->first();
$gigasonic = DB::table('db_brands')->where('id', 19)->first();

if (!$gigabyte || !$gigasonic) {
    echo "RESULT:MISSING_ROW" . PHP_EOL;
    exit(1);
}

// Confirm the collision still exists exactly as expected.
if ($gigabyte->brand_code !== 'GIG' || $gigasonic->brand_code !== 'GIG') {
    echo "RESULT:COLLISION_CHANGED (expected both GIG)" . PHP_EOL;
    exit(1);
}

// Assign GIG2 to the later-created brand (Gigasonic, id 19).
$newCode = 'GIG2';
$existing = DB::table('db_brands')
    ->where('store_id', 1)
    ->where('brand_code', $newCode)
    ->where('id', '!=', 19)
    ->exists();
if ($existing) {
    echo "RESULT:GIG2_TAKEN" . PHP_EOL;
    exit(1);
}

DB::table('db_brands')->where('id', 19)->update(['brand_code' => $newCode, 'updated_at' => now()]);

$after = DB::table('db_brands')->whereIn('id', [10, 19])->get(['id', 'brand_code', 'brand_name']);
foreach ($after as $r) {
    echo "UPDATED: {$r->id} {$r->brand_name} -> {$r->brand_code}" . PHP_EOL;
}

// Final verification: any remaining per-store code duplicates?
$dupes = DB::select(
    "SELECT store_id, brand_code AS val, COUNT(*) AS cnt
     FROM db_brands
     WHERE brand_code IS NOT NULL AND brand_code <> ''
     GROUP BY store_id, brand_code
     HAVING COUNT(*) > 1"
);
echo count($dupes) ? ("STILL_DUPES: " . json_encode($dupes) . PHP_EOL) : "RESULT:CLEAN" . PHP_EOL;
