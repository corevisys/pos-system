<?php

/**
 * Phase 6 wiring: replace each decorative Copy/Excel/PDF button block in the
 * report blades with the shared <x-report-export-buttons> component bound to
 * that report's /data route. Idempotent — re-running is a no-op once replaced.
 */

$views = [
    'sales_payment'      => 'sales_payment_data',
    'customer_orders'    => 'customer_orders_data',
    'gstr1'              => 'gstr1_data',
    'gstr2'              => 'gstr2_data',
    'sales_gst'          => 'sales_gst_data',
    'purchase_gst'       => 'purchase_gst_data',
    'sales_tax'          => 'sales_tax_data',
    'purchase_tax'       => 'purchase_tax_data',
    'supplier_items'     => 'supplier_items_data',
    'sales'              => 'sales_data',
    'sales_return'       => 'sales_return_data',
    'seller_points'      => 'seller_points_data',
    'purchase'           => 'purchase_data',
    'purchase_return'    => 'purchase_return_data',
    'expense'            => 'expense_data',
    'stock'              => 'stock_data',
    'sales_item'         => 'sales_item_data',
    'return_items'       => 'return_items_data',
    'purchase_payments'  => 'purchase_payments_data',
    'sales_payments'     => 'sales_payments_data',
];

$base = dirname(__DIR__) . '/resources/views/module/reports/';

// Matches the decorative button block — Copy is optional (some reports only
// have Excel / PDF) — regardless of the exact utility classes on each button.
$pattern = '#<div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">\s*'
    . '(?:<button[^>]*>\s*Copy\s*</button>\s*)?'
    . '<button[^>]*>\s*Excel\s*</button>\s*'
    . '<button[^>]*>\s*PDF\s*</button>\s*'
    . '</div>#s';

$changed = 0;
$skipped = 0;

foreach ($views as $view => $route) {
    $file = $base . $view . '.blade.php';
    if (!file_exists($file)) {
        echo "MISSING: {$view}\n";
        continue;
    }
    $html = file_get_contents($file);

    if (strpos($html, '<x-report-export-buttons') !== false) {
        echo "ALREADY WIRED: {$view}\n";
        $skipped++;
        continue;
    }

    $replacement = '<x-report-export-buttons :route="route(\'reports.' . $route . '\')" />';
    $new = preg_replace($pattern, $replacement, $html, 1, $count);

    if ($count === 1) {
        file_put_contents($file, $new);
        echo "WIRED: {$view} -> reports.{$route}\n";
        $changed++;
    } else {
        echo "NO MATCH: {$view}\n";
    }
}

echo "\nDone. changed={$changed} already={$skipped}\n";
