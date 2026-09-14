<?php

namespace App\Services;

/**
 * Holds the explicit "acting store" for the current request.
 *
 * Historically current_store_id() was derived solely from auth()->user()->store_id,
 * which makes it impossible for a cross-store role (the future Owner) to view a
 * chosen store, and leaves no explicit session-scoped store. SetCurrentStore
 * middleware resolves the acting store (session -> user default) and binds the
 * result here; current_store_id() reads it first and only falls back to the old
 * user->store_id logic when nothing has been resolved (console/queue contexts, or
 * unauthenticated requests).
 *
 * The "all stores" aggregate view (Owner only) is represented by the isAllStores()
 * flag rather than a magic store id, so a real store id is never confused with the
 * aggregate sentinel.
 */
class StoreContext
{
    /** The resolved acting store id, or null when unresolved / all-stores. */
    private ?int $storeId = null;

    /** True when the acting scope is the cross-store aggregate (Owner only). */
    private bool $allStores = false;

    /** True once SetCurrentStore (or a test) has explicitly set the context. */
    private bool $resolved = false;

    /**
     * Bind a specific acting store.
     */
    public function set(int $storeId, bool $allStores = false): void
    {
        $this->storeId = $storeId;
        $this->allStores = $allStores;
        $this->resolved = true;
    }

    /**
     * Bind the "all stores" aggregate (no single acting store id).
     */
    public function setAllStores(): void
    {
        $this->storeId = null;
        $this->allStores = true;
        $this->resolved = true;
    }

    public function storeId(): ?int
    {
        return $this->storeId;
    }

    public function isAllStores(): bool
    {
        return $this->allStores;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Clear the context (used by tests and long-lived process boundaries so a
     * previous request's binding can never leak into the next one).
     */
    public function forget(): void
    {
        $this->storeId = null;
        $this->allStores = false;
        $this->resolved = false;
    }
}
