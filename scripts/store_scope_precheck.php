<?php

/**
 * PHASE 0 — Store settings data pre-check.
 *
 * READ-ONLY. Determines whether the single `db_store` row can be safely
 * replaced by an explicit per-store scoped lookup without leaving orphan
 * store_id references in other tables.
 *
 * Run:  php scripts/store_scope_precheck.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$driver = DB::connection()->getDriverName();
echo "=== PHASE 0 STORE-SCOPE PRE-CHECK ===\n";
echo "DB driver: {$driver}\n\n";

$storeCount = DB::table('db_store')->count();
$storeIds = DB::table('db_store')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();

echo "db_store row count: {$storeCount}\n";
echo "db_store ids: [" . implode(', ', $storeIds) . "]\n\n";

// --- Discover every table that has a store_id column -----------------------
$tableNames = [];

if ($driver === 'sqlite') {
    $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
    foreach ($tables as $t) {
        $cols = DB::select("PRAGMA table_info('" . $t->name . "')");
        foreach ($cols as $c) {
            if ($c->name === 'store_id') {
                $tableNames[] = $t->name;
                break;
            }
        }
    }
} else {
    $rows = DB::select(
        "SELECT TABLE_NAME AS t FROM information_schema.COLUMNS "
        . "WHERE COLUMN_NAME = 'store_id' AND TABLE_SCHEMA = DATABASE()"
    );
    foreach ($rows as $r) {
        $tableNames[] = $r->t;
    }
}

sort($tableNames);
echo "Tables with a store_id column (" . count($tableNames) . "):\n";
foreach ($tableNames as $t) {
    echo "  - {$t}\n";
}
echo "\n";

// --- Check each table's distinct store_id values against db_store.id -------
$missing = [];

foreach ($tableNames as $t) {
    try {
        $distinct = DB::table($t)->whereNotNull('store_id')->distinct()->pluck('store_id')
            ->map(fn ($v) => (int) $v)->all();
    } catch (\Throwable $e) {
        echo "[??] {$t}: could not read store_id ({$e->getMessage()})\n";
        continue;
    }

    if (empty($distinct)) {
        echo "[OK] {$t}: no non-null store_id values\n";
        continue;
    }

    $orphans = array_values(array_diff($distinct, $storeIds));
    if (empty($orphans)) {
        echo "[OK] {$t}: store_ids [" . implode(', ', $distinct) . "] all resolve\n";
    } else {
        echo "[!!] {$t}: ORPHAN store_ids [" . implode(', ', $orphans) . "]"
            . " (distinct present: [" . implode(', ', $distinct) . "])\n";
        foreach ($orphans as $o) {
            $missing[$o][] = $t;
        }
    }
}

echo "\n";
if (empty($missing)) {
    echo "RESULT: All store_id values resolve to a db_store row.\n";
    echo "SAFE TO PROCEED to Phase 1.\n";
} else {
    echo "RESULT: ORPHAN store_ids detected. STOP — do not auto-create rows.\n";
    foreach ($missing as $id => $tbls) {
        echo "  store_id={$id} referenced by: " . implode(', ', $tbls) . "\n";
    }
}
