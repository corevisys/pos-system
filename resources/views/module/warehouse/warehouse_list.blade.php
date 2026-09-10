<x-app-layout title="Warehouse List">
    <div class="grid grid-cols-1 gap-3">
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-1">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Warehouse List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Warehouse List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">View/Search Warehouses</p>
            </div>

            <a href="{{ route('warehouse.add') }}" class="btn-primary w-full md:w-auto !bg-danger hover:!bg-danger-hover">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Add Warehouse
            </a>
        </div>

        <!-- STATS CARDS (Phase 6: surfaced from controller $stats) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="card p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <p class="text-[8px] font-black uppercase tracking-widest text-text-muted">Total Warehouses</p>
                    <p class="text-lg font-black text-text-primary dark:text-white tabular-nums">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="card p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-success/10 text-success flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[8px] font-black uppercase tracking-widest text-text-muted">Active</p>
                    <p class="text-lg font-black text-success tabular-nums">{{ $stats['active'] }}</p>
                </div>
            </div>
            <div class="card p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                </div>
                <div>
                    <p class="text-[8px] font-black uppercase tracking-widest text-text-muted">Inactive</p>
                    <p class="text-lg font-black text-danger tabular-nums">{{ $stats['inactive'] }}</p>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="card overflow-hidden p-0">
            <!-- Table Controls (search + status filter + export, Phase 6) -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <form action="{{ route('warehouse.list') }}" method="GET" class="flex flex-wrap items-center gap-3">
                    <!-- Status Filter (Phase 6) -->
                    <div class="flex items-center gap-2">
                        <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Status</label>
                        <select name="status" onchange="this.form.submit()" class="input-base !w-auto !py-0.5 !px-1.5 !text-[10px] !rounded-lg !cursor-pointer">
                            <option value="" {{ !request('status') ? 'selected' : '' }}>All</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <!-- Show / per-page -->
                    <div class="flex items-center gap-2">
                        <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Show</label>
                        <select name="per_page" onchange="this.form.submit()" class="input-base !w-auto !py-0.5 !px-1.5 !text-[10px] !rounded-lg !cursor-pointer">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <label class="text-[8px] font-black text-text-muted uppercase tracking-widest leading-none">Entries</label>
                    </div>
                    <div class="relative group">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </form>

                <!-- Export (Phase 6: store-scoped CSV/PDF, Deposit convention) -->
                <div class="flex bg-background dark:bg-slate-800 rounded-xl p-1 border border-border dark:border-dark-border">
                    <a href="{{ route('warehouse.list', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                    <a href="{{ route('warehouse.list', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Warehouse Name</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Contact</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Details</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse($warehouses as $wh)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-4 py-1.5">
                                    <span class="text-[11px] font-bold text-primary-600 dark:text-primary-400">{{ $wh->warehouse_name }}</span>
                                </td>
                                <td class="px-4 py-1.5">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-text-primary dark:text-dark-text">{{ $wh->mobile ?? '---' }}</span>
                                        <span class="text-[9px] font-medium text-text-muted italic">{{ $wh->email ?? 'no email' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-1.5">
                                    <div class="flex flex-col gap-0.5 leading-tight">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[8px] font-black text-text-muted uppercase tracking-widest">Total Items:</span>
                                            <span class="text-[10px] font-black text-text-primary dark:text-dark-text">{{ $wh->total_items ?? 0 }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[8px] font-black text-text-muted uppercase tracking-widest">Available Qty:</span>
                                            <span class="text-[10px] font-black text-success tabular-nums">{{ format_quantity($wh->available_qty ?? 0) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[8px] font-black text-text-muted uppercase tracking-widest">Worth:</span>
                                            <span class="text-[11px] font-bold text-primary-600 dark:text-primary-400 tabular-nums">{{ format_currency($wh->worth ?? 0) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    <span class="{{ $wh->status === 1 ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                        {{ $wh->status === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    <x-dropdown align="right" width="40">
                                        <x-slot name="trigger">
                                            <button type="button" class="btn-primary px-3 py-1 !text-[9px] uppercase tracking-widest flex items-center gap-1.5 leading-none">
                                                Action
                                                <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <x-dropdown-link :href="route('warehouse.edit', $wh->id)">Edit</x-dropdown-link>
                                            <form action="{{ route('warehouse.destroy', $wh->id) }}" method="POST" onsubmit="return confirm('Delete warehouse "{{ addslashes($wh->warehouse_name) }}"?&#10;&#10;Stock in this warehouse: {{ (int) ($wh->total_items ?? 0) }} item(s), {{ format_quantity($wh->available_qty ?? 0) }} qty, worth {{ format_currency($wh->worth ?? 0) }}.&#10;Deletion is only allowed when stock is zero and no sales/serials/transfers reference it.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors uppercase tracking-widest">Delete</button>
                                            </form>
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No warehouses found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $warehouses->firstItem() ?? 0 }} to {{ $warehouses->lastItem() ?? 0 }} of {{ $warehouses->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $warehouses->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
