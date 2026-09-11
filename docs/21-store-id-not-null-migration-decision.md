# Phase 1.4 — `store_id` NOT NULL Migration — Analysis & Decision

**Status:** ❌ NOT APPLIED — migration discarded as incompatible with the codebase.
**Date:** 2026-09-11
**Related commits:** `d0068a2` (trait `orWhereNull` change), `d796e54`…`4018d24` (StoreScoped model batches 1–10)

---

## 1. Objective

Enforce `NOT NULL` on the `store_id` column across every store-scoped table,
using the **real** table list (not a guessed 8-table list), after verifying no
existing NULL rows would be corrupted.

---

## 2. Authoritative schema probe (read-only)

Script (temporary, removed after use): enumerates
`INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'store_id'` and counts
`WHERE store_id IS NULL` per table.

**Result — live MySQL database `laravelpos`:**

- **58 tables** have a `store_id` column.
- **57 are NULLABLE**; `cash_drawer_reconciliations` is already `NOT NULL` (default `1`).
- **ZERO tables contain any NULL `store_id` rows.**

The 58-table set matches the Phase-0 audit list in
[`20-store-settings-rollout-report.md`](20-store-settings-rollout-report.md).
Notably, `db_currency`, `db_country`, and `db_languages` are **not** in the set
(they have no `store_id` column), confirming the Batch-10 exclusions.

So the requested data-safety condition was satisfied: **no STOP condition** —
zero NULLs everywhere.

---

## 3. Migration was written, then rejected

A migration covering the 57 NULLABLE tables (with a runtime NULL-guard that
aborts rather than silently backfilling to `store_id = 1`) was authored, linted,
and **never executed**. It was deleted after the conflict analysis below.

---

## 4. Why the migration is incompatible

### 4.1 The test suite would collapse

`phpunit.xml` runs on **`:memory:` SQLite** with `RefreshDatabase`, which rebuilds
the schema from migrations for every test. Making `store_id` `NOT NULL` **with no
default** on 57 tables means every fixture insert that omits `store_id` would
throw `NOT NULL constraint failed: <table>.store_id`.

**76 test files** create core business models without `store_id`, e.g.:

```php
// tests/Feature/SalesAccountingLedgerTest.php:69
$category = DbCategory::create(['category_name' => 'Goods', 'status' => 1]);
```

Applying the migration would break a large portion of the previously-green suite —
the exact opposite of the Phase 1.3 outcome (16 pre-existing failures, unchanged).

### 4.2 NULL `store_id` is a deliberate *production* semantic, not just a test artifact

The application intentionally treats `NULL store_id` as a **global / shared row**
that any store may use ("current OR null" reads). This is used in production
controllers, for example:

- [`app/Http/Controllers/ItemController.php:134`](../app/Http/Controllers/ItemController.php:134) — `DbUnit::where(fn($w) => $w->where('store_id', current_store_id())->orWhereNull('store_id'))`
- [`app/Http/Controllers/TaxController.php:41`](../app/Http/Controllers/TaxController.php:41) — shared tax rows
- [`app/Http/Controllers/PurchaseController.php:603`](../app/Http/Controllers/PurchaseController.php:603) — taxes, payment types, accounts, categories, units, brands, suppliers, warehouses
- [`app/Http/Controllers/ExpenseController.php:236`](../app/Http/Controllers/ExpenseController.php:236) — "legacy rows created before multi-store (store_id NULL)"

A `NOT NULL` constraint would make it **impossible to create new global/shared
rows**, breaking that read pattern at the write boundary. This is the same
semantic that forced the Phase 1.3 trait change to
`(store_id = X OR store_id IS NULL)` in [`StoreScoped.php`](../app/Models/Traits/StoreScoped.php).

### 4.3 Tension with the trait

The `StoreScoped` global scope explicitly preserves NULL-store_id rows via
`orWhereNull`. A `NOT NULL` constraint would make that branch dead for all
current and future data — the two changes are contradictory.

---

## 5. Decision

**Do not apply the `NOT NULL` migration.** It is incompatible with:

1. the test suite's migration-driven schema + NULL-store_id fixtures, and
2. the production convention of NULL `store_id` = global/shared row (queried via `orWhereNull`).

The migration file and the probe script were **removed**; the working tree is clean.

---

## 6. Recommendation for data integrity instead

If store-scoping integrity is desired, prefer approaches that do **not**
contradict the global-row convention:

- Keep the application-level `(store_id = X OR store_id IS NULL)` scope (already shipped in Phase 1.3).
- Enforce `store_id` on **write** paths in controllers (most already do via `current_store_id()`).
- If a DB constraint is still wanted, scope it to **strict business tables only**
  (sales, purchases, expenses, holds, quotations, items, customers, accounts,
  transfers, stock) and **exclude** shared-lookup tables (tax, units, payment
  types, roles, permissions) — and update the 76 fixture files accordingly.
  This is a larger, deliberate change requiring sign-off, not a drop-in migration.
