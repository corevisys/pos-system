<x-app-layout title="Purchase Invoice">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black tracking-tight flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        Purchase Invoice
                    </h1>
                </div>
                <p class="text-[10px] text-slate-400 font-medium uppercase tracking-widest flex items-center gap-2">
                    Invoice Details Review & Actions
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    Add/Update Invoice
                </p>
            </div>
            
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1 text-slate-400 hover:text-primary-600 transition-colors">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 00-1.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    Home
                </a>
                <span class="text-slate-300 mx-1">></span>
                <a href="{{ route('purchase.list') }}" class="text-slate-400 hover:text-primary-600 transition-colors">Purchase List</a>
                <span class="text-slate-300 mx-1">></span>
                <a href="{{ route('purchase.new') }}" class="text-slate-400 hover:text-primary-600 transition-colors">New Purchase</a>
                <span class="text-slate-300 mx-1">></span>
                <span class="text-primary-600">Invoice</span>
            </div>
        </div>

        <!-- MAIN INVOICE CARD -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-2xl overflow-hidden relative group transition-all duration-500 hover:shadow-primary-100 dark:hover:shadow-none">
            
            <!-- HEADER SECTION -->
            <div class="relative p-6 md:p-8 pb-4">
                <div class="flex flex-col md:flex-row justify-between gap-8 mb-12">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center shadow-lg shadow-primary-200 dark:shadow-none">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-black italic tracking-tighter dark:text-white leading-none">COREVISYS<span class="text-primary-600">POS</span></h2>
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Intelligent Business Core</p>
                            </div>
                        </div>
                        
                        <div class="pt-3">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">From</p>
                            <h3 class="text-base font-black dark:text-white capitalize leading-tight">{{ auth()->user()->name }}</h3>
                            <p class="text-[11px] font-semibold text-slate-500 leading-relaxed max-w-xs">
                                {{ auth()->user()->store->store_name ?? 'Premium Store Warehouse' }}<br>
                                {{ auth()->user()->store->address ?? 'Innovation Hub, Tech Street 101' }}
                            </p>
                        </div>
                    </div>

                    <div class="text-left md:text-right space-y-6">
                        <div>
                             <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Supplier Details</p>
                             <h3 class="text-base font-black dark:text-white leading-tight">{{ $purchase->supplier->supplier_name }}</h3>
                             <p class="text-[11px] font-semibold text-slate-500 leading-relaxed">
                                {{ $purchase->supplier->address }}<br>
                                Phone: {{ $purchase->supplier->mobile }}
                             </p>
                        </div>

                        <div>
                            <div class="inline-flex flex-col text-left">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5 whitespace-nowrap">Invoice Information</span>
                                <div class="px-3 py-2 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-dark-border space-y-1">
                                    <p class="text-xs font-black dark:text-white leading-tight">Invoice #<span class="text-primary-600">{{ $purchase->purchase_code }}</span></p>
                                    <p class="text-xs font-black dark:text-white">Date: <span class="text-slate-500">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d-m-Y') }}</span></p>
                                    <p class="text-xs font-black dark:text-white">Status: <span class="text-emerald-500">{{ $purchase->purchase_status }}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ITEMS TABLE -->
            <div class="px-2 md:px-6">
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-x-auto shadow-sm">
                    <table class="w-full text-left min-w-[800px]">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest w-10 text-center">#</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Item Name</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Price</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Qty</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Tax</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Tax Amt</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Discount</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Unit Cost</th>
                                <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            @foreach($purchase->items as $index => $item)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-4 py-3 text-center text-[10px] font-black text-slate-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-[10px] font-black dark:text-white leading-tight break-words max-w-[150px]">{{ $item->item->item_name }}</p>
                                    <p class="text-[8px] font-bold text-slate-400 font-mono tracking-tighter">{{ $item->item->item_code }}</p>
                                    @if($item->serials->count() > 0)
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach($item->serials as $serial)
                                                <span class="text-[7px] font-black bg-slate-100 dark:bg-slate-800 text-slate-500 px-1 py-0.5 rounded uppercase tracking-tighter border border-slate-200 dark:border-dark-border">
                                                    {{ $serial->serial_number }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px]">{{ format_currency($item->price_per_unit) }}</td>
                                <td class="px-4 py-3 text-center font-black tabular-nums text-[10px]">
                                    <span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 rounded-lg">{{ format_quantity($item->purchase_qty) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black uppercase text-slate-500 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-md whitespace-nowrap w-fit">{{ $item->item->tax->tax_name ?? 'No Tax' }}</span>
                                        <span class="text-[7px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">{{ $item->tax_type }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px]">{{ format_currency($item->tax_amt) }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px] text-rose-500 whitespace-nowrap">{{ $item->discount_amt > 0 ? format_currency($item->discount_amt) : '0' }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px]">{{ format_currency($item->unit_total_cost) }}</td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-[10px]">{{ format_currency($item->total_cost) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50/80 dark:bg-slate-800/80 font-black text-[10px] tabular-nums">
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-[9px] uppercase tracking-widest text-center">Total</td>
                                <td class="px-4 py-2.5 text-center text-[10px]">{{ format_quantity($purchase->items->sum('purchase_qty')) }}</td>
                                <td class="px-4 py-2.5"></td>
                                <td class="px-4 py-2.5 text-right text-[10px]">{{ format_currency($purchase->items->sum('tax_amt')) }}</td>
                                <td class="px-4 py-2.5 text-right text-[10px] text-rose-500">{{ format_currency($purchase->items->sum('discount_amt')) }}</td>
                                <td class="px-4 py-2.5 text-right text-[10px]"></td>
                                <td class="px-4 py-2.5 text-right text-[10px]">{{ format_currency($purchase->items->sum('total_cost')) }}</td>
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
                                <h3 class="text-sm font-black uppercase tracking-widest text-primary-600">Payments Information</h3>
                                <div class="h-px flex-1 bg-slate-100 dark:bg-dark-border"></div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-dark-border overflow-x-auto">
                                <table class="w-full text-left min-w-[500px]">
                                    <thead class="bg-primary-600 text-white">
                                        <tr>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest w-8 text-center">#</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Date</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Type</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest whitespace-nowrap">Account</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest">Note</th>
                                            <th class="px-3 py-2 text-[8px] font-black uppercase tracking-widest text-right whitespace-nowrap">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                                        <tr class="text-[10px] font-bold text-slate-600 dark:text-slate-300">
                                            <td class="px-3 py-2 text-center">1</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d-m-Y') }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-1.5 py-0.5 bg-primary-100 dark:bg-primary-500/10 text-primary-600 text-[8px] rounded-md">CASH</span>
                                            </td>
                                            <td class="px-3 py-2 italic font-medium">Main Business Account</td>
                                            <td class="px-3 py-2 text-slate-400 line-clamp-1">Paid during purchase</td>
                                            <td class="px-3 py-2 text-right font-black dark:text-white whitespace-nowrap">{{ format_currency($purchase->paid_amount) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-slate-100/50 dark:bg-slate-900/50">
                                        <tr>
                                            <td colspan="5" class="px-3 py-2 text-right text-[8px] font-black uppercase tracking-widest">Total Paid</td>
                                            <td class="px-3 py-2 text-right font-black text-xs text-primary-600">{{ format_currency($purchase->paid_amount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-5 bg-slate-50 dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-dark-border">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Discount on All</p>
                                <p class="text-sm font-black dark:text-white">{{ $purchase->discount_to_all_input }} {{ $purchase->discount_to_all_type == 'Percentage' ? '%' : 'Fixed' }}</p>
                                <p class="text-[9px] font-bold text-slate-400 underline decoration-primary-500/30 underline-offset-4 mt-1">Calculated: {{ format_currency($purchase->tot_discount_to_all_amt) }}</p>
                            </div>
                            <div class="p-5 bg-slate-50 dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-dark-border">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Purchase Note</p>
                                <p class="text-xs font-semibold text-slate-500 italic">{{ $purchase->purchase_note ?? 'No additional notes provided.' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: TOTALS SUMMARY -->
                    <div class="flex flex-col justify-end">
                        <div class="bg-slate-900 dark:bg-slate-800/40 rounded-3xl p-6 md:p-8 text-white relative overflow-hidden shadow-2xl space-y-3">
                            <!-- Background decoration -->
                            <div class="absolute -right-16 -bottom-16 w-56 h-56 bg-primary-600/20 rounded-full blur-[80px]"></div>
                            
                            <div class="flex justify-between items-center relative z-10 border-b border-white/10 pb-3">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Net Subtotal</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($purchase->subtotal) }}</span>
                            </div>

                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Item Discount</span>
                                <span class="text-base font-black tabular-nums text-rose-400">- {{ format_currency($purchase->items->sum('discount_amt')) }}</span>
                            </div>

                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Total Tax</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($purchase->items->sum('tax_amt') + $purchase->other_charges_amt) }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Other Charges</span>
                                <span class="text-base font-black tabular-nums">{{ format_currency($purchase->other_charges_input) }}</span>
                            </div>

                            <div class="flex justify-between items-center relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 text-rose-300">Global Discount</span>
                                <span class="text-base font-black tabular-nums text-rose-400">- {{ format_currency($purchase->tot_discount_to_all_amt) }}</span>
                            </div>

                            <div class="flex justify-between items-center relative z-10 pb-3 border-b border-white/5">
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Round Off</span>
                                <span class="text-base font-black tabular-nums text-slate-300">{{ ($purchase->round_off ?? 0) >= 0 ? '+' : '-' }} {{ format_currency(abs($purchase->round_off ?? 0)) }}</span>
                            </div>

                            <div class="pt-4 flex justify-between items-end relative z-10">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-primary-400 mb-0.5">Grand Total</p>
                                    <p class="text-[8px] text-slate-500 font-bold uppercase tracking-widest leading-none whitespace-nowrap">Incl. VAT & Taxes</p>
                                </div>
                                <span class="text-2xl font-black tabular-nums tracking-tighter text-primary-500">{{ format_currency($purchase->grand_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS FOOTER -->
                <div class="mt-16 flex flex-wrap justify-between items-center gap-6">
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('purchase.edit', $purchase->id) }}" class="px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-emerald-200 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            Edit
                        </a>
                        <a href="{{ route('purchase.barcode', $purchase->id) }}" class="px-6 py-3.5 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-rose-200 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                            Barcode
                        </a>
                        <a href="{{ route('purchase.return', $purchase->id) }}" class="px-6 py-3.5 bg-primary-600 hover:bg-primary-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-primary-200 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                            Purchase Return
                        </a>
                    </div>

                    <div class="flex gap-3">
                         <button onclick="window.print()" class="px-8 py-3.5 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-orange-100 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print
                        </button>
                        <button class="px-8 py-3.5 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-rose-100 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- DECORATIVE GLOWS -->
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-primary-500/10 rounded-full blur-[120px] pointer-events-none group-hover:bg-primary-500/20 transition-all duration-700"></div>
            <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none group-hover:bg-emerald-500/20 transition-all duration-700"></div>
        </div>
    </div>

    <!-- PRINT STYLES -->
    <style>
        @media print {
            body * { visibility: hidden; }
            .bg-white, .dark\:bg-dark-card { background: white !important; color: black !important; }
            .bg-slate-900 { background: var(--color-dark-card) !important; color: white !important; }
            .bg-primary-600 { background: var(--color-primary-600) !important; color: white !important; }
            .text-primary-600 { color: var(--color-primary-600) !important; }
            .text-emerald-500 { color: var(--color-emerald-500) !important; }
            .text-rose-500 { color: var(--color-rose-500) !important; }
            .shadow-2xl, .shadow-lg { box-shadow: none !important; }
            main, .p-1\.5, .p-8 { margin: 0 !important; padding: 0 !important; }
            .fixed, header, footer, .flex-wrap, .absolute { display: none !important; }
            .rounded-\[2\.5rem\], .rounded-\[2rem\], .rounded-3xl { border-radius: 0 !important; }
            .group-hover\:shadow-primary-100 { box-shadow: none !important; }
            .bg-white, .bg-slate-50 { border: 1px solid var(--color-slate-200) !important; }
            
            /* Show only the invoice container */
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
