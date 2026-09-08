@props([
    'title' => null,
    'actions' => null,
])

<div {{ $attributes->merge(['class' => 'card overflow-hidden p-0']) }}>
    @if($title || $actions)
        <div class="px-5 py-4 border-b border-border dark:border-dark-border flex items-center justify-between gap-3">
            @if($title)
                <h3 class="text-base font-semibold text-text-primary dark:text-dark-text">{{ $title }}</h3>
            @endif
            @if($actions)
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            @if(!empty($thead))
                <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                    {{ $thead }}
                </thead>
            @endif
            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
