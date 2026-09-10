# Settings-Area Modules Audit — Languages, Countries, States, Tax, Units, Payment Types, SMTP, Currency

> **ROLLOUT STATUS (Stage-3 fix/build/redesign): COMPLETE.**
> Phase 1 (store scoping), Phase 2 (delete guards), Phase 3 (permissions + SMTP
> credential security) and Phase 4 (design-system redesign) are implemented and
> verified. See the "ROLLOUT IMPLEMENTATION LOG" section at the end of this file.

**Scope:** Read-only audit. No code changed.
**Method:** Every claim cites exact file paths, line ranges, and function/class names.
**Known context honored:** `DbCurrency.status` and `DbLanguage.status` are GLOBAL single-active-record flags; the per-store selection is `db_store.currency_id` / `db_store.language_id`. This was re-confirmed and is **not** re-flagged as a bug. The same question was asked of the other six modules; findings are per-module below.

---

## 1. Languages

### A. Store-ID / Scoping

1. **store_id column:** NO. [`2026_02_07_090726_create_db_languages_table.php:14`](../database/migrations/2026_02_07_090726_create_db_languages_table.php:14) defines only `id`, `language`, `status`, timestamps.
2. **Global by design: YES.** [`DbLanguage::activateLanguage()`](../app/Models/DbLanguage.php:30) sets `status = 0` on **every** row (`self::query()->where('id', '!=', $languageId)->update(['status' => 0])`, line 36) then activates one row (line 39) — a global flip. The only per-store write is `DbStore::where('id', $targetStoreId)->update(['language_id' => ...])` (line 48), explicitly scoped to the acting store and documented as such (lines 22–25, 46–53). Matches the known Currency/Language pattern exactly.
3. N/A.
4. **Global-flag read path:** `LanguageController::index()` derives the banner from the full set, `DbLanguage::where('status', 1)->first()` ([`LanguageController.php:33`](../app/Http/Controllers/LanguageController.php:33)). A Store‑B activation changes the "Current System Language" banner Store‑A sees, but Store‑A's `db_store.language_id` is untouched, so its actual selection survives. **Confirmed: still holds, not a bug.**

### B. Standard Audit Sections

- **LOCATE:** [`LanguageController.php`](../app/Http/Controllers/LanguageController.php:1) (index 15, create 41, store 49, edit 85, update 94, activate 144, destroy 165). [`DbLanguage.php`](../app/Models/DbLanguage.php:1). Views `module/settings/languages/{index,create,edit}.blade.php`. Routes [`web.php:407-413`](../routes/web.php:407).
- **UI INVENTORY (index.blade.php):** Add Language btn-primary (30); search form (56–67); Active banner (36–53); columns Language Name/Status/Actions (75–77); row actions: Set Active (104), activate icon (114), Edit (118), Delete form (122), "Active (Locked)" (130); confirm modal (157–192). Create/edit forms: name + status (create:28–46; edit:28–55).
- **FUNCTIONAL:** All wired. Delete blocked for active ([`destroy`](../app/Http/Controllers/LanguageController.php:165) 169–179); deactivate-active blocked in update (105–115).
- **SUBMISSION/EDIT/DELETE:** Store creates `status = 0` then conditionally activates (56–68). Delete guarded only against the active row; no other dependents (only `db_store.language_id`, `onDelete('set null')` at [`2026_02_07_091820_create_db_store_table.php:108`](../database/migrations/2026_02_07_091820_create_db_store_table.php:108)).
- **PERMISSION GATES:** **Gap.** Sidebar gates `language_view` ([`app.blade.php:933`](../resources/views/layouts/app.blade.php:933)); GlobalSearch catalogs it ([`GlobalSearchController.php:144`](../app/Http/Controllers/GlobalSearchController.php:144)); but the slug is **not seeded** ([`PermissionSeeder.php:26-77`](../database/seeders/PermissionSeeder.php:26), [`RolePermissionSeeder.php:16-67`](../database/seeders/RolePermissionSeeder.php:16)) and the controller has **zero** `hasPermission()` checks.
- **STYLING:** ON baseline (`text-text-*`, `card`, `btn-primary`, `input-base`, `x-card`).

### C. High-Risk / Protected
- Only consumer is the store FK + banner. A redesign must preserve "status global, FK per-store".
- No in-use guard needed beyond the active-flag guard.

---

## 2. Countries

### A. Store-ID / Scoping

1. **store_id column:** NO. [`2026_02_07_085616_create_db_country_table.php:14`](../database/migrations/2026_02_07_085616_create_db_country_table.php:14): `id`, `country`, `added_on`, `status`, timestamps.
2. **Global by design: YES** — geographic reference data. Consumers read unscoped: [`CustomerController.php:110`](../app/Http/Controllers/CustomerController.php:110), [`SupplierController.php:185`](../app/Http/Controllers/SupplierController.php:185). FKs: customers `country_id`/`ship_country_id` → `set null` ([`…db_customers_table.php:67-68`](../database/migrations/2026_02_07_085626_create_db_customers_table.php:67)); suppliers `country_id` → `set null` ([`…db_suppliers_table.php:52`](../database/migrations/2026_02_07_091828_create_db_suppliers_table.php:52)); states `country_id` → **cascade** ([`…db_states_table.php:34`](../database/migrations/2026_02_07_091616_create_db_states_table.php:34)).
3. N/A.
4. **Global flag:** plain active/inactive toggle (multiple actives; count at [`CountryController.php:29`](../app/Http/Controllers/CountryController.php:29)). **Not** a single-active flag; no FK write; no Currency/Language-style cross-store risk.

### B. Standard Audit Sections

- **LOCATE:** [`CountryController.php`](../app/Http/Controllers/CountryController.php:1) (index 11, create 35, store 40, edit 54, update 60, destroy 74). [`DbCountry.php`](../app/Models/DbCountry.php:1). Views `countries_list.blade.php`, `add_country.blade.php`, `edit_country.blade.php`. Routes [`web.php:414-419`](../routes/web.php:414).
- **UI INVENTORY (list):** Add Country (17); stat cards (24–37); search (40–51); columns `#`/Country Name/Status/Action (59–62); x-dropdown Edit link (88) + Delete form (92–99). Add/Edit: country name + status.
- **FUNCTIONAL:** All wired/reachable.
- **SUBMISSION/EDIT/DELETE:** Store validates unique name + status, sets `added_on` (40–52). **Delete UNGUARDED** ([`CountryController.php:74-80`](../app/Http/Controllers/CountryController.php:74)) — no dependent check. Deleting a country **cascade-deletes its states** and nulls customers/suppliers' `country_id`.
- **PERMISSION GATES:** **Gap.** Sidebar `country_view` ([`app.blade.php:938`](../resources/views/layouts/app.blade.php:938)); GlobalSearch ([`GlobalSearchController.php:145`](../app/Http/Controllers/GlobalSearchController.php:145)); slug **unseeded**; controller has **no** checks.
- **STYLING:** ON baseline (`text-text-*`, `card`, `input-base`, `btn-primary`, `x-card`, `x-dropdown`).

