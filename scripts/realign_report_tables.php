<?php

/**
 * Phase 7 follow-up v2: realign colspans PER TABLE (some report views contain
 * more than one table). For each <table>…</table> block:
 *   - N = number of <th> in that table's <thead>;
 *   - any tbody row that consists of a single <td> (loading / empty-state) -> colspan=N;
 *   - each <tfoot> <tr>: fix the FIRST cell's colspan so the row's total
 *     spanned width equals N.
 */

$dir = dirname(__DIR__) . '/resources/views/module/reports/';
$files = glob($dir . '*.blade.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    $html = file_get_contents($file);
    $orig = $html;

    $html = preg_replace_callback('#<table\b.*?</table>#s', function ($m) {
        $table = $m[0];
        // N from this table's thead.
        if (!preg_match('#<thead\b.*?</thead>#s', $table, $hm)) {
            return $table;
        }
        $n = preg_match_all('#<th\b#', $hm[0]);
        if ($n < 1) {
            return $table;
        }

        // --- tbody loading/empty single-cell rows ---
        $table = preg_replace_callback(
            '#<tr\b(?:(?!</tr>).)*?>\s*<td colspan="\d+"#s',
            function ($rm) use ($n) {
                $open = $rm[0];
                return preg_replace('#colspan="\d+"#', 'colspan="' . $n . '"', $open, 1);
            },
            $table
        );

        // --- tfoot rows: force total spanned width to N ---
        $table = preg_replace_callback('#<tfoot\b.*?</tfoot>#s', function ($fm) use ($n) {
            $foot = $fm[0];
            return preg_replace_callback('#<tr\b.*?</tr>#s', function ($trm) use ($n) {
                $tr = $trm[0];
                // Collect the <td …> tags (with their colspans).
                if (!preg_match_all('#<td\b([^>]*)>#s', $tr, $tds, PREG_SET_ORDER)) {
                    return $tr;
                }
                $total = 0;
                foreach ($tds as $td) {
                    if (preg_match('#colspan="(\d+)"#', $td[1], $cm)) {
                        $total += (int) $cm[1];
                    } else {
                        $total += 1;
                    }
                }
                if ($total === $n || count($tds) === 0) {
                    return $tr;
                }

                // Adjust the FIRST td's colspan by the delta.
                $delta = $n - $total;
                $first = $tds[0];
                $firstColspan = preg_match('#colspan="(\d+)"#', $first[1], $cm) ? (int) $cm[1] : 1;
                $newColspan = max(1, $firstColspan + $delta);

                $oldTag = $first[0];
                if (preg_match('#colspan="\d+"#', $oldTag)) {
                    $newTag = preg_replace('#colspan="\d+"#', 'colspan="' . $newColspan . '"', $oldTag, 1);
                } else {
                    // Insert a colspan attribute right after <td.
                    $newTag = preg_replace('#^<td#', '<td colspan="' . $newColspan . '"', $oldTag, 1);
                }

                // Replace only the first occurrence.
                $pos = strpos($tr, $oldTag);
                if ($pos === false) {
                    return $tr;
                }
                return substr_replace($tr, $newTag, $pos, strlen($oldTag));
            }, $foot);
        }, $table);

        return $table;
    }, $html);

    if ($html !== $orig) {
        file_put_contents($file, $html);
        echo "REALIGNED: {$name}\n";
    } else {
        echo "unchanged: {$name}\n";
    }
}
