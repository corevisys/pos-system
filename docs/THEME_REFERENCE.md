# THEME REFERENCE — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-13
**Method:** every value read directly from source; citations use `path:line`. Uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`. Prior audit history has been superseded and is no longer preserved in this document.
**Related docs:** [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md), [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md), [`README.md`](docs/README.md).

> To re-verify the design system, check the token table in §2 against [`resources/css/app.css`](resources/css/app.css:7).

---

## 1. Design philosophy (as visible in code)

- **Single indigo accent on neutral slate surfaces** — one brand ramp (`--color-primary*`) plus a small semantic status set ([`app.css`](resources/css/app.css:10), [`app.css`](resources/css/app.css:40)).
- **Tailwind v4, CSS-first** — there is no `tailwind.config.js`; all tokens are declared in the `@theme` block and there is no JS config to keep in sync ([`app.css`](resources/css/app.css:7)).
- **Class-based dark mode** — `.dark` on `<html>`, persisted in `localStorage`, toggled in the layout ([`app.css`](resources/css/app.css:5), [`app.blade.php`](resources/views/layouts/app.blade.php:3)).
- **Component-ish but not a formal design system** — a set of Blade components exists for primitives, but feature modules frequently use raw utility classes inline. Both patterns coexist; neither is enforced ([`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.2).
- **Two visual identities** — the authenticated app uses the "Plus Jakarta Sans / indigo" system; the public and guest/auth pages use a Hind Siliguri + Inter system with a "premium" rounded aesthetic ([`app.blade.php`](resources/views/layouts/app.blade.php:21), [`guest.blade.php`](resources/views/layouts/guest.blade.php:14), [`public.blade.php`](resources/views/layouts/public.blade.php:13)).

## 2. Design tokens (authoritative — `@theme` in `resources/css/app.css`)

All values below are read from [`resources/css/app.css`](resources/css/app.css:7) (the Tailwind v4 `@theme` block). Changing a token here propagates to every `--color-*`/`--shadow-*`/`--radius-*` utility automatically.

### 2.1 Brand / primary (indigo) — [app.css:10](resources/css/app.css:10)
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
| `--primary-rgb` | `99 102 241` (RGB triplet of primary-500) |

`--primary-rgb` exists so custom CSS can do `rgba(var(--primary-rgb), <alpha>)` — used by the `custom-scrollbar` utility ([`app.css`](resources/css/app.css:95)).

### 2.2 Surfaces & text — [app.css:29](resources/css/app.css:29)
| Token | Value | Role |
|---|---|---|
| `--color-navy` | `#0f172a` | dark base / headings |
| `--color-card` | `#ffffff` | card surface |
| `--color-background` | `#f8fafc` | page background |
| `--color-border` | `#e2e8f0` | default border |
| `--color-border-light` | `#f1f5f9` | subtle divider |
| `--color-text-primary` | `#0f172a` | primary text |
| `--color-text-secondary` | `#475569` | secondary text |
| `--color-text-muted` | `#94a3b8` | muted/labels |

### 2.3 Status — [app.css:40](resources/css/app.css:40)
| Token | Value |
|---|---|
| `--color-danger` | `#dc2626` |
| `--color-danger-hover` | `#b91c1c` |
| `--color-danger-light` | `#fef2f2` |
| `--color-warning` | `#d97706` |
| `--color-warning-light` | `#fffbeb` |
| `--color-success` | `#059669` |
| `--color-success-light` | `#ecfdf5` |

### 2.4 Shadows — [app.css:49](resources/css/app.css:49)
| Token | Value | Use |
|---|---|---|
| `--shadow-card` | `0 1px 2px 0 rgba(15,23,42,0.04)` | resting card |
| `--shadow-card-hover` | `0 4px 12px -2px rgba(15,23,42,0.08)` | card hover |
| `--shadow-dropdown` | `0 8px 24px -4px rgba(15,23,42,0.12)` | menus/popovers |
| `--shadow-modal` | `0 24px 48px -12px rgba(15,23,42,0.24)` | modals |

