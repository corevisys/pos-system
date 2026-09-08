<x-app-layout title="Close Cash Drawer">
    <div x-data="cashReconciliationClose()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Close and Reconcile Cash Drawer (Evening)</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="hover:text-primary-600 transition-colors text-[10px]">Cash Reconciliation</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-300 text-[10px] font-bold">{{ $reconciliation->reconciliation_code }} (Closing)</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 px-1">Perform physical count and finalize daily drawer reconciliation</p>
            </div>

            <a href="{{ route('accounts.cash-reconciliation.show', $reconciliation->id) }}" class="btn-secondary !px-4 !py-2 !text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Cancel / Back
            </a>
        </div>

        <form method="POST" action="{{ route('accounts.cash-reconciliation.close', $reconciliation->id) }}" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                
                <!-- LEFT COLUMN: LOCKED OPENING SUMMARY & EXPECTED CALCULATION -->
                <div class="lg:col-span-1 space-y-4">
                    
                    <!-- DRAWER OPENING AUDIT CARD -->
                    <div class="card p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                1. Confirmed Morning Float
                            </h2>
                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 rounded-full text-[9px] font-black uppercase">
                                Locked
                            </span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Drawer Code:</span>
                                <span class="font-black text-slate-700 dark:text-slate-200">{{ $reconciliation->reconciliation_code }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Cash Account:</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $reconciliation->account->account_name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Warehouse:</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $reconciliation->warehouse->warehouse_name ?? 'All Warehouses' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Shift Date:</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $reconciliation->reconciliation_date->format('Y-m-d') }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Opened By:</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $reconciliation->opener->name ?? $reconciliation->user->name ?? 'User' }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-400">Confirmed Starting Float:</span>
                                <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">
                                    {{ format_currency($reconciliation->opening_balance) }}
                                </span>
                            </div>
                        </div>

                        @if($reconciliation->opening_variance != 0)
                            <div class="mt-3 p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300">
                                <div class="font-bold mb-0.5">Overnight Float Discrepancy: {{ $reconciliation->opening_variance > 0 ? '+' : '' }}{{ format_currency($reconciliation->opening_variance) }}</div>
                                @if($reconciliation->opening_notes)
                                    <div class="text-[10px] text-amber-700 dark:text-amber-400 italic">"{{ $reconciliation->opening_notes }}"</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- LIVE SYSTEM EXPECTED BREAKDOWN -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-5 shadow-xl relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-primary-500/10 rounded-full blur-2xl"></div>
                        
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Calculated Expected Cash</span>
                            <span class="text-[8px] uppercase tracking-wider font-bold px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full">Active Ledger Base</span>
                        </div>

                        <div class="text-3xl font-black tracking-tight mb-4 text-emerald-400">
                            {{ format_currency($liveExpectedClosing) }}
                        </div>

                        <div class="space-y-2 text-xs border-t border-slate-700/60 pt-3 text-slate-300">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Confirmed Starting Float:</span>
                                <span class="font-bold text-white">{{ format_currency($reconciliation->opening_balance) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-400">+ Cash Sales:</span>
                                <span class="font-bold text-emerald-400">{{ format_currency($breakdown['cash_sales_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-400">+ Cash Deposits:</span>
                                <span class="font-bold text-emerald-400">{{ format_currency($breakdown['cash_deposits_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-400">+ Transfers In:</span>
                                <span class="font-bold text-emerald-400">{{ format_currency($breakdown['cash_transfers_in'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Cash Refunds:</span>
                                <span class="font-bold text-rose-400">{{ format_currency($breakdown['cash_refunds_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Cash Expenses:</span>
                                <span class="font-bold text-rose-400">{{ format_currency($breakdown['cash_expenses_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Transfers Out:</span>
                                <span class="font-bold text-rose-400">{{ format_currency($breakdown['cash_transfers_out'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: PHYSICAL CASH COUNT & CLOSING RECONCILIATION -->
                <div class="lg:col-span-2 space-y-4">
                    
                    <!-- STEP 2: PHYSICAL CASH COUNT -->
                    <div class="card p-5">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                            <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                                2. Physical Cash Count
                            </h2>
                            <button type="button" @click="resetDenominations()" class="text-[10px] font-bold text-slate-400 hover:text-rose-500 transition-colors flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Clear Denominations
                            </button>
                        </div>

                        <!-- DENOMINATIONS GRID -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                            <template x-for="(denom, index) in denominationsList" :key="denom.value">
                                <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-dark-border rounded-2xl p-3">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <span class="text-[11px] font-black text-slate-700 dark:text-slate-200" x-text="denom.label"></span>
                                        <span class="text-[9px] font-bold text-slate-400" x-text="'× ' + (denominations[denom.key] || 0)"></span>
                                    </div>
                                    <input type="number" min="0" x-model.number="denominations[denom.key]" @input="calculateFromDenominations()" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-2 text-xs font-black text-center outline-none focus:ring-2 focus:ring-primary-500" placeholder="0">
                                    <div class="text-[10px] font-black text-primary-600 dark:text-primary-400 text-right mt-1.5">
                                        {{ $currencySymbol ?? '' }}<span x-text="formatMoney((denominations[denom.key] || 0) * denom.value)"></span>
                                    </div>
                                    <input type="hidden" :name="'denominations[' + denom.key + ']'" :value="denominations[denom.key] || 0">
                                </div>
                            </template>
                        </div>

                        <!-- TOTAL COUNTED AMOUNT INPUT -->
                        <div class="bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-dark-border rounded-2xl p-4">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">
                                Total Physical Cash in Drawer ({{ $currencySymbol ?? '' }}) *
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-base font-black text-slate-400">{{ $currencySymbol ?? '' }}</span>
                                <input type="number" step="0.01" min="0" name="counted_amount" x-model.number="countedAmount" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-3 pl-8 pr-3 text-lg font-black text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-primary-500 transition-all" placeholder="0.00" required>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium mt-1">Calculated automatically from denomination counters above or entered directly.</p>
                        </div>
                    </div>

                    <!-- STEP 3: CLOSING SHIFT VARIANCE & LEDGER ADJUSTMENT -->
                    <div class="card p-5">
                        <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            3. Closing Shift Variance & Reconciliation
                        </h2>

                        <!-- VARIANCE STATUS CARD -->
                        <div class="p-4 rounded-2xl border transition-all mb-4" :class="{
                            'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200': variance === 0,
                            'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200': variance < 0,
                            'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800 text-blue-900 dark:text-blue-200': variance > 0
                        }">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                <div>
                                    <div class="text-[9px] font-black uppercase tracking-widest opacity-75">Daily Shift Variance</div>
                                    <div class="text-xl font-black mt-0.5 flex items-center gap-2">
                                        <template x-if="variance === 0">
                                            <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                Balanced ({{ $currencySymbol ?? '' }}0.00)
                                            </span>
                                        </template>
                                        <template x-if="variance < 0">
                                            <span class="flex items-center gap-1 text-rose-600 dark:text-rose-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                Cash Shortage: {{ $currencySymbol ?? '' }}<span x-text="formatMoney(Math.abs(variance))"></span>
                                            </span>
                                        </template>
                                        <template x-if="variance > 0">
                                            <span class="flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                                Cash Overage: {{ $currencySymbol ?? '' }}<span x-text="formatMoney(variance)"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div class="text-right text-xs">
                                    <div class="text-slate-400">Counted: <span class="font-bold text-slate-700 dark:text-slate-200">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(countedAmount)"></span></span></div>
                                    <div class="text-slate-400">Expected: <span class="font-bold text-slate-700 dark:text-slate-200">{{ format_currency($liveExpectedClosing) }}</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- LEDGER ADJUSTMENT (OPTION C HYBRID) -->
                        @if(Auth::user()->hasPermission('cash_reconciliation_adjust') || Auth::user()->isSuperAdmin())
                            <div x-show="variance !== 0" class="p-4 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-dark-border rounded-2xl mb-4">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="post_adjustment" value="1" x-model="postAdjustment" class="mt-0.5 w-4 h-4 text-primary-600 rounded border-slate-300 focus:ring-primary-500">
                                    <div>
                                        <div class="text-xs font-black text-slate-800 dark:text-slate-100">
                                            Post Variance Adjustment to Account Ledger (Elevated)
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                            <template x-if="variance < 0">
                                                <span>Will automatically create a <strong>CASH SHORTAGE</strong> debit entry in the ledger and deduct {{ $currencySymbol ?? '' }}<span x-text="formatMoney(Math.abs(variance))"></span> from account balance.</span>
                                            </template>
                                            <template x-if="variance > 0">
                                                <span>Will automatically create a <strong>CASH OVERAGE</strong> credit entry in the ledger and add {{ $currencySymbol ?? '' }}<span x-text="formatMoney(variance)"></span> to account balance.</span>
                                            </template>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @endif

                        <!-- NOTES -->
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Closing Notes / Comments</label>
                            <textarea name="notes" rows="2" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl p-3 text-xs font-medium outline-none focus:ring-2 focus:ring-primary-500" placeholder="Optional end-of-day notes or observations..."></textarea>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('accounts.cash-reconciliation.show', $reconciliation->id) }}" class="px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition-all">
                            Cancel
                        </a>
                        <button type="submit" :disabled="isSubmitting" class="btn-primary !px-6 !py-3 !text-xs font-black uppercase tracking-wider flex items-center gap-2 disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <span x-show="!isSubmitting">Reconcile & Close Drawer</span>
                            <span x-show="isSubmitting">Closing...</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function cashReconciliationClose() {
            return {
                isSubmitting: false,
                countedAmount: 0.00,
                expectedClosingBalance: {{ $liveExpectedClosing }},
                postAdjustment: false,
                denominations: {
                    c1000: 0,
                    c500: 0,
                    c100: 0,
                    c50: 0,
                    c20: 0,
                    c10: 0,
                    c5: 0,
                    c1: 0,
                },
                denominationsList: [
                    { key: 'c1000', label: '1000 Note', value: 1000 },
                    { key: 'c500',  label: '500 Note',  value: 500 },
                    { key: 'c100',  label: '100 Note',  value: 100 },
                    { key: 'c50',   label: '50 Note',   value: 50 },
                    { key: 'c20',   label: '20 Note',   value: 20 },
                    { key: 'c10',   label: '10 Note',   value: 10 },
                    { key: 'c5',    label: '5 Note',    value: 5 },
                    { key: 'c1',    label: '1 Coin/Note', value: 1 },
                ],

                get variance() {
                    const counted = parseFloat(this.countedAmount) || 0;
                    return Math.round((counted - this.expectedClosingBalance) * 100) / 100;
                },

                formatMoney(amount) {
                    return (parseFloat(amount) || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },

                calculateFromDenominations() {
                    let total = 0;
                    this.denominationsList.forEach(denom => {
                        const count = parseInt(this.denominations[denom.key]) || 0;
                        total += count * denom.value;
                    });
                    this.countedAmount = Math.round(total * 100) / 100;
                },

                resetDenominations() {
                    Object.keys(this.denominations).forEach(k => this.denominations[k] = 0);
                    this.countedAmount = 0.00;
                }
            };
        }
    </script>
</x-app-layout>
