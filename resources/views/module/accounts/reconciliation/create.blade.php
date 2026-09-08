<x-app-layout title="New Cash Reconciliation">
    <div x-data="cashReconciliation()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Count & Reconcile Cash Drawer</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="hover:text-primary-600 transition-colors text-[10px]">Cash Reconciliation</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold">New Cash Count</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 px-1">Verify physical drawer against system ledger</p>
            </div>

            <a href="{{ route('accounts.cash-reconciliation.index') }}" class="btn-secondary !px-4 !py-2 !text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
        </div>

        <form method="POST" action="{{ route('accounts.cash-reconciliation.store') }}" @submit="validateForm($event)">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                
                <!-- LEFT COLUMN: SCOPE SELECTION, STARTING FLOAT & EXPECTED CASH -->
                <div class="lg:col-span-1 space-y-4">
                    
                    <!-- STEP 1: SCOPE & DATE -->
                    <div class="card p-4">
                        <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                            1. Scope & Date
                        </h2>

                        <div class="space-y-3">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Reconciliation Date *</label>
                                <input type="date" name="reconciliation_date" x-model="reconciliationDate" @change="fetchBreakdown()" class="input-base !py-2 !px-3 !text-xs !font-bold" required>
                            </div>

                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Cash Account *</label>
                                <select name="account_id" x-model="accountId" @change="onAccountChange()" class="input-base !py-2 !px-3 !text-xs !font-bold" required>
                                    <option value="">Select Cash Account</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ $acc->account_code }}) — Balance: {{ format_currency($acc->balance) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Warehouse / Branch (Optional)</label>
                                <select name="warehouse_id" x-model="warehouseId" @change="fetchBreakdown()" class="input-base !py-2 !px-3 !text-xs !font-bold">
                                    <option value="">All Warehouses / Store Wide</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: STARTING CASH FLOAT CONFIRMATION -->
                    <div class="card p-4">
                        <div class="flex justify-between items-center mb-2">
                            <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                2. Starting Cash Float
                            </h2>
                            <span x-show="isFirstReconciliation" class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 rounded-full text-[9px] font-black uppercase tracking-wider">
                                Baseline
                            </span>
                        </div>

                        <!-- FIRST-TIME BASELINE BANNER -->
                        <div x-show="isFirstReconciliation" class="p-3 mb-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-2xl text-[11px] text-blue-900 dark:text-blue-200">
                            <div class="font-bold flex items-center gap-1.5 mb-0.5">
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Initial Drawer Baseline
                            </div>
                            <p class="text-[10px] text-blue-700 dark:text-blue-300">No prior reconciliation was found. Enter the initial starting float cash placed in this drawer.</p>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">
                                    Opening Cash Confirmed ({{ $currencySymbol ?? '' }}) *
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">{{ $currencySymbol ?? '' }}</span>
                                    <input type="number" step="0.01" min="0" name="opening_balance" x-model.number="openingBalance" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-7 pr-3 text-sm font-black text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-primary-500 transition-all" required>
                                </div>
                                <div class="flex justify-between items-center mt-1.5 text-[10px]">
                                    <span class="text-slate-400 font-medium">System Suggestion:</span>
                                    <span class="font-bold text-slate-600 dark:text-slate-300">
                                        {{ $currencySymbol ?? '' }}<span x-text="formatMoney(systemOpeningBalance)">0.00</span>
                                    </span>
                                </div>
                            </div>

                            <!-- OPENING VARIANCE BADGE -->
                            <div x-show="!isFirstReconciliation" class="p-2.5 rounded-xl border text-[11px]" :class="{
                                'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 text-emerald-800 dark:text-emerald-300': openingVariance === 0,
                                'bg-amber-50 dark:bg-amber-950/30 border-amber-200 text-amber-800 dark:text-amber-300': openingVariance !== 0
                            }">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold">Starting Float Variance:</span>
                                    <span class="font-black" x-text="openingVariance === 0 ? 'Matched (' + @json($currencySymbol ?? '') + '0.00)' : (openingVariance > 0 ? '+' + formatMoney(openingVariance) + ' Over' : '-' + formatMoney(Math.abs(openingVariance)) + ' Reduced')"></span>
                                </div>
                            </div>

                            <!-- OPENING VARIANCE REASON NOTES (PROMPTED IF OVERRIDDEN) -->
                            <div x-show="!isFirstReconciliation && openingVariance !== 0" x-transition>
                                <label class="text-[9px] font-black text-amber-600 uppercase tracking-widest block mb-1">
                                    Opening Float Difference Reason *
                                </label>
                                <textarea name="opening_notes" x-model="openingNotes" rows="2" class="input-base !bg-amber-50/50 dark:!bg-slate-800 !border-amber-200 dark:!border-amber-800 !p-2 !text-xs !font-medium focus:!ring-amber-500" placeholder="e.g. Manager removed $800 to bank safe overnight, reset starting drawer float to $200..." :required="!isFirstReconciliation && openingVariance !== 0"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- LIVE SYSTEM EXPECTED BREAKDOWN -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-5 shadow-xl relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-primary-500/10 rounded-full blur-2xl"></div>
                        
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Calculated Expected Cash</span>
                            <span x-show="loading" class="text-[9px] text-primary-400 animate-pulse font-bold">Calculating...</span>
                        </div>

                        <div class="text-3xl font-black tracking-tight mb-4 text-emerald-400">
                            {{ $currencySymbol ?? '' }}<span x-text="formatMoney(computedExpectedClosingBalance)">0.00</span>
                        </div>

                        <div class="space-y-2 text-xs border-t border-slate-700/60 pt-3 text-slate-300">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Confirmed Opening Float:</span>
                                <span class="font-bold text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(openingBalance)">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-400">+ Cash Sales:</span>
                                <span class="font-bold text-emerald-400">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(breakdown.cash_sales_amount)">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-400">+ Deposits / Inflows:</span>
                                <span class="font-bold text-emerald-400">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(breakdown.cash_deposits_amount + breakdown.cash_transfers_in)">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Cash Refunds (Returns):</span>
                                <span class="font-bold text-rose-400">-{{ $currencySymbol ?? '' }}<span x-text="formatMoney(breakdown.cash_refunds_amount)">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Cash Expenses:</span>
                                <span class="font-bold text-rose-400">-{{ $currencySymbol ?? '' }}<span x-text="formatMoney(breakdown.cash_expenses_amount)">0.00</span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-400">- Transfers Out:</span>
                                <span class="font-bold text-rose-400">-{{ $currencySymbol ?? '' }}<span x-text="formatMoney(breakdown.cash_transfers_out)">0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: PHYSICAL CASH COUNT & VARIANCE & ADJUSTMENT -->
                <div class="lg:col-span-2 space-y-4">
                    
                    <!-- PHYSICAL CASH COUNT CARD -->
                    <div class="card p-5">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 mb-4">
                            <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                3. Physical Cash Count (Closing)
                            </h2>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="showDenominations = !showDenominations" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg text-[10px] font-bold hover:bg-slate-200 transition-all flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span x-text="showDenominations ? 'Hide Denominations' : 'Count by Denominations'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- TOTAL COUNTED AMOUNT INPUT -->
                        <div class="mb-4">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Total Physical Cash in Drawer ({{ $currencySymbol ?? '' }}) *</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-lg font-black text-slate-400">{{ $currencySymbol ?? '' }}</span>
                                <input type="number" step="0.01" min="0" name="counted_amount" x-model.number="countedAmount" @input="updateFromTotalInput()" class="input-base !border-2 !rounded-card !py-3 !pl-10 !pr-4 !text-2xl !font-black focus:!border-primary-500 transition-all" placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- DENOMINATIONS GRID (TOGGLEABLE) -->
                        <div x-show="showDenominations" x-collapse class="bg-slate-50 dark:bg-slate-800/40 rounded-2xl p-4 border border-slate-200/60 dark:border-dark-border mb-4">
                            <h3 class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-3">Currency Bill & Coin Breakdown</h3>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                                <template x-for="d in denominationsList" :key="d.value">
                                    <div class="bg-white dark:bg-dark-card p-2.5 rounded-xl border border-slate-200 dark:border-dark-border">
                                        <div class="text-[10px] font-bold text-slate-500 flex justify-between">
                                            <span>{{ $currencySymbol ?? '' }}<span x-text="d.value"></span></span>
                                            <span class="text-primary-600 font-bold" x-text="@json($currencySymbol ?? '') + (d.value * (denominations[d.value] || 0)).toFixed(2)"></span>
                                        </div>
                                        <input type="number" min="0" x-model.number="denominations[d.value]" @input="calculateDenominationsTotal()" :name="'denominations[' + d.value + ']'" class="w-full mt-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-lg py-1 px-2 text-xs font-black text-right outline-none focus:ring-1 focus:ring-primary-500" placeholder="0">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- LIVE CLOSING VARIANCE CARD -->
                        <div class="rounded-2xl p-4 border transition-all" :class="{
                            'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200': closingVariance === 0,
                            'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800 text-blue-900 dark:text-blue-200': closingVariance > 0,
                            'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200': closingVariance < 0
                        }">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                                <div>
                                    <span class="text-[9px] font-black uppercase tracking-widest block" :class="{
                                        'text-emerald-700 dark:text-emerald-400': closingVariance === 0,
                                        'text-blue-700 dark:text-blue-400': closingVariance > 0,
                                        'text-rose-700 dark:text-rose-400': closingVariance < 0
                                    }">Closing Shift Variance</span>
                                    <div class="text-xl font-black">
                                        <span x-show="closingVariance === 0">Balanced (Exact Match)</span>
                                        <span x-show="closingVariance > 0">+{{ $currencySymbol ?? '' }}<span x-text="formatMoney(closingVariance)"></span> (Cash Surplus / Over)</span>
                                        <span x-show="closingVariance < 0">-{{ $currencySymbol ?? '' }}<span x-text="formatMoney(Math.abs(closingVariance))"></span> (Cash Shortage / Deficit)</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-500">Counted: <strong class="text-slate-800 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(countedAmount)"></span></strong></div>
                                    <div class="text-[10px] text-slate-500">Expected: <strong class="text-slate-800 dark:text-white">{{ $currencySymbol ?? '' }}<span x-text="formatMoney(computedExpectedClosingBalance)"></span></strong></div>
                                </div>
                            </div>
                        </div>

                        <!-- NOTES / EXPLANATION -->
                        <div class="mt-4">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">
                                Discrepancy Reason / Notes <span x-show="closingVariance !== 0" class="text-rose-500">* (Required when variance &ne; 0)</span>
                            </label>
                            <textarea name="notes" x-model="notes" rows="2" class="input-base !p-2.5 !text-xs !font-medium" placeholder="State reason for any overage/shortage or general shift handover comments..." :required="closingVariance !== 0"></textarea>
                        </div>

                        <!-- OPTION C MANAGER VARIANCE ADJUSTMENT CHECKBOX -->
                        @if(auth()->user()->hasPermission('cash_reconciliation_adjust') || auth()->user()->isSuperAdmin())
                        <div x-show="closingVariance !== 0" x-transition class="mt-4 p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" name="post_adjustment" value="1" x-model="postAdjustment" class="mt-1 w-4 h-4 rounded text-amber-600 border-amber-300 focus:ring-amber-500">
                                <div>
                                    <span class="text-xs font-black text-amber-900 dark:text-amber-200 block">Post Cash Variance Adjustment to Account Ledger</span>
                                    <span class="text-[10px] text-amber-700 dark:text-amber-400 block mt-0.5">
                                        <span x-show="closingVariance < 0">Creates a <strong>CASH SHORTAGE</strong> debit entry and deducts {{ $currencySymbol ?? '' }}<span x-text="formatMoney(Math.abs(closingVariance))"></span> from account balance.</span>
                                        <span x-show="closingVariance > 0">Creates a <strong>CASH OVERAGE</strong> credit entry and adds {{ $currencySymbol ?? '' }}<span x-text="formatMoney(closingVariance)"></span> to account balance.</span>
                                    </span>
                                </div>
                            </label>
                        </div>
                        @endif

                        <!-- SUBMIT BUTTON -->
                        <div class="mt-5 flex justify-end gap-3">
                            <a href="{{ route('accounts.cash-reconciliation.index') }}" class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider hover:bg-slate-200 transition-all">
                                Cancel
                            </a>
                            <button type="submit" :disabled="loading || !accountId" class="btn-primary !bg-emerald-600 hover:!bg-emerald-700 disabled:opacity-50 !px-6 !py-2.5 !text-xs font-black uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Save Reconciliation
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function cashReconciliation() {
            return {
                reconciliationDate: '{{ date('Y-m-d') }}',
                accountId: '{{ $accounts->first()->id ?? '' }}',
                warehouseId: '',
                openingBalance: 0,
                systemOpeningBalance: 0,
                isFirstReconciliation: false,
                openingNotes: '',
                countedAmount: 0,
                showDenominations: false,
                notes: '',
                postAdjustment: false,
                loading: false,
                accountBalances: @json($accounts->pluck('balance', 'id')),
                accountBalance: 0.00,
                denominationsList: [
                    { value: 1000 },
                    { value: 500 },
                    { value: 100 },
                    { value: 50 },
                    { value: 20 },
                    { value: 10 },
                    { value: 5 },
                    { value: 2 },
                    { value: 1 }
                ],
                denominations: {},
                breakdown: {
                    is_first_reconciliation: false,
                    account_balance: 0,
                    system_opening_balance: 0,
                    opening_balance: 0,
                    cash_sales_amount: 0,
                    cash_refunds_amount: 0,
                    cash_expenses_amount: 0,
                    cash_deposits_amount: 0,
                    cash_transfers_in: 0,
                    cash_transfers_out: 0,
                    expected_closing_balance: 0,
                },
                get openingVariance() {
                    if (this.isFirstReconciliation) return 0;
                    return Math.round(((this.openingBalance || 0) - (this.systemOpeningBalance || 0)) * 100) / 100;
                },
                get computedExpectedClosingBalance() {
                    const ob = parseFloat(this.openingBalance) || 0;
                    const sales = parseFloat(this.breakdown.cash_sales_amount) || 0;
                    const deposits = parseFloat(this.breakdown.cash_deposits_amount) || 0;
                    const transfersIn = parseFloat(this.breakdown.cash_transfers_in) || 0;
                    const refunds = parseFloat(this.breakdown.cash_refunds_amount) || 0;
                    const expenses = parseFloat(this.breakdown.cash_expenses_amount) || 0;
                    const transfersOut = parseFloat(this.breakdown.cash_transfers_out) || 0;

                    return Math.round((ob + sales + deposits + transfersIn - refunds - expenses - transfersOut) * 100) / 100;
                },
                get closingVariance() {
                    return Math.round(((this.countedAmount || 0) - this.computedExpectedClosingBalance) * 100) / 100;
                },
                init() {
                    this.fetchBreakdown();
                },
                onAccountChange() {
                    if (!this.accountId) {
                        this.accountBalance = 0.00;
                        this.openingBalance = 0.00;
                        this.systemOpeningBalance = 0.00;
                        return;
                    }
                    if (this.accountBalances[this.accountId] !== undefined) {
                        const bal = parseFloat(this.accountBalances[this.accountId]) || 0.00;
                        this.accountBalance = bal;
                        this.openingBalance = bal;
                        this.systemOpeningBalance = bal;
                    }
                    this.fetchBreakdown();
                },
                fetchBreakdown() {
                    if (!this.accountId || !this.reconciliationDate) return;
                    this.loading = true;
                    fetch(`{{ route('accounts.cash-reconciliation.calculate-expected') }}?account_id=${this.accountId}&reconciliation_date=${this.reconciliationDate}&warehouse_id=${this.warehouseId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.breakdown = data.data;
                                if (data.data.account_balance !== undefined) {
                                    this.accountBalance = parseFloat(data.data.account_balance) || 0.00;
                                }
                                this.isFirstReconciliation = Boolean(data.data.is_first_reconciliation);
                                if (!this.isFirstReconciliation && parseFloat(data.data.system_opening_balance) !== undefined) {
                                    this.systemOpeningBalance = parseFloat(data.data.system_opening_balance) || 0.00;
                                    this.openingBalance = this.systemOpeningBalance;
                                } else {
                                    this.systemOpeningBalance = this.accountBalance;
                                    this.openingBalance = this.accountBalance;
                                }
                            }
                        })
                        .catch(err => console.error('Failed to load breakdown:', err))
                        .finally(() => this.loading = false);
                },
                calculateDenominationsTotal() {
                    let total = 0;
                    for (let key in this.denominations) {
                        let count = parseInt(this.denominations[key]) || 0;
                        let val = parseFloat(key) || 0;
                        total += count * val;
                    }
                    this.countedAmount = Math.round(total * 100) / 100;
                },
                updateFromTotalInput() {
                    // Manual quick entry
                },
                formatMoney(num) {
                    return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                validateForm(e) {
                    if (!this.isFirstReconciliation && this.openingVariance !== 0 && (!this.openingNotes || this.openingNotes.trim() === '')) {
                        e.preventDefault();
                        alert('A reason is required when the confirmed starting float differs from the system suggested opening balance.');
                        return;
                    }
                    if (this.closingVariance !== 0 && (!this.notes || this.notes.trim() === '')) {
                        e.preventDefault();
                        alert('A reason/note is required whenever there is a variance between the counted cash and expected balance.');
                        return;
                    }
                }
            }
        }
    </script>
</x-app-layout>
