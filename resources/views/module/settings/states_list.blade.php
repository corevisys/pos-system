<x-app-layout title="States List">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">States List <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">View/Search States</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">States List</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('settings.states.add') }}" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    Add State
                </a>
            </div>
        </div>

        <!-- STATISTICS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-dark-border group hover:border-primary-500 transition-all duration-300">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Total States</p>
                <h3 class="text-2xl font-black text-slate-800 dark:text-white tabular-nums">{{ $totalStates }}</h3>
            </div>
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-dark-border group hover:border-emerald-500 transition-all duration-300">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1 text-emerald-500">Active</p>
                <h3 class="text-2xl font-black text-slate-800 dark:text-white tabular-nums">{{ $activeStates }}</h3>
            </div>
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-dark-border group hover:border-rose-500 transition-all duration-300">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1 text-rose-500">Inactive</p>
                <h3 class="text-2xl font-black text-slate-800 dark:text-white tabular-nums">{{ $inactiveStates }}</h3>
            </div>
        </div>


        <div class="grid grid-cols-1 gap-6">
            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-bold outline-none cursor-pointer">
                            <option>10</option>
                            <option>25</option>
                        </select>
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex bg-slate-50 dark:bg-slate-800 rounded-lg p-0.5 border border-slate-200 dark:border-dark-border">
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all text font-bold">Cols</button>
                        </div>
                        <div class="relative group">
                            <input type="text" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-7 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-32 focus:w-48 transition-all shadow-sm">
                            <svg class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-3 w-8 text-center text-[9px] font-black text-slate-400 uppercase tracking-widest tracking-[0.2em]">#</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest tracking-[0.2em]">State Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest tracking-[0.2em]">Country</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest tracking-[0.2em] text-center">Status</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest tracking-[0.2em] text-center text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse($states as $index => $state)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-4 py-2.5 text-center text-[10px] font-bold text-slate-400 tabular-nums">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 tracking-tight transition-transform group-hover:translate-x-1 inline-block">{{ $state->state }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-4 bg-slate-100 dark:bg-slate-800 rounded flex items-center justify-center text-[8px] font-black text-slate-400 uppercase tracking-widest border border-slate-100 dark:border-dark-border">
                                                {{ substr($state->country, 0, 2) }}
                                            </div>
                                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">{{ $state->country }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if($state->status == 1)
                                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-600 rounded-[4px] text-[8px] font-black uppercase tracking-widest">Active</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-rose-100 text-rose-600 rounded-[4px] text-[8px] font-black uppercase tracking-widest">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-2.5 py-1 bg-white border border-slate-200 dark:bg-dark-card dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1 hover:border-primary-500 hover:text-primary-600 transition-all shadow-sm text-slate-500">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" 
                                                 @click.away="open = false" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="absolute right-0 mt-2 w-28 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    <a href="{{ route('settings.states.edit', $state->id) }}" class="flex items-center gap-2 px-3 py-2 text-[9px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest group/item">
                                                        <svg class="w-3 h-3 text-slate-400 group-hover/item:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('settings.states.delete', $state->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this state?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-[9px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border italic group/del">
                                                            <svg class="w-3 h-3 text-rose-400 group-hover/del:text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-[10px] uppercase font-black tracking-widest">No states found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="p-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic tracking-[0.2em]">Showing {{ $states->count() }} entries</p>
                    <div class="flex gap-1">
                        <button class="px-2 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded text-[9px] font-bold text-slate-400">Prev</button>
                        <button class="px-2 py-1 bg-primary-600 text-white rounded text-[9px] font-bold shadow-md shadow-primary-200/50">1</button>
                        <button class="px-2 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded text-[9px] font-bold text-slate-600">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
