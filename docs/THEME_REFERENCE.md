# THEME REFERENCE — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-16
**Method:** every value below was read directly from source this pass; citations use `path:line`. Uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`.
**Related docs:** [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md), [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md), [`README.md`](docs/README.md).

> To re-verify the design system, check the token table in §2 against [`resources/css/app.css`](resources/css/app.css:7) and the component classes in §5 against [`app.css`](resources/css/app.css:188).

---

## 1. Design philosophy (as visible in code)

- **Single indigo accent on neutral slate surfaces** — one brand ramp (`--color-primary*`, [`app.css`](resources/css/app.css:11)) plus a small semantic status set ([`app.css`](resources/css/app.css:41)).
- **Tailwind v4, CSS-first** — there is **no `tailwind.config.js`** in the repo; all tokens are declared in the `@theme` block ([`app.css`](resources/css/app.css:7)) and the Tailwind Vite plugin is wired directly ([`vite.config.js`](vite.config.js:7)).
- **Class-based dark mode** — `.dark` on `<html>`, persisted in `localStorage`, toggled in the layout ([`app.css`](resources/css/app.css:5), [`app.blade.php`](resources/views/layouts/app.blade.php:3)).
- **A real component layer exists now** — semantic classes (`.card`, `.btn-primary`, `.input-base`, …) live in `@layer components` ([`app.css`](resources/css/app.css:188)), in addition to the Blade component library. Feature modules still frequently use raw utility classes inline; both patterns coexist.
- **Two visual identities** — the authenticated app uses "Plus Jakarta Sans / indigo"; the public and guest/auth pages use a Hind Siliguri + Inter "premium" rounded aesthetic ([`app.blade.php`](resources/views/layouts/app.blade.php:21), [`public.blade.php`](resources/views/layouts/public.blade.php:13)).

## 2. Design tokens (authoritative — `@theme` in `resources/css/app.css`)

All values below are read from the Tailwind v4 `@theme` block ([`app.css`](resources/css/app.css:7), closed at [`app.css`](resources/css/app.css:64)). Changing a token here propagates to every `--color-*`/`--shadow-*`/`--radius-*` utility automatically.

### 2.1 Brand / primary (indigo) — [app.css:11](resources/css/app.css:11)

| Token | Value |
|---|---|
| `--color-primary` | `#4f46e5` |
| `--color-primary-hover` | `#4338ca` |
| `--color-primary-light` | `#eef2ff` |
| `--color-primary-50` | `#eef2ff` |
| `--color-primary-100` | `#e0e7ff` |
| `--color-primary-200` | `#c7d2fe` |
| `--color-primary-300` | `#a5b4fc` |
| `--color-primary-400` | `#818cf8` |
| `--color-primary-500` | `#6366f1` |
| `--color-primary-600` | `#4f46e5` |
| `--color-primary-700` | `#4338ca` |
| `--color-primary-800` | `#3730a3` |
| `--color-primary-900` | `#312e81` |
| `--primary-rgb` | `99 102 241` (RGB triplet of primary-500, [`app.css`](resources/css/app.css:27)) |

`--primary-rgb` exists so custom CSS can do `rgba(var(--primary-rgb), <alpha>)` — used by `custom-scrollbar` ([`app.css`](resources/css/app.css:95)).

### 2.2 Surfaces & text — [app.css:30](resources/css/app.css:30)

| Token | Value | Role |
|---|---|---|
| `--color-navy` | `#0f172a` | dark base / sidebar dark surface |
| `--color-card` | `#ffffff` | card surface |
| `--color-background` | `#f8fafc` | page background |
| `--color-border` | `#e2e8f0` | default border |
| `--color-border-light` | `#f1f5f9` | subtle divider |
| `--color-text-primary` | `#0f172a` | primary text |
| `--color-text-secondary` | `#475569` | secondary text |
| `--color-text-muted` | `#94a3b8` | muted/labels |

### 2.3 Status — [app.css:41](resources/css/app.css:41)

