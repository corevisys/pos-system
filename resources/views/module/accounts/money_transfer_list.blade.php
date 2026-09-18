<x-app-layout title="Money Transfer List">
    <div x-data="{
        selected: [],
        allOnPage: {{ json_encode($transfers->pluck('id')) }},
        selectAll: false,
        copySuccess: false,
        toggleAll() {
            if (this.selectAll) {
                this.selected = [...this.allOnPage];
            } else {
                this.selected = [];
            }
        },
        copyTable() {
            let rows = Array.from(document.querySelectorAll('table tbody tr')).filter(r => r.querySelectorAll('td').length > 1);
            if (rows.length === 0) return;
            let text = 'Transfer Code\tDate\tReference No\tDebit Acc\tCredit Acc\tAmount\tCreator\n';
            rows.forEach(r => {
                let cells = r.querySelectorAll('td');
                if (cells.length >= 8) {
                    let code = cells[1].innerText.trim();
                    let date = cells[2].innerText.trim();
                    let ref = cells[3].innerText.trim();
                    let debit = cells[4].innerText.trim();
                    let credit = cells[5].innerText.trim();
                    let amt = cells[6].innerText.trim();
                    let creator = cells[7].innerText.trim();
                    text += `${code}\t${date}\t${ref}\t${debit}\t${credit}\t${amt}\t${creator}\n`;
                }
            });
            navigator.clipboard.writeText(text).then(() => {
                this.copySuccess = true;
                setTimeout(() => this.copySuccess = false, 2000);
            });
        }
    }" class="grid grid-cols-1 gap-3">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-1">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Money Transfer</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Money Transfer List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">View/Search Transfers</p>
            </div>

            <a href="{{ route('accounts.transfer.add') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Transfer
            </a>
        </div>

        <!-- FILTERS -->
        <form action="{{ route('accounts.transfer') }}" method="GET" class="card p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Transfer Date -->
                <div class="group relative">
                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Transfer Date</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </span>
                        <input type="date" name="transfer_date" value="{{ request('transfer_date') }}" class="input-base !pl-10 !text-[10px] !font-bold">
                    </div>
                </div>

                <!-- Debit Account -->
                <div class="group relative">
                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Debit Account</label>
                    <x-searchable-select name="debit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="All Debit Accounts" emptyValue="" placeholder="All Debit Accounts" :value="request('debit_account_id')" />
                </div>

                <!-- Credit Account -->
                <div class="group relative">
                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Credit Account</label>
                    <x-searchable-select name="credit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="All Credit Accounts" emptyValue="" placeholder="All Credit Accounts" :value="request('credit_account_id')" />
                </div>

                <!-- Filter Button -->
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full !py-2 !text-[10px] uppercase tracking-widest">Filter</button>
                </div>
            </div>
        </form>

        <!-- FLOATING BULK ACTIONS BAR -->
        <div x-show="selected.length > 0" x-cloak class="p-3 bg-danger-light dark:bg-rose-950/30 border border-danger/20 dark:border-rose-800/40 rounded-card flex items-center justify-between transition-all">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-danger text-white text-[10px] font-black flex items-center justify-center" x-text="selected.length"></span>
                <span class="text-[11px] font-bold text-danger dark:text-rose-300">transfer(s) selected on this page</span>
            </div>
            <form action="{{ route('accounts.transfer.bulk-delete') }}" method="POST" onsubmit="return confirm('Reverse and delete all selected transfers? Destination accounts must have sufficient balances.');">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" class="btn-danger px-4 py-1.5 !text-[10px] uppercase tracking-widest flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Delete Selected
                </button>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="card overflow-hidden p-0">
            <!-- Table Controls -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <!-- Page Size Selector -->
                <div class="flex items-center gap-2">
                    <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Show</label>
                    <select onchange="const url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.set('page', '1'); window.location.href = url.toString();" class="input-base !w-auto !py-0.5 !px-1.5 !text-[10px] !rounded-lg !cursor-pointer">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <label class="text-[8px] font-black text-text-muted uppercase tracking-widest leading-none">Entries</label>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Export Actions -->
                    <div class="flex bg-background dark:bg-slate-800 rounded-xl p-1 border border-border dark:border-dark-border">
                        <button @click="copyTable()" type="button" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all" x-text="copySuccess ? 'Copied!' : 'Copy'">Copy</button>
                        <a href="{{ route('accounts.transfer', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                        <a href="{{ route('accounts.transfer', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                    </div>
                    <form method="GET" action="{{ route('accounts.transfer') }}" class="relative group">
                        @if(request('per_page'))
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                        @endif
                        @if(request('transfer_date'))
                            <input type="hidden" name="transfer_date" value="{{ request('transfer_date') }}">
                        @endif
                        @if(request('debit_account_id'))
                            <input type="hidden" name="debit_account_id" value="{{ request('debit_account_id') }}">
                        @endif
                        @if(request('credit_account_id'))
                            <input type="hidden" name="credit_account_id" value="{{ request('credit_account_id') }}">
                        @endif
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-3 py-3 w-10 text-center">
                                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="w-3 h-3 rounded border-border text-primary focus:ring-primary cursor-pointer">
                            </th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Transfer Code</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Reference No.</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Debit Acc</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Credit Acc</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount ({{ $currencySymbol }})</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Creator</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse($transfers as $tr)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox" :value="{{ $tr->id }}" x-model="selected" class="w-3 h-3 rounded border-border text-primary focus:ring-primary cursor-pointer">
                                </td>
                                <td class="px-4 py-2 text-[10px] font-black text-primary dark:text-primary tracking-wider font-mono leading-none">
                                    {{ $tr->transfer_code }}
                                </td>
                                <td class="px-4 py-2 text-[10px] font-black text-text-primary dark:text-dark-text leading-none">
                                    {{ \Carbon\Carbon::parse($tr->transfer_date)->format('d-m-Y') }}
                                </td>
                                <td class="px-4 py-2 text-[10px] font-bold text-text-muted italic">
                                    {{ $tr->reference_no ?? '---' }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-[10px] font-black text-danger leading-none">
                                        {{ $tr->debitAccount ? $tr->debitAccount->account_name : '---' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-[10px] font-black text-success leading-none">
                                        {{ $tr->creditAccount ? $tr->creditAccount->account_name : '---' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span class="text-[10px] font-black tabular-nums text-text-primary dark:text-white leading-none">
                                        <x-money value="{{ $tr->amount }}" />
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-0.5 bg-background dark:bg-slate-800 rounded font-black text-[8px] uppercase tracking-widest text-text-muted">
                                        {{ $tr->creator ? $tr->creator->name : 'System' }}
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
                                            <x-dropdown-link :href="route('accounts.transfer.edit', $tr->id)">Edit</x-dropdown-link>
                                            <form action="{{ route('accounts.transfer.delete', $tr->id) }}" method="POST" onsubmit="return confirm('Delete and reverse this transfer? Destination account must have sufficient balance.');">
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
                                <td colspan="9" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No transfers found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $transfers->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
