<x-app-layout title="Cash Flow Statement">
    <div x-data="cashFlowReport()" x-init="init()" x-cloak>
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2">
                    <span>Cash Flow Statement</span>
                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                        Financial Intelligence
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
                    <span class="text-primary-600 dark:text-primary-400 text-[10px] font-black uppercase tracking-wider">Cash Flow Statement</span>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button @click="exportCSV()" class="px-4 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export CSV
                </button>
                <button @click="window.print()" class="px-4 py-2.5 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-800 dark:hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Statement
                </button>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 mb-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-dark-border">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 flex items-center gap-2">
                    <span class="w-2 h-4 bg-primary-500 rounded-full"></span>
                    Period Filter & Scope
                </h3>
                <!-- Quick Date Presets -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <button @click="setPreset('today')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'today' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">Today</button>
                    <button @click="setPreset('yesterday')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'yesterday' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">Yesterday</button>
                    <button @click="setPreset('last7')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'last7' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">Last 7 Days</button>
                    <button @click="setPreset('thisMonth')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'thisMonth' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">This Month</button>
                    <button @click="setPreset('lastMonth')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'lastMonth' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">Last Month</button>
                    <button @click="setPreset('thisYear')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase tracking-wider transition-all" :class="activePreset === 'thisYear' ? 'bg-primary-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'">This Year</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- From Date -->
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">From Date</label>
                    <input type="date" x-model="filters.start_date" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-xs font-bold transition-all outline-none text-slate-800 dark:text-white">
                </div>

                <!-- To Date -->
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">To Date</label>
                    <input type="date" x-model="filters.end_date" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-xs font-bold transition-all outline-none text-slate-800 dark:text-white">
                </div>

                <!-- Account Scope Filter -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Account Scope</label>
                    <x-searchable-select 
                        :options="$accounts" 
                        labelKey="account_name" 
                        valueKey="id" 
                        subtextKey="account_code" 
                        emptyOption="All Accounts (Consolidated Business Cash)" 
                        emptyValue="all" 
                        placeholder="Select Account" 
                        model="filters.account_id" 
                    />
                </div>
            </div>

            <div class="flex justify-between items-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-dark-border">
                <div class="text-[11px] text-slate-400 font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Reconciliation Formula: <strong>Opening Cash + Net Cash Flow = Closing Cash</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="resetFilters()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                        Reset
                    </button>
                    <button @click="loadData()" :disabled="isLoading" class="px-6 py-2 bg-primary-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-primary-700 transition-all shadow-md shadow-primary-500/20 flex items-center gap-2 disabled:opacity-50">
                        <svg x-show="!isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <svg x-show="isLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isLoading ? 'Recalculating...' : 'Generate Statement'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI SUMMARY CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <!-- 1. Opening Cash Position -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opening Cash Position</span>
                    <span class="p-2 bg-blue-50 dark:bg-blue-500/10 text-blue-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-slate-800 dark:text-white tabular-nums">
                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.opening_balance)">0.00</span>
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>At Period Start:</span>
                    <span class="text-slate-600 dark:text-slate-300 font-extrabold" x-text="period.start_date || '—'">—</span>
                </p>
            </div>

            <!-- 2. Total Cash Inflows -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Cash Inflow (+)</span>
                    <span class="p-2 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.total_inflow)">0.00</span>
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Operating + Deposits:</span>
                    <span class="text-emerald-600 font-extrabold" x-text="'+' + formatNumber(summary.total_inflow)">+0.00</span>
                </p>
            </div>

            <!-- 3. Total Cash Outflows -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">Total Cash Outflow (−)</span>
                    <span class="p-2 bg-rose-50 dark:bg-rose-500/10 text-rose-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-rose-600 dark:text-rose-400 tabular-nums">
                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.total_outflow)">0.00</span>
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Expenses + Refunds:</span>
                    <span class="text-rose-600 font-extrabold" x-text="'−' + formatNumber(summary.total_outflow)">−0.00</span>
                </p>
            </div>

            <!-- 4. Net Cash Flow -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider" :class="summary.net_cash_flow >= 0 ? 'text-emerald-600' : 'text-rose-600'">Net Cash Flow (In − Out)</span>
                    <span class="p-2 rounded-xl" :class="summary.net_cash_flow >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-rose-50 text-rose-600 dark:bg-rose-500/10'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black tabular-nums" :class="summary.net_cash_flow >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    <span x-text="summary.net_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(summary.net_cash_flow))">0.00</span>
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Cash Position Change:</span>
                    <span class="font-extrabold" :class="summary.net_cash_flow >= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="summary.net_cash_flow >= 0 ? 'Net Surplus' : 'Net Deficit'">Surplus</span>
                </p>
            </div>

            <!-- 5. Closing Cash Position & Reconciliation Check -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border shadow-sm relative overflow-hidden" :class="summary.is_reconciled ? 'border-emerald-200 dark:border-emerald-500/20 bg-emerald-50/20 dark:bg-emerald-500/5' : 'border-rose-200 dark:border-rose-500/20'">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Closing Cash Position</span>
                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest flex items-center gap-1" :class="summary.is_reconciled ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'">
                        <svg x-show="summary.is_reconciled" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        <span x-text="summary.is_reconciled ? 'Reconciled' : 'Variance'">Reconciled</span>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-white tabular-nums">
                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.closing_balance)">0.00</span>
                </h3>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                    <span>At Period End:</span>
                    <span class="text-slate-700 dark:text-slate-200 font-extrabold" x-text="period.end_date || '—'">—</span>
                </p>
            </div>
        </div>

        <!-- PURCHASE PAYMENTS ADVISORY BANNER -->
        <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4 mb-6 flex items-start gap-3.5 shadow-sm">
            <div class="p-2 bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl mt-0.5 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex-1 text-xs">
                <div class="font-bold text-amber-800 dark:text-amber-300 mb-0.5 flex items-center justify-between">
                    <span>Purchase Payments Included in Cash Flow</span>
                    <span class="font-black tabular-nums bg-amber-200 dark:bg-amber-500/30 px-2 py-0.5 rounded text-amber-900 dark:text-amber-200">
                        Purchase Payments: {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(operating_activities.purchase_payments)">0.00</span>
                    </span>
                </div>
                <p class="text-amber-700 dark:text-amber-400/90 leading-relaxed">
                    Vendor purchase payments are posted to the selected account and included in operating cash outflows from the accounting ledger.
                </p>
            </div>
        </div>

        <!-- 3-SECTION STATEMENT + TREND CHART GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- LEFT 2 COLS: STRUCTURED 3-SECTION CASH FLOW STATEMENT -->
            <div class="lg:col-span-2 bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-6 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-dark-border mb-6">
                    <div>
                        <h2 class="text-lg font-black text-slate-800 dark:text-white">Statement of Cash Flows</h2>
                        <p class="text-xs text-slate-400 font-bold" x-text="'Scope: ' + (period.selected_account || 'Consolidated') + ' • ' + (period.formatted_range || '')"></p>
                    </div>
                    <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider">
                        Standard Direct Method
                    </span>
                </div>

                <!-- SECTION 1: OPERATING ACTIVITIES -->
                <div class="mb-8">
                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl mb-3">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            1. Cash Flow from Operating Activities
                        </span>
                        <span class="text-xs font-black tabular-nums" :class="operating_activities.net_operating_cash_flow >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                            <span x-text="operating_activities.net_operating_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(operating_activities.net_operating_cash_flow))">0.00</span>
                        </span>
                    </div>

                    <div class="space-y-2.5 px-3">
                        <!-- Sales Payments Inflow -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">(+)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Cash Received from Sales & Collections</span>
                            </div>
                            <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                                +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(operating_activities.sales_payments)">0.00</span>
                            </span>
                        </div>

                        <!-- Sales Returns Outflow -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-rose-600 font-bold">(−)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Cash Paid for Sales Return Refunds</span>
                            </div>
                            <span class="font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                                −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(operating_activities.sales_refunds)">0.00</span>
                            </span>
                        </div>

                        <!-- Operating Expenses Outflow -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-rose-600 font-bold">(−)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Cash Paid for Operating Expenses</span>
                            </div>
                            <span class="font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                                −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(operating_activities.expenses)">0.00</span>
                            </span>
                        </div>

                        <!-- Purchase Payments Outflow -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-rose-600 font-bold">(−)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Cash Paid for Purchases</span>
                            </div>
                            <span class="font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                                −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(operating_activities.purchase_payments)">0.00</span>
                            </span>
                        </div>

                        <!-- Cash Drawer Reconciliation Adjustments -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="font-bold" :class="operating_activities.net_drawer_adjustment >= 0 ? 'text-emerald-600' : 'text-rose-600'">(+/−)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Cash Drawer Reconciliation Adjustments (Overage − Shortage)</span>
                            </div>
                            <span class="font-bold tabular-nums" :class="operating_activities.net_drawer_adjustment >= 0 ? 'text-slate-800 dark:text-slate-100' : 'text-rose-600 dark:text-rose-400'">
                                <span x-text="operating_activities.net_drawer_adjustment >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(operating_activities.net_drawer_adjustment))">0.00</span>
                            </span>
                        </div>

                        <!-- Net Operating Subtotal -->
                        <div class="flex items-center justify-between text-xs pt-2 font-black text-slate-800 dark:text-white">
                            <span class="uppercase tracking-wider">Net Cash from Operating Activities</span>
                            <span class="text-sm tabular-nums" :class="operating_activities.net_operating_cash_flow >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                <span x-text="operating_activities.net_operating_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(operating_activities.net_operating_cash_flow))">0.00</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: FINANCING & INTERNAL ACTIVITIES -->
                <div class="mb-8">
                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl mb-3">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            2. Cash Flow from Financing & Account Movements
                        </span>
                        <span class="text-xs font-black tabular-nums" :class="financing_activities.net_financing_cash_flow >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                            <span x-text="financing_activities.net_financing_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(financing_activities.net_financing_cash_flow))">0.00</span>
                        </span>
                    </div>

                    <div class="space-y-2.5 px-3">
                        <!-- Capital / Account Deposits -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">(+)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">External Deposits into Accounts</span>
                            </div>
                            <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                                +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(financing_activities.deposits)">0.00</span>
                            </span>
                        </div>

                        <!-- Account Opening Balances Registered in Period -->
                        <div x-show="financing_activities.opening_balances > 0" class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">(+)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">New Accounts Initial Capital Entries</span>
                            </div>
                            <span class="font-bold text-slate-800 dark:text-slate-100 tabular-nums">
                                +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(financing_activities.opening_balances)">0.00</span>
                            </span>
                        </div>

                        <!-- Internal Transfers In -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 font-bold">(+)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Transfers In (Inter-account Credits)</span>
                            </div>
                            <span class="font-bold text-slate-600 dark:text-slate-300 tabular-nums">
                                +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(financing_activities.transfers_in)">0.00</span>
                            </span>
                        </div>

                        <!-- Internal Transfers Out -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 font-bold">(−)</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Transfers Out (Inter-account Debits)</span>
                            </div>
                            <span class="font-bold text-slate-600 dark:text-slate-300 tabular-nums">
                                −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(financing_activities.transfers_out)">0.00</span>
                            </span>
                        </div>

                        <!-- Net Transfers Explanation -->
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40 bg-slate-50/50 dark:bg-slate-800/30 px-2 rounded-lg">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 font-black">(=)</span>
                                <span class="text-slate-600 dark:text-slate-400 font-bold" x-text="financing_activities.is_consolidated ? 'Net Internal Transfers (Zero Business Impact on Consolidated Total)' : 'Net Inter-account Movement for Selected Account'">Net Internal Transfers</span>
                            </div>
                            <span class="font-black tabular-nums" :class="financing_activities.net_internal_transfers == 0 ? 'text-slate-500' : (financing_activities.net_internal_transfers > 0 ? 'text-emerald-600' : 'text-rose-600')">
                                {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(financing_activities.net_internal_transfers)">0.00</span>
                            </span>
                        </div>

                        <!-- Net Financing Subtotal -->
                        <div class="flex items-center justify-between text-xs pt-2 font-black text-slate-800 dark:text-white">
                            <span class="uppercase tracking-wider">Net Cash from Financing Activities</span>
                            <span class="text-sm tabular-nums" :class="financing_activities.net_financing_cash_flow >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                <span x-text="financing_activities.net_financing_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(financing_activities.net_financing_cash_flow))">0.00</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: STATEMENT SUMMARY & RECONCILIATION -->
                <div class="bg-slate-900 dark:bg-slate-950 text-white rounded-2xl p-5 shadow-lg">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 pb-2 border-b border-slate-800 flex items-center justify-between">
                        <span>3. Period Summary & Reconciliation</span>
                        <span class="text-emerald-400 text-[10px] font-extrabold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Mathematical Audit Verified
                        </span>
                    </h3>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between text-slate-300">
                            <span>Opening Cash Position (Start Date):</span>
                            <span class="font-bold text-white tabular-nums text-sm">
                                {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.opening_balance)">0.00</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-300">
                            <span>(+) Net Cash from Operating Activities:</span>
                            <span class="font-bold tabular-nums" :class="operating_activities.net_operating_cash_flow >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                <span x-text="operating_activities.net_operating_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(operating_activities.net_operating_cash_flow))">0.00</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-300">
                            <span>(+) Net Cash from Financing Activities:</span>
                            <span class="font-bold tabular-nums" :class="financing_activities.net_financing_cash_flow >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                <span x-text="financing_activities.net_financing_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(financing_activities.net_financing_cash_flow))">0.00</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-300 pt-2 border-t border-slate-800">
                            <span class="font-black text-white">(=) Net Change in Cash Position:</span>
                            <span class="font-black text-sm tabular-nums" :class="summary.net_cash_flow >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                <span x-text="summary.net_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(summary.net_cash_flow))">0.00</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-slate-100 pt-3 border-t-2 border-slate-700 text-sm">
                            <span class="font-black uppercase tracking-wider text-emerald-400">Closing Cash Position (End Date):</span>
                            <span class="font-black text-base text-emerald-400 tabular-nums">
                                {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.closing_balance)">0.00</span>
                            </span>
                        </div>

                        <div class="bg-slate-800/80 rounded-xl p-3 mt-3 flex items-center justify-between text-[11px]">
                            <span class="text-slate-400">Reconciliation Audit Formula:</span>
                            <span class="font-mono text-emerald-300 font-bold" x-text="formatNumber(summary.opening_balance) + ' + (' + formatNumber(summary.net_cash_flow) + ') = ' + formatNumber(summary.closing_balance)"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT 1 COL: DAILY TREND CHART & BREAKDOWNS -->
            <div class="space-y-6">
                
                <!-- DAILY CASH FLOW TREND CHART -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <span class="w-2 h-4 bg-primary-500 rounded-full"></span>
                            Cash Flow Trend
                        </h3>
                        <span class="text-[10px] text-slate-400 font-bold">Daily Movements</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="cashFlowChart"></canvas>
                    </div>
                </div>

                <!-- PAYMENT METHODS INFLOW BREAKDOWN -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <span class="w-2 h-4 bg-emerald-500 rounded-full"></span>
                        Inflows by Payment Method
                    </h3>
                    <div class="space-y-2.5">
                        <template x-for="item in operating_activities.by_payment_method" :key="item.method">
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="font-bold text-slate-700 dark:text-slate-300" x-text="item.method"></span>
                                    <span class="text-[10px] text-slate-400" x-text="'(' + item.count + ' tx)'"></span>
                                </div>
                                <span class="font-black text-slate-800 dark:text-white tabular-nums">
                                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(item.amount)">0.00</span>
                                </span>
                            </div>
                        </template>
                        <div x-show="!operating_activities.by_payment_method || operating_activities.by_payment_method.length === 0" class="text-center py-4 text-xs text-slate-400 font-medium">
                            No sales payment inflow in this period.
                        </div>
                    </div>
                </div>

                <!-- EXPENSES OUTFLOW BREAKDOWN -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <span class="w-2 h-4 bg-rose-500 rounded-full"></span>
                        Expenses by Category
                    </h3>
                    <div class="space-y-2.5">
                        <template x-for="item in operating_activities.by_expense_category" :key="item.category">
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50 dark:border-dark-border/40">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    <span class="font-bold text-slate-700 dark:text-slate-300" x-text="item.category"></span>
                                    <span class="text-[10px] text-slate-400" x-text="'(' + item.count + ')'"></span>
                                </div>
                                <span class="font-black text-rose-600 dark:text-rose-400 tabular-nums">
                                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(item.amount)">0.00</span>
                                </span>
                            </div>
                        </template>
                        <div x-show="!operating_activities.by_expense_category || operating_activities.by_expense_category.length === 0" class="text-center py-4 text-xs text-slate-400 font-medium">
                            No expense outflows in this period.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ACCOUNT-WISE BREAKDOWN TABLE -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-6 mb-8 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100 dark:border-dark-border">
                <div>
                    <h2 class="text-base font-black text-slate-800 dark:text-white flex items-center gap-2">
                        <span>Account-Wise Cash Movement Breakdown</span>
                        <span class="px-2 py-0.5 bg-blue-500/10 text-blue-600 text-[10px] font-black uppercase rounded-lg">
                            Per-Account Balances
                        </span>
                    </h2>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">Opening, inflows, outflows, net change, and closing positions per active account.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/30 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-4">Account Name</th>
                            <th class="py-3 px-4">Account Code</th>
                            <th class="py-3 px-4 text-right">Opening Balance</th>
                            <th class="py-3 px-4 text-right text-emerald-600">Total Inflows</th>
                            <th class="py-3 px-4 text-right text-rose-600">Total Outflows</th>
                            <th class="py-3 px-4 text-right">Net Movement</th>
                            <th class="py-3 px-4 text-right text-slate-800 dark:text-white">Closing Balance</th>
                            <th class="py-3 px-4 text-right">Share of Cash</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border text-xs font-semibold">
                        <template x-for="acc in account_breakdown" :key="acc.id">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-800 dark:text-white" x-text="acc.account_name"></td>
                                <td class="py-3 px-4 font-mono text-slate-500" x-text="acc.account_code"></td>
                                <td class="py-3 px-4 text-right tabular-nums text-slate-600 dark:text-slate-300 font-bold">
                                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(acc.opening_balance)">0.00</span>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums text-emerald-600 font-bold">
                                    +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(acc.total_inflow)">0.00</span>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums text-rose-600 font-bold">
                                    −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(acc.total_outflow)">0.00</span>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums font-black" :class="acc.net_change >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                    <span x-text="acc.net_change >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(acc.net_change))">0.00</span>
                                </td>
                                <td class="py-3 px-4 text-right tabular-nums font-black text-slate-900 dark:text-white">
                                    {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(acc.closing_balance)">0.00</span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-12 bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-primary-500 h-full rounded-full" :style="'width: ' + Math.min(100, Math.max(0, acc.share_percentage)) + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-black text-slate-500" x-text="acc.share_percentage + '%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="account_breakdown.length === 0">
                            <td colspan="8" class="text-center py-6 text-slate-400 font-medium">No accounts found in this scope.</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 dark:border-dark-border bg-slate-50 dark:bg-slate-800/60 font-black text-xs">
                        <tr>
                            <td colspan="2" class="py-3.5 px-4 text-slate-800 dark:text-white uppercase tracking-wider">Total Consolidated Position</td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-slate-800 dark:text-white">
                                {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.opening_balance)">0.00</span>
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-emerald-600">
                                +{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.total_inflow)">0.00</span>
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-rose-600">
                                −{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.total_outflow)">0.00</span>
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums" :class="summary.net_cash_flow >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                <span x-text="summary.net_cash_flow >= 0 ? '+' : '−'">+</span>{{ $currencySymbol ?? '$' }}<span x-text="formatNumber(Math.abs(summary.net_cash_flow))">0.00</span>
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums text-emerald-600 dark:text-emerald-400 text-sm">
                                {{ $currencySymbol ?? '$' }}<span x-text="formatNumber(summary.closing_balance)">0.00</span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-slate-700 dark:text-slate-300">100.0%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- DETAILED TRANSACTION ACTIVITY LOG (DRILL DOWN) -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 dark:border-dark-border">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="w-2 h-4 bg-primary-500 rounded-full"></span>
                    Period Ledger Activity Log (<span x-text="data.length">0</span> Transactions)
                </h3>
            </div>

            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-white dark:bg-dark-card z-10">
                        <tr class="border-b border-slate-100 dark:border-dark-border text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Reference / Code</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Account Details</th>
                            <th class="py-2.5 px-3 text-right">Debit (−)</th>
                            <th class="py-2.5 px-3 text-right">Credit (+)</th>
                            <th class="py-2.5 px-3">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border/40 text-xs font-medium">
                        <template x-for="tx in data" :key="tx.id">
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="py-2 px-3 text-slate-500 whitespace-nowrap" x-text="tx.date"></td>
                                <td class="py-2 px-3 font-mono font-bold text-slate-700 dark:text-slate-300" x-text="tx.payment_code"></td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300': tx.type === 'SALES PAYMENT' || tx.type === 'CASH OVERAGE' || tx.type === 'DEPOSIT',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300': tx.type === 'EXPENSE' || tx.type === 'SALES RETURN REFUND' || tx.type === 'CASH SHORTAGE',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300': tx.type === 'TRANSFER',
                                            'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300': tx.type === 'OPENING BALANCE',
                                        }"
                                        x-text="tx.type">
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-slate-700 dark:text-slate-300">
                                    <span x-show="tx.credit_account" class="text-emerald-600 font-bold" x-text="'To: ' + tx.credit_account"></span>
                                    <span x-show="tx.debit_account && tx.credit_account"> | </span>
                                    <span x-show="tx.debit_account" class="text-rose-600 font-bold" x-text="'From: ' + tx.debit_account"></span>
                                </td>
                                <td class="py-2 px-3 text-right font-bold tabular-nums" :class="tx.debit_amt > 0 ? 'text-rose-600' : 'text-slate-400'">
                                    <span x-show="tx.debit_amt > 0">−{{ $currencySymbol ?? '$' }}</span><span x-text="formatNumber(tx.debit_amt)">0.00</span>
                                </td>
                                <td class="py-2 px-3 text-right font-bold tabular-nums" :class="tx.credit_amt > 0 ? 'text-emerald-600' : 'text-slate-400'">
                                    <span x-show="tx.credit_amt > 0">+{{ $currencySymbol ?? '$' }}</span><span x-text="formatNumber(tx.credit_amt)">0.00</span>
                                </td>
                                <td class="py-2 px-3 text-slate-400 text-[11px] truncate max-w-xs" x-text="tx.note || '—'"></td>
                            </tr>
                        </template>
                        <tr x-show="data.length === 0">
                            <td colspan="7" class="text-center py-6 text-slate-400">No transactions recorded during this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- SCRIPT & CHART INITIALIZATION -->
    @push('scripts')
    {{-- Phase 7 item 18: Chart.js vendored locally (asset()) instead of a CDN. --}}
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
    <script>
        function cashFlowReport() {
            return {
                isLoading: false,
                activePreset: 'thisMonth',
                filters: {
                    start_date: '{{ date("Y-m-01") }}',
                    end_date: '{{ date("Y-m-d") }}',
                    account_id: 'all'
                },
                period: {},
                summary: {
                    opening_balance: 0,
                    total_inflow: 0,
                    total_outflow: 0,
                    net_cash_flow: 0,
                    closing_balance: 0,
                    is_reconciled: true,
                    reconciliation_diff: 0
                },
                operating_activities: {
                    sales_payments: 0,
                    sales_refunds: 0,
                    expenses: 0,
                    cash_overage: 0,
                    cash_shortage: 0,
                    net_drawer_adjustment: 0,
                    net_operating_cash_flow: 0,
                    by_payment_method: [],
                    by_expense_category: []
                },
                financing_activities: {
                    deposits: 0,
                    opening_balances: 0,
                    transfers_in: 0,
                    transfers_out: 0,
                    net_internal_transfers: 0,
                    net_financing_cash_flow: 0,
                    is_consolidated: true
                },
                other_activities: {
                    other_credits: 0,
                    other_debits: 0,
                    net_other_cash_flow: 0
                },
                unlinked_purchases: {
                    direct_purchases_paid: 0,
                    note: ''
                },
                account_breakdown: [],
                data: [],
                chartInstance: null,

                init() {
                    this.loadData();
                },

                setPreset(preset) {
                    this.activePreset = preset;
                    const today = new Date();
                    const formatDate = (d) => {
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };

                    if (preset === 'today') {
                        this.filters.start_date = formatDate(today);
                        this.filters.end_date = formatDate(today);
                    } else if (preset === 'yesterday') {
                        const yest = new Date(today);
                        yest.setDate(yest.getDate() - 1);
                        this.filters.start_date = formatDate(yest);
                        this.filters.end_date = formatDate(yest);
                    } else if (preset === 'last7') {
                        const l7 = new Date(today);
                        l7.setDate(l7.getDate() - 6);
                        this.filters.start_date = formatDate(l7);
                        this.filters.end_date = formatDate(today);
                    } else if (preset === 'thisMonth') {
                        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                        this.filters.start_date = formatDate(firstDay);
                        this.filters.end_date = formatDate(today);
                    } else if (preset === 'lastMonth') {
                        const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
                        this.filters.start_date = formatDate(firstDay);
                        this.filters.end_date = formatDate(lastDay);
                    } else if (preset === 'thisYear') {
                        const firstDay = new Date(today.getFullYear(), 0, 1);
                        this.filters.start_date = formatDate(firstDay);
                        this.filters.end_date = formatDate(today);
                    }

                    this.loadData();
                },

                resetFilters() {
                    this.activePreset = 'thisMonth';
                    this.filters.account_id = 'all';
                    this.setPreset('thisMonth');
                },

                formatNumber(val) {
                    const num = Number(val) || 0;
                    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                loadData() {
                    this.isLoading = true;
                    const params = new URLSearchParams();
                    if (this.filters.start_date) params.append('start_date', this.filters.start_date);
                    if (this.filters.end_date) params.append('end_date', this.filters.end_date);
                    if (this.filters.account_id && this.filters.account_id !== 'all') params.append('account_id', this.filters.account_id);

                    fetch(`{{ route('reports.cash_flow_data') }}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.json())
                    .then(res => {
                        this.isLoading = false;
                        if (res.status === 'success') {
                            this.period = res.period || {};
                            this.summary = res.summary || {};
                            this.operating_activities = res.operating_activities || {};
                            this.financing_activities = res.financing_activities || {};
                            this.other_activities = res.other_activities || {};
                            this.unlinked_purchases = res.unlinked_purchases || {};
                            this.account_breakdown = res.account_breakdown || [];
                            this.data = res.data || [];

                            this.renderChart(res.charts?.daily_trend || []);
                        } else {
                            if (window.showError) window.showError(res.message || 'Failed to load cash flow statement');
                        }
                    })
                    .catch(err => {
                        this.isLoading = false;
                        console.error('Cash Flow Error:', err);
                        if (window.showError) window.showError('An unexpected network error occurred while generating statement.');
                    });
                },

                renderChart(dailyData) {
                    const ctx = document.getElementById('cashFlowChart');
                    if (!ctx) return;
                    if (!ctx.getContext('2d')) return;

                    if (this.chartInstance) {
                        this.chartInstance.destroy();
                    }

                    const labels = dailyData.map(d => d.formatted_date);
                    const inflows = dailyData.map(d => d.inflow);
                    const outflows = dailyData.map(d => d.outflow);
                    const runningBalances = dailyData.map(d => d.running_balance);

                    this.chartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    type: 'line',
                                    label: 'Cumulative Cash Position',
                                    data: runningBalances,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.2,
                                    yAxisID: 'y1',
                                    pointRadius: dailyData.length > 20 ? 0 : 3
                                },
                                {
                                    type: 'bar',
                                    label: 'Inflows (+)',
                                    data: inflows,
                                    backgroundColor: '#10b981',
                                    borderRadius: 4,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'bar',
                                    label: 'Outflows (−)',
                                    data: outflows,
                                    backgroundColor: '#ef4444',
                                    borderRadius: 4,
                                    yAxisID: 'y'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: { size: 10, weight: 'bold' }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 9 }, maxRotation: 45 }
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { color: 'rgba(200, 200, 200, 0.15)' },
                                    ticks: { font: { size: 9 } }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: { font: { size: 9 } }
                                }
                            }
                        }
                    });
                },

                exportCSV() {
                    let csv = "CASH FLOW STATEMENT\n";
                    csv += `Period,${this.period.formatted_range || ''}\n`;
                    csv += `Scope,${this.period.selected_account || 'All Accounts'}\n\n`;

                    csv += "STATEMENT SUMMARY\n";
                    csv += `Opening Cash Position,${this.summary.opening_balance}\n`;
                    csv += `Total Inflows,${this.summary.total_inflow}\n`;
                    csv += `Total Outflows,${this.summary.total_outflow}\n`;
                    csv += `Net Cash Flow,${this.summary.net_cash_flow}\n`;
                    csv += `Closing Cash Position,${this.summary.closing_balance}\n\n`;

                    csv += "OPERATING ACTIVITIES\n";
                    csv += `Cash Received from Sales,${this.operating_activities.sales_payments}\n`;
                    csv += `Cash Paid for Sales Return Refunds,-${this.operating_activities.sales_refunds}\n`;
                    csv += `Cash Paid for Expenses,-${this.operating_activities.expenses}\n`;
                    csv += `Cash Drawer Adjustments,${this.operating_activities.net_drawer_adjustment}\n`;
                    csv += `Net Operating Cash Flow,${this.operating_activities.net_operating_cash_flow}\n\n`;

                    csv += "FINANCING & INTERNAL ACTIVITIES\n";
                    csv += `External Deposits,${this.financing_activities.deposits}\n`;
                    csv += `Transfers In,${this.financing_activities.transfers_in}\n`;
                    csv += `Transfers Out,-${this.financing_activities.transfers_out}\n`;
                    csv += `Net Internal Transfers,${this.financing_activities.net_internal_transfers}\n`;
                    csv += `Net Financing Cash Flow,${this.financing_activities.net_financing_cash_flow}\n\n`;

                    csv += "ACCOUNT BREAKDOWN\n";
                    csv += "Account Name,Account Code,Opening Balance,Total Inflow,Total Outflow,Net Change,Closing Balance,Share %\n";
                    this.account_breakdown.forEach(a => {
                        csv += `"${a.account_name}","${a.account_code}",${a.opening_balance},${a.total_inflow},${a.total_outflow},${a.net_change},${a.closing_balance},${a.share_percentage}%\n`;
                    });

                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement("a");
                    link.setAttribute("href", url);
                    link.setAttribute("download", `cash_flow_statement_${this.filters.start_date}_to_${this.filters.end_date}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
