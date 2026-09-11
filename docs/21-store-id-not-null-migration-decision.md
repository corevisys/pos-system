# Phase 1.4 — `store_id` NOT NULL Migration — Analysis & Decision

**Status:** ⚠️ SUPERSEDED — initially NOT APPLIED (discarded), then APPLIED after the
live DB was confirmed EMPTY and the test suite was made compatible.
**Date:** 2026-09-11
**Related commits:** `d0068a2` (trait `orWhereNull` change), `d796e54`…`4018d24` (StoreScoped model batches 1–10), `33885f9` (NOT NULL migration + fixtures/production fixes)

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

---

## 7. UPDATE — Migration was ultimately applied (fresh DB)

The decision above was later **superseded** by the project owner:

- The live MySQL database was re-verified and found to be **completely empty**
  (0 rows in all 58 store_id tables), so no data-corruption risk existed.
- The owner instructed: *"apply koro database a kono data nei fresh migration
  korte paro"* (apply it — the database has no data, you can do a fresh migration).
- The migration [`2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php`](../database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php)
  was recreated and **run successfully** — all 58 tables now have `NOT NULL store_id`.
- The `:memory:` SQLite test suite (which rebuilds schema from migrations) was made
  compatible by adding explicit `'store_id' => 1` to:
  - `database/factories/UserFactory.php` (factory default),
  - **387 fixture create() call sites across 66 test files** (programmatic transform),
  - the `DashboardTest` `firstOrCreate`,
  - the `Phase2SettingsTogglesTest` `DbPermission::create`.
- The migration also exposed **latent production bugs** — writes that never set
  `store_id` — now fixed:
  - `CustomerController::saveStep()` (both basic-step and import paths) — was
    creating customers without `store_id`;
  - `RegisteredUserController::store()` — public registration created users
    without `store_id`;
  - `StateSeeder` — inserted states without `store_id`.

### Final state

- Live DB: **58/58 tables `NOT NULL store_id`, zero NULL rows** (verified post-migration).
- Full test suite: **16 failed / 952 passed (5255 assertions)** — identical to the
  known pre-existing flaky/baseline set; no new regressions.
- Commit: `33885f9` (71 files: migration + factory + 66 test files + 4 production/seed fixes).

---

## 8. Post-push review reconciliation (GAP 1 + GAP 2)

### GAP 1 — fixture transform did NOT neuter multi-store isolation tests

Search method: `findstr /S "store_id => 1" tests\Feature\*.php` cross-referenced
with `git diff ad7000f..HEAD` for transform-added lines, plus a definitive
per-line audit of every inserted `'store_id' => 1` (checking for a later
`store_id` key in the same array = PHP last-key-wins override).

**Result:** the transform only inserted `'store_id' => 1` at the **start** of
create/firstOrCreate arrays; every multiline array already carried a later
`store_id` key (override wins) **except two parameterized builders**, which were
genuinely neutered and are now fixed:

| File | Line | Fix |
|---|---|---|
| `tests/Feature/SalesListPageTest.php` | `makeSalesListSale()` | customer `store_id` → `$storeId` |
| `tests/Feature/PaymentsListPageTest.php` | `makePaymentsListFixture()` | warehouse + customer `store_id` → `$storeId`, and created the owning `db_store` row (the hardcoded `1` had masked a missing FK fixture) |

**StoreSettingsStoreScopingTest.php** — verified the Store-A/Store-B fixtures
kept distinct store ids: the transform's inserted `'store_id' => 1` is overridden
by the later `'store_id' => $this->storeA->id / $this->storeB->id` keys
(last-key-wins). All 10 tests pass (raw output in the Phase 1 report).

Multi-store suites re-run after fixes: `SalesListPageTest`, `PaymentsListPageTest`,
`StoreSettingsStoreScopingTest`, `CategoryBrandVariantRolloutTest`,
`StockModuleStoreScopeTest`, `EmiFlowFixesTest`, `ServiceStoreScopeTest`,
`ServiceDeleteStoreScopeTest`, `ItemDeleteStoreScopeTest`,
`CashReconciliationFixesTest`, `AccountsListFixesTest`, `DepositFixesTest`,
`DepositGapClosingTest`, `MoneyTransferFixesTest`, `WarehouseRolloutTest`,
`SupplierRedesignAndRisksTest`, `AdvanceTest`, `ReturnsListFixesTest`,
`ItemPosDropdownStoreScopeTest` → **all pass** (the two fixed suites now genuinely
exercise distinct store contexts).

### GAP 2 — +37 test count reconciled

Evidence:
- `php vendor/bin/pest --list-tests` → **968 tests listed** = 952 passed + 16 failed (matches the final run exactly).
- `git diff --name-status ad7000f..HEAD -- tests/` → **zero added test files** (all `M`, no `A`).
- `git grep -c "function test|test(" ad7000f -- tests` vs HEAD → **identical (116 files)**.
- `git ls-tree -r --name-only ad7000f -- tests/Feature tests/Unit` vs HEAD → **identical (118 files)**.

**Root cause of the "+37":** the 931 baseline (916 passed + 15 failed) was
measured in docs/20 at a fixed point mid-Store-Settings-rollout. The current 968
total includes all tests from **prior rollouts** (warehouse, stock, expenses,
categories/brands/variants, store settings, manager variants, services, serials,
etc.) that already existed at `ad7000f`. **Phase 1 itself added 0 test files and
0 test methods** — the count difference is entirely a baseline-timing artifact,
exactly like the Store Settings Gap-1 reconciliation, not a Phase 1 test addition.

### TODO (future phase — tracking only, no code change here)

**TODO:** Retire the `orWhereNull('store_id')` read paths in
`ItemController`, `TaxController`, `PurchaseController`, `ExpenseController`
(and the equivalent `(store_id = X OR store_id IS NULL)` fallback in
`StoreScoped`). After the NOT NULL migration (Phase 1.4) NULL store_id rows can
no longer be created, so the shared/global-row semantics are obsolete. Scheduled
for its own phase; do not touch in this round.
