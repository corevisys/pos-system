<x-app-layout title="Open Cash Drawer">
    <div x-data="cashReconciliationOpen()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Open Cash Drawer (Morning Start)</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="hover:text-primary-600 transition-colors text-[10px]">Cash Reconciliation</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-300 text-[10px] font-bold">Open Drawer</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 px-1">Confirm initial morning cash float to initialize today's drawer</p>
            </div>

            <a href="{{ route('accounts.cash-reconciliation.index') }}" class="btn-secondary !px-4 !py-2 !text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
        </div>

        <form method="POST" action="{{ route('accounts.cash-reconciliation.open') }}" @submit="validateForm($event)">
            @csrf
            
            <div class="max-w-2xl mx-auto space-y-4">
                
                <!-- ACTIVE OPEN DRAWER WARNING BANNER -->
                <div x-show="openDrawerInfo" x-transition class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 rounded-3xl text-xs text-amber-900 dark:text-amber-200 shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center text-amber-600 flex-shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-black text-xs text-amber-900 dark:text-amber-100 uppercase tracking-wide">
                                    Active Open Drawer Detected (<span x-text="openDrawerInfo?.code"></span>)
                                </h3>
                                <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5 leading-relaxed">
                                    A cash drawer for this account (<span x-text="openDrawerInfo?.warehouse_name"></span>) was already opened on <strong x-text="openDrawerInfo?.date"></strong> by <span x-text="openDrawerInfo?.opener_name"></span>. You must close the pending drawer before opening a new one.
                                </p>
                            </div>
                        </div>
                        <a :href="openDrawerInfo?.close_url" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-1.5 shadow-md shadow-amber-500/20 whitespace-nowrap flex-shrink-0">
                            <span>Close Drawer Now</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>
                </div>

                <!-- STEP 1: SCOPE & DATE -->
                <div class="card p-5">
                    <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                        1. Select Account & Shift Date
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Date *</label>
                            <input type="date" name="reconciliation_date" x-model="reconciliationDate" @change="fetchBreakdown()" class="input-base !py-2.5 !px-3 !text-xs !font-bold" required>
                        </div>

                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Cash Account *</label>
                            <select name="account_id" x-model="accountId" @change="onAccountChange()" class="input-base !py-2.5 !px-3 !text-xs !font-bold" required>
                                <option value="">Select Cash Account</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->account_name }} ({{ $acc->account_code }}) — Balance: {{ format_currency($acc->balance) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Warehouse / Branch (Optional)</label>
                            <select name="warehouse_id" x-model="warehouseId" @change="fetchBreakdown()" class="input-base !py-2.5 !px-3 !text-xs !font-bold">
                                <option value="">All Warehouses / Store Wide</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: STARTING CASH FLOAT CONFIRMATION -->
                <div class="card p-5">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            2. Starting Cash Float Confirmation
                        </h2>
                        <span x-show="isFirstReconciliation" class="px-2.5 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 rounded-full text-[9px] font-black uppercase tracking-wider">
                            Initial Baseline
                        </span>
                    </div>

                    <!-- FIRST-TIME BASELINE BANNER -->
                    <div x-show="isFirstReconciliation && !openDrawerInfo" class="p-3.5 mb-4 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-2xl text-xs text-blue-900 dark:text-blue-200">
                        <div class="font-bold flex items-center gap-1.5 mb-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Initial Drawer Baseline
                        </div>
                        <p class="text-[11px] text-blue-700 dark:text-blue-300 leading-relaxed">
                            No prior closed reconciliation was found for this account/branch. The opening float has been auto-selected from the account ledger balance. You may adjust it to the physical cash handed to you this morning.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">
                                    Physical Opening Cash Confirmed ({{ $currencySymbol ?? '' }}) *
                                </label>
                                <button type="button" x-show="accountId && accountBalance !== openingBalance && !openDrawerInfo" @click="openingBalance = accountBalance" class="text-[10px] font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                    Use Current Balance ({{ $currencySymbol ?? '' }}<span x-text="formatMoney(accountBalance)"></span>)
                                </button>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">{{ $currencySymbol ?? '' }}</span>
                                <input type="number" step="0.01" min="0" name="opening_balance" x-model.number="openingBalance" :disabled="!!openDrawerInfo" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-3 pl-8 pr-3 text-base font-black text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-primary-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed" required>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-dark-border text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400 font-medium">Account Ledger Balance:</span>
                                    <span class="font-black text-slate-700 dark:text-slate-200">
                                        {{ $currencySymbol ?? '' }}<span x-text="formatMoney(accountBalance)">0.00</span>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-400 font-medium">System Auto-Suggestion:</span>
                                    <span class="font-black text-slate-700 dark:text-slate-200">
                                        {{ $currencySymbol ?? '' }}<span x-text="formatMoney(systemOpeningBalance)">0.00</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- OPENING VARIANCE BADGE -->
                        <div x-show="!isFirstReconciliation && !openDrawerInfo" class="p-3 rounded-2xl border text-xs" :class="{
                            'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 text-emerald-800 dark:text-emerald-300': openingVariance === 0,
                            'bg-amber-50 dark:bg-amber-950/30 border-amber-200 text-amber-800 dark:text-amber-300': openingVariance !== 0
                        }">
                            <div class="flex justify-between items-center">
                                <span class="font-bold">Starting Float Variance:</span>
                                <span class="font-black" x-text="openingVariance === 0 ? 'Matched Exactly (' + @json($currencySymbol ?? '') + '0.00)' : (openingVariance > 0 ? '+' + formatMoney(openingVariance) + ' Over' : '-' + formatMoney(Math.abs(openingVariance)) + ' Reduced / Vault Drop')"></span>
                            </div>
                        </div>

                        <!-- OPENING VARIANCE REASON NOTES (PROMPTED IF OVERRIDDEN) -->
                        <div x-show="!isFirstReconciliation && openingVariance !== 0 && !openDrawerInfo" x-transition>
                            <label class="text-[9px] font-black text-amber-600 uppercase tracking-widest block mb-1">
                                Reason for Starting Float Difference *
                            </label>
                            <textarea name="opening_notes" x-model="openingNotes" rows="2" class="input-base !bg-amber-50/50 dark:!bg-slate-800 !border-amber-200 dark:!border-amber-800 !p-3 !text-xs !font-medium focus:!ring-amber-500" placeholder="e.g. Manager removed $800 to store vault overnight; starting float set to $200 change float..." :required="!isFirstReconciliation && openingVariance !== 0"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT ACTION -->
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition-all">
                        Cancel
                    </a>
                    <button type="submit" :disabled="!!openDrawerInfo || isSubmitting" class="btn-primary !bg-emerald-600 hover:!bg-emerald-700 disabled:!bg-slate-300 dark:disabled:!bg-slate-700 disabled:cursor-not-allowed !px-6 !py-3 !text-xs font-black uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                        <span x-show="!isSubmitting">Open Cash Drawer</span>
                        <span x-show="isSubmitting">Opening...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function cashReconciliationOpen() {
            return {
                reconciliationDate: '{{ date('Y-m-d') }}',
                accountId: '',
                warehouseId: '',
                accountBalances: @json($accounts->pluck('balance', 'id')),
                accountBalance: 0.00,
                openingBalance: 0.00,
                systemOpeningBalance: 0.00,
                openingNotes: '',
                isFirstReconciliation: false,
                openDrawerInfo: null,
                loading: false,

                get openingVariance() {
                    if (this.isFirstReconciliation) return 0.00;
                    return Math.round(((parseFloat(this.openingBalance) || 0) - (parseFloat(this.systemOpeningBalance) || 0)) * 100) / 100;
                },

                formatMoney(amount) {
                    return (parseFloat(amount) || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },

                onAccountChange() {
                    if (!this.accountId) {
                        this.accountBalance = 0.00;
                        this.openingBalance = 0.00;
                        this.systemOpeningBalance = 0.00;
                        this.openDrawerInfo = null;
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

                async fetchBreakdown() {
                    if (!this.accountId || !this.reconciliationDate) return;
                    this.loading = true;
                    try {
                        let url = `{{ route('accounts.cash-reconciliation.calculate-expected') }}?account_id=${this.accountId}&reconciliation_date=${this.reconciliationDate}`;
                        if (this.warehouseId) {
                            url += `&warehouse_id=${this.warehouseId}`;
                        }
                        const res = await fetch(url);
                        const json = await res.json();
                        if (json.success && json.data) {
                            if (json.data.account_balance !== undefined) {
                                this.accountBalance = parseFloat(json.data.account_balance) || 0.00;
                            }
                            this.openDrawerInfo = json.data.open_drawer || null;
                            this.isFirstReconciliation = Boolean(json.data.is_first_reconciliation);
                            if (!this.isFirstReconciliation && parseFloat(json.data.system_opening_balance) !== undefined) {
                                this.systemOpeningBalance = parseFloat(json.data.system_opening_balance) || 0.00;
                                this.openingBalance = this.systemOpeningBalance;
                            } else {
                                this.systemOpeningBalance = this.accountBalance;
                                this.openingBalance = this.accountBalance;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to fetch breakdown:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                isSubmitting: false,

                validateForm(e) {
                    if (this.isSubmitting) {
                        e.preventDefault();
                        return;
                    }
                    if (this.openDrawerInfo) {
                        e.preventDefault();
                        alert(`A cash drawer (${this.openDrawerInfo.code}) is already open for this account. Please close it first.`);
                        return;
                    }
                    if (!this.accountId) {
                        e.preventDefault();
                        alert('Please select a Cash Account.');
                        return;
                    }
                    if (!this.isFirstReconciliation && this.openingVariance !== 0 && !this.openingNotes.trim()) {
                        e.preventDefault();
                        alert('Please provide a reason note for the Starting Float Difference.');
                        return;
                    }
                    this.isSubmitting = true;
                }
            };
        }
    </script>
</x-app-layout>
