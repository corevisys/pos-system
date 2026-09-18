@props([
    'label' => null,
    'value' => null,
    'icon' => null,
    'iconBg' => 'bg-primary-light text-primary',
    'footer' => null,
    // When true, $value is a RAW amount (PHP number OR raw Alpine expression)
    // rendered through the shared money component (compact K/L/Cr formatter)
    // with the .amount-tip hover/tap exact-value reveal. Default false
    // preserves the original verbatim rendering exactly for existing consumers.
    'money' => false,
])

<div {{ $attributes->merge(['class' => 'card card-hover p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            @if($label)
                <p class="text-xs font-medium text-text-secondary uppercase tracking-wider mb-1.5">
                    {{ $label }}
                </p>
            @endif
            <p class="text-2xl font-bold text-text-primary dark:text-dark-text">
                @if($money)
                    <x-money :value="$value" />
                @else
                    {{ $value ?? $slot }}
                @endif
            </p>
        </div>

        @if($icon)
            <div class="h-10 w-10 rounded-lg flex items-center justify-center shrink-0 {{ $iconBg }}">
                {!! $icon !!}
            </div>
        @endif
    </div>

    @if($footer)
        <div class="mt-4">
            {!! $footer !!}
        </div>
    @endif
</div>
