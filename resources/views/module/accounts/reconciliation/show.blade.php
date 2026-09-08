<x-app-layout title="Reconciliation Summary">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4 print:hidden">
            <div>
                <h1 class="text-xl font-black tracking-tight">
                    {{ $reconciliation->status === 'Open' ? 'Active Cash Drawer (Open)' : 'Reconciliation Details & Closing Slip' }}
                </h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="hover:text-primary-600 transition-colors text-[10px]">Cash Reconciliation</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-300 text-[10px] font-bold">{{ $reconciliation->reconciliation_code }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($reconciliation->status === 'Open')
                    @if($isOpener)
                        <a href="{{ route('accounts.cash-reconciliation.close-form', $reconciliation->id) }}" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Close Drawer (Evening Count)
                        </a>
                    @endif
                @else
                    <button onclick="window.print()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Closing Slip
                    </button>
                @endif
                <a href="{{ route('accounts.cash-reconciliation.index') }}" class="px-4 py-2 bg-slate-100 dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-700 dark:text-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">
                    Back to List
                </a>
            </div>
        </div>

        @if($reconciliation->status === 'Open')
            <!-- OPEN DRAWER ACTIVE VIEW -->
            <div class="max-w-4xl mx-auto space-y-4">
                
                <!-- STATUS BANNER -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-3xl p-6 shadow-xl relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 relative z-10">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-black/20 rounded-full text-xs font-black uppercase tracking-wider mb-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                Drawer Currently Open and Active
                            </div>
                            <h2 class="text-2xl font-black">{{ $reconciliation->account->account_name ?? 'Cash Drawer' }}</h2>
                            <p class="text-xs text-amber-100 mt-1">
                                Opened by <strong>{{ $reconciliation->opener->name ?? 'User' }}</strong> on {{ $reconciliation->reconciliation_date->format('F d, Y') }} at {{ $reconciliation->opened_at ? $reconciliation->opened_at->format('h:i A') : 'Morning' }}
                            </p>
                        </div>

                        <div class="text-left sm:text-right">
                            <div class="text-[10px] font-black uppercase tracking-widest text-amber-200">Confirmed Starting Float</div>
                            <div class="text-3xl font-black mt-0.5">
                                {{ format_currency($reconciliation->opening_balance) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIVE RUNNING ESTIMATE NOTICE -->
                <div class="p-4 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-3xl text-xs text-blue-900 dark:text-blue-200 flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <strong class="font-black">Live Running Estimate:</strong>
                        <span class="ml-1">The figures below represent live activity as of right now. Cash totals and closing balance will be finalized when the drawer is counted and closed this evening.</span>
                    </div>
                </div>

                <!-- OPENER RESTRICTION NOTICE IF DIFFERENT USER -->
                @if(!$isOpener)
                    <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-3xl text-xs text-rose-900 dark:text-rose-200 flex items-start gap-3">
                        <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div>
                            <strong class="font-black">Opener Restriction Active:</strong>
                            <span class="ml-1">Only <strong>{{ $reconciliation->opener->name ?? 'the user who opened this drawer' }}</strong> can close this drawer at end-of-day.</span>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- TIER 1: STARTING FLOAT AUDIT -->
                    <div class="card p-5">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            Starting Float Audit (Morning)
                        </h3>

                        <div class="space-y-2.5 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">System Suggestion:</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ format_currency($reconciliation->system_opening_balance) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-dark-border">
                                <span class="text-slate-400">Confirmed Float:</span>
                                <span class="font-black text-slate-900 dark:text-white">{{ format_currency($reconciliation->opening_balance) }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-400">Starting Variance:</span>
                                <span class="font-black {{ $reconciliation->opening_variance == 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ $reconciliation->opening_variance >= 0 ? '+' : '' }}{{ format_currency($reconciliation->opening_variance) }}
                                </span>
                            </div>
                        </div>

                        @if($reconciliation->opening_notes)
                            <div class="mt-3 p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300">
                                <strong class="font-bold">Reason Note:</strong> {{ $reconciliation->opening_notes }}
                            </div>
                        @endif
                    </div>

                    <!-- LIVE RUNNING ACTIVITY ESTIMATE -->
                    <div class="card p-5">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            Live Shift Activity (Running)
                        </h3>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-emerald-600 font-bold">+ Cash Sales:</span>
                                <span class="font-bold text-emerald-600">{{ format_currency($breakdown['cash_sales_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-emerald-600 font-bold">+ Deposits & Transfers In:</span>
                                <span class="font-bold text-emerald-600">{{ format_currency(($breakdown['cash_deposits_amount'] ?? 0) + ($breakdown['cash_transfers_in'] ?? 0)) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-rose-600 font-bold">- Refunds & Expenses:</span>
                                <span class="font-bold text-rose-600">-{{ format_currency(($breakdown['cash_refunds_amount'] ?? 0) + ($breakdown['cash_expenses_amount'] ?? 0)) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-slate-100 dark:border-dark-border">
                                <span class="font-black text-slate-800 dark:text-slate-100">Live Expected Cash:</span>
                                <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">
                                    {{ format_currency($liveExpectedClosing) }}
                                </span>
                            </div>
                        </div>

                        @if($isOpener)
                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-dark-border">
                                <a href="{{ route('accounts.cash-reconciliation.close-form', $reconciliation->id) }}" class="w-full py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    Proceed to Close Drawer
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        @else
            <!-- COMPLETED RECONCILIATION CLOSING SLIP -->
            <div class="card max-w-3xl mx-auto p-6 md:p-8 print:border-none print:shadow-none print:p-0">
                
                <!-- SLIP HEADER -->
                <div class="border-b border-slate-100 dark:border-dark-border pb-5 mb-5 text-center">
                    <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-wider">Daily Cash Drawer Closing Slip</h2>
                    <p class="text-xs text-slate-500 font-bold mt-1">CorevisysPOS &bull; Dual-Tier Financial Audit Report</p>
                    <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-xs font-black">
                        <span>Code: {{ $reconciliation->reconciliation_code }}</span>
                        <span>&bull;</span>
                        <span>Date: {{ \Carbon\Carbon::parse($reconciliation->reconciliation_date)->format('F d, Y') }}</span>
                    </div>
                </div>

                <!-- AUDIT METADATA GRID -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 dark:bg-slate-800/40 rounded-2xl border border-slate-100 dark:border-dark-border mb-6 text-xs">
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Cash Account</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $reconciliation->account->account_name ?? 'N/A' }}</strong>
                        <div class="text-[9px] text-slate-400">{{ $reconciliation->account->account_code ?? '' }}</div>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Warehouse</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $reconciliation->warehouse->warehouse_name ?? 'All Warehouses' }}</strong>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Opened / Closed By</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $reconciliation->opener->name ?? $reconciliation->user->name ?? 'User' }}</strong>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block">Audit Status</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $reconciliation->status === 'Adjusted' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $reconciliation->status }}
                        </span>
                    </div>
                </div>

                <!-- TIER 1: OPENING FLOAT AUDIT SUMMARY -->
                <div class="mb-6 bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-4 border border-slate-200/80 dark:border-dark-border text-xs">
                    <div class="flex justify-between items-center mb-2.5">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">1. Shift Starting Float Audit</h3>
                        @if($reconciliation->is_initial)
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 rounded-full text-[9px] font-black uppercase">Initial Baseline</span>
                        @elseif($reconciliation->opening_variance == 0)
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 rounded-full text-[9px] font-black uppercase">Matched Prior Close</span>
                        @elseif($reconciliation->opening_variance > 0)
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 rounded-full text-[9px] font-black uppercase">+{{ format_currency($reconciliation->opening_variance) }} Float Addition</span>
                        @else
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 rounded-full text-[9px] font-black uppercase">-{{ format_currency(abs($reconciliation->opening_variance)) }} Float Reduction</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div>
                            <span class="text-[9px] text-slate-400 font-bold block">System Suggestion (Prior Close)</span>
                            <strong class="text-slate-700 dark:text-slate-300 text-sm">{{ format_currency($reconciliation->system_opening_balance) }}</strong>
                        </div>
                        <div>
                            <span class="text-[9px] text-slate-400 font-bold block">Confirmed Starting Float</span>
                            <strong class="text-slate-900 dark:text-white text-sm">{{ format_currency($reconciliation->opening_balance) }}</strong>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <span class="text-[9px] text-slate-400 font-bold block">Opening Variance</span>
                            <strong class="{{ $reconciliation->opening_variance == 0 ? 'text-emerald-600' : 'text-amber-600' }} text-sm">
                                {{ $reconciliation->opening_variance >= 0 ? '+' : '' }}{{ format_currency($reconciliation->opening_variance) }}
                            </strong>
                        </div>
                    </div>

                    @if($reconciliation->opening_notes)
                    <div class="mt-3 pt-2.5 border-t border-slate-200 dark:border-dark-border text-[11px]">
                        <span class="font-bold text-slate-500">Opening Variance Note:</span>
                        <span class="text-slate-700 dark:text-slate-200 ml-1">{{ $reconciliation->opening_notes }}</span>
                    </div>
                    @endif
                </div>

                <!-- TIER 2: FINANCIAL RECONCILIATION BREAKDOWN TABLE -->
                <div class="mb-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">2. Shift Calculation & Closing Count</h3>
                    <div class="border border-slate-200 dark:border-dark-border rounded-2xl overflow-hidden">
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                                <tr class="bg-slate-50/50 dark:bg-slate-800/30">
                                    <td class="px-4 py-2.5 font-bold text-slate-700 dark:text-slate-300">Confirmed Starting Cash Float</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ format_currency($reconciliation->opening_balance) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 font-bold text-emerald-600">+ Cash Sales Payments Received</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-emerald-600">+{{ format_currency($reconciliation->cash_sales_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 font-bold text-emerald-600">+ Cash Deposits & Money Transfers In</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-emerald-600">+{{ format_currency($reconciliation->cash_deposits_amount + $reconciliation->cash_transfers_in) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 font-bold text-rose-600">- Cash Sales Return Refunds Paid Out</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-rose-600">-{{ format_currency($reconciliation->cash_refunds_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 font-bold text-rose-600">- Cash Expenses Paid Out</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-rose-600">-{{ format_currency($reconciliation->cash_expenses_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2.5 font-bold text-rose-600">- Money Transfers Out</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-rose-600">-{{ format_currency($reconciliation->cash_transfers_out) }}</td>
                                </tr>
                                <tr class="bg-slate-100 dark:bg-slate-800 font-black text-sm">
                                    <td class="px-4 py-3 text-slate-900 dark:text-white">Expected Closing Cash Balance</td>
                                    <td class="px-4 py-3 text-right text-slate-900 dark:text-white">{{ format_currency($reconciliation->expected_closing_balance) }}</td>
                                </tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-950/40 font-black text-base">
                                    <td class="px-4 py-3 text-emerald-900 dark:text-emerald-200">Actual Physically Counted Cash</td>
                                    <td class="px-4 py-3 text-right text-emerald-900 dark:text-emerald-200">{{ format_currency($reconciliation->counted_amount) }}</td>
                                </tr>
                                <tr class="font-black text-sm {{ $reconciliation->variance == 0 ? 'bg-slate-50 dark:bg-slate-800' : ($reconciliation->variance > 0 ? 'bg-blue-50 dark:bg-blue-950/40' : 'bg-rose-50 dark:bg-rose-950/40') }}">
                                    <td class="px-4 py-3">
                                        Closing Shift Variance
                                        @if($reconciliation->variance == 0)
                                            <span class="text-xs text-emerald-600 font-bold ml-2">(Balanced)</span>
                                        @elseif($reconciliation->variance > 0)
                                            <span class="text-xs text-blue-600 font-bold ml-2">(Surplus / Overage)</span>
                                        @else
                                            <span class="text-xs text-rose-600 font-bold ml-2">(Deficit / Shortage)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right {{ $reconciliation->variance == 0 ? 'text-emerald-700' : ($reconciliation->variance > 0 ? 'text-blue-700' : 'text-rose-700') }}">
                                        {{ $reconciliation->variance >= 0 ? '+' : '' }}{{ format_currency($reconciliation->variance) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DENOMINATIONS BREAKDOWN (IF RECORDED) -->
                @if($reconciliation->denominations && is_array($reconciliation->denominations) && count(array_filter($reconciliation->denominations)) > 0)
                <div class="mb-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Counted Currency Denominations</h3>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 p-3 bg-slate-50 dark:bg-slate-800/30 rounded-2xl border border-slate-100 dark:border-dark-border text-xs">
                        @foreach($reconciliation->denominations as $val => $qty)
                            @if($qty > 0)
                            @php $numVal = is_numeric($val) ? (float)$val : (float)ltrim($val, 'c'); @endphp
                            <div class="p-2 bg-white dark:bg-dark-card rounded-xl border border-slate-200 dark:border-dark-border text-center">
                                <div class="text-[9px] text-slate-400 font-bold">{{ $currencySymbol ?? '' }}{{ $numVal }} Bill</div>
                                <div class="font-black text-slate-800 dark:text-white">&times; {{ $qty }}</div>
                                <div class="text-[10px] font-bold text-primary-600">{{ format_currency($numVal * $qty) }}</div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- CLOSING NOTES & AUDIT FOOTER -->
                @if($reconciliation->notes)
                <div class="p-4 bg-slate-50 dark:bg-slate-800/40 rounded-2xl border border-slate-200 dark:border-dark-border mb-8 text-xs">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Closing Discrepancy Reason / Notes</span>
                    <p class="text-slate-700 dark:text-slate-200 font-medium">{{ $reconciliation->notes }}</p>
                </div>
                @endif

                <!-- SIGNATURE BLOCKS FOR PRINTING -->
                <div class="pt-8 border-t border-slate-200 dark:border-dark-border grid grid-cols-2 gap-8 text-center text-xs mt-6">
                    <div>
                        <div class="h-10"></div>
                        <div class="border-t border-slate-300 dark:border-dark-border pt-1 font-bold text-slate-600 dark:text-slate-400">Cashier Signature</div>
                    </div>
                    <div>
                        <div class="h-10"></div>
                        <div class="border-t border-slate-300 dark:border-dark-border pt-1 font-bold text-slate-600 dark:text-slate-400">Manager / Auditor Signature</div>
                    </div>
                </div>

            </div>
        @endif

    </div>
</x-app-layout>
