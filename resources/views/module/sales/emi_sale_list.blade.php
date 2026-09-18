<x-app-layout title="EMI Sales List">
    <div>

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">EMI Sale List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold">EMI Sale List</span>
                </div>
            </div>
            <a href="{{ route('sales.add') }}" class="btn-danger !px-4 !py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Sales
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
            <x-stat-card label="Invoices" :value="number_format($globalStats['total_invoices'])"
                iconBg="bg-success-light text-success"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>' />
            <x-stat-card label="Total Loan" :money="true" :value="$globalStats['total_loan']"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
            <x-stat-card label="Total Payable" :money="true" :value="$globalStats['total_payable']"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
            <x-stat-card label="Outstanding" :money="true" :value="$globalStats['total_due']"
                iconBg="bg-warning-light text-warning"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
            <x-stat-card label="Overdue Installments" :value="number_format($globalStats['total_overdue'])"
                iconBg="bg-danger-light text-danger"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' />
        </div>

        <!-- FILTERS SECTION -->
        <form id="emiFilterForm" x-ref="filterForm" action="{{ route('sales.emi.list') }}" method="GET" class="card p-3 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                <div class="space-y-0.5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block px-0.5">Warehouse</label>
                    <x-searchable-select name="warehouse_id" :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="All Warehouses" emptyValue="" placeholder="All Warehouses" :value="request('warehouse_id')" change="document.getElementById('emiFilterForm')?.submit()" class="w-full" />
                </div>

                <div class="space-y-0.5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block px-0.5">Customers</label>
                    <x-searchable-select name="customer_id" :options="$customers" labelKey="customer_name" valueKey="id" subtextKey="mobile" emptyOption="All Customers" emptyValue="" placeholder="All Customers" :value="request('customer_id')" change="document.getElementById('emiFilterForm')?.submit()" class="w-full" />
                </div>

                <div class="space-y-0.5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block px-0.5">Users</label>
                    <x-searchable-select name="created_by" :options="$users" labelKey="name" valueKey="id" emptyOption="All Users" emptyValue="" placeholder="All Users" :value="request('created_by')" change="document.getElementById('emiFilterForm')?.submit()" class="w-full" />
                </div>

                <div class="space-y-0.5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block px-0.5">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="input-base !w-full !py-1.5 !text-[10px]">
                </div>

                <div class="space-y-0.5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block px-0.5">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="input-base !w-full !py-1.5 !text-[10px]">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1 !px-3 !py-1.5 text-[10px] font-black uppercase tracking-widest">Filter</button>
                    <a href="{{ route('sales.emi.list') }}" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-text-secondary dark:text-dark-text rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">Clear</a>
                </div>
            </div>
        </form>

        <!-- Active filter chips -->
        @if(request('warehouse_id') || request('customer_id') || request('created_by') || request('from_date') || request('to_date') || request('search'))
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @if(request('warehouse_id'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            Warehouse: {{ $warehouses->firstWhere('id', request('warehouse_id'))->warehouse_name ?? request('warehouse_id') }}
                            <a href="{{ request()->fullUrlWithQuery(['warehouse_id' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                @if(request('customer_id'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            Customer: {{ $customers->firstWhere('id', request('customer_id'))->customer_name ?? request('customer_id') }}
                            <a href="{{ request()->fullUrlWithQuery(['customer_id' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                @if(request('created_by'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            User: {{ $users->firstWhere('id', request('created_by'))->name ?? request('created_by') }}
                            <a href="{{ request()->fullUrlWithQuery(['created_by' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                @if(request('from_date'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            From: {{ request('from_date') }}
                            <a href="{{ request()->fullUrlWithQuery(['from_date' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                @if(request('to_date'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            To: {{ request('to_date') }}
                            <a href="{{ request()->fullUrlWithQuery(['to_date' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                @if(request('search'))
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            Search: {{ request('search') }}
                            <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
                        </span>
                    </x-badge>
                @endif
                <a href="{{ route('sales.emi.list') }}" class="text-[9px] font-black uppercase tracking-widest text-text-muted hover:text-danger transition-colors">Clear All</a>
            </div>
        @endif

        <!-- TABLE CONTROLS (per-page, export, search) -->
        <div class="card p-3 mb-4 flex flex-wrap justify-between items-center gap-2">
            <div class="flex items-center gap-2">
                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Show</label>
                <select name="limit" onchange="window.location.href = '{{ $emiSales->url(1) }}' + (this.value ? '&limit=' + this.value : '')"
                    class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                    <option value="10" {{ request('limit', 10) == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('limit', 10) == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('limit', 10) == 50 ? 'selected' : '' }}>50</option>
                </select>
                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Entries</label>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex bg-background dark:bg-slate-800 rounded-lg p-0.5 border border-border-light dark:border-dark-border">
                    <button type="button" onclick="navigator.clipboard?.writeText(window.location.href)" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">PDF</a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Print</a>
                </div>
                <form action="{{ route('sales.emi.list') }}" method="GET" class="flex items-center gap-2">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button type="submit" class="px-3 py-1.5 bg-primary-600 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all">Search</button>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <x-table title="EMI Sales">
            <x-slot name="thead">
                <tr>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Start Date</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Sales Code</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Loan Amount</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Total Payable</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Remaining</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Installments Paid</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot>

            @forelse($emiSales as $emi)
                @php
                    $paidCount = $emi->schedule->where('status', 'Paid')->count();
                    $overdueCount = $emi->schedule
                        ->filter(fn($s) => $s->status !== 'Paid' && $s->due_date < now()->toDateString())
                        ->count();
                    $remaining = (float) $emi->total_payable - (float) $emi->schedule->sum('paid_amount');
                @endphp
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-6 py-3 text-[10px] font-bold text-text-secondary">{{ \Carbon\Carbon::parse($emi->start_date)->format('d-m-Y') }}</td>
                    <td class="px-6 py-3">
                        <a href="{{ route('sales.emi.show', $emi->id) }}" class="text-[10px] font-black text-primary hover:underline hover:text-primary-hover transition-all">
                            {{ $emi->sale->sales_code }}
                        </a>
                    </td>
                    <td class="px-6 py-3 text-[10px] font-bold text-text-primary dark:text-dark-text">
                        {{ $emi->customer->customer_name }}
                        <div class="text-[8px] text-text-muted">{{ $emi->customer->mobile }}</div>
                    </td>
                    <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums"><x-money value="{{ $emi->loan_amount }}" /></td>
                    <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums text-primary"><x-money value="{{ $emi->total_payable }}" /></td>
                    <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums {{ $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}"><x-money value="{{ $remaining }}" /></td>
                    <td class="px-6 py-3 text-center">
                        <x-badge color="{{ $paidCount == $emi->duration_months ? 'success' : 'neutral' }}">{{ $paidCount }}/{{ $emi->duration_months }}</x-badge>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($overdueCount > 0)
                            <x-badge color="danger">Overdue ({{ $overdueCount }})</x-badge>
                        @elseif($emi->status == 'Completed')
                            <x-badge color="success">Completed</x-badge>
                        @else
                            <x-badge color="warning">{{ $emi->status }}</x-badge>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-center">
                        <x-dropdown align="right" width="w-40">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-1 px-2 py-0.5 bg-background dark:bg-slate-800/50 text-text-secondary dark:text-dark-text rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-primary-50 hover:text-primary transition-all border border-border-light dark:border-dark-border group">
                                    Action
                                    <svg class="w-2.5 h-2.5 group-hover:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="p-1">
                                    <a href="{{ route('sales.emi.show', $emi->id) }}" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary group rounded-md text-text-secondary dark:text-dark-text transition-colors block">
                                        <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542 7z"></path></svg>
                                        View Details
                                    </a>

                                    <a href="{{ route('sales.emi.show', $emi->id) }}#schedule" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-emerald-50 hover:text-emerald-600 group rounded-md text-text-secondary dark:text-dark-text transition-colors block">
                                        <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Pay Installment
                                    </a>

                                    <a href="{{ route('sales.invoice', $emi->sale->id) }}" target="_blank" class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-tight py-1.5 px-3 hover:bg-primary-50 hover:text-primary group rounded-md text-text-secondary dark:text-dark-text transition-colors block">
                                        <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Print Invoice
                                    </a>
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-10 text-text-muted text-xs font-bold uppercase tracking-widest">No EMI Sales Found</td>
                </tr>
            @endforelse
        </x-table>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $emiSales->links() }}
        </div>
    </div>
</x-app-layout>
