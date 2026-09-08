@props([
    'color' => 'primary', // primary | success | warning | danger | neutral
])

@php
    $badgeClasses = [
        'primary' => 'bg-primary-50 text-primary-700 border-primary-100 dark:bg-primary/15 dark:text-primary-300 dark:border-primary/30',
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
        'danger'  => 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/30',
        'neutral' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    ];
    $classes = $badgeClasses[$color] ?? $badgeClasses['primary'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium border ' . $classes]) }}>
    {{ $slot }}
</span>
