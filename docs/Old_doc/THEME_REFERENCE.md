

| Item | Value | File |
|---|---|---|
| CSS Framework | **Tailwind CSS v4** | `package.json` |
| Build Tool | **Vite** | `vite.config.js` |
| JS Library | **Alpine.js** | `resources/js/app.js` |
| Icons (Backend) | **Font Awesome 6.4.0** | layouts e CDN |
| Icons (Auth/Frontend) | **Lucide** | CDN |
| Charts | **Chart.js 4.4.1** | CDN |

`package.json` e ei dependencies thakbe:
```json
"dependencies": {
    "@tailwindcss/vite": "^4.1.11",
    "tailwindcss": "^4.0.7",
    "vite": "^7.0.4",
    "laravel-vite-plugin": "^2.0"
}
"devDependencies": {
    "alpinejs": "^3.15.4"
}
```

`vite.config.js`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        tailwindcss(),
    ],
});
```

---

## 2. 🔤 FONT (Sizes)

### Font Sizes (project joto jaiga use hoy):
| Element | Class / Size |
|---|---|
| Page Title | `text-2xl font-bold` (24px) |
| Card Title / Heading | `text-base font-semibold` (16px) |
| Stat Card Value | `text-2xl font-bold` |
| Stat Card Label | `text-xs font-medium uppercase tracking-wider` (12px) |
| Table / Form Text | `text-sm` (14px) |
| Sidebar Menu | `text-sm font-medium` |
| Section Kicker | `text-[10px] font-semibold uppercase tracking-widest` |
| Small muted text | `text-[10px]` / `text-xs text-text-muted` |
| Breadcrumb | `text-sm` |
| Sidebar Group Header | `text-[10px] font-semibold uppercase tracking-widest` |

---

## 4. 🔲 SHADOW + RADIUS (Card, Dropdown, Button)

```css
--shadow-card: 0 1px 2px 0 rgba(15, 23, 42, 0.04);
--shadow-card-hover: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
--shadow-dropdown: 0 8px 24px -4px rgba(15, 23, 42, 0.12);
--shadow-modal: 0 24px 48px -12px rgba(15, 23, 42, 0.24);

--radius-card: 0.75rem;     /* 12px - cards/modals */
--radius-input: 0.5rem;     /* 8px - inputs */
--radius-button: 0.5rem;    /* 8px - buttons */
```

**Note:** Buttons normally `rounded-lg`, Cards `rounded-xl` / `rounded-[var(--radius-card)]`, Inputs `rounded-lg`.

---

## 5. 🔘 BUTTON STYLES (Sizes + Variants)

### Primary Button (Standard size)
```html
<button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors shadow-sm">
    <i class="fa-solid fa-plus text-xs"></i> Create
</button>
```
- Padding: `px-4 py-2`
- Text: `text-sm font-medium`
- Radius: `rounded-lg`
- Hover: `hover:bg-primary-hover`
- Shadow: `shadow-sm`

### Primary Button (Large - form submit)
```html
<button class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl bg-primary text-white hover:bg-primary-hover transition-colors shadow-sm">
    Save
</button>
```

### Secondary / Outline Button (Cancel)
```html
<a class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white text-text-primary border border-border hover:bg-background transition-all shadow-sm">
    Cancel
</a>
```

### Small Button (Header "Create")
```html
<button class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary hover:bg-primary-hover rounded-lg transition-colors shadow-sm">
    <i class="fa-solid fa-plus text-xs"></i> Create
</button>
```

### Icon Button Component (`components/icon-button.blade.php`)
```css
base: 'inline-flex items-center justify-center rounded-lg transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40'
variants:
  default: 'text-text-secondary hover:text-text-primary hover:bg-slate-100'
  primary: 'text-primary hover:bg-primary-light'
  danger:  'text-danger hover:bg-danger-light'
  ghost:   'text-text-muted hover:text-text-primary'
sizes:
  sm: 'h-8 w-8 text-xs' | md: 'h-9 w-9 text-sm' | lg: 'h-10 w-10 text-base'
```

### Danger Button
```html
<button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-danger hover:bg-danger-hover rounded-lg transition-colors shadow-sm">
    <i class="fa-solid fa-circle-exclamation"></i> Report Issue
</button>
```

### Button Loading State (JS handles: `resources/js/app.js`)
```js
// Submit kora hole button auto "Processing..." + spinner hoy
submitBtn.disabled = true;
submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-lg"></i> <span class="ml-2">Processing...</span>';
```

---

## 6. 📐 LAYOUT SIZES (Layout Dimensions)

### Main App Shell (User Layout)
```html
<body class="bg-background font-sans antialiased overflow-hidden" x-data="appShell()">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        @include('backend.components.sidebar')
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            @include('backend.components.header')   <!-- h-16 -->
            <main class="flex-1 overflow-y-auto bg-background">
                @yield('content')
            </main>
        </div>
    </div>
