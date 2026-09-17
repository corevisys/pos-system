# MULTI-STORE GAP ANALYSIS — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-16
**Companion document:** [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (current-state map).
**Purpose:** state exactly what exists today versus what is required to safely operate multiple independent stores/branches, precise enough to implement from directly.
**Method:** every statement was read directly from source this pass; citations use `path:line`; uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`.

> **Material change since the last audit (2026-09-13):** the multi-store programme described here as "remaining work" has largely **shipped**. Phases 2 (roles/acting store), 3 (consolidated reporting), 4 (stock source of truth + dead-code removal) and 5 (shared customer identity) are implemented and covered by passing tests. The blocking "customer sharing" decision was **made and implemented**. Details below with evidence.

---

## 1. Executive summary

**The tenant data model, the role model, the acting-store context, consolidated reporting and customer identity sharing are all implemented and verified.** What remains is small, specific and listed in §7.

### Confirmed model (owner decisions — now implemented)

1. **Stores stay fully independent.** Each `db_store` owns its own items, customers, suppliers and ledger. No shared catalogue.
2. **One store = one business; multiple outlets = multiple warehouses.** Implemented via warehouse-to-warehouse transfer.
3. **Single currency (BDT) / single active language.** Enforced by data-fix migrations and by runtime tests ([`SingleActiveCurrencyTest.php`](tests/Feature/SingleActiveCurrencyTest.php:1), [`SingleActiveLanguageTest.php`](tests/Feature/SingleActiveLanguageTest.php:1)).
4. **Per-store sequential numbering.** Implemented for every entity including `purchase_return` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167)).
5. **No inter-store stock transfer.** `to_store_id` columns dropped ([`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27)).
6. **Branches isolated; only the Owner has cross-store visibility.** Implemented.
7. **Branches file tax independently** via their own `db_store.gst_no`.
8. **No production data** — no backfill needed. Future imports must specify the store explicitly; never rely on the `default_store_id()` fallback ([`helpers.php`](app/Helpers/helpers.php:24)).
9. **Customer sharing: DECIDED — shared identity only** (see §5).

---

## 2. Decision Register (all decisions resolved)

| # | Question | Decision | Implementation status | Evidence |
|---|---|---|---|---|
| 1 | Shared or independent catalogue? | **Fully independent** | As-is (no work needed) | [`StoreScoped.php`](app/Models/Traits/StoreScoped.php:23) |
| 2 | Shared or independent customers? | **Shared identity only** (option c) | **IMPLEMENTED** | [`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29), [`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:25) |
| 3 | HQ role definition | **Three roles: Branch Admin / Owner / Developer** | **IMPLEMENTED** | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:169), [`…:179`](database/seeders/RolePermissionSeeder.php:179) |
| 4 | Multi-currency? | **Single currency (BDT)** | Enforced + tested | [`SingleActiveCurrencyTest.php`](tests/Feature/SingleActiveCurrencyTest.php:1) |
| 5 | Numbering policy | **Per-store sequential** | Implemented for all types | [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167) |
| 6 | Inter-store transfers? | **Not needed** | Dead columns removed | [`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27) |
| 7 | Inter-store visibility | **Branches isolated; Owner-only cross-store** | **IMPLEMENTED** | [`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:114) |
| 8 | Tax filing per store | **Independent per branch** | As-is | `db_store.gst_no` |
| 9 | Legacy data attribution | **None needed (no prod data)** | N/A | — |
| 10 | Legacy warehouse-less stock | **Retain `db_items.stock`** as authoritative fallback | **IMPLEMENTED** | [`DbItem.php`](app/Models/DbItem.php:114) |
| 11 | Per-store invoice templates | **Deferred / optional** | Not built | see §7 |

**No decision in this register remains OPEN.** The previously blocking customer-sharing decision (item 2) is resolved and implemented.

---

## 3. Role Model — implemented

Three genuinely distinct capability sets, with **binary** access (one branch, or the Owner/Developer cross-store identities). No `user_store` pivot.

| Role | Scope | Implementation | Evidence |
|---|---|---|---|
| **Branch Admin** | Own branch only | Seeded as `Super Admin` per store with `is_super_admin = false`, `is_owner = false`; constrained to its own store by `SetCurrentStore::canActAs()` | [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:69), [`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:73) |
| **Owner** | All stores, consolidated reporting | `is_owner = true`; permissions limited to `dashboard_view`, `reports_view`, `store_settings_view`; **does not bypass permission checks** | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:169), [`User.php`](app/Models/User.php:134), [`User.php`](app/Models/User.php:149) |
| **Developer** | System/maintenance super-admin | Sole holder of `is_super_admin = true`; separate seeded account | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:184), [`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:57) |

