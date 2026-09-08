@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'input-base']) }}>
    {{ $slot }}
</select>
