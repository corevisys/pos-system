<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
auth()->login($user);

$html = view('dashboard')->render();

preg_match_all('/class="[^"]*sidebar-tooltip[^"]*">\s*([^\s<]+(?:\s+[^\s<]+)*)\s*</', $html, $matches);

echo "Found " . count($matches[1]) . " tooltips:\n";
foreach ($matches[1] as $i => $tooltip) {
    echo ($i + 1) . ". " . trim($tooltip) . "\n";
}
