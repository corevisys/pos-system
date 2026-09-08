@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-text-primary dark:text-dark-text']) }}>
    {{ $value ?? $slot }}
</label>
