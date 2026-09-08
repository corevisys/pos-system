<x-app-layout title="Sales Return Details">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                        <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v4a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2h4m8 0V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h.01M8 20h.01M11 20h.01M14 20h.01M17 20h.01M4 16H4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z"></path></svg>
                        Sales Return Details
                    </h1>
                </div>
                <p class="text-[10px] text-text-muted font-medium uppercase tracking-widest flex items-center gap-2">
                    Return Invoice Review & Actions
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    {{ $return->return_code }}
                </p>
            </div>
            
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1 text-text-muted hover:text-primary transition-colors">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 00-1.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001 1h2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    Home
                </a>
                <span class="text-slate-300 mx-1">></span>
                <a href="{{ route('sales.returns') }}" class="text-text-muted hover:text-primary transition-colors">Returns List</a>
                <span class="text-slate-300 mx-1">></span>
                <span class="text-success">Return Details</span>
            </div>
        </div>

        <!-- MAIN RETURN CARD -->
        <div class="card overflow-hidden relative group transition-shadow duration-500 hover:shadow-card-hover">
            
            <!-- HEADER SECTION -->
            <div class="relative p-6 md:p-8 pb-4">
                <div class="flex flex-col md:flex-row justify-between gap-8 mb-12">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success rounded-xl flex items-center justify-center shadow-lg shadow-success/20 dark:shadow-none">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path></svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-black italic tracking-tighter text-text-primary dark:text-dark-text leading-none">COREVISYS<span class="text-success">POS</span></h2>
                                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest">Sales Return Invoice</p>
                            </div>
                        </div>
                        
                        <div class="pt-3">
                            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest mb-0.5">Store / Warehouse</p>
                            <h3 class="text-base font-black text-text-primary dark:text-dark-text capitalize leading-tight">{{ $return->warehouse->warehouse_name ?? 'Main Warehouse' }}</h3>
                            <p class="text-[11px] font-semibold text-text-muted leading-relaxed max-w-xs">
                                {{ $return->warehouse->address ?? 'Warehouse Location' }}<br>
                                Processed By: {{ $return->user->name ?? 'System' }}
                            </p>
                        </div>
                    </div>

                    <div class="text-left md:text-right space-y-6">
                        <div>
                             <p class="text-[9px] font-black text-text-muted uppercase tracking-widest mb-0.5">Return From Customer</p>
                             <h3 class="text-base font-black text-text-primary dark:text-dark-text leading-tight">{{ $return->customer->customer_name ?? 'Walk-in customer' }}</h3>
                             <p class="text-[11px] font-semibold text-text-muted leading-relaxed">
                                {{ $return->customer->address ?? '' }}<br>
                                Phone: {{ $return->customer->mobile ?? '' }}
                             </p>
                        </div>

                        <div>
                            <div class="inline-flex flex-col text-left">
                                <span class="text-[9px] font-black text-text-muted uppercase tracking-widest mb-0.5 whitespace-nowrap">Return Information</span>
                                <div class="px-3 py-2 bg-background dark:bg-slate-800 rounded-xl border border-border-light dark:border-dark-border space-y-1">
                                    <p class="text-xs font-black text-text-primary dark:text-dark-text leading-tight">Return #<span class="text-success">{{ $return->return_code }}</span></p>
                                    <p class="text-xs font-black text-text-primary dark:text-dark-text leading-tight">Original Sale #
                                        @if($return->sales_id)
                                            <a href="{{ route('sales.show', $return->sales_id) }}" class="text-primary hover:underline">
                                                {{ $return->sale->sales_code ?? 'SA-#' . $return->sales_id }}
                                            </a>
                                        @else
                                            <span class="text-text-muted">---</span>
                                        @endif
                                    </p>
                                    <p class="text-xs font-black text-text-primary dark:text-dark-text">Date: <span class="text-text-secondary">{{ \Carbon\Carbon::parse($return->return_date)->format('d-m-Y') }}</span></p>
                                    <p class="text-xs font-black text-text-primary dark:text-dark-text">Payment Status:
                                        <x-badge color="{{ $return->payment_status === 'Paid' ? 'success' : ($return->payment_status === 'Partial' ? 'warning' : 'danger') }}">{{ $return->payment_status }}</x-badge>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ITEMS TABLE -->
            <div class="px-2 md:px-6">
                <div class="card overflow-x-auto p-0">
                    <table class="w-full text-left min-w-[800px]">
                        <thead class="bg-background dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest w-10 text-center">#</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Item Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Price ({{ $currencySymbol }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Return Qty</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Tax Amt ({{ $currencySymbol }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Discount ({{ $currencySymbol }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Unit Total ({{ $currencySymbol }})</th>
                                <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-dark-border">
                            @foreach($return->items as $index => $item)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-4 py-3 text-center text-[10px] font-black text-text-muted">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-[10px] font-black text-text-primary dark:text-dark-text leading-tight break-words max-w-[200px]">{{ $item->item->item_name }}</p>
                                    <p class="text-[8px] font-bold text-text-muted font-mono tracking-tighter">{{ $item->item->item_code }}</p>
                                    @php
                                        $serials = $item->returned_serials ? json_decode($item->returned_serials, true) : [];
                                    @endphp
                                    @if(!empty($serials))
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach($serials as $serial)
                                                <x-badge color="success">{{ $serial }}</x-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-text-primary dark:text-dark-text">{{ format_currency($item->price_per_unit) }}</td>
                                <td class="px-4 py-3 text-center font-black tabular-nums text-[10px]">
                                    <x-badge color="danger">{{ format_quantity($item->return_qty) }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-text-primary dark:text-dark-text">{{ format_currency($item->tax_amt) }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-danger whitespace-nowrap">{{ $item->discount_amt > 0 ? format_currency($item->discount_amt) : '0' }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-text-primary dark:text-dark-text">{{ format_currency($item->price_per_unit + $item->tax_amt - $item->discount_amt) }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-text-primary dark:text-dark-text">{{ format_currency($item->total_cost) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-background/80 dark:bg-slate-800/80 font-black text-[10px] tabular-nums border-t border-border-light dark:border-dark-border">
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-[9px] uppercase tracking-widest text-center text-text-secondary">Total Return</td>
                                <td class="px-4 py-2.5 text-center text-[10px]">{{ format_quantity($return->items->sum('return_qty')) }}</td>
                                <td class="px-4 py-2.5 text-right text-[10px]">{{ format_currency($return->items->sum('tax_amt')) }}</td>
                                <td class="px-4 py-2.5 text-right text-[10px] text-danger">{{ format_currency($return->items->sum('discount_amt')) }}</td>
                                <td class="px-4 py-2.5"></td>
                                <td class="px-4 py-2.5 text-right text-[10px]">{{ format_currency($return->items->sum('total_cost')) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- LOWER SECTIONS -->
            <div class="p-6 md:p-8 pt-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- LEFT COLUMN: PAYMENTS & NOTES -->
                    <div class="space-y-8">
                        <div>
                            <div class="flex items-center gap-2 mb-4">
                                <h3 class="text-sm font-black uppercase tracking-widest text-success">Payments (Return Released)</h3>
                                <div class="h-px flex-1 bg-border-light dark:bg-dark-border"></div>
                            </div>
                            <div class="card overflow-x-auto p-0">
                                <table class="w-full text-left min-w-[500px]">
                                    <thead class="bg-success text-white">
                                        <tr>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest w-8 text-center">#</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Date</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Type</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Account</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest text-right whitespace-nowrap">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                        @foreach($return->payments as $pIndex => $payment)
                                        <tr class="text-[10px] font-bold text-text-secondary dark:text-slate-300">
                                            <td class="px-3 py-2 text-center">{{ $pIndex + 1 }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y') }}</td>
                                            <td class="px-3 py-2">
                                                <x-badge color="success">{{ strtoupper($payment->payment_type) }}</x-badge>
                                            </td>
                                            <td class="px-3 py-2 italic font-medium">{{ $payment->account->account_name ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 text-right font-black text-text-primary dark:text-dark-text whitespace-nowrap">{{ format_currency($payment->payment) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-background/60 dark:bg-slate-900/50">
                                        <tr>
                                            <td colspan="4" class="px-3 py-2 text-right text-[8px] font-black uppercase tracking-widest text-text-muted">Total Released</td>
                                            <td class="px-3 py-2 text-right font-black text-xs text-success">{{ format_currency($return->paid_amount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="card p-5">
                            <p class="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1">Return Note</p>
                            <p class="text-xs font-semibold text-text-muted italic">{{ $return->return_note ?? 'No return reason provided.' }}</p>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: TOTALS SUMMARY -->
                    <div class="flex flex-col justify-end">
                        <div class="bg-navy dark:bg-slate-800/40 rounded-2xl p-6 md:p-8 text-white relative overflow-hidden shadow-card space-y-3">
                            <!-- Background decoration -->
                            <div class="absolute -right-16 -bottom-16 w-56 h-56 bg-success/20 rounded-full blur-[80px]"></div>
                            
                            <div class="flex justify-between items-center relative z-10 border-b border-white/10 pb-3">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Return Subtotal</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($return->subtotal) }}</span>
                            </div>

                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Return Tax</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($return->items->sum('tax_amt')) }}</span>
                            </div>

                            @if($return->other_charges_amt > 0)
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Other Charges (+)</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($return->other_charges_amt) }}</span>
                            </div>
                            @endif

                            @if($return->tot_discount_to_all_amt > 0)
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Discount to All (-)</span>
                                <span class="text-base font-black tabular-nums text-rose-400">{{ format_currency($return->tot_discount_to_all_amt) }}</span>
                            </div>
                            @endif

                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Round Off</span>
                                <span class="text-base font-black tabular-nums text-slate-300">{{ ($return->round_off ?? 0) >= 0 ? '+' : '-' }} {{ format_currency(abs($return->round_off ?? 0)) }}</span>
                            </div>

                            <div class="pt-4 flex justify-between items-end relative z-10">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-success mb-0.5">Grand Total Return</p>
                                    <p class="text-[8px] text-slate-500 font-bold uppercase tracking-widest leading-none whitespace-nowrap">Amount to Refund</p>
                                </div>
                                <span class="text-2xl font-black tabular-nums tracking-tighter text-success">{{ format_currency($return->grand_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS FOOTER -->
                <div class="mt-16 flex flex-wrap justify-between items-center gap-6">
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('sales.returns') }}" class="btn-secondary !px-6 !py-3.5 !text-[10px] font-black uppercase tracking-widest">
                            Back to List
                        </a>
                        <form action="{{ route('sales.return.delete', $return->id) }}" method="POST" onsubmit="return confirm('Revert stock and delete this return?')" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger !px-6 !py-3.5 !text-[10px] font-black uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Delete Return
                            </button>
                        </form>
                    </div>

                    <div class="flex gap-3">
                         <button onclick="window.print()" class="px-8 py-3.5 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-orange-100 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Return
                        </button>
                    </div>
                </div>
            </div>

            <!-- DECORATIVE GLOWS -->
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none group-hover:bg-emerald-500/20 transition-all duration-700"></div>
            <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none group-hover:bg-indigo-500/20 transition-all duration-700"></div>
        </div>
    </div>

    <!-- PRINT STYLES -->
    <style>
        @media print {
            body * { visibility: hidden; }
            .bg-white, .dark\:bg-dark-card { background: white !important; color: black !important; }
            .bg-slate-900 { background: var(--color-dark-card) !important; color: white !important; }
            .bg-emerald-600 { background: var(--color-emerald-600) !important; color: white !important; }
            .text-emerald-600 { color: var(--color-emerald-600) !important; }
            .text-rose-600 { color: var(--color-rose-600) !important; }
            .shadow-2xl, .shadow-lg { box-shadow: none !important; }
            main, .p-1\.5, .p-8 { margin: 0 !important; padding: 0 !important; }
            .fixed, header, footer, .flex-wrap, .absolute { display: none !important; }
            .rounded-\[2\.5rem\], .rounded-\[2rem\], .rounded-3xl { border-radius: 0 !important; }
            .bg-white, .bg-slate-50 { border: 1px solid var(--color-slate-200) !important; }
            
            /* Show only the return card container */
            div.bg-white.dark\:bg-dark-card {
                visibility: visible;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
            }
            div.bg-white.dark\:bg-dark-card * {
                visibility: visible;
            }
        }
    </style>
</x-app-layout>
