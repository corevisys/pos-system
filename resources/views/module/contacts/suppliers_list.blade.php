<x-app-layout title="Suppliers List">
    <div x-data="{
        deleteId: null,
        deleteName: '',
        searchTerm: '{{ request('search') }}',

        openDeleteModal(id, name) {
            this.deleteId = id;
            this.deleteName = name;
            $dispatch('open-modal', 'confirm-delete-supplier');
        },

        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Suppliers List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('contacts.suppliers.import') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Import Suppliers</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Suppliers List</span>
                </div>
            </div>

            <a href="{{ route('contacts.suppliers.add') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                Add Supplier
            </a>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form id="supplierFilterForm" x-ref="filterForm" action="{{ route('contacts.suppliers.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Show</label>
                        <select name="limit" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="10" {{ request('limit') == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('limit') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('limit') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Entries</label>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Status</label>
                        <div class="w-40">
                            <x-searchable-select name="status"
                                :options="[['id' => 1, 'label' => 'Active'], ['id' => 0, 'label' => 'Inactive']]"
                                emptyOption="All Status"
                                emptyValue=""
                                placeholder="Filter by status"
                                :value="request('status')"
                                change="submitFilters()" />
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 pr-1">
                        <input type="checkbox"
                               id="accountPayableOnly"
                               name="account_payable"
                               value="1"
                               @change="submitFilters()"
                               {{ request('account_payable') ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary cursor-pointer">
                        <label for="accountPayableOnly" class="text-[9px] font-black text-text-muted uppercase tracking-widest italic cursor-pointer select-none">Account Payable Only</label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="navigator.clipboard?.writeText(window.location.href)" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Copy</button>
                        <button type="submit" form="supplierFilterForm" formaction="{{ route('contacts.suppliers.list', ['export' => 'csv']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Excel</button>
                        <button type="submit" form="supplierFilterForm" formaction="{{ route('contacts.suppliers.list', ['export' => 'print']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">PDF</button>
                    </div>
                    <div class="relative group">
                        <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Supplier ID</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Supplier Name</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Mobile</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Email</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Location</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Prev Bal</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Purchase Due</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Return Due</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                </tr>
            </x-slot>

            @forelse ($suppliers as $s)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-black text-primary">{{ $s->supplier_code }}</span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-bold text-text-primary dark:text-dark-text">{{ $s->supplier_name }}</span>
                    </td>
                    <td class="px-4 py-2 text-[10px] font-medium text-text-secondary whitespace-nowrap">{{ $s->mobile }}</td>
                    <td class="px-4 py-2 text-[10px] font-medium text-text-muted truncate max-w-[150px]">{{ $s->email ?? '—' }}</td>
                    <td class="px-4 py-2 text-[10px] font-medium text-text-secondary whitespace-nowrap">{{ $s->city ?? '—' }}</td>
                    <td class="px-4 py-2 text-[10px] font-black text-right tabular-nums text-text-secondary">{{ format_currency($s->opening_balance) }}</td>
                    <td class="px-4 py-2 text-[10px] font-black text-right tabular-nums text-danger">{{ format_currency($s->live_purchase_due ?? 0) }}</td>
                    <td class="px-4 py-2 text-[10px] font-black text-right tabular-nums text-emerald-600">{{ format_currency($s->live_return_due ?? 0) }}</td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        <x-badge :color="$s->status == 1 ? 'success' : 'danger'">
                            {{ $s->status == 1 ? 'Active' : 'Inactive' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-2 text-center whitespace-nowrap">
                        <x-dropdown align="right" width="36">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary !px-2.5 !py-1 text-[8px] flex items-center gap-1">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('contacts.suppliers.edit', $s->id)">
                                    Edit
                                </x-dropdown-link>
                                <button type="button" @click="openDeleteModal({{ $s->id }}, '{{ addslashes($s->supplier_name) }}')" class="w-full text-left px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-danger hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors border-t border-border dark:border-dark-border">
                                    Delete
                                </button>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-10 text-center">
                        <div class="flex flex-col items-center gap-2 opacity-40">
                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            <p class="text-[10px] font-black uppercase tracking-widest text-text-muted">No Suppliers Found!!</p>
                        </div>
                    </td>
                </tr>
            @endforelse

            <x-slot name="tfoot">
                <tr class="font-black text-[9px] uppercase tracking-widest text-text-muted bg-slate-50/80 dark:bg-slate-800/80 border-t border-border dark:border-dark-border">
                    <td colspan="5" class="px-4 py-3 text-right">Total Summary</td>
                    <td class="px-4 py-3 text-right tabular-nums text-text-primary dark:text-dark-text border-l border-border dark:border-dark-border">{{ format_currency($suppliers->sum('opening_balance')) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-danger border-l border-border dark:border-dark-border">{{ format_currency($suppliers->sum('live_purchase_due')) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-emerald-600 border-l border-border dark:border-dark-border">{{ format_currency($suppliers->sum('live_return_due')) }}</td>
                    <td colspan="2" class="border-l border-border dark:border-dark-border"></td>
                </tr>
            </x-slot>
        </x-table>

        <div class="mt-4">
            {{ $suppliers->links() }}
        </div>

        <!-- CONFIRM DELETE MODAL -->
        <x-modal name="confirm-delete-supplier" maxWidth="md">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-danger flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-wider">Delete Supplier</h3>
                        <p class="text-[11px] text-text-muted mt-0.5">Are you sure you want to delete <span class="font-bold text-text-primary dark:text-dark-text" x-text="deleteName"></span>?</p>
                    </div>
                </div>

                <p class="text-[11px] text-text-muted mb-6 bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-border dark:border-dark-border">
                    This action will soft-delete the supplier. Suppliers with existing purchases, purchase returns, or ledger transactions cannot be deleted.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="$dispatch('close-modal', 'confirm-delete-supplier')" class="btn-secondary">
                        Cancel
                    </button>
                    <form :action="'{{ url('contacts/suppliers') }}/' + deleteId" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger">
                            Delete Supplier
                        </button>
                    </form>
                </div>
            </div>
        </x-modal>

    </div>
</x-app-layout>