### C. High-Risk / Protected
- Cascade delete of states is the concrete risk; add an in-use guard showing state/customer/supplier counts before blocking (Expenses/Warehouse convention).
- No formula depends on country data.

---

## 3. States

### A. Store-ID / Scoping

1. **store_id column: YES but DEAD.** [`2026_02_07_091616_create_db_states_table.php:16`](../database/migrations/2026_02_07_091616_create_db_states_table.php:16) (`store_id` nullable, indexed line 28) and `company_id` (line 23, indexed line 30). **Nothing sets or filters either.** [`StateController::store()`](../app/Http/Controllers/StateController.php:45) writes only `state/country_id/country/added_on/status`; index/update/destroy never scope by store. [`DbState`](../app/Models/DbState.php:7) has no store relation. Both columns are dead.
2. **Global by design: largely YES** — states are geographic reference data; all consumers read unscoped ([`CustomerController.php:111`](../app/Http/Controllers/CustomerController.php:111), [`SupplierController.php:186`](../app/Http/Controllers/SupplierController.php:186), [`StoreSettingsController.php:56`](../app/Http/Controllers/StoreSettingsController.php:56), [`getStates()`](../app/Http/Controllers/StoreSettingsController.php:66)). The dead `store_id` is an abandoned per-store attempt, not a live contract.
3. N/A (dead column — remove or deliberately wire, do not half-scope).
4. **Global flag:** plain status toggle; no single-active flag; no cross-store write.

### B. Standard Audit Sections

- **LOCATE:** [`StateController.php`](../app/Http/Controllers/StateController.php:1) (index 12, create 39, store 45, edit 62, update 69, destroy 87). [`DbState.php`](../app/Models/DbState.php:1) (`country()` 13–16). Views `states_list.blade.php`, `add_state.blade.php`, `edit_state.blade.php`. Routes [`web.php:421-426`](../routes/web.php:421).
- **UI INVENTORY (list):** Add State (17); stat cards (24–37); search (40–51); columns `#`/State/Country (2-letter badge)/Status/Action (59–63); row Edit (97) + Delete (101). Add/Edit: state name, `x-searchable-select` country (`add_state.blade.php:38`, `edit_state.blade.php:38`), status.
- **FUNCTIONAL:** All wired. Country dropdown filtered to `status=1` ([`StateController.php:41`](../app/Http/Controllers/StateController.php:41), 65).
- **SUBMISSION/EDIT/DELETE:** Store copies `country` name (53–55). **Delete UNGUARDED** ([`StateController.php:87-93`](../app/Http/Controllers/StateController.php:87)). `db_customers.state_id`/`db_suppliers.state_id` reference states via Eloquent ([`DbCustomer.php:85`](../app/Models/DbCustomer.php:85), [`DbSupplier.php:57`](../app/Models/DbSupplier.php:57)) but have **no FK constraint** — deleting a state **silently orphans** their `state_id`.
- **PERMISSION GATES:** **Gap.** Sidebar `state_view` ([`app.blade.php:943`](../resources/views/layouts/app.blade.php:943)); GlobalSearch ([`GlobalSearchController.php:146`](../app/Http/Controllers/GlobalSearchController.php:146)); slug **unseeded**; controller has **no** checks.
- **STYLING:** ON baseline (`text-text-*`, `card`, `input-base`, `x-searchable-select`, `x-dropdown`).

### C. High-Risk / Protected
- In-use guard missing: an assigned state can be deleted, orphaning `state_id`. Add usage count before blocking.
- [`StoreSettingsController::getStates()`](../app/Http/Controllers/StoreSettingsController.php:66) filters by the denormalized `country` **name** string (line 72); renaming a country changes the join key for states (which store both `country_id` and `country`). A redesign should not rely on the name column.

---

## 4. Tax

### A. Store-ID / Scoping

1. **store_id column: YES.** [`2026_02_07_092457_create_db_tax_table.php:16`](../database/migrations/2026_02_07_092457_create_db_tax_table.php:16); FK to `db_store` `onDelete('cascade')` (line 29).
2. **Should be scoped — currently PARTIALLY scoped, inconsistently:**
   - **Create scoped (imperfectly):** `'store_id' => auth()->user()->store_id ?? 1` ([`TaxController.php:62-63`](../app/Http/Controllers/TaxController.php:62)); falls back to store **1** when no store_id — wrong in multi-store; should use [`current_store_id()`](../app/Helpers/helpers.php:151) (PaymentTypeController already does).
   - **List NOT scoped:** [`index()`](../app/Http/Controllers/TaxController.php:14) — `DbTax::where('group_bit', 0)` / `= 1` with no store filter (22–25).
   - **Edit/Delete NOT scoped:** `findOrFail($id)` by global ID ([`95`](../app/Http/Controllers/TaxController.php:95), [`119`](../app/Http/Controllers/TaxController.php:119)).
   - **Unscoped dropdown consumers (recurring pattern):** [`SaleController.php:49-51`](../app/Http/Controllers/SaleController.php:49) (1-hour global `Cache::remember('db_taxes_list')`), [`SaleController.php:74`](../app/Http/Controllers/SaleController.php:74), [`PosController.php:60`](../app/Http/Controllers/PosController.php:60), [`ItemController.php:135`](../app/Http/Controllers/ItemController.php:135)/462, [`ImportsItems.php:59`](../app/Http/Controllers/Concerns/ImportsItems.php:59)/199. **Scoped correctly elsewhere:** [`PurchaseController.php:595-597`](../app/Http/Controllers/PurchaseController.php:595) (`store_id = current OR null`), [`QuotationController.php:66`](../app/Http/Controllers/QuotationController.php:66), [`ServiceController.php:121-122`](../app/Http/Controllers/ServiceController.php:121)/239–240.
3. **Consistency:** NOT consistent — settings CRUD unscoped; consumers split; the global tax cache is a cross-store staleness vector.
4. **Global-flag risk:** `status` is a plain toggle (no single-active flag). **But a cross-store contamination vector exists:** tax-group rate `DbTax::whereIn('id', $request->subtax_ids_array)->sum('tax')` ([`59`](../app/Http/Controllers/TaxController.php:59), [`101`](../app/Http/Controllers/TaxController.php:101)) sums **across all stores** and persists the sum on the group.

### B. Standard Audit Sections

