<x-app-layout title="Receive Payment">
    @php
        $remainingDue = max(0, (float)$sale->grand_total - (float)$sale->paid_amount);
        $isFullyPaid = $remainingDue <= 0.0001;
    @endphp

    <div x-data="receivePaymentApp()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight flex items-center gap-2.5 text-text-primary dark:text-dark-text">
                    <div class="w-9 h-9 bg-emerald-600 rounded-xl flex items-center justify-center shadow-md shadow-emerald-200 dark:shadow-none text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    Receive Payment
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('sales.list') }}" class="hover:text-primary transition-colors text-[10px]">Sales List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('sales.show', $sale->id) }}" class="hover:text-primary transition-colors text-[10px]">Sale #{{ $sale->sales_code }}</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary dark:text-slate-300 text-[10px] font-bold">Receive Payment</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1">Record payment collection for Sale #{{ $sale->sales_code }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('sales.show', $sale->id) }}" class="btn-secondary !px-3.5 !py-2 !text-[10px] font-black uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    View Sale
                </a>
                <a href="{{ route('sales.invoice', $sale->id) }}" class="btn-secondary !px-3.5 !py-2 !text-[10px] font-black uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Invoice
                </a>
                <a href="{{ route('sales.list') }}" class="btn-ghost !px-3.5 !py-2 !text-[10px] font-black uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to List
                </a>
            </div>
        </div>

        <!-- FINANCIAL KPI CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <!-- Total Invoice -->
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest">Grand Total</p>
                    <h3 class="text-lg font-black text-text-primary dark:text-white tabular-nums">{{ format_currency($sale->grand_total) }}</h3>
                </div>
            </div>

            <!-- Already Paid -->
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[9px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Paid Amount</p>
                    <h3 class="text-lg font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ format_currency($sale->paid_amount) }}</h3>
                </div>
            </div>

            <!-- Remaining Due -->
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl {{ $isFullyPaid ? 'bg-slate-100 dark:bg-slate-800 text-slate-400' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-600' }} flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[9px] font-black {{ $isFullyPaid ? 'text-slate-400' : 'text-rose-600 dark:text-rose-400' }} uppercase tracking-widest">Balance Due</p>
                    <h3 class="text-lg font-black {{ $isFullyPaid ? 'text-slate-600 dark:text-slate-300' : 'text-rose-600 dark:text-rose-400' }} tabular-nums">{{ format_currency($remainingDue) }}</h3>
                </div>
            </div>

            <!-- Payment Status -->
            <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl {{ $sale->payment_status === 'Paid' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600' : ($sale->payment_status === 'Partial' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-600') }} flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                </div>
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Payment Status</p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        @if($sale->payment_status === 'Paid')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Fully Paid
                            </span>
                        @elseif($sale->payment_status === 'Partial')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                Partially Paid
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Unpaid
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            
            <!-- LEFT COLUMN: SALE & CUSTOMER OVERVIEW + PAYMENT HISTORY -->
            <div class="lg:col-span-7 space-y-5">
                
                <!-- SALE & CUSTOMER INFO CARD -->
                <div class="card p-5">
                    <div class="flex items-center justify-between border-b border-border-light dark:border-dark-border pb-3.5 mb-4">
                        <h2 class="text-xs font-black text-text-primary dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                            Invoice & Customer Details
                        </h2>
                        <span class="text-[10px] font-bold text-text-muted">Sale ID #{{ $sale->id }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Customer:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $sale->customer->customer_name ?? 'Walk-in Customer' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Mobile:</span>
                                <span class="font-medium text-slate-600 dark:text-slate-300">{{ $sale->customer->mobile ?? '—' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Cust Code:</span>
                                <span class="font-mono text-[11px] text-slate-600 dark:text-slate-400">{{ $sale->customer->customer_code ?? '—' }}</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Sale Code:</span>
                                <span class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ $sale->sales_code }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Date:</span>
                                <span class="font-medium text-slate-600 dark:text-slate-300">{{ date('d M, Y', strtotime($sale->sales_date)) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-[10px] font-black uppercase tracking-wider w-24">Warehouse:</span>
                                <span class="font-medium text-slate-600 dark:text-slate-300">{{ $sale->warehouse->warehouse_name ?? 'Main Warehouse' }}</span>
                            </div>
                        </div>
                    </div>

                    @if($sale->items && $sale->items->count() > 0)
                        <div class="mt-4 pt-3 border-t border-border-light dark:border-dark-border">
                            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest mb-2">Invoice Line Items ({{ $sale->items->count() }})</p>
                            <div class="space-y-1 max-h-28 overflow-y-auto custom-scrollbar pr-1">
                                @foreach($sale->items as $item)
                                    <div class="flex items-center justify-between text-[11px] py-1 px-2 rounded-lg bg-background dark:bg-slate-800/40">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                            <span class="font-bold text-text-primary dark:text-slate-200">{{ $item->item->item_name ?? 'Product' }}</span>
                                            <span class="text-[10px] text-text-muted">× {{ format_quantity($item->sales_qty) }}</span>
                                        </div>
                                        <span class="font-mono font-bold text-text-primary dark:text-slate-300">{{ format_currency($item->total_cost) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- PAYMENT HISTORY CARD -->
                <div class="card overflow-hidden p-0">
                    <div class="px-5 py-4 border-b border-border-light dark:border-dark-border flex items-center justify-between bg-background/50 dark:bg-slate-800/30">
                        <h2 class="text-xs font-black text-text-primary dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            Payment History
                        </h2>
                        <span class="text-[10px] font-bold text-text-muted">{{ $sale->payments->count() }} transaction(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-background/80 dark:bg-slate-800/60 border-b border-border-light dark:border-dark-border">
                                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Date</th>
                                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Method</th>
                                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Deposit Account</th>
                                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Note</th>
                                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                @forelse($sale->payments as $payment)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                        <td class="px-5 py-3.5 text-[11px] font-bold text-text-secondary dark:text-slate-300 whitespace-nowrap">
                                            {{ date('d M, Y', strtotime($payment->payment_date)) }}
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <x-badge color="neutral">{{ $payment->payment_type ?? 'Cash' }}</x-badge>
                                        </td>
                                        <td class="px-5 py-3.5 text-[11px] font-medium text-text-secondary dark:text-slate-300">
                                            <div class="flex flex-col">
                                                <span class="font-bold">{{ $payment->account->account_name ?? 'N/A' }}</span>
                                                @if(isset($payment->account->account_code))
                                                    <span class="text-[9px] font-mono text-text-muted">{{ $payment->account->account_code }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-[10px] text-text-secondary dark:text-slate-400 italic max-w-[150px] truncate">
                                            {{ $payment->payment_note ?: '—' }}
                                        </td>
                                        <td class="px-5 py-3.5 text-[11px] font-black text-text-primary dark:text-white text-right tabular-nums">
                                            {{ format_currency($payment->payment) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-10 h-10 bg-background dark:bg-slate-800 rounded-xl flex items-center justify-center text-slate-300 dark:text-slate-600 mb-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                </div>
                                                <p class="text-[10px] font-black text-text-muted uppercase tracking-widest">No previous payments recorded</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($sale->payments->count() > 0)
                                <tfoot class="bg-background/80 dark:bg-slate-800/50 border-t border-border-light dark:border-dark-border">
                                    <tr>
                                        <td colspan="4" class="px-5 py-3 text-[10px] font-black text-text-muted uppercase tracking-widest text-right">Total Collected</td>
                                        <td class="px-5 py-3 text-xs font-black text-success text-right tabular-nums">{{ format_currency($sale->paid_amount) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: RECEIVE PAYMENT FORM -->
            <div class="lg:col-span-5">
                <div class="card p-5 sticky top-4">
                    
                    @if($isFullyPaid)
                        <!-- FULLY SETTLED CARD -->
                        <div class="p-6 text-center space-y-4">
                            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 mx-auto shadow-inner">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">Invoice Fully Settled</h3>
                                <p class="text-xs text-slate-400 font-medium mt-1 leading-relaxed">
                                    All dues for Sale #<span class="font-bold text-slate-700 dark:text-slate-200">{{ $sale->sales_code }}</span> have been received in full. No additional payment collection is required.
                                </p>
                            </div>
                            <div class="pt-2 flex flex-col gap-2">
                                <a href="{{ route('sales.invoice', $sale->id) }}" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    View Printable Receipt
                                </a>
                                <a href="{{ route('sales.list') }}" class="w-full py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center justify-center">
                                    Return to Sales List
                                </a>
                            </div>
                        </div>
                    @else
                        <!-- PAYMENT FORM -->
                        <div class="flex items-center justify-between border-b border-border-light dark:border-dark-border pb-3.5 mb-4">
                            <h2 class="text-xs font-black text-text-primary dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                Record Payment Receipt
                            </h2>
                            <span class="text-[9px] font-black text-danger uppercase tracking-widest">
                                Due: {{ format_currency($remainingDue) }}
                            </span>
                        </div>

                        <form action="{{ route('sales.payments.store') }}" method="POST" class="space-y-4" @submit="validateSubmit($event)">
                            @csrf
                            
                            <!-- Hidden Sales ID -->
                            <input type="hidden" name="sales_id" value="{{ $sale->id }}">

                            <!-- Amount -->
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block">
                                        Payment Amount ({{ $currencySymbol ?? '$' }}) <span class="text-danger">*</span>
                                    </label>
                                    <button type="button" @click="setFullAmount()" class="text-[10px] font-bold text-primary hover:underline">
                                        Pay Full Due ({{ $currencySymbol ?? '$' }}<span x-text="formatMoney(maxDue)"></span>)
                                    </button>
                                </div>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-black text-text-muted">{{ $currencySymbol ?? '$' }}</span>
                                    <input type="number"
                                           step="0.01"
                                           min="0.01"
                                           max="{{ $remainingDue }}"
                                           name="amount"
                                           x-model.number="amount"
                                           class="w-full bg-background dark:bg-slate-800/60 border border-border dark:border-dark-border rounded-xl py-3 pl-8 pr-3 text-base font-black text-text-primary dark:text-white outline-none focus:ring-2 focus:ring-primary-500 transition-all tabular-nums"
                                           :class="{'border-rose-300 dark:border-rose-700 text-rose-600': isOverpaying}"
                                           required>
                                </div>
                                
                                <!-- Overpayment Warning -->
                                <p x-show="isOverpaying" x-cloak class="text-[10px] text-danger font-bold mt-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    Amount cannot exceed remaining balance ({{ $currencySymbol ?? '$' }}<span x-text="formatMoney(maxDue)"></span>)
                                </p>
                            </div>

                            <!-- Payment Date -->
                            <div>
                                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">
                                    Payment Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       name="payment_date"
                                       value="{{ old('payment_date', date('Y-m-d')) }}"
                                       class="input-base"
                                       required>
                            </div>

                            <!-- Payment Type / Method (Searchable Dropdown) -->
                            <div>
                                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">
                                    Payment Method <span class="text-danger">*</span>
                                </label>
                                <x-searchable-select 
                                    name="payment_type" 
                                    :options="$paymentTypes" 
                                    labelKey="payment_type" 
                                    valueKey="payment_type" 
                                    placeholder="Select Payment Method" 
                                    :value="old('payment_type', 'Cash')" 
                                    required />
                            </div>

                            <!-- Deposit Account (Searchable Dropdown) -->
                            <div>
                                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">
                                    Deposit Account <span class="text-danger">*</span>
                                </label>
                                <x-searchable-select 
                                    name="account_id" 
                                    :options="$accounts" 
                                    labelKey="account_name" 
                                    valueKey="id" 
                                    subtextKey="account_code" 
                                    placeholder="Select Deposit Account" 
                                    :value="old('account_id', $accounts->first()->id ?? '')" 
                                    required />
                            </div>

                            <!-- Note -->
                            <div>
                                <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">
                                    Payment Note / Reference
                                </label>
                                <textarea name="payment_note"
                                          rows="2"
                                          placeholder="e.g. Cheque number, bank reference, or remarks..."
                                          class="input-base resize-none"></textarea>
                            </div>

                            <!-- LIVE SUMMARY PREVIEW BOX -->
                            <div class="p-3.5 rounded-xl bg-background dark:bg-slate-800/40 border border-border-light dark:border-dark-border space-y-2 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-text-muted font-medium">New Total Paid:</span>
                                    <span class="font-bold text-text-primary dark:text-slate-200">
                                        {{ $currencySymbol ?? '$' }}<span x-text="formatMoney(newPaidTotal)"></span>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-text-muted font-medium">New Remaining Due:</span>
                                    <span class="font-black" :class="newBalance <= 0 ? 'text-success' : 'text-danger'">
                                        {{ $currencySymbol ?? '$' }}<span x-text="formatMoney(newBalance)"></span>
                                    </span>
                                </div>
                                <div x-show="isFullSettlement" x-cloak class="pt-1.5 border-t border-border-light dark:border-dark-border flex items-center justify-center gap-1.5 text-success text-[10px] font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    This payment will fully settle this invoice.
                                </div>
                            </div>

                            <!-- SUBMIT BUTTON -->
                            <button type="submit"
                                    :disabled="isOverpaying || amount <= 0"
                                    class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-500/20 dark:shadow-none flex items-center justify-center gap-2 transform active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                Record Payment
                            </button>
                        </form>
                    @endif

                </div>
            </div>

        </div>
    </div>

    <!-- ALPINE.JS COMPONENT LOGIC -->
    <script>
        function receivePaymentApp() {
            const maxDue = {{ (float)$remainingDue }};
            const alreadyPaid = {{ (float)$sale->paid_amount }};
            const grandTotal = {{ (float)$sale->grand_total }};

            return {
                amount: maxDue > 0 ? maxDue : 0,
                maxDue: maxDue,
                alreadyPaid: alreadyPaid,
                grandTotal: grandTotal,

                get newBalance() {
                    const val = parseFloat(this.amount) || 0;
                    return Math.max(0, Math.round((this.maxDue - val) * 100) / 100);
                },

                get newPaidTotal() {
                    const val = parseFloat(this.amount) || 0;
                    return Math.round((this.alreadyPaid + val) * 100) / 100;
                },

                get isOverpaying() {
                    const val = parseFloat(this.amount) || 0;
                    return val > (this.maxDue + 0.001);
                },

                get isFullSettlement() {
                    const val = parseFloat(this.amount) || 0;
                    return Math.abs(val - this.maxDue) < 0.001 && this.maxDue > 0;
                },

                setFullAmount() {
                    this.amount = this.maxDue;
                },

                formatMoney(num) {
                    return (parseFloat(num) || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },

                validateSubmit(e) {
                    if (this.isOverpaying) {
                        e.preventDefault();
                        alert('Payment amount cannot exceed the balance due of {{ $currencySymbol ?? "$" }}' + this.formatMoney(this.maxDue));
                        return;
                    }
                    if (this.amount <= 0) {
                        e.preventDefault();
                        alert('Please enter a valid payment amount greater than zero.');
                        return;
                    }
                }
            };
        }
    </script>
</x-app-layout>
