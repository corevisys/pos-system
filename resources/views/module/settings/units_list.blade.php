<x-app-layout title="Units List">
    <div x-data="{
        showAddModal: false, 
        showEditModal: false,
        editId: null,
        editName: '',
        editDescription: '',
        editStatus: 1
    }">
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Units List</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Units List</span>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto">
                <button @click="showAddModal = true" class="flex-grow md:flex-none px-4 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-rose-200/50 dark:shadow-none">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    New Unit
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <!-- TABLE SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Table Controls -->
                <div class="px-3 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                        <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-bold outline-none cursor-pointer focus:ring-1 focus:ring-primary-500 transition-all">
                            <option>10</option>
                            <option>25</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex bg-slate-100 dark:bg-slate-800 rounded-lg p-0.5 gap-1">
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Copy</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">Excel</button>
                            <button class="px-2 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-md transition-all">PDF</button>
                        </div>
                        <div class="relative group">
                            <input type="text" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-7 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-32 focus:w-40 transition-all shadow-sm">
                            <svg class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-3 w-8 text-center">
                                    <input type="checkbox" class="w-3 h-3 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Unit Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @forelse ($units as $unit)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-4 py-2.5 text-center">
                                        <input type="checkbox" class="w-3 h-3 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $unit->unit_name }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-[10px] font-medium text-slate-400 dark:text-slate-500 italic">
                                        {{ $unit->description ?? '---' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if ($unit->status)
                                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-600 rounded-[4px] text-[8px] font-black uppercase tracking-widest">Active</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-rose-100 text-rose-600 rounded-[4px] text-[8px] font-black uppercase tracking-widest">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                         <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <button @click="open = !open" class="px-2.5 py-1 bg-white border border-slate-200 dark:bg-dark-card dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1 hover:border-rose-500 hover:text-rose-600 transition-all shadow-sm text-slate-500">
                                                Action
                                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-28 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden" x-cloak>
                                                <div class="py-1">
                                                    <button @click="editId = {{ $unit->id }}; editName = '{{ $unit->unit_name }}'; editDescription = '{{ $unit->description }}'; editStatus = {{ $unit->status }}; showEditModal = true; open = false;" class="w-full text-left block px-3 py-2 text-[9px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Edit</button>
                                                    <form :action="'/settings/units/' + {{ $unit->id }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="w-full text-left block px-3 py-2 text-[9px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:divide-dark-border italic">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                         </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-8 h-8 text-slate-100 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0 a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0l-8 8-8-8"></path></svg>
                                            <span class="text-[9px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-widest">No units found</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="px-4 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic italic">Showing {{ $units->count() }} entries</p>
                </div>
            </div>

            <!-- ADD UNIT MODAL -->
            <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white dark:bg-dark-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-dark-border">
                        <form action="/settings/units" method="POST">
                            @csrf
                            <div class="px-6 py-4 border-b border-slate-50 dark:border-dark-border flex justify-between items-center">
                                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-800 dark:text-white">Add Unit</h3>
                                <button @click="showAddModal = false" type="button" class="text-slate-400 hover:text-rose-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="px-6 py-6 space-y-4">
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Unit Name</label>
                                    <input type="text" name="unit_name" required placeholder="e.g. KG" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Description</label>
                                    <textarea name="description" rows="3" placeholder="Enter description (optional)" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"></textarea>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Status</label>
                                    <select name="status" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-50 dark:border-dark-border flex justify-end gap-3">
                                <button @click="showAddModal = false" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 transition-colors">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none">Save Unit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- EDIT UNIT MODAL -->
            <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white dark:bg-dark-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-dark-border">
                        <form :action="'/settings/units/' + editId" method="POST">
                            @csrf
                            <div class="px-6 py-4 border-b border-slate-50 dark:border-dark-border flex justify-between items-center">
                                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-800 dark:text-white">Edit Unit</h3>
                                <button @click="showEditModal = false" type="button" class="text-slate-400 hover:text-rose-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="px-6 py-6 space-y-4">
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Unit Name</label>
                                    <input type="text" name="unit_name" required x-model="editName" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Description</label>
                                    <textarea name="description" rows="3" x-model="editDescription" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all"></textarea>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Status</label>
                                    <select name="status" x-model="editStatus" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none transition-all">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-50 dark:border-dark-border flex justify-end gap-3">
                                <button @click="showEditModal = false" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 transition-colors">Cancel</button>
                                <button type="submit" class="px-6 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none">Update Unit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full opacity-20"></div>
        </div>
    </div>
</x-app-layout>