- **LOCATE:** [`TaxController.php`](../app/Http/Controllers/TaxController.php:1) (index 14, store 44, update 86, destroy 117). [`DbTax.php`](../app/Models/DbTax.php:1). View `tax_list.blade.php` (two sections: Tax List 34–205, Tax Groups 207–400). Routes [`web.php:433-436`](../routes/web.php:433).
- **UI INVENTORY:** Shared search (19–30); "New Tax" (46) + Add Individual Tax modal (122–162); Tax columns Name/%(Status (57–60); row Edit (86) + Delete (91). Tax Groups: "New Tax Group" (219); columns Name/%/Sub Taxes/Status/Action (230–234); row Edit (268) + Delete (273); Add Group modal with sub-tax checkboxes (304–352); Edit Group modal (355–400). Note: in-cell sub-tax name lookup [`tax_list.blade.php:249`](../resources/views/module/settings/tax_list.blade.php:249) runs a `DbTax::whereIn(...)` **inside the loop** (N+1, unscoped).
- **FUNCTIONAL:** All wired/reachable. `destroy` uses global `findOrFail`/`delete`.
- **SUBMISSION/EDIT/DELETE:** `store` (44–81) handles individual (`group_bit=0`) and group (`group_bit=1`, sums subtaxes); `update` (86–112). **Delete UNGUARDED** ([`destroy`](../app/Http/Controllers/TaxController.php:117) 119–120): no check against `db_items.tax_id`, `db_salesitems.tax_id`, `db_purchaseitems.tax_id`, `db_quotationitems.tax_id`, `db_holditems.tax_id`, `db_purchase.other_charges_tax_id`, etc. Deleting a tax leaves those FKs dangling/zeroed.
- **PERMISSION GATES:** **Gap.** Sidebar gates `tax_view` ([`app.blade.php:953`](../resources/views/layouts/app.blade.php:953)); GlobalSearch catalogs `tax_view` ([`GlobalSearchController.php:148`](../app/Http/Controllers/GlobalSearchController.php:148)); `tax_view/add/edit/delete` ARE seeded ([`PermissionSeeder.php:49`](../database/seeders/PermissionSeeder.php:49), [`RolePermissionSeeder.php:39`](../database/seeders/RolePermissionSeeder.php:39)) but **`TaxController` checks none of them**.
- **STYLING:** ON baseline (`text-text-*`, `card`, `input-base`, `btn-primary`, `x-dropdown`).

### C. High-Risk / Protected
- **Tax calculation must not regress.** Item creation reads the rate live: [`ItemCreationService.php:66-68`](../app/Services/ItemCreationService.php:66) (`findOrFail($validated['tax_id'])`, `$taxRate = (float) $tax->tax`); [`ItemController.php:257`](../app/Http/Controllers/ItemController.php:257); [`PurchaseController.php:708-710`](../app/Http/Controllers/PurchaseController.php:708); [`QuotationController.php:149-151`](../app/Http/Controllers/QuotationController.php:149).
- **Historical snapshot confirmed:** `*_items.tax_amt` is persisted — [`db_salesitems_table.php:25`](../database/migrations/2026_02_07_090940_create_db_salesitems_table.php:25), [`db_purchaseitems_table.php:24`](../database/migrations/2026_02_07_090921_create_db_purchaseitems_table.php:24), [`db_quotationitems_table.php:25`](../database/migrations/2026_02_07_090934_create_db_quotationitems_table.php:25), [`db_holditems_table.php:24`](../database/migrations/2026_02_07_090718_create_db_holditems_table.php:24), [`db_salesitemsreturn_table.php:25`](../database/migrations/2026_02_07_090942_create_db_salesitemsreturn_table.php:25), [`db_purchaseitemsreturn_table.php:24`](../database/migrations/2026_02_07_090923_create_db_purchaseitemsreturn_table.php:24). **Editing a tax rate does not rewrite historical `tax_amt`, nor does the item's stored `tax_id` alter it.** A redesign must preserve this snapshot behavior.
- In-use guard missing on delete: should show which items/documents reference the tax before blocking (Expenses/Warehouse convention).

---

## 5. Units

### A. Store-ID / Scoping

1. **store_id column: YES.** [`2026_02_07_092503_create_db_units_table.php:16`](../database/migrations/2026_02_07_092503_create_db_units_table.php:16); FK to `db_store` `onDelete('cascade')` (line 27).
2. **Should be scoped — currently PARTIALLY scoped:**
   - **Create scoped (imperfectly):** `'store_id' => auth()->user()->store_id ?? 1` ([`UnitController.php:46-47`](../app/Http/Controllers/UnitController.php:46)) — same store-1 fallback flaw; should use `current_store_id()`.
   - **List NOT scoped:** [`index()`](../app/Http/Controllers/UnitController.php:13) — `DbUnit::query()` (15) with no store filter.
   - **Edit/Delete NOT scoped:** `findOrFail($id)` ([`76`](../app/Http/Controllers/UnitController.php:76), [`91`](../app/Http/Controllers/UnitController.php:91)).
   - **Unscoped dropdown consumers:** [`ItemController.php:134`](../app/Http/Controllers/ItemController.php:134)/461, [`ImportsItems.php:58`](../app/Http/Controllers/Concerns/ImportsItems.php:58)/198, [`PurchaseController.php:108`](../app/Http/Controllers/PurchaseController.php:108) (unscoped) vs [605-606](../app/Http/Controllers/PurchaseController.php:605) (scoped `store_id = current OR null`); [`QuotationController.php:68`](../app/Http/Controllers/QuotationController.php:68) (scoped).
3. **Consistency:** NOT consistent.
4. **Global-flag risk:** `status` is a plain toggle (no single-active flag; no default-unit flag). No Currency/Language-style risk.

### B. Standard Audit Sections

- **LOCATE:** [`UnitController.php`](../app/Http/Controllers/UnitController.php:1) (index 13, store 38, update 68, destroy 89). [`DbUnit.php`](../app/Models/DbUnit.php:1). View `units_list.blade.php`. Routes [`web.php:438-441`](../routes/web.php:438).
- **UI INVENTORY (units_list.blade.php):** New Unit (26); server-side search (36–44); columns Unit Name/Description/Status/Action (51–54); row x-dropdown Edit (80) + Delete (85). Add modal (116–156); Edit modal (158–198). Also consumed as a quick-add modal in items/purchase: [`add_item.blade.php:579`](../resources/views/module/items/add_item.blade.php:579), [`edit_item.blade.php:1110`](../resources/views/module/items/edit_item.blade.php:1110), [`new_purchase.blade.php:1383`](../resources/views/module/purchase/new_purchase.blade.php:1383).
- **FUNCTIONAL:** All wired. Quick-add posts to `settings.units.store` (AJAX branch at [`UnitController.php:53-60`](../app/Http/Controllers/UnitController.php:53)).
- **SUBMISSION/EDIT/DELETE:** Create/update validate name/description/status. **Delete UNGUARDED** ([`destroy`](../app/Http/Controllers/UnitController.php:89) 91–92): `db_items.unit_id` ([`…db_items_table.php:23`](../database/migrations/2026_02_07_090724_create_db_items_table.php:23)) is not checked — deleting a unit orphans every item's `unit_id`.
- **PERMISSION GATES:** **Gap.** Sidebar `unit_view` ([`app.blade.php:958`](../resources/views/layouts/app.blade.php:958)); GlobalSearch `unit_view` ([`GlobalSearchController.php:149`](../app/Http/Controllers/GlobalSearchController.php:149)); `units_view/add/edit/delete` seeded ([`PermissionSeeder.php:50`](../database/seeders/PermissionSeeder.php:50), [`RolePermissionSeeder.php:40`](../database/seeders/RolePermissionSeeder.php:40)) but **`UnitController` checks none**.
- **STYLING:** ON baseline (`text-text-*`, `card`, `input-base`, `btn-primary`, `x-dropdown`).

### C. High-Risk / Protected
- **No unit-conversion engine exists** (searched `conversion|convert` — no unit converter; only number-to-words and quotation convert). Units are a flat picklist, so no conversion formula is at risk.
- **Snapshot behavior:** document lines persist `price_per_unit` and tax separately; the unit is a label/relation, not a multiplier. Editing a unit name does not retroactively change historical lines.
- In-use guard missing on delete: show item reference count before blocking.

---

## 6. Payment Types

### A. Store-ID / Scoping

1. **store_id column: YES** (plain `integer`, no FK). [`2026_02_07_090730_create_db_paymenttypes_table.php:16`](../database/migrations/2026_02_07_090730_create_db_paymenttypes_table.php:16).
2. **Should be scoped — currently ONLY create is scoped:**
   - **Create scoped correctly:** `'store_id' => current_store_id()` ([`PaymentTypeController.php:29-30`](../app/Http/Controllers/PaymentTypeController.php:29)) — the *only* module in this audit that uses the helper correctly. (`CASH` is protected from edit/delete in the view, [`payment_types.blade.php:90`](../resources/views/module/settings/payment_types.blade.php:90).)
   - **List NOT scoped:** `DbPaymentType::all()` ([`index()`](../app/Http/Controllers/PaymentTypeController.php:13), line 15).
   - **Edit/Delete NOT scoped:** `findOrFail($id)` ([`48`](../app/Http/Controllers/PaymentTypeController.php:48), [`62`](../app/Http/Controllers/PaymentTypeController.php:62)).
   - **Unscoped consumers:** [`SaleController.php:53`](../app/Http/Controllers/SaleController.php:53)/75/663/672, [`PosController.php:61`](../app/Http/Controllers/PosController.php:61), [`PurchaseController.php:94`](../app/Http/Controllers/PurchaseController.php:94)/105/598, [`ExpenseController.php:29`](../app/Http/Controllers/ExpenseController.php:29) (`canonicalPaymentTypes`), [`QuotationController`](../app/Http/Controllers/QuotationController.php:1) consumers.
3. **Consistency:** NOT consistent.
4. **Global-flag risk:** `status` is a plain toggle; no single-active flag. **Contamination vector:** editing/deleting another store's payment type by ID is possible because update/destroy are unscoped; disabling `Cash` (via a direct POST that bypasses the view's `!= 'CASH'` guard) affects every store that depends on the canonical `Cash` literal ([`ExpenseController.php:20-30`](../app/Http/Controllers/ExpenseController.php:20) documents this canonicalization). No Currency/Language-style *single-active auto-flip* exists.

