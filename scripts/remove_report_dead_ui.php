<?php

/**
 * Phase 7 items 16 & 17: remove decorative UI from the report blades.
 *
 *  - item 16: table row checkboxes + "select all" (Alpine selectedAll /
 *             selectedRecords / toggleAll) — no bulk action exists.
 *  - item 17: dead row-action dropdowns (<a href="#">…</a>) and their
 *             enclosing "Action" column — real drill-down is out of scope.
 *
 * Idempotent: re-running reports already-clean files as unchanged.
 */

$dir = dirname(__DIR__) . '/resources/views/module/reports/';
$files = glob($dir . '*.blade.php');
sort($files);

$summary = [];

foreach ($files as $file) {
    $name = basename($file);
    $html = file_get_contents($file);
    $orig = $html;

    // ---- item 17: remove the whole Action <td> block (x-data="{ open: false }") ----
    // Structure is uniform: <td ...> <div x-data="{ open: false }" ...> ... </div> </div> </td>
    $html = preg_replace(
        '#<td[^>]*>\s*<div x-data="\{ open: false \}".*?</div>\s*</div>\s*</td>#s',
        '',
        $html
    );

    // ---- item 17: remove the corresponding <th>...Action</th> ----
    $html = preg_replace(
        '#<th[^>]*>\s*Action\s*</th>#s',
        '',
        $html
    );

    // ---- item 16: remove the "select all" header checkbox <th> ----
    $html = preg_replace(
        '#<th[^>]*>\s*<input type="checkbox" x-model="selectedAll"[^>]*>\s*</th>#s',
        '',
        $html
    );

    // ---- item 16: remove the per-row checkbox <td> ----
    $html = preg_replace(
        '#<td[^>]*>\s*<input type="checkbox"[^>]*x-model="selectedRecords"[^>]*>\s*</td>#s',
        '',
        $html
    );

    if ($html !== $orig) {
        file_put_contents($file, $html);
        $summary[] = "CLEANED: {$name}";
    } else {
        $summary[] = "unchanged: {$name}";
    }
}

echo implode("\n", $summary) . "\n";
echo "\nTotal files: " . count($files) . "\n";