| Token | Value |
|---|---|
| `--color-danger` | `#dc2626` |
| `--color-danger-hover` | `#b91c1c` |
| `--color-danger-light` | `#fef2f2` |
| `--color-warning` | `#d97706` |
| `--color-warning-light` | `#fffbeb` |
| `--color-success` | `#059669` |
| `--color-success-light` | `#ecfdf5` |

### 2.4 Shadows — [app.css:50](resources/css/app.css:50)

| Token | Value | Use |
|---|---|---|
| `--shadow-card` | `0 1px 2px 0 rgba(15, 23, 42, 0.04)` | resting card |
| `--shadow-card-hover` | `0 4px 12px -2px rgba(15, 23, 42, 0.08)` | card hover |
| `--shadow-dropdown` | `0 8px 24px -4px rgba(15, 23, 42, 0.12)` | menus/popovers |
| `--shadow-modal` | `0 24px 48px -12px rgba(15, 23, 42, 0.24)` | modals |

### 2.5 Radius — [app.css:56](resources/css/app.css:56)

| Token | Value |
|---|---|
| `--radius-card` | `0.75rem` |
| `--radius-input` | `0.5rem` |
| `--radius-button` | `0.5rem` |

### 2.6 Dark-mode palette — [app.css:60](resources/css/app.css:60)

| Token | Value |
|---|---|
| `--color-dark-bg` | `#0f172a` |
| `--color-dark-card` | `#1e293b` |
| `--color-dark-border` | `#334155` |
| `--color-dark-text` | `#f8fafc` |

### 2.7 Typography — [app.css:8](resources/css/app.css:8)

- `--font-sans`: `"Plus Jakarta Sans", "Hind Siliguri", "Figtree", sans-serif`.
- App layout loads `Plus Jakarta Sans` from Google Fonts ([`app.blade.php`](resources/views/layouts/app.blade.php:21)).
- Public layout loads `Plus Jakarta Sans` + `Hind Siliguri` ([`public.blade.php`](resources/views/layouts/public.blade.php:13)).

## 3. Theme variants & base rules

- Dark variant: `@custom-variant dark (&:where(.dark, .dark *))` — `dark:` utilities apply within any `.dark` ancestor ([`app.css`](resources/css/app.css:5)).
- Alpine loading guard: `[x-cloak] { display: none !important; }` in `@layer base` ([`app.css`](resources/css/app.css:68)).
- Tailwind plugins: `@plugin "@tailwindcss/forms"` and `@plugin "@tailwindcss/typography"` ([`app.css`](resources/css/app.css:2), [`app.css`](resources/css/app.css:3)).

## 4. Custom utilities (project-defined, `@utility`)

| Utility | Purpose | Evidence |
|---|---|---|
| `scrollbar-hide` | Hides scrollbars (webkit + Firefox) | [`app.css`](resources/css/app.css:74) |
| `custom-scrollbar` | 4px themed scrollbar using `--primary-rgb` | [`app.css`](resources/css/app.css:84) |
| `anime-fade-in` | 0.3s fade + translateY entrance animation | [`app.css`](resources/css/app.css:105) |
| `gradient-bg` | Animated 4-stop gradient background for guest/landing pages | [`app.css`](resources/css/app.css:122) |
| `hero-gradient` | Radial highlight for marketing hero sections | [`app.css`](resources/css/app.css:142) |
| `shadow-premium` | Soft large shadow | [`app.css`](resources/css/app.css:146) |
| `shadow-premium-lg` | Larger premium shadow | [`app.css`](resources/css/app.css:150) |
| `pulse-orange` | Infinite orange pulse ring | [`app.css`](resources/css/app.css:154) |

Keyframes: `fade-in` ([`app.css`](resources/css/app.css:109)), `gradient-bg` ([`app.css`](resources/css/app.css:128)), `pulse-orange` ([`app.css`](resources/css/app.css:158)).

