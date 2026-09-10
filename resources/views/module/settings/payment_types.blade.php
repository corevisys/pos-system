<x-app-layout title="Payment Types">
    <div x-data="{
        showAddModal: false,
        showEditModal: false,
        editId: null,
        editName: '',
        editStatus: 1
    }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Payment Types <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">View/Search Payment Types</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Payment Types</span>
                </div>
            </div>

            <button @click="showAddModal = true" class="btn-primary w-full md:w-auto !bg-danger hover:!bg-danger-hover">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New Payment Type
            </button>
        </div>

        <!-- TABLE SECTION -->
        <x-card padding="p-0" class="overflow-hidden">
            <!-- Table Controls (server-side search + per-page limit) -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <form action="{{ route('settings.payment_types') }}" method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Show</label>
                        <select name="limit" onchange="this.form.submit()" class="bg-white dark:bg-dark-card border border-border dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-bold outline-none cursor-pointer">
                            @foreach ([10, 25, 50, 100] as $opt)
                                <option value="{{ $opt }}" {{ request('limit', 10) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest">Entries</label>
                    </div>

                    <div class="relative group">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button type="submit" class="btn-primary !py-1.5 !px-3 !text-[10px] font-black uppercase tracking-widest">Search</button>
                    @if(request('search'))
                        <a href="{{ route('settings.payment_types') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2.5 w-12 text-center text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Payment Type Name</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse ($paymentTypes as $index => $p)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-4 py-1.5 text-center text-[10px] font-bold text-text-muted tabular-nums">
                                    {{ $paymentTypes->firstItem() + $index }}
                                </td>
                                <td class="px-4 py-1.5">
                                    <span class="text-[11px] font-bold text-text-primary dark:text-dark-text uppercase tracking-tight">{{ $p->payment_type }}</span>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    <span class="{{ $p->status ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                        {{ $p->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    @if (strtoupper(trim((string) $p->payment_type)) !== 'CASH')
                                        <x-dropdown align="right" width="40">
                                            <x-slot name="trigger">
                                                <button type="button" class="btn-primary px-3 py-1 !text-[9px] uppercase tracking-widest flex items-center gap-1.5 leading-none">
                                                    Action
                                                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>
                                            </x-slot>
                                            <x-slot name="content">
                                                <button
                                                    @click="editId = @js($p->id); editName = @js($p->payment_type); editStatus = @js((int) $p->status); showEditModal = true"
                                                    class="w-full text-left block px-4 py-2 text-sm text-text-primary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors uppercase tracking-widest">
                                                    Edit
                                                </button>
                                                <form :action="'/settings/payment-types/' + @js($p->id)" method="POST" onsubmit="return confirm('Are you sure?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors uppercase tracking-widest">Delete</button>
                                                </form>
                                            </x-slot>
                                        </x-dropdown>
                                    @else
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">-NA-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No Payment Types Found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $paymentTypes->firstItem() ?? 0 }} to {{ $paymentTypes->lastItem() ?? 0 }} of {{ $paymentTypes->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $paymentTypes->links() }}
                </div>
            </div>
        </x-card>

        <!-- ADD MODAL -->
        <div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showAddModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="{{ route('settings.payment_types.store') }}" method="POST">
                        @csrf
                        <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Add Payment Type</h3>
                            <button @click="showAddModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="px-6 py-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Payment Type Name</label>
                                <input type="text" name="payment_type" required placeholder="e.g. Bkash" class="input-base">
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
                            <button type="submit" class="btn-primary">Save Payment Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom card rounded-2xl text-left overflow-hidden shadow-modal transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form :action="'/settings/payment-types/' + editId" method="POST">
                        @csrf
                        <div class="px-6 py-4 border-b border-border dark:border-dark-border flex justify-between items-center">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-text-primary dark:text-dark-text">Edit Payment Type</h3>
                            <button @click="showEditModal = false" type="button" class="text-text-muted hover:text-danger transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="px-6 py-6 space-y-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Payment Type Name</label>
                                <input type="text" name="payment_type" required x-model="editName" class="input-base">
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
                            <button type="submit" class="btn-primary">Update Payment Type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
