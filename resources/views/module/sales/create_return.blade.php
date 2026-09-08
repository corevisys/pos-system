<x-app-layout title="Create Sales Return">
    <div x-data="salesReturnForm()">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-success-light text-success">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v4a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2h4m8 0V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m4 6h.01M5 20h.01M8 20h.01M11 20h.01M14 20h.01M17 20h.01M4 16H4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4z"></path></svg>
                    </div>
                    Sales Return
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-bold mt-1">
                    <span class="text-[10px] uppercase tracking-widest bg-background dark:bg-slate-800 px-2 py-0.5 rounded-md text-text-secondary italic">Original Sale: {{ $sale->sales_code }}</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('sales.list') }}" class="btn-secondary !px-4 !py-2 !text-[10px] font-black uppercase tracking-widest">
                    Back to List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <!-- MAIN AREA (CART) -->
            <div class="col-span-12 lg:col-span-8 flex flex-col gap-4">
                
                <!-- ITEMS TABLE CARD -->
                <div class="card overflow-hidden p-0">
                    <div class="p-4 border-b border-border-light dark:border-dark-border bg-background/40 dark:bg-white/5 flex justify-between items-center">
                        <h2 class="text-[11px] font-black uppercase tracking-widest text-text-muted italic">Return Cart Items</h2>
                        <span class="text-[10px] font-black text-primary bg-primary-light dark:bg-primary/15 px-2 py-1 rounded-lg" x-text="'Total Items to Return: ' + form.items.filter(i => i.return_qty > 0).length"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left order-collapse">
                            <thead class="bg-background/50 dark:bg-slate-800/50 border-b border-border-light dark:border-dark-border">
                                <tr>
                                    <th class="px-6 py-4 text-[9px] font-black text-text-muted uppercase tracking-widest min-w-[200px]">Item Description</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-text-muted uppercase tracking-widest">Qty</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-text-muted uppercase tracking-widest">Ret’d</th>
                                    <th class="px-4 py-3 text-[10px] font-black text-text-muted uppercase tracking-widest text-center">Return Qty</th>
                                    <th class="px-6 py-4 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Price</th>
                                    <th class="px-6 py-4 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Discount</th>
                                    <th class="px-6 py-4 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Tax</th>
                                    <th class="px-6 py-4 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                <template x-for="(item, index) in form.items" :key="index">
                                    <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="text-[11px] font-black text-text-primary dark:text-dark-text leading-tight mb-0.5" x-text="item.item_name"></span>
                                                <span class="text-[9px] text-text-muted font-bold uppercase tracking-tighter" x-text="'SKU: ' + item.sku"></span>
                                                
                                                <!-- Serial Numbers Selector -->
                                                <template x-if="item.is_serialized">
                                                    <div class="mt-2">
                                                        <button @click="openSerialModal(index)" 
                                                                class="text-[8px] font-black uppercase tracking-widest px-2 py-1 rounded bg-orange-50 dark:bg-orange-500/10 text-orange-600 border border-orange-100 dark:border-orange-500/20 hover:bg-orange-100 transition-all flex items-center gap-1.5 focus:ring-1 focus:ring-orange-500 outline-none">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                                                            Select Serials (<span x-text="item.serials.length"></span>)
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-[10px] font-black text-text-muted" x-text="item.sold_qty"></td>
                                        <td class="px-4 py-3 text-[10px] font-black text-danger" x-text="item.already_returned"></td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="inline-flex items-center gap-1 bg-background dark:bg-slate-800 p-1 rounded-xl border border-border-light dark:border-dark-border shadow-inner">
                                                <button @click="updateQty(index, -1)" class="w-6 h-6 flex items-center justify-center bg-card dark:bg-dark-card rounded-lg text-text-muted hover:text-danger hover:bg-danger-light transition-all shadow-sm border border-border-light dark:border-dark-border outline-none">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"></path></svg>
                                                </button>
                                                <input type="number" x-model.number="item.return_qty" @input="validateQty(index)" class="w-12 bg-transparent border-none text-center text-[10px] font-black p-0 focus:ring-0 tabular-nums outline-none text-text-primary dark:text-dark-text" step="any">
                                                <button @click="updateQty(index, 1)" class="w-6 h-6 flex items-center justify-center bg-card dark:bg-dark-card rounded-lg text-text-muted hover:text-success hover:bg-success-light transition-all shadow-sm border border-border-light dark:border-dark-border outline-none">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                            <p class="text-[7px] font-bold text-text-muted mt-1 uppercase tracking-tighter">Max: <span x-text="item.remaining_qty"></span></p>
                                        </td>
                                        <td class="px-6 py-4 text-right tabular-nums">
                                            <span class="text-[10px] font-black text-text-secondary dark:text-slate-200" x-text="formatCurrency(item.price_per_unit)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-right tabular-nums">
                                            <span class="text-[10px] font-black text-danger" x-text="formatCurrency(item.discount_amt * item.return_qty)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-right tabular-nums">
                                            <span class="text-[10px] font-black text-warning" x-text="formatCurrency(item.tax_amt * item.return_qty)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-right tabular-nums">
                                            <span class="text-[10px] font-black text-primary" x-text="formatCurrency(calculateItemTotal(item))"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- NOTES AREA -->
                <div class="card p-5">
                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 pl-1 italic">Internal Note</label>
                    <textarea x-model="form.note" rows="3" class="input-base resize-none" placeholder="Enter return reason or notes..."></textarea>
                </div>
            </div>

            <!-- SIDEBAR (INFO & SUMMARY) -->
            <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
                
                <!-- SALE INFO CARD -->
                <div class="card p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-primary-light text-primary flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none mb-0.5">Customer & Sale Info</p>
                            <h3 class="text-sm font-black text-text-primary dark:text-dark-text tabular-nums">{{ $sale->sales_code }}</h3>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1">Customer</label>
                                <div class="bg-background dark:bg-slate-800/50 rounded-xl px-3 py-1.5 border border-border-light dark:border-dark-border">
                                    <span class="text-[10px] font-black text-text-primary dark:text-slate-300">{{ $sale->customer->customer_name ?? 'Walk-in' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1">Warehouse</label>
                                <div class="bg-background dark:bg-slate-800/50 rounded-xl px-3 py-1.5 border border-border-light dark:border-dark-border">
                                    <span class="text-[10px] font-black text-text-primary dark:text-slate-300 uppercase italic">{{ $sale->warehouse->warehouse_name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sale Financial Status Summary -->
                        <div class="p-3 bg-background dark:bg-slate-800/40 rounded-xl border border-border-light dark:border-dark-border space-y-1.5 text-xs">
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-text-muted font-bold uppercase tracking-wider">Sale Total:</span>
                                <span class="font-black text-text-primary dark:text-slate-200 tabular-nums">{{ format_currency($sale_grand_total) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-success font-bold uppercase tracking-wider">Customer Paid:</span>
                                <span class="font-black text-success tabular-nums">{{ format_currency($sale_paid) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="{{ $sale_due > 0 ? 'text-danger' : 'text-text-muted' }} font-bold uppercase tracking-wider">Outstanding Due:</span>
                                <span class="font-black {{ $sale_due > 0 ? 'text-danger' : 'text-text-secondary dark:text-slate-300' }} tabular-nums">{{ format_currency($sale_due) }}</span>
                            </div>
                            @if($sale_credit > 0)
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-warning font-bold uppercase tracking-wider">Credit Balance (Overpaid):</span>
                                <span class="font-black text-warning tabular-nums">{{ format_currency($sale_credit) }}</span>
                            </div>
                            @endif
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-danger tracking-widest block mb-1 italic">Return Date *</label>
                            <input type="date" x-model="form.return_date" class="input-base">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1 pl-1">Reference No</label>
                            <input type="text" x-model="form.reference_no" placeholder="Optional" class="input-base">
                        </div>
                    </div>
                </div>

                <!-- PAYMENT & SUMMARY -->
                <div class="card overflow-hidden p-0 sticky top-6">
                    <div class="p-5 bg-navy dark:bg-slate-900 text-white">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-300/60 mb-1">Return Amount Summary</p>
                        <div class="flex items-baseline gap-2">
                              <span class="text-[11px] font-black text-indigo-400 italic">{{ $currencySymbol }}</span>
                             <h2 class="text-3xl font-black tracking-tighter tabular-nums" x-text="formatNumber(calculateGrandTotal)"></h2>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="space-y-2.5">
                            <div class="flex justify-between items-center text-[10px] font-bold text-text-muted">
                                <span class="uppercase tracking-widest">Return Subtotal</span>
                                <span class="text-text-primary dark:text-slate-200 tabular-nums font-black" x-text="formatCurrency(calculateSubtotal)"></span>
                            </div>
                            <div class="flex justify-between items-center text-[10px] font-bold text-text-muted">
                                <span class="uppercase tracking-widest">Total Tax (+)</span>
                                <span class="text-primary tabular-nums font-black" x-text="formatCurrency(calculateTotalTax)"></span>
                            </div>
                            <div class="flex justify-between items-center text-[10px] font-bold text-text-muted">
                                <span class="uppercase tracking-widest">Total Discount (-)</span>
                                <span class="text-danger tabular-nums font-black" x-text="formatCurrency(calculateTotalDiscount)"></span>
                            </div>
                            <div class="flex justify-between items-center text-[10px] font-bold text-text-muted border-t border-border-light dark:border-dark-border pt-2.5">
                                <span class="uppercase tracking-widest text-primary font-black italic">Gross Return Total</span>
                                <span class="text-primary tabular-nums font-black text-base" x-text="formatCurrency(calculateGrandTotal)"></span>
                            </div>

                            <!-- DUE OFFSET & MAX REFUND BREAKDOWN -->
                            <template x-if="saleDue > 0 && calculateGrandTotal > 0">
                                <div class="pt-2 border-t border-dashed border-border dark:border-dark-border space-y-2">
                                    <div class="flex justify-between items-center text-[10px] font-bold text-warning">
                                        <span class="uppercase tracking-widest flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Due Balance Offset (-)
                                        </span>
                                        <span class="tabular-nums font-black" x-text="formatCurrency(calculateDueOffset)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-bold text-text-muted">
                                        <span class="uppercase tracking-widest">New Remaining Due</span>
                                        <span class="font-black" :class="newSaleDue <= 0 ? 'text-success' : 'text-danger'" x-text="formatCurrency(newSaleDue)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-bold text-success border-t border-border-light dark:border-dark-border pt-1.5">
                                        <span class="uppercase tracking-widest font-black">Max Cash Refund</span>
                                        <span class="tabular-nums font-black text-sm" x-text="formatCurrency(calculateMaxCashRefund)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- PAYMENT SECTION -->
                        <div class="pt-4 border-t border-border-light dark:border-dark-border space-y-4">
                            <template x-if="calculateMaxCashRefund > 0">
                                <div>
                                    <label class="text-[9px] font-black uppercase text-success tracking-widest block mb-2 italic px-1">
                                        Cash Refund to Customer (Max: <span x-text="formatCurrency(calculateMaxCashRefund)"></span>)
                                    </label>
                                    <div class="grid grid-cols-1 gap-2.5">
                                        <div class="relative">
                                            <select x-model="form.account_id" class="input-base cursor-pointer" :required="form.paid_amount > 0">
                                                <option value="">Select Refund Account</option>
                                                @foreach($accounts as $acc)
                                                    <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ $acc->account_number }}) — Bal: {{ format_currency($acc->balance) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="relative group">
                                             <input type="number"
                                                    step="0.01"
                                                    min="0"
                                                    :max="calculateMaxCashRefund"
                                                    x-model.number="form.paid_amount"
                                                    class="input-base tabular-nums"
                                                    :class="isOverRefunding ? 'border-danger focus:ring-danger text-danger' : ''"
                                                    placeholder="Cash Refund Amount">
                                             <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                 <button type="button" @click="form.paid_amount = calculateMaxCashRefund" class="text-[8px] font-black uppercase text-primary hover:text-primary-700 bg-card dark:bg-dark-card px-1.5 py-0.5 rounded shadow-sm border border-border-light transition-all">Max</button>
                                             </div>
                                        </div>
                                        <p x-show="isOverRefunding" x-cloak class="text-[9px] text-danger font-bold px-1">
                                            Refund cannot exceed max cash refund of <span x-text="formatCurrency(calculateMaxCashRefund)"></span>.
                                        </p>
                                        <p x-show="!isOverRefunding && form.account_id && (parseFloat(form.paid_amount) || 0) === 0"
                                           x-cloak class="text-[9px] text-text-muted font-bold px-1 italic flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            No refund will be issued — the returned value will be applied against the sale balance.
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="calculateGrandTotal > 0 && calculateMaxCashRefund === 0">
                                <div class="p-3.5 bg-primary-light dark:bg-primary/10 border border-primary-100 dark:border-primary-800/40 rounded-xl text-xs text-primary-900 dark:text-primary-200">
                                    <div class="font-bold flex items-center gap-1.5 mb-1">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Due Balance Settled via Return
                                    </div>
                                    <p class="text-[10px] text-primary-700 dark:text-primary-300 leading-relaxed">
                                        This return of <strong x-text="formatCurrency(calculateGrandTotal)"></strong> offsets the customer's unpaid balance. No cash refund is owed from the drawer.
                                    </p>
                                </div>
                            </template>

                            <button @click="submitForm"
                                    :disabled="isSubmitting || isOverRefunding"
                                    class="btn-primary w-full !py-3.5 !text-[11px] font-black uppercase tracking-[0.2em] disabled:bg-slate-300 dark:disabled:bg-slate-800 disabled:cursor-not-allowed">
                                <span x-show="!isSubmitting">Complete Return</span>
                                <span x-show="isSubmitting" class="flex items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Processing...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SERIAL SELECTOR MODAL -->
        <div x-show="serialModalActive" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-sm"
             @click.away="serialModalActive = false">
            <div class="bg-card dark:bg-dark-card w-full max-w-lg rounded-2xl shadow-2xl border border-border dark:border-dark-border overflow-hidden">
                <div class="px-5 py-4 border-b border-border-light dark:border-dark-border bg-background/40 dark:bg-white/5 flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-tight">Select Serials to Return</h3>
                        <p class="text-[9px] font-bold text-text-muted mt-0.5 uppercase tracking-widest italic" x-text="'Item: ' + (activeItemIndex !== null ? form.items[activeItemIndex].item_name : '')"></p>
                    </div>
                    <button @click="serialModalActive = false" class="text-text-muted hover:text-danger transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-5 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="sn in (activeItemIndex !== null ? form.items[activeItemIndex].available_serials : [])" :key="sn">
                             <button @click="toggleSerial(sn)"
                                     class="p-3 rounded-xl border text-[10px] font-black transition-all flex items-center justify-between group"
                                     :class="isSerialSelected(sn) ? 'bg-primary-light dark:bg-primary/10 border-primary text-primary' : 'bg-background dark:bg-slate-800 border-border-light dark:border-dark-border text-text-muted hover:border-slate-300'">
                                 <span x-text="sn"></span>
                                 <div class="w-4 h-4 rounded-md border flex items-center justify-center transition-all"
                                      :class="isSerialSelected(sn) ? 'bg-primary border-primary text-white' : 'bg-card dark:bg-dark-card border-border'">
                                     <svg x-show="isSerialSelected(sn)" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                 </div>
                             </button>
                        </template>
                    </div>

                    <div x-show="activeItemIndex !== null && form.items[activeItemIndex].available_serials.length === 0" class="py-12 text-center">
                        <div class="inline-flex p-4 rounded-full bg-background dark:bg-slate-800 mb-4">
                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p class="text-[10px] font-black text-text-muted uppercase tracking-widest italic">No serials recorded for this sale</p>
                    </div>
                </div>

                <div class="px-5 py-4 bg-background/40 dark:bg-white/5 border-t border-border-light dark:border-dark-border flex justify-between items-center">
                    <span class="text-[9px] font-black uppercase text-text-muted italic" x-text="'Selected: ' + (activeItemIndex !== null ? form.items[activeItemIndex].serials.length : 0) + ' / ' + (activeItemIndex !== null ? form.items[activeItemIndex].return_qty : 0)"></span>
                    <button @click="serialModalActive = false" class="btn-primary !px-6 !py-2 !text-[10px] font-black uppercase tracking-widest">Done</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function salesReturnForm() {
            return {
                isSubmitting: false,
                serialModalActive: false,
                activeItemIndex: null,
                saleDue: {{ (float)($sale_due ?? 0) }},
                salePaid: {{ (float)($sale_paid ?? 0) }},
                saleGrandTotal: {{ (float)($sale_grand_total ?? 0) }},
                form: {
                    sales_id: '{{ $sale->id }}',
                    return_date: '{{ date("Y-m-d") }}',
                    reference_no: '',
                    note: '',
                    subtotal: 0,
                    grand_total: 0,
                    paid_amount: 0,
                    account_id: '',
                    payment_type: 'Cash',
                    items: {!! $items_json !!}
                },

                init() {
                    // Set initial refund to maximum allowable cash refund
                    this.$nextTick(() => {
                        this.form.paid_amount = this.calculateMaxCashRefund;
                    });
                },

                updateQty(index, delta) {
                    let item = this.form.items[index];
                    let newQty = Number(item.return_qty) + delta;
                    if (newQty >= 0 && newQty <= item.remaining_qty) {
                        item.return_qty = newQty;
                        if (item.is_serialized && item.serials.length > newQty) {
                            item.serials = item.serials.slice(0, newQty);
                        }
                        this.autoAdjustRefund();
                    }
                },

                validateQty(index) {
                    let item = this.form.items[index];
                    if (item.return_qty < 0) item.return_qty = 0;
                    if (item.return_qty > item.remaining_qty) item.return_qty = item.remaining_qty;
                    
                    if (item.is_serialized && item.serials.length > item.return_qty) {
                        item.serials = item.serials.slice(0, Math.floor(item.return_qty));
                    }
                    this.autoAdjustRefund();
                },

                autoAdjustRefund() {
                    if (this.form.paid_amount > this.calculateMaxCashRefund) {
                        this.form.paid_amount = this.calculateMaxCashRefund;
                    }
                },

                calculateItemTotal(item) {
                    if (item.return_qty <= 0) return 0;
                    let total = (item.price_per_unit - item.discount_amt + item.tax_amt) * item.return_qty;
                    item.total_cost = total;
                    return total;
                },

                get calculateSubtotal() {
                    let sub = this.form.items.reduce((acc, item) => {
                        return acc + ((item.price_per_unit - item.discount_amt) * item.return_qty);
                    }, 0);
                    this.form.subtotal = sub;
                    return sub;
                },

                get calculateTotalTax() {
                    return this.form.items.reduce((acc, item) => {
                        return acc + (item.tax_amt * item.return_qty);
                    }, 0);
                },

                get calculateTotalDiscount() {
                    return this.form.items.reduce((acc, item) => {
                        return acc + (item.discount_amt * item.return_qty);
                    }, 0);
                },

                get calculateGrandTotal() {
                    let total = Math.round((this.calculateSubtotal + this.calculateTotalTax) * 100) / 100;
                    this.form.grand_total = total;
                    return total;
                },

                get calculateDueOffset() {
                    return Math.min(this.calculateGrandTotal, this.saleDue);
                },

                get calculateMaxCashRefund() {
                    return Math.max(0, Math.round((this.calculateGrandTotal - this.calculateDueOffset) * 100) / 100);
                },

                get newSaleDue() {
                    return Math.max(0, Math.round((this.saleDue - this.calculateDueOffset) * 100) / 100);
                },

                get isOverRefunding() {
                    return (parseFloat(this.form.paid_amount) || 0) > (this.calculateMaxCashRefund + 0.001);
                },

                openSerialModal(index) {
                    this.activeItemIndex = index;
                    this.serialModalActive = true;
                },

                toggleSerial(sn) {
                    let item = this.form.items[this.activeItemIndex];
                    let idx = item.serials.indexOf(sn);
                    if (idx > -1) {
                        item.serials.splice(idx, 1);
                    } else {
                        if (item.serials.length < item.return_qty) {
                            item.serials.push(sn);
                        } else {
                            showError('Serial numbers count cannot exceed return quantity');
                        }
                    }
                },

                isSerialSelected(sn) {
                    if (this.activeItemIndex === null) return false;
                    return this.form.items[this.activeItemIndex].serials.includes(sn);
                },

                formatCurrency(val) {
                    return '{{ $currencySymbol }}' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                },

                formatNumber(val) {
                    return Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                },

                submitForm() {
                    if (this.calculateGrandTotal <= 0) {
                        showError('Return quantity must be greater than 0 for at least one item');
                        return;
                    }

                    if (this.isOverRefunding) {
                        showError(`Refund amount cannot exceed the maximum allowable cash refund of ${this.formatCurrency(this.calculateMaxCashRefund)}`);
                        return;
                    }

                    // Validate Serial Numbers selection
                    for (let item of this.form.items) {
                        if (item.is_serialized && item.return_qty > 0 && item.serials.length !== Math.floor(item.return_qty)) {
                            showError(`Please select ${Math.floor(item.return_qty)} serial numbers for item: ${item.item_name}`);
                            return;
                        }
                    }

                    if (this.form.paid_amount > 0 && !this.form.account_id) {
                        showError('Please select an account for the refund payment');
                        return;
                    }

                    this.isSubmitting = true;

                    fetch('{{ route("sales.return.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.form)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccess(data.message || 'Sales Return created successfully!');
                            setTimeout(() => { window.location.href = data.redirect || "{{ route('sales.returns') }}"; }, 800);
                        } else {
                            showError(data.message || 'Failed to create sales return.');
                            this.isSubmitting = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showError('Something went wrong. Please check console for details.');
                        this.isSubmitting = false;
                    });
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
