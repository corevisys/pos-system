# Warehouse Rollout — Gap Coverage Report (4 Gaps)

> **Scope:** Test-only pass closing four coverage gaps flagged in the
> [`docs/12c-warehouse-rollout-report.md`](docs/12c-warehouse-rollout-report.md:1).
> **No production code was changed.** Each gap reports the exact test name,
> exact assertion, and whether it passed on the FIRST run (claim already correct)
> or required a fix first (a real gap existed).
>
> **Suite results:** [`tests/Feature/WarehouseRolloutTest.php`](tests/Feature/WarehouseRolloutTest.php:1)
> (18 + 4 gap tests) + [`tests/Feature/WarehouseMigrationDuplicatePreCheckTest.php`](tests/Feature/WarehouseMigrationDuplicatePreCheckTest.php:1)
> (GAP 3, isolated) = **24 tests / 113 assertions, all pass.**

---

## GAP 1 — Soft-deleted warehouses must not leak back into index()/stats

**Test:** `test_gap1_soft_deleted_warehouse_excluded_from_index_and_stats`
(in [`WarehouseRolloutTest.php`](tests/Feature/WarehouseRolloutTest.php))

**Flow & exact assertions:**
1. Seed 3 warehouses (`Gap1-A`, `Gap1-B`, `Gap1-C`).
2. Baseline GET `warehouse.list` → `viewData('stats')['total'] === 3`;
   `assertSee('Gap1-A')` / `assertSee('Gap1-C')`.
3. Soft-delete `Gap1-B` via the **real destroy() flow** →
   `assertSessionHas('success')`; precondition `delete_bit === 1`.
4. After GET `warehouse.list` →
   `stats['total'] === 2` (exact 3→2, not "still renders"),
   `assertSame(3, $stats['total'] + 1)` (exact delta),
   `assertDontSee('Gap1-B')`, `assertSee('Gap1-A')`, `assertSee('Gap1-C')`.

**First-run result: ✅ PASSED** — `index()` already had
`->where('delete_bit', 0)` ([`WarehouseController.php:91`](app/Http/Controllers/WarehouseController.php:91))
and `stats` add `where('delete_bit', 0)` ([:151-153](app/Http/Controllers/WarehouseController.php:151)).
No fix needed.

---

## GAP 2 — Store scope + permission must compose

**Test A (blocked path):** `test_gap2_store_b_with_delete_permission_still_blocked_from_store_a_warehouse`

- Store-B user **WITH** `warehouse_edit` + `warehouse_delete` attempts
  `PUT warehouse.update/{storeA_id}` → `assertNotFound()`; row name unchanged
  (`assertSame('Gap2-StoreA', ...)`).
- Same user `DELETE warehouse.destroy/{storeA_id}` → `assertNotFound()`;
  `delete_bit` still 0.
- Proves: having the right permission does **not** bypass store scoping.

**Test B (control):** `test_gap2_store_a_with_permission_can_edit_and_delete_own_warehouse`

- Store-A user with `warehouse_edit`/`warehouse_delete` on own warehouse:
  `PUT` → `assertRedirect(route('warehouse.list'))`, name becomes
  `Gap2-Own-Renamed`; `DELETE` → `assertSessionHas('success')`,
  `delete_bit === 1`.
- Proves the two guards don't over-block a legitimately permitted same-store
  action.

**First-run result: ✅ PASSED** — `destroy()` has the permission gate
([`WarehouseController.php:270`](app/Http/Controllers/WarehouseController.php:270)) plus
`scopedWarehouse()` ([:277](app/Http/Controllers/WarehouseController.php:277));
`update()` has the same pair ([:211](app/Http/Controllers/WarehouseController.php:211), [:215](app/Http/Controllers/WarehouseController.php:215)).
No fix needed.

---

## GAP 3 — Phase 5 migration pre-check aborts on same-store duplicates

**Test:** `test_gap3_phase5_migration_assert_no_duplicates_aborts_on_same_store_duplicate`
(in the isolated [`WarehouseMigrationDuplicatePreCheckTest.php`](tests/Feature/WarehouseMigrationDuplicatePreCheckTest.php:1))