### B. Standard Audit Sections

- **LOCATE:** [`PaymentTypeController.php`](../app/Http/Controllers/PaymentTypeController.php:1) (index 13, store 22, update 41, destroy 60). [`DbPaymentType.php`](../app/Models/DbPaymentType.php:1). View `payment_types.blade.php`. Routes [`web.php:442-445`](../routes/web.php:442).
- **UI INVENTORY (payment_types.blade.php):** New Payment Type (25); **DECORATIVE table controls** — "Show" select with hard-coded 10/25 that is not wired to the controller (41–45), Copy/Excel/PDF/Cols buttons with no handlers (50–53), non-functional search input (placeholder only, no name/submit — 56–58); columns `#`/Name/Status/Action (67–70); Action **hidden for `CASH`** showing `-NA-` (90–109); row Edit (98) + Delete form (99). Add modal (128–164); Edit modal (166–202). Footer shows only `{{ $paymentTypes->count() }}` (no pagination — 123).
- **FUNCTIONAL:** Real actions (Add/Edit/Delete) WORK. Search box, Show-entries select, and Copy/Excel/PDF/Cols are DECORATIVE-DEAD (no `name`/`onclick`/handler).
- **SUBMISSION/EDIT/DELETE:** Create uses `current_store_id()` (correct). Update/destroy use global `findOrFail`. **Delete UNGUARDED** at the controller ([`destroy`](../app/Http/Controllers/PaymentTypeController.php:60) 62–63) — the `CASH` protection lives only in the Blade `@if` (view), not the server; `db_expense.payment_type` / `ac_transactions` store payment-type **strings**, not FKs, so deletes do not orphan rows but can break downstream lookups that expect the type to exist.
- **PERMISSION GATES:** **Gap.** Sidebar `payment_types_view` ([`app.blade.php:963`](../resources/views/layouts/app.blade.php:963)); GlobalSearch ([`GlobalSearchController.php:150`](../app/Http/Controllers/GlobalSearchController.php:150)); `payment_types_*` seeded ([`PermissionSeeder.php:39`](../database/seeders/PermissionSeeder.php:39), [`RolePermissionSeeder.php:29`](../database/seeders/RolePermissionSeeder.php:29)) but **controller checks none**.
- **STYLING:** **OFF baseline** — uses raw `slate-*`/`rose-*`/`emerald-*` utilities and hand-rolled `bg-rose-600` buttons (25, 159, 197) instead of `text-text-*` tokens and `btn-primary`; raw table wrapper instead of `x-card`; custom dropdown instead of `x-dropdown`. It is the only module in this audit not using the design-system components.

### C. High-Risk / Protected
- The canonical `Cash` literal is load-bearing for Expenses/ledger ([`ExpenseController.php:20-30`](../app/Http/Controllers/ExpenseController.php:20)); a redesign must not allow deleting/renaming it server-side.
- No monetary formula is computed from payment type itself; it is stored as a label on `db_expense`/`ac_transactions`.

---

## 7. SMTP

### A. Store-ID / Scoping

1. **store_id column:** The live table is **`db_store`**, not `db_smtps`. SMTP fields live on `db_store`: [`2026_02_07_091820_create_db_store_table.php:82-86`](../database/migrations/2026_02_07_091820_create_db_store_table.php:82) (`smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_status`). The empty [`db_smtps` table](../database/migrations/2026_02_24_015257_create_db_smtps_table.php:14) has only `id`+timestamps and the [`DbSmtp` model](../app/Models/DbSmtp.php:7) is an empty stub — **dead/unused schema**.
2. **Should be scoped?** **Reasoning: arguable, lean global-by-design.** A real multi-store deployment typically runs **one** mail server for the whole app (transactional email identity is usually app-wide). The code stores it per-store (`db_store` row), which is a defensible per-store model (each store can send as its own brand), **but** the per-store storage introduces the same cross-store write hazard as Currency/Language because `db_store` is shared. Either model is valid; the important thing is consistency.
3. **Scoping status:** `index()` and `update()` now resolve the acting store via `current_store_id()` ([`SmtpSettingsController.php:18`](../app/Http/Controllers/SmtpSettingsController.php:18), [`:33`](../app/Http/Controllers/SmtpSettingsController.php:33)) — **the Store Settings rollout fix holds.** The old `session('store_id') ?? 1` bug is gone (comment at lines 15–17).
4. **Global-flag risk:** `smtp_status` is a per-store on/off flag read with `$store->smtp_status` ([`:56`](../app/Http/Controllers/SmtpSettingsController.php:56)). No auto-flip across stores. **However**, because `db_store` is one row per store, a Store‑B user editing SMTP only touches their own row — no cross-store leak, unlike the global status flags.

