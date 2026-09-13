<?php

/**
 * Phase 7 follow-up: after removing the decorative Action column and checkbox
 * column, realign each report table's empty-state / loading-row colspan and drop
 * the now-orphaned trailing empty summary cells.
 *
 * Strategy per file:
 *  1. headerCount = number of <th> inside the first <thead>…</thead>.
 *  2. Any empty/loading row whose first cell is <td colspan="N" ...> gets N = headerCount.
 *  3. In <tfoot> rows, remove trailing empty <td> / <td colspan="N"></td> cells
 *     so the summary row totals headerCount cells.
 */

$dir = dirname(__DIR__) . '/resources/views/module/reports/';
$files = glob($dir . '*.blade.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $html = file_get_contents($file);
    $orig = $html;

    // --- 1. header count from the first thead ---
    $headerCount = 0;
    if (preg_match('#<thead\b.*?</thead>#s', $html, $m)) {
        $headerCount = preg_match_all('#<th\b#', $m[0]);
    }
    if ($headerCount === 0) {
        echo "SKIP (no thead): {$name}\n";
        continue;
    }

    // --- 2. realign empty/loading row colspans ---
    $html = preg_replace_callback(
        '#<td colspan="\d+"([^>]*)>(\s*(?:<div[^>]*>\s*)?(?:<svg|<span)[^>]*)#s',
        function ($mm) use ($headerCount) {
            return '<td colspan="' . $headerCount . '"' . $mm[1] . '>' . $mm[2];
        },
        $html
    );

    // Simpler, explicit: any colspan on a row whose text mentions "No " / "Loading"
    $html = preg_replace_callback(
        '#<td colspan="\d+"([^>]*)>((?:(?!</td>).)*?(?:No\s|Loading|No\s.*Found)(?:(?!</td>).)*?)</td>#s',
        function ($mm) use ($headerCount) {
            return '<td colspan="' . $headerCount . '"' . $mm[1] . '>' . $mm[2] . '</td>';
        },
        $html
    );

    // --- 3. drop orphaned trailing empty summary cells in tfoot ---
    $html = preg_replace('#<td colspan="2"></td>\s*(</tr>)#', '$1', $html);
    $html = preg_replace('#<td></td>\s*(</tr>)#', '$1', $html);
    $html = preg_replace('#<td colspan="3"></td>\s*(</tr>)#', '$1', $html);

    if ($html !== $orig) {
        file_put_contents($file, $html);
        echo "FIXED: {$name} (headerCount={$headerCount})\n";
    } else {
        echo "unchanged: {$name} (headerCount={$headerCount})\n";
    }
}
