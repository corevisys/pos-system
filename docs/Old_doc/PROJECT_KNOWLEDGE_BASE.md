# CorevisysPOS — Project Knowledge Base & Codebase Architecture

> **Generated:** September 2026 — Rebuilt from a fresh, line-by-line audit of the current codebase.
> Every claim below was verified against the actual source on 2026-09-01 (`composer.json`, `package.json`,
> `resources/css/app.css`, `resources/js/app.js`, `resources/views/**`, `app/Http/Controllers/**`,
> `app/View/Components/**`, `config/**`, `.env.example`, and the test suite). Where the previous version of
> this file disagreed with the code, **the code won** — the previous file is treated as untrusted.
> Items that could not be verified with confidence are explicitly flagged as "unverified".

---

## 1. TECH STACK OVERVIEW

| Layer | Technology | Verified source |
| :--- | :--- | :--- |
| Backend framework | Laravel `^12.0` (`laravel/framework` in `composer.json`) | [`composer.json`](composer.json:14) |
| Language runtime | PHP `^8.2` | [`composer.json`](composer.json:12) |
| Frontend build tooling | Vite `^7.0.7` via `laravel-vite-plugin ^2.0.0` | [`package.json`](package.json:19) |
| CSS framework | Tailwind CSS `4.1.18` (v4 — CSS-first `@import "tailwindcss"` + `@theme`) | [`package.json`](package.json:19), [`resources/css/app.css`](resources/css/app.css:1) |
| JS framework | Alpine.js `^3.4.2` (bundled via Vite) + `@alpinejs/collapse ^3.17.0` | [`package.json`](package.json:13), [`resources/js/app.js`](resources/js/app.js:2) |
| Icon libraries | **Font Awesome 6.0.0 (CDN only on non-app layouts)** — `guest`, `public`, `welcome` | [`guest.blade.php`](resources/views/layouts/guest.blade.php:17), [`welcome.blade.php`](resources/views/welcome.blade.php:8) |
| Chart library | Chart.js (CDN `https://cdn.jsdelivr.net/npm/chart.js` in `<head>` of `layouts/app.blade.php`, plus a page-level `<script>` in `reports/cash_flow.blade.php`) | [`layouts/app.blade.php`](resources/views/layouts/app.blade.php:19), [`reports/cash_flow.blade.php`](resources/views/module/reports/cash_flow.blade.php:655) |
| Testing | Pest PHP `^3.8` + `pestphp/pest-plugin-laravel ^3.2` (PHPUnit underlay) | [`composer.json`](composer.json:27) |

### Key Composer packages (verified against `composer.json`)
- `barryvdh/laravel-dompdf ^3.1` — PDF invoices, labels, tax reports.
- `picqer/php-barcode-generator ^3.2` — barcode SVG rendering.
- `spatie/laravel-backup ^9.3` — database/file backups.
- `laravel/breeze ^2.3` — auth scaffolding (require-dev).
- `laravel/tinker ^2.10.1`.

### Page-specific CDN dependencies (important landmine area)
**SweetAlert2 is loaded per-page, never bundled.** It is a `<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11">` at the *top* of the file (before `<x-app-layout>`) on exactly these pages (verified by grep):
- [`module/sales/pos.blade.php`](resources/views/module/sales/pos.blade.php:1) — POS checkout/hold/coupon/EMI confirmations.
- [`module/sales/add.blade.php`](resources/views/module/sales/add.blade.php:1058) — Add Sale checkout/hold/coupon/EMI.
- [`module/settings/database_backup.blade.php`](resources/views/module/settings/database_backup.blade.php:1) — backup create/delete confirmations.
- [`module/items/items_list.blade.php`](resources/views/module/items/items_list.blade.php:234) and [`module/items/services_list.blade.php`](resources/views/module/items/services_list.blade.php:147) — delete confirmations.

Why CDN rather than bundled: these pages mix server-rendered Blade with Alpine-managed fetch flows and rely on `Swal` being a global. There is **no `sweetalert2` npm dependency** in `package.json`, so any page that calls `Swal.` **must** include its own CDN script tag; the global is *not* available app-wide.

**Chart.js** is loaded globally in `layouts/app.blade.php` `<head>` (so dashboard + most reports get it for free), **but** `reports/cash_flow.blade.php` additionally pushes its own CDN script in `@push('scripts')` — harmless duplicate, but a sign the global dependency is not yet formalized.

**Font Awesome `fas` classes are used by the global loading utility in `app.js`, but FA is NOT loaded on the app layout** — see the "loading-state utility" section below for the consequence.

---

## 2. DESIGN SYSTEM — EXACT CURRENT STATE

### 2.1 Design tokens / CSS custom properties — exact values

All tokens are defined in one place: the `@theme` block of [`resources/css/app.css`](resources/css/app.css:7) (Tailwind v4 CSS-first config). Grouped exactly as the file groups them:

**Typography**
```
--font-sans: "Plus Jakarta Sans", "Hind Siliguri", "Figtree", sans-serif;
```
(Note: `guest`/`public`/`welcome` layouts override body font with Google Fonts `Hind Siliguri`/`Inter` inline `<style>`; the app layout relies on the Plus Jakarta Sans Google Fonts link in `<head>`.)

**Primary (Indigo scale)**
```
--color-primary: #4f46e5;          /* = primary-600 */
--color-primary-hover: #4338ca;    /* = primary-700 */
--color-primary-light: #eef2ff;    /* = primary-50 */
--color-primary-50: #eef2ff;
--color-primary-100: #e0e7ff;
--color-primary-200: #c7d2fe;
--color-primary-300: #a5b4fc;
--color-primary-400: #818cf8;
--color-primary-500: #6366f1;
--color-primary-600: #4f46e5;
--color-primary-700: #4338ca;
--color-primary-800: #3730a3;
--color-primary-900: #312e81;
--primary-rgb: 99 102 241;         /* RGB triplet of primary-500 for rgba(var(--primary-rgb), α) */
```

**Semantic surfaces**
```
--color-navy: #0f172a;
--color-card: #ffffff;
--color-background: #f8fafc;
--color-border: #e2e8f0;
--color-border-light: #f1f5f9;
--color-text-primary: #0f172a;
--color-text-secondary: #475569;
--color-text-muted: #94a3b8;
```

