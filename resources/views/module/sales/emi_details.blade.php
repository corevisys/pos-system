<x-app-layout title="EMI Sale Details">
    <div x-data="{ payScheduleId: null, payAmount: 0, payInstallmentNo: 0 }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">EMI Details: <span class="text-primary">{{ $emiSale->sale->sales_code }}</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('sales.emi.list') }}" class="hover:text-primary transition-colors text-[10px]">EMI Sale List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold">View EMI Details</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('sales.invoice', $emiSale->sale->id) }}" target="_blank" class="btn-primary !px-4 !py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Invoice
                </a>
                <button onclick="window.close()" class="btn-danger !px-4 !py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Close Tab
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- LEFT: INFO & ITEMS -->
            <div class="lg:col-span-2 space-y-4">

                <!-- CLIENT & EMI SUMMARY -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="card p-4">
                        <p class="text-[10px] font-black text-text-muted uppercase tracking-widest mb-3">Customer Information</p>
                        <h3 class="text-base font-black text-text-primary dark:text-dark-text">{{ $emiSale->customer->customer_name }}</h3>
                        <p class="text-xs text-text-secondary font-medium mt-1">
                            <span class="text-text-muted">Phone:</span> {{ $emiSale->customer->mobile }}
                        </p>
                        <p class="text-xs text-text-secondary font-medium">
                            <span class="text-text-muted">Email:</span> {{ $emiSale->customer->email ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-text-secondary font-medium">
                            <span class="text-text-muted">Address:</span> {{ $emiSale->customer->address ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="card p-4">
                        <p class="text-[10px] font-black text-text-muted uppercase tracking-widest mb-3">EMI Summary</p>
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-text-secondary">Start Date</span>
                                <span class="font-bold text-text-primary dark:text-dark-text">{{ \Carbon\Carbon::parse($emiSale->start_date)->format('d-M-Y') }}</span>
                            </div>
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-text-secondary">Warehouse</span>
                                <span class="font-bold text-text-primary dark:text-dark-text">{{ $emiSale->sale->warehouse->warehouse_name }}</span>
                            </div>
                            <div class="flex justify-between text-xs font-medium items-center">
                                <span class="text-text-secondary">Status</span>
                                @if($emiSale->status === 'Completed')
                                    <x-badge color="success">Completed</x-badge>
                                @else
                                    <x-badge color="warning">{{ $emiSale->status }}</x-badge>
                                @endif
                            </div>
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-text-secondary">Duration</span>
                                <span class="font-bold underline decoration-primary/40 text-text-primary dark:text-dark-text">{{ $emiSale->duration_months }} Months</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SOLD ITEMS -->
                <x-table title="Sold Items">
                    <x-slot name="thead">
                        <tr>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Item Name</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Price</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Qty</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Total</th>
                        </tr>
                    </x-slot>
                    @foreach($emiSale->sale->items as $index => $item)
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                            <td class="px-6 py-3 text-[10px] font-bold text-text-muted">{{ $index + 1 }}</td>
                            <td class="px-6 py-3">
                                <div class="font-black text-[11px] text-text-primary dark:text-dark-text group-hover:text-primary transition-colors">{{ $item->item->item_name }}</div>
                                <div class="flex flex-col gap-0.5 mt-0.5">
                                    @if($item->item->variant_id)
                                        <div class="flex items-center gap-1">
                                            <span class="text-[8px] text-white bg-slate-800 px-1.5 py-0.5 rounded font-black uppercase">{{ $item->item->variant->variant_name }}</span>
                                            <span class="text-[9px] text-text-muted font-bold italic">{{ $item->item->item_code }}</span>
                                        </div>
                                    @else
                                        <span class="text-[9px] text-text-muted font-bold italic">{{ $item->item->item_code }}</span>
                                    @endif

                                    @php
                                        $itemSerials = $emiSale->sale->serials->where('item_id', $item->item_id);
                                    @endphp
                                    @if($itemSerials->count() > 0)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($itemSerials as $serial)
                                                <span class="text-[8px] font-black uppercase bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded border border-emerald-100 flex items-center gap-1">
                                                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                    {{ $serial->serial_number }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums">{{ format_currency($item->price_per_unit) }}</td>
                            <td class="px-6 py-3 text-[10px] font-black text-center tabular-nums">
                                <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-text-secondary dark:text-dark-text">{{ format_quantity($item->sales_qty) }}</span>
                            </td>
                            <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums text-primary">{{ format_currency($item->total_cost) }}</td>
                        </tr>
                    @endforeach
                </x-table>

                <!-- EMI SCHEDULE -->
                <x-table title="EMI Schedule" id="schedule">
                    <x-slot name="thead">
                        <tr>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Due Date</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Paid</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                            <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </x-slot>
                    @foreach($emiSale->schedule as $sch)
                        @php
                            $isOverdue = $sch->status !== 'Paid' && $sch->due_date < now()->toDateString();
                        @endphp
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors {{ $isOverdue ? 'bg-rose-50/40 dark:bg-rose-900/10' : '' }}">
                            <td class="px-6 py-3 text-[10px] font-bold text-text-muted">{{ $sch->installment_no }}</td>
                            <td class="px-6 py-3 text-[10px] font-bold {{ $isOverdue ? 'text-rose-600' : 'text-text-primary dark:text-dark-text' }}">{{ \Carbon\Carbon::parse($sch->due_date)->format('d-M-Y') }}</td>
                            <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums">{{ format_currency($sch->amount) }}</td>
                            <td class="px-6 py-3 text-[10px] font-black text-right tabular-nums text-emerald-600">{{ format_currency($sch->paid_amount) }}</td>
                            <td class="px-6 py-3 text-center">
                                @if($isOverdue)
                                    <x-badge color="danger">Overdue</x-badge>
                                @elseif($sch->status == 'Paid')
                                    <x-badge color="success">Paid</x-badge>
                                @elseif($sch->status == 'Partial')
                                    <x-badge color="warning">Partial</x-badge>
                                @else
                                    <x-badge color="neutral">Pending</x-badge>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-center">
                                @if($sch->status != 'Paid')
                                    <button @click="
                                        payScheduleId = {{ $sch->id }};
                                        payAmount = {{ $sch->amount - $sch->paid_amount }};
                                        payInstallmentNo = {{ $sch->installment_no }};
                                        $dispatch('open-modal', 'pay-emi-installment');
                                    " class="btn-success !px-3 !py-1 text-[9px] font-black uppercase tracking-widest">Pay</button>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600 text-[10px]">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>

            <!-- RIGHT: FINANCIAL SUMMARY -->
            <div class="space-y-4">
                <div class="card p-5 shadow-md sticky top-20">
                    <p class="text-[10px] font-black text-text-muted uppercase tracking-widest mb-4 border-b border-border-light dark:border-dark-border pb-2">Financial Summary</p>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-text-secondary">Total Payable</span>
                            <span class="text-xs font-black text-text-primary dark:text-dark-text">{{ format_currency($emiSale->total_payable) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-rose-500 p-2 bg-danger-light/50 dark:bg-rose-900/10 rounded-lg">
                            <span class="text-[10px] font-black uppercase tracking-tight">Initial Payment</span>
                            <span class="text-xs font-black">- {{ format_currency($emiSale->sale->paid_amount) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-emerald-600 p-2 bg-success-light dark:bg-emerald-900/10 rounded-lg">
                            <span class="text-[10px] font-black uppercase tracking-tight">Total Collected</span>
                            <span class="text-xs font-black">{{ format_currency($emiSale->sale->payments->sum('payment')) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-text-secondary">Installments Paid</span>
                            <span class="text-xs font-bold text-emerald-600">{{ format_currency($emiSale->schedule->sum('paid_amount')) }}</span>
                        </div>
                        @if($overdueInstallments->count() > 0)
                            <div class="flex justify-between items-center text-rose-600 p-2 bg-danger-light dark:bg-rose-900/10 rounded-lg">
                                <span class="text-[10px] font-black uppercase tracking-tight">Overdue Installments</span>
                                <x-badge color="danger">{{ $overdueInstallments->count() }}</x-badge>
                            </div>
                        @endif
                        <hr class="border-border-light dark:border-dark-border">
                        <div class="flex justify-between items-center pt-1">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-widest text-text-muted">Remaining Balance</span>
                                @if($emiSale->status === 'Completed')
                                    <x-badge color="success" class="mt-1 w-fit">
                                        <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        Completed
                                    </x-badge>
                                @endif
                            </div>
                            <span class="text-2xl font-black text-rose-600 tabular-nums leading-none">
                                {{ format_currency($emiSale->total_payable - $emiSale->schedule->sum('paid_amount')) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-4">
                            <div class="p-2 bg-success-light dark:bg-emerald-900/10 rounded-xl border border-emerald-100 dark:border-emerald-900/20">
                                <p class="text-[8px] font-black text-emerald-600 uppercase tracking-widest">Subtotal</p>
                                <p class="text-xs font-black text-emerald-600 tabular-nums">{{ format_currency($emiSale->sale->subtotal) }}</p>
                            </div>
                            <div class="p-2 bg-slate-50 dark:bg-slate-900/10 rounded-xl border border-border-light dark:border-slate-900/20 text-right">
                                <p class="text-[8px] font-black text-text-secondary uppercase tracking-widest">Duration</p>
                                <p class="text-xs font-black text-text-secondary tabular-nums">{{ $emiSale->duration_months }} M</p>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-border-light dark:border-dark-border mt-4">
                            <p class="text-[8px] font-black text-text-muted uppercase tracking-widest mb-1">Amount In Words</p>
                            <p class="text-[10px] font-bold text-text-secondary dark:text-dark-text leading-tight italic">
                                {{ $amount_in_words }}
                            </p>
                        </div>
                    </div>

                    @if($emiSale->notes)
                    <div class="mt-6 p-3 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-100 dark:border-amber-900/20">
                        <p class="text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            EMI Notes
                        </p>
                        <p class="text-[10px] text-amber-700 dark:text-amber-400 font-medium italic leading-relaxed">{{ $emiSale->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- PAYMENT MODAL -->
        <x-modal name="pay-emi-installment" maxWidth="md" :show="false">
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="w-12 h-12 bg-primary-100 dark:bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-3 text-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    </div>
                    <h3 class="text-lg font-black text-text-primary dark:text-dark-text">Pay Installment #<span x-text="payInstallmentNo"></span></h3>
                    <p class="text-xs text-text-secondary">Record a payment for this EMI schedule.</p>
                </div>

                <form action="{{ route('sales.emi.pay') }}" method="POST">
                    @csrf
                    <input type="hidden" name="schedule_id" :value="payScheduleId">

                    <div class="space-y-4">
                        <div>
                            <label class="text-[10px] font-black uppercase text-text-muted tracking-widest pl-1 block mb-1.5">Amount</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-xs font-bold">{{ $currencySymbol }}</span>
                                <input type="number" name="amount" x-model="payAmount" step="0.01" class="input-base !pl-8 !py-2.5 !text-sm !font-black">
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase text-text-muted tracking-widest pl-1 block mb-1.5">Account</label>
                            <select name="account_id" class="input-base !py-2.5 !text-xs !font-bold appearance-none">
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-8">
                        <button type="button" @click="$dispatch('close-modal', 'pay-emi-installment')" class="flex-1 px-4 py-2.5 bg-white dark:bg-dark-card border border-border-light dark:border-dark-border rounded-xl text-xs font-black uppercase tracking-widest text-text-secondary hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 btn-primary !text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/20">Confirm Payment</button>
                    </div>
                </form>
            </div>
        </x-modal>
    </div>
</x-app-layout>
