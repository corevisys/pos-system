<x-app-layout title="Services List">
    <div x-data="servicesListPage()">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Services <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">List</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">Services List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Service Catalog Management</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <a href="{{ route('items.service.add') }}" class="btn-primary w-full md:w-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Service
                </a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form id="serviceFilterForm" x-ref="filterForm" action="{{ route('items.service.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
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
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Category</label>
                        <div class="w-44">
                            <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="All Categories" emptyValue="" placeholder="All Categories" :value="request('category_id')" change="submitFilters()" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button type="button" @click="copyPageUrl()" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Copy</button>
                        <button type="submit" form="serviceFilterForm" formaction="{{ route('items.service.list', ['export' => 'csv']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Excel</button>
                        <button type="submit" form="serviceFilterForm" formaction="{{ route('items.service.list', ['export' => 'print']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">PDF</button>
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
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Service Info</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Category</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Base Price ({{ $currencySymbol }})</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Sales Price ({{ $currencySymbol }})</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                </tr>
            </x-slot>

            @forelse($services as $service)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-4 py-2">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $service->item_name }}</span>
                            <span class="text-[9px] font-black text-text-muted uppercase tracking-tight">{{ $service->item_code }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-bold text-text-secondary">{{ $service->category->category_name ?? '---' }}</span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[11px] font-medium text-text-secondary tabular-nums">{{ format_currency($service->price) }}</span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ format_currency($service->sales_price) }}</span>
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        @if($service->status)
                            <x-badge color="success">Active</x-badge>
                        @else
                            <x-badge color="neutral">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        <x-dropdown align="right" width="40">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary !px-2.5 !py-1 text-[8px] flex items-center gap-1">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('items.service.edit', $service->id)">Edit</x-dropdown-link>
                                <button type="button" @click="toggleServiceStatus({{ $service->id }}, {{ $service->status ? '0' : '1' }}, '{{ $service->status ? 'deactivate' : 'activate' }}')" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest {{ $service->status ? 'text-amber-600' : 'text-emerald-600' }} hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors">
                                    {{ $service->status ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button type="button" @click="openDeleteModal({{ $service->id }}, '{{ addslashes($service->item_name) }}')" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-danger hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors border-t border-border dark:border-dark-border">
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
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-muted">No Services Found!!</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $services->firstItem() ?? 0 }} to {{ $services->lastItem() ?? 0 }} of {{ $services->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $services->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-service" maxWidth="sm">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-danger flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-wider">Delete Service</h3>
                        <p class="text-[11px] text-text-muted mt-0.5">Are you sure you want to delete <span class="font-bold text-text-primary dark:text-dark-text" x-text="deleteName"></span>?</p>
                    </div>
                </div>

                <p class="text-[11px] text-text-muted mb-6 bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-border dark:border-dark-border">
                    This will permanently remove the service from the catalog.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'confirm-delete-service')" class="btn-secondary">
                        Cancel
                    </button>
                    <button type="button" @click="submitDelete($event.currentTarget)" class="btn-danger" :disabled="isDeleting">
                        <span x-show="!isDeleting">Delete Service</span>
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
        Alpine.data('servicesListPage', () => ({
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

            copyPageUrl() {
                if (navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(window.location.href);
                    if (window.showSuccess) window.showSuccess('Page URL copied to clipboard.');
                }
            },

            openDeleteModal(id, name) {
                this.deleteId = id;
                this.deleteName = name;
                $dispatch('open-modal', 'confirm-delete-service');
            },

            submitDelete(btn) {
                if (!this.deleteId || this.isDeleting) return;
                this.isDeleting = true;

                if (btn && window.setButtonLoading) window.setButtonLoading(btn, 'Deleting...');

                fetch('/items/service/delete/' + this.deleteId, {
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
                        $dispatch('close-modal', 'confirm-delete-service');
                        if (window.showSuccess) window.showSuccess('Service has been deleted.');
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
            },

            toggleServiceStatus(id, newStatus, action) {
                fetch('/items/service/toggle-status/' + id, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (window.showSuccess) window.showSuccess(data.message || 'Service updated.');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        if (window.showError) window.showError(data.message || 'Something went wrong.');
                    }
                })
                .catch(() => {
                    if (window.showError) window.showError('An unexpected error occurred. Please try again.');
                });
            }
        }));
    });
    </script>
    @endpush
</x-app-layout>
