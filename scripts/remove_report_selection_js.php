<?php

/**
 * Phase 7 item 16 follow-up: remove the now-orphaned Alpine selection state
 * (selectedAll / selectedRecords / selectedOrders and the toggleAll() method)
 * after the decorative checkboxes were removed from the markup.
 */

$dir = dirname(__DIR__) . '/resources/views/module/reports/';
$files = glob($dir . '*.blade.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $html = file_get_contents($file);
    $orig = $html;

    // State declarations (with leading whitespace + trailing newline).
    $html = preg_replace('#^[ \t]*selectedAll:\s*false,\s*\r?\n#m', '', $html);
    $html = preg_replace('#^[ \t]*selectedRecords:\s*\[\],\s*\r?\n#m', '', $html);
    $html = preg_replace('#^[ \t]*selectedOrders:\s*\[\],\s*\r?\n#m', '', $html);

    // toggleAll() method block (handles selectedRecords and selectedOrders bodies).
    $html = preg_replace(
        '#^[ \t]*toggleAll\(\)\s*\{.*?\n[ \t]*\},\s*\r?\n#ms',
        '',
        $html
    );

    if ($html !== $orig) {
        file_put_contents($file, $html);
        echo "JS-CLEANED: {$name}\n";
    } else {
        echo "unchanged: {$name}\n";
    }
}
