<x-app-layout title="Brands List">
    <div x-data="brandsListPage()">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Brands <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">List</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">Brands List</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <a href="{{ route('items.brands.add') }}" class="btn-primary w-full md:w-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Brand
                </a>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border">
                <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Brands</p>
                <h3 class="text-sm font-black dark:text-white">{{ $stats['total'] }}</h3>
            </div>
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border">
                <p class="text-[8px] font-bold text-emerald-500 uppercase tracking-widest mb-0.5">Active</p>
                <h3 class="text-sm font-black dark:text-white">{{ $stats['active'] }}</h3>
            </div>
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border">
                <p class="text-[8px] font-bold text-rose-500 uppercase tracking-widest mb-0.5">Inactive</p>
                <h3 class="text-sm font-black dark:text-white">{{ $stats['inactive'] }}</h3>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form id="brandFilterForm" x-ref="filterForm" action="{{ route('items.brands') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
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
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Status</label>
                        <select name="status" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="relative group">
                    <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                    <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Brand Name</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Brand Code</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Description</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                </tr>
            </x-slot>

            @forelse($brands as $brand)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                            </div>
                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $brand->brand_name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-bold text-text-muted leading-tight uppercase tracking-widest italic">{{ $brand->brand_code ?? '---' }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <span class="text-[10px] font-bold text-text-secondary leading-tight">{{ $brand->description ?? 'No description' }}</span>
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        @if($brand->status)
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
                                <x-dropdown-link :href="route('items.brands.edit', $brand->id)">Edit</x-dropdown-link>
                                <button type="button" @click="toggleBrandStatus({{ $brand->id }}, {{ $brand->status ? '0' : '1' }})" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest {{ $brand->status ? 'text-amber-600' : 'text-emerald-600' }} hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors">
                                    {{ $brand->status ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button type="button" @click="openDeleteModal({{ $brand->id }}, '{{ addslashes($brand->brand_name) }}')" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-danger hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors border-t border-border dark:border-dark-border">
                                    Delete
                                </button>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center">
                        <div class="flex flex-col items-center gap-2 opacity-40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-muted">No Brands Found!!</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $brands->firstItem() ?? 0 }} to {{ $brands->lastItem() ?? 0 }} of {{ $brands->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $brands->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <div x-show="deleteModal" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 z-[100] flex items-center justify-center bg-dark-bg/40 backdrop-blur-md p-6">
            <div x-show="deleteModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="scale-95 translate-y-10" x-transition:enter-end="scale-100 translate-y-0" @click.away="deleteModal = false"
                    class="bg-white dark:bg-dark-card w-full max-w-sm rounded-[3rem] shadow-2xl p-10 border border-slate-100 dark:border-dark-border text-center overflow-hidden">

                <div class="w-20 h-20 bg-rose-50 dark:bg-rose-500/10 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </div>
                <h2 class="text-2xl font-black mb-2">Delete Brand?</h2>
                <p class="text-slate-400 text-sm font-medium mb-8 leading-relaxed">
                    This will remove the brand from the system.<br>
                    Existing items linked to this brand may be affected.
                </p>

                <form :action="'{{ url('items/brands') }}/' + deleteId" method="POST" class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-xl shadow-rose-200 dark:shadow-none">Yes, Delete Brand</button>
                    <button type="button" @click="deleteModal = false" class="w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('brandsListPage', () => ({
            searchTerm: @js(request('search')),
            deleteModal: false,
            deleteId: null,
            isSubmitting: false,

            submitFilters() {
                if (this.isSubmitting) return;
                this.isSubmitting = true;
                this.$refs.filterForm.submit();
                setTimeout(() => { this.isSubmitting = false; }, 1000);
            },

            openDeleteModal(id, name) {
                this.deleteId = id;
                this.deleteModal = true;
            },

            toggleBrandStatus(id, newStatus) {
                fetch('/items/brands/toggle-status/' + id, {
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
                        if (window.showSuccess) window.showSuccess(data.message || 'Brand updated.');
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
