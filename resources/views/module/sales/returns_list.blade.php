<x-app-layout title="Sales Returns List">
    <div x-data="{
        deleteAction: '',
        deleteId: null,
        openDelete(id, url) {
            this.deleteId = id;
            this.deleteAction = url;
            $dispatch('open-modal', 'confirm-delete-return');
        }
    }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Sales Returns List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold">Sales Returns List</span>
                </div>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <x-stat-card label="Total Invoices" :value="number_format($globalStats['total_invoices'])"
                iconBg="bg-success-light text-success"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>' />
            <x-stat-card label="Return Amount" :money="true" :value="$globalStats['grand_total']"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
            <x-stat-card label="Paid Amount" :money="true" :value="$globalStats['paid_amount']"
                iconBg="bg-warning-light text-warning"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' />
            <x-stat-card label="Return Due" :money="true" :value="$globalStats['total_due']"
                iconBg="bg-danger-light text-danger"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' />
        </div>

        <div class="space-y-4">
            <!-- FILTERS SECTION -->
            <div class="card p-3 flex flex-col md:flex-row justify-between items-center gap-3">
                <form id="returnsFilterForm" action="{{ route('sales.returns') }}" method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-background dark:bg-slate-800 rounded-xl flex items-center justify-center text-text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        </div>
                        <x-searchable-select name="warehouse_id" :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="All Warehouses" emptyValue="" placeholder="All Warehouses" :value="request('warehouse_id')" change="document.getElementById('returnsFilterForm')?.submit()" class="md:w-52" />
                    </div>
                </form>

                <button type="button" @click="$dispatch('open-modal', 'new-return-picker')" class="btn-danger w-full md:w-auto !px-4 !py-2 !text-[9px] font-black uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Return
                </button>
            </div>

            <!-- Active filter chips -->
            @if(request('warehouse_id') || request('search'))
                <div class="flex flex-wrap items-center gap-2">
                    @if(request('warehouse_id'))
                        <x-badge color="primary">
                            <span class="flex items-center gap-1.5">
                                Warehouse: {{ $warehouses->firstWhere('id', request('warehouse_id'))->warehouse_name ?? request('warehouse_id') }}
                                <a href="{{ request()->fullUrlWithQuery(['warehouse_id' => null]) }}" class="hover:text-white/80" title="Clear">×</a>
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
                    <a href="{{ route('sales.returns') }}" class="text-[9px] font-black uppercase tracking-widest text-text-muted hover:text-danger transition-colors">Clear All</a>
                </div>
            @endif

            <!-- TABLE CONTROLS (per-page, export, search) -->
            <div class="card p-3 flex flex-wrap justify-between items-center gap-2">
                <div class="flex items-center gap-2">
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Show</label>
                    <select name="limit" onchange="window.location.href = '{{ $returns->url(1) }}' + (this.value ? '&limit=' + this.value : '')"
                        class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                        <option value="10" {{ request('limit', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('limit', 10) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('limit', 10) == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Entries</label>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex bg-background dark:bg-slate-800 rounded-lg p-0.5 border border-border-light dark:border-dark-border">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">PDF</a>
                        <button type="button" onclick="navigator.clipboard?.writeText(window.location.href)" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                    </div>
                    <form action="{{ route('sales.returns') }}" method="GET" class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </form>
                </div>
            </div>

            <!-- TABLE -->
            <x-table>
                <x-slot name="thead">
                    <tr>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Sale Code</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Return Code</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Return Status</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Ref No.</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Warehouse</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Total</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Paid</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Py Status</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Created By</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                    </tr>
                </x-slot>

                @foreach($returns as $r)
                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                        <td class="px-6 py-2.5 text-[10px] font-bold text-text-secondary">{{ date('d-m-Y', strtotime($r->return_date)) }}</td>
                        <td class="px-6 py-2.5">
                            @if($r->sales_id)
                                <a href="{{ route('sales.show', $r->sales_id) }}" class="text-[10px] font-black text-primary hover:underline">
                                    {{ $r->sale->sales_code ?? 'SA-#' . $r->sales_id }}
                                </a>
                                @php
                                    $sale = $r->sale;
                                    $netRaw = $sale
                                        ? ((float)$sale->grand_total - (float)$sale->returns->sum('grand_total'))
                                            - ((float)$sale->paid_amount - (float)$sale->returns->sum('paid_amount'))
                                        : 0;
                                    $creditAmt = max(0, -$netRaw);
                                @endphp
                                @if($creditAmt > 0)
                                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-[7px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200" title="Customer is overpaid by this amount after returns/refunds">
                                        Credit <x-money value="{{ $creditAmt }}" />
                                    </span>
                                @endif
                            @else
                                <span class="text-[10px] font-black text-text-muted">---</span>
                            @endif
                        </td>
                        <td class="px-6 py-2.5">
                            <x-badge color="primary">{{ $r->return_code }}</x-badge>
                        </td>
                        <td class="px-6 py-2.5 text-center">
                            <x-badge color="{{ $r->return_status === 'Completed' ? 'success' : 'warning' }}">{{ $r->return_status }}</x-badge>
                        </td>
                        <td class="px-6 py-2.5 text-[10px] font-medium text-text-muted">{{ $r->reference_no ?: '---' }}</td>
                        <td class="px-6 py-2.5 text-[10px] font-bold italic text-text-secondary">{{ $r->warehouse->warehouse_name ?? '---' }}</td>
                        <td class="px-6 py-2.5 text-[10px] font-bold text-text-primary dark:text-dark-text">{{ $r->customer->customer_name ?? 'Walk-in' }}</td>
                        <td class="px-6 py-2.5 text-[10px] font-black text-right tabular-nums"><x-money value="{{ $r->grand_total }}" /></td>
                        <td class="px-6 py-2.5 text-[10px] font-black text-right tabular-nums text-success"><x-money value="{{ $r->paid_amount }}" /></td>
                        <td class="px-6 py-2.5 text-center">
                            <x-badge color="{{ $r->payment_status === 'Paid' ? 'success' : ($r->payment_status === 'Partial' ? 'warning' : 'danger') }}">{{ $r->payment_status }}</x-badge>
                        </td>
                        <td class="px-6 py-2.5">
                            <span class="text-[9px] font-black uppercase text-text-muted bg-background dark:bg-slate-800 px-2 py-0.5 rounded-md">{{ $r->user->name ?? 'System' }}</span>
                        </td>
                        <td class="px-6 py-2.5 text-center">
                            <x-dropdown align="right" width="40" contentClasses="py-1">
                                <x-slot name="trigger">
                                    <button type="button" class="btn-secondary px-3 py-1.5 text-[10px] font-black uppercase tracking-widest">
                                        Action
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <a href="{{ route('sales.return.show', $r->id) }}" class="flex items-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-text-secondary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        View
                                    </a>
                                    <button type="button"
                                        @click="openDelete({{ $r->id }}, '{{ route('sales.return.delete', $r->id) }}')"
                                        class="flex items-center gap-2 w-full px-4 py-2 text-[10px] font-black uppercase tracking-widest text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors border-t border-border-light dark:border-dark-border mt-1 pt-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Delete
                                    </button>
                                </x-slot>
                            </x-dropdown>
                        </td>
                    </tr>
                @endforeach

            </x-table>

            <!-- Totals Summary -->
            <div class="card p-3 flex flex-wrap items-center justify-end gap-6">
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-text-muted">Total Summary</span>
                    <span class="text-sm font-black tabular-nums text-text-primary dark:text-white"><x-money value="{{ $globalStats['grand_total'] }}" /></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-text-muted">Paid</span>
                    <span class="text-sm font-black tabular-nums text-success"><x-money value="{{ $globalStats['paid_amount'] }}" /></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-text-muted">Due</span>
                    <span class="text-sm font-black tabular-nums text-danger"><x-money value="{{ $globalStats['total_due'] }}" /></span>
                </div>
            </div>

            <!-- Footer / Pagination -->
            <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
                <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                    Showing {{ $returns->firstItem() ?? 0 }} to {{ $returns->lastItem() ?? 0 }} of {{ $returns->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $returns->appends(request()->all())->links() }}
                </div>
            </div>
        </div>

        <!-- DELETE RETURN CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-return" maxWidth="sm">
            <form :action="deleteAction" method="POST">
                @csrf
                @method('DELETE')
                <div class="p-6 sm:p-8 text-center">
                    <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Delete This Return?</h2>
                    <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">
                        This will revert the returned stock, re-mark any serials as sold,
                        reverse the cash refund, and update the sale balance. This cannot be undone.
                    </p>
                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-danger w-full">Yes, Delete Return</button>
                        <button type="button" @click="$dispatch('close')" class="btn-secondary w-full">Cancel</button>
                    </div>
                </div>
            </form>
        </x-modal>

        <!-- NEW RETURN SALE PICKER MODAL -->
        <x-modal name="new-return-picker" maxWidth="lg">
            <div class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-tight">New Return — Select Sale</h3>
                    <button @click="$dispatch('close')" class="text-text-muted hover:text-danger transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto custom-scrollbar space-y-1.5">
                    @forelse($returnableSales as $s)
                        <a href="{{ route('sales.return.create', $s->id) }}"
                           class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-border-light dark:border-dark-border hover:border-primary-300 hover:bg-primary-50/50 dark:hover:bg-primary-500/10 transition-all">
                            <div class="min-w-0">
                                <p class="text-[11px] font-black text-text-primary dark:text-dark-text truncate">{{ $s->sales_code }}</p>
                                <p class="text-[9px] font-bold text-text-muted truncate">{{ $s->customer->customer_name ?? 'Walk-in' }}</p>
                            </div>
                            <span class="shrink-0 text-[9px] font-black uppercase tracking-widest text-success bg-success-light dark:bg-success/10 px-2 py-0.5 rounded-lg">Return</span>
                        </a>
                    @empty
                        <p class="text-center text-[10px] font-black text-text-muted uppercase tracking-widest py-8">
                            No sales with remaining returnable items found
                        </p>
                    @endforelse
                </div>

                <div class="pt-3 mt-3 border-t border-border-light dark:border-dark-border flex justify-end">
                    <a href="{{ route('sales.list') }}" class="text-[9px] font-black uppercase tracking-widest text-text-muted hover:text-primary transition-colors">
                        Browse all sales in Sales List →
                    </a>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
