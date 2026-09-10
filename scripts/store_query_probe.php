<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "DB_STORE_QUERY_PROBE\n";

$user = \App\Models\User::where('store_id', 1)->first();
if (!$user) {
    echo "NO USER with store_id=1\n";
    exit(1);
}
auth()->login($user);

DB::flushQueryLog();
DB::enableQueryLog();

$s = store_settings();
$sym = \App\Providers\AppServiceProvider::resolveCurrencySymbol();
$tz = \App\Providers\AppServiceProvider::configureStoreTimezone();
$store2 = $user->store;

echo 'RESULT store=' . ($s->id ?? 'null')
    . ' sym=' . $sym
    . ' tz=' . $tz
    . ' rel=' . ($store2->id ?? 'null') . "\n";

foreach (DB::getQueryLog() as $q) {
    if (str_contains(strtolower($q['query']), 'db_store')) {
        echo 'Q: ' . $q['query'] . "\n";
    }
}
