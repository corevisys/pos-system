<?php

namespace App\Http\Middleware;

use App\Services\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the explicit "acting store" for the request and binds it into
 * StoreContext, which current_store_id() (and therefore every StoreScoped model)
 * then reads.
 *
 * Resolution order:
 *   1. session('current_store_id') — if the authenticated user is allowed to act as
 *      that store (a branch admin may only act as their own store; a developer/super
 *      admin may act as any store). A disallowed value is ignored, never honoured.
 *   2. the authenticated user's own store_id.
 *   3. (otherwise) leave the context unresolved so current_store_id() falls back to
 *      its legacy/auth/default behaviour — this covers unauthenticated requests,
 *      guest routes and console/queue contexts.
 *
 * Registered globally on the web group (see bootstrap/app.php), so it runs after
 * StartSession and before any controller or StoreScoped query. It only ever reads
 * the session value; the Owner store selector that WRITES it is a separate route.
 */
class SetCurrentStore
{
    public const SESSION_KEY = 'current_store_id';

    public function __construct(private StoreContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // A fresh context per request — never inherit a value from a previous one.
        $this->context->forget();

        $user = $request->user();

        if ($user) {
            $sessionStoreId = $request->hasSession()
                ? $request->session()->get(self::SESSION_KEY)
                : null;

            if ($sessionStoreId !== null && $sessionStoreId !== ''
                && $this->canActAs($user, (int) $sessionStoreId)) {
                $this->context->set((int) $sessionStoreId);
            } elseif (!empty($user->store_id)) {
                $this->context->set((int) $user->store_id);
            }
        }

        return $next($request);
    }

    /**
     * Whether $user is permitted to act as $storeId. A disallowed session value must
     * be ignored (falling back to the user's own store), never silently accepted.
     */
    private function canActAs($user, int $storeId): bool
    {
        // Cross-store identities (Developer/system account and Owner) may act as any
        // store. The Owner is NOT a super admin, but it is allowed cross-store read
        // scope, which is exactly what "acting as a chosen store" requires.
        if (method_exists($user, 'canViewAllStores') && $user->canViewAllStores()) {
            return true;
        }

        // Everyone else is binary-bound to exactly their own store.
        return !empty($user->store_id) && (int) $user->store_id === $storeId;
    }
}
