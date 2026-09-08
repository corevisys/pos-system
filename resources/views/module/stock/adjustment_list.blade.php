<x-app-layout title="Stock Adjustment List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Stock Adjustment List</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Adjustment List</span>
                </div>
            </div>

            <a href="{{ route('stock.adjustment.create') }}" class="w-full md:w-auto px-4 py-1.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New Adjustment
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <!-- FILTER SECTION -->
            <div class="bg-white dark:bg-dark-card p-3 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                <form id="adjustmentFilterForm" action="{{ route('stock.adjustment') }}" method="GET" class="w-full md:w-auto flex flex-wrap items-center gap-3">
                    <div class="group relative w-full md:w-64">
                        <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-rose-500 italic">
                            Warehouse
                        </label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-rose-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                            <select name="warehouse_id" onchange="document.getElementById('adjustmentFilterForm').submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 pl-10 pr-8 text-[10px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-rose-500 appearance-none cursor-pointer transition-all">
                                <option value="">-All Warehouses-</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                </form>
            </div>

            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="GET" action="{{ route('stock.adjustment') }}" class="relative group">
                            @if(request('warehouse_id'))
                                <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}">
                            @endif
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
                                    <input type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Adjustment Date</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Reference No.</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Warehouse</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Created by</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($adjustments as $adj)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-center">
                                        <input type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d-m-Y') }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-black text-slate-400 font-mono tracking-wider italic">{{ $adj->reference_no ?? '---' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400">{{ $adj->warehouse->warehouse_name ?? '-' }}</span>
                                    </td>
                                    <td class="px-6 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-primary-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></div>
                                            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400">{{ $adj->user->name ?? 'System' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    <a href="#" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">View</a>
                                                    <a href="{{ route('stock.adjustment.edit', $adj->id) }}" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border">Edit</a>
                                                    <a href="#" class="block px-4 py-2 text-[10px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic">Delete</a>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-slate-400 text-xs font-bold uppercase tracking-widest">No Adjustments Found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5">
                    {{ $adjustments->links() }}
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full"></div>
        </div>
    </div>
</x-app-layout>