**Status colors**
```
--color-danger: #dc2626;   --color-danger-hover: #b91c1c;   --color-danger-light: #fef2f2;
--color-warning: #d97706;  --color-warning-light: #fffbeb;
--color-success: #059669;  --color-success-light: #ecfdf5;
```

**Shadows**
```
--shadow-card: 0 1px 2px 0 rgba(15, 23, 42, 0.04);
--shadow-card-hover: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
--shadow-dropdown: 0 8px 24px -4px rgba(15, 23, 42, 0.12);
--shadow-modal: 0 24px 48px -12px rgba(15, 23, 42, 0.24);
```

**Radius**
```
--radius-card: 0.75rem;  --radius-input: 0.5rem;  --radius-button: 0.5rem;
```

**Dark-mode surfaces**
```
--color-dark-bg: #0f172a;
--color-dark-card: #1e293b;
--color-dark-border: #334155;
--color-dark-text: #f8fafc;
```

Dark variant is declared as `@custom-variant dark (&:where(.dark, .dark *))` — so `dark:` utilities apply only under an ancestor with class `dark` (see §3 for where that class lives).

### 2.2 Reusable Blade components — verified against actual files

**Class components (`app/View/Components/`)**
- [`AppLayout`](app/View/Components/AppLayout.php:8) — constructor `?string $title = null`; renders `layouts.app`. Used as `<x-app-layout title="...">` on every admin page.
- [`GuestLayout`](app/View/Components/GuestLayout.php) — renders `layouts.guest` (auth pages).
- [`PublicLayout`](app/View/Components/PublicLayout.php:10) — constructor `public ?string $pageKey = 'home'`; renders `layouts.public` (public marketing/legal pages).

**Anonymous components (`resources/views/components/`)** — verified props and usage:

| Component | Props (all optional unless noted) | Defaults | Merge/notes |
| :--- | :--- | :--- | :--- |
| [`<x-card>`](resources/views/components/card.blade.php:1) | `padding`, `hover` | `padding='p-5'`, `hover=false` | Root div gets `card card-hover p-5` |
| [`<x-stat-card>`](resources/views/components/stat-card.blade.php:1) | `label`, `value`, `icon` (raw HTML), `iconBg`, `footer` (raw HTML) | `iconBg='bg-primary-light text-primary'` | `icon` and `footer` are echoed with `{!! !!}` — do not pass untrusted input |
| [`<x-table>`](resources/views/components/table.blade.php:1) | `title`, `actions`, named slots `thead`, default `slot` | — | Root: `card overflow-hidden p-0` |
| [`<x-badge>`](resources/views/components/badge.blade.php:1) | `color` ∈ `primary\|success\|warning\|danger\|neutral` | `primary` | Pill span |
| [`<x-icon-button>`](resources/views/components/icon-button.blade.php:1) | `variant` ∈ `default\|primary\|danger\|ghost`, `size` ∈ `sm\|md\|lg`, `label` (→ title/aria) | `default`, `md` | Root gets `type=button` |
| [`<x-searchable-select>`](resources/views/components/searchable-select.blade.php:1) | `name,id,options,value,selected,placeholder,emptyOption,emptyValue,model,change,required,disabled,labelKey,valueKey,subtextKey,optionsExpression,quickAddClick,class,inputClass,maxHeight` | `placeholder='Search or select...'`, `emptyValue=''`, `maxHeight='max-h-40'` | ~131 call sites (see §6.4) |
| [`<x-dropdown>`](resources/views/components/dropdown.blade.php:1) | `align` ∈ `left\|top\|right`, `width`, `contentClasses`; named slots `trigger`, `content` | `right`, `48`, `py-1 bg-white` | **Teleports panel to `<body>`** (see §6.3) |
| [`<x-modal>`](resources/views/components/modal.blade.php:1) | `name` (required), `show`, `maxWidth` ∈ `sm\|md\|lg\|xl\|2xl`, optional `focusable` attr | `2xl` | Breeze-derived; responds to `open-modal`/`close-modal` window events |
| [`<x-primary-button>`](resources/views/components/primary-button.blade.php:1) | — | root gets `type=submit`, class `btn-primary` | |
| [`<x-secondary-button>`](resources/views/components/secondary-button.blade.php:1) | — | root gets `type=button`, class `btn-secondary` | |
| [`<x-danger-button>`](resources/views/components/danger-button.blade.php:1) | — | root gets `type=submit`, class `btn-danger` | |
| [`<x-text-input>`](resources/views/components/text-input.blade.php:1) | `disabled` | class `input-base` | |
| [`<x-textarea>`](resources/views/components/textarea.blade.php) | `disabled` | class `input-base` | |
| [`<x-select>`](resources/views/components/select.blade.php:1) | `disabled` | class `input-base` | |
| [`<x-input-label>`](resources/views/components/input-label.blade.php) | `value`, `for` | — | |
| [`<x-input-error>`](resources/views/components/input-error.blade.php) | `messages` | — | |
| [`<x-nav-link>`](resources/views/components/nav-link.blade.php:1) | `active` | — | Breeze-derived indigo underline nav |
| [`<x-notification-toast>`](resources/views/components/notification-toast.blade.php:1) | — | — | Registered once in `layouts.app`; defines `window.showSuccess`/`window.showError` (see §2.4) |
| [`<x-keyboard-shortcuts>`](resources/views/components/keyboard-shortcuts.blade.php:1) / [`<x-keyboard-shortcuts-modal>`](resources/views/components/keyboard-shortcuts-modal.blade.php:1) | — | — | Two-key shortcut listener + help modal, registered in `layouts.app` |
| [`<x-seo-meta>`](resources/views/components/seo-meta.blade.php) | `page-key` | — | Used in `guest`, `public`, `welcome` |
| [`<x-application-logo>`](resources/views/components/application-logo.blade.php) | — | — | |
| [`<x-auth-session-status>`](resources/views/components/auth-session-status.blade.php) | — | — | |
| [`<x-dropdown-link>`](resources/views/components/dropdown-link.blade.php) | — | — | |
| [`<x-responsive-nav-link>`](resources/views/components/responsive-nav-link.blade.php) | — | — | Breeze leftovers |

