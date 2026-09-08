<x-app-layout title="Warehouse List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Warehouse List</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Warehouse List</span>
                </div>
            </div>

            <a href="{{ route('warehouse.add') }}" class="w-full md:w-auto px-4 py-1.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
    Add Warehouse
</a>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <form action="{{ route('warehouse.list') }}" method="GET" class="flex items-center gap-4 w-full md:w-auto">
                        <div class="flex items-center gap-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                            <select name="per_page" onchange="this.form.submit()" class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                                <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>

                        <div class="relative group">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40 shadow-sm transition-all">
                            <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </form>

                    <div class="flex items-center gap-2">
                        <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                            <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest w-12 text-center italic">#</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Warehouse Name</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Contact</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Details</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($warehouses as $wh)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-[10px] font-black text-slate-400 text-center italic">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[11px] font-bold text-primary-600 dark:text-primary-400">{{ $wh->warehouse_name }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400">{{ $wh->mobile ?? '---' }}</span>
                                            <span class="text-[9px] font-medium text-slate-400 italic">{{ $wh->email ?? 'no email' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex flex-col gap-0.5 leading-tight">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Total Items:</span>
                                                <span class="text-[10px] font-black text-slate-700 dark:text-slate-200">{{ $wh->total_items ?? 0 }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Available Qty:</span>
                                                <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ format_quantity($wh->available_qty ?? 0) }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Worth:</span>
                                                <span class="text-[11px] font-bold text-primary-600 dark:text-primary-400 tabular-nums">{{ format_currency($wh->worth ?? 0) }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                        <span class="{{ $wh->status === 1 ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 border-slate-500/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                            {{ $wh->status === 1 ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    <a href="{{ route('warehouse.edit', $wh->id) }}" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</a>
                                                    <form action="{{ route('warehouse.destroy', $wh->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this warehouse?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-full text-left block px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest italic">No warehouses found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">
                        Showing {{ $warehouses->firstItem() ?? 0 }} to {{ $warehouses->lastItem() ?? 0 }} of {{ $warehouses->total() }} entries
                    </p>
                    <div class="flex gap-1">
                        {{ $warehouses->links() }}
                    </div>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full opacity-50"></div>
        </div>
    </div>
</x-app-layout>
