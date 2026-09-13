<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Shared report-filter validation (Phase 4).
 *
 * Every reports/* /data endpoint previously parsed `start_date` / `end_date`
 * straight into Carbon::parse() and passed id filters straight into where()
 * with no validation at all, so malformed input threw an uncaught exception
 * that only the blanket try/catch surfaced as a 500. This trait centralises
 * the rules so all report data methods validate identically instead of
 * duplicating 24 rule sets.
 *
 * Usage inside a /data method:
 *
 *     if ($error = $this->validateReportFilters($request, ['customer_id'])) {
 *         return $error;
 *     }
 */
trait ValidatesReportFilters
{
    /**
     * Validate the standard report filter set.
     *
     * @param  Request  $request
     * @param  array<int,string>  $idFilters  Which optional id filters this endpoint reads.
     * @return JsonResponse|null  A 422 JSON response on failure, null when valid.
     */
    protected function validateReportFilters(Request $request, array $idFilters = []): ?JsonResponse
    {
        $rules = [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date_format:Y-m-d'],
        ];

        $messages = [
            'start_date.date_format' => 'The start date must be a valid date in YYYY-MM-DD format.',
            'end_date.date_format'   => 'The end date must be a valid date in YYYY-MM-DD format.',
        ];

        // 'all' is the sentinel the report UIs send for "no filter", so allow it
        // alongside a real integer that must exist in the target table.
        $tableMap = [
            'customer_id'  => 'db_customers',
            'supplier_id'  => 'db_suppliers',
            'warehouse_id' => 'db_warehouse',
            'account_id'   => 'ac_accounts',
            'category_id'  => 'db_category',
            'brand_id'     => 'db_brands',
            'item_id'      => 'db_items',
            'user_id'      => 'users',
        ];

        foreach ($idFilters as $filter) {
            if (!isset($tableMap[$filter])) {
                continue;
            }
            $rules[$filter] = [
                'nullable',
                function ($attribute, $value, $fail) use ($tableMap, $filter) {
                    if ($value === null || $value === '' || $value === 'all') {
                        return;
                    }
                    if (!is_numeric($value) || (int) $value != $value) {
                        $fail("The {$attribute} filter must be an integer or 'all'.");
                        return;
                    }
                    if (!\Illuminate\Support\Facades\DB::table($tableMap[$filter])->where('id', (int) $value)->exists()) {
                        $fail("The selected {$attribute} does not exist.");
                    }
                },
            ];
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid report filters.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Cross-field range check: reject start_date > end_date cleanly.
        $start = $request->input('start_date');
        $end   = $request->input('end_date');
        if ($start && $end) {
            try {
                if (Carbon::parse($start)->gt(Carbon::parse($end))) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Invalid report filters.',
                        'errors'  => ['end_date' => ['The end date must be on or after the start date.']],
                    ], 422);
                }
            } catch (\Throwable $e) {
                // The date_format rule already covered malformed input.
            }
        }

        return null;
    }
}
