<x-app-layout title="Purchase Return Invoice">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black tracking-tight flex items-center gap-2 text-slate-800 dark:text-white">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                        Purchase Return Invoice
                    </h1>
                </div>
                <p class="text-[10px] text-slate-400 font-medium uppercase tracking-widest flex items-center gap-2">
                    Debit Note & Supplier Return Details
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="font-mono text-rose-600 font-bold">{{ $return->return_code }}</span>
                </p>
            </div>
            
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1 text-slate-400 hover:text-primary-600 transition-colors">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 00-1.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    Home
                </a>
                <span class="text-slate-300 mx-1">></span>
                <a href="{{ route('purchase.returns') }}" class="text-slate-400 hover:text-primary-600 transition-colors">Purchase Returns</a>
                <span class="text-slate-300 mx-1">></span>
                <span class="text-rose-600">Return Details</span>
            </div>
        </div>

        <!-- MAIN RETURN INVOICE CARD -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-2xl overflow-hidden relative group transition-all duration-500">
            <div class="relative p-6 md:p-8 pb-4">
                <!-- TOP HEADER -->
                <div class="flex flex-col md:flex-row justify-between gap-8 mb-8">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-rose-600 rounded-xl flex items-center justify-center shadow-lg shadow-rose-200 dark:shadow-none">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-black italic tracking-tighter dark:text-white leading-none">COREVISYS<span class="text-rose-600">POS</span></h2>
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Debit Note / Return Note</p>
                            </div>
                        </div>
                        
                        <div class="pt-2">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Store / Warehouse</p>
                            <h3 class="text-base font-black dark:text-white capitalize leading-tight">{{ $return->warehouse->warehouse_name ?? 'Main Warehouse' }}</h3>
                            <p class="text-[11px] font-semibold text-slate-500 leading-relaxed max-w-xs">
                                {{ auth()->user()->store->store_name ?? 'Main Branch' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col md:items-end justify-between">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-900/50 rounded-xl">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-rose-600 dark:text-rose-400">Debit Note</span>
                        </div>

                        <div class="space-y-1 md:text-right mt-4">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Return Reference</p>
                            <h3 class="text-lg font-black font-mono tracking-tight text-slate-800 dark:text-white">#{{ $return->return_code }}</h3>
                            <p class="text-[11px] font-bold text-slate-500">Date: {{ \Carbon\Carbon::parse($return->return_date)->format('M d, Y') }}</p>
                            @if($return->purchase)
                                <p class="text-[11px] font-bold text-slate-500">Original Purchase: <a href="{{ route('purchase.invoice', $return->purchase->id) }}" class="text-primary-600 hover:underline font-mono">{{ $return->purchase->purchase_code }}</a></p>
                            @endif
                            @if($return->reference_no)
                                <p class="text-[10px] text-slate-400">Ref: {{ $return->reference_no }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- SUPPLIER INFO -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 rounded-2xl bg-slate-50/50 dark:bg-slate-800/30 border border-slate-100 dark:border-dark-border mb-6">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Returned To Supplier</p>
                        <h4 class="text-sm font-black text-slate-800 dark:text-white">{{ $return->supplier->supplier_name ?? 'N/A' }}</h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $return->supplier->mobile ?? '' }} {{ $return->supplier->email ? '• ' . $return->supplier->email : '' }}</p>
                        @if($return->supplier?->address)
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $return->supplier->address }}</p>
                        @endif
                    </div>
                    <div class="md:text-right">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Settlement Status</p>
                        @if($refundPayment && (float)$refundPayment->payment > 0)
                            <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 rounded-lg text-[10px] font-black uppercase tracking-wider border border-emerald-200 dark:border-emerald-800">
                                Cash / Bank Refund: <x-money value="{{ $refundPayment->payment }}" /> ({{ $refundPayment->payment_type }})
                            </span>
                        @else
                            <span class="inline-block px-3 py-1 bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 rounded-lg text-[10px] font-black uppercase tracking-wider border border-amber-200 dark:border-amber-800">
                                Adjusted Against Supplier Payable
                            </span>
                        @endif
                    </div>
                </div>

                <!-- ITEMS TABLE -->
                <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-dark-border mb-6">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border text-[9px] font-black uppercase tracking-widest text-slate-400">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Item Description</th>
                                <th class="px-4 py-3 text-center">Return Qty</th>
                                <th class="px-4 py-3 text-right">Unit Cost</th>
                                <th class="px-4 py-3 text-right">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border text-xs">
                            @foreach($return->items as $index => $item)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-4 py-3 text-slate-400 font-bold">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-800 dark:text-white">
                                        {{ $item->item->item_name ?? 'Unknown Item' }}
                                        <div class="text-[10px] text-slate-400 font-normal font-mono">{{ $item->item->item_code ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-black text-rose-600">{{ $item->return_qty }}</td>
                                    <td class="px-4 py-3 text-right font-bold tabular-nums"><x-money value="{{ $item->price_per_unit }}" /></td>
                                    <td class="px-4 py-3 text-right font-black tabular-nums text-rose-600"><x-money value="{{ $item->total_cost }}" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- SUMMARY TOTALS -->
                <div class="flex justify-end mb-8">
                    <div class="w-full max-w-xs space-y-2 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border">
                        <div class="flex justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
                            <span>Subtotal Return:</span>
                            <span class="tabular-nums"><x-money value="{{ $return->subtotal }}" /></span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-dark-border pt-2 flex justify-between text-sm font-black text-rose-600 dark:text-rose-400">
                            <span>Total Refund / Credit:</span>
                            <span class="tabular-nums"><x-money value="{{ $return->grand_total }}" /></span>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="flex flex-wrap justify-between items-center gap-4 pt-6 border-t border-slate-100 dark:border-dark-border">
                    <a href="{{ route('purchase.returns') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back to Returns
                    </a>

                    <div class="flex gap-2">
                        <button onclick="window.print()" class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-rose-200 dark:shadow-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Debit Note
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