### B. Standard Audit Sections

- **LOCATE:** [`SmtpSettingsController.php`](../app/Http/Controllers/SmtpSettingsController.php:1) (index 13, update 24, testSmtp 47). [`DbStore.php`](../app/Models/DbStore.php:1). View `smtp.blade.php`. Routes [`web.php:448-450`](../routes/web.php:448).
- **UI INVENTORY (smtp.blade.php):** SMTP Status select (31–34); Host (44); Port (54); User (63); Password with show/hide toggle (73–77); Reset + Save Configuration (83–84); Test sidebar: Test Email input (101), Send Test Email button calling `sendTestEmail()` (104), `<script>` fetch to `settings.smtp.test` (112–151).
- **FUNCTIONAL:** All wired and reachable. `index`/`update`/`testSmtp` are defined; view posts to the right routes. Test flow depends on a working mail server (see runtime list). **`update` requires `smtp_pass` on every save** ([`:30`](../app/Http/Controllers/SmtpSettingsController.php:30)) — because the field is pre-filled with the stored plaintext value, a normal save works, but it means the password is always re-sent.
- **SUBMISSION/EDIT/DELETE:** `update` (24–45) validates host/port/user/pass and writes the five columns through `DbStore::update()` guarded by `resolveActingStore`-equivalent (`current_store_id()` + `findOrFail`). No delete flow.
- **SECURITY — credentials storage:** `smtp_pass` is stored **in plaintext** in `db_store.smtp_pass` and rendered back into the form value ([`smtp.blade.php:73`](../resources/views/module/settings/smtp.blade.php:73)). [`DbStore`](../app/Models/DbStore.php:1) has `protected $guarded = []` and **no `$casts`/encryption cast** for `smtp_pass`. Not hashed/encrypted. The password is also logged indirectly only on failure ([`:76`](../app/Http/Controllers/SmtpSettingsController.php:76) logs the exception message, not the password). **Recommendation: `encrypted` cast + write-only password field.**
- **Test-connection:** `testSmtp()` (47–79) sets `mail.mailers.smtp.*` and `mail.from.*` at runtime then `Mail::raw(...)`. It returns JSON success/error. It works **only** when `smtp_status == 1` (early return at 56–58) and the credentials are valid.
- **PERMISSION GATES:** **Gap AND slug mismatch.** Sidebar gates `smtp_settings_view` ([`app.blade.php:973`](../resources/views/layouts/app.blade.php:973)) and GlobalSearch catalogs `smtp_settings_view` ([`GlobalSearchController.php:152`](../app/Http/Controllers/GlobalSearchController.php:152)); but the seeded slug is **`smtp_settings`** ([`PermissionSeeder.php:68`](../database/seeders/PermissionSeeder.php:68), [`RolePermissionSeeder.php:58`](../database/seeders/RolePermissionSeeder.php:58)) — no `_view` variant is seeded. `SmtpSettingsController` performs **no** permission check at all, so even a non-super-admin who types the URL can read/write SMTP credentials.
- **STYLING:** **OFF baseline** — raw `slate-*`/`primary-*` utilities and hand-rolled buttons (22, 84, 104), raw inputs instead of `input-base`, no `x-card`/`x-dropdown`. Functionally complete but visually pre-baseline.

### C. High-Risk / Protected
- Credential exposure (plaintext at rest + displayed in the form) is the highest-risk item in this module.
- `Config::set('mail.*')` mutates process config at request time ([`:62-67`](../app/Http/Controllers/SmtpSettingsController.php:62)) — a redesign must not change how outbound mail is configured elsewhere without auditing other `Mail::` senders.

---

## 8. Currency

### A. Store-ID / Scoping

1. **store_id column:** NO on `db_currency`. [`2026_02_07_085621_create_db_currency_table.php:14`](../database/migrations/2026_02_07_085621_create_db_currency_table.php:14): `id`, `currency_name`, `currency_code`, `currency`, `symbol`, `status`, timestamps. The per-store selection is `db_store.currency_id` ([`…db_store_table.php:53`](../database/migrations/2026_02_07_091820_create_db_store_table.php:53)).
2. **Global by design: YES.** Currency list is a global reference set; the store picks one via `db_store.currency_id`.
3. N/A.
4. **Global-flag read path confirmed (same as Language):** [`DbCurrency::activateCurrency()`](../app/Models/DbCurrency.php:37) sets `status = 0` on all rows (line 42), activates one (43), then writes **only the acting store's** `currency_id` (line 51, `DbStore::where('id', $targetStoreId)->update(...)`). [`CurrencyController::index()`](../app/Http/Controllers/CurrencyController.php:15) reads the banner from the full set (`firstWhere('status', 1)`, line 18). `AppServiceProvider::resolveCurrencySymbol()` prefers `db_store.currency_id` and falls back to `DbCurrency::where('status', 1)->first()` only when no store resolves ([`AppServiceProvider.php:201-215`](../app/Providers/AppServiceProvider.php:201)). **Confirmed: still holds, not a bug.**

### B. Standard Audit Sections

