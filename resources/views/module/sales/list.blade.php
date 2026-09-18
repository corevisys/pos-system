<x-app-layout title="Sales List">
    <div>
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Sales List</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px]">Dashboard</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold">Sales List</span>
                </div>
            </div>
            <a href="{{ route('sales.add') }}" class="px-3 py-2 bg-rose-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Sales
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                <div class="flex justify-between items-start mb-2">
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    </div>
                </div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Invoices</p>
                <h3 class="text-base font-black dark:text-white">{{ number_format($stats['total_invoices']) }}</h3>
            </div>
            
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                <div class="flex justify-between items-start mb-2">
                    <div class="p-2 rounded-xl bg-blue-500/10 text-blue-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Amount</p>
                <h3 class="text-base font-black dark:text-white"><x-money value="{{ $stats['total_amount'] }}" /></h3>
            </div>

            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                <div class="flex justify-between items-start mb-2">
                    <div class="p-2 rounded-xl bg-purple-500/10 text-purple-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Received</p>
                <h3 class="text-base font-black dark:text-white"><x-money value="{{ $stats['total_paid'] }}" /></h3>
            </div>

            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                <div class="flex justify-between items-start mb-2">
                    <div class="p-2 rounded-xl bg-orange-500/10 text-orange-500 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Sales Due</p>
                <h3 class="text-base font-black dark:text-white"><x-money value="{{ $stats['total_due'] }}" /></h3>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            
            <!-- FILTERS SECTION -->
            <div class="bg-white dark:bg-dark-card p-2 md:p-3 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm">
                <form id="filterForm" action="{{ route('sales.list') }}" method="GET" x-ref="filterForm" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-2 md:gap-3">
                    <!-- Warehouse -->
                    <div class="space-y-0.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Warehouse</label>
                        <x-searchable-select name="warehouse_id" :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="All Warehouses" emptyValue="" placeholder="All Warehouses" :value="request('warehouse_id')" change="document.getElementById('filterForm')?.submit()" />
                    </div>

                    <!-- Customers (Searchable) -->
                    <div class="space-y-0.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Customers</label>
                        <x-searchable-select name="customer_id" :options="$customers" labelKey="customer_name" valueKey="id" subtextKey="mobile" emptyOption="All Customers" emptyValue="" placeholder="All Customers" :value="request('customer_id')" change="document.getElementById('filterForm')?.submit()" />
                    </div>

                    <!-- Users (Searchable) -->
                    <div class="space-y-0.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Users</label>
                        <x-searchable-select name="created_by" :options="$users" labelKey="name" valueKey="id" emptyOption="All Users" emptyValue="" placeholder="All Users" :value="request('created_by')" change="document.getElementById('filterForm')?.submit()" />
                    </div>

                    <!-- From Date -->
                    <div class="space-y-0.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1 px-3 text-[10px] font-bold transition-all outline-none focus:ring-1 focus:ring-primary-500">
                    </div>

                    <!-- To Date -->
                    <div class="space-y-0.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1 px-3 text-[10px] font-bold transition-all outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                </form>
            </div>

            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="p-2 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-2">
                    <div class="flex items-center gap-2">
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Show</label>
                        <select name="limit" form="filterForm" onchange="document.getElementById('filterForm')?.submit()" class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-0.5 px-1.5 text-[10px] font-bold outline-none">
                            <option value="10" {{ request('limit') == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('limit') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('limit') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex bg-slate-50 dark:bg-slate-800 rounded-lg p-0.5 border border-slate-200 dark:border-dark-border">
                            <button type="submit" form="filterForm" formaction="{{ route('sales.list', ['export' => 'csv']) }}" class="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                            <button type="submit" form="filterForm" formaction="{{ route('sales.list', ['export' => 'csv']) }}" class="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</button>
                            <button type="submit" form="filterForm" formaction="{{ route('sales.list', ['export' => 'pdf']) }}" class="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">PDF</button>
                            <button type="submit" form="filterForm" formaction="{{ route('sales.list', ['export' => 'print']) }}" class="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Print</button>
                        </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative group">
                            <input type="text" name="search" form="filterForm" value="{{ request('search') }}" placeholder="Search Code/Ref..." class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-lg py-1 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40">
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <button type="submit" form="filterForm" class="p-1 px-3 bg-primary-600 text-white rounded-lg text-[9px] font-black shadow-md shadow-primary-200/50">Filter</button>
                    </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Date</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Due Date</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Sales Code</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Ref No.</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Customer</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Total ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Paid ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Created By</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @foreach($sales as $sale)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-4 py-2 text-[10px] font-bold">{{ date('d-m-Y', strtotime($sale->sales_date)) }}</td>
                                    <td class="px-4 py-2 text-[10px] font-bold">{{ $sale->due_date ? date('d-m-Y', strtotime($sale->due_date)) : 'N/A' }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex flex-col gap-1">
                                            @if($sale->emi)
                                                <a href="{{ route('sales.emi.show', $sale->emi->id) }}" class="text-[10px] font-black text-primary-600 hover:underline hover:text-primary-700 transition-all cursor-pointer">
                                                    {{ $sale->sales_code }}
                                                </a>
                                            @else
                                                <a href="{{ route('sales.show', $sale->id) }}" class="text-[10px] font-black text-primary-600 hover:underline hover:text-primary-700 transition-all cursor-pointer">
                                                    {{ $sale->sales_code }}
                                                </a>
                                            @endif
                                            @php
                                                $allSerials = $sale->serials;
                                            @endphp
                                            @if($allSerials->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($allSerials->take(3) as $serial)
                                                        <span class="text-[7px] font-black uppercase bg-slate-50 text-slate-500 px-1 py-0.25 rounded border border-slate-100">{{ $serial->serial_number }}</span>
                                                    @endforeach
                                                    @if($allSerials->count() > 3)
                                                        <span class="text-[7px] font-black text-slate-400">+{{ $allSerials->count() - 3 }} more</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-[10px] font-medium text-slate-500">{{ $sale->reference_no ?? 'N/A' }}</td>
                                    <td class="px-4 py-2 text-[10px] font-bold">{{ $sale->customer->customer_name ?? 'Walk-in customer' }}</td>
                                    <td class="px-4 py-2 text-[10px] font-black text-right tabular-nums"><x-money value="{{ $sale->grand_total }}" /></td>
                                    <td class="px-4 py-2 text-[10px] font-black text-right tabular-nums text-emerald-600"><x-money value="{{ $sale->paid_amount }}" /></td>
                                    <td class="px-4 py-2 text-center">
                                        <div class="flex flex-col gap-1 items-center">
                                            @php
                                                $netRaw = ((float)$sale->grand_total - (float)$sale->returns->sum('grand_total'))
                                                    - ((float)$sale->paid_amount - (float)$sale->returns->sum('paid_amount'));
                                                $creditAmt = max(0, -$netRaw);
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-full text-[7.5px] font-black uppercase tracking-widest
                                                @if($sale->payment_status === 'Paid') bg-emerald-50 text-emerald-600
                                                @elseif($sale->payment_status === 'Partial') bg-orange-50 text-orange-600
                                                @else bg-rose-50 text-rose-600 @endif">
                                                {{ $sale->payment_status }}
                                            </span>
                                            @if($sale->return_bit)
                                                <span class="px-2 py-0.5 rounded-full text-[7.5px] font-black uppercase tracking-widest bg-rose-100 text-rose-700 border border-rose-200">
                                                    Returned
                                                </span>
                                            @endif
                                            @if($sale->return_bit)
                                                @php
                                                    $retQtys = $sale->returnItems ? $sale->returnItems->groupBy('item_id')->map->sum('return_qty') : collect();
                                                    $retReturnable = $sale->items->sum(function ($it) use ($retQtys) {
                                                        return max(0, (float)$it->sales_qty - (float)($retQtys[$it->item_id] ?? 0));
                                                    });
                                                @endphp
                                                @if($retReturnable > 0)
                                                    <span class="px-2 py-0.5 rounded-full text-[7.5px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" title="Total remaining returnable quantity across items">
                                                        Returnable: {{ format_quantity($retReturnable) }}
                                                    </span>
                                                @endif
                                            @endif
                                            @if($creditAmt > 0)
                                                <span class="px-2 py-0.5 rounded-full text-[7.5px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200" title="Customer is overpaid by this amount after returns/refunds">
                                                    Credit <x-money value="{{ $creditAmt }}" />
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-[9px] font-black uppercase text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                                            {{ $sale->user->name ?? 'System' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <x-dropdown align="right" width="w-40">
                                            <x-slot name="trigger">
                                                <button class="flex items-center gap-1 px-2 py-0.5 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-primary-50 hover:text-primary-600 transition-all border border-slate-200 dark:border-dark-border group">
                                                    Action
                                                    <svg class="w-2.5 h-2.5 group-hover:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>
                                            </x-slot>

                                            <x-slot name="content">
                                                <div class="p-1">
                                                    <!-- View Sales -->
                                                    @if($sale->emi)
                                                        <a href="{{ route('sales.emi.show', $sale->emi->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542 7z"></path></svg>
                                                            View sales
                                                        </a>
                                                    @else
                                                        <a href="{{ route('sales.show', $sale->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542 7z"></path></svg>
                                                            View sales
                                                        </a>
                                                    @endif

                                                    <!-- Edit -->
                                                    <a href="{{ route('sales.pos') }}?sale_id={{ $sale->id }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        Edit
                                                    </a>

                                                     <!-- View Payments -->
                                                     <a href="{{ route('sales.payments', ['search' => $sale->sales_code]) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                         <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                         View Payments
                                                     </a>

                                                     <!-- Receive Payments -->
                                                     @if($sale->payment_status !== 'Paid')
                                                     <a href="{{ route('sales.payments.receive', $sale->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-emerald-600 dark:text-emerald-400 transition-colors block font-bold">
                                                         <svg class="w-3.5 h-3.5 text-emerald-500 group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                         Receive Payments
                                                     </a>
                                                     @endif

                                                    <!-- PDF -->
                                                    <a href="{{ route('sales.invoice', ['id' => $sale->id, 'mode' => 'pdf']) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                        PDF
                                                    </a>

                                                    <!-- POS Invoice -->
                                                    <a href="{{ route('sales.invoice', $sale->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                        POS Invoice
                                                    </a>

                                                    <!-- Sales Return -->
                                                    @php
                                                        $returnedItemQtys = $sale->returnItems ? $sale->returnItems->groupBy('item_id')->map->sum('return_qty') : collect();
                                                        $canReturn = $sale->items->contains(function ($item) use ($returnedItemQtys) {
                                                            $alreadyReturned = (float) ($returnedItemQtys[$item->item_id] ?? 0);
                                                            return ((float) $item->sales_qty - $alreadyReturned) > 0.0001;
                                                        });
                                                    @endphp
                                                    @if($canReturn)
                                                    <a href="{{ route('sales.return.create', $sale->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary-600 group rounded-md text-slate-700 dark:text-slate-300 transition-colors block">
                                                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M16 15v4a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2h4m8 0V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h.01M8 20h.01M11 20h.01M14 20h.01M17 20h.01M4 16H4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z"></path></svg>
                                                        Sales Return
                                                    </a>
                                                    @endif

                                                    <div class="border-t border-slate-50 dark:border-dark-border my-1"></div>

                                                    <!-- Delete -->
                                                    <form action="{{ route('sales.delete', $sale->id) }}" method="POST" onsubmit="return confirm('Restore stock and delete this sale?')" class="block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-full flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-rose-50 hover:text-rose-600 group transition-colors text-start rounded-md text-slate-700 dark:text-slate-300">
                                                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </x-slot>
                                        </x-dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50/80 dark:bg-slate-800/80">
                            <tr class="font-black text-[9px] uppercase tracking-widest text-slate-500">
                                <td colspan="5" class="px-4 py-3 text-right">Total Summary</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-900 dark:text-white"><x-money value="{{ $filteredStats['total_amount'] }}" /></td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600"><x-money value="{{ $filteredStats['total_paid'] }}" /></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5">
                    {{ $sales->appends(request()->all())->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>