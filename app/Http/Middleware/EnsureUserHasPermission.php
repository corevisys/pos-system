<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate.
 *
 * Registered as the `permission` alias (see bootstrap/app.php) so an entire route
 * group can be gated with one declaration:
 *
 *     Route::middleware('permission:reports_view')->group(...)
 *
 * This mirrors the existing in-controller gate semantics exactly:
 *   - unauthenticated requests pass through (the auth middleware handles them);
 *   - Super Admins short-circuit via User::hasPermission();
 *   - JSON/API requests receive a 403 JSON payload, web requests an abort(403).
 *
 * Introduced for the reports/* group, where repeating ~24 inline checks would be
 * the only alternative. Controllers that already use the established inline
 * `hasPermission()` + abort(403) pattern are intentionally left as-is.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Auth middleware owns the unauthenticated case.
        if (!$user) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
