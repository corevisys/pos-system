<?php

/**
 * Phase 7 follow-up: remove the decorative "Show 10/25" page-size control.
 * It had no x-model / @change binding and drove no pagination, so it is a fake
 * affordance of the same class removed in items 16/17.
 */

$dir = dirname(__DIR__) . '/resources/views/module/reports/';
$files = glob($dir . '*.blade.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $html = file_get_contents($file);
    $orig = $html;

    $pattern = '#<div class="flex items-center gap-2">\s*'
        . '<label[^>]*>\s*Show\s*</label>\s*'
        . '<select[^>]*>\s*'
        . '<option>\s*10\s*</option>\s*'
        . '<option>\s*25\s*</option>\s*'
        . '</select>\s*'
        . '</div>#s';

    $html = preg_replace($pattern, '', $html, 1, $count);

    if ($count > 0) {
        file_put_contents($file, $html);
        echo "REMOVED page-size: {$name}\n";
    } else {
        echo "unchanged: {$name}\n";
    }
}
