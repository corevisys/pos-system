<x-app-layout title="Cash Reconciliation Report">
    <div x-data="cashReconciliationReport()" x-init="init()" x-cloak>
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2">
                    <span>Cash Reconciliation Report</span>
                    <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                        Audit Intelligence
                    </span>
                </h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-300 text-[10px] font-black uppercase tracking-wider">Reports</span>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-primary-600 dark:text-primary-400 text-[10px] font-black uppercase tracking-wider">Cash Reconciliation</span>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('accounts.cash-reconciliation.index') }}" class="px-4 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Reconciliations List
                </a>
                {{-- Phase 6 item 12: Cash Reconciliation's previously-missing CSV/
                     print export, via the shared mechanism. --}}
                <x-report-export-buttons :route="route('reports.cash_reconciliation_data')" class="!bg-white dark:!bg-dark-card border border-slate-200 dark:border-dark-border" />
                <button @click="window.print()" class="px-4 py-2.5 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-800 dark:hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Report
                </button>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 mb-6 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">From Date</label>
                    <input type="date" x-model="filters.start_date" @change="fetchData()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">To Date</label>
                    <input type="date" x-model="filters.end_date" @change="fetchData()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Warehouse</label>
                    <select x-model="filters.warehouse_id" @change="fetchData()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Cash Account</label>
                    <select x-model="filters.account_id" @change="fetchData()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Phase 4 item 7: wire the previously orphaned user_id filter.
                     $users is now store-scoped (Phase 2). --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">User</label>
                    <select x-model="filters.user_id" @change="fetchData()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->username ?: trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="resetFilters()" class="w-full py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-200 transition-all">
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI SUMMARY CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Total Reconciled</span>
                <div class="text-2xl font-black text-slate-800 dark:text-white" x-text="summary.total_reconciliations || 0">0</div>
                <div class="text-[10px] text-slate-400 mt-1"><span x-text="summary.balanced_count || 0">0</span> Balanced</div>
            </div>
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Expected Cash</span>
                <div class="text-2xl font-black text-slate-800 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(summary.total_expected)">0.00</span></div>
                <div class="text-[10px] text-slate-400 mt-1">System Expected Sum</div>
            </div>
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Counted Cash</span>
                <div class="text-2xl font-black text-slate-800 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(summary.total_counted)">0.00</span></div>
                <div class="text-[10px] text-slate-400 mt-1">Physically Counted Sum</div>
            </div>
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-4 shadow-sm" :class="{
                'border-l-4 border-l-emerald-500': summary.total_variance === 0,
                'border-l-4 border-l-blue-500': summary.total_variance > 0,
                'border-l-4 border-l-rose-500': summary.total_variance < 0
            }">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Net Variance</span>
                <div class="text-2xl font-black" :class="{
                    'text-emerald-600': summary.total_variance === 0,
                    'text-blue-600': summary.total_variance > 0,
                    'text-rose-600': summary.total_variance < 0
                }">
                    {{ $currencySymbol ?? '' }}<span x-text="formatMoney(summary.total_variance)">0.00</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    <span class="text-rose-500 font-bold">-{{ $currencySymbol ?? '' }}<span x-text="formatMoney(summary.total_shortage)">0.00</span> short</span> &bull; 
                    <span class="text-blue-500 font-bold">+{{ $currencySymbol ?? '' }}<span x-text="formatMoney(summary.total_overage)">0.00</span> over</span>
                </div>
            </div>
        </div>

        <!-- DETAILED TABLE SECTION -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-dark-border flex justify-between items-center">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-300">Reconciliation Records</h3>
                <span x-show="loading" class="text-xs text-primary-500 font-bold animate-pulse">Loading data...</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Code / Date</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Account</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Warehouse</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400">Counted By</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Expected</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Counted</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Variance</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-slate-400 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border font-medium">
                        <template x-for="r in records" :key="r.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-bold text-slate-800 dark:text-white">
                                    <div x-text="r.code"></div>
                                    <div class="text-[9px] font-medium text-slate-400" x-text="r.date"></div>
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-700 dark:text-slate-300" x-text="r.account"></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400" x-text="r.warehouse"></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400" x-text="r.user"></td>
                                <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(r.expected)"></span></td>
                                <td class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(r.counted)"></span></td>
                                <td class="px-4 py-3 text-right font-black">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px]" :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300': r.variance === 0,
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': r.variance > 0,
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300': r.variance < 0
                                    }">
                                        <span x-text="(r.variance >= 0 ? '+' : '') + '{{ $currencySymbol ?? '' }}' + formatMoney(r.variance)"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold" x-text="r.status"></td>
                            </tr>
                        </template>
                        <tr x-show="!loading && records.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400 font-bold">
                                No cash reconciliation records found for the selected date range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function cashReconciliationReport() {
            return {
                loading: false,
                filters: {
                    start_date: '',
                    end_date: '',
                    warehouse_id: '',
                    account_id: '',
                    user_id: ''
                },
                summary: {},
                records: [],
                init() {
                    this.fetchData();
                },
                fetchData() {
                    this.loading = true;
                    let query = new URLSearchParams(this.filters).toString();
                    fetch(`{{ route('reports.cash_reconciliation_data') }}?${query}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.summary = data.summary;
                                this.records = data.data;
                            }
                        })
                        .catch(err => console.error('Failed to fetch report data:', err))
                        .finally(() => this.loading = false);
                },
                resetFilters() {
                    this.filters = { start_date: '', end_date: '', warehouse_id: '', account_id: '', user_id: '' };
                    this.fetchData();
                },
                formatMoney(num) {
                    return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }
        }
    </script>
</x-app-layout>