</body>
```

### Sidebar
| Property | Value |
|---|---|
| Expanded width | **288px** (w-72) |
| Collapsed width | **72px** |
| Background | `bg-navy` (#0F172A) |
| Text | `text-slate-300` |
| Logo height | `h-16` |
| Menu padding | `py-4 px-3 space-y-1` |
| Menu item | `px-3 py-2.5 rounded-lg text-sm font-medium` |
| Active menu | `bg-primary text-white` |
| Inactive menu | `text-slate-400 hover:text-white hover:bg-white/5` |
| Group header | `text-[10px] font-semibold text-slate-500 uppercase tracking-widest` |
| Mobile sidebar | `w-72` fixed overlay + `bg-navy/60 backdrop-blur-sm` overlay |

Sidebar collapse logic (Alpine):
```js
sidebarWidth() { return this.sidebarCollapsed ? '72px' : '288px'; }
```

### Header (Topbar)
```html
<header class="h-16 bg-card border-b border-border flex items-center justify-between px-4 lg:px-6 shrink-0 sticky top-0 z-20">
```
- Height: **64px (h-16)**
- Background: white card
- Padding: `px-4 lg:px-6`

### Content Padding
```html
<main class="p-6 lg:p-10">   <!-- standard pages -->
<main class="p-6">          <!-- admin pages -->
```

### Card
```html
<x-card>  <!-- or -->
<div class="bg-card border border-border rounded-[var(--radius-card)] shadow-sm p-5 card-hover">
```
- Padding: `p-5` default
- Radius: 12px
- Border + shadow-sm

### Stat Card
```html
<div class="bg-card border border-border rounded-[var(--radius-card)] shadow-sm p-5">
    <p class="text-xs font-medium text-text-secondary uppercase tracking-wider mb-1.5">Label</p>
    <p class="text-2xl font-bold text-text-primary">Value</p>
    <!-- icon: h-10 w-10 rounded-lg iconBg iconColor -->
</div>
```

### Modal
```html
<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-navy/60 backdrop-blur-sm"></div>
    <div class="relative bg-card rounded-xl shadow-modal border border-border w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-border"><h3 class="text-base font-semibold">Title</h3></div>
        <div class="flex-1 overflow-y-auto p-6">{{ $slot }}</div>
        <div class="px-6 py-4 border-t border-border bg-background">{{ $footer }}</div>
    </div>
</div>
```

### Table
```html
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-background border-b border-border"></thead>
        <tbody class="divide-y divide-border-light"></tbody>
    </table>
</div>
```
Tables often wrapped in `<x-card padding="p-0">`.

### Form Input
```html
<input class="w-full px-3 py-2 text-sm bg-card border border-border rounded-lg text-text-primary placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
```
- Padding: `px-3 py-2`
- Text: `text-sm`
- Radius: `rounded-lg`
- Focus: blue border + ring

### Label
```html
<label class="block text-sm font-medium text-text-primary">Label <span class="text-danger">*</span></label>
```

### Dropdown
```html
<div class="absolute right-0 top-full mt-2 w-56 bg-card rounded-lg shadow-dropdown border border-border py-1.5 z-50">
```
- Width: `w-56` (224px)
- Radius: `rounded-lg`
- Shadow: `shadow-dropdown`

---

## 7. 🧩 STATUS BADGES / COLORS

```css
.status-open { background-color: var(--color-primary-light); color: var(--color-primary); }
.status-progress { background-color: var(--color-warning-light); color: var(--color-warning); }
.status-solved { background-color: var(--color-success-light); color: var(--color-success); }
.status-critical { background-color: var(--color-danger-light); color: var(--color-danger); }
```

Badge pattern: `bg-{color}-100 text-{color}-600/700` (Tailwind safelist included in app.css).

---

## 8. 🌐 AUTH PAGES THEME (Dark Glass Design)

Login/Register/Forgot-password pages use a **separate dark glass theme**:

```css
body { background-color: #0F172A; color: #F8FAFC; }

.glass-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
}

.gradient-bg { background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); }
.gradient-text { background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); -webkit-background-clip: text; color: transparent; }
```

### Animated Blob Background (Keyframes)
```js
animation: { 'blob': 'blob 7s infinite' },
keyframes: {
    blob: {
        '0%':   { transform: 'translate(0px, 0px) scale(1)' },
        '33%':  { transform: 'translate(30px, -50px) scale(1.1)' },
        '66%':  { transform: 'translate(-20px, 20px) scale(0.9)' },
        '100%': { transform: 'translate(0px, 0px) scale(1)' }
    }
}
```

Blob elements:
```html
<div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/20 rounded-full mix-blend-screen filter blur-[100px] animate-blob"></div>
<div class="absolute top-1/3 right-1/4 w-96 h-96 bg-secondary/20 rounded-full mix-blend-screen filter blur-[100px] animate-blob animation-delay-2000"></div>
```

Auth form input:
```html
<input class="w-full pl-10 pr-4 py-3 bg-slate-800/50 border border-white/10 rounded-xl focus:ring-2 focus:ring-primary/50 focus:border-primary/50 focus:outline-none text-white placeholder-slate-500 transition-all">
```

Auth submit button:
```html
<button class="w-full py-3.5 mt-2 gradient-bg hover:shadow-lg hover:shadow-primary/40 text-white font-semibold rounded-xl transition-all flex items-center justify-center gap-2 transform hover:-translate-y-0.5">
    Sign In <i data-lucide="arrow-right" class="w-4 h-4"></i>
</button>
```

---

## 10. 📁 REUSABLE BLADE COMPONENTS

| Component | File | Description |
|---|---|---|
| Card | ` | Card wrapper |
| Stat Card | ` | Dashboard stat |
| Input | ` | Form input |
| Select | ` | Form select |
| Textarea | ` | Form textarea |
| Button/Icon | ` | Icon button |
| Search | ` | Global search (⌘K) |
| Dropdown | ` | Dropdown menu |
| Modal | ` | Modal dialog |
| Table | ` | Data table |
| Tabs | ` | Tab navigation |
| Alert | ` | Info/success/warning/danger |
| Toast | ` | Toast notifications |
| Skeleton | ` | Loading skeleton |
| Progress | ` | Progress bar |
| Timeline | ` | Activity timeline |

---
