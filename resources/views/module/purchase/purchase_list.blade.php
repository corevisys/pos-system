<x-app-layout title="Purchase List">
    <div x-data="purchaseListPage()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-primary-500/10 text-primary-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                    Purchase List
                </h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-xs flex items-center gap-1 font-bold uppercase tracking-wider">
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-400 text-xs font-bold uppercase tracking-wider">Purchases</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('purchase.returns') }}" class="px-4 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-700 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider hover:bg-slate-50 dark:hover:bg-slate-800 transition-all flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                    View Returns
                </a>
                <a href="{{ route('purchase.new') }}" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Purchase
                </a>
            </div>
        </div>

        <!-- STATISTICS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-stat-card 
                label="Total Invoices" 
                :value="number_format($stats['total_invoices'])" 
                icon-bg="bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Total Invoiced" 
                :money="true"
                :value="$stats['total_amount']"
                icon-bg="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Total Paid" 
                :money="true"
                :value="$stats['total_paid']"
                icon-bg="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Total Due" 
                :money="true"
                :value="$stats['total_due']"
                icon-bg="bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <!-- FILTERS PANEL -->
        <form action="{{ route('purchase.list') }}" method="GET" id="filterForm" class="bg-white dark:bg-dark-card p-4 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Filter -->
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Code / Ref / Supplier..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 pl-9 pr-3 text-xs font-semibold text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <select name="warehouse_id" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500 cursor-pointer">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Payment Status Filter -->
                <div>
                    <select name="payment_status" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500 cursor-pointer">
                        <option value="">All Payment Statuses</option>
                        <option value="Paid" {{ request('payment_status') === 'Paid' ? 'selected' : '' }}>Paid</option>
                        <option value="Partial" {{ request('payment_status') === 'Partial' ? 'selected' : '' }}>Partial</option>
                        <option value="Unpaid" {{ request('payment_status') === 'Unpaid' ? 'selected' : '' }}>Unpaid</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                </div>

                <!-- Date To & Reset -->
                <div class="flex items-center gap-2">
                    <input type="date" name="to_date" value="{{ request('to_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                    @if(request()->hasAny(['search', 'warehouse_id', 'payment_status', 'from_date', 'to_date']))
                        <a href="{{ route('purchase.list') }}" class="p-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded-xl transition-colors shrink-0" title="Reset Filters">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <!-- TABLE SECTION -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            <!-- Toolbar -->
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-dark-border flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Show</span>
                    <select name="per_page" form="filterForm" onchange="document.getElementById('filterForm').submit()" class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-1 px-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-1 focus:ring-primary-500">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <span class="text-xs font-bold text-slate-400">entries</span>
                </div>

                <!-- Export Controls -->
                <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 p-1 rounded-xl border border-slate-100 dark:border-dark-border">
                    <button type="button" @click="copyTableData()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Copy
                    </button>
                    <button type="button" @click="exportCsv()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Excel
                    </button>
                    <button type="button" onclick="window.print()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Print
                    </button>
                </div>
            </div>

            <!-- Table Content -->
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="purchaseTable">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/40 border-b border-slate-100 dark:border-dark-border text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <tr>
                            <th class="px-4 py-3.5">Date</th>
                            <th class="px-4 py-3.5">Code</th>
                            <th class="px-4 py-3.5">Reference</th>
                            <th class="px-4 py-3.5">Supplier</th>
                            <th class="px-4 py-3.5">Warehouse</th>
                            <th class="px-4 py-3.5 text-right">Grand Total</th>
                            <th class="px-4 py-3.5 text-right">Paid</th>
                            <th class="px-4 py-3.5 text-right">Due</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-4 py-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border text-xs">
                        @forelse($purchases as $pur)
                            @php
                                $due = max(0, (float)$pur->grand_total - (float)$pur->paid_amount);
                                $hasReturns = $pur->returns()->exists();
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 font-bold whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($pur->purchase_date)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 font-mono font-black text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                    <a href="{{ route('purchase.invoice', $pur->id) }}" class="hover:underline">{{ $pur->purchase_code }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-semibold">
                                    {{ $pur->reference_no ?: '---' }}
                                </td>
                                <td class="px-4 py-3 font-black text-slate-800 dark:text-slate-200">
                                    {{ $pur->supplier->supplier_name ?? '---' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 font-semibold">
                                    {{ $pur->warehouse->warehouse_name ?? '---' }}
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-slate-800 dark:text-white whitespace-nowrap">
                                    <x-money value="{{ $pur->grand_total }}" />
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-emerald-600 whitespace-nowrap">
                                    <x-money value="{{ $pur->paid_amount }}" />
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums {{ $due > 0 ? 'text-rose-600' : 'text-slate-400' }} whitespace-nowrap">
                                    <x-money value="{{ $due }}" />
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($pur->payment_status === 'Paid')
                                        <x-badge variant="success">Paid</x-badge>
                                    @elseif($pur->payment_status === 'Partial')
                                        <x-badge variant="warning">Partial</x-badge>
                                    @else
                                        <x-badge variant="danger">Unpaid</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-dropdown align="right" width="48">
                                        <x-slot:trigger>
                                            <button type="button" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1.5 shadow-sm">
                                                Action
                                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <x-slot:content>
                                            <a href="{{ route('purchase.invoice', $pur->id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                View Invoice
                                            </a>

                                            @if(!$hasReturns)
                                                <a href="{{ route('purchase.edit', $pur->id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    Edit Purchase
                                                </a>
                                            @else
                                                <span class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-400 cursor-not-allowed opacity-60" title="Cannot edit purchase because returns exist">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                    Edit (Returns Exist)
                                                </span>
                                            @endif

                                            <a href="{{ route('purchase.return', $pur->id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                                                Create Return
                                            </a>

                                            <a href="{{ route('purchase.barcode', $pur->id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                Print Barcode
                                            </a>

                                            @if($due > 0)
                                                <button type="button" @click="openPaymentModal({{ $pur->id }}, '{{ $pur->purchase_code }}', {{ $due }})" class="w-full text-left flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                    Record Payment
                                                </button>
                                            @endif

                                            <div class="border-t border-slate-100 dark:border-dark-border my-1"></div>

                                            <button type="button" @click="confirmDelete({{ $pur->id }}, '{{ $pur->purchase_code }}')" class="w-full text-left flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Delete Purchase
                                            </button>
                                        </x-slot:content>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        <p class="text-sm font-bold text-slate-600 dark:text-slate-400">No purchases found</p>
                                        <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or record a new purchase.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($purchases->isNotEmpty())
                        <tfoot class="bg-slate-50/60 dark:bg-slate-800/60 border-t border-slate-100 dark:border-dark-border text-xs font-black">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right uppercase tracking-wider text-slate-400">Page Totals:</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-800 dark:text-white"><x-money value="{{ $purchases->sum('grand_total') }}" /></td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600"><x-money value="{{ $purchases->sum('paid_amount') }}" /></td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-600"><x-money value="{{ max(0, $purchases->sum('grand_total') - $purchases->sum('paid_amount')) }}" /></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Pagination -->
            @if($purchases->hasPages())
                <div class="px-5 py-4 border-t border-slate-100 dark:border-dark-border flex flex-wrap justify-between items-center gap-3">
                    <p class="text-xs text-slate-400 font-semibold">
                        Showing {{ $purchases->firstItem() }} to {{ $purchases->lastItem() }} of {{ $purchases->total() }} entries
                    </p>
                    <div>
                        {{ $purchases->links() }}
                    </div>
                </div>
            @endif
        </div>

        <!-- RECORD PAYMENT MODAL -->
        <div x-show="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;" x-cloak>
            <div class="bg-white dark:bg-dark-card w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 dark:border-dark-border overflow-hidden" @click.away="showPaymentModal = false">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-dark-border flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-black text-slate-800 dark:text-white">Record Supplier Payment</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider" x-text="paymentForm.purchase_code"></p>
                    </div>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Remaining Due</label>
                        <p class="text-xl font-black text-rose-600 tabular-nums" x-text="'{{ $currencySymbol }}' + parseFloat(paymentForm.remaining_due || 0).toFixed(2)"></p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Date *</label>
                        <input type="date" x-model="paymentForm.payment_date" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Amount *</label>
                        <input type="number" step="0.01" min="0.01" :max="paymentForm.remaining_due" x-model="paymentForm.payment_amount" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Type *</label>
                        <select x-model="paymentForm.payment_type" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                            @foreach($paymentTypes as $pt)
                                <option value="{{ $pt->payment_type }}">{{ $pt->payment_type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Bank / Cash Account *</label>
                        <select x-model="paymentForm.account_id" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ format_currency($acc->balance) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payment Note</label>
                        <input type="text" x-model="paymentForm.note" placeholder="Optional reference note" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-dark-border flex justify-end gap-2">
                    <button type="button" @click="showPaymentModal = false" class="px-4 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider">
                        Cancel
                    </button>
                    <button type="button" @click="submitPayment()" :disabled="isSubmittingPayment" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 transition-all">
                        <template x-if="isSubmittingPayment">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </template>
                        <span x-text="isSubmittingPayment ? 'Saving...' : 'Confirm Payment'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        function purchaseListPage() {
            return {
                showPaymentModal: false,
                isSubmittingPayment: false,
                paymentForm: {
                    purchase_id: null,
                    purchase_code: '',
                    remaining_due: 0,
                    payment_date: '{{ date('Y-m-d') }}',
                    payment_amount: 0,
                    payment_type: 'Cash',
                    account_id: '{{ $accounts->first()?->id ?? '' }}',
                    note: ''
                },

                openPaymentModal(id, code, due) {
                    this.paymentForm.purchase_id = id;
                    this.paymentForm.purchase_code = code;
                    this.paymentForm.remaining_due = due;
                    this.paymentForm.payment_amount = due;
                    this.paymentForm.payment_date = '{{ date('Y-m-d') }}';
                    this.showPaymentModal = true;
                },

                async submitPayment() {
                    if (this.isSubmittingPayment) return;
                    if (parseFloat(this.paymentForm.payment_amount || 0) <= 0) {
                        alert('Please enter a valid payment amount.');
                        return;
                    }
                    if (!this.paymentForm.account_id) {
                        alert('Please select a payment account.');
                        return;
                    }

                    this.isSubmittingPayment = true;
                    try {
                        const url = '{{ url('purchase') }}/' + this.paymentForm.purchase_id + '/payment';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.paymentForm)
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Payment recording failed.');
                        }
                    } catch (e) {
                        alert('An unexpected error occurred while processing payment.');
                    } finally {
                        this.isSubmittingPayment = false;
                    }
                },

                async confirmDelete(id, code) {
                    if (!confirm('Are you sure you want to delete purchase ' + code + '? This will reverse all stock and ledger entries.')) {
                        return;
                    }

                    try {
                        const url = '{{ url('purchase/delete') }}/' + id;
                        const res = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to delete purchase.');
                        }
                    } catch (e) {
                        alert('An unexpected error occurred while deleting.');
                    }
                },

                copyTableData() {
                    const table = document.getElementById('purchaseTable');
                    let text = '';
                    for (let row of table.rows) {
                        let rowData = [];
                        for (let cell of row.cells) {
                            rowData.push(cell.innerText.trim().replace(/\s+/g, ' '));
                        }
                        text += rowData.join('\t') + '\n';
                    }
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Purchase list copied to clipboard!');
                    });
                },

                exportCsv() {
                    const table = document.getElementById('purchaseTable');
                    let csv = [];
                    for (let row of table.rows) {
                        let rowData = [];
                        for (let cell of row.cells) {
                            rowData.push('"' + cell.innerText.trim().replace(/"/g, '""').replace(/\s+/g, ' ') + '"');
                        }
                        csv.push(rowData.join(','));
                    }
                    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'purchases_' + new Date().toISOString().slice(0, 10) + '.csv';
                    a.click();
                }
            };
        }
    </script>
</x-app-layout>