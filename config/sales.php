<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enforce server-side grand_total validation (KILL-SWITCH)
    |--------------------------------------------------------------------------
    |
    | When TRUE (default), PosController@store / storeEmi REJECT (HTTP 422) any
    | sale whose client-sent grand_total does not match the server-side recompute
    | within a small rounding tolerance. This prevents client-tampered/false totals
    | from being persisted.
    |
    | When FALSE, the server still RUNS the recompute and LOGS any mismatch (via
    | logTotalMismatch) but does NOT reject the request — it falls back to trusting
    | the client total (the pre-validation behavior).
    |
    | Controlled by the ENFORCE_TOTAL_VALIDATION environment variable. If a
    | false-positive rejection ever appears in production, flip this to false in
    | .env and run `php artisan config:clear` — no code deploy required.
    |
    */

    'enforce_total_validation' => env('ENFORCE_TOTAL_VALIDATION', true),

];
