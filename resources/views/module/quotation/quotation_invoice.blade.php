<x-app-layout title="Quotation Invoice">
    @php
        $effectiveStatus = $quotation->effective_status;
        $isConverted = $quotation->isConverted();
        $isExpired = $quotation->isExpired();
    @endphp

    <div x-data="{
        status: '{{ $effectiveStatus }}',
        isConverting: false,
        isUpdatingStatus: false,
        async convertToSale() {
            if (!confirm('Are you sure you want to convert quotation {{ $quotation->quotation_code }} into an active Sale now? Stock will be reserved and deducted.')) {
                return;
            }
            this.isConverting = true;
            try {
                const res = await fetch('{{ route('quotation.convert', $quotation->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Conversion failed.');
                    this.isConverting = false;
                }
            } catch (err) {
                alert('Network error while converting quotation: ' + err.message);
                this.isConverting = false;
            }
        },
        async changeStatus(newStatus) {
            this.isUpdatingStatus = true;
            try {
                const res = await fetch('{{ route('quotation.status.update', $quotation->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                } else {
                    alert(data.message || 'Failed to update status.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            } finally {
                this.isUpdatingStatus = false;
            }
        }
    }" class="space-y-4">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                    <span>Quotation:</span>
                    <span class="text-primary font-mono">{{ $quotation->quotation_code }}</span>
                </h1>
                <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('quotation.list') }}" class="hover:text-primary transition-colors font-bold">Quotations</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-bold">Invoice</span>
                </div>
            </div>

            <!-- Header Action Row -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="window.print()" class="btn-secondary !py-1.5 !px-3 text-xs font-bold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print</span>
                </button>

                @if(!$isConverted)
                    <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn-secondary !py-1.5 !px-3 text-xs font-bold flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Edit</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- PROMINENT CONVERTED LINK (Item 19) -->
        @if($isConverted && $quotation->sale)
            <div class="card p-3 bg-purple-50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-purple-900 dark:text-purple-200">This quotation has been converted into an active Sale</p>
                        <p class="text-[10px] text-purple-700 dark:text-purple-400 font-mono">Sale Code: <span class="font-bold">{{ $quotation->sale->sales_code }}</span></p>
                    </div>
                </div>
                <a href="{{ route('sales.invoice', $quotation->sale->id) }}" class="btn-primary !py-1.5 !px-3.5 text-xs font-bold flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    <span>View Sale Invoice</span>
                </a>
            </div>
        @endif

        <!-- MAIN INVOICE CONTAINER (Print-Targeted) -->
        <div class="card p-6 md:p-8 space-y-6 shadow-sm border border-border dark:border-dark-border bg-white dark:bg-dark-card">
            
            <!-- HEADER / META SECTION -->
            <div class="flex flex-col sm:flex-row justify-between gap-6 pb-6 border-b border-border-light dark:border-dark-border">
                <!-- From (Store) -->
                <div class="space-y-1">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center font-black text-sm">
                            CV
                        </div>
                        <div>
                            <h2 class="text-sm font-black uppercase tracking-tight text-text-primary dark:text-dark-text">
                                {{ auth()->user()->store->store_name ?? 'Primary Store' }}
                            </h2>
                            <p class="text-[9px] font-bold text-text-secondary uppercase tracking-wider">Point of Sale & Quotations</p>
                        </div>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed max-w-xs">
                        {{ auth()->user()->store->address ?? 'Warehouse Location' }}
                    </p>
                </div>

                <!-- To (Customer) -->
                <div class="space-y-1 sm:text-right">
                    <p class="text-[9px] font-black uppercase text-text-secondary tracking-wider">Quotation For</p>
                    <h3 class="text-base font-black text-text-primary dark:text-dark-text">
                        {{ $quotation->customer->customer_name }}
                    </h3>
                    <p class="text-xs text-text-secondary leading-relaxed">
                        {{ $quotation->customer->address }}<br>
                        Phone: {{ $quotation->customer->mobile }}
                    </p>
                </div>
            </div>

            <!-- QUOTATION METADATA STRIP -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-background dark:bg-dark-bg/60 rounded-2xl border border-border-light dark:border-dark-border text-xs">
                <div>
                    <span class="text-[9px] font-black uppercase text-text-secondary tracking-wider block">Quotation Code</span>
                    <span class="font-mono font-bold text-primary">{{ $quotation->quotation_code }}</span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-text-secondary tracking-wider block">Date</span>
                    <span class="font-bold text-text-primary dark:text-dark-text">{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d-m-Y') }}</span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-text-secondary tracking-wider block">Expiry</span>
                    <span class="font-bold {{ $isExpired ? 'text-danger' : 'text-text-primary dark:text-dark-text' }}">
                        {{ \Carbon\Carbon::parse($quotation->expire_date)->format('d-m-Y') }}
                    </span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-text-secondary tracking-wider block">Status</span>
                    <template x-if="status === 'Converted'">
                        <x-badge color="primary">Converted</x-badge>
                    </template>
                    <template x-if="status === 'Accepted'">
                        <x-badge color="success">Accepted</x-badge>
                    </template>
                    <template x-if="status === 'Rejected'">
                        <x-badge color="danger">Rejected</x-badge>
                    </template>
                    <template x-if="status === 'Expired'">
                        <x-badge color="warning">Expired</x-badge>
                    </template>
                    <template x-if="status === 'Quoted'">
                        <x-badge color="neutral">Quoted</x-badge>
                    </template>
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <div class="overflow-x-auto rounded-xl border border-border-light dark:border-dark-border">
                <table class="w-full text-left text-xs">
                    <thead class="bg-background dark:bg-dark-bg/60 border-b border-border-light dark:border-dark-border">
                        <tr>
                            <th class="px-3.5 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest w-8 text-center">#</th>
                            <th class="px-3.5 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest">Item Description</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Price ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Qty</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest">Tax</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Tax Amt ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Discount</th>
                            <th class="px-3.5 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @foreach($quotation->items as $index => $item)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-dark-bg/30">
                            <td class="px-3.5 py-2 text-center text-text-secondary font-bold">{{ $index + 1 }}</td>
                            <td class="px-3.5 py-2">
                                <p class="font-bold text-text-primary dark:text-dark-text">{{ $item->item->item_name }}</p>
                                <p class="text-[9px] text-text-secondary font-mono">{{ $item->item->item_code }}</p>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums"><x-money value="{{ $item->price_per_unit }}" /></td>
                            <td class="px-3 py-2 text-center tabular-nums font-bold">{{ format_quantity($item->quotation_qty) }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[9px] font-bold uppercase text-text-secondary">{{ $item->item->tax->tax_name ?? 'No Tax' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-text-secondary"><x-money value="{{ $item->tax_amt }}" /></td>
                            <td class="px-3 py-2 text-right tabular-nums text-danger">@if($item->discount_amt > 0)<x-money value="{{ $item->discount_amt }}" />@else 0 @endif</td>
                            <td class="px-3.5 py-2 text-right tabular-nums font-black text-text-primary dark:text-dark-text"><x-money value="{{ $item->total_cost }}" /></td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-background dark:bg-dark-bg/60 font-bold border-t border-border-light dark:border-dark-border text-xs">
                        <tr>
                            <td colspan="3" class="px-3.5 py-2 text-[9px] uppercase tracking-wider text-center font-black">Total Quantity</td>
                            <td class="px-3 py-2 text-center tabular-nums">{{ format_quantity($quotation->items->sum('quotation_qty')) }}</td>
                            <td colspan="3"></td>
                            <td class="px-3.5 py-2 text-right tabular-nums font-black text-primary"><x-money value="{{ $quotation->items->sum('total_cost') }}" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- TOTALS & NOTES -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                <!-- Notes Column -->
                <div class="space-y-3 text-xs">
                    <div>
                        <h4 class="text-[9px] font-black uppercase text-text-secondary tracking-wider mb-1">Quotation Notes & Instructions</h4>
                        <div class="p-3 bg-background dark:bg-dark-bg/50 rounded-xl border border-border-light dark:border-dark-border">
                            <p class="italic text-text-secondary leading-relaxed">
                                {{ $quotation->quotation_note ?: 'No notes or special instructions provided.' }}
                            </p>
                        </div>
                    </div>

                    @if($quotation->tot_discount_to_all_amt > 0)
                        <div class="p-2.5 bg-background dark:bg-dark-bg/50 rounded-xl border border-border-light dark:border-dark-border flex justify-between items-center text-xs">
                            <span class="text-text-secondary font-bold">Global Discount ({{ $quotation->discount_to_all_input }} {{ $quotation->discount_to_all_type === 'Percentage' ? '%' : 'Fixed' }}):</span>
                            <span class="font-bold text-danger">- <x-money value="{{ $quotation->tot_discount_to_all_amt }}" /></span>
                        </div>
                    @endif
                </div>

                <!-- Totals Box -->
                <div class="space-y-2 text-xs bg-slate-900 text-white dark:bg-dark-bg p-4 rounded-2xl">
                    <div class="flex justify-between items-center py-0.5 border-b border-white/10 dark:border-dark-border">
                        <span class="text-slate-400">Subtotal:</span>
                        <span class="font-bold tabular-nums"><x-money value="{{ $quotation->subtotal }}" /></span>
                    </div>

                    @if($quotation->other_charges_input > 0 || $quotation->other_charges_amt > 0)
                        <div class="flex justify-between items-center py-0.5 border-b border-white/10 dark:border-dark-border">
                            <span class="text-slate-400">Other Charges:</span>
                            <span class="font-bold tabular-nums"><x-money value="{{ $quotation->other_charges_input + $quotation->other_charges_amt }}" /></span>
                        </div>
                    @endif

                    @if($quotation->tot_discount_to_all_amt > 0)
                        <div class="flex justify-between items-center py-0.5 border-b border-white/10 dark:border-dark-border text-rose-400">
                            <span>Discount on All:</span>
                            <span class="font-bold tabular-nums">- <x-money value="{{ $quotation->tot_discount_to_all_amt }}" /></span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center py-0.5 border-b border-white/10 dark:border-dark-border">
                        <span class="text-slate-400">Round Off:</span>
                        <span class="font-bold tabular-nums">{{ $quotation->round_off >= 0 ? '+' : '-' }} <x-money value="{{ abs($quotation->round_off ?? 0) }}" /></span>
                    </div>

                    <div class="pt-2 flex justify-between items-baseline">
                        <span class="text-primary-400 uppercase tracking-widest font-black text-xs">Grand Total:</span>
                        <span class="text-2xl font-black text-white tabular-nums tracking-tight"><x-money value="{{ $quotation->grand_total }}" /></span>
                    </div>
                </div>
            </div>

            <!-- ACTION BAR (Items 17-18) -->
            <div class="pt-4 border-t border-border-light dark:border-dark-border flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                <!-- Left: Conversion & Status Actions -->
                <div class="flex flex-wrap items-center gap-2">
                    @if(!$isConverted)
                        @if(!$isExpired)
                            <button 
                                type="button"
                                @click="convertToSale()" 
                                :disabled="isConverting" 
                                class="btn-primary !py-2 !px-4 text-xs font-black uppercase tracking-widest flex items-center gap-2 shadow-sm disabled:opacity-50">
                                <template x-if="!isConverting">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 100 4h2m1 8H7a2 2 0 110-4h5m10 0l-4 4m4-4l-4-4"></path></svg>
                                        <span>Convert to Sale</span>
                                    </div>
                                </template>
                                <template x-if="isConverting">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span>Converting...</span>
                                    </div>
                                </template>
                            </button>

                            <!-- Secondary status actions -->
                            <button 
                                type="button"
                                x-show="status !== 'Accepted'" 
                                @click="changeStatus('Accepted')" 
                                :disabled="isUpdatingStatus" 
                                class="btn-secondary !py-2 !px-3 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">
                                Mark Accepted
                            </button>

                            <button 
                                type="button"
                                x-show="status !== 'Rejected'" 
                                @click="changeStatus('Rejected')" 
                                :disabled="isUpdatingStatus" 
                                class="btn-secondary !py-2 !px-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                Mark Rejected
                            </button>
                        @else
                            <!-- Inline Status-dependent explanation (Item 18) -->
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 text-xs">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>Expired on {{ \Carbon\Carbon::parse($quotation->expire_date)->format('d-m-Y') }} — cannot convert to sale without updating the expiry date.</span>
                            </div>
                        @endif
                    @else
                        <!-- Already Converted explanation (Item 18) -->
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/20 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800 text-xs font-semibold">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <span>Quotation converted to Sale {{ $quotation->sale?->sales_code ?? '' }}. Locked from further edits.</span>
                        </div>
                    @endif
                </div>

                <!-- Right: Destructive Action (Isolated, Danger Styling) -->
                @if(!$isConverted)
                    <div class="flex justify-end pt-2 sm:pt-0">
                        <form action="{{ route('quotation.delete', $quotation->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete quotation {{ $quotation->quotation_code }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger !py-2 !px-3.5 text-xs font-bold flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                <span>Delete Quotation</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- CLEAN PRINT STYLES -->
    <style>
        @media print {
            body * { visibility: hidden; }
            .bg-white, .dark\:bg-dark-card { background: white !important; color: black !important; }
            .bg-slate-900, .dark\:bg-dark-bg { background: #1e293b !important; color: white !important; }
            .bg-primary { background: #4f46e5 !important; color: white !important; }
            .text-primary { color: #4f46e5 !important; }
            .shadow-sm, .shadow-md, .shadow-lg, .shadow-2xl { box-shadow: none !important; }
            main { margin: 0 !important; padding: 0 !important; }
            header, footer, nav, .btn-primary, .btn-secondary, .btn-danger, form { display: none !important; }
            .rounded-3xl, .rounded-2xl, .rounded-xl { border-radius: 0 !important; }
            .card { border: 1px solid #e2e8f0 !important; }
            
            div.card.p-6 {
                visibility: visible;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
            }
            div.card.p-6 * {
                visibility: visible;
            }
        }
    </style>
</x-app-layout>
