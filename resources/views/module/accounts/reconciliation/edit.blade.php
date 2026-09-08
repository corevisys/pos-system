<x-app-layout title="Edit Cash Reconciliation">
    <div x-data="{
        isSubmitting: false,
        expectedClosing: {{ (float) $reconciliation->expected_closing_balance }},
        countedAmount: {{ (float) $reconciliation->counted_amount }},
        get variance() {
            return (parseFloat(this.countedAmount || 0) - this.expectedClosing).toFixed(2);
        }
    }">
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Edit Cash Reconciliation</h1>
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
        </div>

        <div class="card max-w-2xl p-6">
            <div class="border-b border-slate-100 dark:border-dark-border pb-4 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-base font-black text-slate-800 dark:text-white">Adjust Reconciled Count</h2>
                        <p class="text-xs text-slate-400">Account: <strong>{{ $reconciliation->account->account_name }}</strong> | Date: <strong>{{ $reconciliation->reconciliation_date->format('d-m-Y') }}</strong></p>
                    </div>
                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 rounded-full text-[10px] font-black uppercase">
                        {{ $reconciliation->status }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('accounts.cash-reconciliation.update', $reconciliation->id) }}" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <!-- Expected Closing Balance (Read-Only) -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Expected Closing Balance (System Computed)</label>
                        <input type="text" readonly value="{{ format_currency($reconciliation->expected_closing_balance) }}" class="w-full bg-slate-100 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-xs font-bold text-slate-500 cursor-not-allowed">
                    </div>

                    <!-- Counted Amount (Editable) -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Actual Physical Counted Amount *</label>
                        <input type="number" step="0.01" min="0" name="counted_amount" x-model="countedAmount" required class="input-base !py-2 !px-4 !text-xs !font-bold">
                    </div>

                    <!-- Live Calculated Variance -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-400">Recalculated Variance:</span>
                        <span class="text-sm font-black font-mono" :class="variance < 0 ? 'text-rose-600' : (variance > 0 ? 'text-blue-600' : 'text-emerald-600')" x-text="variance">
                        </span>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Notes / Explanation</label>
                        <textarea name="notes" rows="3" class="input-base !py-2 !px-4 !text-xs !font-bold">{{ old('notes', $reconciliation->notes) }}</textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100 dark:border-dark-border">
                    <a href="{{ route('accounts.cash-reconciliation.show', $reconciliation->id) }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-widest">
                        Cancel
                    </a>
                    <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-primary-200/50 dark:shadow-none">
                        <span x-show="!isSubmitting">Save Changes</span>
                        <span x-show="isSubmitting">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
