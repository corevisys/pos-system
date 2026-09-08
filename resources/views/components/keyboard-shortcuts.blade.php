@php
    $shortcutsConfig = \App\Services\NavigationShortcutService::getShortcutsForUser(auth()->user());
@endphp

<div x-data="keyboardShortcutsHandler()" 
     x-init="initShortcuts()"
     @open-shortcuts-help.window="openHelpModal()"
     class="relative z-50">

    {{-- Sequence Mode Live Indicator Pill --}}
    <div x-show="inSequence"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 bg-slate-900/95 dark:bg-slate-800/95 text-white rounded-2xl shadow-2xl border border-slate-700/80 backdrop-blur-md"
         x-cloak>
        <div class="w-2.5 h-2.5 rounded-full bg-primary-400 animate-ping"></div>
        <div class="flex flex-col">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-300">Shortcut Sequence:</span>
                <span class="text-xs font-black text-primary-400 tracking-wide" x-text="activeModuleName"></span>
                <span class="text-slate-400">&gt;</span>
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[11px] font-mono font-bold bg-primary-500/20 text-primary-300 rounded border border-primary-400/30 animate-pulse">_</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-0.5">Press second key or <kbd class="font-mono bg-slate-800 px-1 py-0.5 rounded text-[9px] text-slate-300">Esc</kbd> to cancel</p>
        </div>
    </div>
</div>

<script>
    window.APP_SHORTCUTS = @json($shortcutsConfig);

    function keyboardShortcutsHandler() {
        return {
            inSequence: false,
            pendingModuleKey: null,
            activeModuleName: '',
            sequenceTimer: null,
            timeoutDuration: 1800, // ~1.8 seconds timeout

            initShortcuts() {
                window.addEventListener('keydown', (e) => this.handleKeyDown(e));
            },

            isEditableTarget(target) {
                if (!target) return false;
                const tagName = target.tagName ? target.tagName.toUpperCase() : '';
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)) return true;
                if (target.isContentEditable) return true;
                if (target.closest && target.closest('[data-global-search-modal]')) {
                    return true;
                }
                return false;
            },

            openHelpModal() {
                window.dispatchEvent(new CustomEvent('toggle-shortcuts-modal', { detail: { open: true } }));
            },

            handleKeyDown(e) {
                // Disabled on mobile and tablet screens (< 1024px)
                if (window.innerWidth < 1024) return;

                // Never intercept if focused on input, textarea, select, or search box
                if (this.isEditableTarget(e.target)) return;

                const key = e.key ? e.key.toUpperCase() : '';

                // If in sequence mode
                if (this.inSequence) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        this.cancelSequence();
                        return;
                    }

                    // Ignore standalone modifier presses while waiting for the 2nd key
                    if (['CONTROL', 'ALT', 'SHIFT', 'META'].includes(key)) {
                        return;
                    }

                    e.preventDefault();
                    this.executeSecondKey(key);
                    return;
                }

                // Check for Help Modal shortcut: Ctrl+Alt+? or Shift+?
                if (e.ctrlKey && e.altKey && (e.key === '?' || e.key === '/')) {
                    e.preventDefault();
                    this.openHelpModal();
                    return;
                }

                // Trigger First Key: Ctrl + Alt + [Module Key]
                if (e.ctrlKey && e.altKey && !e.shiftKey && !e.metaKey) {
                    if (window.APP_SHORTCUTS && window.APP_SHORTCUTS[key]) {
                        e.preventDefault();
                        this.startSequence(key);
                    }
                }
            },

            startSequence(moduleKey) {
                this.inSequence = true;
                this.pendingModuleKey = moduleKey;
                this.activeModuleName = window.APP_SHORTCUTS[moduleKey]?.module || moduleKey;

                if (this.sequenceTimer) clearTimeout(this.sequenceTimer);
                this.sequenceTimer = setTimeout(() => {
                    this.cancelSequence();
                }, this.timeoutDuration);
            },

            cancelSequence() {
                this.inSequence = false;
                this.pendingModuleKey = null;
                this.activeModuleName = '';
                if (this.sequenceTimer) {
                    clearTimeout(this.sequenceTimer);
                    this.sequenceTimer = null;
                }
            },

            executeSecondKey(pageKey) {
                const moduleData = window.APP_SHORTCUTS[this.pendingModuleKey];
                this.cancelSequence();

                if (!moduleData || !moduleData.items) return;

                const targetItem = moduleData.items.find(item => item.page_key === pageKey);

                if (!targetItem) {
                    // Second key not found in this module
                    return;
                }

                // Strict permission enforcement
                if (!targetItem.has_permission) {
                    if (typeof window.showError === 'function') {
                        window.showError("You don't have permission to access this page");
                    } else {
                        alert("You don't have permission to access this page");
                    }
                    return;
                }

                // Navigate to target route
                if (targetItem.url && targetItem.url !== '#') {
                    window.location.href = targetItem.url;
                }
            }
        };
    }
</script>
