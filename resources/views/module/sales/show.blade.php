<x-app-layout title="Sale Details">
    @php
        $totalReturnedAmount = $sale->returns->sum('grand_total');
        $totalRefundedPaid = $sale->returns->sum('paid_amount');
        $netRawDue = ($sale->grand_total - $totalReturnedAmount) - ($sale->paid_amount - $totalRefundedPaid);
        $netBalanceDue = max(0, $netRawDue);
        // Overpaid/credit balance: when refunds + payments exceed the post-return total,
        // surface the excess instead of silently clipping the due to zero.
        $creditBalance = max(0, -$netRawDue);

        $returnedItemQtys = $sale->returnItems->groupBy('item_id')->map->sum('return_qty');
        $hasReturnableQty = $sale->items->contains(function ($item) use ($returnedItemQtys) {
            $alreadyReturned = (float) ($returnedItemQtys[$item->item_id] ?? 0);
            return ((float) $item->sales_qty - $alreadyReturned) > 0.0001;
        });
    @endphp
    <div>
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Sale Details: <span class="text-primary-600">{{ $sale->sales_code }}</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px]">Dashboard</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('sales.list') }}" class="hover:text-primary-600 transition-colors text-[10px]">Sales List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold">View Sale</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($sale->payment_status !== 'Paid' && $netBalanceDue > 0)
                <a href="{{ route('sales.payments.receive', $sale->id) }}" class="px-3 py-2 bg-emerald-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Receive Payment
                </a>
                @endif
                @if($hasReturnableQty)
                <a href="{{ route('sales.return.create', $sale->id) }}" class="px-3 py-2 bg-rose-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v4a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2h4m8 0V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h.01M8 20h.01M11 20h.01M14 20h.01M17 20h.01M4 16H4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z"></path></svg>
                    Create Return
                </a>
                @endif
                <a href="{{ route('sales.payments', ['search' => $sale->sales_code]) }}" class="px-3 py-2 bg-indigo-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    View Payments
                </a>
                <a href="{{ route('sales.invoice', $sale->id) }}" target="_blank" class="px-3 py-2 bg-primary-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Invoice
                </a>
                <a href="{{ route('sales.pos') }}?sale_id={{ $sale->id }}" class="px-3 py-2 bg-slate-800 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-lg shadow-slate-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Sale
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Left Side: Sale Info & Items -->
            <div class="lg:col-span-2 space-y-4">
                <!-- CLIENT & STORE INFO -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Customer Information</p>
                        <h3 class="text-base font-black dark:text-white">{{ $sale->customer?->customer_name ?? 'Walk-in Customer' }}</h3>
                        <p class="text-xs text-slate-500 font-medium mt-1">
                            <span class="text-slate-400">Phone:</span> {{ $sale->customer?->mobile ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-slate-500 font-medium">
                            <span class="text-slate-400">Email:</span> {{ $sale->customer?->email ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-slate-500 font-medium">
                            <span class="text-slate-400">Address:</span> {{ $sale->customer?->address ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-dark-card p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Sale Summary</p>
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-slate-500">Sales Date</span>
                                <span class="font-bold">{{ date('d-M-Y', strtotime($sale->sales_date)) }}</span>
                            </div>
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-slate-500">Warehouse</span>
                                <span class="font-bold">{{ $sale->warehouse?->warehouse_name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between text-xs font-medium items-center">
                                <span class="text-slate-500">Payment Status</span>
                                <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest 
                                    @if($sale->payment_status === 'Paid') bg-emerald-50 text-emerald-600
                                    @elseif($sale->payment_status === 'Partial') bg-orange-50 text-orange-600
                                    @else bg-rose-50 text-rose-600 @endif">
                                    {{ $sale->payment_status }}
                                </span>
                            </div>
                            <div class="flex justify-between text-xs font-medium">
                                <span class="text-slate-500">Sales Created By</span>
                                <span class="font-bold underline decoration-primary-200">{{ $sale->user?->name ?? 'System' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ITEM LIST -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Itemized List</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 dark:bg-slate-100/5 border-b border-slate-50 dark:border-dark-border">
                                <tr>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">#</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Item Name</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Price ({{ $currencySymbol }})</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Qty</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                                @foreach($sale->items as $index => $item)
                                <tr class="hover:bg-slate-50/30 transition-colors group">
                                    <td class="px-4 py-3 text-[10px] font-bold text-slate-400">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-black text-[11px] group-hover:text-primary-600 transition-colors">{{ $item->item?->item_name ?? 'Item Deleted' }}</div>
                                        <div class="flex flex-col gap-0.5 mt-0.5">
                                            @if($item->item?->variant_id && $item->item?->variant)
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[8px] text-white bg-slate-800 px-1.5 py-0.5 rounded font-black uppercase">{{ $item->item->variant->variant_name }}</span>
                                                    <span class="text-[9px] text-slate-400 font-bold italic">{{ $item->item->item_code }}</span>
                                                </div>
                                            @else
                                                <span class="text-[9px] text-slate-400 font-bold italic">{{ $item->item?->item_code ?? 'N/A' }}</span>
                                            @endif
                                            
                                            @php
                                                $itemSerials = $sale->serials->where('item_id', $item->item_id);
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
                                    <td class="px-4 py-3 text-[10px] font-black text-right tabular-nums">{{ format_currency($item->price_per_unit) }}</td>
                                    <td class="px-4 py-3 text-[10px] font-black text-center tabular-nums">
                                        <span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-slate-600 dark:text-slate-400">{{ format_quantity($item->sales_qty) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[10px] font-black text-right tabular-nums text-primary-600">{{ format_currency($item->total_cost) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PAYMENT HISTORY -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Payment History</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 dark:bg-slate-100/5 border-b border-slate-50 dark:border-dark-border">
                                <tr>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Payment Type</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Account</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                                @forelse($sale->payments as $payment)
                                <tr class="hover:bg-emerald-50/10 transition-colors">
                                    <td class="px-4 py-3 text-[10px] font-bold">{{ date('d-M-Y', strtotime($payment->payment_date)) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="text-[10px] font-black uppercase text-slate-600">{{ $payment->payment_type }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[10px] font-medium text-slate-500">{{ $payment->account->account_name ?? 'N/A' }}</td>
                                     <td class="px-4 py-3 text-[10px] font-black text-right tabular-nums text-emerald-600">{{ format_currency($payment->payment) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-[10px] text-slate-400 font-bold italic">No payments recorded yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($sale->returns->isNotEmpty())
                <!-- SALES RETURNS HISTORY -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-rose-50/20 dark:bg-rose-900/10 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-rose-500/10 text-rose-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v4a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2h4m8 0V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h.01M8 20h.01M11 20h.01M14 20h.01M17 20h.01M4 16H4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z"></path></svg>
                            </span>
                            <p class="text-[10px] font-black text-rose-600 uppercase tracking-widest">Sales Returns History</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-rose-100 dark:bg-rose-900/30 text-rose-600">
                            {{ $sale->returns->count() }} {{ \Illuminate\Support\Str::plural('Return', $sale->returns->count()) }}
                        </span>
                    </div>
                    
                    <div class="divide-y divide-slate-100 dark:divide-dark-border">
                        @foreach($sale->returns as $ret)
                        <div class="p-4 space-y-3">
                            <!-- Return Header Info -->
                            <div class="flex flex-wrap justify-between items-center gap-2 pb-2 border-b border-slate-50 dark:border-dark-border">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('sales.return.show', $ret->id) }}" class="text-xs font-black text-rose-600 hover:underline flex items-center gap-1">
                                        <span>{{ $ret->return_code }}</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                    </a>
                                    <span class="text-slate-300 dark:text-slate-600">•</span>
                                    <span class="text-[10px] font-bold text-slate-500">{{ date('d-M-Y', strtotime($ret->return_date)) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600">
                                        {{ $ret->return_status ?? 'Completed' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest 
                                        @if($ret->payment_status === 'Paid') bg-emerald-50 text-emerald-600
                                        @elseif($ret->payment_status === 'Partial') bg-orange-50 text-orange-600
                                        @else bg-rose-50 text-rose-600 @endif">
                                        Refund: {{ $ret->payment_status }}
                                    </span>
                                </div>
                            </div>

                            <!-- Returned Items Table -->
                            <div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-dark-border">
                                <table class="w-full text-left">
                                    <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                                        <tr>
                                            <th class="px-3 py-2 text-[8.5px] font-black text-slate-400 uppercase tracking-widest">#</th>
                                            <th class="px-3 py-2 text-[8.5px] font-black text-slate-400 uppercase tracking-widest">Returned Product</th>
                                            <th class="px-3 py-2 text-[8.5px] font-black text-slate-400 uppercase tracking-widest text-right">Unit Price</th>
                                            <th class="px-3 py-2 text-[8.5px] font-black text-slate-400 uppercase tracking-widest text-center">Qty</th>
                                            <th class="px-3 py-2 text-[8.5px] font-black text-slate-400 uppercase tracking-widest text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                                        @foreach($ret->items as $rIndex => $rItem)
                                        <tr class="hover:bg-slate-50/30 transition-colors">
                                            <td class="px-3 py-2 text-[9px] font-bold text-slate-400">{{ $rIndex + 1 }}</td>
                                            <td class="px-3 py-2">
                                                <div class="font-black text-[10px]">{{ $rItem->item?->item_name ?? 'Item Deleted' }}</div>
                                                <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                                    @if($rItem->item?->variant_id && $rItem->item?->variant)
                                                        <span class="text-[7.5px] text-white bg-slate-800 px-1 py-0.2 rounded font-black uppercase">{{ $rItem->item->variant->variant_name }}</span>
                                                    @endif
                                                    <span class="text-[8.5px] text-slate-400 font-bold italic">{{ $rItem->item?->item_code ?? 'N/A' }}</span>
                                                </div>
                                                @if(!empty($rItem->returned_serials))
                                                    @php
                                                        $serials = json_decode($rItem->returned_serials, true);
                                                    @endphp
                                                    @if(is_array($serials) && count($serials) > 0)
                                                        <div class="flex flex-wrap gap-1 mt-1">
                                                            @foreach($serials as $sn)
                                                                <span class="text-[7.5px] font-black uppercase bg-rose-50 text-rose-600 px-1 py-0.2 rounded border border-rose-100">
                                                                    SN: {{ $sn }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-[9.5px] font-black text-right tabular-nums">{{ format_currency($rItem->price_per_unit) }}</td>
                                            <td class="px-3 py-2 text-[9.5px] font-black text-center tabular-nums">
                                                <span class="bg-rose-50 text-rose-600 dark:bg-rose-900/30 px-1.5 py-0.5 rounded">{{ format_quantity($rItem->return_qty) }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-[9.5px] font-black text-right tabular-nums text-rose-600">{{ format_currency($rItem->total_cost) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Return Financial & Refund Info -->
                            <div class="flex flex-wrap justify-between items-center gap-2 pt-1 text-[10px]">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($ret->payments->isNotEmpty())
                                        @foreach($ret->payments as $rPay)
                                            <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold text-slate-600 dark:text-slate-300 flex items-center gap-1">
                                                <span>Refund ({{ $rPay->payment_type }} - {{ $rPay->account->account_name ?? 'N/A' }}):</span>
                                                <span class="font-black text-rose-600">{{ format_currency($rPay->payment) }}</span>
                                            </span>
                                        @endforeach
                                    @elseif($ret->paid_amount > 0)
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold text-slate-600 dark:text-slate-300">
                                            Refund Paid: <span class="font-black text-rose-600">{{ format_currency($ret->paid_amount) }}</span>
                                        </span>
                                    @else
                                        <span class="text-[9px] font-bold text-slate-400 italic">No cash refund issued (Due offset).</span>
                                    @endif
                                </div>

                                <div class="text-right">
                                    <span class="text-slate-400 font-bold text-[9px] uppercase tracking-wider">Return Total:</span>
                                    <span class="text-xs font-black text-rose-600 tabular-nums ml-1">{{ format_currency($ret->grand_total) }}</span>
                                </div>
                            </div>

                            @if($ret->return_note)
                                <p class="text-[9.5px] text-slate-500 italic bg-slate-50 dark:bg-slate-800/40 p-2 rounded-lg border border-slate-100 dark:border-dark-border">
                                    <span class="font-bold text-slate-600 dark:text-slate-400">Note:</span> {{ $ret->return_note }}
                                </p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Right Side: Calculations -->
            <div class="space-y-4">
                <div class="bg-white dark:bg-dark-card p-5 rounded-2xl border border-slate-100 dark:border-dark-border shadow-md sticky top-20">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-50 dark:border-dark-border pb-2">Financial Summary</p>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-slate-500">Subtotal</span>
                             <span class="text-xs font-black">{{ format_currency($sale->subtotal) }}</span>
                        </div>
                        @if($sale->tot_discount_to_all_amt > 0)
                        <div class="flex justify-between items-center text-rose-500 p-2 bg-rose-50/50 dark:bg-rose-900/10 rounded-lg">
                            <span class="text-[10px] font-black uppercase tracking-tight">Invoice Discount</span>
                             <span class="text-xs font-black">- {{ format_currency($sale->tot_discount_to_all_amt) }}</span>
                        </div>
                        @endif
                        @if($sale->coupon_amt > 0)
                        <div class="flex justify-between items-center text-emerald-600 p-2 bg-emerald-50/50 dark:bg-emerald-900/10 rounded-lg">
                            <span class="text-[10px] font-black uppercase tracking-tight">Coupon Discount</span>
                             <span class="text-xs font-black">- {{ format_currency($sale->coupon_amt) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-slate-500">Other Charges / Tax</span>
                             <span class="text-xs font-black">{{ format_currency($sale->round_off + $sale->other_charges_amt) }}</span>
                        </div>
                        <hr class="border-slate-100 dark:border-dark-border">
                        <div class="flex justify-between items-center pt-1">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Grand Total</span>
                                @if($sale->payment_status === 'Paid' && $netBalanceDue <= 0.001)
                                    <span class="text-[8px] font-black uppercase text-emerald-500 bg-emerald-50 px-1 rounded flex items-center gap-1 w-fit">
                                        <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        Fully Paid
                                    </span>
                                @endif
                            </div>
                             <span class="text-2xl font-black text-primary-600 tabular-nums leading-none">{{ format_currency($sale->grand_total) }}</span>
                        </div>

                        @if($sale->returns->isNotEmpty())
                        <div class="flex justify-between items-center text-amber-600 dark:text-amber-400 p-2 bg-amber-50/50 dark:bg-amber-900/10 rounded-lg">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-tight">Returned Items</span>
                                @if($totalRefundedPaid > 0)
                                    <span class="text-[8px] font-bold text-slate-400">(Refund Paid: {{ format_currency($totalRefundedPaid) }})</span>
                                @endif
                            </div>
                            <span class="text-xs font-black">- {{ format_currency($totalReturnedAmount) }}</span>
                        </div>
                        @endif
                        
                        <div class="grid grid-cols-2 gap-2 mt-4">
                            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/10 rounded-xl border border-emerald-100 dark:border-emerald-900/20">
                                <p class="text-[8px] font-black text-emerald-600 uppercase tracking-widest">Received</p>
                                 <p class="text-xs font-black text-emerald-600 tabular-nums">{{ format_currency($sale->paid_amount) }}</p>
                            </div>
                            <div class="p-2 {{ $creditBalance > 0 ? 'bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-800/40' : 'bg-rose-50 dark:bg-rose-900/10 rounded-xl border border-rose-100 dark:border-rose-900/20' }} text-right">
                                <p class="text-[8px] font-black {{ $creditBalance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600' }} uppercase tracking-widest">
                                    {{ $creditBalance > 0 ? 'Credit Balance (Overpaid)' : 'Balance Due' }}
                                </p>
                                 <p class="text-xs font-black {{ $creditBalance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600' }} tabular-nums">
                                     {{ $creditBalance > 0 ? format_currency($creditBalance) : format_currency($netBalanceDue) }}
                                 </p>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-200 dark:border-dark-border mt-4">
                            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Amount In Words</p>
                            <p class="text-[10px] font-bold text-slate-600 dark:text-slate-300 leading-tight italic">
                                {{ $amount_in_words }}
                            </p>
                        </div>
                    </div>
                    
                    @if($sale->sale_note)
                    <div class="mt-6 p-3 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-100 dark:border-amber-900/20">
                        <p class="text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Sale Note
                        </p>
                        <p class="text-[10px] text-amber-700 dark:text-amber-400 font-medium italic leading-relaxed">{{ $sale->sale_note }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