**Flow & exact assertions:**
1. Build a scratch SQLite DB with the **pre-Phase-5 schema** (plain indexes,
   NO `(store_id, warehouse_name)` unique) — the exact dirty-legacy state.
2. Seed `DupName` twice in Store 1 (duplicate) and once in Store 2
   (legitimate cross-store reuse) via direct inserts.
3. Run the **real** `2026_09_10_000002_...` migration `up()` against it.
4. Asserts:
   - The migration **must** throw `\RuntimeException` (via `expectException` +
     the `try/catch` fail branch); message contains both
     `Pre-existing duplicate db_warehouse.warehouse_name` and
     `db_warehouse_store_warehouse_name_unique`.
   - The unique index was **not** added
     (`sqlite_master` lookup returns `assertFalse`).
   - Both duplicate rows still exist (no silent dedupe) — `COUNT(*) === 2`.

**First-run result: ⚠️ needed a test-harness fix, not a production fix.**
- The first version seeded duplicates into the main `RefreshDatabase` :memory:
  DB, which **already has the Phase-5 unique index**, so the duplicate insert
  failed at the DB level before the pre-check could run
  (`UniqueConstraintViolationException`). That's a harness artifact, not a
  migration bug.
- Running the real migration against a scratch pre-Phase-5 schema requires
  swapping the DB connection. The first swap corrupted sibling tests'
  `RefreshDatabase` transaction state ("cannot VACUUM from within a
  transaction", "table migrations already exists"). Fixed by isolating the
  test in its own class **and** using a dedicated named connection
  (`wh_gap3`) so the framework's default `:memory:` connection is never
  purged.
- The migration's pre-check itself was **already correct** (throws
  `RuntimeException` at [`2026_09_10_000002_...:37-41`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:37)).
  No production change needed.

---

## GAP 4 — Stock threshold edge cases

**Test A:** `test_gap4_item_row_at_zero_qty_blocks_delete_total_items_reading`

- Warehouse has 1 `db_warehouseitems` row with `available_qty = 0`
  (`total_items = 1`, qty sums to 0).
- `DELETE` → `assertSessionHas('error')`; message contains `1 item(s)`;
  `delete_bit` stays 0.

**Test B:** `test_gap4_negative_available_qty_blocks_delete`

- Warehouse has 1 `db_warehouseitems` row with `available_qty = -3`.
- `DELETE` → `assertSessionHas('error')`; `delete_bit` stays 0.

**Intentional reading (state which the fix uses):** the guard condition is
`$stock['total_items'] > 0 || $stock['available_qty'] > 0`
([`WarehouseController.php:281`](app/Http/Controllers/WarehouseController.php:281)).
"**Holds stock**" is read as **item rows exist** (`total_items > 0`) OR
**quantity present** (`available_qty > 0`). The `||` means the zero-qty-with-
item-row case and the negative-qty case BOTH block deletion — an item row
occupying the warehouse (even at 0/negative balance) prevents delete, which is
the conservative, intentional reading. **No production change was needed.**

**First-run result: ✅ PASSED** (both) once GAP 3 stopped corrupting the shared
connection — the tests themselves were correct from the start.

---

## Summary

| Gap | Test | Production fix needed? | First-run status |
|---|---|---|---|
| 1 — index()/stats delete_bit=0 | `test_gap1_soft_deleted_warehouse_excluded_from_index_and_stats` | no | ✅ passed |
| 2 — store scope + permission compose | `test_gap2_store_b_with_delete_permission_...` + `test_gap2_store_a_with_permission_...` | no | ✅ passed |
| 3 — migration pre-check abort | `test_gap3_phase5_migration_assert_no_duplicates_...` | no (migration already aborts) | ⚠️ harness fix (isolated class + dedicated connection) |
| 4 — stock threshold edge cases | `test_gap4_item_row_at_zero_qty_...` + `test_gap4_negative_available_qty_...` | no (intentional `total_items>0 \|\| available_qty>0` reading) | ✅ passed |

**Bottom line:** the Phase 1–7 report's claims were correct. All four gaps were
missing test coverage only; the only "fix" required was test-harness isolation
for the migration test (separate class + dedicated connection). No production
code was touched.
