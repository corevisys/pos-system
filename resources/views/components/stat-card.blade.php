@props([
    'label' => null,
    'value' => null,
    'icon' => null,
    'iconBg' => 'bg-primary-light text-primary',
    'footer' => null,
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
                {{ $value ?? $slot }}
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
