@props([
    'padding' => 'p-5',
    'hover' => false,
])

<div {{ $attributes->merge(['class' => 'card ' . ($hover ? 'card-hover ' : '') . $padding]) }}>
    {{ $slot }}
</div>
