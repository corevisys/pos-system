<x-app-layout title="Advance Payments List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Advance Payments</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Advance List</span>
                </div>
            </div>

            @if(auth()->user()->hasPermission('cust_adv_payments_add'))
                <a href="{{ route('advance.add') }}" class="btn-primary w-full md:w-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                    Add Advance
                </a>
            @endif
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form action="{{ route('advance.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Date</label>
                        <input type="date" name="date" value="{{ request('date') }}" class="input-base !w-auto !py-1.5 !px-3 !text-[11px] !rounded-lg">
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Customer</label>
                        <div class="w-52">
                            <x-searchable-select name="customer_id" :options="$customers" labelKey="customer_name" valueKey="id" emptyOption="All Customers" emptyValue="" placeholder="All Customers" :value="request('customer_id')" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary !py-1.5 !px-3 !text-[10px]">
                        Filter
                    </button>
                    <a href="{{ route('advance.list') }}" class="btn-secondary !py-1.5 !px-3 !text-[10px]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Code</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer Name</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount ({{ $currencySymbol }})</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Type</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Note</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center w-28">Action</th>
                </tr>
            </x-slot>

            @forelse($advances as $advance)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-5 py-3">
                        <span class="text-[11px] font-black text-primary dark:text-primary-400 font-mono">{{ $advance->payment_code }}</span>
                    </td>
                    <td class="px-5 py-3 text-[11px] font-bold text-text-secondary">
                        {{ \Carbon\Carbon::parse($advance->payment_date)->format('d-m-Y') }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $advance->customer ? $advance->customer->customer_name : 'N/A' }}</span>
                            @if($advance->customer && $advance->customer->customer_code)
                                <span class="text-[9px] font-medium text-text-muted">({{ $advance->customer->customer_code }})</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3 text-[11px] font-black text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                        {{ format_currency($advance->amount) }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <x-badge color="neutral">{{ $advance->payment_type }}</x-badge>
                    </td>
                    <td class="px-5 py-3 text-[11px] text-text-muted">
                        {{ Str::limit($advance->note, 30) ?: '—' }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <x-dropdown align="right" width="48" contentClasses="py-1 bg-white dark:bg-dark-card">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary !py-1 !px-2.5 !text-[9px] uppercase tracking-widest flex items-center gap-1.5">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('advance.edit', $advance->id)">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </div>
                                </x-dropdown-link>
                                
                                <form action="{{ route('advance.delete', $advance->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this advance payment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 focus:outline-none transition duration-150 ease-in-out border-t border-border-light dark:border-dark-border">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            <span>Delete</span>
                                        </div>
                                    </button>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-8 text-center text-text-muted text-xs font-bold">No advance payments found.</td>
                </tr>
            @endforelse
        </x-table>

        <!-- PAGINATION -->
        @if($advances->hasPages())
            <div class="mt-4">
                {{ $advances->appends(request()->all())->links() }}
            </div>
        @endif
    </div>
</x-app-layout>