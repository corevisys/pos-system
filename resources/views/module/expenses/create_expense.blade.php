<x-app-layout title="Add Expense">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Add Expense</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('expenses.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold text-slate-400">Expenses List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold">New Expense</span>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto">


            <!-- MAIN FORM CARD -->
            <div class="bg-white dark:bg-dark-card p-4 md:p-6 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm">
                
                <div class="flex items-center gap-3 mb-6 border-b border-slate-50 dark:border-dark-border pb-4">
                    <div class="p-2 bg-rose-50 dark:bg-rose-500/10 rounded-xl">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">New Expense Entry</h2>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Record business expenditures</p>
                    </div>
                </div>

                <form action="{{ route('expenses.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <!-- Form Fields Grid -->
                    <div class="grid grid-cols-1 gap-y-4">
                        
                        <!-- Expense Date -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Expense Date <span class="text-rose-500">*</span></label>
                            <div class="flex-1 relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-10 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">
                            </div>
                        </div>

                        <!-- Category -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Category <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="Select Category" emptyValue="" placeholder="Select Category" :value="old('category_id')" required />
                            </div>
                        </div>

                        <!-- Expense For -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Expense For <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <input type="text" name="expense_for" placeholder="e.g. Office Supplies" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">
                            </div>
                        </div>

                        <!-- Reference No -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Reference No.</label>
                            <div class="flex-1">
                                <input type="text" name="reference_no" placeholder="Optional" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Amount <span class="text-rose-500">*</span></label>
                            <div class="flex-1 relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]">{{ $currencySymbol }}</span>
                                <input type="number" name="expense_amt" step="0.01" placeholder="0.00" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-10 text-[10px] font-black tabular-nums transition-all focus:ring-1 focus:ring-primary-500 text-rose-600">
                            </div>
                        </div>

                        <!-- Account -->
                        @if($accounts->isNotEmpty())
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Account</label>
                            <div class="flex-1">
                                <x-searchable-select name="account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="Select Account" emptyValue="" placeholder="Select Account" :value="old('account_id')" />
                            </div>
                        </div>
                        @else
                         <input type="hidden" name="account_id" value="">
                        @endif

                        <!-- Note -->
                        <div class="flex flex-col md:flex-row items-start gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right mt-2">Note</label>
                            <div class="flex-1">
                                <textarea name="note" rows="3" placeholder="Additional details..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center gap-3 pt-4 border-t border-slate-50 dark:border-dark-border mt-6">
                        <button type="submit" class="px-8 py-2 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all shadow-lg shadow-rose-200/50 dark:shadow-none flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Save Expense
                        </button>
                        <a href="{{ route('expenses.list') }}" class="px-8 py-2 bg-amber-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 transition-all shadow-lg shadow-amber-200/50 dark:shadow-none flex items-center justify-center">
                            Close
                        </a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-app-layout>
