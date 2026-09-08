<x-app-layout title="Items List">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Items List <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Inventory</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Items List</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                @if(auth()->user()->hasPermission('items_print_labels'))
                    <a href="{{ route('items.labels') }}" class="flex-grow md:flex-none px-4 py-2.5 bg-slate-800 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Labels
                    </a>
                @endif
                <a href="{{ route('items.add') }}" class="flex-grow md:flex-none px-5 py-2.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Item
                </a>
                <a href="#" class="flex-grow md:flex-none px-5 py-2.5 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Service
                </a>
            </div>
        </div>

        <div class="space-y-6">
            <!-- FILTERS -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 shadow-sm">
                <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-4 bg-primary-500 rounded-full"></span>
                    Filter Criteria
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Warehouse -->
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Warehouse <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <select class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer leading-tight transition-all">
                                <option>-All Warehouses-</option>
                            </select>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Item Type -->
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Item Type</label>
                        <div class="relative">
                            <select name="item_type" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer leading-tight transition-all">
                                <option value="">All</option>
                                <option value="Items" {{ request('item_type') == 'Items' ? 'selected' : '' }}>Item</option>
                                <option value="Services" {{ request('item_type') == 'Services' ? 'selected' : '' }}>Service</option>
                            </select>
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Brand</label>
                        <x-searchable-select name="brand_id" :options="$brands" labelKey="brand_name" valueKey="id" emptyOption="-All Brands-" emptyValue="" placeholder="All Brands" :value="request('brand_id')" change="document.getElementById('filterForm')?.submit()" />
                    </div>

                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Category</label>
                        <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="-All Categories-" emptyValue="" placeholder="All Categories" :value="request('category_id')" change="document.getElementById('filterForm')?.submit()" />
                    </div>
                </div>
            </div>

            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm" x-data="{
                searchTerm: '{{ request('search') }}',
                selectedAll: false,
                selectedItems: [],
                toggleAll() {
                    if (this.selectedAll) {
                        this.selectedItems = Array.from(document.querySelectorAll('.item-checkbox')).map(cb => cb.value);
                    } else {
                        this.selectedItems = [];
                    }
                }
            }">
                
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                            <option>10</option>
                            <option>25</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <template x-if="selectedItems.length > 0">
                            <button type="button" @click="window.location.href = '{{ route('items.labels') }}?items=' + selectedItems.join(',')" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-[9px] font-black uppercase tracking-widest rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                Print Labels (<span x-text="selectedItems.length"></span>)
                            </button>
                        </template>
                        <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                        </div>
                        <form action="{{ route('items.list') }}" method="GET" class="relative group">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40 shadow-sm transition-all">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </form>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 w-10 text-center">
                                    <input type="checkbox" x-model="selectedAll" @change="toggleAll()" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Item Info</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Brand/Category</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Stock</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Price ({{ $currencySymbol }})</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @foreach($items as $item)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-center">
                                        <input type="checkbox" value="{{ $item->id }}" x-model="selectedItems" class="item-checkbox w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg border border-slate-100 dark:border-dark-border flex items-center justify-center bg-white dark:bg-dark-card overflow-hidden">
                                                @if($item->item_image)
                                                    <img src="{{ asset($item->item_image) }}" alt="{{ $item->item_name }}" class="w-full h-full object-cover">
                                                @else
                                                    <svg class="w-4 h-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                @endif
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $item->item_name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight">{{ $item->item_code }}</span>
                                                    @if($item->is_serialized)
                                                        <span class="text-[8px] font-bold bg-primary-50 dark:bg-primary-500/10 text-primary-600 px-1.5 py-0.5 rounded uppercase tracking-widest flex items-center gap-1 border border-primary-100/50">
                                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                                            Serialized: {{ $item->serials_count }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-tight italic">{{ $item->brand->brand_name ?? '---' }}</span>
                                            <span class="text-[9px] font-bold text-slate-400">{{ $item->category->category_name ?? '---' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5 text-right font-mono">
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-[11px] font-black text-slate-700 dark:text-slate-200">{{ $item->stock }}</span>
                                            @if($item->stock <= $item->alert_qty)
                                                <span class="text-[8px] font-black text-rose-500 uppercase italic">Low Stock</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5 text-right font-mono">
                                        <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400">{{ format_currency($item->sales_price) }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                        <span class="px-2 py-0.5 {{ $item->status ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20' }} rounded text-[8px] font-black uppercase tracking-widest border">
                                            {{ $item->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                             <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1 text-left">
                                                    <a href="{{ route('items.show', $item->id) }}" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">View</a>
                                                    <a href="{{ route('items.edit', $item->id) }}" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border">Edit</a>
                                                    @if(auth()->user()->hasPermission('items_print_labels'))
                                                        <a href="{{ route('items.labels', ['items' => $item->id]) }}" class="block px-4 py-2 text-[10px] font-bold text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border">Print Label</a>
                                                    @endif
                                                    <a href="javascript:void(0)" onclick="deleteItem({{ $item->id }})" class="block px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</a>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4 text-[10px] font-bold text-slate-500">
                    <p class="uppercase tracking-widest italic">
                        Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} entries
                    </p>
                    <div class="pagination-container">
                        {{ $items->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteItem(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/items/delete/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Deleted!',
                            'Item has been deleted.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            data.message || 'Something went wrong.',
                            'error'
                        );
                    }
                });
            }
        });
    }
</script>
@endpush