Minimal usage example (verified pattern used across Users/Items/Reports pages):
```blade
<x-app-layout title="Users List">
    <div class="card p-3 mb-4">
        <x-searchable-select name="role" :options="$roles" labelKey="role_name" valueKey="id"
            emptyOption="All Roles" emptyValue="" placeholder="All Roles" :value="request('role')"
            change="submitFilters()" />
    </div>
    <x-stat-card label="Total Users" :value="$stats['total']" iconBg="bg-primary-light text-primary"
        icon='<svg class="w-5 h-5">…</svg>' />
</x-app-layout>
```

### 2.3 `@layer components` utility classes — exact `@apply` expansions

From [`resources/css/app.css`](resources/css/app.css:188) (`@layer components`):

- **`.card`** → `@apply bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-card shadow-card;`
- **`.card-hover`** → `@apply transition-shadow duration-200 hover:shadow-card-hover;`
- **`.page-padding`** → `@apply px-6 pb-6 pt-2 lg:px-10 lg:pb-10 lg:pt-3;` (the page content padding convention — see §3)
- **`.input-base`** → `@apply w-full px-3 py-2 text-sm bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-input text-text-primary dark:text-dark-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all;`
- **`.btn-primary`** → `@apply inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-hover rounded-button shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-60 disabled:cursor-not-allowed;`
- **`.btn-secondary`** → `@apply inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium bg-white dark:bg-dark-card text-text-primary dark:text-dark-text border border-border dark:border-dark-border rounded-button hover:bg-background dark:hover:bg-dark-card shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40;`
- **`.btn-danger`** → `@apply inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-danger hover:bg-danger-hover rounded-button shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-danger/40 disabled:opacity-60 disabled:cursor-not-allowed;`
- **`.btn-ghost`** → `@apply inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-button transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40;`

Also defined (outside the `components` layer, as `@utility`):
- `scrollbar-hide` (app sidebar), `custom-scrollbar` (thin themed scrollbar, uses `--primary-rgb`), `anime-fade-in`, `gradient-bg` + `hero-gradient` (guest/welcome), `shadow-premium`, `shadow-premium-lg` (guest/welcome), `pulse-orange`.
- Unlayered sidebar-collapse CSS for `aside.sidebar-collapsed` (see §3).
- `.pos-screen:fullscreen` — POS fullscreen-fit CSS.

**Heads-up:** `custom-scrollbar`, `shadow-premium`, `gradient-bg`, etc. are *utilities*, so they only exist in the CSS if the class is literally present in a scanned template. That is satisfied today, but Tailwind v4's content scanning means an unused utility disappears from the build.

### 2.4 Global loading-state utility — `setButtonLoading` / `resetButtonLoading`

Defined in [`resources/js/app.js`](resources/js/app.js:8) (loaded by Vite on the app layout):

```js
function setButtonLoading(button, label = 'Processing...')   // returns undefined
function resetButtonLoading(button)                           // returns undefined
window.setButtonLoading = setButtonLoading;
window.resetButtonLoading = resetButtonLoading;
```