Unlayered rule: `.pos-screen:fullscreen` fills the viewport when the POS page enters browser fullscreen ([`app.css`](resources/css/app.css:174)), with a dark variant ([`app.css`](resources/css/app.css:183)).

These all carry "formerly inline in <view>" comments — the project has been actively migrating per-view `<style>` blocks into this central stylesheet.

## 5. Design-system component classes (`@layer components`) — [app.css:188](resources/css/app.css:188)

| Class | Definition |
|---|---|
| `.card` | `bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-card shadow-card` ([`app.css`](resources/css/app.css:189)) |
| `.card-hover` | hover shadow transition ([`app.css`](resources/css/app.css:193)) |
| `.page-padding` | responsive page padding (`px-6 pb-6 pt-2 lg:px-10 lg:pb-10 lg:pt-3`) ([`app.css`](resources/css/app.css:197)) |
| `.input-base` | standard form input ([`app.css`](resources/css/app.css:201)) |
| `.btn-primary` | indigo filled button ([`app.css`](resources/css/app.css:205)) |
| `.btn-secondary` | bordered surface button ([`app.css`](resources/css/app.css:209)) |
| `.btn-danger` | red filled button ([`app.css`](resources/css/app.css:213)) |
| `.btn-ghost` | text/ghost button ([`app.css`](resources/css/app.css:217)) |

**Sidebar theming** is centralised (unlayered so it beats utility hover states): collapsed-rail rules on `aside.sidebar-collapsed` ([`app.css`](resources/css/app.css:224)), sidebar tooltips ([`app.css`](resources/css/app.css:279)), and app-sidebar surfaces — light `var(--color-card)` / dark `var(--color-navy)` ([`app.css`](resources/css/app.css:296), [`app.css`](resources/css/app.css:301)), plus dark submenu link colours ([`app.css`](resources/css/app.css:315)).

## 6. Interaction patterns

### 6.1 Button loading state (global)

[`resources/js/app.js`](resources/js/app.js:34) exposes `setButtonLoading(button, label)` ([`app.js`](resources/js/app.js:34)) and `resetButtonLoading(button)` ([`app.js`](resources/js/app.js:60)):

- Disables the button, adds `opacity-75`/`cursor-not-allowed` ([`app.js`](resources/js/app.js:24), [`app.js`](resources/js/app.js:44)), stashes the original `innerHTML` on `data-original-html` ([`app.js`](resources/js/app.js:40)).
- A single global `submit` listener auto-wires **plain** form submissions — it skips anything that already called `preventDefault()` ([`app.js`](resources/js/app.js:83)) or opted out via `data-no-loading` ([`app.js`](resources/js/app.js:93)).
- Both functions are exposed on `window` for manual AJAX opt-in ([`app.js`](resources/js/app.js:75)).
- **Caveat (ISSUE-9):** the spinner markup uses a Font Awesome icon (`<i class="fas fa-circle-notch fa-spin">`, [`app.js`](resources/js/app.js:50)) but the authenticated app layout loads **no Font Awesome** ([`app.blade.php`](resources/views/layouts/app.blade.php:21)), so the glyph may not render.

### 6.2 Alpine.js

Alpine is imported and started globally in [`app.js`](resources/js/app.js:2) and [`app.js`](resources/js/app.js:6); the collapse plugin is a dependency ([`package.json`](package.json:23)). The app layout uses Alpine for the sidebar, dropdowns, notification panel and dark-mode toggle ([`app.blade.php`](resources/views/layouts/app.blade.php:28)). **Note:** the public layout loads only CSS, not `app.js` ([`public.blade.php`](resources/views/layouts/public.blade.php:19)), so Alpine directives on public pages rely on per-page scripts (ISSUE-7).

## 7. Blade component library (inventory)

All under `resources/views/components/` — **27 files** (enumerated from the directory listing):

