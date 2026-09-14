# Multi-Store Follow-ups (post Phase 0–4)

**Status:** Phases 0–3 and 4.1/4.3/4.4 are implemented and verified green on both
SQLite (1325 passed) and MySQL (1309 passed / 16 skipped SQLite-only harnesses).

This file tracks the items that were **deliberately deferred** and are not yet done.

## 1. Phase 4.2 — Deprecate `db_items.stock`

**Current state:** stock is dual-written to both `db_items.stock` (legacy aggregate)
and `db_warehouseitems.available_qty` (per-warehouse truth). Both are written across
sale / purchase / return / adjustment / quotation / item flows (the dual-write sites
start at [`PosController`](../app/Http/Controllers/PosController.php:494)).

**Why deferred:** this is a broad, cross-cutting refactor. Doing it partially would
let the two figures diverge (the exact failure mode the gap analysis warns about), so
it must be done as one complete piece of work:
1. Audit every read of `db_items.stock` and repoint it to `db_warehouseitems`
   (summed per item/warehouse as appropriate).
2. Stop writing `db_items.stock` once nothing reads it.
3. Keep the column (denormalised cache) or drop it in a later migration — decide
   based on how many hot read paths benefit from the cached aggregate.

**Risk:** Medium. Touches POS, purchase, returns, adjustments, quotations and items.

## 2. Phase 4.5 — Per-store invoice templates

Not built. The gap analysis marks this optional and only worth doing if document
branding becomes a real requirement.

## 3. Customer sharing (still a blocking decision)

Unchanged from the gap analysis: whether `db_customers` stays `StoreScoped`, whether
`customer_code` is per-store or global, and whether loyalty/dues/advance follow a
customer across branches remains **undecided**. As a result:
- Phase 3.2 consolidated reporting deliberately **excludes** customer/due rollups.
- No customer-scoped multi-store work should be started until the owner decides.

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
