<x-app-layout title="Brands List">
    <div x-data="{
        deleteModal: false,
        deleteId: null,
        searchTerm: '{{ request('search') }}',
        selectedAll: false,
        selectedBrands: [],
        
        openDeleteModal(id) {
            this.deleteId = id;
            this.deleteModal = true;
        },

        toggleAll() {
            const checkboxes = document.querySelectorAll('.brand-checkbox');
            if (this.selectedAll) {
                this.selectedBrands = Array.from(checkboxes).map(cb => cb.value);
            } else {
                this.selectedBrands = [];
            }
        },
        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Brand Management</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Brand List</span>
                </div>
            </div>

            <a href="{{ route('items.brands.add') }}" class="w-full md:w-auto px-4 py-1.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2 text-center">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New Brand
            </a>
        </div>


        <div class="grid grid-cols-1 gap-4">
            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                    <div class="flex justify-between items-start mb-1">
                        <div class="p-1.5 bg-slate-50 dark:bg-slate-800 rounded-xl group-hover:scale-110 transition-transform duration-300 text-blue-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    </div>
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Brands</p>
                    <h3 class="text-sm font-black dark:text-white">{{ $stats['total'] }}</h3>
                </div>
                <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                    <div class="flex justify-between items-start mb-1">
                        <div class="p-1.5 bg-slate-50 dark:bg-slate-800 rounded-xl group-hover:scale-110 transition-transform duration-300 text-emerald-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Active</p>
                    <h3 class="text-sm font-black dark:text-white">{{ $stats['active'] }}</h3>
                </div>
                <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border group hover:shadow-lg transition-all duration-300">
                    <div class="flex justify-between items-start mb-1">
                        <div class="p-1.5 bg-slate-50 dark:bg-slate-800 rounded-xl group-hover:scale-110 transition-transform duration-300 text-rose-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Inactive</p>
                    <h3 class="text-sm font-black dark:text-white">{{ $stats['inactive'] }}</h3>
                </div>
            </div>

            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <form x-ref="filterForm" action="{{ route('items.brands') }}" method="GET" class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                            <select name="per_page" @change="submitFilters()" class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                                <option value="10" {{ request('per_page') == 10 || !request('per_page') ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>

                         <div class="flex items-center gap-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Status</label>
                            <select name="status" @change="submitFilters()" class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                                <option value="">All Status</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                            <button type="button" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                            <button type="button" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                            <button type="button" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                        </div>
                        <div class="relative group">
                            <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40 shadow-sm transition-all">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </form>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 w-10 text-center">
                                    <input type="checkbox" x-model="selectedAll" @change="toggleAll()" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                </th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest cursor-pointer hover:text-primary-600 transition-colors">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'brand_name', 'order' => request('order') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1">
                                        Brand Name
                                        @if(request('sort') === 'brand_name')
                                            <svg class="w-2 h-2 {{ request('order') === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
                                        @endif
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Brand Code</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($brands as $brand)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-center">
                                        <input type="checkbox" value="{{ $brand->id }}" x-model="selectedBrands" class="brand-checkbox w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                            </div>
                                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $brand->brand_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 leading-tight uppercase tracking-widest italic">{{ $brand->brand_code ?? '---' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 leading-tight">{{ $brand->description ?? 'No description' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                        @if($brand->status)
                                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-600 rounded text-[8px] font-black uppercase tracking-widest border border-emerald-500/20">Active</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-rose-500/10 text-rose-600 rounded text-[8px] font-black uppercase tracking-widest border border-rose-500/20">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1 text-left">
                                                    <a href="{{ route('items.brands.edit', $brand->id) }}" class="w-full text-left block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</a>
                                                    <button @click="openDeleteModal({{ $brand->id }}); open = false" class="w-full text-left block px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</button>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-[10px] font-bold text-slate-400 italic uppercase tracking-widest">No brands found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">
                        Showing {{ $brands->firstItem() ?? 0 }} to {{ $brands->lastItem() ?? 0 }} of {{ $brands->total() }} entries
                    </p>
                    <div class="flex gap-1">
                        {{ $brands->links() }}
                    </div>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full"></div>
        </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div x-show="deleteModal" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 z-[100] flex items-center justify-center bg-dark-bg/40 backdrop-blur-md p-6">
        <div x-show="deleteModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="scale-95 translate-y-10" x-transition:enter-end="scale-100 translate-y-0" @click.away="deleteModal = false"
                class="bg-white dark:bg-dark-card w-full max-w-sm rounded-[3rem] shadow-2xl p-10 border border-slate-100 dark:border-dark-border text-center overflow-hidden">
            
            <div class="w-20 h-20 bg-rose-50 dark:bg-rose-500/10 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <h2 class="text-2xl font-black mb-2">Delete Brand?</h2>
            <p class="text-slate-400 text-sm font-medium mb-8 leading-relaxed">This will remove the brand from the system. <br> Existing items linked to this brand may be affected.</p>
            
            <form :action="'{{ url('items/brands') }}/' + deleteId" method="POST" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-xl shadow-rose-200 dark:shadow-none">Yes, Delete Brand</button>
                <button type="button" @click="deleteModal = false" class="w-full py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all">Cancel</button>
            </form>
        </div>
    </div>
    </div>
</x-app-layout>
