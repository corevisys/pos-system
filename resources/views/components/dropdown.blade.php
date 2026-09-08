@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'origin-top-left',
    'top' => 'origin-top',
    default => 'origin-top-right',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};

// True when the panel should be anchored to the LEFT edge of the trigger.
// Injected as a JS boolean so the x-data body never needs a double-quote.
$isAlignLeft = $align === 'left';
@endphp

<div
    x-data="{
        open: false,
        triggerRect: null,
        panelVisible: false,

        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.open = true;
                this.panelVisible = false;
            }
        },

        captureTrigger() {
            const trigger = this.$el.querySelector('[data-dropdown-trigger]');
            if (trigger) {
                this.triggerRect = trigger.getBoundingClientRect();
            }
        },

        close() {
            this.open = false;
            this.panelVisible = false;
        },

        openPanel() {
            if (!this.open) return;

            this.captureTrigger();

            // Stash the bound handler so we can remove it on close.
            if (!this._boundReposition) {
                this._boundReposition = () => {
                    if (this.open) this.captureTrigger();
                };
                window.addEventListener('scroll', this._boundReposition, true);
                window.addEventListener('resize', this._boundReposition);
            }

            // If the trigger ever leaves the viewport (scrolled out), close.
            this.$nextTick(() => {
                const r = this.triggerRect;
                if (r && (r.bottom < 0 || r.top > window.innerHeight || r.right < 0 || r.left > window.innerWidth)) {
                    this.close();
                } else {
                    this.panelVisible = true;
                }
            });
        },

        closePanel() {
            if (this._boundReposition) {
                window.removeEventListener('scroll', this._boundReposition, true);
                window.removeEventListener('resize', this._boundReposition);
                this._boundReposition = null;
            }
        },

        get panelStyle() {
            // No rect yet (e.g. during the open tick): park the panel off-screen
            // so it never flashes at the viewport origin.
            if (!this.triggerRect) {
                return 'position: fixed; top: -9999px; left: -9999px; z-index: 60; visibility: hidden;';
            }

            const r = this.triggerRect;
            const gap = 8; // ~mt-2
            const style = {
                position: 'fixed',
                top: (r.bottom + gap) + 'px',
                zIndex: 60,
                visibility: this.panelVisible ? 'visible' : 'hidden',
            };

            if ({{ $isAlignLeft ? 'true' : 'false' }}) {
                style.left = r.left + 'px';
            } else {
                // align right (default): right-align panel to the trigger's right edge.
                style.right = (window.innerWidth - r.right) + 'px';
            }

            return Object.entries(style).map(([k, v]) => `${k}: ${v}`).join('; ');
        },

        init() {
            this.$watch('open', (val) => {
                if (val) {
                    this.$nextTick(() => {
                        this.captureTrigger();
                        this.openPanel();
                    });
                } else {
                    this.closePanel();
                }
            });
        },
    }"
    @click.outside="close()"
    @close.stop="close()"
    class="relative"
>
    <div class="inline-flex" data-dropdown-trigger @click="toggle()">
        {{ $trigger }}
    </div>

    {{-- Portal the panel to document.body so no ancestor stacking context
         (transform/overflow/filter on the table card or rows) can clip it or
         trap its z-index below the Action buttons. Alpine x-teleport moves
         this node to <body> at init while preserving its data scope, so the
         per-row @can/@click handlers and Blade-bound hrefs keep working. --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            :style="panelStyle"
            @click="open = false"
            class="fixed rounded-lg shadow-dropdown border border-border dark:border-dark-border bg-card dark:bg-dark-card {{ $width }} {{ $alignmentClasses }}"
        >
            <div class="rounded-lg py-1.5 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    </template>
</div>
