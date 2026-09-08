<x-app-layout title="Customers List">
    <div x-data="{
        deleteId: null,
        searchTerm: '{{ request('search') }}',

        openDeleteModal(id) {
            this.deleteId = id;
            $dispatch('open-modal', 'confirm-delete-customer');
        },

        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Customers List</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('contacts.customers.import') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Import Customers</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Customers List</span>
                </div>
            </div>

            <a href="{{ route('contacts.customers.add') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                Add Customer
            </a>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form id="customerFilterForm" x-ref="filterForm" action="{{ route('contacts.customers.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
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
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="navigator.clipboard?.writeText(window.location.href)" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Copy</button>
                        <button type="submit" form="customerFilterForm" formaction="{{ route('contacts.customers.list', ['export' => 'csv']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Excel</button>
                        <button type="submit" form="customerFilterForm" formaction="{{ route('contacts.customers.list', ['export' => 'print']) }}" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">PDF</button>
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
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Customer ID</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Customer Name</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Mobile</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Email</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Location</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Credit Limit</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Prev Due</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Return Due (+)</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right whitespace-nowrap">Advance</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Action</th>
                </tr>
            </x-slot>

            @forelse($customers as $customer)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-black text-primary">{{ $customer->customer_code }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <span class="text-[11px] font-bold text-text-primary dark:text-dark-text leading-tight block">{{ $customer->customer_name }}</span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-medium text-text-secondary">{{ $customer->mobile ?: $customer->mobile_primary ?: '-' }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <span class="text-[10px] font-medium text-text-muted truncate max-w-[150px] block">{{ $customer->email ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="text-[10px] font-medium text-text-muted">{{ $customer->city ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[10px] font-black tabular-nums text-text-primary dark:text-dark-text">
                            {{ $customer->credit_limit == -1 ? 'No Limit' : format_currency($customer->credit_limit) }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[10px] font-black tabular-nums text-text-secondary dark:text-text-muted">{{ format_currency($customer->opening_balance) }}</span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[10px] font-black tabular-nums text-danger">{{ format_currency($customer->sales_return_due) }}</span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <span class="text-[10px] font-black tabular-nums text-success">{{ format_currency($customer->tot_advance) }}</span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        @if($customer->status == 1)
                            <x-badge color="success">Active</x-badge>
                        @else
                            <x-badge color="neutral">Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center">
                        <x-dropdown align="right" width="48" contentClasses="py-1">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary px-3 py-1.5 text-[10px] font-black uppercase tracking-widest">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('contacts.customers.edit', $customer->id)">Edit Customer</x-dropdown-link>
                                <button type="button"
                                    @click="openDeleteModal({{ $customer->id }})"
                                    class="block w-full px-4 py-2 text-start text-sm leading-5 text-danger dark:text-danger hover:bg-danger-light dark:hover:bg-danger/10 focus:outline-none focus:bg-danger-light dark:focus:bg-danger/10 transition duration-150 ease-in-out">
                                    Delete
                                </button>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-10 text-center text-[10px] font-bold text-text-muted italic uppercase tracking-widest">No customers found</td>
                </tr>
            @endforelse
        </x-table>

        <!-- This Page Totals -->
        <div class="mt-4 card p-2.5 px-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">This Page Total</p>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <span class="text-[9px] font-black uppercase tracking-widest text-text-secondary italic">Prev Due: <span class="tabular-nums not-italic">{{ format_currency($customers->sum('opening_balance')) }}</span></span>
                <span class="text-[9px] font-black uppercase tracking-widest text-danger italic">Return Due: <span class="tabular-nums not-italic">{{ format_currency($customers->sum('sales_return_due')) }}</span></span>
                <span class="text-[9px] font-black uppercase tracking-widest text-success italic">Advance: <span class="tabular-nums not-italic">{{ format_currency($customers->sum('tot_advance')) }}</span></span>
            </div>
        </div>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $customers->firstItem() ?? 0 }} to {{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $customers->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-customer" maxWidth="sm">
            <form :action="'{{ url('contacts/customers') }}' + '/' + deleteId" method="POST">
                @csrf
                @method('DELETE')
                <div class="p-6 sm:p-8 text-center">
                    <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Delete Customer?</h2>
                    <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">This will remove the customer from the list.<br>Customers with sales or payment history cannot be deleted.</p>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-danger w-full">Yes, Delete Customer</button>
                        <button type="button" @click="$dispatch('close')" class="btn-secondary w-full">Cancel</button>
                    </div>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
