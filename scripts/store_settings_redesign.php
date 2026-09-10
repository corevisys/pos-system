<?php

/**
 * PHASE 5 — Store Settings page redesign to the design-system baseline.
 *
 * Converts raw-utility markup (bg-white dark:bg-dark-card rounded-3xl
 * border-slate-100, text-slate-*, bg-emerald-500/bg-amber-500 buttons) to the
 * established tokens: x-card, btn-primary/btn-secondary, input-base,
 * text-text-primary/secondary/muted, text-danger.
 *
 * Every form field name, Alpine binding, and test-asserted string is preserved
 * (isSubmitting guard, 'Save Settings' label, option selected values,
 * data:image/svg placeholder, 'Please fix the following errors' banner).
 */

$file = __DIR__ . '/../resources/views/module/settings/store.blade.php';

if (!is_file($file)) {
    fwrite(STDERR, "Missing view file: {$file}\n");
    exit(1);
}

$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "Cannot read view file.\n");
    exit(1);
}

$repl = [
    // Header / breadcrumbs
    ['text-slate-800 dark:text-white">Store Settings', 'text-text-primary dark:text-dark-text">Store Settings'],
    ['class="flex items-center gap-2 text-slate-400 font-medium mt-1"', 'class="flex items-center gap-2 text-text-muted font-medium mt-1"'],
    ['hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider', 'hover:text-primary transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider'],
    ['text-slate-600 text-[10px] font-black uppercase tracking-wider', 'text-text-secondary text-[10px] font-black uppercase tracking-wider'],

    // Tabs nav
    ['p-1 bg-slate-100/50 dark:bg-slate-800/50 rounded-2xl w-fit', 'p-1 bg-background/60 dark:bg-slate-800/50 rounded-2xl w-fit border border-border dark:border-dark-border'],
    ["'bg-white dark:bg-dark-card text-primary-600 shadow-sm'", "'bg-card dark:bg-dark-card text-primary shadow-sm'"],
    ["'text-slate-500 hover:text-slate-700'", "'text-text-secondary hover:text-text-primary'"],

    // Main container -> x-card
    ['<div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden p-5">', '<x-card class="overflow-hidden p-0">'],
    ['<form x-ref="storeForm" @submit="handleFormSubmit($event)" action="{{ route(\'settings.store.update\') }}" method="POST" enctype="multipart/form-data" class="space-y-6">', "<x-card class=\"overflow-hidden p-0\">\n            <div class=\"px-5 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex items-center gap-2\">\n                <div class=\"p-1.5 bg-primary/10 rounded-lg\">\n                    <svg class=\"w-4 h-4 text-primary\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2.5\" d=\"M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4\"></path></svg>\n                </div>\n                <h2 class=\"text-sm font-black text-text-primary dark:text-white uppercase tracking-widest\">Store Configuration</h2>\n            </div>\n\n            <form x-ref=\"storeForm\" @submit=\"handleFormSubmit($event)\" action=\"{{ route('settings.store.update') }}\" method=\"POST\" enctype=\"multipart/form-data\" class=\"space-y-6 p-5 md:p-6\">"],
    ["</form>\n        </div>", "</form>\n        </x-card>"],
];

// Apply the ordered replacements; each entry is [search, replace]
$changed = 0;
foreach ($repl as [$search, $replace]) {
    if (str_contains($src, $search)) {
        $src = str_replace($search, $replace, $src);
        $changed++;
    }
}