- **LOCATE:** [`CurrencyController.php`](../app/Http/Controllers/CurrencyController.php:1) (index 15, store 25, update 66, activate 121, destroy 142). [`DbCurrency.php`](../app/Models/DbCurrency.php:1). View `currency_list.blade.php`. Routes [`web.php:451-455`](../routes/web.php:451).
- **UI INVENTORY (currency_list.blade.php):** Add Currency (40); Active banner "Current System Currency" with "Only 1 currency is active system-wide…" (48–68); **DECORATIVE table controls** — Show select (78–81), Copy/Excel/PDF/Cols (87–90), non-functional search (93); columns `#`/Name (with pulse dot)/Code/Symbol/Status/Action (104–109); Status cell renders "Set Active" for inactive (139–147) and Active pill for active (133–137); Action dropdown: Activate (159) for inactive, Edit (167), Delete form (169) or "Active (Locked)" (175); Activate confirm modal (199–237); Add modal (240–286) with status note (276); Edit modal (289–349) that locks status when currently active (321–330).
- **FUNCTIONAL:** Real actions WORK (Activate/Edit/Delete/Add). Search box, Show select, and Copy/Excel/PDF/Cols are DECORATIVE-DEAD.
- **SUBMISSION/EDIT/DELETE:** Store (25–61) and update (66–116) prevent deactivating the active currency (79–89) and route activation through `activateCurrency` in a transaction. **Delete guarded** against the active row ([`destroy`](../app/Http/Controllers/CurrencyController.php:142) 146–156); non-active delete is allowed and referenced by `db_store.currency_id` `onDelete('set null')` ([`…db_store_table.php:109`](../database/migrations/2026_02_07_091820_create_db_store_table.php:109)).
- **PERMISSION GATES:** **Gap.** Sidebar `currency_view` ([`app.blade.php:977`](../resources/views/layouts/app.blade.php:977)); GlobalSearch `currency_view` ([`GlobalSearchController.php:153`](../app/Http/Controllers/GlobalSearchController.php:153)); but `currency_view` is **not seeded** ([`PermissionSeeder.php:26-77`](../database/seeders/PermissionSeeder.php:26), [`RolePermissionSeeder.php:16-67`](../database/seeders/RolePermissionSeeder.php:16)) and `CurrencyController` checks **no** permission.
- **STYLING:** MIXED — uses correct `text-text-*`/`card`/`btn-primary` tokens in the list shell, but the modals use raw `slate-*`/`emerald-*`/`rose-*` utilities (246, 281, 344).

### C. High-Risk / Protected
- **Currency formatting is protected** — [`format_currency()`](../app/Helpers/helpers.php:170) uses `$store->decimals` and `store_settings()->currency_placement`, with the symbol from `AppServiceProvider::resolveCurrencySymbol()`. A redesign must not bypass the store’s `currency_id`/`decimals`/`currency_placement`.
- Activating a currency also rewrites the acting store's `db_store.currency_id` and busts the memoized cache ([`DbCurrency.php:50-61`](../app/Models/DbCurrency.php:50)) — preserve this side-effect coupling.

---

## Per-Module UI Classification Summary

| Module | WORKS | PARTIALLY WORKS | DECORATIVE-DEAD | MISSING |
|---|---|---|---|---|
| **Languages** | Add/Edit/Delete/Activate, search, pagination, active-guard | — | — | Permission check; seeded `language_view` |
| **Countries** | Add/Edit/Delete, search, pagination, stat cards | — | — | Permission check; seeded `country_view`; delete guard (states cascade) |
| **States** | Add/Edit/Delete, search, pagination, stat cards, country dropdown | store_id/company_id (dead columns) | — | Permission check; seeded `state_view`; delete in-use guard |
| **Tax** | Add/Edit/Delete (individual + group), search, dual pagination | Store scoping (create only; list/edit/delete unscoped) | In-loop sub-tax lookup (N+1) | Permission check; seeded slugs exist but unchecked; delete in-use guard |
| **Units** | Add/Edit/Delete, search, pagination, quick-add from items/purchase | Store scoping (create only) | — | Permission check; seeded slugs exist but unchecked; delete in-use guard |
| **Payment Types** | Add/Edit/Delete, CASH-protection (view-level) | Store scoping (create only) | Search, Show-select, Copy/Excel/PDF/Cols | Permission check; server-side CASH guard; pagination; styling baseline |
| **SMTP** | Load/Update, Test-Email send (needs live mail) | — | — | Permission check; **slug mismatch** (`smtp_settings` vs `smtp_settings_view`); password encryption |
| **Currency** | Add/Edit/Delete/Activate, active-guard, confirm modal | Styling (modals off-baseline) | Search, Show-select, Copy/Excel/PDF/Cols | Permission check; seeded `currency_view` |

---

## Cross-Module Comparison Table (primary deliverable)

| Module | Has store_id? | Actually scoped? | Should be scoped (reasoning) | Global-flag risk (Y/N, like Currency/Language) | Delete guarded? | Permission gates checked? | On design-system baseline already? |
|---|---|---|---|---|---|---|---|
| **Languages** | No | N/A — global flag + per-store FK | No — global reference list; per-store via `db_store.language_id` | **Y** (accepted, documented; not a bug) | Y (active-lock only; no dependents) | **N** — `language_view` unseeded + 0 checks | Y |
| **Countries** | No | N/A | No — geographic reference data | N (plain multi-active toggle) | **N** — cascade-deletes states | **N** — `country_view` unseeded + 0 checks | Y |
| **States** | **Yes (dead)** + `company_id` dead | **N** — never set/filtered | No — geographic reference data; dead columns should be dropped | N (plain toggle) | **N** — orphans `customers/suppliers.state_id` (no FK) | **N** — `state_view` unseeded + 0 checks | Y |
| **Tax** | Yes | **Partial** — create only; list/edit/delete + dropdowns unscoped | **Yes** — tax rates legitimately differ per store | N-single-active; **but cross-store sum in group + global cached dropdown** | **N** — orphans `db_items` & all `*_items.tax_id` | **N** — slugs seeded (`tax_*`) but 0 checks | Y |
| **Units** | Yes | **Partial** — create only; list/edit/delete + dropdowns unscoped | **Yes** — units may differ per store | N (plain toggle) | **N** — orphans `db_items.unit_id` | **N** — slugs seeded (`units_*`) but 0 checks | Y |
| **Payment Types** | Yes (no FK) | **Partial** — create only (uses `current_store_id()`); list/edit/delete unscoped | **Yes** — payment methods differ per store | N-single-active; **unscoped update/delete + view-only CASH guard** | **N** — controller-level guard absent (CASH guard is Blade-only) | **N** — slugs seeded (`payment_types_*`) but 0 checks | **N** — off-baseline |
| **SMTP** | Fields on `db_store` (per-store row); `db_smtps` empty/dead | **Y** — `current_store_id()` fix holds | **Arguable** — lean global (one mail server/app) but per-store storage is defensible; either is valid if consistent | N per-store (no cross-store leak), but shared-table write | N/A (no delete) | **N** — slug mismatch + 0 checks; plaintext password | **N** — off-baseline |
| **Currency** | No | N/A — global flag + per-store FK | No — global reference list; per-store via `db_store.currency_id` | **Y** (accepted, documented; not a bug) | Y (active-lock only; FK `set null`) | **N** — `currency_view` unseeded + 0 checks | Mixed (list Y, modals off-baseline) |

**Rollout scoping takeaways:**
1. **Unscoped CRUD is the dominant defect** — Tax, Units, Payment Types all scope *create* but not list/edit/delete; States has a dead `store_id`. Fix order: list → edit → delete → dropdown consumers.
2. **`auth()->user()->store_id ?? 1` fallback** in [TaxController](../app/Http/Controllers/TaxController.php:63) and [UnitController](../app/Http/Controllers/UnitController.php:47) should be replaced with [`current_store_id()`](../app/Helpers/helpers.php:151) (PaymentType already uses it).
3. **No delete guard exists in any of the 8 modules.** Countries (cascade→states), States (orphan customer/supplier), Tax/Units (orphan items) are the highest-impact.
4. **No permission gate is enforced** in any of the 8 controllers; Language/Country/State/Currency `*_view` slugs are also **unseeded** (Tax/Units/PaymentTypes slugs are seeded but unchecked; SMTP has a name mismatch).
5. **Design-system baseline** is met by Languages/Countries/States/Tax/Units; Payment Types and SMTP are off-baseline; Currency modals are partially off-baseline.