**Verified invariants** ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:1)):
- Branch admin is neither super admin nor owner ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:76)).
- A branch admin **cannot** act as another store even with a forged session value ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:87)).
- Owner has cross-store visibility but is **not** a super admin ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:100)).
- Owner does **not** bypass permission checks ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:111)).
- Developer is globally privileged.

The privilege-escalation defect previously tracked as ISSUE-8 (all three per-store admins globally privileged) is **closed**: cleared by migration ([`2026_09_13_000003`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:28)), by seeder ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:75)), and by `RolePermissionSeeder` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:197)).

---

## 4. Gap tables (re-verified this pass)

Legend: **P0** blocker · **P1** important · **P2** nice-to-have · ✅ closed.

### 1. Data model & scoping ✅

| Current State (evidence) | Requirement | Status |
|---|---|---|
| Every operational table carries `store_id`; the strict `StoreScoped` scope filters to the acting store with **no NULL escape hatch** ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:23)). SMS tables store-scoped and NOT NULL. | Stores independent; every read path store-scoped. | **Met.** One deliberate exception: `db_customer_identities` is global by design ([`CustomerIdentity.php`](app/Models/CustomerIdentity.php:14)). `activity_logs.store_id` is deliberately nullable. |

### 2. Uniqueness rules ✅

| Current State (evidence) | Status |
|---|---|
| Per-store composite uniques on items/`item_code`, SKU/barcode, reconciliation code, the 9 code-bearing tables, categories/brands/variants, suppliers `(store_id, mobile)`/`(store_id, email)`, warehouses, accounts, roles `(store_id, role_name)`, SMS rules `(store_id, event_type)`. | **Met.** |
| `purchase_return` numbering through the sequential generator. | **Met** — [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167). |
| Customer mobile/email uniqueness per store. | **Met at the validation layer**, not by a DB index: `Rule::unique(...)->where('store_id', …)` ([`CustomerController.php`](app/Http/Controllers/CustomerController.php:141), [`…:644`](app/Http/Controllers/CustomerController.php:644)). `db_customers.mobile` is a plain index ([`create_db_customers_table.php`](database/migrations/2026_02_07_085626_create_db_customers_table.php:62)). `[INFERENCE]` This is sufficient for the app's own write paths but a raw SQL/import path bypassing validation would not be protected by the database. |

### 3. Accounts & ledger isolation ✅

| Current State (evidence) | Status |
|---|---|
| `ac_accounts`/`ac_transactions` are `StoreScoped`; system contra accounts found-or-created per `(store_id, system_key)` with unique index + lock + retry ([`AcAccount.php`](app/Models/AcAccount.php:44)). | **Met.** |
| No consolidated ledger view previously. | **CLOSED** — `ConsolidatedReportController::ledger()` reads across stores with `withoutGlobalScope('store_id')` grouped by `store_id`, gated to `canViewAllStores()` ([`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:32), [`…:114`](app/Http/Controllers/ConsolidatedReportController.php:114)). |

### 4. Users, roles & permissions ✅

| Current State (evidence) | Status |
|---|---|
| A user has exactly one `store_id`; binary access; no `user_store` pivot. | **Met.** |
| Roles are per-store rows with `(store_id, role_name)` unique. | **Met** ([`RoleNamePerStoreUniqueTest.php`](tests/Feature/RoleNamePerStoreUniqueTest.php:1)). |
| `isSuperAdmin()` reads only `is_super_admin`; `isOwner()` reads only `is_owner`; `canViewAllStores()` = either. | **Met** ([`User.php`](app/Models/User.php:122), [`…:134`](app/Models/User.php:134), [`…:144`](app/Models/User.php:144)). |
| One canonical permission-slug vocabulary; every state-changing route gated. | **Met** ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:16), [`ControllerPermissionGateSweepTest.php`](tests/Feature/ControllerPermissionGateSweepTest.php:1)). |

### 5. Stock & transfers ✅ (one residual)

| Current State (evidence) | Status |
|---|---|
| Stock in `db_warehouseitems`; warehouse belongs to a store; transfer between warehouses of the same store. | **Met.** |
| `db_items.stock` dual-write previously flagged. | **CLOSED as a documented contract** — `DbItem::availableStock()` is the single canonical rule; `db_items.stock` is authoritative for warehouse-less items and never zeroed ([`DbItem.php`](app/Models/DbItem.php:114), [`…:148`](app/Models/DbItem.php:148)). Covered by [`ItemStockSourceOfTruthTest.php`](tests/Feature/ItemStockSourceOfTruthTest.php:1) and [`PosItemSearchStockTest.php`](tests/Feature/PosItemSearchStockTest.php:1). |
| `to_store_id` dead columns. | **CLOSED** — dropped ([`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27)). |
| Consolidated stock view for the Owner. | **IMPLEMENTED** — [`ConsolidatedReportController::stock()`](app/Http/Controllers/ConsolidatedReportController.php:69). |
| Residual | **EMI tables carry no `store_id`** ([`create_db_emi_tables.php`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:14)); isolation is transitive via `db_sales`. See §7. |

