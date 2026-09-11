# Phase 1.5 — StoreScoped on User / Role / Permission — Analysis & Decision

**Status:** ❌ NOT APPLIED — the trait is incompatible with the auth layer.
**Date:** 2026-09-11
**Related commits:** `d0068a2` (StoreScoped trait, `orWhereNull` semantics), `d796e54`…`4018d24` (model batches 1–10), `33885f9` (NOT NULL migration + fixtures).

---

## 1. Objective

Apply `StoreScoped` to `User`, `DbRole`, `DbPermission` so user/role/permission
queries are store-filtered, while preserving Super Admin's cross-store visibility
via `scopeAllStores()`.

---

## 2. Empirically confirmed circular dependency (User)

The instructions explicitly required tracing the circularity before touching
`User.php`. A depth-guarded probe was written that:

1. Registered the **exact** `StoreScoped` closure on `User` (guarded to throw at
   depth 5 instead of overflowing the stack).
2. Put the user id into the session the way a **real logged-in request** does
   (`session()->put($guard->getName(), $user->id)`) — deliberately NOT via
   `actingAs()`, which bypasses the DB query through `setUser()`.
3. Called `$guard->user()` to force session resolution.

**Result — RuntimeException thrown at depth 5:**

```
CIRCULAR RECURSION CONFIRMED: User scope -> auth()->check() -> SessionGuard::user()
-> retrieveById() -> User query (re-entered). Depth=5
```

**Root cause chain (from code trace):**
- `config/auth.php` → `'driver' => 'eloquent'`, `'model' => User::class` — the
  guard resolves users via `EloquentUserProvider::retrieveById()`.
- On a fresh authenticated request the session holds only the **user id**; the
  guard's `user()` calls `retrieveById($id)` → `User::newQuery()->where('id', $id)`.
- The `StoreScoped` scope closure calls `auth()->check()` **while `$this->user`
  is still `null`**, so the guard re-attempts resolution → the User query runs
  again → the scope re-enters → infinite recursion.

In production this would make **every authenticated request hang/crash** — a
total lockout of all users.

---

## 3. DbRole / DbPermission — already excluded (Phase 1.3)

In Batch 9 of Phase 1.3, applying the trait to `DbRole`/`DbPermission` produced a
wave of **403 permission-gate failures**:

- [`User.php:112`](../app/Models/User.php:112) `hasPermission()` resolves the
  acting user's role via `$this->role->permissions` (Eloquent relations).
- With the scope active, a role whose `store_id` differs from the acting user's
  `store_id` becomes invisible → `$this->role` is `null` → permission denied.
- This is structurally incompatible: `role_id === 1` (Super Admin) is the
  convention ([`User.php:103`](../app/Models/User.php:103) `isSuperAdmin()`), and
  roles/permissions are **authorization config**, not store business data.

These two models were therefore reverted in Batch 9 and are **excluded** here.

---

## 4. Decision

**Do NOT apply `StoreScoped` to `User`, `DbRole`, or `DbPermission`.**

| Model | Reason |
|---|---|
| `User` | **Confirmed infinite recursion** during session auth resolution (probe depth 5). |
| `DbRole` | Breaks `hasPermission()` cross-store resolution (role's store_id ≠ user's store_id). |
| `DbPermission` | Same as `DbRole` — auth config, not business data. |

No code changes were made in Phase 1.5; the working tree is unchanged from the
end of Phase 1.4.

---

## 5. Safer alternatives (future work)

- For **user list management**: the controllers already scope user queries
  explicitly, or can call `scopeAllStores()` for cross-store super-admin views
  without a global scope.
- If a global scope on `User` is ever required, it must guard the closure so it
  **does not call `auth()->check()` while the guard is mid-resolution** (e.g. a
  static "resolving" flag), or resolve the store id without touching the guard.
- Keep `isSuperAdmin()`/`hasPermission()` on unscoped role/permission lookups.

---

## 6. Verification

The auth/login/permission suites run green with the current (unscoped) models;
see the Phase 1.4 baseline: `16 failed / 952 passed (5255 assertions)` — all 16
are the known pre-existing flaky parallel/node-check set plus the pre-existing
SMTP 403, none auth-related.
