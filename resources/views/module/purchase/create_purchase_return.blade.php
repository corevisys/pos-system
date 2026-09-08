<x-app-layout title="Create Purchase Return">
    <script>
        window.purchaseReturnForm = () => ({
        purchase_id: @json($purchase->id),
        return_date: @json(date('Y-m-d')),
        reference_no: '',
        isSubmitting: false,
        items: [
            @foreach($purchase->items as $item)
            @php
                $alreadyReturned = (float)($returnedQuantities[$item->item_id] ?? 0);
                $remaining = max(0, (float)$item->purchase_qty - $alreadyReturned);
            @endphp
            {
                item_id: {{ $item->item_id }},
                name: @json($item->item->item_name),
                purchase_qty: {{ (float) $item->purchase_qty }},
                returned_qty: {{ $alreadyReturned }},
                remaining_qty: {{ $remaining }},
                return_qty: 0,
                purchase_price: {{ (float) $item->price_per_unit }},
                tax_id: @json((string) $item->tax_id),
                total: 0
            },
            @endforeach
        ],
        
        calculateTotal(item) {
            item.total = (parseFloat(item.return_qty || 0) * parseFloat(item.purchase_price)).toFixed(2);
        },
        
        get grandTotal() {
            return this.items.reduce((sum, item) => sum + parseFloat(item.total || 0), 0).toFixed(2);
        },

        async submitReturn() {
            if (this.isSubmitting) return;

            const returnedItems = this.items.filter(i => i.return_qty > 0);
            if (returnedItems.length === 0) {
                showError('Please enter return quantity for at least one item.');
                return;
            }

            this.isSubmitting = true;

            const data = {
                purchase_id: this.purchase_id,
                return_date: this.return_date,
                reference_no: this.reference_no,
                items: returnedItems,
                _token: @json(csrf_token())
            };

            try {
                const response = await fetch(@json(route('purchase.return.store', [], false)), {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token())
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (response.ok && result.success) {
                    showSuccess(result.message || 'Purchase return created successfully!');
                    setTimeout(() => { window.location.href = result.redirect || @json(route('purchase.returns')); }, 800);
                } else {
                    showError(result.message || 'Failed to create purchase return.');
                }
            } catch (err) {
                console.error(err);
                showError('An unexpected error occurred. Please try again.');
            } finally {
                this.isSubmitting = false;
            }
        }
        });
    </script>

    <div x-data="purchaseReturnForm()">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4 text-slate-800 dark:text-slate-200">
            <div>
                <h1 class="text-xl font-black tracking-tight">Create Purchase Return: <span class="text-primary-600 font-mono">{{ $purchase->purchase_code }}</span></h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Select items and quantities to return</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('purchase.list') }}" class="px-4 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center gap-2">Cancel</a>
                <button @click="submitReturn()"
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-70 cursor-not-allowed' : ''"
                        class="px-5 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center gap-2">
                    <template x-if="isSubmitting">
                        <svg class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </template>
                    <span x-text="isSubmitting ? 'Submitting...' : 'Submit Return'"></span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-white dark:bg-dark-card p-4 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm">
                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1">Return Date</label>
                <input type="date" x-model="return_date" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold">
            </div>
            <div class="bg-white dark:bg-dark-card p-4 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm">
                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1">Reference No</label>
                <input type="text" x-model="reference_no" placeholder="Optional" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold">
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Item Name</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Purchased</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Returned</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Remaining</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Return Qty</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Price ({{ $currencySymbol }})</th>
                            <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        <template x-for="item in items" :key="item.item_id">
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-all">
                                <td class="px-4 py-3 text-[10px] font-bold text-slate-700 dark:text-slate-300" x-text="item.name"></td>
                                <td class="px-4 py-3 text-center text-[10px] font-black" x-text="item.purchase_qty"></td>
                                <td class="px-4 py-3 text-center text-[10px] font-bold text-slate-400" x-text="item.returned_qty"></td>
                                <td class="px-4 py-3 text-center text-[10px] font-black text-emerald-600" x-text="item.remaining_qty"></td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" x-model="item.return_qty" @input="calculateTotal(item)" :max="item.remaining_qty" min="0" class="w-20 bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-1 px-2 text-[10px] font-black text-center focus:ring-1 focus:ring-rose-500 text-rose-600">
                                </td>
                                <td class="px-4 py-3 text-right text-[10px] font-bold tabular-nums" x-text="item.purchase_price.toFixed(2)"></td>
                                <td class="px-4 py-3 text-right text-[10px] font-black tabular-nums text-rose-600" x-text="item.total"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 dark:bg-white/5 dark:border-dark-border flex justify-end items-baseline gap-2">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Grand Total Return:</span>
                <span class="text-2xl font-black text-rose-600 tabular-nums">{{ $currencySymbol }}<span x-text="grandTotal"></span></span>
            </div>
        </div>
    </div>
</x-app-layout>