### 6. Reporting ✅

| Current State (evidence) | Status |
|---|---|
| Whole `reports/*` group gated by `permission:reports_view` and decorated by `report.export` ([`routes/web.php`](routes/web.php:364), [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:31)). | **Met.** All 24 report pages render ([`ReportPhase7RedesignTest.php`](tests/Feature/ReportPhase7RedesignTest.php:1)); exports are store-safe ([`ReportSharedExportTest.php`](tests/Feature/ReportSharedExportTest.php:1)). |
| No per-store selector / "All stores" mode previously. | **CLOSED** — consolidated endpoints accept `?store_id=` / `all`, Owner-gated only; branch admins get 403 regardless ([`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:127), [`ConsolidatedReportingTest.php`](tests/Feature/ConsolidatedReportingTest.php:1)). |

### 7. Settings ✅ (one residual)

| Current State (evidence) | Status |
|---|---|
| Store settings are per store; changes audited in `activity_logs`. | **Met** ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:23)). |
| SMTP credentials live on the store row and are **encrypted at rest**, with a migration encrypting pre-existing plaintext idempotently. | **Met** — verified live by `[VERIFY] G3/G3b` output in [`SettingsRolloutRuntimeVerificationTest.php`](tests/Feature/SettingsRolloutRuntimeVerificationTest.php:1). |
| Currency/language globally single-active. | **Met** ([`SingleActiveCurrencyTest.php`](tests/Feature/SingleActiveCurrencyTest.php:1), [`SingleActiveLanguageTest.php`](tests/Feature/SingleActiveLanguageTest.php:1)). |
| **Residual:** the Owner cannot edit a chosen branch's settings even after switching, because `resolveActingStore()` reads `auth()->user()->store_id` directly instead of `current_store_id()` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:25)). | See §7. |

### 8. Operational / session context — mostly ✅, residual P1

| Current State (evidence) | Status |
|---|---|
| Previously no acting-store context. | **IMPLEMENTED** — `StoreContext` singleton + `SetCurrentStore` middleware on the `web` group ([`bootstrap/app.php`](bootstrap/app.php:25), [`StoreContext.php`](app/Services/StoreContext.php:20), [`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:39)); `current_store_id()` reads the binding first ([`helpers.php`](app/Helpers/helpers.php:160)). Covered by [`SetCurrentStoreMiddlewareTest.php`](tests/Feature/SetCurrentStoreMiddlewareTest.php:1). |
| Owner store selector. | **Partially implemented.** Controller + routes exist and are correctly gated ([`StoreSelectorController.php`](app/Http/Controllers/StoreSelectorController.php:21), [`routes/web.php`](routes/web.php:60)), but **no Blade view posts to them** (content search across `resources/views` for `store-context`/`store_context`/`switch store`: 0 results). The Owner has no UI to switch. See §7. |

### 9. Messaging / notifications ✅

Per-store provider credentials, templates, rules, blacklist and duplicate suppression ([`SmsService.php`](app/SMS/Services/SmsService.php:34), [`…:71`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:84)); scheduler spans all stores and each job resolves its own store ([`routes/console.php`](routes/console.php:22)). **Met.** Residual: missing `config/sms.php` makes the sandbox/throttle knobs unreachable (see §7).

### 10. Authentication & login flow ✅

Login resolves to the acting store through `SetCurrentStore`; branch admins are binary-bound; Owner/Developer may target any store. `EnsureUserHasStore` correctly exempts super admin and Owner, and validates existence + active status for regular users ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:30), [`…:38`](app/Http/Middleware/EnsureUserHasStore.php:38), [`…:56`](app/Http/Middleware/EnsureUserHasStore.php:56)). **Met.**

