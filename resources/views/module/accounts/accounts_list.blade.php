<x-app-layout title="Accounts List">
    <div x-data="{
        selected: [],
        allOnPage: {{ json_encode($accounts->pluck('id')) }},
        isSubmittingBulk: false,
        copied: false,
        toggleAll() {
            if (this.selected.length === this.allOnPage.length) {
                this.selected = [];
            } else {
                this.selected = [...this.allOnPage];
            }
        },
        copyTable() {
            let text = 'Account Code\tAccount Name\tParent Account\tBalance\tCreated By\n';
            document.querySelectorAll('tbody tr.data-row').forEach(row => {
                let cells = row.querySelectorAll('td');
                if (cells.length >= 6) {
                    let code = cells[1]?.innerText.trim() || '';
                    let name = cells[2]?.innerText.trim() || '';
                    let parent = cells[3]?.innerText.trim() || '';
                    let bal = cells[4]?.innerText.trim() || '';
                    let user = cells[5]?.innerText.trim() || '';
                    text += `${code}\t${name}\t${parent}\t${bal}\t${user}\n`;
                }
            });
            navigator.clipboard.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Accounts</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Accounts List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">View/Search Accounts</p>
            </div>

            <a href="{{ route('accounts.add') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Add Account
            </a>
        </div>

        <div class="grid grid-cols-1 gap-3">
            <!-- TABLE SECTION -->
            <div class="card overflow-hidden p-0">
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Show</label>
                        <select onchange="const url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.delete('page'); window.location.href = url.toString();" class="input-base !w-auto !py-0.5 !px-1.5 !text-[10px] !rounded-lg !cursor-pointer">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex bg-background dark:bg-slate-800 rounded-xl p-1 border border-border dark:border-dark-border">
                            <button type="button" @click="copyTable()" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center gap-1">
                                <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                            </button>
                            <a href="{{ route('accounts.list', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                            <a href="{{ route('accounts.list', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                        </div>
                        <form method="GET" action="{{ route('accounts.list') }}" class="relative group">
                            @if(request('per_page'))
                                <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                            @endif
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search accounts..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </form>
                    </div>
                </div>

                <!-- Bulk Selection Floating Bar -->
                <div x-show="selected.length > 0" x-cloak class="px-4 py-2 bg-danger-light dark:bg-rose-950/30 border-b border-danger/20 dark:border-rose-900/50 flex flex-wrap justify-between items-center gap-2 transition-all">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-danger animate-pulse"></span>
                        <span class="text-[10px] font-black uppercase tracking-wider text-danger dark:text-rose-400" x-text="selected.length + ' account(s) selected on this page'"></span>
                    </div>
                    <form method="POST" action="{{ route('accounts.bulk-delete') }}" @submit="if(isSubmittingBulk) { $event.preventDefault(); return false; } if(!confirm('Are you sure you want to delete the selected ' + selected.length + ' account(s)?')) { $event.preventDefault(); return false; } isSubmittingBulk = true;">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <button type="submit" :disabled="isSubmittingBulk" class="btn-danger px-3 py-1 !text-[9px] flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            <span x-text="isSubmittingBulk ? 'Deleting...' : 'Delete Selected'">Delete Selected</span>
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                            <tr>
                                <th class="px-3 py-3 w-10 text-center">
                                    <input type="checkbox" @change="toggleAll()" :checked="selected.length === allOnPage.length && allOnPage.length > 0" class="w-3 h-3 rounded border-border text-primary focus:ring-primary cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Account Number</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Account Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Parent Account</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Balance ({{ $currencySymbol }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Created By</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-dark-border">
                            @forelse($accounts as $account)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group data-row">
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" :value="{{ $account->id }}" x-model="selected" class="w-3 h-3 rounded border-border text-primary focus:ring-primary cursor-pointer">
                                    </td>
                                    <td class="px-4 py-2 text-[10px] font-black text-text-secondary dark:text-text-muted tracking-wider font-mono uppercase italic">
                                        {{ $account->account_code }}
                                    </td>
                                    <td class="px-4 py-2 text-[10px] font-black text-text-primary dark:text-dark-text">
                                        {{ $account->account_name }}
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-0.5 bg-background dark:bg-slate-800 border border-border dark:border-dark-border rounded text-[8px] font-black uppercase tracking-widest text-text-muted">
                                            {{ $account->parent ? $account->parent->account_name : '---' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <span class="text-[10px] font-black tabular-nums text-text-primary dark:text-white leading-none">
                                            {{ format_currency($account->balance) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-0.5 bg-background dark:bg-slate-800 rounded font-black text-[8px] uppercase tracking-widest text-text-muted">
                                            {{ $account->creator ? $account->creator->name : 'System' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <x-dropdown align="right" width="40">
                                            <x-slot name="trigger">
                                                <button type="button" class="btn-primary px-3 py-1 !text-[9px] uppercase tracking-widest flex items-center gap-1.5 leading-none">
                                                    Action
                                                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>
                                            </x-slot>
                                            <x-slot name="content">
                                                <x-dropdown-link :href="route('accounts.edit', $account->id)">Edit</x-dropdown-link>
                                                <form action="{{ route('accounts.delete', $account->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors uppercase tracking-widest">Delete</button>
                                                </form>
                                            </x-slot>
                                        </x-dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-text-muted text-xs font-bold leading-none">No accounts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                    <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                        Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }} entries
                    </p>
                    <div class="flex gap-1 leading-none">
                        @if ($accounts->onFirstPage())
                            <button class="px-2 py-1 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest text-text-muted cursor-not-allowed transition-all">Prev</button>
                        @else
                            <a href="{{ $accounts->previousPageUrl() }}" class="px-2 py-1 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest text-text-secondary hover:text-primary transition-all">Prev</a>
                        @endif

                        @foreach ($accounts->getUrlRange(1, $accounts->lastPage()) as $page => $url)
                            @if ($page == $accounts->currentPage())
                                <button class="px-2.5 py-1 bg-primary text-white rounded-lg text-[9px] font-black shadow-md shadow-primary/20 transition-all">{{ $page }}</button>
                            @else
                                <a href="{{ $url }}" class="px-2 py-1 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest text-text-secondary hover:text-primary transition-all">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($accounts->hasMorePages())
                            <a href="{{ $accounts->nextPageUrl() }}" class="px-2 py-1 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest text-text-secondary hover:text-primary transition-all">Next</a>
                        @else
                            <button class="px-2 py-1 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest text-text-muted cursor-not-allowed transition-all">Next</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
