<x-app-layout title="Hold Sales List">
    <div x-data="{
        deleteAction: '',
        deleteId: null,
        deleting: false,
        openDelete(id, url) {
            this.deleteId = id;
            this.deleteAction = url;
            $dispatch('open-modal', 'confirm-delete-hold');
        },
        confirmDelete() {
            if (this.deleting || !this.deleteAction) return;
            this.deleting = true;
            fetch(this.deleteAction, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            })
            .then(async res => {
                const data = await res.json();
                if (res.ok && data.success) {
                    $dispatch('close');
                    if (typeof window.showSuccess === 'function') {
                        window.showSuccess(data.message || 'Hold deleted');
                    }
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    if (typeof window.showError === 'function') {
                        window.showError(data.message || 'Error deleting hold');
                    }
                    $dispatch('close');
                }
            })
            .catch(() => {
                if (typeof window.showError === 'function') {
                    window.showError('Error deleting hold');
                }
                $dispatch('close');
            })
            .finally(() => { this.deleting = false; this.deleteAction = ''; });
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Hold Sales List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px]">Dashboard</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold">Hold Sales</span>
                </div>
            </div>
            <a href="{{ route('sales.pos') }}" class="btn-primary !px-3 !py-2 !text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                POS Page
            </a>
        </div>

        <div class="space-y-4">

            <!-- FILTERS SECTION -->
            <div class="card p-3 flex flex-col md:flex-row justify-between items-center gap-3">
                <form id="holdFilterForm" action="{{ route('sales.hold.list') }}" method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-background dark:bg-slate-800 rounded-xl flex items-center justify-center text-text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        </div>
                        <x-searchable-select name="warehouse_id" :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="All Warehouses" emptyValue="" placeholder="All Warehouses" :value="request('warehouse_id')" change="document.getElementById('holdFilterForm')?.submit()" class="md:w-52" />
                    </div>
                </form>
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
                    <a href="{{ route('sales.hold.list') }}" class="text-[9px] font-black uppercase tracking-widest text-text-muted hover:text-danger transition-colors">Clear All</a>
                </div>
            @endif

            <!-- TABLE CONTROLS (per-page + search) -->
            <div class="card p-3 flex flex-wrap justify-between items-center gap-2">
                <div class="flex items-center gap-2">
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Show</label>
                    <select name="limit" onchange="window.location.href = '{{ $holds->url(1) }}' + (this.value ? '&limit=' + this.value : '')"
                        class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                        <option value="10" {{ request('limit', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('limit', 10) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('limit', 10) == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Entries</label>
                </div>

                <form action="{{ route('sales.hold.list') }}" method="GET" class="relative">
                    <input type="hidden" name="limit" value="{{ request('limit', 10) }}">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Ref / Customer..." class="input-base !w-52 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </form>
            </div>

            <!-- TABLE -->
            <x-table>
                <x-slot name="thead">
                    <tr>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Held</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Reference No.</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Warehouse</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Items</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount</th>
                        <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                    </tr>
                </x-slot>

                @forelse($holds as $hold)
                    <tr class="hover:bg-background/60 dark:hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-3">
                            <div class="flex flex-col">
                                <span class="text-[11px] font-bold text-text-primary dark:text-dark-text tabular-nums">{{ date('d-m-Y', strtotime($hold->sales_date)) }}</span>
                                <!-- Age-of-hold indicator (information-hiding fix) -->
                                <span class="text-[8px] font-bold text-text-muted uppercase tracking-wider">
                                    @php
                                        $heldAt = $hold->created_at ?? $hold->sales_date;
                                        $ageLabel = '—';
                                        if ($heldAt) {
                                            $diff = \Carbon\Carbon::parse($heldAt)->diff(\Carbon\Carbon::now());
                                            if ($diff->d > 0) $ageLabel = "Held {$diff->d}d {$diff->h}h ago";
                                            elseif ($diff->h > 0) $ageLabel = "Held {$diff->h}h ago";
                                            else $ageLabel = 'Held just now';
                                        }
                                    @endphp
                                    {{ $ageLabel }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="text-[11px] font-black text-primary-600 dark:text-primary-400">{{ $hold->reference_no }}</span>
                        </td>
                        <td class="px-6 py-3 text-[11px] font-bold text-text-primary dark:text-dark-text">
                            {{ $hold->customer->customer_name ?? 'Walk-in Customer' }}
                        </td>
                        <td class="px-6 py-3">
                            @if($hold->warehouse)
                                <x-badge color="neutral">{{ $hold->warehouse->warehouse_name }}</x-badge>
                            @else
                                <x-badge color="warning">No Warehouse</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-center">
                            @if($hold->status === 'open')
                                <x-badge color="success">Open</x-badge>
                            @else
                                <x-badge color="danger">Resumed / Completed</x-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right text-[11px] font-bold text-text-secondary tabular-nums">
                            {{ format_quantity($hold->items_count) }}
                        </td>
                        <td class="px-6 py-3 text-right text-[12px] font-black text-text-primary dark:text-dark-text tabular-nums">
                            <x-money value="{{ $hold->grand_total }}" />
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($hold->status === 'open')
                                    <a href="{{ route('sales.pos') }}?hold_id={{ $hold->id }}" class="p-1.5 text-text-muted hover:text-success hover:bg-success-light dark:hover:bg-success/10 rounded-lg transition-all" title="Retrieve to POS">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    </a>
                                    <button type="button" @click="openDelete({{ $hold->id }}, '{{ route('sales.pos.hold.delete', $hold->id) }}')" class="p-1.5 text-text-muted hover:text-danger hover:bg-danger-light dark:hover:bg-danger/10 rounded-lg transition-all" title="Discard hold">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                @else
                                    <span class="text-[8px] font-black uppercase tracking-widest text-text-muted">Consumed</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-14 text-center">
                            <div class="flex flex-col items-center justify-center text-text-muted">
                                <svg class="w-12 h-12 mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                <span class="text-[10px] font-black uppercase tracking-widest">No held sales found</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-table>

            <!-- Totals Summary -->
            <div class="card p-3 flex flex-wrap items-center justify-end gap-6">
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-text-muted">Filtered Total</span>
                    <span class="text-sm font-black tabular-nums text-text-primary dark:text-white"><x-money value="{{ $filteredTotal }}" /></span>
                </div>
            </div>

            <!-- Footer / Pagination -->
            <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
                <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                    Showing {{ $holds->firstItem() ?? 0 }} to {{ $holds->lastItem() ?? 0 }} of {{ $holds->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $holds->appends(request()->all())->links() }}
                </div>
            </div>
        </div>

        <!-- DELETE HOLD CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-hold" maxWidth="sm">
            <div class="p-6 sm:p-8 text-center">
                <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </div>
                <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Discard This Held Sale?</h2>
                <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">
                    This held invoice has NOT been completed, so no stock has been reserved
                    or deducted. Discarding it simply removes the saved cart. This cannot be undone.
                </p>
                <div class="flex flex-col gap-3">
                    <button type="button" @click="confirmDelete()" :disabled="deleting" class="btn-danger w-full inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-show="deleting" class="w-3.5 h-3.5 animate-spin rounded-full border-2 border-white border-t-transparent inline-block align-middle" x-cloak></span>
                        <span x-text="deleting ? 'Discarding...' : 'Yes, Discard Hold'">Yes, Discard Hold</span>
                    </button>
                    <button type="button" @click="$dispatch('close')" :disabled="deleting" class="btn-secondary w-full">Cancel</button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