// Repeated token replacements (input / label / select / textarea classes, danger stars)
$tokenRepl = [
    // Standard text input
    'class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"',
    'class="input-base !py-3 !text-[11px] !font-bold"',

    // store_name variant (extra focus:bg-white)
    'class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 focus:bg-white dark:focus:bg-dark-card shadow-inner-sm"',
    'class="input-base !py-3 !text-[11px] !font-bold"',

    // readonly store_code
    'class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-400 outline-none cursor-not-allowed"',
    'class="input-base !py-3 !text-[11px] !font-bold !text-text-muted !cursor-not-allowed"',

    // textareas (emerald focus)
    'class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-emerald-500 shadow-inner-sm resize-none"',
    'class="input-base !py-3 !text-[11px] !font-bold resize-none"',

    // invoice_terms textarea
    'class="w-full bg-slate-50/20 dark:bg-slate-800/10 border border-slate-200 dark:border-dark-border rounded-2xl p-4 text-[10px] font-medium text-slate-500 leading-relaxed outline-none focus:border-emerald-500 shadow-inner-sm transition-all resize-none italic"',
    'class="input-base !py-3 !text-[11px] !font-bold resize-none italic"',

    // prefix text inputs (rounded-lg, font-black, py-1.5)
    'class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-lg py-1.5 px-3 text-[11px] font-black text-slate-700 outline-none transition-all focus:border-primary-500 shadow-inner-sm"',
    'class="input-base !py-2 !text-[11px] !font-black"',

    // plain selects
    'class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 outline-none appearance-none"',
    'class="input-base !py-3 !text-[11px] !font-bold appearance-none"',

    // field labels (base)
    'class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10"',
    'class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10"',

    // label suffix variants
    ' tracking-widest z-10 transition-colors group-focus-within:text-emerald-500"',
    ' tracking-widest z-10 transition-colors group-focus-within:text-success"',
    ' tracking-widest z-10 transition-colors group-focus-within:text-primary-500"',
    ' tracking-widest z-10 transition-colors group-focus-within:text-primary"',

    // required stars + toggle text tokens
    '<span class="text-rose-500">',
    '<span class="text-danger">',
    'text-[10px] font-black uppercase text-slate-400 tracking-widest',
    'text-[10px] font-black uppercase text-text-muted tracking-widest',
    'text-[9px] font-black uppercase text-slate-500 tracking-widest group-hover:text-primary-500 transition-colors',
    'text-[9px] font-black uppercase text-text-secondary tracking-widest group-hover:text-primary transition-colors',
    'text-[9px] font-black uppercase text-slate-300 tracking-widest group-hover:text-primary-500 transition-colors',
    'text-[9px] font-black uppercase text-text-muted tracking-widest group-hover:text-primary transition-colors',
    'text-[9px] font-medium text-slate-400 mt-1 pl-1',
    'text-[9px] font-medium text-text-muted mt-1 pl-1',
    'text-[9px] font-medium text-slate-400 dark:text-slate-500 mt-1 italic',
    'text-[9px] font-medium text-text-muted mt-1 italic',
    'text-primary-600 dark:text-primary-400 hover:underline font-bold',
    'text-primary hover:underline font-bold',

    // Save + Close buttons -> design-system
    'class="w-full md:w-48 py-2.5 bg-emerald-500 text-white rounded-xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-emerald-200 dark:shadow-none hover:bg-emerald-600 active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"',
    'class="btn-primary w-full md:w-48 !bg-emerald-500 hover:!bg-emerald-600 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none"',
    'class="w-full md:w-32 py-2.5 bg-amber-500 text-white rounded-xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-amber-200 dark:shadow-none hover:bg-amber-600 active:scale-[0.98] transition-all flex items-center justify-center gap-2"',
    'class="btn-secondary w-full md:w-32 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest"',
];

$tokenChanged = 0;
for ($i = 0; $i < count($tokenRepl); $i += 2) {
    $search = $tokenRepl[$i];
    $replace = $tokenRepl[$i + 1];
    if (str_contains($src, $search)) {
        $src = str_replace($search, $replace, $src);
        $tokenChanged++;
    }
}

$bytes = file_put_contents($file, $src);
if ($bytes === false) {
    fwrite(STDERR, "Cannot write view file.\n");
    exit(1);
}

echo "PHASE 5 REDESIGN APPLIED\n";
echo "Structured replacements matched: {$changed}\n";
echo "Token replacements matched: {$tokenChanged}\n";
echo "Bytes written: {$bytes}\n";
