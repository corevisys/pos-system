<x-app-layout title="SMS Blacklist">
    <div x-data="{ showAddModal: false }"
        x-effect="document.body.classList.toggle('overflow-y-hidden', showAddModal)"
        @keydown.escape.window="showAddModal = false">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">SMS Blacklist <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Numbers opted out for this store</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">SMS Blacklist</span>
                </div>
            </div>

            <button @click="showAddModal = true" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                Blacklist a Number
            </button>
        </div>

        <!-- Session flash is surfaced automatically by the global notification-toast
             component included once in layouts/app.blade.php. -->

        <!-- TABLE SECTION -->
        <x-card padding="p-0" class="overflow-hidden">
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <form action="{{ route('sms.blacklist') }}" method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Show</label>
                        <select name="limit" onchange="this.form.submit()" class="bg-white dark:bg-dark-card border border-border dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-bold outline-none cursor-pointer">
                            @foreach ([10, 25, 50, 100] as $opt)
                                <option value="{{ $opt }}" {{ request('limit', 10) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="relative group">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search phone..." class="input-base !w-44 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button type="submit" class="btn-primary !py-1.5 !px-3 !text-[10px] font-black uppercase tracking-widest">Search</button>
                    @if(request('search'))
                        <a href="{{ route('sms.blacklist') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2.5 w-12 text-center text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Phone</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Reason</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse ($blacklists as $index => $b)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-2 text-center text-[10px] font-bold text-text-muted tabular-nums">
                                    {{ $blacklists->firstItem() + $index }}
                                </td>
                                <td class="px-4 py-2 text-[11px] font-black text-text-primary dark:text-dark-text tabular-nums">
                                    {{ $b->phone }}
                                </td>
                                <td class="px-4 py-2 text-[10px] text-text-secondary">
                                    {{ $b->reason ?: '-NA-' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <form action="{{ route('sms.blacklist.delete', $b->id) }}" method="POST" onsubmit="return confirm('Remove this number from the blacklist?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-danger hover:text-danger-hover text-[9px] font-black uppercase tracking-widest">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No Blacklisted Numbers Found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $blacklists->firstItem() ?? 0 }} to {{ $blacklists->lastItem() ?? 0 }} of {{ $blacklists->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $blacklists->links() }}
                </div>
            </div>
        </x-card>

        <!-- ADD MODAL -->
        {{-- x-teleport is REQUIRED: the app shell has overflow-hidden/overflow-y-auto
             ancestors, so a fixed overlay left in place is clipped (backdrop shows,
             dialog invisible, page unusable). Teleporting to <body> fixes positioning
             while keeping the Alpine scope intact. --}}
        <template x-teleport="body">
        <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop z-0 + panel relative z-10. The positioned, z-index:auto
                     blurred layer paints in CSS 2.1 Appendix E step 6 — above the
                     static inline-block panel (step 5) — which blurred the dialog
                     itself and stole every click inside it. --}}
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showAddModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="{{ route('sms.blacklist.store') }}" method="POST">
                        @csrf
                        <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Blacklist a Number</h3>
                            <button @click="showAddModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="px-6 py-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone" required placeholder="e.g. 01711000001" class="input-base">
                                @error('phone')
                                    <p class="text-[10px] text-danger font-bold">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Reason</label>
                                <input type="text" name="reason" placeholder="e.g. Customer requested opt-out" class="input-base">
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                            <button @click="showAddModal = false" type="button" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Add to Blacklist</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </template>
    </div>
</x-app-layout>
