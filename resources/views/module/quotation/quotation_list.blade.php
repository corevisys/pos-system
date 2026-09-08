<x-app-layout title="Quotation List">
    <div class="space-y-4">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Quotation List</h1>
                <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-bold">Quotation List</span>
                </div>
            </div>

            <a href="{{ route('quotation.new') }}" class="btn-primary px-4 py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Quotation
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <x-stat-card 
                label="Total Quotations" 
                :value="number_format($stats['total_quotations'])"
                iconBg="bg-primary-50 text-primary-600 dark:bg-primary/10 dark:text-primary-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Total Quoted Value" 
                :value="format_currency($stats['total_amount'])"
                iconBg="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <x-slot:icon>
                    <span class="text-base font-black italic">{{ $currencySymbol }}</span>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Active / In Pipeline" 
                :value="number_format($quotations->total() > 0 ? $quotations->total() : $stats['total_quotations'])"
                iconBg="bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <!-- FILTER BAR & ACTIVE FILTER CHIPS -->
        <div class="card p-3 space-y-2.5">
            <form id="filterForm" action="{{ route('quotation.list') }}" method="GET" class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-2.5">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <!-- Warehouse Filter -->
                    <div class="w-full sm:w-56">
                        <x-searchable-select 
                            name="warehouse_id" 
                            :options="$warehouses" 
                            labelKey="warehouse_name" 
                            valueKey="id" 
                            emptyOption="All Warehouses" 
                            emptyValue="" 
                            placeholder="All Warehouses" 
                            :value="request('warehouse_id')" 
                            change="document.getElementById('filterForm')?.submit()" />
                    </div>

                    <!-- Search Input -->
                    <div class="relative flex-1 sm:max-w-xs">
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Search code, reference, customer..." 
                            class="input-base !py-1.5 !pl-8 !pr-3 !text-xs !rounded-xl w-full">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-secondary dark:text-dark-text/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <button type="submit" class="btn-primary !py-1.5 !px-3 text-xs font-bold">
                        Filter
                    </button>
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-border-light dark:border-dark-border">
                    <!-- Per Page Limit -->
                    <div class="flex items-center gap-1.5">
                        <label class="text-[9px] font-black text-text-secondary uppercase tracking-wider">Show</label>
                        <select 
                            name="per_page" 
                            onchange="this.form.submit()" 
                            class="input-base !w-auto !py-1 !px-2 !text-xs !rounded-lg">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                </div>
            </form>

            <!-- Active filter chips row -->
            @if(request('warehouse_id') || request('search'))
                <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-border-light dark:border-dark-border">
                    <span class="text-[9px] font-black uppercase tracking-wider text-text-secondary mr-1">Active Filters:</span>
                    
                    @if(request('warehouse_id'))
                        @php
                            $selectedWh = $warehouses->firstWhere('id', request('warehouse_id'));
                        @endphp
                        <x-badge color="primary">
                            <span class="flex items-center gap-1">
                                Warehouse: {{ $selectedWh->warehouse_name ?? request('warehouse_id') }}
                                <a href="{{ request()->fullUrlWithQuery(['warehouse_id' => null]) }}" class="hover:text-primary-800 font-bold ml-0.5" title="Remove filter">×</a>
                            </span>
                        </x-badge>
                    @endif

                    @if(request('search'))
                        <x-badge color="primary">
                            <span class="flex items-center gap-1">
                                Search: {{ request('search') }}
                                <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="hover:text-primary-800 font-bold ml-0.5" title="Remove filter">×</a>
                            </span>
                        </x-badge>
                    @endif

                    <a href="{{ route('quotation.list') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline ml-2">
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- QUOTATIONS TABLE -->
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Date / Expiry</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Quotation Code</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Customer</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Status</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot:thead>

            @forelse($quotations as $quot)
                @php
                    $effectiveStatus = $quot->effective_status;
                    $isExpired = $quot->isExpired();
                @endphp
                <tr class="hover:bg-slate-50/60 dark:hover:bg-dark-bg/60 transition-colors">
                    <!-- Date & Expiry -->
                    <td class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-text-primary dark:text-dark-text">{{ $quot->quotation_date }}</span>
                            @if($isExpired)
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-600 dark:text-amber-400 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>
                                    Expired ({{ $quot->expire_date }})
                                </span>
                            @elseif($quot->expire_date)
                                <span class="text-[9px] text-text-secondary dark:text-dark-text/60 mt-0.5">
                                    Exp: {{ $quot->expire_date }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <!-- Code & Ref -->
                    <td class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <a href="{{ route('quotation.invoice', $quot->id) }}" class="text-xs font-black text-primary hover:underline font-mono tracking-wide">
                                {{ $quot->quotation_code }}
                            </a>
                            @if($quot->reference_no)
                                <span class="text-[9px] text-text-secondary dark:text-dark-text/60">
                                    Ref: {{ $quot->reference_no }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <!-- Customer -->
                    <td class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-text-primary dark:text-dark-text">
                                {{ $quot->customer->customer_name ?? 'Walk-in Customer' }}
                            </span>
                            @if($quot->customer && $quot->customer->mobile)
                                <span class="text-[9px] text-text-secondary dark:text-dark-text/60">
                                    {{ $quot->customer->mobile }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <!-- Status -->
                    <td class="px-4 py-2.5 text-center">
                        <div class="flex flex-col items-center gap-1">
                            @if($effectiveStatus === 'Converted')
                                <x-badge color="primary">Converted</x-badge>
                                @if($quot->sale)
                                    <a href="{{ route('sales.invoice', $quot->sale->id) }}" class="text-[9px] font-black text-primary hover:underline flex items-center gap-0.5">
                                        <span>Sale: {{ $quot->sale->sales_code }}</span>
                                    </a>
                                @endif
                            @elseif($effectiveStatus === 'Accepted')
                                <x-badge color="success">Accepted</x-badge>
                            @elseif($effectiveStatus === 'Rejected')
                                <x-badge color="danger">Rejected</x-badge>
                            @elseif($effectiveStatus === 'Expired')
                                <x-badge color="warning">Expired</x-badge>
                            @else
                                <x-badge color="neutral">Quoted</x-badge>
                            @endif
                        </div>
                    </td>

                    <!-- Total -->
                    <td class="px-4 py-2.5 text-right font-black tabular-nums text-xs text-text-primary dark:text-dark-text">
                        {{ format_currency($quot->grand_total) }}
                    </td>

                    <!-- Action Dropdown -->
                    <td class="px-4 py-2.5 text-center">
                        <x-dropdown align="right" width="44" contentClasses="py-1">
                            <x-slot:trigger>
                                <button type="button" class="btn-secondary !py-1 !px-2.5 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot:trigger>

                            <x-slot:content>
                                <a href="{{ route('quotation.invoice', $quot->id) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-text-primary dark:text-dark-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    View Invoice
                                </a>

                                @if($quot->isConverted() && $quot->sale)
                                    <a href="{{ route('sales.invoice', $quot->sale->id) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary-50 dark:hover:bg-primary/10 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        View Sale
                                    </a>
                                @endif

                                @if(!$quot->isConverted())
                                    <a href="{{ route('quotation.edit', $quot->id) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-text-primary dark:text-dark-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        Edit Quotation
                                    </a>

                                    <form action="{{ route('quotation.delete', $quot->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete quotation {{ $quot->quotation_code }}?');" class="border-t border-border-light dark:border-dark-border mt-1 pt-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full text-left flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors">
                                            <svg class="w-3.5 h-3.5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </x-slot:content>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-xs text-text-secondary italic">
                        No quotations found matching your criteria.
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- PAGINATION -->
        @if($quotations->hasPages())
            <div class="card p-3 flex flex-wrap justify-between items-center gap-3">
                <p class="text-xs text-text-secondary">
                    Showing {{ $quotations->firstItem() ?? 0 }} to {{ $quotations->lastItem() ?? 0 }} of {{ $quotations->total() }} entries
                </p>
                <div>
                    {{ $quotations->links() }}
                </div>
            </div>
        @endif

    </div>
</x-app-layout>