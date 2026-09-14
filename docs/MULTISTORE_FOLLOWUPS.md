# Multi-Store Follow-ups (post Phase 0–4)

**Status:** Phases 0–3 and 4.1/4.3/4.4 are implemented and verified green on both
SQLite (1325 passed) and MySQL (1309 passed / 16 skipped SQLite-only harnesses).

This file tracks the items that were **deliberately deferred** and are not yet done.

## 1. Phase 4.2 — Stock source of truth — IMPLEMENTED (2026-09-14, pragmatic form)

**Finding (from the mandated 4.2.0/4.2.1 pre-work):** the plan's "drop the column"
premise was unsafe. `db_items.stock` is NOT vestigial: it is the ONLY stock figure for
a large, supported class of items that have **no `db_warehouseitems` rows at all**
(opening-stock items created without a warehouse; seeded items). The new read-only
`items:compare-stock-sources` command reported **45 warehouse-less items** and **0
mismatches** where warehouse rows exist.

**Chosen model (implemented):**
- A single canonical rule lives on the model:
  `DbItem::availableStock(?int $warehouseId = null)` — the requested warehouse's
  `available_qty` when a row exists, otherwise `SUM(available_qty)` across the item's
  warehouses, otherwise falls back to `db_items.stock` for warehouse-less items. It
  uses the eager-loaded relation when present (no N+1).
- `DbItem::syncGlobalStock($itemId)` is the single aggregation that keeps
  `db_items.stock == SUM(available_qty)` for items WITH warehouse rows, and leaves
  warehouse-less items untouched (never zeroes them).
- Read sites routed through the helper: POS availability check
  (`PosController`), SMS LowStock decision + payload (`SmsTriggerService`), items-list
  screen/print/export and `ItemController::syncGlobalStock`. The dashboard low-stock
  query keeps using the (provably equal) `db_items.stock` aggregate for its SQL
  ordering.
- **`db_items.stock` is kept, not dropped** — it is the documented authoritative figure
  for warehouse-less items and a safe denormalised aggregate otherwise.

**Verification:** SQLite 1334 passed; MySQL 1319 passed / 16 skipped. New tests in
`tests/Feature/ItemStockSourceOfTruthTest.php` cover warehouse-less fallback,
multi-warehouse summing, `syncGlobalStock` equality (and non-zeroing), and summed POS
availability.

**Not done (deliberate):** dropping the column, and backfilling warehouse rows for
legacy items — both require enforcing a warehouse on every create path (a launch-time
behaviour change) and are out of scope per the gap analysis.

## 2. Phase 4.5 — Per-store invoice templates

Not built. The gap analysis marks this optional and only worth doing if document
branding becomes a real requirement.

## 3. Customer sharing — DECIDED (2026-09-14): shared identity only

Option (c) was chosen and is implemented as **Phase 5**:
- New table `db_customer_identities` (NOT StoreScoped; globally unique `phone`) holds
  the person's identity; `db_customers.customer_identity_id` links each store-scoped
  row to it. Resolution is centralised in `App\Services\CustomerIdentityResolver`
  (the single sanctioned cross-store read).
- `db_customers` stays `StoreScoped`; dues/loyalty/sales history stay per store and do
  not carry over. Phone uniqueness on `db_customers` is now **per store** (it was
  global), which is what allows the same person to exist in two stores.
- Phase 3.2 consolidated reporting still deliberately **excludes** customer/due
  rollups (a cross-store customer view was explicitly deferred).

Still deferred (not built): consolidated Owner customer/due view; identity merge tooling
for corrected phone numbers.

## Verification commands

```
# SQLite (default)
php artisan test --parallel

# MySQL gate (dedicated throwaway database; never the dev DB)
php -r "$p=new PDO('mysql:host=127.0.0.1;port=3306','root','');$p->exec('DROP DATABASE IF EXISTS laravelpos_test');$p->exec('CREATE DATABASE laravelpos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
php vendor\bin\pest -c phpunit.mysql.xml
```

Note: 16 self-contained SQLite concurrency/migration harnesses are skipped on MySQL
via `skipUnlessSqlite()` — they build their own SQLite files and spawn `php` workers,
so they are not applicable to the MySQL gate.
