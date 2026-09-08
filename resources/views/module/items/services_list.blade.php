<x-app-layout title="Services List">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Services List <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Management</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Services List</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <a href="{{ route('items.service.add') }}" class="flex-grow md:flex-none px-5 py-2.5 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center justify-center gap-2">
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
                <form action="{{ route('items.service.list') }}" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Category -->
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Category</label>
                        <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="-All Categories-" emptyValue="" placeholder="All Categories" :value="request('category_id')" change="this.closest('form').submit()" />
                    </div>

                    <!-- Search -->
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name/Code..." class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 pl-8 text-[11px] font-bold outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                            <option>10</option>
                            <option>25</option>
                        </select>
                    </div>

                    <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Service Info</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Category</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Base Price ({{ $currencySymbol }})</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Sales Price ({{ $currencySymbol }})</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($services as $service)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $service->item_name }}</span>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-tight">{{ $service->item_code }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">{{ $service->category->category_name ?? '---' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-right font-mono">
                                        <span class="text-[11px] font-medium text-slate-500">{{ format_currency($service->price) }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-right font-mono">
                                        <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400">{{ format_currency($service->sales_price) }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                        <span class="px-2 py-0.5 {{ $service->status ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20' }} rounded text-[8px] font-black uppercase tracking-widest border">
                                            {{ $service->status ? 'Active' : 'Inactive' }}
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
                                                    <a href="{{ route('items.service.edit', $service->id) }}" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</a>
                                                    <a href="javascript:void(0)" onclick="deleteService({{ $service->id }})" class="block px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</a>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest italic">No services found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4 text-[10px] font-bold text-slate-500">
                    <p class="uppercase tracking-widest italic">
                        Showing {{ $services->firstItem() ?? 0 }} to {{ $services->lastItem() ?? 0 }} of {{ $services->total() }} entries
                    </p>
                    <div class="pagination-container">
                        {{ $services->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteService(id) {
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
                fetch(`/items/service/delete/${id}`, {
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
                            'Service has been deleted.',
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