Behavior:
- `setButtonLoading` stashes `button.innerHTML` in `button.dataset.originalHtml` (once — won't clobber on double-invoke), sets `button.disabled = true`, adds `opacity-75 cursor-not-allowed`, and replaces innerHTML with `<i class="fas fa-circle-notch fa-spin text-lg"></i><span class="ml-2">{label}</span>`.
- `resetButtonLoading` restores the original innerHTML and removes the classes.

**Auto-wire submit-listener (global, `document` level):**
```js
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;              // ← CRITICAL guard
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-loading')) return; // ← opt-out (whole form)
    const button = (event.submitter && ...) || form.querySelector('button[type="submit"]');
    if (!button || button.hasAttribute('data-no-loading')) return; // ← opt-out (button)
    setButtonLoading(button);
});
```

- **`defaultPrevented` guard:** any page that calls `e.preventDefault()` on submit (POS checkout, Add Sale, add/edit item, purchase/quotation save, quick-add modals — all of which `@submit.prevent="submitQuickAdd($event, …)"` or similar) is skipped; those flows manage their own loading UX via the Alpine `submitting` flag.
- **Opt-out attribute:** `data-no-loading` on either the `<form>` or the submit `<button>`.
- **No reset needed for plain forms:** the page navigates away on success; a validation re-render ships a fresh button.

**Known defect (document it):** the spinner markup uses `fas fa-circle-notch` (Font Awesome), but Font Awesome is **not loaded on the app layout** (only `guest`, `public`, `welcome`). On app pages that trigger the plain-submit auto-wire, the spinner `<i>` renders as an empty/unknown glyph; the button still disables (the real protection) but the visual spinner is absent. Dashboard quick-action `<a>` buttons call `window.setButtonLoading($event.currentTarget, 'Loading...')` manually with the same consequence.

### 2.5 Blade component-attribute escaping bug class (Alpine directives on `<x-component>` tags)

**Root cause (precisely):** When an Alpine directive is placed on a Blade component tag, e.g. `<x-card x-data="{ sel: querySelectorAll('input[type=checkbox][name=\'permissions[]\']') }">`, the attribute value is passed through Blade's **PHP-expression compiler**. Blade re-escapes the backslash-escaped single quotes (`\'` → `\\'`), which terminates the PHP string literal early and throws a render-time `ParseError` (HTTP 500). This is specific to **component tags** (`<x-...>`) — plain HTML tags pass attribute values through as raw HTML text and are safe.

**Current status — verified fresh on 2026-09-01:**
- `php artisan view:clear && php artisan view:cache` → both succeeded (exit 0).
- `php -l` sweep across every compiled view under `storage/framework/views/` → **zero failures**.
- Regex search for backslash-escaped quotes / `\['"'"'\]` patterns across all `resources/views/**` → **zero matches**.
- The old failing site ([`roles_edit.blade.php`](resources/views/module/users/roles_edit.blade.php:30)) now uses the safe string-concatenation form: `querySelectorAll('input[type=checkbox][name=' + 'permissions[]' + ']')` — identical runtime behavior, no escape problem.
- [`modal.blade.php`](resources/views/components/modal.blade.php:22) still contains a `\'hidden\'` escape *inside an x-data string*, but that is inside an anonymous component's own x-data on a plain `<div>` (not a component-tag attribute), so it compiles and is safe.

**Safe patterns (use these):**
1. String concatenation: `querySelectorAll('input[type=checkbox][name=' + 'permissions[]' + ']')`.
2. Named Alpine function reference instead of an inline literal.
3. Keep Alpine directives with complex JS on plain HTML tags, not `<x-...>` tags.
4. Always smoke-test the rendered route — `php artisan view:cache` writes compiled views without executing them and will **not** surface this bug class.

---

## 3. LAYOUT SHELL

From [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php):

- **Root `<html>`** (line 3): `x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: true }" :class="{ 'dark': darkMode }"`.
- **Dark mode mechanism:** the `dark` class lives on **`<html>` (`document.documentElement`)**, driven by Alpine state `darkMode`, initialized from `localStorage.getItem('darkMode')`. The header toggle (line 1002) does `@click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)"`. Persistence = `localStorage` key `'darkMode'` with values `'true'`/`'false'`.
- **Sidebar:** `<aside class="app-sidebar fixed inset-y-0 left-0 z-50 w-72 transition-all duration-300 transform" :class="sidebarOpen ? 'translate-x-0 lg:w-72' : '-translate-x-full lg:translate-x-0 lg:w-[72px] sidebar-collapsed'">`. Collapse state variable = **`sidebarOpen`** (declared in the root flex container `x-data`, initialized `window.innerWidth >= 1024`). Collapsed width = `72px` (icon rail) via the unlayered `aside.sidebar-collapsed` CSS in `app.css`.
- **Header:** `<header class="sticky top-0 z-40 h-16 bg-white/80 dark:bg-dark-bg/80 backdrop-blur-xl border-b border-border dark:border-dark-border px-4 lg:px-6 flex items-center justify-between">`. Height `h-16` (4rem). Contains: hamburger, store-name pill, global search trigger (`Ctrl K`), shortcuts help trigger, **dark-mode toggle**, profile dropdown.
- **Main content:** `<main class="flex-1 ... lg:ml-72 / lg:ml-[72px]">` with `page-padding` wrapper around `{{ $slot }}` (line 1056). **Page-padding convention = the `.page-padding` utility**: `px-6 pb-6 pt-2 lg:px-10 lg:pb-10 lg:pt-3`.
- **Footer:** fixed bottom bar `h-10`, `right-0`, left edge toggles `lg:left-72` / `lg:left-[72px]` to track the sidebar; contains © COREVISYS POS INTEL, Privacy / Terms / Docs links.
- **Mobile overlay:** fixed `z-40` backdrop when `sidebarOpen` on `< lg`.
- **Global components in app layout:** `<x-notification-toast />`, `<x-keyboard-shortcuts />`, `<x-keyboard-shortcuts-modal />`, plus the inline **Global Spotlight Search modal** (command palette, `Ctrl K`/`Cmd K`, `AbortController`-guarded).

### ⚠️ Cross-cutting dark-mode dependency (flag for any dark-mode refactor)

**All SweetAlert2 calls on POS (`pos.blade.php`) and Add Sale (`add.blade.php`) read `document.documentElement.classList.contains('dark')` directly** to pick Swal `background`/`color` (verified: 20+ occurrences in `pos.blade.php`, 10+ in `add.blade.php`). If the `dark` class ever moves off `<html>` (e.g. onto `<body>` or a wrapper div), every Swal modal on these two pages silently loses its dark theme. **Check this before any dark-mode refactor.** The same `:class="{ 'dark': darkMode }"` binding is on `<html>`, so the dependency is currently consistent.

---

## 4. PAGE-BY-PAGE ROLLOUT STATUS

Status legend:
- **Fully complete** = uses design-system components/classes AND money-critical logic is wired/guarded.
- **Styled** = design-system look (tokens/classes) but functional gaps remain.
- **Legacy-styled** = theme-aware (dark: variants, slate palette) but hand-rolled markup (long inline class strings, `bg-slate-*`/`bg-white dark:bg-dark-card rounded-3xl` instead of `.card`/`.btn-*`), no shared components.
- **Not started** = not covered by prior design-system work.

**Verified per page (sampled directly, not assumed from any changelog):**

| Module / Page | Status | Evidence |
| :--- | :--- | :--- |
| **Dashboard** (`resources/views/dashboard.blade.php`) | **Styled** | Uses `btn-primary`/`btn-secondary` quick actions + `x-stat-card` + Chart.js; **but** KPI footer strings are hand-built `@php` HTML passed via `:footer`; uses `text-slate-*`/`dark:text-*` classes rather than text tokens. |
| **Users List** (`module/users/list.blade.php`) | **Styled + functional** | `btn-primary`, `x-stat-card`, `x-searchable-select` filters, `input-base` search. |
| **Users Add/Edit** (`module/users/add.blade.php`, `edit.blade.php`) | **Styled** | `x-searchable-select` role/store, `input-base` inputs, file-upload Alpine previews. |
| **Roles List** (`module/users/roles_list.blade.php`) | **Styled** | `input-base` search. |
| **Roles Add/Edit** (`module/users/roles_add.blade.php`, `roles_edit.blade.php`) | **Styled + functional** | `x-card`, `input-base`; the Blade/Alpine escaping bug was fixed via string concatenation (see §2.5). |
| **Users Show** (`module/users/show.blade.php`, `roles_show.blade.php`) | Not individually audited (low risk) | — |
| **POS** (`module/sales/pos.blade.php`) | **Styled + functional fixes done (money-critical)** | Full design-system look; server-side total validation wired; **double-submit guard added** (see §5.3); server-side EMI-eligibility enforced by shared `storeEmi` (see §5.4). |
| **Add Sale** (`module/sales/add.blade.php`) | **Styled + functional fixes done (money-critical)** | Design-system look; **HAS `submitting` guard**; server-side total validation; Swal dark-theme reads `documentElement`. |
| **Sales List** (`module/sales/list.blade.php`) | **Styled** | `x-searchable-select` filters, hand-rolled table card. |
| **Sales Show / Returns / Returns List / Return Show** (`module/sales/show.blade.php`, `returns_list.blade.php`, `create_return.blade.php`, `return_show.blade.php`) | **Styled** | Refund cap UI + server cap verified in `SalesReturnController`; legacy-ish table cards. |
| **Receive Payment** (`module/sales/receive_payment.blade.php`) | **Styled + functional** | Redesigned with `x-searchable-select`; field names match controller. |
| **Hold List / EMI List / EMI Details** (`module/sales/hold_list.blade.php`, `emi_sale_list.blade.php`, `emi_details.blade.php`) | **Styled** | `x-searchable-select` filters; legacy table cards. |
| **Sales Payments** (`module/sales/payments.blade.php`) | **Styled, functional gap** | Delete button is a **placeholder** (`confirmDeletepayment` only `console.log`s; see §8). |
| **Invoice view/pdf** (`module/sales/invoice/view.blade.php`, `pdf.blade.php`, `header.blade.php`, `footer.blade.php`) | **Styled/complete** | DomPDF + store_settings-driven footer/terms/words. |
| **Purchases** (`module/purchase/new_purchase.blade.php`, `edit_purchase.blade.php`, `purchase_list.blade.php`, `purchase_invoice.blade.php`, `barcode.blade.php`, `create_purchase_return.blade.php`, `purchase_returns_list.blade.php`) | **Styled + functional** | `x-searchable-select` throughout; quick-add modals; weighted-average cost logic (Bug A fix) verified in controller. |
| **Quotations** (`module/quotation/*`) | **Styled** | `x-searchable-select` + hand-rolled inputs. |
| **Items** (`module/items/add_item.blade.php`, `edit_item.blade.php`, `items_list.blade.php`, `add/edit category|brand|variant|service`, `view_item.blade.php`, `print_labels.blade.php`, `pdf_labels.blade.php`, `import_*`) | **Styled** | `x-searchable-select` (with `quickAddClick`), `input-base` in places; variant/serial bulk-paste Alpine. |
| **Stock** (`module/stock/create|edit_adjustment|transfer`, `adjustment_list`, `transfer_list`) | **Styled** | `x-searchable-select` warehouses; legacy dense table cards. |
| **Accounts** (`module/accounts/accounts_list.blade.php`, `add_account.blade.php`, `add_deposit.blade.php`, `add_transfer.blade.php`, `deposit_list`, `money_transfer_list`, `cash_transactions`) | **Legacy-styled** | `bg-white dark:bg-dark-card rounded-3xl border border-slate-100` pattern; `x-searchable-select` in forms. |
| **Cash Reconciliation** (`module/accounts/reconciliation/*`) | **Styled** | Open/Close two-step forms with `@submit="validateForm($event)"`. |
| **Expenses** (`module/expenses/*`) | **Legacy-styled** | Same hand-rolled card pattern; `x-searchable-select` in create. |
| **Contacts** (`module/contacts/add_customer.blade.php`, `add_supplier.blade.php`, `customers_list`, `suppliers_list`, `import_*`) | **Styled** | `x-searchable-select` country/state; multi-step customer KYC Alpine. |
| **Coupons** (`module/coupons/*`) | **Legacy-styled** | Hand-rolled; `x-searchable-select` in customer coupon. |
| **Advance** (`module/advance/add_advance.blade.php`, `advance_list.blade.php`) | **Legacy-styled** | `x-searchable-select` in forms. |
| **Reports** (all `module/reports/*`) | **Styled** | `x-searchable-select` filter panels on nearly every page; Chart.js on `sales_summary`, `cash_flow`; legacy table cards. |
| **SMS** (`module/sms/*`) | **Styled** | Custom composer UI (`sms/send.blade.php`), `x-searchable-select` in auto_rules; **hand-rolled toast** (not the global `<x-notification-toast>`). |
| **Messaging (legacy)** (`module/messaging/*`) | **Legacy-styled** | Older template manager; coexists with the newer `module/sms/*` subsystem (duplicate-module debt — see §8). |
| **Settings** (`module/settings/store.blade.php`) | **Styled + functional** | `x-searchable-select` country/state/currency/language; form posts to `settings.store.update`. |
| **Settings — `site_settings.blade.php`** | **NOT STARTED (dead/placeholder UI)** | Hardcoded inputs (`value="Shop Keeper"`), static logo preview, `Save Changes` button with **no `action`/form binding** — a static mockup, not wired to any route (see §8). |
| **Settings — tax/units/payment_types/currency/languages/countries/states/smtp/sms_api/database_backup** | **Legacy-styled** | Hand-rolled cards/modals; `x-searchable-select` in some. |
| **Warehouse** (`module/warehouse/*`) | **Legacy-styled** | Hand-rolled cards. |
| **Profile** (`module/profile/*`) | **Styled** | `x-input-error`, hand-rolled inputs. |
| **Welcome / guest / public layouts** | **Legacy (separate design)** | Own `blue-600`/Hind Siliguri theme, Font Awesome, Swiper on welcome. |

**Module views NOT covered by prior work (list them explicitly):**
- `module/messaging/*` (legacy SMS template manager — superseded by `module/sms/*`).
- `module/settings/site_settings.blade.php` (dead placeholder UI).
- `module/items/import_items.blade.php`, `import_services.blade.php`, `view_item.blade.php`, `pdf_labels.blade.php` (present, styled, but not part of the shared-component rollout).
- `module/reports/*` secondary pages (`gstr1`, `gstr2`, `purchase_tax`, `purchase_gst`, `sales_gst`, `seller_points`, `supplier_items`, `customer_orders`, `purchase_payments`, `sales_payments`, `return_items`) — styled, but never converted to `x-card`/`x-table` components.

---

## 5. MONEY-CRITICAL LOGIC — PROTECTED REGIONS

### 5.1 Shared `PosController@store` / `storeEmi` validation

- `SaleController@store`, `SaleController@update`, and `SaleController@storeEmi` **delegate to `PosController`** via `app(PosController::class)->store($request)` / `->storeEmi($request)` ([`SaleController.php`](app/Http/Controllers/SaleController.php:58)). So POS and Add Sale share one code path.
- Both `store()` and `storeEmi()` call `private function validateSalePayload(Request $request)` first ([`PosController.php`](app/Http/Controllers/PosController.php:887)). On failure they return HTTP 422 JSON `{success:false, message}` and **do not begin the transaction**.
- `validateSalePayload` checks:
  1. `warehouse_id` present + active `DbWarehouse`.
  2. `customer_id` either the "Walk-in customer"/"Walk-in Customer" literal or an existing `DbCustomer`.
  3. `cart` is a non-empty array.
  4. Per line: item `id` present, `qty` numeric > 0 (fractional allowed — `decimal(16,2)` sells by weight), `price` numeric ≥ 0, item exists and `status = 1`. Quantities are aggregated per item id (duplicate-line protection).
  5. **Stock-oversell check** — the comment in code says: use `db_warehouseitems.available_qty` for the warehouse when the row exists, otherwise fall back to `db_items.stock` (global). If `available < qty` → 422 "Insufficient stock for '…' (requested X, available Y)". **This check is present and verified** — the old "stock-oversell is still partial" note does not match current code.

### 5.2 Server-side total recompute (`recomputeServerTotals`) + `ENFORCE_TOTAL_VALIDATION` kill-switch

- `private function recomputeServerTotals(Request $request, $couponAmt = 0.0)` ([`PosController.php`](app/Http/Controllers/PosController.php:1047)) independently recomputes totals using:
  - **Client per-line price is authoritative** (cashier price-override is a legitimate feature); DB `sales_price` is only a fallback when `price` is omitted. The recompute validates the *arithmetic*, not the price itself.
  - `lineTotal = price * qty`; `lineDisc = min(discount, lineTotal)`; `lineTax = (lineTotal − lineDisc) * tax% / 100`.
  - `serverInvoiceDiscount` = `subtotal * discount_on_all/100` (percent) or flat.
  - `serverTotalDiscount = min(subtotal, itemDisc + invoiceDisc + couponAmt)`.
  - `serverRawPayable = max(0, subtotal + totalTax + otherCharges − totalDiscount − advance)`.
  - If store `round_off` enabled → `serverGrandTotal = round(rawPayable)`; `serverRoundOff = grand − raw`. Else grand = raw, roundOff = 0.
- **Mismatch logic** (both `store` at line 183–212 and `storeEmi` at line 617–636):
  - Accepts the client total if it matches **either** the raw payable **or** the rounded grand total within `0.01` (POS sends the rounded total; Add Sale sends the raw total).
  - On mismatch: `logTotalMismatch(...)` runs, then if `config('sales.enforce_total_validation')` is true → `DB::rollBack()` + 422 "Total mismatch detected… (Server total: …)". If false → falls back to trusting the client total (pre-validation behavior).
  - **The server always persists the server-authoritative total** (and recomputed `round_off`), never the client number.
- **Kill-switch:** config key `sales.enforce_total_validation`, env var `ENFORCE_TOTAL_VALIDATION`, **default `true`** (verified in [`config/sales.php`](config/sales.php:25) and [`.env.example`](.env.example:14)). `true` = reject mismatched totals (422). `false` = recompute + log only, trust client total. Flip in `.env` + `php artisan config:clear` — no code deploy.

### 5.3 Double-submit guard pattern (`submitting` flag)

- **Add Sale (`add.blade.php`):** has the guard. `submitSale()` starts with `if (this.submitting) return;`, sets `this.submitting = true` before the Swal confirm and resets `false` in all `.then`/`.catch`/else branches; all submit buttons have `:disabled="submitting"` ([`add.blade.php`](resources/views/module/sales/add.blade.php:1556)).
- **Add Sale EMI (`submitEmi`):** same guard ([`add.blade.php`](resources/views/module/sales/add.blade.php:1648)).
- **POS (`pos.blade.php`):** **NOW HAS the guard (FIXED 2026-09-01).** `posComponent()` declares `submitting: false`; `submitSale()`, `submitHold()`, and `submitEmi()` all early-return `if (this.submitting)`, set `this.submitting = true` before opening their Swal confirm / issuing the fetch, and reset to `false` in success, error, and cancel paths. Trigger buttons (Pay All, Hold-modal OK, Cash-modal Save/Save & Print, Multiple-modal Save/Save & Print, EMI-modal Create EMI) bind `:disabled="submitting"` with a visible spinner + `Saving...`/`Holding...` label change, mirroring the Add Sale pattern exactly. Coverage: `tests/Feature/PosSubmitGuardTest.php`.
- The global `app.js` auto-wire spinner covers plain server-bound forms (which call `preventDefault` nowhere) — POS/Add Sale skip it via `defaultPrevented`.

### 5.4 Coupon lifecycle (resolve → consume-after-success-only)

- `private function resolveCoupon(Request $request, $subtotal)` ([`PosController.php`](app/Http/Controllers/PosController.php:974)): checks `DbCustomerCoupon` first (by `customer_coupon_id` or code), then master `DbCoupon`. Validates `status == 1`, `expire_date >= today`, customer ownership (customer coupons require `(int)$custCoupon->customer_id === (int)$customerId`). Computes flat or % discount, capped at subtotal.
- **Why the change:** consumption is **deferred**. `resolveCoupon` only *stashes* the customer coupon on `$this->consumedCustomerCoupon`; the flag is set to `status = 0` by `private function consumeCouponAfterSale()` **only after the sale/EMI sale is fully created inside the same transaction** ([`PosController.php`](app/Http/Controllers/PosController.php:963)). If validation, total-mismatch rejection, or any exception rolls back, the coupon is never consumed — a failed sale can't burn a one-time coupon. Verified by `PosStoreValidationTest` tests "customer coupon is NOT consumed when the sale fails total validation" / "IS consumed when the sale succeeds".
- `POST /sales/coupon/validate` → `CouponController::validateCoupon` re-validates server-side (customer coupon first, then master coupon) and returns structured JSON including `discount_amount` ([`CouponController.php`](app/Http/Controllers/CouponController.php:99)).

### 5.5 Remaining known gaps — verified current state (do not assume from old notes)

- **Stock-oversell check: PRESENT** in `validateSalePayload` (see §5.1). Not a gap.
- **EMI eligibility enforcement: FIXED (2026-09-01).** `PosController@storeEmi()` now verifies `db_customers.customer_type` is `emi` for the resolved customer before any EMI logic proceeds (shared by POS and Add Sale). Non-EMI customers and Walk-in customers are rejected with HTTP 422 `{success:false, message:…EMI…}`. The UI `isEmiCustomer` gate remains as UX; the server is now authoritative. Coverage in `PosStoreValidationTest` (4 new EMI eligibility tests).
- **POS double-submit guard: FIXED (2026-09-01)** (see §5.3). Coverage in `PosSubmitGuardTest`.
- **Sales payment delete: PLACEHOLDER** — `module/sales/payments.blade.php` `confirmDeletepayment()` only `console.log`s; the confirm dialog itself even warns "This will NOT update the sale balance automatically in this version." **Open gap.**
- **Purchase ledger: PARTIALLY RESOLVED.** The old KB claimed purchase payments were not ledger-synced. **Current code contradicts that**: `PurchaseController::store()` writes `PURCHASE PAYMENT` (debit to account, decrement `ac_accounts.balance`) and `PURCHASE PAYABLE` (credit) entries; `storeReturn()` writes `PURCHASE RETURN PAYABLE` (debit) and a refund payment record. **However** `ReportController` notes (lines 2282, 2583) still say purchase payments are "not yet connected to the general ledger accounts" — a stale comment, but the Cash Flow statement may exclude purchase-payment flows (see §8).

### 5.6 `sales_mismatch` log channel

- **Exists** in [`config/logging.php`](config/logging.php:93): `driver=daily`, `path=storage/logs/sales_mismatch.log`, `level=warning`, retention `LOG_SALES_MISMATCH_DAYS` default 30.
- Written by `PosController::logTotalMismatch()` via `\Log::channel('sales_mismatch')->warning(...)` ([`PosController.php`](app/Http/Controllers/PosController.php:1209)) — diagnostic only; it never changes rejection behavior (that is governed solely by `enforce_total_validation`).
- Monitor with: `tail -f storage/logs/sales_mismatch-*.log` or `grep -i mismatch storage/logs/sales_mismatch-YYYY-MM-DD.log`.

---

## 6. KNOWN CROSS-PAGE PATTERNS / CONVENTIONS

### 6.1 The 5-button semantic color mapping on payment actions

The codebase uses a consistent semantic palette (defined as tokens in §2.1) on payment/submit actions. Verified usage:
- `btn-primary` (`bg-primary`) → primary "Save" action — Users list, POS multiple-payment modal "Save", Add Sale save.
- `bg-success`/`bg-emerald-500` → "Save & Print"/confirm-success — POS modal `bg-success` Save & Print; Add Sale `bg-emerald-500` confirm button.
- `bg-danger`/`bg-rose-500` → destructive / "Due only" — Add Sale `bg-danger` EMI trigger and `bg-pink-500` "Due" button (pink is used for the "due"/save-without-print variant); `text-rose-*` delete icons everywhere.
- `bg-warning`/`bg-amber-*` → pending/warning states — payment status badges, drawer warnings.
- Neutral → `btn-secondary`, `bg-slate-*` — cancel/back/filter.

Pages that use it: **Add Sale** (`bg-pink-500` due / `bg-emerald-500` save-and-print / `bg-teal-600` EMI confirm), **POS** (`bg-primary` Save, `bg-success` Save & Print, `bg-danger` remove, `bg-warning` notices), **Items list** (`bg-rose-600` New Item, `bg-emerald-600` New Service), **Expenses/Accounts/Warehouse** (`bg-rose-600` add buttons). The exact mapping is: **primary = default save, success/emerald = confirm+print, danger/rose/pink = due-only or delete, warning/amber = advisory, neutral = cancel/filter.**

### 6.2 (Not a separate cross-page pattern — see §6.3.) 

### 6.3 `<x-dropdown>` teleport fix for table-clipping

**Applied and verified.** [`dropdown.blade.php`](resources/views/components/dropdown.blade.php:132) teleports the panel to `<body>` via `<template x-teleport="body">` and positions it **fixed** relative to the trigger's `getBoundingClientRect()` (`triggerRect`), so no ancestor with `transform`/`overflow`/`filter` (table cards, action rows) can clip it or trap its z-index. It repositions on scroll/resize (`window.addEventListener('scroll', …, true)` + `resize`), closes when the trigger leaves the viewport, and parks off-screen (`top/left: -9999px; visibility: hidden`) while open but not yet measured. Alignment is `right` by default; `left` flips the anchor.

### 6.4 Touch-target sizing conventions

- **POS / Add Sale (dense, touch-heavy):** compact targets — buttons `py-1.5`/`py-2`/`py-3`, inputs `py-1.5`–`py-3`, text `text-[10px]`–`text-[11px]`, `x-searchable-select` default `py-2` (POS passes `inputClass="!py-1.5"`), modal buttons `px-6–8 py-2.5–3`. Keyboard-first (Enter-to-add, serial modal, coupon apply).
- **Admin pages elsewhere:** standard density — `.btn-primary`/`.btn-secondary` (`px-4 py-2 text-sm`), `.input-base` (`px-3 py-2 text-sm`), `x-icon-button` sizes `sm=8×8`, `md=9×9`, `lg=10×10`. Report filter `x-searchable-select`s often use default sizing; `inputClass="!py-2"` used on purchase pages.

### 6.5 `<x-searchable-select>` — still the standard, ~131 call sites

Verified: **131 matches** for `x-searchable-select` across `resources/views/**` (grep, 2026-09-01). It remains the app-wide standard for searchable dropdowns (users, POS, add sale, purchase, quotation, stock, accounts, expenses, contacts, coupons, advance, sms auto-rules, settings, and every report filter). No Tom Select dependency exists. Key props in active use: `model` (Alpine `x-model` binding), `optionsExpression` (dynamic option lists, e.g. Store Settings states), `change` (submit filters / `fetchItems()` / `calculateTotals()`), `emptyOption`/`emptyValue` ("All…", "Walk-in customer"), `quickAddClick` (item/brand/category/unit/tax quick-add modals), `inputClass` (density overrides), `subtextKey` (mobile/code hints). It syncs to a hidden `<input name=…>` for form POST and dispatches `select`/`input`/`change` events.

---

## 7. TESTING

### 7.1 Money-critical test files (verified present + passing)

- [`tests/Feature/PosStoreValidationTest.php`](tests/Feature/PosStoreValidationTest.php) (12 tests, all pass): shared `PosController@store`/`storeEmi` validation — valid POS sale, valid Add Sale with tax+other charges, tampered `grand_total` rejected (422) on **both** routes, insufficient stock rejected, empty cart rejected, customer coupon NOT consumed on failed total validation, coupon consumed on success, **EMI store succeeds for an EMI-eligible customer, EMI store rejects tampered total for an EMI-eligible customer, EMI store rejects a non-EMI customer on the POS route (422), EMI store rejects a non-EMI customer on the Add Sale route (422), EMI store rejects a Walk-in customer (422)** — the latter four pin the server-side EMI eligibility gate.
- [`tests/Feature/PosTotalValidationMatrixTest.php`](tests/Feature/PosTotalValidationMatrixTest.php) (29 tests, all pass): exhaustive matrix (baseline, per-item tax, other charges, item discount, global fixed/percent, coupon, all combined ± round_off, advance) against **both** routes; multiple-payment + hold restore; serialized items; tampered total rejected with kill-switch ON, accepted-but-logged with OFF; config default `true` assertion.
- [`tests/Feature/PosSubmitGuardTest.php`](tests/Feature/PosSubmitGuardTest.php) (**NEW, 2 tests, all pass**): view-render pin of the POS double-submit guard — asserts `submitting: false` state exists, ≥3 early-return guards (`if (this.submitting) {`), ≥6 `:disabled="submitting"` button bindings, spinner/label-change visuals, and (regression-protection) that the POS store route, Swal dark-mode coupling, and `pos-screen` fullscreen class are all still present in the rendered page.

### 7.2 The pre-existing unrelated failure — re-verified

- [`tests/Feature/PurchaseAndStockAdjustmentFlowTest.php`](tests/Feature/PurchaseAndStockAdjustmentFlowTest.php) **still fails** (confirmed by running it: `Failed asserting that 11.88 is identical to 15.0` at line 82).
- **Why it fails (verified):** the test asserts `$item->purchase_price` is exactly `15.0` after purchasing 3 units @ 15 into an item that already had 5 units @ 10. But `PurchaseController` applies a **weighted-average cost** (`applyPurchaseCostToItem`, "Bug A fix"): `(10×5 + 15×3)/8 = 11.875 → 11.88`. The production behavior is the weighted average (intended), and the test expectation was never updated. 
- **Is it related to money-critical sales logic? No** — it exercises the purchase/return/stock-adjustment flow, not POS/Add Sale. The 37 POS/Add-Sale tests all pass. Treat it as a stale test, not a product regression. It also asserts `PURCHASE PAYABLE` ledger rows exist — which confirms purchase-payable ledgering is active (see §5.5).

---

## 8. OPEN ITEMS / NOT YET DONE

Pulled from actual code state (grep + read, 2026-09-01):

1. **`module/settings/site_settings.blade.php` is a dead placeholder UI.** No `<form>`/`action`, hardcoded `value="Shop Keeper"`, static logo preview, and a `Save Changes` button that submits nothing. No controller action renders/persists it. Either wire it to `StoreSettingsController` or delete it.
2. **[FIXED 2026-09-01] POS double-submit guard.** `submitting` flag now exists on `posComponent()` and guards `submitSale`/`submitHold`/`submitEmi`; all trigger buttons bind `:disabled="submitting"` (see §5.3).
3. **[FIXED 2026-09-01] Server-side EMI eligibility.** `PosController@storeEmi()` now enforces `customer_type === 'emi'` for the resolved customer (see §5.5).
4. **Sales payment delete is a placeholder.** `module/sales/payments.blade.php` `confirmDeletepayment()` only logs to console and warns the sale balance won't update. There is no `SaleController::destroyPayment` wired to a route.
5. **Stale report comments.** `ReportController` lines 2282/2583 still say purchase payments are "not yet connected to the general ledger accounts", but `PurchaseController` now writes `PURCHASE PAYMENT`/`PURCHASE PAYABLE`/`PURCHASE RETURN PAYABLE` ledger rows. Investigate whether the **Cash Flow statement** excludes purchase-payment/refund movements (it may be intentionally scoped to sales-side flows) and fix the stale comments.
6. **Stale test.** `PurchaseAndStockAdjustmentFlowTest` line 82 expects latest-cost instead of weighted-average cost — update the assertion to `11.88` (or the intended formula) so the suite is green.
7. **Font Awesome spinner glyph is missing on the app layout** — `app.js` loading utility emits `fas fa-circle-notch` but FA is only loaded on `guest`/`public`/`welcome`. The disable-state still works; the spinner icon does not render on app pages.
8. **Duplicate messaging modules.** `module/messaging/*` (older basic template manager, `MessageTemplateController`) coexists with `module/sms/*` (newer Messaging Intelligence). Both are routed and navigable.
9. **Legacy hand-rolled markup is still widespread** (Accounts, Expenses, Coupons, Advance, Warehouse, Settings tax/units/currency/etc.): `bg-white dark:bg-dark-card rounded-3xl border border-slate-100` cards, `bg-rose-600` add buttons, inline `bg-slate-50 dark:bg-slate-800` inputs, and per-page `Copy/Excel/PDF` buttons that are **non-functional decorations** (no handlers) on Expenses/Accounts/Warehouse lists.
10. **Controller-level RBAC remains absent.** Feature controllers gate only by `auth`/`verified` middleware + Blade-level `hasPermission()` — direct HTTP access to restricted URLs is not blocked server-side (unchanged from prior audits; verified in `routes/web.php` group middleware).
11. **`CodeGeneratorService` sequence races** — `MAX(id)+1` scheme is not collision-safe under concurrency (documented in service, unchanged).
12. **`ReportController` fat controller** (~2,300+ lines by file offset evidence) — inline aggregation; no pre-aggregated summary tables for scale.
13. **`module/sales/payments.blade.php` delete placeholder** is item 4 (dedup — listed once above).

---

## 9. QUICK LANDMINE REGISTER (do not re-discover)

- **Swal `dark` reads `document.documentElement.classList`** on POS/Add Sale — break the `dark` class location and every Swal loses its theme (§3).
- **Blade component-tag Alpine attribute escaping** — string-concatenation only; `view:cache` will not catch it (§2.5).
- **`data-no-loading` / `defaultPrevented`** control the global submit-spinner; POS/Add Sale keep their own `submitting` UX — both pages now have the guard (POS fixed 2026-09-01, §5.3). Any new server-hitting POS action must keep the same `submitting` early-return/reset discipline.
- **Client price is authoritative** in `recomputeServerTotals` — tamper protection validates arithmetic, not per-line price; the cashier price-override is intentional (§5.2).
- **Purchase cost is weighted-average, not latest** — tests/expectations must use the average formula (§7.2).
- **`ENFORCE_TOTAL_VALIDATION=false`** only logs to `sales_mismatch`; it never changes persistence behavior other than trusting the client total (§5.2/§5.6).
