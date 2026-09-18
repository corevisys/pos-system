<x-app-layout title="Expenses List">
    <div x-data="{
        copySuccess: false,
        copyTable() {
            let rows = Array.from(document.querySelectorAll('table tbody tr')).filter(r => r.querySelectorAll('td').length > 1);
            if (rows.length === 0) return;
            let text = 'Date\tCategory\tReference No\tExpense For\tAmount\tPayment Type\tAccount\tNote\n';
            rows.forEach(r => {
                let cells = r.querySelectorAll('td');
                if (cells.length >= 8) {
                    let date = cells[0].innerText.trim();
                    let cat = cells[1].innerText.trim();
                    let ref = cells[2].innerText.trim();
                    let ef = cells[3].innerText.trim();
                    let amt = cells[4].innerText.trim();
                    let pt = cells[5].innerText.trim();
                    let acc = cells[6].innerText.trim();
                    let note = cells[7].innerText.trim();
                    text += `${date}\t${cat}\t${ref}\t${ef}\t${amt}\t${pt}\t${acc}\t${note}\n`;
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
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Expenses List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Expenses List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">View/Search Expenses</p>
            </div>

            <a href="{{ route('expenses.add') }}" class="btn-primary w-full md:w-auto !bg-danger hover:!bg-danger-hover">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Expense
            </a>
        </div>

        <!-- TABLE SECTION -->
        <div class="card overflow-hidden p-0">
            <!-- Table Controls -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <!-- Page Size Selector (Phase 6: wired to per_page) -->
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
                    <!-- Export Actions (Phase 6: wired, store-scoped) -->
                    <div class="flex bg-background dark:bg-slate-800 rounded-xl p-1 border border-border dark:border-dark-border">
                        <button @click="copyTable()" type="button" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all" x-text="copySuccess ? 'Copied!' : 'Copy'">Copy</button>
                        <a href="{{ route('expenses.list', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                        <a href="{{ route('expenses.list', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                    </div>
                    <form method="GET" action="{{ route('expenses.list') }}" class="relative group">
                        @if(request('per_page'))
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
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
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Category</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Reference No.</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Expense For</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount ({{ $currencySymbol }})</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Payment Type</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Account</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Note</th>
                            <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse($expenses as $expense)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-4 py-2 text-[10px] font-black text-text-primary dark:text-dark-text leading-none">
                                    {{ \Carbon\Carbon::parse($expense->expense_date)->format('d-m-Y') }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 bg-primary/10 text-primary rounded text-[9px] font-black uppercase tracking-widest border border-primary/20">
                                        {{ $expense->category ? $expense->category->category_name : 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-[10px] font-bold text-text-muted italic font-mono">
                                    {{ $expense->reference_no ?? '---' }}
                                </td>
                                <td class="px-4 py-2 text-[10px] font-bold text-text-primary dark:text-dark-text">
                                    {{ $expense->expense_for }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span class="text-[10px] font-black tabular-nums text-text-primary dark:text-white leading-none">
                                        <x-money value="{{ $expense->expense_amt }}" />
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 bg-background dark:bg-slate-800 rounded font-black text-[8px] uppercase tracking-widest text-text-muted">
                                        {{ $expense->payment_type ?? 'Cash' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-[10px] font-black text-text-muted uppercase tracking-tight">
                                    {{ $expense->account ? $expense->account->account_name : '---' }}
                                </td>
                                <td class="px-4 py-2 text-[10px] font-medium text-text-muted italic">{{ \Illuminate\Support\Str::limit($expense->note, 20) ?? '---' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <x-dropdown align="right" width="40">
                                        <x-slot name="trigger">
                                            <button type="button" class="btn-primary px-3 py-1 !text-[9px] uppercase tracking-widest flex items-center gap-1.5 leading-none">
                                                Action
                                                <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <x-dropdown-link :href="route('expenses.edit', $expense->id)">Edit</x-dropdown-link>
                                            <form action="{{ route('expenses.delete', $expense->id) }}" method="POST" onsubmit="return confirm('Delete and reverse this expense? Its ledger impact will be reversed with an EXPENSE REVERSAL entry.');">
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
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No expenses found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Total + Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-4">
                    <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                        Showing {{ $expenses->firstItem() ?? 0 }} to {{ $expenses->lastItem() ?? 0 }} of {{ $expenses->total() }} entries
                    </p>
                    <span class="px-3 py-1 bg-success/10 text-success rounded text-[10px] font-black tabular-nums border border-success/20">
                        Total: <x-money value="{{ $totalExpenses }}" />
                    </span>
                </div>
                <div class="flex gap-1">
                    {{ $expenses->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
