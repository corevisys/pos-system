<x-app-layout title="Add Deposit">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Deposit</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.deposit') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Deposit List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">New Deposit</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Add/Update Deposits</p>
            </div>
        </div>

        <div class="max-w-4xl" x-data="{ isSubmitting: false }">
            <x-card class="overflow-hidden p-0">
                <!-- Form Header -->
                <div class="px-5 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5">
                    <h2 class="text-sm font-black text-text-primary dark:text-white tracking-tight uppercase tracking-widest">Please Enter Valid Data</h2>
                </div>

                <div class="p-5 md:p-6">
                    <form action="{{ route('accounts.deposit.store') }}" method="POST" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                        @csrf

                        <!-- LEFT COLUMN -->
                        <div class="space-y-6">
                            <!-- Deposit Date -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Deposit Date <span class="text-danger">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <input type="date" name="deposit_date" value="{{ old('deposit_date', now()->format('Y-m-d')) }}" required class="input-base !pl-11 !py-2.5 !text-[11px] !font-bold">
                                </div>
                            </div>

                            <!-- Debit Account (Source / Optional) -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-danger">
                                    Debit Account (Source / Optional)
                                </label>
                                <x-searchable-select name="debit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="External Deposit (No Debit Account)" emptyValue="" placeholder="External Deposit (No Debit Account)" :value="old('debit_account_id')" />
                            </div>

                            <!-- Amount -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Amount <span class="text-danger">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" required placeholder="0.00" class="input-base !pl-10 !pr-5 !py-2.5 !text-[12px] !font-black tabular-nums">
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN -->
                        <div class="space-y-6">
                            <!-- Reference No -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Reference No.
                                </label>
                                <input type="text" name="reference_no" value="{{ old('reference_no') }}" placeholder="Enter reference number" class="input-base !py-2.5 !text-[11px] !font-bold tracking-wider">
                            </div>

                            <!-- Credit Account (Destination / Required) -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-success">
                                    Credit Account (Destination) <span class="text-danger">*</span>
                                </label>
                                <x-searchable-select name="credit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="Select Destination Account" emptyValue="" placeholder="Select Destination Account" :value="old('credit_account_id')" required />
                            </div>

                            <!-- Note -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Note
                                </label>
                                <textarea name="note" class="input-base min-h-[50px] !py-2.5 !text-[11px] !font-medium resize-none" placeholder="Enter any additional details...">{{ old('note') }}</textarea>
                            </div>
                        </div>

                        <!-- BUTTONS -->
                        <div class="md:col-span-2 flex flex-col md:flex-row justify-center items-center gap-4 mt-4 pt-4 border-t border-border dark:border-dark-border">
                            <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-56 !bg-emerald-600 hover:!bg-emerald-700 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                                <span x-show="!isSubmitting" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Save
                                </span>
                                <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving Deposit...
                                </span>
                            </button>
                            <a href="{{ route('accounts.deposit') }}" class="btn-secondary w-full md:w-56 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Close
                            </a>
                        </div>
                    </form>
                </div>
            </x-card>

            <!-- INFO CARD -->
            <div class="mt-4 p-4 bg-slate-900 rounded-card text-white flex items-center gap-4 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-3xl group-hover:bg-white/10 transition-colors"></div>
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h4 class="text-[9px] font-black uppercase tracking-widest text-emerald-400">Security & Ledger Note</h4>
                    <p class="text-[10px] font-bold text-slate-400 mt-0.5 leading-relaxed tracking-tight">Deposits record balanced double-entry transactions and update account balances with strict overdraft and solvency guards.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
