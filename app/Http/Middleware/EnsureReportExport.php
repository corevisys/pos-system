<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared report export mechanism (Phase 6).
 *
 * Registered on the whole `reports/*` group (same slab as the `reports_view`
 * permission), this converts the ALREADY-BUILT, ALREADY-STORE-SCOPED JSON that
 * every /data endpoint returns into a real downloadable artifact whenever the
 * request carries `?export=csv|excel|pdf|print`.
 *
 * Why middleware rather than 21 per-method edits:
 *   - ONE implementation, so a future export fix/feature touches one file;
 *   - it reuses the exact same store-scoped rows the on-screen report shows,
 *     so an export can never contain another store's data by construction;
 *   - it does not alter any report's JSON shape (returns the original response
 *     untouched when no `export` param is present).
 *
 * Modes mirror the established Deposit/Expenses/Warehouse convention:
 *   csv/excel -> UTF-8 BOM CSV stream (Content-Disposition: attachment)
 *   pdf/print -> shared module.reports.partials.report_print blade
 */
class EnsureReportExport
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $request->query('export');

        if (!in_array($mode, ['csv', 'excel', 'pdf', 'print'], true)) {
            return $next($request);
        }

        $response = $next($request);

        if (!$response instanceof JsonResponse || $response->getStatusCode() !== 200) {
            return $response;
        }

        $payload = $response->getData(true);
        if (!is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            return $response;
        }

        [$rows, $title] = $this->extractRowsAndTitle($payload, $request);

        if (in_array($mode, ['csv', 'excel'], true)) {
            $filename = 'report_' . $title['slug'] . '_' . date('Y_m_d_His') . '.csv';
            return response()->stream(function () use ($rows) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
                if (!empty($rows)) {
                    fputcsv($handle, array_keys($rows[0]));
                    foreach ($rows as $r) {
                        fputcsv($handle, array_values($r));
                    }
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return response()->view('module.reports.partials.report_print', [
            'title' => $title['label'],
            'columns' => $this->columnsFromRows($rows),
            'records' => $rows,
            'meta' => [
                'Report' => $title['label'],
                'Scope' => 'Acting store only',
            ],
        ]);
    }

    /**
     * Derive the printable/exportable rows from the JSON payload.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array{slug:string,label:string}}
     */
    private function extractRowsAndTitle(array $payload, Request $request): array
    {
        $path = trim($request->path(), '/');
        $label = str_replace('-', ' ', str_replace('reports/', '', $path));
        $label = ucwords(str_replace('/data', '', $label)) . ' Report';
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(str_replace('/data', '', $path)));

        $source = $payload['records'] ?? $payload['data'] ?? null;

        if (is_array($source) && $this->isListOfRows($source)) {
            return [array_values($source), ['slug' => $slug, 'label' => $label]];
        }

        // Nested/non-tabular payloads (e.g. Profit & Loss) -> flatten to
        // "Section.Field" => value rows so the export is still truthful.
        $flat = [];
        $walk = function ($node, string $prefix = '') use (&$walk, &$flat) {
            if (is_array($node)) {
                foreach ($node as $k => $v) {
                    $walk($v, $prefix === '' ? (string) $k : $prefix . '.' . $k);
                }
            } else {
                $flat[ucwords(str_replace(['.', '_'], ' ', $prefix))] = $node;
            }
        };
        if (is_array($payload['data'] ?? null)) {
            $walk($payload['data']);
        } elseif (is_array($payload['summary'] ?? null)) {
            $walk($payload['summary']);
        }

        $rows = [];
        foreach ($flat as $k => $v) {
            $rows[] = ['Item' => $k, 'Value' => $v];
        }

        return [$rows, ['slug' => $slug, 'label' => $label]];
    }

    private function isListOfRows(array $source): bool
    {
        if (empty($source)) {
            return false;
        }
        // True when keys are 0..n-1 and values are arrays/objects.
        return array_keys($source) === range(0, count($source) - 1)
            && is_array(reset($source));
    }

    /** @return array<string,string> header label => row key */
    private function columnsFromRows(array $rows): array
    {
        if (empty($rows)) {
            return ['No Data' => 'no_data'];
        }
        $cols = [];
        foreach (array_keys($rows[0]) as $key) {
            $cols[ucwords(str_replace('_', ' ', (string) $key))] = $key;
        }
        return $cols;
    }
}
