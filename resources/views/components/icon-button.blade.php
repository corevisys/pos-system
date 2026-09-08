@props([
    'variant' => 'default', // default | primary | danger | ghost
    'size' => 'md',         // sm | md | lg
    'label' => null,
])

@php
    $variants = [
        'default' => 'text-text-secondary hover:text-text-primary hover:bg-slate-100 dark:hover:bg-slate-800',
        'primary' => 'text-primary hover:bg-primary-light dark:hover:bg-primary/20',
        'danger'  => 'text-danger hover:bg-danger-light dark:hover:bg-danger/20',
        'ghost'   => 'text-text-muted hover:text-text-primary',
    ];
    $sizes = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-9 w-9 text-sm',
        'lg' => 'h-10 w-10 text-base',
    ];
@endphp

<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-button transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 ' . $variants[$variant] . ' ' . $sizes[$size]]) }}
    @if($label) title="{{ $label }}" aria-label="{{ $label }}" @endif
>
    {{ $slot }}
</button>