---

## Needs Runtime Test

Unconfirmed without a live test / DB state:

1. **SMTP test send** — `sendTestEmail()` → `SmtpSettingsController::testSmtp()` → `Mail::raw()` success/failure against a real mail server (requires `smtp_status = 1` and valid credentials). Also verify the temp `Config::set('mail.*')` does not leak into queued jobs.
2. **Concurrent edits (all modules)** — two users saving the same Tax/Unit/PaymentType row simultaneously; no optimistic lock exists, so last-write-wins is expected but unverified.
3. **Language/Currency cross-store banner** — confirm with two real stores that Store‑B activation changes the banner text for Store‑A while Store‑A's `db_store.language_id`/`currency_id` remains unchanged.
4. **Tax cross-store group sum** — create sub-taxes in Store‑1 and a group in Store‑2 and confirm the summed rate pulls in Store‑1 rows (predicted from [`TaxController.php:59`](../app/Http/Controllers/TaxController.php:59)).
5. **Global tax cache staleness** — add a tax in one store and confirm the POS dropdown ([`SaleController.php:49`](../app/Http/Controllers/SaleController.php:49)) does not refresh until the 1-hour cache expires.
6. **Cascade/orphan deletes** — delete a Country with child states and verify states rows disappear; delete a State used by a customer and verify `state_id` becomes dangling/null; delete a Tax/Unit used by an item and observe the orphaned reference.
7. **Direct-URL permission bypass** — as a non-super-admin lacking the (unseeded) slugs, hit each settings route directly to confirm no 403 is raised.
8. **SMTP password exposure** — inspect `db_store.smtp_pass` at rest to confirm plaintext; confirm the value is echoed into the form.

*End of report.*

---

## ROLLOUT IMPLEMENTATION LOG (Stage-3 fix/build/redesign)

**Scope decisions accepted (stated explicitly):**
1. **Languages, Currency, Countries — NO scoping change.** Accepted; global-by-design, correct, untouched.
2. **States — dead `store_id`/`company_id` columns: LEAVE AS-IS.** Accepted; left untouched.
3. **SMTP — KEEP per-store storage.** Accepted; `current_store_id()` scoping already holds; only security/permission/styling gaps fixed.
4. **Tax, Units, Payment Types — FULLY store-scoped.** Accepted; implemented.

### Phase 1 — Store scoping (Tax, Units, Payment Types)
- **1.1** `TaxController::store()` now uses `current_store_id()` instead of `auth()->user()->store_id ?? 1` ([`TaxController.php`](app/Http/Controllers/TaxController.php)); same for `UnitController::store()` ([`UnitController.php`](app/Http/Controllers/UnitController.php)).
- **1.2** `index()` scoped via "current store OR null": TaxController, UnitController, PaymentTypeController.
- **1.3** `update()`/`destroy()` scoped via "current store OR null" `findOrFail` in all three controllers (cross-store ID tampering now 404s).
- **1.4** Dropdown/cache consumers scoped: `SaleController` (`db_taxes_list` → per-store `db_taxes_list_{store_id}` key; payment types scoped at create/edit/payments/receive-payment), `PosController`, `ItemController` (create+edit), `ImportsItems` (both), `PurchaseController` (list/create/edit).
- **1.5** Cross-store tax-group sum constrained to acting store's (or null) sub-tax rows.
- **1.6** In-loop sub-tax lookup in `tax_list.blade.php` replaced with a controller pre-fetched `$subtaxNames` map (single query).
- **Verify:** 51 tests (LookupModulesRedesign, StoreSettingsPermissionGate, SingleActiveCurrency/Language) + 94 tests (Item/Purchase/Quotation/Service/POS) all passed; `php -l` clean on all edited controllers.

### Phase 2 — Delete / orphan guards
- **2.1** `CountryController::destroy()` blocks when states/customers/suppliers reference the country, showing counts.
- **2.2** `StateController::destroy()` blocks when customers/suppliers reference the state (no FK exists → orphan risk eliminated).
- **2.3** `TaxController::destroy()` counts references across items, all `*_items.tax_id`, and all `other_charges_tax_id` columns; blocks with counts. Historical `tax_amt` untouched (Protected Region honored).
- **2.4** `UnitController::destroy()` blocks when any `db_items.unit_id` references the unit.
- **2.5** `PaymentTypeController::update()/destroy()` add server-side CASH guard (bypassing the Blade `@if`); destroy also warns/blocks on `db_expense` references.
- **Verify:** same suites re-run, 75 tests passed (incl. PaymentDeleteFlow, PaymentsListPage, ReceivePaymentPage, Expenses guards).

### Phase 3 — Permission gates + SMTP credential security
- **3.1** Seeded `language_view`, `country_view`, `state_view`, `currency_view` in `PermissionSeeder` + `RolePermissionSeeder`; added `hasPermission()` gates to all 8 controllers (index/create/store/edit/update/destroy/activate as applicable).
- **3.2** SMTP slug renamed `smtp_settings` → `smtp_settings_view` (matches sidebar/GlobalSearch); `SmtpSettingsController` now gates index/update/testSmtp.
- **3.3** `DbStore` adds `$casts = ['smtp_pass' => 'encrypted']`; new idempotent migration `2026_09_10_000003_encrypt_existing_smtp_pass_values.php` encrypts existing plaintext rows. `testSmtp()` still decrypts transparently (runtime test required to confirm against a live mail server).
- **Verify:** `php -l` clean on all controllers/models/seeders/migration; views cached successfully.

### Phase 4 — Redesign / styling baseline
- **4.1** `payment_types.blade.php` rewritten to baseline: `text-text-*`, `card`, `btn-primary`, `input-base`, `x-card`, `x-dropdown`; decorative search/Show/Copy/Excel/PDF/Cols replaced with wired server-side search + per-page limit + real pagination (controller `index()` now paginates).
- **4.2** `smtp.blade.php` rewritten to baseline; test-email flow preserved verbatim.
- **4.3** Currency Activate/Add/Edit modals restyled to baseline tokens; activation logic untouched.
- **Verify:** `php artisan view:cache` succeeds; Currency/Languages/Units/Tax/Countries/States/PaymentTypes render tests pass.

### Protected-Region confirmation
- `DbLanguage::activateLanguage()` / `DbCurrency::activateCurrency()` global-flip pattern: **untouched**.
- `format_currency()` / `resolveCurrencySymbol()`: **untouched**.
- `*_items.tax_amt` snapshot: **untouched** — delete guards only block, never rewrite.
- Canonical `Cash` literal: now protected **server-side**.
- `testSmtp()` `Config::set('mail.*')`: **unchanged**.
- No unit-conversion engine introduced.

