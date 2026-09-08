{{--
 ╔══════════════════════════════════════════════════════════════╗
 ║       GLOBAL NOTIFICATION TOAST COMPONENT                    ║
 ║  Included ONCE in layouts/app.blade.php.                     ║
 ║  Exposes: window.showSuccess(msg), window.showError(msg|arr) ║
 ║  Also auto-reads Laravel session flash on page-load.         ║
 ╚══════════════════════════════════════════════════════════════╝
--}}

<div
    x-data
    class="fixed top-4 right-4 z-[9999] flex flex-col gap-2.5 w-[360px] max-w-[calc(100vw-2rem)] pointer-events-none"
    aria-live="polite"
    aria-label="Notifications"
>
    <template x-for="toast in $store.notify.toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8 scale-95"
            x-transition:enter-end="opacity-100 translate-x-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0 scale-100"
            x-transition:leave-end="opacity-0 translate-x-8 scale-95"
            class="pointer-events-auto relative overflow-hidden rounded-2xl shadow-2xl border"
            :class="{
                'bg-white dark:bg-dark-card border-emerald-200 dark:border-emerald-500/30 shadow-emerald-100 dark:shadow-none': toast.type === 'success',
                'bg-white dark:bg-dark-card border-rose-200 dark:border-rose-500/30 shadow-rose-100 dark:shadow-none': toast.type === 'error'
            }"
            role="alert"
        >
            {{-- Accent bar on the left --}}
            <div
                class="absolute inset-y-0 left-0 w-1 rounded-l-2xl"
                :class="{
                    'bg-emerald-500': toast.type === 'success',
                    'bg-rose-500': toast.type === 'error'
                }"
            ></div>

            {{-- Progress bar for auto-dismiss (success only) --}}
            <div
                x-show="toast.type === 'success' && toast.duration > 0"
                class="absolute bottom-0 left-0 h-0.5 bg-emerald-500/30 rounded-b-2xl"
                style="right: 0"
            >
                <div
                    class="h-full bg-emerald-500 rounded-b-2xl transition-all ease-linear"
                    :style="`width: ${toast.progress}%; transition-duration: ${toast.duration}ms`"
                    x-init="$nextTick(() => { toast.progress = 0 })"
                ></div>
            </div>

            <div class="flex items-start gap-3 px-4 py-3.5 pl-5">
                {{-- Icon --}}
                <div
                    class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center mt-0.5"
                    :class="{
                        'bg-emerald-100 dark:bg-emerald-500/20': toast.type === 'success',
                        'bg-rose-100 dark:bg-rose-500/20': toast.type === 'error'
                    }"
                >
                    {{-- Success checkmark --}}
                    <template x-if="toast.type === 'success'">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </template>
                    {{-- Error warning --}}
                    <template x-if="toast.type === 'error'">
                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                        </svg>
                    </template>
                </div>

                {{-- Message area --}}
                <div class="flex-1 min-w-0 pt-0.5">
                    {{-- Title --}}
                    <p
                        class="text-[11px] font-black uppercase tracking-widest mb-0.5"
                        :class="{
                            'text-emerald-700 dark:text-emerald-400': toast.type === 'success',
                            'text-rose-700 dark:text-rose-400': toast.type === 'error'
                        }"
                        x-text="toast.type === 'success' ? 'Success' : 'Error'"
                    ></p>

                    {{-- Single message --}}
                    <template x-if="!Array.isArray(toast.messages)">
                        <p class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 leading-snug" x-text="toast.messages"></p>
                    </template>

                    {{-- Multiple messages (validation errors list) --}}
                    <template x-if="Array.isArray(toast.messages)">
                        <ul class="space-y-1 mt-0.5">
                            <template x-for="(msg, i) in toast.messages" :key="i">
                                <li class="flex items-start gap-1.5 text-[13px] font-semibold text-slate-700 dark:text-slate-200 leading-snug">
                                    <span class="w-1 h-1 rounded-full bg-rose-400 shrink-0 mt-1.5"></span>
                                    <span x-text="msg"></span>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- Dismiss button --}}
                <button
                    @click="$store.notify.dismiss(toast.id)"
                    class="shrink-0 mt-0.5 p-1 rounded-lg transition-all duration-150"
                    :class="{
                        'text-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-500/10': toast.type === 'success',
                        'text-rose-400 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-500/10': toast.type === 'error'
                    }"
                    :aria-label="'Dismiss notification'"
                    title="Dismiss"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- Alpine Store + Global JS API                               --}}
{{-- Registered before Alpine boots via document.addEventListener --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('notify', {
            toasts: [],
            _idCounter: 0,

            /**
             * Show a success toast.
             * @param {string} message
             * @param {number} duration  Auto-dismiss ms. Default 4000. 0 = never.
             */
            showSuccess(message, duration = 4000) {
                this._add('success', message, duration);
            },

            /**
             * Show an error toast.
             * @param {string|string[]} message  Single string or array of validation errors.
             * @param {number}          duration  Default 0 = stays until dismissed.
             */
            showError(message, duration = 0) {
                // Normalize: if Laravel validation errors object {field: [msgs]} convert to flat array
                if (message && typeof message === 'object' && !Array.isArray(message)) {
                    message = Object.values(message).flat();
                }
                this._add('error', message, duration);
            },

            /**
             * @private
             */
            _add(type, messages, duration) {
                const id = ++this._idCounter;
                const toast = { id, type, messages, duration, visible: true, progress: 100 };
                this.toasts.push(toast);

                if (duration > 0) {
                    // After a short delay, start the progress bar shrink
                    setTimeout(() => {
                        const t = this.toasts.find(t => t.id === id);
                        if (t) t.progress = 0;
                    }, 50);

                    // Auto-dismiss
                    setTimeout(() => this.dismiss(id), duration);
                }

                // Keep max 5 toasts visible at once — remove oldest
                if (this.toasts.length > 5) {
                    this.dismiss(this.toasts[0].id);
                }
            },

            dismiss(id) {
                const toast = this.toasts.find(t => t.id === id);
                if (toast) {
                    toast.visible = false;
                    // Remove from array after leave animation completes
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 300);
                }
            }
        });

        queueMicrotask(() => {
            @if(session('success'))
                Alpine.store('notify').showSuccess(@js(session('success')));
            @endif
            @if(session('error'))
                Alpine.store('notify').showError(@js(session('error')));
            @endif
            @if(isset($errors) && $errors->any())
                Alpine.store('notify').showError(@js($errors->all()));
            @endif
        });
    });

    /**
     * ── Global convenience aliases ─────────────────────────────────
     * Callable from ANY script tag, AJAX handler, or Alpine component:
     *
     *   showSuccess('Item saved successfully.');
     *   showError('The brand name has already been taken.');
     *   showError(['Name required.', 'Price must be numeric.']);
     */
    window.showSuccess = (msg, duration = 4000) => {
        if (typeof Alpine !== 'undefined' && Alpine.store) {
            Alpine.store('notify').showSuccess(msg, duration);
        } else {
            document.addEventListener('alpine:init', () => {
                Alpine.store('notify').showSuccess(msg, duration);
            }, { once: true });
        }
    };

    window.showError = (msg, duration = 0) => {
        if (typeof Alpine !== 'undefined' && Alpine.store) {
            Alpine.store('notify').showError(msg, duration);
        } else {
            document.addEventListener('alpine:init', () => {
                Alpine.store('notify').showError(msg, duration);
            }, { once: true });
        }
    };
</script>
