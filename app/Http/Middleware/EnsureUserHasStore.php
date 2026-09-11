<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasStore
{
    /**
     * Handle an incoming request.
     *
     * Gated for authenticated operational routes:
     * - Super Admins bypass this check entirely (isSuperAdmin() convention).
     * - Regular users must have a store_id pointing to an existing, active db_store row.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unauthenticated requests pass through to auth middleware
        if (!$user) {
            return $next($request);
        }

        // Super Admin bypasses all store restrictions
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Regular user must have a non-null, non-empty store_id
        if (empty($user->store_id)) {
            abort(403, 'No store is associated with your account.');
        }

        // Resolve the store settings / db_store row
        $store = function_exists('store_settings')
            ? store_settings(false, (int) $user->store_id)
            : \App\Models\DbStore::where('id', (int) $user->store_id)->first();

        if (!$store) {
            abort(403, 'The assigned store does not exist.');
        }

        if ((int) $store->status !== 1) {
            abort(403, 'The assigned store has been deactivated. Please contact your administrator.');
        }

        return $next($request);
    }
}