### Running status (final)
| Module | Audit | Phase 1 (scoping) | Phase 2 (guards) | Phase 3 (permissions) | Phase 4 (redesign) |
|---|---|---|---|---|---|
| Languages | Done | N/A (global) | N/A | Done | N/A (baseline) |
| Countries | Done | N/A (global) | Done | Done | N/A (baseline) |
| States | Done | N/A (global; dead cols out of scope) | Done | Done | N/A (baseline) |
| Tax | Done | Done | Done | Done | N/A (baseline) |
| Units | Done | Done | Done | Done | N/A (baseline) |
| Payment Types | Done | Done | Done | Done | Done |
| SMTP | Done | N/A (keep per-store) | N/A | Done (slug + encryption) | Done |
| Currency | Done | N/A (global) | N/A (already guarded) | Done | Done (modals) |

### Items still requiring a runtime test
1. SMTP test-send end to end against a real mail server (cast now decrypts; needs live verification).
2. Cross-store direct-POST rejection (store-2 user editing store-1 Tax/Unit/PaymentType ID → 404).
3. Per-store tax cache refresh within the same request cycle for the changing store.
4. Country/State/Tax/Unit delete-guard messages rendering with accurate counts in a real DB.
5. Non-super-admin direct-URL 403 for all 8 modules' routes.
6. `db_store.smtp_pass` at rest inspection (plaintext → ciphertext after migration).

*End of rollout log.*

---

## RUNTIME VERIFICATION (gap-closing pass)

Executed via [`tests/Feature/SettingsRolloutRuntimeVerificationTest.php`](tests/Feature/SettingsRolloutRuntimeVerificationTest.php), which prints the exact HTTP status / DB value for each check. **Two genuine defects were found and fixed during this pass** (see Reopened items).

### G1 — Cross-store direct-POST rejection (Store-2 user, holding edit/delete slugs, targeting Store-1 rows)
| # | Request | Observed | DB after |
|---|---|---|---|
| 1 | POST `settings.tax.update` (Store-1 tax id) | **HTTP 404** | row UNCHANGED (`tax_name=S1 Tax`, `tax=5`) |
| 2 | DELETE `settings.tax.delete` (Store-1 tax id) | **HTTP 404** | row STILL EXISTS |
| 3 | POST `settings.units.update` (Store-1 unit id) | **HTTP 404** | row UNCHANGED (`unit_name=S1 Unit`) |
| 4 | DELETE `settings.units.delete` (Store-1 unit id) | **HTTP 404** | row STILL EXISTS |
| 5 | POST `settings.payment_types.update` (Store-1 id) | **HTTP 404** | row UNCHANGED (`payment_type=S1 Pay`) |
| 6 | DELETE `settings.payment_types.delete` (Store-1 id) | **HTTP 404** | row STILL EXISTS |

**G1 CLOSED — 6/6 rejected; no Store-1 row was changed or deleted.**

### G2 — Non-super-admin direct-URL 403 (role_id != 1, no `*_view` slugs)
| Module | Route | Observed |
|---|---|---|
| Languages | GET /settings/languages | **HTTP 403** |
| Countries | GET /settings/countries | **HTTP 403** |
| States | GET /settings/states | **HTTP 403** |
| Tax | GET /settings/tax | **HTTP 403** |
| Units | GET /settings/units | **HTTP 403** |
| Payment Types | GET /settings/payment-types | **HTTP 403** |
| SMTP | GET /settings/smtp | **HTTP 403** |
| Currency | GET /settings/currency | **HTTP 403** |

**G2 CLOSED — 8/8 return 403.**

### G3 — SMTP credential at rest / display / migration
- Saved `PlainSecret123!`; raw `db_store.smtp_pass` = `eyJpdiI6IkxGM1hBL3lrREhocjFiMXRtWlducHc9…` (Laravel `encrypted` payload, base64/JSON with `iv`/`value`/`mac`). **Not plaintext.**
- `Crypt::decryptString(raw)` → `PlainSecret123!`; model read returns `PlainSecret123!`.
- `GET /settings/smtp` (authorised store-1 user) → **HTTP 200**, form renders the decrypted secret.
- Companion migration on a simulated legacy plaintext row (`LegacyPlain`) → stored value became ciphertext; decrypts to `LegacyPlain`. **Second run unchanged (idempotent).**
- `testSmtp()` reached the mailer with the decrypted value (see G6).

### G4 — Delete-guard counts vs real DB
| Guard | Message shown | Actual dependent rows |
|---|---|---|
| Country (3 states) | "…referenced by **3 state(s)**…" | 3 |
| State (2 customers) | "…referenced by **2 customer(s)**…" | 2 |
| Tax (2 items) | "…referenced by **2 items**…" | 2 |
| Unit (1 item) | "…used by **1 item(s)**…" | 1 |

All blocked (HTTP 302 back with `error`); target rows persisted. **Counts match exactly.**

### G5 — Per-store tax cache freshness (after the G5 fix)
- Warm: `db_taxes_list_1` present, `db_taxes_list_2` present.
- Create tax in Store 1 → store-1 key **busted**; store-2 key **untouched**.
- Store-1 next read rebuilds cache and **contains `FreshTaxAlpha`** immediately (no expiry wait).
- Store-2 cached list does **not** contain it. **No cross-store leak.**

### G6 — SMTP live test-send
- `smtp_status=0` → HTTP 200 `{"status":"error","message":"SMTP is disabled. Please enable it first."}`
- `smtp_status=1` with a deliberately unreachable host → HTTP 200 `{"status":"error","message":"Failed: Email \"user\" does not comply with addr-spec of RFC 2822."}` — the endpoint executed through the mailer with the **decrypted** password and returned its error contract.
- **No reachable mail server exists in this environment (phpunit sets `MAIL_MAILER=array`); an actual delivered email could NOT be confirmed. G6 remains open pending a live SMTP server.**

### Reopened items found and fixed during this pass
1. **[FIXED] G5 — tax cache not busted on write.** The per-store key existed but no write invalidated it, so the creating store read a 1-hour-stale list. Added `TaxController::forgetTaxDropdownCache($storeId)` called from `store()`, `update()`, and `destroy()`.
2. **[FIXED] Country delete guard threw HTTP 500.** [`CountryController.php`](app/Http/Controllers/CountryController.php) used `DbState::` without importing the class (Phase 2.1 introduced the reference). Added `use App\Models\DbState;`. G4 Country now returns HTTP 302 with the correct count message.

### Final test tally
`SettingsRolloutRuntimeVerificationTest` (7) + `LookupModulesRedesignTest` (20) + `SingleActiveCurrencyTest` (13) + `SingleActiveLanguageTest` (13) = **53 passed (492 assertions)**.

*End of runtime verification.*