<x-app-layout title="Profit & Loss Report">
    <div x-data="profitLossReport()">
                
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Profit & Loss Report <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Financial Analysis</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Profit & Loss</span>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="px-4 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Export Report
                </button>
            </div>
        </div>

        <!-- DATE SELECTOR -->
        <div class="mb-6 relative group w-full max-w-sm">
            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Select Date Range</label>
            <div class="relative">
                <button @click="isDatePickerOpen = !isDatePickerOpen" class="w-full flex items-center justify-between gap-3 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus:border-primary-500 hover:border-primary-500 transition-all shadow-sm">
                    <span x-text="dateRange" class="text-[11px] font-bold text-slate-700 dark:text-slate-300"></span>
                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="isDatePickerOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                
                <div x-show="isDatePickerOpen" @click.away="isDatePickerOpen = false" x-cloak 
                     x-transition:enter="transition ease-out duration-200" 
                     x-transition:enter-start="opacity-0 translate-y-2" 
                     x-transition:enter-end="opacity-100 translate-y-0" 
                     class="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-dark-card rounded-xl shadow-xl border border-slate-100 dark:border-dark-border z-50 p-2">
                    <div class="space-y-0.5">
                        <template x-for="range in ['Today', 'Yesterday', 'Last 7 Days', 'Last 30 Days', 'This Month', 'Last Month', 'Custom Range']">
                            <button @click="dateRange = range; isDatePickerOpen = false" 
                                    class="w-full text-left px-3 py-2 rounded-lg text-[10px] font-bold transition-all uppercase tracking-wider"
                                    :class="dateRange === range ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/10' : 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800'"
                                    x-text="range">
                            </button>
                        </template>
                    </div>
                    <div class="mt-2 pt-2 border-t border-slate-50 dark:border-dark-border flex gap-2">
                        <button @click="isDatePickerOpen = false" class="flex-1 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Apply</button>
                        <button @click="isDatePickerOpen = false" class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROFIT CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Company Info & Gross/Net -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                <div class="p-4 border-b border-slate-50 dark:border-dark-border">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight" x-text="company.name"></h3>
                            <div class="mt-1 space-y-0.5">
                                <p class="text-[9px] font-bold text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span x-text="'Address: ' + company.address + ', ' + company.country"></span>
                                </p>
                                <p class="text-[9px] font-bold text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <span x-text="'Mobile: ' + company.mobile"></span>
                                </p>
                                <p class="text-[9px] font-bold text-slate-400 flex items-center gap-1.5">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <span x-text="'Email: ' + company.email"></span>
                                </p>
                            </div>
                        </div>
                        <div class="px-2 py-1 bg-rose-50 dark:bg-rose-500/10 text-rose-600 rounded-lg flex items-center gap-2 border border-rose-100 dark:border-rose-900/20">
                            <span class="w-1.5 h-1.5 bg-rose-600 rounded-full animate-pulse"></span>
                            <span class="text-[8px] font-black uppercase tracking-widest">Financial Report</span>
                        </div>
                    </div>
                </div>
                <div class="p-0">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-3 text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Gross Profit</td>
                                <td class="px-4 py-3 text-xs font-black tabular-nums text-right text-slate-800 dark:text-white" :class="isLoading ? 'opacity-50' : ''">
                                    {{ $currencySymbol }}<span x-text="summary.grossProfit">0.00</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors bg-slate-50/50 dark:bg-slate-800/30">
                                <td class="px-4 py-3 text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-widest">Net Profit</td>
                                <td class="px-4 py-3 text-xs font-black tabular-nums text-right text-slate-800 dark:text-white" :class="isLoading ? 'opacity-50' : ''">
                                    {{ $currencySymbol }}<span x-text="summary.netProfit">0.00</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stats Overview Card -->
            <div class="bg-slate-900 dark:bg-black rounded-2xl p-6 text-white relative overflow-hidden shadow-lg flex flex-col justify-center border border-slate-800">
                <div class="relative z-10">
                    <h3 class="text-[10px] font-black uppercase tracking-widest mb-4 flex items-center gap-2 text-slate-400">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping"></span>
                        Performance Overview
                    </h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Sales</p>
                            <h4 class="text-2xl font-black text-white">{{ $currencySymbol }}<span x-text="sales.totalSales">0.00</span></h4>
                            <p class="text-[9px] text-emerald-400 font-bold mt-1 flex items-center gap-1">
                                <span>▲</span> <span>+12.5% vs prev</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Expense</p>
                            <h4 class="text-2xl font-black text-white">{{ $currencySymbol }}<span x-text="expenses.total">0.00</span></h4>
                            <p class="text-[9px] text-slate-500 font-bold mt-1 flex items-center gap-1">
                                <span>Stable this period</span>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Decorative glow -->
                <div class="absolute -right-20 -top-20 w-56 h-56 bg-primary-600/10 rounded-full blur-3xl"></div>
                <div class="absolute -left-20 -bottom-20 w-56 h-56 bg-emerald-600/10 rounded-full blur-3xl"></div>
            </div>
        </div>

        <!-- DETAILED BREAKDOWN -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Left Column: Inventory & Purchases -->
            <div class="space-y-6">
                <!-- Purchases Section -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Inventory & Purchase</h3>
                        </div>
                        <div class="p-1.5 bg-blue-50 dark:bg-blue-500/10 text-blue-500 rounded-lg">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors bg-blue-50/20 dark:bg-blue-500/5">
                                <td class="px-4 py-2.5 text-[10px] font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Opening Stock</td>
                                <td class="px-4 py-2.5 text-[11px] font-black tabular-nums text-right">{{ $currencySymbol }}<span x-text="inventory.openingStock">0.00</span></td>
                            </tr>
                            <tr class="bg-slate-50/30 dark:bg-slate-800/20"><td colspan="2" class="px-4 py-1.5 text-[9px] font-black text-primary-600 uppercase tracking-widest italic">Purchase</td></tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Purchase</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchases.totalPurchase">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Purchase Tax</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchases.purchaseTax">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Other Charges</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchases.otherCharges">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchases.discount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-emerald-600">Paid Payment</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-emerald-600">{{ $currencySymbol }}<span x-text="purchases.paidAmount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-rose-600">Purchase Due</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-rose-600">{{ $currencySymbol }}<span x-text="purchases.due">0.00</span></td>
                            </tr>
                            <tr class="bg-slate-50/30 dark:bg-slate-800/20"><td colspan="2" class="px-4 py-1.5 text-[9px] font-black text-primary-600 uppercase tracking-widest italic">Purchase Return</td></tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Purchase Return</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchaseReturns.totalReturn">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Return Tax</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchaseReturns.returnTax">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Other Charges</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchaseReturns.otherCharges">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="purchaseReturns.discount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-emerald-600">Paid Payment</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-emerald-600">{{ $currencySymbol }}<span x-text="purchaseReturns.paidAmount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-rose-600">Return Due</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-rose-600">{{ $currencySymbol }}<span x-text="purchaseReturns.due">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Sales & Expenses -->
            <div class="space-y-6">
                <!-- Sales Section -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Sales & Revenue</h3>
                        </div>
                        <div class="p-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 rounded-lg">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors bg-rose-50/20 dark:bg-rose-500/5">
                                <td class="px-4 py-2.5 text-[10px] font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Total Expense</td>
                                <td class="px-4 py-2.5 text-[11px] font-black tabular-nums text-right">{{ $currencySymbol }}<span x-text="expenses.total">0.00</span></td>
                            </tr>
                            <tr class="bg-slate-50/30 dark:bg-slate-800/20"><td colspan="2" class="px-4 py-1.5 text-[9px] font-black text-primary-600 uppercase tracking-widest italic">Sales</td></tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Sales</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="sales.totalSales">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Sales Tax</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="sales.salesTax">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Other Charges</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="sales.otherCharges">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="sales.discount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500 font-italic">Coupon Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="sales.couponDiscount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors bg-slate-50/50 dark:bg-slate-800/50">
                                <td class="px-4 py-2 text-[10px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest">Total Sales</td>
                                <td class="px-4 py-2 text-[11px] font-black tabular-nums text-right text-slate-800 dark:text-white">{{ $currencySymbol }}<span x-text="sales.grandTotal">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-emerald-600">Paid Payment</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-emerald-600">{{ $currencySymbol }}<span x-text="sales.paidAmount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-rose-600">Sales Due</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-rose-600">{{ $currencySymbol }}<span x-text="sales.due">0.00</span></td>
                            </tr>

                            <tr class="bg-slate-50/30 dark:bg-slate-800/20"><td colspan="2" class="px-4 py-1.5 text-[9px] font-black text-primary-600 uppercase tracking-widest italic">Sales Return</td></tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Total Sales Return</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="salesReturns.totalReturn">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Return Tax</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="salesReturns.returnTax">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Other Charges</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="salesReturns.otherCharges">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500 font-italic">Coupon Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="salesReturns.couponDiscount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-medium text-slate-500">Return Discount</td>
                                <td class="px-4 py-2 text-[10px] font-bold tabular-nums text-right text-slate-700 dark:text-slate-300">{{ $currencySymbol }}<span x-text="salesReturns.discount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors bg-slate-50/50 dark:bg-slate-800/50">
                                <td class="px-4 py-2 text-[10px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest">Return Total</td>
                                <td class="px-4 py-2 text-[11px] font-black tabular-nums text-right text-slate-800 dark:text-white">{{ $currencySymbol }}<span x-text="salesReturns.grandTotal">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-emerald-600">Paid Payment</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-emerald-600">{{ $currencySymbol }}<span x-text="salesReturns.paidAmount">0.00</span></td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                <td class="px-4 py-2 text-[10px] font-bold text-rose-600">Return Due</td>
                                <td class="px-4 py-2 text-[10px] font-black tabular-nums text-right text-rose-600">{{ $currencySymbol }}<span x-text="salesReturns.due">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script>
        function profitLossReport() {
            return {
                dateRange: 'This Month',
                isDatePickerOpen: false,
                isLoading: false,
                startDate: moment().startOf('month').format('YYYY-MM-DD'),
                endDate: moment().endOf('month').format('YYYY-MM-DD'),
                
                company: {
                    name: '{{ $store->store_name ?? 'My Store' }}',
                    address: '{{ $store->address ?? 'N/A' }}',
                    country: '{{ $store->country ?? 'N/A' }}',
                    mobile: '{{ $store->mobile ?? 'N/A' }}',
                    email: '{{ $store->email ?? 'N/A' }}',
                },
                
                summary: { grossProfit: '0.00', netProfit: '0.00' },
                inventory: { openingStock: '0.00' },
                purchases: { totalPurchase: '0.00', purchaseTax: '0.00', otherCharges: '0.00', discount: '0.00', paidAmount: '0.00', due: '0.00', grandTotal: '0.00' },
                purchaseReturns: { totalReturn: '0.00', returnTax: '0.00', otherCharges: '0.00', discount: '0.00', paidAmount: '0.00', due: '0.00', grandTotal: '0.00' },
                sales: { totalSales: '0.00', salesTax: '0.00', otherCharges: '0.00', discount: '0.00', couponDiscount: '0.00', paidAmount: '0.00', due: '0.00', grandTotal: '0.00' },
                salesReturns: { totalReturn: '0.00', returnTax: '0.00', otherCharges: '0.00', couponDiscount: '0.00', discount: '0.00', paidAmount: '0.00', due: '0.00', grandTotal: '0.00' },
                expenses: { total: '0.00' },

                init() {
                    const self = this;
                    
                    // Allow external date picker to update our state if needed, or we just handle it directly below
                    this.$watch('dateRange', (value) => {
                        this.applyDateRange(value);
                    });
                    
                    // Fetch initial data
                    this.fetchData();
                },

                applyDateRange(rangeIndicator) {
                    switch(rangeIndicator) {
                        case 'Today':
                            this.startDate = moment().format('YYYY-MM-DD');
                            this.endDate = moment().format('YYYY-MM-DD');
                            break;
                        case 'Yesterday':
                            this.startDate = moment().subtract(1, 'days').format('YYYY-MM-DD');
                            this.endDate = moment().subtract(1, 'days').format('YYYY-MM-DD');
                            break;
                        case 'Last 7 Days':
                            this.startDate = moment().subtract(6, 'days').format('YYYY-MM-DD');
                            this.endDate = moment().format('YYYY-MM-DD');
                            break;
                        case 'Last 30 Days':
                            this.startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');
                            this.endDate = moment().format('YYYY-MM-DD');
                            break;
                        case 'This Month':
                            this.startDate = moment().startOf('month').format('YYYY-MM-DD');
                            this.endDate = moment().endOf('month').format('YYYY-MM-DD');
                            break;
                        case 'Last Month':
                            this.startDate = moment().subtract(1, 'month').startOf('month').format('YYYY-MM-DD');
                            this.endDate = moment().subtract(1, 'month').endOf('month').format('YYYY-MM-DD');
                            break;
                    }
                    this.fetchData();
                },

                async fetchData() {
                    this.isLoading = true;
                    try {
                        const response = await fetch(`{{ route('reports.profit_loss_data') }}?start_date=${this.startDate}&end_date=${this.endDate}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });
                        
                        if (!response.ok) throw new Error('Network response was not ok');
                        
                        const result = await response.json();
                        
                        if (result.status === 'success') {
                            const data = result.data;
                            this.summary = data.summary;
                            this.inventory = data.inventory;
                            this.purchases = data.purchases;
                            this.purchaseReturns = data.purchaseReturns;
                            this.sales = data.sales;
                            this.salesReturns = data.salesReturns;
                            this.expenses = data.expenses;
                        } else {
                            console.error('API Error:', result.message);
                        }
                    } catch (error) {
                        console.error('Failed to fetch profit loss data:', error);
                    } finally {
                        this.isLoading = false;
                    }
                }
            }
        }
    </script>
</x-app-layout>