### 11. Database strategy

Option **(a) single DB + `store_id` + scoping everywhere** remains the implemented and recommended strategy. Options (b) separate DB per store and (c) hybrid schemas remain not recommended.

### 12. Migration path & concurrency ✅

| Current State (evidence) | Status |
|---|---|
| Store-scoped tables migrated to NOT NULL `store_id` with a data-safety guard; per-store uniques swapped after duplicate pre-checks that abort loudly. | **Met.** |
| Concurrency previously unproven (12 race tests failing). | **CLOSED.** The full SQLite suite now passes with **0 failures** (`1346 passed, 6479 assertions, 149.92s` on 2026-09-16), and the MySQL gate runs the SQLite-only harnesses as explicit skips ([`tests/Pest.php`](tests/Pest.php:91)). |

---

## 5. Open Decision that was previously blocking — Customer Sharing: RESOLVED

**Status: DECIDED (option c — shared identity only) and IMPLEMENTED as Phase 5.**

| Element | Implementation | Evidence |
|---|---|---|
| Shared identity table | `db_customer_identities`, **globally unique `phone`**, deliberately not store-scoped | [`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:25), [`CustomerIdentity.php`](app/Models/CustomerIdentity.php:14) |
| Link from the store-scoped row | `db_customers.customer_identity_id` (nullable, indexed) | [`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:45) |
| Single sanctioned cross-store read | `App\Services\CustomerIdentityResolver` with phone normalisation | [`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29), [`…:78`](app/Services/CustomerIdentityResolver.php:78) |
| Write-path wiring | customer create / update / quick-add / save-step / CSV import | [`CustomerController.php`](app/Http/Controllers/CustomerController.php:158), [`…:279`](app/Http/Controllers/CustomerController.php:279), [`…:532`](app/Http/Controllers/CustomerController.php:532), [`…:699`](app/Http/Controllers/CustomerController.php:699), [`…:1058`](app/Http/Controllers/CustomerController.php:1058) |
| Dues/loyalty/history stay per store | verified | [`SharedCustomerIdentityTest.php`](tests/Feature/SharedCustomerIdentityTest.php:128) |
| No cross-store customer endpoint | verified | [`SharedCustomerIdentityTest.php`](tests/Feature/SharedCustomerIdentityTest.php:1) |
| Per-store duplicate handling unchanged | verified | [`SharedCustomerIdentityTest.php`](tests/Feature/SharedCustomerIdentityTest.php:91) |

**Consequences now settled:** `db_customers` stays `StoreScoped`; `customer_code` stays `(store_id, customer_code)` unique; dues/advance remain per store; consolidated reporting deliberately **excludes** customer/due rollups ([`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:20)).

---

## 6. Prioritised Action List (rebuilt)

### P1 — important

1. **Expose the Owner store selector in the UI** (ISSUE-14). The controller/routes are done and gated ([`StoreSelectorController.php`](app/Http/Controllers/StoreSelectorController.php:21), [`routes/web.php`](routes/web.php:60)); a Blade control must POST to `store.context.update` and the sidebar should show the currently acting store. Without this the Owner cannot switch acting store.
2. **Make `StoreSettingsController::resolveActingStore()` honour the acting-store context** (ISSUE-15). It currently reads `auth()->user()->store_id` directly ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:25)), so an Owner cannot configure a chosen branch even after switching. Change to `current_store_id()` with the same store-scoping guard.
3. **Add `config/sms.php`** (ISSUE-12). `config('sms.sandbox')` ([`SmsService.php`](app/SMS/Services/SmsService.php:24)) and `config("sms.throttle.{$provider}")` ([`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:39)) are read but no config file exists, so both always resolve to inline defaults.

### P2 — nice-to-have

4. **De-duplicate the migration timestamp** `2026_09_13_000003` (ISSUE-13) — two migrations share it.
5. **Consider a DB-level per-store unique on `db_customers.mobile`** (gap 2) if non-validation write paths are ever introduced.
6. **Consider adding `store_id` to the EMI tables** (ISSUE-16) to remove reliance on the transitive `db_sales` join.
7. **Optional: per-store invoice templates** (gap 7) — only if document branding becomes a requirement.
8. **Verify the Font Awesome spinner** in the authenticated layout (ISSUE-9) — either load FA in the app layout or replace the spinner glyph with an inline SVG.

> **Out of scope (decided):** shared catalogue, per-store currency/language, inter-store stock transfer, per-user store mapping (`user_store` pivot), legacy backfill.
