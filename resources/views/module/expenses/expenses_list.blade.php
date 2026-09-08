<x-app-layout title="Expenses List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Expenses List</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Expenses List</span>
                </div>
            </div>

            <a href="{{ route('expenses.add') }}" class="w-full md:w-auto px-4 py-1.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New Expense
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4">


            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                            <option>10</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                        </div>
                        <form method="GET" action="{{ route('expenses.list') }}" class="relative group">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40 shadow-sm transition-all">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </form>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 w-10 text-center">
                                    <input type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Category</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Reference No.</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Expense for</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Amount ({{ $currencySymbol }})</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Account</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Note</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($expenses as $expense)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-center">
                                        <input type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d-m-Y') }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 rounded text-[9px] font-black uppercase tracking-widest border border-primary-500/20">
                                            {{ $expense->category ? $expense->category->category_name : 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-black text-slate-400 font-mono tracking-wider italic">{{ $expense->reference_no ?? '---' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">{{ $expense->expense_for }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-right">
                                        <span class="text-[11px] font-black text-slate-800 dark:text-white tabular-nums tracking-tighter">{{ format_currency($expense->expense_amt) }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-tight">
                                        {{ $expense->account ? $expense->account->account_name : '---' }}
                                    </td>
                                    <td class="px-6 py-2.5 text-[10px] font-medium text-slate-400 italic">{{ \Illuminate\Support\Str::limit($expense->note, 20) ?? '---' }}</td>
                                    <td class="px-6 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    {{-- <a href="#" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</a> --}}
                                                    <form action="{{ route('expenses.delete', $expense->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-slate-400 text-xs font-bold">No expenses found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-slate-50/80 dark:bg-slate-800/80 border-t border-slate-200 dark:border-dark-border">
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 italic">Total Expenses</td>
                                <td class="px-6 py-3 text-right">
                                    <span class="px-3 py-1 bg-emerald-500/10 text-emerald-600 rounded text-[11px] font-black tabular-nums border border-emerald-500/20">
                                        {{ format_currency($expenses->sum('expense_amt')) }}
                                    </span>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">
                        Showing {{ $expenses->firstItem() ?? 0 }} to {{ $expenses->lastItem() ?? 0 }} of {{ $expenses->total() }} entries
                    </p>
                    <div class="flex gap-1">
                        @if ($expenses->onFirstPage())
                            <button class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-black text-slate-400 hover:bg-slate-50 transition-colors cursor-not-allowed">Prev</button>
                        @else
                            <a href="{{ $expenses->previousPageUrl() }}" class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-black text-slate-500 hover:bg-slate-50 transition-colors">Prev</a>
                        @endif

                        @foreach ($expenses->getUrlRange(1, $expenses->lastPage()) as $page => $url)
                            @if ($page == $expenses->currentPage())
                                <button class="px-3 py-1 bg-primary-600 text-white rounded-lg text-[9px] font-black shadow-md shadow-primary-200/50">{{ $page }}</button>
                            @else
                                <a href="{{ $url }}" class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-black text-slate-500 hover:bg-slate-50 transition-colors">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($expenses->hasMorePages())
                            <a href="{{ $expenses->nextPageUrl() }}" class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-black text-slate-500 hover:bg-slate-50 transition-colors">Next</a>
                        @else
                            <button class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-black text-slate-400 hover:bg-slate-50 transition-colors cursor-not-allowed">Next</button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full"></div>
        </div>
    </div>
</x-app-layout>
