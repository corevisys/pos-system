<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared report export mechanism (Phase 6).
 *
 * All 21 currently-decorative report button sets (Copy / Excel / PDF) and the
 * dead P&L "Export Report" button route through this ONE helper instead of each
 * blade re-implementing button wiring. It mirrors the already-proven
 * store-scoped CSV convention used by Deposit / Expenses / Warehouse:
 *
 *   ?export=csv|excel  -> BOM-prefixed CSV stream (Content-Disposition: attachment)
 *   ?export=pdf|print  -> shared print blade partial
 *
 * Contract preservation: this helper is called AFTER the report has built its
 * store-scoped $records, and returns null when no export mode is requested, so
 * the report's existing JSON response shape is completely unchanged. Because the
 * rows passed in are the SAME already-store-scoped rows the JSON would have
 * returned, an export can never contain another store's data.
 */
trait ExportsReportData
{
    /**
     * @param  Request  $request
     * @param  string   $baseName   Filename stem, e.g. 'sales_report'.
     * @param  string   $title      Human title for the print header.
     * @param  array<string,string>  $columns  Map of "Header Label" => record-key.
     * @param  iterable $records    Already-scoped rows (assoc arrays).
     * @param  array    $meta       Optional extra print-header key/value pairs.
     * @return StreamedResponse|View|null  Null when not an export request.
     */
    protected function reportExport(Request $request, string $baseName, string $title, array $columns, $records, array $meta = []): StreamedResponse|View|null
    {
        $mode = $request->query('export');

        if (!in_array($mode, ['csv', 'excel', 'pdf', 'print'], true)) {
            return null;
        }

        // Normalise to an array so stream/view both work with Collections too.
        $rows = is_array($records) ? $records : $records->all();
        $filename = $baseName . '_' . date('Y_m_d_His') . '.csv';

        if (in_array($mode, ['csv', 'excel'], true)) {
            return response()->stream(function () use ($columns, $rows) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM so Excel opens the file with correct encoding.
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($handle, array_keys($columns));
                foreach ($rows as $r) {
                    $line = [];
                    foreach ($columns as $key) {
                        $line[] = is_array($r) ? ($r[$key] ?? '') : ($r->{$key} ?? '');
                    }
                    fputcsv($handle, $line);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // pdf | print
        return view('module.reports.partials.report_print', [
            'title' => $title,
            'columns' => $columns,
            'records' => $rows,
            'meta' => $meta,
        ]);
    }
}