### 2.5 Radius — [app.css:55](resources/css/app.css:55)
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
- `--font-sans`: `"Plus Jakarta Sans", "Hind Siliguri", "Figtree", sans-serif` (app layout loads Plus Jakarta Sans from Google Fonts, [`app.blade.php`](resources/views/layouts/app.blade.php:21)).
- Guest/login pages override the body font to `'Hind Siliguri', 'Inter'` ([`guest.blade.php`](resources/views/layouts/guest.blade.php:23)).
- Public page loads `Plus Jakarta Sans` + `Hind Siliguri` ([`public.blade.php`](resources/views/layouts/public.blade.php:13)); welcome page loads `Hind Siliguri` + `Inter` ([`welcome.blade.php`](resources/views/welcome.blade.php:9)).

## 3. Theme variants & base rules — [app.css:5](resources/css/app.css:5)

- Dark variant: `@custom-variant dark (&:where(.dark, .dark *))` — so `dark:` utilities apply within any `.dark` ancestor ([`app.css`](resources/css/app.css:5)).
- Alpine loading guard: `[x-cloak] { display: none !important; }` in `@layer base` ([`app.css`](resources/css/app.css:68)).
- Tailwind plugins: `@plugin "@tailwindcss/forms"`, `@plugin "@tailwindcss/typography"` ([`app.css`](resources/css/app.css:2), [`app.css`](resources/css/app.css:3)).

## 4. Custom utilities (project-defined, `@utility`)

| Utility | Purpose | Evidence |
|---|---|---|
| `scrollbar-hide` | Hides scrollbars (webkit + Firefox) | [`app.css`](resources/css/app.css:74) |
| `custom-scrollbar` | 4px themed scrollbar using `--primary-rgb` | [`app.css`](resources/css/app.css:84) |
| `anime-fade-in` | 0.3s fade + translateY entrance animation | [`app.css`](resources/css/app.css:105) |

Keyframes: `fade-in` ([`app.css`](resources/css/app.css:109)). These utilities were moved out of inline `<style>` blocks in Blade views into the central stylesheet (comments reference the origin: [`app.css`](resources/css/app.css:66), [`…:83`](resources/css/app.css:83), [`…:104`](resources/css/app.css:104)).

## 5. Interaction patterns

### 5.1 Button loading state (global)
[`resources/js/app.js`](resources/js/app.js:34) exposes `setButtonLoading(button, label)` and `resetButtonLoading(button)`:
- Disables the button, adds `opacity-75`/`cursor-not-allowed`, stashes original `innerHTML`, and swaps in a spinner + label ([`app.js`](resources/js/app.js:34), [`app.js`](resources/js/app.js:24)).
- A single global `submit` listener auto-wires **plain** form submissions (those that do not `preventDefault()` and do not opt out via `data-no-loading`); AJAX/Swal flows opt out by calling `preventDefault()` ([`app.js`](resources/js/app.js:17)).
- **Caveat (ISSUE-9):** the spinner markup uses a Font Awesome icon (`<i class="fas fa-circle-notch fa-spin">`) but the authenticated app layout does **not** load Font Awesome, so the icon may not render ([`app.js`](resources/js/app.js:50) vs [`app.blade.php`](resources/views/layouts/app.blade.php:1)).

### 5.2 Alpine.js
Alpine is imported and started globally in [`app.js`](resources/js/app.js:2); collapse plugin is a dependency ([`package.json`](package.json:23)). The app layout uses Alpine for the sidebar, dropdowns, notification panel and dark-mode toggle ([`app.blade.php`](resources/views/layouts/app.blade.php:28)). **Note:** the public layout loads only CSS, not `app.js`, so Alpine directives on public pages rely on per-page scripts (ISSUE-7).

## 6. Blade component library (inventory)

All under `resources/views/components/` (27 files):

| Group | Components |
|---|---|
| Branding / SEO | `application-logo`, `seo-meta` |
| Layout primitives | `card`, `stat-card`, `badge`, `table` |
| Buttons | `primary-button`, `secondary-button`, `danger-button`, `icon-button`, **`report-export-buttons`** |
| Form controls | `text-input`, `textarea`, `select`, `input-label`, `input-error`, `searchable-select` |
| Navigation / overlays | `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link` |
| UX helpers | `notification-toast`, `sidebar-tooltip`, `keyboard-shortcuts`, `keyboard-shortcuts-modal` |
| Auth | `auth-session-status` |

