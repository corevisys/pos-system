<x-app-layout title="Sales Payments">
    <div x-data="{
        deleteAction: '',
        searchTerm: '{{ request('search') }}',
        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">

        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Sales Payments</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Overview of all payment transactions</span>
                </div>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <x-stat-card label="Total Payments" :value="number_format($globalStats['total_payments'])"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>' />
            <x-stat-card label="Total Amount" :value="format_currency($globalStats['total_amount'])"
                iconBg="bg-success-light text-success"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
            <x-stat-card label="Cash Payments" :value="format_currency($globalStats['cash_payments'])"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>' />
            <x-stat-card label="Other Payments" :value="format_currency($globalStats['other_payments'])"
                iconBg="bg-warning-light text-warning"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>' />
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form x-ref="filterForm" action="{{ route('sales.payments') }}" method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px] relative">
                    <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search by Invoice # or Customer..." class="input-base !pl-8 !text-xs">
                    <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-44">
                        <x-searchable-select
                            name="payment_type"
                            :options="$paymentTypes"
                            labelKey="payment_type"
                            valueKey="payment_type"
                            emptyOption="All Methods"
                            emptyValue=""
                            placeholder="All Methods"
                            :value="request('payment_type')"
                            change="submitFilters()" />
                    </div>

                    <button type="submit" class="btn-primary !px-4 !py-2 text-[10px] font-black uppercase tracking-widest">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Filter
                    </button>
                </div>
            </form>

            <!-- Active filter chip -->
            @if(request('search'))
                <div class="flex items-center gap-2 mt-3">
                    <x-badge color="primary">
                        <span class="flex items-center gap-1.5">
                            Search: {{ request('search') }}
                            <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="hover:text-white/80 transition-colors" title="Clear search">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                        </span>
                    </x-badge>
                    @if(request('payment_type'))
                        <x-badge color="neutral">
                            Method: {{ request('payment_type') }}
                        </x-badge>
                    @endif
                    @if(request('search') || request('payment_type'))
                        <a href="{{ route('sales.payments') }}" class="text-[10px] font-black uppercase tracking-widest text-text-muted hover:text-danger transition-colors">
                            Clear All
                        </a>
                    @endif
                </div>
            @elseif(request('payment_type'))
                <div class="flex items-center gap-2 mt-3">
                    <x-badge color="neutral">
                        Method: {{ request('payment_type') }}
                    </x-badge>
                    <a href="{{ route('sales.payments') }}" class="text-[10px] font-black uppercase tracking-widest text-text-muted hover:text-danger transition-colors">
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- TABLE -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Payment ID</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Sale Code</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Account</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Type</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Due</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot>

            @forelse($payments as $payment)
                @php
                    $salePaid = (float)($payment->sale->paid_amount ?? 0);
                    $saleTotal = (float)($payment->sale->grand_total ?? 0);
                    $remainingDue = max(0, round($saleTotal - $salePaid, 2));
                @endphp
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-6 py-2.5">
                        <x-badge color="primary">PAY-{{ $payment->id }}</x-badge>
                    </td>
                    <td class="px-6 py-2.5">
                        <span class="text-[10px] font-bold text-text-secondary dark:text-slate-400">{{ date('d-m-Y', strtotime($payment->payment_date)) }}</span>
                    </td>
                    <td class="px-6 py-2.5">
                        <a href="{{ route('sales.show', $payment->sales_id) }}" class="text-[10px] font-black text-primary hover:underline">{{ $payment->sale->sales_code ?? '-' }}</a>
                    </td>
                    <td class="px-6 py-2.5">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-black text-text-primary dark:text-dark-text">{{ $payment->customer->customer_name ?? 'Walk-in' }}</span>
                            <span class="text-[9px] text-text-muted font-bold uppercase tracking-widest">{{ $payment->customer->mobile ?? 'No Mobile' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-2.5">
                        <span class="text-[11px] font-bold text-text-secondary dark:text-slate-400">{{ $payment->account->account_name ?? 'N/A' }}</span>
                    </td>
                    <td class="px-6 py-2.5 text-right">
                        <span class="text-[11px] font-black tabular-nums text-success">{{ format_currency($payment->payment) }}</span>
                    </td>
                    <td class="px-6 py-2.5">
                        <x-badge color="{{ strtolower($payment->payment_type) === 'cash' ? 'success' : 'neutral' }}">{{ $payment->payment_type }}</x-badge>
                    </td>
                    <td class="px-6 py-2.5 text-right">
                        <span class="text-[11px] font-black tabular-nums {{ $remainingDue > 0 ? 'text-warning' : 'text-text-muted' }}">{{ format_currency($remainingDue) }}</span>
                    </td>
                    <td class="px-6 py-2.5 text-center">
                        <x-dropdown align="right" width="48" contentClasses="py-1">
                            <x-slot name="trigger">
                                <button type="button" class="btn-secondary px-3 py-1.5 text-[10px] font-black uppercase tracking-widest">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                @if(($payment->sale->payment_status ?? 'Paid') !== 'Paid')
                                    <a href="{{ route('sales.payments.receive', $payment->sales_id) }}" class="flex items-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-success hover:bg-success-light dark:hover:bg-success/10 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Receive Payment
                                    </a>
                                @endif
                                <a href="{{ route('sales.invoice', $payment->sales_id) }}" class="flex items-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-text-secondary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    Invoice
                                </a>
                                <button type="button"
                                    @click='deleteAction = "{{ route('sales.payments.destroy', $payment->id) }}"; $dispatch("open-modal", "confirm-delete-payment")'
                                    class="flex items-center gap-2 w-full px-4 py-2 text-[10px] font-black uppercase tracking-widest text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors border-t border-border-light dark:border-dark-border mt-1 pt-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Delete
                                </button>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-6 py-10 text-center text-[10px] font-bold text-text-muted italic uppercase tracking-widest">
                        No payments found — try adjusting your filters or search query
                    </td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $payments->firstItem() ?? 0 }} to {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} payments
            </p>
            <div class="flex gap-1">
                {{ $payments->appends(request()->all())->links() }}
            </div>
        </div>

        <!-- DELETE PAYMENT CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-payment" maxWidth="sm">
            <form :action="deleteAction" method="POST">
                @csrf
                @method('DELETE')
                <div class="p-6 sm:p-8 text-center">
                    <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Delete This Payment?</h2>
                    <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">
                        This will permanently remove the payment record, reverse the account balance,
                        and update the sale's paid amount and status. This cannot be undone.
                    </p>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-danger w-full">Yes, Delete Payment</button>
                        <button type="button" @click="$dispatch('close')" class="btn-secondary w-full">Cancel</button>
                    </div>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
