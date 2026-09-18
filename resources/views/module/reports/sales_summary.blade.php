<x-app-layout title="Sales Summary Report">
    <div x-data="salesSummaryReport()" x-cloak>
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2">
                    <span>Sales Summary</span>
                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                        Analytics Engine
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
                    <span class="text-primary-600 dark:text-primary-400 text-[10px] font-black uppercase tracking-wider">Sales Summary</span>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <button @click="exportCSV()" class="px-4 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export CSV / Excel
                </button>
                <button @click="window.print()" class="px-4 py-2.5 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-800 dark:hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Report
                </button>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 mb-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-dark-border">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 flex items-center gap-2">
                    <span class="w-2 h-4 bg-primary-500 rounded-full"></span>
                    Filter Criteria & Date Range
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

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
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

                <!-- Warehouse Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Warehouse</label>
                    <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="All Warehouses" emptyValue="all" placeholder="Select Warehouse" model="filters.warehouse_id" />
                </div>

                <!-- Customer Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Customer</label>
                    <x-searchable-select :options="$customers" labelKey="customer_name" valueKey="id" subtextKey="customer_code" emptyOption="All Customers" emptyValue="all" placeholder="Select Customer" model="filters.customer_id" />
                </div>

                <!-- Payment Status -->
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider mb-1.5">Payment Status</label>
                    <select x-model="filters.payment_status" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-xs font-bold transition-all outline-none text-slate-800 dark:text-white">
                        <option value="all">All Statuses</option>
                        <option value="Paid">Paid</option>
                        <option value="Partial">Partial</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end items-center gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-dark-border">
                <button @click="resetFilters()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                    Reset
                </button>
                <button @click="loadData()" :disabled="isLoading" class="px-6 py-2 bg-primary-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-primary-700 transition-all shadow-md shadow-primary-500/20 flex items-center gap-2 disabled:opacity-50">
                    <svg x-show="!isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <svg x-show="isLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isLoading ? 'Calculating...' : 'Generate Summary'"></span>
                </button>
            </div>
        </div>

        <!-- 8 EXECUTIVE KPI CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- 1. Total Sales -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Sales</span>
                    <span class="p-2 bg-blue-50 dark:bg-blue-500/10 text-blue-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-slate-800 dark:text-white tabular-nums">
                    <x-money value="summary.total_sales" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Invoices / Orders:</span>
                    <span class="text-slate-700 dark:text-slate-200 font-extrabold" x-text="summary.total_orders || 0">0</span>
                </p>
            </div>

            <!-- 2. Total Paid / Collected -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Collected</span>
                    <span class="p-2 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                    <x-money value="summary.total_paid" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Collection Rate:</span>
                    <span class="text-emerald-600 font-extrabold" x-text="getCollectionRate() + '%'">0%</span>
                </p>
            </div>

            <!-- 3. Total Due (Outstanding) -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">Total Outstanding Due</span>
                    <span class="p-2 bg-rose-50 dark:bg-rose-500/10 text-rose-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-rose-600 dark:text-rose-400 tabular-nums">
                    <x-money value="summary.total_due" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Due Percentage:</span>
                    <span class="text-rose-500 font-extrabold" x-text="getDueRate() + '%'">0%</span>
                </p>
            </div>

            <!-- 4. Estimated Gross Profit -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Total Gross Profit</span>
                    <span class="p-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </span>
                </div>
                <h3 class="text-2xl font-black text-indigo-600 dark:text-indigo-400 tabular-nums">
                    <x-money value="summary.total_profit" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2 flex items-center justify-between">
                    <span>Profit Margin:</span>
                    <span class="text-indigo-600 font-extrabold" x-text="getProfitMargin() + '%'">0%</span>
                </p>
            </div>

            <!-- 5. Total Cost of Goods (COGS) -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Cost of Goods (COGS)</span>
                    <span class="p-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </span>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white tabular-nums">
                    <x-money value="summary.total_cost" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2">Baseline inventory cost</p>
            </div>

            <!-- 6. Total Discounts Given -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-500">Total Discounts</span>
                    <span class="p-2 bg-amber-50 dark:bg-amber-500/10 text-amber-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    </span>
                </div>
                <h3 class="text-xl font-black text-amber-600 dark:text-amber-400 tabular-nums">
                    <x-money value="summary.total_discount" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2">Item + Invoice + Coupon</p>
            </div>

            <!-- 7. Total Tax Collected -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-400">Tax Collected</span>
                    <span class="p-2 bg-purple-50 dark:bg-purple-500/10 text-purple-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                    </span>
                </div>
                <h3 class="text-xl font-black text-purple-600 dark:text-purple-400 tabular-nums">
                    <x-money value="summary.total_tax" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2">GST / VAT Liability</p>
            </div>

            <!-- 8. Average Order Value (AOV) -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-teal-600 dark:text-teal-400">Avg Order Value (AOV)</span>
                    <span class="p-2 bg-teal-50 dark:bg-teal-500/10 text-teal-600 rounded-xl">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </span>
                </div>
                <h3 class="text-xl font-black text-teal-600 dark:text-teal-400 tabular-nums">
                    <x-money value="summary.avg_order_value" symbol="{{ $currencySymbol ?? '' }}" />
                </h3>
                <p class="text-xs font-bold text-slate-400 mt-2">Average checkout value</p>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Daily Trend Chart -->
            <div class="lg:col-span-2 bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-white">Daily Sales & Collections Trend</h3>
                        <p class="text-[10px] font-bold text-slate-400 mt-0.5">Timeline comparison of invoiced sales vs collected payments</p>
                    </div>
                </div>
                <div class="h-64 relative">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
            </div>

            <!-- Payment Methods Chart -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-white">Payment Methods Breakdown</h3>
                        <p class="text-[10px] font-bold text-slate-400 mt-0.5">Distribution across Cash, Card, and Bank channels</p>
                    </div>
                </div>
                <div class="h-64 relative flex items-center justify-center">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- MULTI-DIMENSIONAL BREAKDOWNS (WAREHOUSES & CATEGORIES & TOP PRODUCTS) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <!-- Warehouse Performance Breakdown -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                        Warehouse-Wise Performance
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="p-3">Warehouse</th>
                                <th class="p-3 text-center">Orders</th>
                                <th class="p-3 text-right">Total Sales</th>
                                <th class="p-3 text-right">Collected</th>
                                <th class="p-3 text-right">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                            <template x-for="wh in breakdowns.warehouse_wise" :key="wh.warehouse_id">
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-3 font-bold text-slate-800 dark:text-slate-200" x-text="wh.warehouse_name"></td>
                                    <td class="p-3 text-center font-bold text-slate-500" x-text="wh.total_orders"></td>
                                    <td class="p-3 text-right font-black text-slate-800 dark:text-white"><x-money value="wh.total_sales" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-bold text-emerald-600"><x-money value="wh.total_paid" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-bold text-rose-500"><x-money value="wh.total_due" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                </tr>
                            </template>
                            <tr x-show="!breakdowns.warehouse_wise || breakdowns.warehouse_wise.length === 0">
                                <td colspan="5" class="p-6 text-center text-slate-400 font-bold">No warehouse data for selected filters</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category-Wise Breakdown -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                        Category-Wise Sales Breakdown
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="p-3">Category</th>
                                <th class="p-3 text-center">Units Sold</th>
                                <th class="p-3 text-right">Total Revenue</th>
                                <th class="p-3 text-right">Revenue Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                            <template x-for="cat in breakdowns.category_wise" :key="cat.category_name">
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-3 font-bold text-slate-800 dark:text-slate-200" x-text="cat.category_name"></td>
                                    <td class="p-3 text-center font-bold text-slate-500" x-text="cat.total_qty"></td>
                                    <td class="p-3 text-right font-black text-slate-800 dark:text-white"><x-money value="cat.total_sales" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-bold text-purple-600" x-text="getCategoryShare(cat.total_sales) + '%'"></td>
                                </tr>
                            </template>
                            <tr x-show="!breakdowns.category_wise || breakdowns.category_wise.length === 0">
                                <td colspan="4" class="p-6 text-center text-slate-400 font-bold">No category data for selected filters</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TOP 10 PRODUCTS & CUSTOMER RISK GRIDS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <!-- Top 10 Selling Products -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                        Top 10 Selling Products
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="p-3">Product Name</th>
                                <th class="p-3 text-center">Qty Sold</th>
                                <th class="p-3 text-right">Revenue</th>
                                <th class="p-3 text-right">Cost</th>
                                <th class="p-3 text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                            <template x-for="prod in breakdowns.top_products" :key="prod.item_id">
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-bold text-slate-800 dark:text-slate-200" x-text="prod.item_name"></div>
                                        <div class="text-[10px] font-medium text-slate-400" x-text="prod.item_code"></div>
                                    </td>
                                    <td class="p-3 text-center font-extrabold text-slate-700 dark:text-slate-300" x-text="prod.qty"></td>
                                    <td class="p-3 text-right font-bold text-slate-800 dark:text-white"><x-money value="prod.revenue" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-medium text-slate-500"><x-money value="prod.cost" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-black" :class="prod.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'">
                                        <x-money value="prod.profit" symbol="{{ $currencySymbol ?? '' }}" />
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!breakdowns.top_products || breakdowns.top_products.length === 0">
                                <td colspan="5" class="p-6 text-center text-slate-400 font-bold">No product sales found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer Outstanding Dues -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                        Customers With Outstanding Due
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="p-3">Customer</th>
                                <th class="p-3 text-center">Orders</th>
                                <th class="p-3 text-right">Total Invoiced</th>
                                <th class="p-3 text-right">Total Paid</th>
                                <th class="p-3 text-right text-rose-500">Unpaid Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                            <template x-for="cust in breakdowns.customers_with_due" :key="cust.customer_id">
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-bold text-slate-800 dark:text-slate-200" x-text="cust.customer_name"></div>
                                        <div class="text-[10px] font-medium text-slate-400" x-text="cust.mobile"></div>
                                    </td>
                                    <td class="p-3 text-center font-bold text-slate-500" x-text="cust.orders_count"></td>
                                    <td class="p-3 text-right font-bold text-slate-700 dark:text-slate-300"><x-money value="cust.total_spent" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-bold text-emerald-600"><x-money value="cust.total_paid" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                    <td class="p-3 text-right font-black text-rose-600"><x-money value="cust.total_due" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                </tr>
                            </template>
                            <tr x-show="!breakdowns.customers_with_due || breakdowns.customers_with_due.length === 0">
                                <td colspan="5" class="p-6 text-center text-emerald-600 dark:text-emerald-400 font-bold">
                                    ✓ All sales in this range are fully paid. Zero outstanding due!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DETAILED INVOICES TABLE -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-dark-border flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                    Detailed Invoices (<span x-text="invoices.length">0</span>)
                </h3>
                <div class="relative w-full sm:w-64">
                    <input type="text" x-model="searchQuery" placeholder="Search by code or customer..." class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-1.5 pl-8 pr-3 text-xs font-medium focus:ring-1 focus:ring-primary-500 outline-none text-slate-800 dark:text-white">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="p-3">Invoice No</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Customer</th>
                            <th class="p-3">Warehouse</th>
                            <th class="p-3 text-right">Grand Total</th>
                            <th class="p-3 text-right">Paid</th>
                            <th class="p-3 text-right">Due</th>
                            <th class="p-3 text-center">Status</th>
                            
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                        <template x-for="inv in paginatedInvoices" :key="inv.id">
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="p-3 font-mono font-bold text-primary-600 dark:text-primary-400" x-text="inv.sales_code"></td>
                                <td class="p-3 font-medium text-slate-500" x-text="inv.sales_date"></td>
                                <td class="p-3 font-bold text-slate-800 dark:text-slate-200" x-text="inv.customer"></td>
                                <td class="p-3 font-medium text-slate-500" x-text="inv.warehouse"></td>
                                <td class="p-3 text-right font-black text-slate-800 dark:text-white"><x-money value="inv.grand_total" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                <td class="p-3 text-right font-bold text-emerald-600"><x-money value="inv.paid_amount" symbol="{{ $currencySymbol ?? '' }}" /></td>
                                <td class="p-3 text-right font-bold" :class="inv.due_amount > 0 ? 'text-rose-500' : 'text-slate-400'">
                                    <x-money value="inv.due_amount" symbol="{{ $currencySymbol ?? '' }}" />
                                </td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 text-[9px] font-black uppercase rounded-full tracking-wider" 
                                          :class="{
                                              'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 border border-emerald-500/20': inv.payment_status === 'Paid',
                                              'bg-amber-50 text-amber-600 dark:bg-amber-500/10 border border-amber-500/20': inv.payment_status === 'Partial',
                                              'bg-rose-50 text-rose-600 dark:bg-rose-500/10 border border-rose-500/20': inv.payment_status === 'Unpaid'
                                          }"
                                          x-text="inv.payment_status">
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <a :href="'/sales/invoice/' + inv.id" target="_blank" class="p-1.5 text-slate-400 hover:text-primary-600 inline-block transition-colors" title="View Invoice">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredInvoices.length === 0">
                            <td colspan="8" class="p-8 text-center text-slate-400 font-bold">No sales invoices found matching your filter criteria.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="p-4 border-t border-slate-100 dark:border-dark-border flex flex-wrap items-center justify-between gap-3 bg-slate-50/30 dark:bg-slate-800/10 text-xs font-bold text-slate-500">
                <div>
                    Showing <span x-text="Math.min(1, filteredInvoices.length)"></span> to <span x-text="Math.min(currentPage * perPage, filteredInvoices.length)"></span> of <span x-text="filteredInvoices.length"></span> invoices
                </div>
                <div class="flex items-center gap-1">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-dark-border disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800">Prev</button>
                    <span class="px-3 py-1.5 font-bold" x-text="currentPage + ' / ' + maxPages"></span>
                    <button @click="currentPage = Math.min(maxPages, currentPage + 1)" :disabled="currentPage === maxPages" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-dark-border disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-800">Next</button>
                </div>
            </div>
        </div>

    </div>

    <!-- SCRIPT & CLIENT CONTROLLER -->
    <script>
        function salesSummaryReport() {
            return {
                isLoading: false,
                activePreset: 'thisMonth',
                filters: {
                    start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
                    end_date: new Date().toISOString().split('T')[0],
                    warehouse_id: 'all',
                    customer_id: 'all',
                    payment_status: 'all',
                },
                summary: {
                    total_sales: 0,
                    total_orders: 0,
                    total_paid: 0,
                    total_due: 0,
                    total_discount: 0,
                    total_tax: 0,
                    total_cost: 0,
                    total_profit: 0,
                    avg_order_value: 0
                },
                breakdowns: {
                    payment_status: {},
                    payment_methods: [],
                    warehouse_wise: [],
                    top_products: [],
                    category_wise: [],
                    top_customers: [],
                    customers_with_due: []
                },
                invoices: [],
                searchQuery: '',
                currentPage: 1,
                perPage: 15,
                dailyChart: null,
                paymentChart: null,

                init() {
                    this.loadData();
                },

                setPreset(preset) {
                    this.activePreset = preset;
                    const now = new Date();
                    let start = new Date();
                    let end = new Date();

                    if (preset === 'today') {
                        start = now;
                        end = now;
                    } else if (preset === 'yesterday') {
                        start.setDate(now.getDate() - 1);
                        end.setDate(now.getDate() - 1);
                    } else if (preset === 'last7') {
                        start.setDate(now.getDate() - 6);
                        end = now;
                    } else if (preset === 'thisMonth') {
                        start = new Date(now.getFullYear(), now.getMonth(), 1);
                        end = now;
                    } else if (preset === 'lastMonth') {
                        start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        end = new Date(now.getFullYear(), now.getMonth(), 0);
                    } else if (preset === 'thisYear') {
                        start = new Date(now.getFullYear(), 0, 1);
                        end = now;
                    }

                    this.filters.start_date = start.toISOString().split('T')[0];
                    this.filters.end_date = end.toISOString().split('T')[0];
                    this.loadData();
                },

                resetFilters() {
                    this.setPreset('thisMonth');
                    this.filters.warehouse_id = 'all';
                    this.filters.customer_id = 'all';
                    this.filters.payment_status = 'all';
                    this.loadData();
                },

                async loadData() {
                    this.isLoading = true;
                    try {
                        const params = new URLSearchParams(this.filters);
                        const response = await fetch(`{{ route('reports.sales_summary_data') }}?${params.toString()}`);
                        const res = await response.json();

                        if (res.status === 'success') {
                            this.summary = res.summary || {};
                            this.breakdowns = res.breakdowns || {};
                            this.invoices = res.data || [];
                            this.currentPage = 1;

                            this.$nextTick(() => {
                                this.renderDailyChart(res.charts?.daily_trend || []);
                                this.renderPaymentChart(res.charts?.payment_methods || []);
                            });
                        }
                    } catch (e) {
                        console.error('Error loading sales summary:', e);
                    } finally {
                        this.isLoading = false;
                    }
                },

                renderDailyChart(dailyData) {
                    const ctx = document.getElementById('dailyTrendChart');
                    if (!ctx || !ctx.isConnected) return;

                    if (this.dailyChart) {
                        this.dailyChart.destroy();
                    }
                    const existingChart = Chart.getChart(ctx);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    const labels = dailyData.map(d => d.formatted_date || d.date);
                    const salesValues = dailyData.map(d => d.sales);
                    const paidValues = dailyData.map(d => d.paid);

                    this.dailyChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels.length ? labels : ['No Data'],
                            datasets: [
                                {
                                    label: 'Sales Amount',
                                    data: salesValues.length ? salesValues : [0],
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    borderWidth: 2.5,
                                    pointRadius: 3,
                                    pointHoverRadius: 6
                                },
                                {
                                    label: 'Collected Amount',
                                    data: paidValues.length ? paidValues : [0],
                                    borderColor: '#10b981',
                                    backgroundColor: 'transparent',
                                    borderWidth: 2,
                                    borderDash: [4, 4],
                                    tension: 0.35,
                                    pointRadius: 2.5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: { font: { size: 10, weight: 'bold' } }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(200, 200, 200, 0.15)' },
                                    ticks: { font: { size: 9 } }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 9 }, maxRotation: 45 }
                                }
                            }
                        }
                    });
                },

                renderPaymentChart(methodsData) {
                    const ctx = document.getElementById('paymentMethodsChart');
                    if (!ctx || !ctx.isConnected) return;

                    if (this.paymentChart) {
                        this.paymentChart.destroy();
                    }
                    const existingChart = Chart.getChart(ctx);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    const labels = methodsData.length ? methodsData.map(m => m.method) : ['No Payments'];
                    const amounts = methodsData.length ? methodsData.map(m => m.amount) : [1];
                    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b'];

                    this.paymentChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: amounts,
                                backgroundColor: methodsData.length ? colors.slice(0, labels.length) : ['#e2e8f0'],
                                borderWidth: 2,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { font: { size: 10, weight: 'bold' } }
                                }
                            },
                            cutout: '65%'
                        }
                    });
                },

                get filteredInvoices() {
                    if (!this.searchQuery.trim()) {
                        return this.invoices;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.invoices.filter(i => 
                        (i.sales_code && i.sales_code.toLowerCase().includes(q)) ||
                        (i.customer && i.customer.toLowerCase().includes(q)) ||
                        (i.warehouse && i.warehouse.toLowerCase().includes(q))
                    );
                },

                get paginatedInvoices() {
                    const start = (this.currentPage - 1) * this.perPage;
                    return this.filteredInvoices.slice(start, start + this.perPage);
                },

                get maxPages() {
                    return Math.max(1, Math.ceil(this.filteredInvoices.length / this.perPage));
                },

                formatNumber(val) {
                    const n = parseFloat(val) || 0;
                    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                getCollectionRate() {
                    const sales = parseFloat(this.summary.total_sales) || 0;
                    const paid = parseFloat(this.summary.total_paid) || 0;
                    if (sales <= 0) return 0;
                    return Math.min(100, (paid / sales * 100)).toFixed(1);
                },

                getDueRate() {
                    const sales = parseFloat(this.summary.total_sales) || 0;
                    const due = parseFloat(this.summary.total_due) || 0;
                    if (sales <= 0) return 0;
                    return Math.min(100, (due / sales * 100)).toFixed(1);
                },

                getProfitMargin() {
                    const sales = parseFloat(this.summary.total_sales) || 0;
                    const profit = parseFloat(this.summary.total_profit) || 0;
                    if (sales <= 0) return 0;
                    return (profit / sales * 100).toFixed(1);
                },

                getCategoryShare(catSales) {
                    const total = parseFloat(this.summary.total_sales) || 0;
                    const cSales = parseFloat(catSales) || 0;
                    if (total <= 0) return 0;
                    return (cSales / total * 100).toFixed(1);
                },

                exportCSV() {
                    if (!this.invoices || this.invoices.length === 0) {
                        showError('No invoice data to export.');
                        return;
                    }

                    const rows = [
                        ['SALES SUMMARY REPORT'],
                        ['Date Range', `${this.filters.start_date} to ${this.filters.end_date}`],
                        ['Total Sales', this.summary.total_sales],
                        ['Total Collected', this.summary.total_paid],
                        ['Total Due', this.summary.total_due],
                        ['Total Profit', this.summary.total_profit],
                        ['Total Orders', this.summary.total_orders],
                        ['Avg Order Value', this.summary.avg_order_value],
                        [],
                        ['DETAILED INVOICES'],
                        ['Invoice No', 'Date', 'Customer', 'Warehouse', 'Grand Total', 'Paid Amount', 'Due Amount', 'Payment Status']
                    ];

                    this.invoices.forEach(inv => {
                        rows.push([
                            inv.sales_code,
                            inv.sales_date,
                            `"${(inv.customer || '').replace(/"/g, '""')}"`,
                            `"${(inv.warehouse || '').replace(/"/g, '""')}"`,
                            inv.grand_total,
                            inv.paid_amount,
                            inv.due_amount,
                            inv.payment_status
                        ]);
                    });

                    const csvContent = '\uFEFF' + rows.map(e => e.join(',')).join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `Sales_Summary_${this.filters.start_date}_to_${this.filters.end_date}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            };
        }
    </script>
</x-app-layout>
