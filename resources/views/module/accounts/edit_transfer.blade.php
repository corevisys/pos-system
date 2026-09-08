<x-app-layout title="Edit Money Transfer">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight flex items-center gap-2 text-text-primary dark:text-dark-text">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    Edit Money Transfer
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.transfer') }}" class="hover:text-primary transition-colors text-[10px]">Money Transfer List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Transfer</span>
                </div>
            </div>

            <a href="{{ route('accounts.transfer') }}" class="btn-secondary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
        </div>

        <form action="{{ route('accounts.transfer.update', $transfer->id) }}" method="POST" class="max-w-4xl mx-auto" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            @method('PUT')
            <x-card class="overflow-hidden p-0">
                <div class="px-8 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">

                        <!-- Transfer Date -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Transfer Date <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-focus-within:text-primary transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <input type="date" name="transfer_date" value="{{ old('transfer_date', $transfer->transfer_date) }}" required class="input-base !pl-12 !py-3.5 !text-[11px] !font-bold">
                            </div>
                        </div>

                        <!-- Transfer Code (read-only) -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Transfer Code <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                                </span>
                                <input type="text" value="{{ $transfer->transfer_code }}" disabled readonly class="input-base !pl-12 !py-3.5 !text-[11px] !font-black !text-primary !tracking-wider !font-mono !bg-background dark:!bg-slate-900">
                            </div>
                        </div>

                        <!-- Debit Account (From) -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-danger">From Account <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-focus-within:text-danger transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <div class="pl-4">
                                    <x-searchable-select name="debit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="Select Source Account" emptyValue="" placeholder="Select Source Account" :value="old('debit_account_id', $transfer->debit_account_id)" required />
                                </div>
                            </div>
                        </div>

                        <!-- Credit Account (To) -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-success">To Account <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-focus-within:text-success transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <div class="pl-4">
                                    <x-searchable-select name="credit_account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="Select Destination Account" emptyValue="" placeholder="Select Destination Account" :value="old('credit_account_id', $transfer->credit_account_id)" required />
                                </div>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="group relative md:col-span-2">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Amount <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-focus-within:text-primary transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 8h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2z"></path></svg>
                                </span>
                                <input type="number" step="0.01" name="amount" value="{{ old('amount', $transfer->amount) }}" required class="input-base !pl-14 !py-5 !text-2xl !font-black tabular-nums">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-[10px] font-black uppercase tracking-widest text-text-muted">{{ $currencySymbol }}</span>
                            </div>
                        </div>

                        <!-- Reference No -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Reference No.</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                                </span>
                                <input type="text" name="reference_no" value="{{ old('reference_no', $transfer->reference_no) }}" placeholder="Optional reference" class="input-base !pl-12 !py-3.5 !text-[11px] !font-bold">
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Note</label>
                            <div class="relative">
                                <span class="absolute left-4 top-6 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </span>
                                <textarea name="note" rows="1" placeholder="Add a note..." class="input-base !pl-12 !py-3.5 !text-[11px] !font-bold resize-none">{{ old('note', $transfer->note) }}</textarea>
                            </div>
                        </div>

                    </div>

                    <!-- AUDIT NOTICE -->
                    <div class="mt-8 p-4 rounded-card bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/30 flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="text-[10px] text-amber-800 dark:text-amber-300">
                            <span class="font-bold">Ledger Integrity Notice:</span> Modifying accounts or amount will automatically reverse the previous ledger impact with auditable <span class="font-mono font-bold">TRANSFER REVERSAL</span> entries and write new forward entries. The destination account must have sufficient balance to support the reversal.
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="mt-10 flex flex-col md:flex-row gap-4">
                        <button type="submit" :disabled="isSubmitting" class="btn-primary flex-1 !bg-amber-600 hover:!bg-amber-700 !py-4 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                            <span x-show="!isSubmitting" class="flex items-center justify-center gap-3">
                                Update Transfer
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Updating Transfer...
                            </span>
                        </button>
                        <a href="{{ route('accounts.transfer') }}" class="btn-secondary px-10 !py-4 !text-[11px] uppercase tracking-widest">Cancel</a>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
