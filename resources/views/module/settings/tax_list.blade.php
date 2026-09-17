<x-app-layout title="Tax List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Tax List <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">View/Search Tax</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Tax List</span>
                </div>
            </div>
        </div>

        <!-- SHARED SEARCH BAR (server-side, filters both lists) -->
        <form action="{{ route('settings.tax') }}" method="GET" class="card p-3 mb-4 flex flex-wrap justify-between items-center gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative group">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tax name..." class="input-base !w-56 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                    <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button type="submit" class="btn-primary !py-1.5 !px-3 text-[10px] font-black uppercase tracking-widest">Search</button>
                @if(request('search'))
                    <a href="{{ route('settings.tax') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline">Clear</a>
                @endif
            </div>
        </form>

        <div class="grid grid-cols-1 gap-6">

            <!-- SECTION 1: TAX LIST -->
            <div class="space-y-3" x-data="{
                showAddModal: false,
                showEditModal: false,
                editId: null,
                editName: '',
                editRate: '',
                editStatus: 1,
                isSubmitting: false
            }"
                x-effect="document.body.classList.toggle('overflow-y-hidden', showAddModal || showEditModal)"
                @keydown.escape.window="showAddModal = false; showEditModal = false">
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em]">Tax List</h2>
                    <button @click="showAddModal = true" class="btn-primary !py-1.5 !px-3 !text-[10px] tracking-widest">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                        New Tax
                    </button>
                </div>

                <x-card padding="p-0" class="overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                                <tr>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Tax Name</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Tax(%)</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                @forelse ($taxes as $tax)
                                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-4 py-1.5">
                                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $tax->tax_name }}</span>
                                        </td>
                                        <td class="px-4 py-1.5">
                                            <span class="text-[10px] font-black tabular-nums text-text-secondary dark:text-text-muted">{{ $tax->tax }}</span>
                                        </td>
                                        <td class="px-4 py-1.5 text-center">
                                            <span class="{{ $tax->status ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                                {{ $tax->status ? 'Active' : 'Inactive' }}
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
                                                    <button
                                                        @click="editId = @js($tax->id); editName = @js($tax->tax_name); editRate = @js((string) $tax->tax); editStatus = @js((int) $tax->status); showEditModal = true"
                                                        class="w-full text-left block px-4 py-2 text-sm text-text-primary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors uppercase tracking-widest">
                                                        Edit
                                                    </button>
                                                    <form :action="'/settings/tax/' + @js($tax->id)" method="POST" onsubmit="return confirm('Are you sure?')">
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
                                        <td colspan="4" class="px-6 py-8 text-center">
                                            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No taxes found</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                        <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                            Showing {{ $taxes->firstItem() ?? 0 }} to {{ $taxes->lastItem() ?? 0 }} of {{ $taxes->total() }} entries
                        </p>
                        <div class="flex gap-1">
                            {{ $taxes->links() }}
                        </div>
                    </div>
                </x-card>

                <!-- ADD TAX MODAL -->
                {{-- x-teleport is REQUIRED: the app shell has overflow-hidden/overflow-y-auto
                     ancestors, so a fixed overlay left in place is clipped (backdrop shows,
                     dialog invisible, page unusable). Teleporting to <body> fixes positioning
                     while keeping the Alpine scope intact. --}}
                <template x-teleport="body">
                <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        {{-- Backdrop z-0 + panel relative z-10. The positioned, z-index:auto
                             blurred layer is painted in CSS 2.1 Appendix E step 6, which
                             beat the static inline-block panel (step 5) — blurring the
                             dialog itself and swallowing its clicks. --}}
                        <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showAddModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="/settings/tax" method="POST" @submit="isSubmitting = true">
                                @csrf
                                <input type="hidden" name="group_bit" value="0">
                                <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Add Individual Tax</h3>
                                    <button @click="showAddModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <div class="px-6 py-6 space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Tax Name <span class="text-danger">*</span></label>
                                        <input type="text" name="tax_name" required placeholder="e.g. VAT" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Tax Rate (%) <span class="text-danger">*</span></label>
                                        <input type="number" name="tax" required step="0.01" placeholder="0.00" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                        <select name="status" class="input-base">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                                    <button @click="showAddModal = false" type="button" class="btn-secondary">Cancel</button>
                                    <button type="submit" :disabled="isSubmitting" class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed">Save Tax</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </template>

                <!-- EDIT TAX MODAL -->
                <template x-teleport="body">
                <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showEditModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form :action="'/settings/tax/' + editId" method="POST" @submit="isSubmitting = true">
                                @csrf
                                <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Edit Tax</h3>
                                    <button @click="showEditModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <div class="px-6 py-6 space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Tax Name <span class="text-danger">*</span></label>
                                        <input type="text" name="tax_name" required x-model="editName" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Tax Rate (%) <span class="text-danger">*</span></label>
                                        <input type="number" name="tax" required step="0.01" x-model="editRate" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                        <select name="status" x-model="editStatus" class="input-base">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                                    <button @click="showEditModal = false" type="button" class="btn-secondary">Cancel</button>
                                    <button type="submit" :disabled="isSubmitting" class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed">Update Tax</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </template>
            </div>

            <!-- SECTION 2: TAX GROUPS -->
            <div class="space-y-3" x-data="{
                showAddGroupModal: false,
                showEditGroupModal: false,
                editGroupId: null,
                editGroupName: '',
                editGroupSubtaxes: [],
                editGroupStatus: 1,
                isSubmitting: false
            }"
                x-effect="document.body.classList.toggle('overflow-y-hidden', showAddGroupModal || showEditGroupModal)"
                @keydown.escape.window="showAddGroupModal = false; showEditGroupModal = false">
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em]">Tax Groups</h2>
                    <button @click="showAddGroupModal = true" class="btn-primary !py-1.5 !px-3 !text-[10px] tracking-widest">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                        New Tax Group
                    </button>
                </div>

                <x-card padding="p-0" class="overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                                <tr>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Tax Name</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Tax(%)</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Sub Taxes</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                                    <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                @forelse ($taxGroups as $group)
                                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-4 py-1.5">
                                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $group->tax_name }}</span>
                                        </td>
                                        <td class="px-4 py-1.5">
                                            <span class="text-[10px] font-black tabular-nums text-text-secondary dark:text-text-muted">{{ $group->tax }}</span>
                                        </td>
                                        <td class="px-4 py-1.5">
                                            <span class="text-[10px] font-black tabular-nums text-text-secondary dark:text-text-muted">
                                                @php
                                                    // Phase 1.6: names pre-resolved once in the controller
                                                    // ($subtaxNames keyed by id) instead of a per-row query.
                                                    $names = collect(explode(',', (string) $group->subtax_ids))
                                                        ->map(fn($id) => $subtaxNames[(int) $id] ?? null)
                                                        ->filter()
                                                        ->all();
                                                    echo implode(', ', $names);
                                                @endphp
                                            </span>
                                        </td>
                                        <td class="px-4 py-1.5 text-center">
                                            <span class="{{ $group->status ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                                {{ $group->status ? 'Active' : 'Inactive' }}
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
                                                    <button
                                                        @click="editGroupId = @js($group->id); editGroupName = @js($group->tax_name); editGroupSubtaxes = @js(explode(',', $group->subtax_ids)); editGroupStatus = @js((int) $group->status); showEditGroupModal = true"
                                                        class="w-full text-left block px-4 py-2 text-sm text-text-primary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors uppercase tracking-widest">
                                                        Edit
                                                    </button>
                                                    <form :action="'/settings/tax/' + @js($group->id)" method="POST" onsubmit="return confirm('Are you sure?')">
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
                                            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No tax groups found</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                        <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                            Showing {{ $taxGroups->firstItem() ?? 0 }} to {{ $taxGroups->lastItem() ?? 0 }} of {{ $taxGroups->total() }} entries
                        </p>
                        <div class="flex gap-1">
                            {{ $taxGroups->links() }}
                        </div>
                    </div>
                </x-card>

                <!-- ADD TAX GROUP MODAL -->
                <template x-teleport="body">
                <div x-show="showAddGroupModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showAddGroupModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showAddGroupModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div x-show="showAddGroupModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="{{ route('settings.tax.store') }}" method="POST" @submit="isSubmitting = true">
                                @csrf
                                <input type="hidden" name="group_bit" value="1">
                                <input type="hidden" name="tax" value="0">
                                <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Add Tax Group</h3>
                                    <button @click="showAddGroupModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <div class="px-6 py-6 space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Group Name <span class="text-danger">*</span></label>
                                        <input type="text" name="tax_name" required placeholder="e.g. Sales Tax Group" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Select Sub Taxes</label>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            @foreach ($allTaxes as $tax)
                                                <label class="flex items-center gap-2 p-2 rounded-input bg-background dark:bg-white/5 border border-border dark:border-dark-border cursor-pointer hover:bg-slate-100 transition-colors">
                                                    <input type="checkbox" name="subtax_ids_array[]" value="{{ $tax->id }}" class="w-3.5 h-3.5 rounded border-slate-300 text-primary focus:ring-primary/30">
                                                    <span class="text-[10px] font-bold text-text-secondary dark:text-dark-text">{{ $tax->tax_name }} ({{ $tax->tax }}%)</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                        <select name="status" class="input-base">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                                    <button @click="showAddGroupModal = false" type="button" class="btn-secondary">Cancel</button>
                                    <button type="submit" :disabled="isSubmitting" class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed">Save Group</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </template>

                <!-- EDIT TAX GROUP MODAL -->
                <template x-teleport="body">
                <div x-show="showEditGroupModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div x-show="showEditGroupModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="showEditGroupModal = false" class="fixed inset-0 z-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div x-show="showEditGroupModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" @click.stop class="relative z-10 inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form :action="'/settings/tax/' + editGroupId" method="POST" @submit="isSubmitting = true">
                                @csrf
                                <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Edit Tax Group</h3>
                                    <button @click="showEditGroupModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <div class="px-6 py-6 space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Group Name <span class="text-danger">*</span></label>
                                        <input type="text" name="tax_name" required x-model="editGroupName" class="input-base">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Select Sub Taxes</label>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            @foreach ($allTaxes as $tax)
                                                <label class="flex items-center gap-2 p-2 rounded-input bg-background dark:bg-white/5 border border-border dark:border-dark-border cursor-pointer hover:bg-slate-100 transition-colors">
                                                    <input type="checkbox" name="subtax_ids_array[]" value="{{ $tax->id }}" :checked="editGroupSubtaxes.includes(@js((string) $tax->id))" class="w-3.5 h-3.5 rounded border-slate-300 text-primary focus:ring-primary/30">
                                                    <span class="text-[10px] font-bold text-text-secondary dark:text-dark-text">{{ $tax->tax_name }} ({{ $tax->tax }}%)</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Status</label>
                                        <select name="status" x-model="editGroupStatus" class="input-base">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-background/50 dark:bg-white/5 border-t border-border dark:border-dark-border flex justify-end gap-3">
                                    <button @click="showEditGroupModal = false" type="button" class="btn-secondary">Cancel</button>
                                    <button type="submit" :disabled="isSubmitting" class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed">Update Group</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                </template>
            </div>

        </div>
    </div>
</x-app-layout>
