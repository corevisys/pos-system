@php
    $shortcutsConfig = \App\Services\NavigationShortcutService::getShortcutsForUser(auth()->user());
@endphp

<div x-data="shortcutsModalHandler()"
     @toggle-shortcuts-modal.window="openModal($event.detail?.open ?? true)"
     @keydown.escape.window="closeModal()"
     class="relative z-50">

    {{-- MODAL BACKDROP & DIALOG --}}
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6"
         x-cloak>

        <div @click.away="closeModal()"
             x-show="isOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-4xl max-h-[85vh] bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-3xl shadow-2xl flex flex-col overflow-hidden">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b border-slate-100 dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800 flex items-center justify-center text-primary-600 dark:text-primary-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            Keyboard Navigation Shortcuts
                            <span class="text-[11px] font-extrabold px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300">91 Pages</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Press <kbd class="font-mono bg-slate-200 dark:bg-slate-700 px-1 py-0.5 rounded text-[10px] text-slate-700 dark:text-slate-300 font-bold">Ctrl + Alt + [Module]</kbd> then release and press <kbd class="font-mono bg-slate-200 dark:bg-slate-700 px-1 py-0.5 rounded text-[10px] text-slate-700 dark:text-slate-300 font-bold">[Page]</kbd></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="toggleAll()" class="px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 rounded-lg transition-colors" x-text="allExpanded ? 'Collapse All' : 'Expand All'"></button>
                    <button @click="closeModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- SEARCH & FILTER BAR --}}
            <div class="p-4 border-b border-slate-100 dark:border-dark-border bg-white dark:bg-dark-card">
                <div class="relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Search shortcuts by page name, module, or key (e.g. POS, Sale, Reports, Ctrl+Alt+S)..."
                           class="w-full pl-10 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-dark-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-slate-800 dark:text-white placeholder-slate-400">
                </div>
            </div>

            {{-- SHORTCUT LIST (GROUPED BY MODULE) --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                <template x-for="(module, modKey) in filteredModules" :key="modKey">
                    <div class="border border-slate-200 dark:border-dark-border rounded-2xl overflow-hidden bg-slate-50/40 dark:bg-slate-800/20">
                        {{-- Module Header Accordion --}}
                        <button @click="toggleModule(modKey)"
                                class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-100/60 dark:hover:bg-slate-800/60 transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center gap-1 font-mono text-xs font-bold px-2 py-0.5 rounded-lg bg-primary-600 text-white shadow-xs">
                                    <span>Ctrl+Alt+</span><span x-text="modKey"></span>
                                </span>
                                <span class="font-bold text-sm text-slate-800 dark:text-slate-200" x-text="module.module"></span>
                                <span class="text-[11px] font-semibold text-slate-400" x-text="'(' + module.items.length + ' pages)'"></span>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200"
                                 :class="isModuleExpanded(modKey) ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        {{-- Page Shortcuts Table / Grid --}}
                        <div x-show="isModuleExpanded(modKey)" x-collapse>
                            <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-2 border-t border-slate-200/70 dark:border-dark-border/70 bg-white dark:bg-dark-card">
                                <template x-for="item in module.items" :key="item.page_key + item.route">
                                    <div class="flex items-center justify-between p-2.5 rounded-xl border transition-all"
                                         :class="item.has_permission ? 'border-slate-100 dark:border-dark-border hover:border-primary-200 dark:hover:border-primary-900/50 bg-slate-50/50 dark:bg-slate-800/30' : 'border-rose-100 dark:border-rose-900/20 bg-rose-50/30 dark:bg-rose-950/10 opacity-75'">
                                        
                                        <div class="flex items-center gap-2 overflow-hidden mr-2">
                                            <template x-if="item.has_permission">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                            </template>
                                            <template x-if="!item.has_permission">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0" title="Permission required"></span>
                                            </template>
                                            
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate" x-text="item.label"></span>
                                        </div>

                                        <div class="flex items-center gap-1 shrink-0 font-mono text-[11px]">
                                            <kbd class="px-1.5 py-0.5 rounded bg-slate-200/90 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold border border-slate-300 dark:border-slate-600" x-text="'Ctrl+Alt+' + modKey"></kbd>
                                            <span class="text-slate-400 text-[10px]">then</span>
                                            <kbd class="px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 font-bold border border-primary-200 dark:border-primary-700" x-text="item.page_key"></kbd>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Empty Search Results State --}}
                <div x-show="Object.keys(filteredModules).length === 0" class="py-12 text-center">
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No keyboard shortcuts found matching "<span x-text="searchQuery"></span>"</p>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="px-6 py-3 border-t border-slate-100 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/40 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Available for your role
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span> Restricted by permissions
                    </span>
                </div>
                <div class="font-mono text-[11px]">
                    Press <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 font-bold">Esc</kbd> to close
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function shortcutsModalHandler() {
        return {
            isOpen: false,
            searchQuery: '',
            expandedModules: {},
            allExpanded: true,

            init() {
                // By default expand all modules
                if (window.APP_SHORTCUTS) {
                    Object.keys(window.APP_SHORTCUTS).forEach(k => {
                        this.expandedModules[k] = true;
                    });
                }
            },

            openModal(open = true) {
                this.isOpen = open;
                if (open) {
                    this.init();
                }
            },

            closeModal() {
                this.isOpen = false;
                this.searchQuery = '';
            },

            toggleModule(key) {
                this.expandedModules[key] = !this.expandedModules[key];
            },

            isModuleExpanded(key) {
                if (this.searchQuery.trim().length > 0) return true;
                return !!this.expandedModules[key];
            },

            toggleAll() {
                this.allExpanded = !this.allExpanded;
                if (window.APP_SHORTCUTS) {
                    Object.keys(window.APP_SHORTCUTS).forEach(k => {
                        this.expandedModules[k] = this.allExpanded;
                    });
                }
            },

            get filteredModules() {
                if (!window.APP_SHORTCUTS) return {};
                const q = this.searchQuery.trim().toLowerCase();
                if (!q) return window.APP_SHORTCUTS;

                const result = {};
                Object.entries(window.APP_SHORTCUTS).forEach(([modKey, mod]) => {
                    const modMatches = mod.module.toLowerCase().includes(q) || ('ctrl+alt+' + modKey.toLowerCase()).includes(q);
                    const matchingItems = mod.items.filter(item => {
                        return modMatches ||
                               item.label.toLowerCase().includes(q) ||
                               item.page_key.toLowerCase() === q ||
                               item.sequence.toLowerCase().includes(q);
                    });

                    if (matchingItems.length > 0) {
                        result[modKey] = {
                            ...mod,
                            items: matchingItems
                        };
                    }
                });
                return result;
            }
        };
    }
</script>