| Group | Components |
|---|---|
| Branding / SEO | `application-logo`, `seo-meta` |
| Layout primitives | `card`, `stat-card`, `badge`, `table` |
| Buttons | `primary-button`, `secondary-button`, `danger-button`, `icon-button`, `report-export-buttons` |
| Form controls | `text-input`, `textarea`, `select`, `input-label`, `input-error`, `searchable-select` |
| Navigation / overlays | `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link` |
| UX helpers | `notification-toast`, `sidebar-tooltip`, `keyboard-shortcuts`, `keyboard-shortcuts-modal` |
| Auth | `auth-session-status` |

**Naming convention:** components are referenced via `<x-component-name>`; class-based components `AppLayout`/`GuestLayout`/`PublicLayout` live under `app/View/Components/`. `[UNVERIFIED]` per-component `@props` tables were not exhaustively extracted in this pass — read each component's `@props` before relying on its API.

## 8. Layouts

| Layout | Fonts | CSS/JS | Icons |
|---|---|---|---|
| [`layouts/app.blade.php`](resources/views/layouts/app.blade.php:1) (authenticated) | Plus Jakarta Sans | `@vite(css+js)` + `asset('vendor/chart.umd.min.js')` | inline SVG; **no Font Awesome** |
| [`layouts/public.blade.php`](resources/views/layouts/public.blade.php:1) (marketing) | Plus Jakarta Sans + Hind Siliguri | `@vite(css)` only | Font Awesome 6 (cdnjs) |
| [`resources/views/welcome.blade.php`](resources/views/welcome.blade.php:1) (landing) | — | uses the public layout | Font Awesome via the public layout |

The app layout derives the browser tab title from the page name + store name ([`app.blade.php`](resources/views/layouts/app.blade.php:10)) and renders the notification toast component.

## 9. Tailwind class conventions seen in feature views

- Spacing/type scale is driven by small explicit utilities; label/meta text commonly uses `text-[10px] font-bold uppercase tracking-widest`.
- Newer screens prefer the semantic component classes (`.card`, `.btn-primary`, `.input-base`) from §5.
- Card surfaces commonly use `bg-white dark:bg-dark-card border border-border rounded-card`-style utilities or the `x-card` component.
- Dark mode is applied with `dark:` variants throughout (e.g. `text-text-primary dark:text-dark-text`).
- **Legacy looseness:** some views still use long inline utility strings; this is convention-by-history, not a token violation.

## 10. Assets & build

- Entry points: `resources/css/app.css` and `resources/js/app.js`, wired via `@vite(...)` ([`vite.config.js`](vite.config.js:9)).
- Build output: [`public/build/manifest.json`](public/build/manifest.json:1) → `app-EY2auKIM.css`, `app-vZIy2K19.js`.
- Vendored third-party libs (served as-is, not bundled): `public/vendor/chart.umd.min.js` ([`app.blade.php`](resources/views/layouts/app.blade.php:19)). `ReportPhase7RedesignTest` asserts external CDN scripts were vendored locally and the vendored assets exist on disk.
- Uploaded media served from `public/uploads/...` (item images under `public/uploads/items/`).
- Build with `npm run build`; dev with `npm run dev` ([`package.json`](package.json:6), [`package.json`](package.json:7)).

## 11. Contribution rules (derived, not stated upstream)

`[INFERENCE]` These follow from the code, not from an existing written policy:

1. **Change tokens in the `app.css` `@theme` block**, never inline a new brand hex — brand colour is centralised at [`app.css`](resources/css/app.css:7).
2. **Prefer a semantic class or an existing component** (`.btn-primary`, `x-card`, `x-badge`, …) over re-styling a raw element.
3. **Use `dark:` variants** for anything on a themed surface; the app ships dark mode.
4. **Put new cross-page utilities in `@utility`**, not in per-view `<style>` blocks — the project has visibly been migrating in this direction.
5. **Do not add a `tailwind.config.js`** — v4 CSS-first is intentional.
6. **If you rely on Font Awesome inside the authenticated app**, verify the icon source, because the app layout does not load it (ISSUE-9).
