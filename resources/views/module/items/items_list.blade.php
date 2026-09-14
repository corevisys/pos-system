<x-app-layout title="Items List">
    <div x-data="itemsListPage()">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Items List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.serial-history') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Serial History</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Items List</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                @if(auth()->user()->hasPermission('items_print_labels'))
                    <a href="{{ route('items.labels') }}" class="btn-secondary w-full md:w-auto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Labels
                    </a>
                @endif
                <a href="{{ route('items.add') }}" class="btn-primary w-full md:w-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    New Item
                </a>
                <a href="{{ route('items.service.add') }}" class="btn-secondary w-full md:w-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    New Service
                </a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form id="itemFilterForm" x-ref="filterForm" action="{{ route('items.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Show</label>
                        <select name="per_page" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="10" {{ request('per_page') == 10 || !request('per_page') ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Entries</label>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Type</label>
                        <div class="w-36">
                            <x-searchable-select name="item_type"
                                :options="[['id' => 'Items', 'label' => 'Item'], ['id' => 'Services', 'label' => 'Service']]"
                                emptyOption="All Types"
                                emptyValue=""
                                placeholder="Item type"
                                :value="request('item_type')"
                                change="submitFilters()" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Brand</label>
                        <div class="w-40">
                            <x-searchable-select name="brand_id" :options="$brands" labelKey="brand_name" valueKey="id" emptyOption="All Brands" emptyValue="" placeholder="All Brands" :value="request('brand_id')" change="submitFilters()" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Category</label>
                        <div class="w-40">
                            <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="All Categories" emptyValue="" placeholder="All Categories" :value="request('category_id')" change="submitFilters()" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="navigator.clipboard?.writeText(window.location.href)" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Copy</button>
                        <button type="submit" form="itemFilterForm" formaction="{{ route('items.list', ['export' => 'csv']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Excel</button>
                        <button type="submit" form="itemFilterForm" formaction="{{ route('items.list', ['export' => 'print']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">PDF</button>
                    </div>
                    <div class="relative group">
                        <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Item Info</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Brand/Category</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Stock</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Price ({{ $currencySymbol }})</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                </tr>
            </x-slot>

            @forelse($items as $item)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg border border-border-light dark:border-dark-border flex items-center justify-center bg-card dark:bg-dark-card overflow-hidden shrink-0">
                                @if($item->item_image)
                                    <img src="{{ asset($item->item_image) }}" alt="{{ $item->item_name }}" class="w-full h-full object-cover">
                                @else
                                    <svg class="w-4 h-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                @endif
                            </div>
                            <div class="flex flex-col min-w-0">
                                <a href="{{ route('items.show', $item->id) }}" class="text-[11px] font-bold text-text-primary dark:text-dark-text hover:text-primary transition-colors truncate block max-w-[220px]">{{ $item->item_name }}</a>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[9px] font-black text-text-muted uppercase tracking-tight">{{ $item->item_code }}</span>
                                    @if($item->is_serialized)
                                        <a href="{{ route('items.serial-history') }}" class="text-[8px] font-bold bg-primary-50 dark:bg-primary/15 text-primary dark:text-primary-300 px-1.5 py-0.5 rounded uppercase tracking-widest flex items-center gap-1 border border-primary-100/50 hover:bg-primary-100 dark:hover:bg-primary/25 transition-colors" title="View serial history">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                            Serialized: {{ $item->serials_count }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-text-secondary uppercase tracking-tight italic">{{ $item->brand->brand_name ?? '---' }}</span>
                            <span class="text-[9px] font-bold text-text-muted">{{ $item->category->category_name ?? '---' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <div class="flex flex-col leading-tight items-end">
                            @php($__avail = $item->availableStock())
                            <span class="text-[11px] font-black text-text-primary dark:text-dark-text tabular-nums">{{ $__avail }}</span>
                            @if((float) $__avail <= (float) $item->alert_qty)
                                <x-badge color="danger">Low Stock</x-badge>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ format_currency($item->sales_price) }}</span>
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        @if($item->status)
                            <x-badge color="success">Active</x-badge>
                        @else
                            <x-badge color="neutral">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        <x-dropdown align="right" width="36">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary !px-2.5 !py-1 text-[8px] flex items-center gap-1">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('items.show', $item->id)">View</x-dropdown-link>
                                <x-dropdown-link :href="route('items.edit', $item->id)">Edit</x-dropdown-link>
                                @if(auth()->user()->hasPermission('items_print_labels'))
                                    <x-dropdown-link :href="route('items.labels', ['items' => $item->id])">Print Label</x-dropdown-link>
                                @endif
                                <button type="button" @click="openDeleteModal({{ $item->id }}, '{{ addslashes($item->item_name) }}')" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-danger hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors border-t border-border dark:border-dark-border">
                                    Delete
                                </button>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center">
                        <div class="flex flex-col items-center gap-2 opacity-40">
                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-muted">No Items Found!!</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $items->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-item" maxWidth="sm">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-danger flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-wider">Delete Item</h3>
                        <p class="text-[11px] text-text-muted mt-0.5">Are you sure you want to delete <span class="font-bold text-text-primary dark:text-dark-text" x-text="deleteName"></span>?</p>
                    </div>
                </div>

                <p class="text-[11px] text-text-muted mb-6 bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-border dark:border-dark-border">
                    This will permanently remove the item and all of its variants and stock records.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'confirm-delete-item')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="button" @click="submitDelete($event.currentTarget)" class="btn-danger" :disabled="isDeleting">
                        <span x-show="!isDeleting">Delete Item</span>
                        <span x-show="isDeleting" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            Deleting...
                        </span>
                    </button>
                </div>
            </div>
        </x-modal>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('itemsListPage', () => ({
            searchTerm: @js(request('search')),
            deleteId: null,
            deleteName: '',
            isDeleting: false,
            isSubmitting: false,

            submitFilters() {
                if (this.isSubmitting) return;
                this.isSubmitting = true;
                this.$refs.filterForm.submit();
                setTimeout(() => { this.isSubmitting = false; }, 1000);
            },

            openDeleteModal(id, name) {
                this.deleteId = id;
                this.deleteName = name;
                $dispatch('open-modal', 'confirm-delete-item');
            },

            submitDelete(btn) {
                if (!this.deleteId || this.isDeleting) return;
                this.isDeleting = true;

                if (btn && window.setButtonLoading) window.setButtonLoading(btn, 'Deleting...');

                fetch('/items/delete/' + this.deleteId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    this.isDeleting = false;
                    if (data.success) {
                        $dispatch('close-modal', 'confirm-delete-item');
                        if (window.showSuccess) window.showSuccess('Item has been deleted.');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        if (window.showError) window.showError(data.message || 'Something went wrong.');
                    }
                })
                .catch(error => {
                    this.isDeleting = false;
                    if (window.showError) window.showError('An unexpected error occurred. Please try again.');
                })
                .finally(() => {
                    if (btn && window.resetButtonLoading) window.resetButtonLoading(btn);
                });
            }
        }));
    });
    </script>
    @endpush
    </x-app-layout>
