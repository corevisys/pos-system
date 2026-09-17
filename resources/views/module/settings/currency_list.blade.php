<x-app-layout title="Currency List">
    <div x-data="{
        showAddModal: false, 
        showEditModal: false,
        showConfirmModal: false,
        currentActiveName: '{{ $activeCurrency ? addslashes($activeCurrency->currency_name) : '' }}',
        targetCurrencyId: null,
        targetCurrencyName: '',
        targetCurrencyCode: '',
        editId: null,
        editName: '',
        editCode: '',
        editSymbol: '',
        editStatus: 1,
        isCurrentlyActive: false,

        openActivateConfirm(id, name, code) {
            this.targetCurrencyId = id;
            this.targetCurrencyName = name;
            this.targetCurrencyCode = code;
            this.showConfirmModal = true;
        },

        closeActivateConfirm() {
            this.showConfirmModal = false;
            this.targetCurrencyId = null;
        }
    }"
        x-effect="document.body.classList.toggle('overflow-y-hidden', showAddModal || showEditModal || showConfirmModal)"
        @keydown.escape.window="showAddModal = false; showEditModal = false; showConfirmModal = false">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Currencies List <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">View/Search Currency</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Currencies List</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button @click="showAddModal = true" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    Add Currency
                </button>
            </div>
        </div>

        <!-- ACTIVE CURRENCY BANNER -->
        @if ($activeCurrency)
            <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/20 dark:border-emerald-500/10 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-lg">
                        {{ $activeCurrency->symbol }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Current System Currency</span>
                            <span class="px-2 py-0.5 bg-emerald-500 text-white rounded-full text-[8px] font-black uppercase tracking-wider">Active</span>
                        </div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">
                            {{ $activeCurrency->currency_name }} ({{ $activeCurrency->currency_code }}) — Symbol: <span class="font-black">{{ $activeCurrency->symbol }}</span>
                        </h2>
                    </div>
                </div>
                <div class="text-[10px] font-bold text-slate-400">
                    Only 1 currency is active system-wide. Activating another currency automatically deactivates this one.
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6">
            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-bold outline-none cursor-pointer">
                            <option>10</option>
                            <option>25</option>
                        </select>
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex bg-slate-50 dark:bg-slate-800 rounded-lg p-0.5 border border-slate-200 dark:border-dark-border">
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">PDF</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all text font-bold">Cols</button>
                        </div>
                        <div class="relative group">
                            <input type="text" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-7 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-32 focus:w-48 transition-all shadow-sm">
                            <svg class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center w-20">#</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Currency Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Currency Code</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Currency Symbol</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse ($currencies as $c)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group {{ $c->status ? 'bg-emerald-50/15 dark:bg-emerald-950/10' : '' }}">
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="text-[10px] font-bold text-slate-400">{{ $loop->iteration }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 tracking-tight">{{ $c->currency_name }}</span>
                                            @if ($c->status)
                                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Active Currency"></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-[10px] font-black text-primary-600 uppercase tracking-widest">{{ $c->currency_code }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-[14px] font-bold text-slate-600 dark:text-slate-400 transition-colors group-hover:text-primary-500">{{ $c->symbol }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if ($c->status)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 rounded-full text-[8px] font-black uppercase tracking-wider shadow-xs">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                Active
                                            </span>
                                        @else
                                            <button 
                                                @click="openActivateConfirm({{ $c->id }}, '{{ addslashes($c->currency_name) }}', '{{ addslashes($c->currency_code) }}')" 
                                                type="button" 
                                                class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 dark:bg-slate-800 dark:hover:bg-emerald-950/40 dark:text-slate-400 dark:hover:text-emerald-400 border border-slate-200 dark:border-slate-700 hover:border-emerald-300 rounded-full text-[8px] font-black uppercase tracking-wider transition-all cursor-pointer group/btn"
                                                title="Click to activate {{ $c->currency_name }}"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400 group-hover/btn:bg-emerald-500"></span>
                                                Set Active
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-2.5 py-1 bg-white border border-slate-200 dark:bg-dark-card dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1 hover:border-rose-500 hover:text-rose-600 transition-all shadow-sm text-slate-500">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-32 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    @if (!$c->status)
                                                        <button 
                                                            @click="openActivateConfirm({{ $c->id }}, '{{ addslashes($c->currency_name) }}', '{{ addslashes($c->currency_code) }}'); open = false;" 
                                                            class="w-full text-left flex items-center gap-1.5 px-3 py-2 text-[9px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors uppercase tracking-widest"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                            Activate
                                                        </button>
                                                    @endif
                                                    <button @click="editId = {{ $c->id }}; editName = '{{ addslashes($c->currency_name) }}'; editCode = '{{ addslashes($c->currency_code) }}'; editSymbol = '{{ addslashes($c->symbol) }}'; editStatus = {{ $c->status }}; isCurrentlyActive = {{ $c->status ? 'true' : 'false' }}; showEditModal = true; open = false;" class="w-full text-left block px-3 py-2 text-[9px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</button>
                                                    @if (!$c->status)
                                                        <form action="{{ route('settings.currency.delete', $c->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this currency?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full text-left block px-3 py-2 text-[9px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</button>
                                                        </form>
                                                    @else
                                                        <div class="px-3 py-1.5 text-[8px] font-bold text-slate-400 italic border-t border-slate-50 dark:border-dark-border">Active (Locked)</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">No Currencies Found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="p-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Showing {{ $currencies->count() }} entries</p>
                </div>
            </div>
        </div>

        <!-- ACTIVATE CONFIRMATION MODAL -->
        {{-- x-teleport is REQUIRED: the app shell has overflow-hidden/overflow-y-auto
             ancestors, so a fixed overlay left in place is clipped (backdrop shows,
             dialog invisible, page unusable). Teleporting to <body> fixes positioning
             while keeping the Alpine scope intact. --}}
        <template x-teleport="body">
        <div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop z-0 + panel relative z-10. The positioned, z-index:auto
                     blurred layer paints in CSS 2.1 Appendix E step 6 — above the
                     static inline-block panel (step 5) — which blurred the dialog
                     itself and stole every click inside it. --}}
                <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="closeActivateConfirm()" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="px-6 py-5 border-b border-slate-50 dark:border-dark-border flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.15em] text-slate-800 dark:text-white">Switch Active Currency</h3>
                            <p class="text-[10px] font-bold text-slate-400 mt-0.5">Confirmation required</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
                            This will deactivate <strong class="text-slate-900 dark:text-white font-bold" x-text="currentActiveName ? currentActiveName : 'the currently active currency'"></strong> and set <strong class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="targetCurrencyName"></strong> (<span x-text="targetCurrencyCode"></span>) as the active currency across the entire system.
                        </p>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            Continue?
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end items-center gap-3">
                        <button @click="showConfirmModal = false; targetCurrencyId = null;" type="button" class="btn-secondary">
                            Cancel
                        </button>
                        <form :action="'/settings/currency/' + targetCurrencyId + '/activate'" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="btn-primary !bg-success hover:!bg-success/90 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Confirm & Activate
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </template>

        <!-- ADD MODAL -->
        <template x-teleport="body">
        <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showAddModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="{{ route('settings.currency.store') }}" method="POST">
                        @csrf
                        <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Add Currency</h3>
                            <button @click="showAddModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="px-6 py-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Name</label>
                                <input type="text" name="currency_name" required placeholder="e.g. US Dollar" class="input-base">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Code</label>
                                    <input type="text" name="currency_code" required placeholder="e.g. USD" class="input-base">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Symbol</label>
                                    <input type="text" name="symbol" required placeholder="e.g. $" class="input-base">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                <select name="status" class="input-base">
                                    <option value="0">Inactive</option>
                                    <option value="1">Active (Sets as primary active currency)</option>
                                </select>
                                <p class="text-[9px] font-medium text-text-muted italic">Setting to Active will automatically deactivate any other active currency.</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                            <button @click="showAddModal = false" type="button" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Save Currency</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </template>

        <!-- EDIT MODAL -->
        <template x-teleport="body">
        <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showEditModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form :action="'/settings/currency/' + editId" method="POST">
                        @csrf
                        <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Edit Currency</h3>
                            <button @click="showEditModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="px-6 py-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Name</label>
                                <input type="text" name="currency_name" required x-model="editName" class="input-base">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Code</label>
                                    <input type="text" name="currency_code" required x-model="editCode" class="input-base">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Currency Symbol</label>
                                    <input type="text" name="symbol" required x-model="editSymbol" class="input-base">
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                <template x-if="isCurrentlyActive">
                                    <div>
                                        <input type="hidden" name="status" value="1">
                                        <div class="w-full px-4 py-2.5 bg-success/10 border border-success/20 rounded-xl text-xs font-bold text-success flex items-center justify-between">
                                            <span>Active (System Primary)</span>
                                            <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                        <p class="text-[9px] font-medium text-text-muted italic mt-1">To change active currency, activate another currency from the list.</p>
                                    </div>
                                </template>
                                <template x-if="!isCurrentlyActive">
                                    <div>
                                        <select name="status" x-model="editStatus" class="input-base">
                                            <option value="0">Inactive</option>
                                            <option value="1">Active (Sets as primary active currency)</option>
                                        </select>
                                        <p class="text-[9px] font-medium text-text-muted italic mt-1">Changing to Active will automatically deactivate the currently active currency.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                            <button @click="showEditModal = false" type="button" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Update Currency</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </template>
    </div>
</x-app-layout>
