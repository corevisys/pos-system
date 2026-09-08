<x-app-layout title="Cash Transactions">
    <div x-data="{
        copySuccess: false,
        copyTable() {
            let rows = Array.from(document.querySelectorAll('table tbody tr')).filter(r => r.querySelectorAll('td').length > 1);
            if (rows.length === 0) return;
            let text = 'Date\tType/Method\tAccount\tNote\tDebit\tCredit\tUser\n';
            rows.forEach(r => {
                let cells = r.querySelectorAll('td');
                if (cells.length >= 7) {
                    let date = cells[0].innerText.trim();
                    let type = cells[1].innerText.trim();
                    let acc = cells[2].innerText.trim();
                    let note = cells[3].innerText.trim();
                    let debit = cells[4].innerText.trim();
                    let credit = cells[5].innerText.trim();
                    let user = cells[6].innerText.trim();
                    text += `${date}\t${type}\t${acc}\t${note}\t${debit}\t${credit}\t${user}\n`;
                }
            });
            navigator.clipboard.writeText(text).then(() => {
                this.copySuccess = true;
                setTimeout(() => this.copySuccess = false, 2000);
            });
        }
    }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Cash Transactions</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Cash Transactions</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1 italic">Financial History & Journal</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3">
            <!-- FILTERS -->
            <form action="{{ route('accounts.transactions') }}" method="GET" class="card p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Transaction Date -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Transaction Date</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </span>
                            <input type="date" name="transaction_date" value="{{ request('transaction_date') }}" class="input-base !pl-10 !text-[10px] !font-bold">
                        </div>
                    </div>

                    <!-- Filter by Account -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Account</label>
                        <x-searchable-select name="account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="All Accounts" emptyValue="" placeholder="All Accounts" :value="request('account_id')" />
                    </div>

                    <!-- Dynamic Transaction Type Filter -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Type</label>
                        <div class="relative">
                            <select name="transaction_type" class="input-base !py-2 !pr-8 !text-[10px] !font-bold appearance-none cursor-pointer leading-tight">
                                <option value="">All Types</option>
                                @foreach($transactionTypes as $type)
                                    <option value="{{ $type }}" {{ request('transaction_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Users -->
                    <div class="group relative flex gap-2 items-end">
                        <div class="flex-1 relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Users</label>
                            <x-searchable-select name="created_by" :options="$users" labelKey="name" valueKey="id" emptyOption="All Users" emptyValue="" placeholder="All Users" :value="request('created_by')" />
                        </div>
                        <button type="submit" class="btn-primary h-[34px] !py-2 !px-4 !text-[10px] uppercase tracking-widest">Filter</button>
                    </div>
                </div>
            </form>

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
                            <a href="{{ route('accounts.transactions', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                            <a href="{{ route('accounts.transactions', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                        </div>
                        <form method="GET" action="{{ route('accounts.transactions') }}" class="relative group">
                            @if(request('per_page'))
                                <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                            @endif
                            @if(request('transaction_date'))
                                <input type="hidden" name="transaction_date" value="{{ request('transaction_date') }}">
                            @endif
                            @if(request('account_id'))
                                <input type="hidden" name="account_id" value="{{ request('account_id') }}">
                            @endif
                            @if(request('transaction_type'))
                                <input type="hidden" name="transaction_type" value="{{ request('transaction_type') }}">
                            @endif
                            @if(request('created_by'))
                                <input type="hidden" name="created_by" value="{{ request('created_by') }}">
                            @endif
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left font-sans">
                        <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Type / Method</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Account</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Note</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Debit ({{ $currencySymbol ?? '' }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Credit ({{ $currencySymbol ?? '' }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-dark-border">
                            @forelse($transactions as $tr)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-4 py-2 text-[10px] font-black text-text-primary dark:text-dark-text leading-none">
                                        {{ \Carbon\Carbon::parse($tr->transaction_date)->format('d-m-Y') }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-primary">{{ $tr->transaction_type }}</span>
                                            <span class="text-[8px] font-bold text-text-muted">{{ $tr->payment_code ?? 'CASH' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-[10px] font-black text-text-primary dark:text-dark-text leading-none">
                                            @if($tr->debit_amt > 0)
                                                {{ $tr->debitAccount ? $tr->debitAccount->account_name : '---' }}
                                            @else
                                                {{ $tr->creditAccount ? $tr->creditAccount->account_name : '---' }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-[10px] font-bold text-text-muted italic leading-none max-w-[200px] truncate" title="{{ $tr->note }}">
                                        {{ $tr->note ?? '---' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <span class="text-[10px] font-black tabular-nums {{ $tr->debit_amt > 0 ? 'text-danger' : 'text-text-muted' }} leading-none">
                                            {{ format_currency($tr->debit_amt > 0 ? $tr->debit_amt : 0) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <span class="text-[10px] font-black tabular-nums {{ $tr->credit_amt > 0 ? 'text-success' : 'text-text-muted' }} leading-none">
                                            {{ format_currency($tr->credit_amt > 0 ? $tr->credit_amt : 0) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-0.5 bg-background dark:bg-slate-800 rounded font-black text-[8px] uppercase tracking-widest text-text-muted">
                                            {{ $tr->creator ? $tr->creator->name : 'System' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-text-muted italic text-[10px] font-black uppercase tracking-widest">No transactions found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                    <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                        Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} entries
                    </p>
                    <div class="flex gap-1">
                        {{ $transactions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
