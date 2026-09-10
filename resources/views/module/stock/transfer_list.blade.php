<x-app-layout title="Stock Transfer List">
    <div x-data="transferListPage()" class="space-y-4">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Stock Transfer List</h1>
                <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-bold">Transfer List</span>
                </div>
            </div>

            <a href="{{ route('stock.transfer.create') }}" class="btn-primary px-4 py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Transfer
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <x-stat-card label="Total Transfers" :value="number_format($transfers->total())">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Items Moved (page)" :value="number_format($transfers->getCollection()->sum(fn ($t) => $t->items->count()))">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Total Qty Moved (page)" :value="format_quantity($transfers->getCollection()->sum(fn ($t) => $t->items->sum('transfer_qty')))">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 .5.5 1 1 1h14c.5 0 1-.5 1-1V7c0-.5-.5-1-1-1H5c-.5 0-1 .5-1 1z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <!-- FILTER BAR & ACTIVE FILTER CHIPS -->
        <div class="card p-3 space-y-2.5">
            <form id="transferFilterForm" action="{{ route('stock.transfer') }}" method="GET" class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-2.5">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <!-- From Warehouse Filter -->
                    <div class="w-full sm:w-48">
                        <x-searchable-select
                            name="warehouse_from"
                            :options="$warehouses"
                            labelKey="warehouse_name"
                            valueKey="id"
                            emptyOption="All Sources"
                            emptyValue=""
                            placeholder="All Sources"
                            :value="request('warehouse_from')"
                            change="document.getElementById('transferFilterForm')?.submit()" />
                    </div>

                    <!-- To Warehouse Filter -->
                    <div class="w-full sm:w-48">
                        <x-searchable-select
                            name="warehouse_to"
                            :options="$warehouses"
                            labelKey="warehouse_name"
                            valueKey="id"
                            emptyOption="All Destinations"
                            emptyValue=""
                            placeholder="All Destinations"
                            :value="request('warehouse_to')"
                            change="document.getElementById('transferFilterForm')?.submit()" />
                    </div>

                    <!-- Search Input -->
                    <div class="relative flex-1 sm:max-w-xs">
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search reference, note, warehouse..."
                            class="input-base !py-1.5 !pl-8 !pr-3 !text-xs !rounded-xl w-full">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-secondary dark:text-dark-text/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <button type="submit" class="btn-primary !py-1.5 !px-3 text-xs font-bold">Filter</button>
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-border-light dark:border-dark-border">
                    <!-- Export Buttons (real: CSV/Excel + print/PDF) -->
                    <div class="flex items-center gap-1.5">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-secondary !py-1 !px-2.5 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 5H7a2 2 0 01-2-2V6a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V16a2 2 0 01-2 2z"></path></svg>
                            Excel
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'print']) }}" target="_blank" class="btn-secondary !py-1 !px-2.5 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            PDF
                        </a>
                    </div>

                    <!-- Per Page Limit -->
                    <div class="flex items-center gap-1.5">
                        <label class="text-[9px] font-black text-text-secondary uppercase tracking-wider">Show</label>
                        <select name="per_page" onchange="this.form.submit()" class="input-base !w-auto !py-1 !px-2 !text-xs !rounded-lg">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                </div>
            </form>

            <!-- Active filter chips row -->
            @if(request('warehouse_from') || request('warehouse_to') || request('search'))
                <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-border-light dark:border-dark-border">
                    <span class="text-[9px] font-black uppercase tracking-wider text-text-secondary mr-1">Active Filters:</span>

                    @if(request('warehouse_from'))
                        @php $selFrom = $warehouses->firstWhere('id', request('warehouse_from')); @endphp
                        <x-badge color="primary">
                            <span class="flex items-center gap-1">
                                From: {{ $selFrom->warehouse_name ?? request('warehouse_from') }}
                                <a href="{{ request()->fullUrlWithQuery(['warehouse_from' => null]) }}" class="hover:text-primary-800 font-bold ml-0.5">×</a>
                            </span>
                        </x-badge>
                    @endif

                    @if(request('warehouse_to'))
                        @php $selTo = $warehouses->firstWhere('id', request('warehouse_to')); @endphp
                        <x-badge color="primary">
                            <span class="flex items-center gap-1">
                                To: {{ $selTo->warehouse_name ?? request('warehouse_to') }}
                                <a href="{{ request()->fullUrlWithQuery(['warehouse_to' => null]) }}" class="hover:text-primary-800 font-bold ml-0.5">×</a>
                            </span>
                        </x-badge>
                    @endif

                    @if(request('search'))
                        <x-badge color="primary">
                            <span class="flex items-center gap-1">
                                Search: {{ request('search') }}
                                <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="hover:text-primary-800 font-bold ml-0.5">×</a>
                            </span>
                        </x-badge>
                    @endif

                    <a href="{{ route('stock.transfer') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline ml-2">Clear All</a>
                </div>
            @endif
        </div>

        <!-- TRANSFERS TABLE -->
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Transfer Date</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Reference No</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">From Warehouse</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">To Warehouse</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Details</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Status</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Note</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Created by</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot:thead>

            @forelse($transfers as $tr)
                @php $isConsumed = $consumedFlags[$tr->id] ?? false; @endphp
                <tr class="hover:bg-slate-50/60 dark:hover:bg-dark-bg/60 transition-colors">
                    <td class="px-4 py-2.5">
                        <span class="text-xs font-bold text-text-primary dark:text-dark-text">{{ \Carbon\Carbon::parse($tr->transfer_date)->format('d-m-Y') }}</span>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="text-xs font-black text-primary font-mono tracking-wide">{{ $tr->reference_no }}</span>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="text-xs font-bold text-text-primary dark:text-dark-text flex items-center gap-1">
                            <svg class="w-3 h-3 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            {{ $tr->fromWarehouse->warehouse_name ?? '---' }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            {{ $tr->toWarehouse->warehouse_name ?? '---' }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <div class="flex flex-col leading-tight">
                            <span class="text-[9px] font-black text-text-secondary uppercase italic">Items: {{ $tr->items->count() }}</span>
                            <span class="text-xs font-black text-text-primary dark:text-dark-text tabular-nums">Qty: {{ format_quantity($tr->items->sum('transfer_qty')) }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        @if($isConsumed)
                            <x-badge color="warning">Partially Consumed</x-badge>
                        @else
                            <x-badge color="success">Intact</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs font-medium text-text-secondary dark:text-dark-text/70 italic max-w-[160px] truncate">{{ $tr->note ?: '---' }}</td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-primary-500 shadow-[0_0_8px_rgba(59,130,246,0.3)]"></div>
                            <span class="text-xs font-bold text-text-primary dark:text-dark-text">{{ $tr->creator->name ?? 'System' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <x-dropdown align="right" width="44" contentClasses="py-1">
                            <x-slot:trigger>
                                <button type="button" class="btn-secondary !py-1 !px-2.5 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot:trigger>

                            <x-slot:content>
                                <a href="{{ route('stock.transfer.edit', $tr->id) }}" class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-text-primary dark:text-dark-text hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Edit
                                </a>
                                <button
                                    type="button"
                                    @click="openDeleteModal({{ $tr->id }}, '{{ $tr->reference_no }}', {{ $isConsumed ? 'true' : 'false' }})"
                                    class="w-full text-left flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors border-t border-border-light dark:border-dark-border mt-1 pt-1">
                                    <svg class="w-3.5 h-3.5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Delete
                                </button>
                            </x-slot:content>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-xs text-text-secondary italic">
                        No transfers found matching your criteria.
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- PAGINATION -->
        @if($transfers->hasPages())
            <div class="card p-3 flex flex-wrap justify-between items-center gap-3">
                <p class="text-xs text-text-secondary">
                    Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }} entries
                </p>
                <div>
                    {{ $transfers->links() }}
                </div>
            </div>
        @endif

        <!-- DELETE CONFIRMATION MODAL (F1: surfaces WHY a delete is blocked) -->
        <x-modal name="delete-transfer-modal" maxWidth="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-text-primary dark:text-dark-text mb-2">Delete Stock Transfer</h2>
                <p class="text-sm text-text-secondary dark:text-dark-text/70">
                    Are you sure you want to delete transfer <strong x-text="deleteTransferRef"></strong>?
                </p>
                <p x-show="deleteTransferConsumed" class="mt-2 text-xs font-semibold text-amber-600 dark:text-amber-400">
                    ⚠ This transfer's destination stock appears to have been partially sold or moved.
                    Deletion may be blocked by the system to protect inventory.
                </p>
                <p x-show="deleteError" class="mt-2 text-xs font-semibold text-danger" x-text="deleteError"></p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', { name: 'delete-transfer-modal' })" class="btn-secondary px-4 py-2 text-xs font-bold">
                        Cancel
                    </button>
                    <button type="button" @click="confirmDeleteTransfer" class="btn-danger px-4 py-2 text-xs font-bold" x-text="deleteBusy ? 'Deleting...' : 'Delete Transfer'"></button>
                </div>
            </div>
        </x-modal>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transferListPage', () => ({
                deleteTransferId: null,
                deleteTransferRef: '',
                deleteTransferConsumed: false,
                deleteBusy: false,
                deleteError: '',

                openDeleteModal(id, ref, consumed) {
                    this.deleteTransferId = id;
                    this.deleteTransferRef = ref;
                    this.deleteTransferConsumed = !!consumed;
                    this.deleteError = '';
                    this.$dispatch('open-modal', { name: 'delete-transfer-modal' });
                },

                async confirmDeleteTransfer() {
                    if (!this.deleteTransferId || this.deleteBusy) return;
                    this.deleteBusy = true;
                    this.deleteError = '';

                    try {
                        const response = await fetch(`/stock/transfer/${this.deleteTransferId}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.$dispatch('close-modal', { name: 'delete-transfer-modal' });
                            showSuccess(data.message || 'Transfer deleted successfully!');
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            // Surface WHY it's blocked (Phase A guards) right in the modal.
                            this.deleteError = data.message || 'Failed to delete transfer.';
                        }
                    } catch (err) {
                        this.deleteError = 'An unexpected error occurred while deleting.';
                    } finally {
                        this.deleteBusy = false;
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