**Naming convention:** components are referenced via `<x-component-name>`; class-based components `AppLayout`/`GuestLayout` live under [`app/View/Components/`](app/View/Components/AppLayout.php:1). `[UNVERIFIED]` per-component `@props` tables were not exhaustively extracted in this pass — read each component's `@props` before relying on its API.

## 7. Layouts

| Layout | Fonts | CSS/JS | Icons |
|---|---|---|---|
| [`layouts/app.blade.php`](resources/views/layouts/app.blade.php:1) (authenticated) | Plus Jakarta Sans | `@vite(css+js)` + `asset('vendor/chart.umd.min.js')` | inline SVG; **no Font Awesome** |
| [`layouts/guest.blade.php`](resources/views/layouts/guest.blade.php:1) (login/register) | Hind Siliguri + Inter | `@vite(css+js)` | Font Awesome 6 (cdnjs) |
| [`layouts/public.blade.php`](resources/views/layouts/public.blade.php:1) (marketing) | Plus Jakarta Sans + Hind Siliguri | `@vite(css)` only | Font Awesome 6 (cdnjs) |
| [`welcome.blade.php`](resources/views/welcome.blade.php:1) (landing) | Hind Siliguri + Inter | `@vite(css)` | Font Awesome 6 (cdnjs) |

The app layout also derives the browser tab title from the page name + store name ([`app.blade.php`](resources/views/layouts/app.blade.php:10)).

## 8. Tailwind class conventions seen in feature views

- Spacing/type scale is driven by small explicit utilities rather than a spacing token layer; label/meta text commonly uses `text-[10px] font-bold uppercase tracking-widest` (e.g. [`multi-store-dashboard.blade.php`](resources/views/multi-store-dashboard.blade.php:17)).
- Buttons in newer screens use semantic helper classes (`btn-primary`, `btn-secondary`) defined where needed, e.g. [`multi-store-dashboard.blade.php`](resources/views/multi-store-dashboard.blade.php:24).
- Cards use the `card` component or `bg-white dark:bg-dark-card border border-border rounded-[--radius-card]`-style utilities.
- Dark mode is applied with `dark:` variants throughout (e.g. `text-text-primary dark:text-dark-text`).
- **Legacy looseness:** many views still use long inline utility strings and occasional `!important`-style escapes; this is convention-by-history, not a token violation ([`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.2).

## 9. Assets & build

- Entry points: `resources/css/app.css` and `resources/js/app.js`, wired via `@vite(...)`.
- Build output: [`public/build/manifest.json`](public/build/manifest.json:1) → `app-EY2auKIM.css`, `app-vZIy2K19.js`.
- Vendored third-party libs (served as-is, not bundled): `public/vendor/chart.umd.min.js`, `public/vendor/moment.min.js`.
- Uploaded media served from `public/uploads/...` (e.g. item images under `public/uploads/items/`).
- Build with `npm run build`; dev with `npm run dev` ([`package.json`](package.json:6), [`package.json`](package.json:7)).

## 10. Contribution rules (derived, not stated upstream)

`[INFERENCE]` These are conclusions from the code, not an existing written policy:

1. **Change tokens in `app.css` `@theme`, never inline a new brand hex.** Brand colour is centralised at [`app.css`](resources/css/app.css:7); a stray hex breaks the single-accent rule.
2. **Prefer an existing component** (`x-primary-button`, `x-card`, `x-badge`, …) over re-styling a raw element.
3. **Use `dark:` variants** for anything on a themed surface; the app ships dark mode.
4. **Put new cross-page utilities in `@utility`**, not in per-view `<style>` blocks (the project has been actively migrating in this direction — see the "formerly inline" comments in [`app.css`](resources/css/app.css:66)).
5. **Do not add a `tailwind.config.js`** — v4 CSS-first is intentional.
6. **If you rely on Font Awesome inside the authenticated app**, verify the icon source, because the app layout does not load it (ISSUE-9).
