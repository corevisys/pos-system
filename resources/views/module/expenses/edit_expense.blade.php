<x-app-layout title="Edit Expense">
    <div>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight flex items-center gap-2 text-text-primary dark:text-dark-text">
                    <div class="w-8 h-8 rounded-xl bg-danger/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    Edit Expense
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px]">Home</a>
                    <span class="text-[10px] text-text-muted">/</span>
                    <a href="{{ route('expenses.list') }}" class="hover:text-primary transition-colors text-[10px]">Expenses List</a>
                    <span class="text-[10px] text-text-muted">/</span>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Expense #{{ $expense->id }}</span>
                </div>
            </div>
            <a href="{{ route('expenses.list') }}" class="btn-secondary w-full md:w-auto">Back to List</a>
        </div>

        <form action="{{ route('expenses.update', $expense->id) }}" method="POST" class="max-w-4xl mx-auto" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            <x-card class="overflow-hidden p-0">
                <div class="px-8 py-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date) }}" required class="input-base !pl-4 !py-3.5 !text-[11px] !font-bold">
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Payment Type <span class="text-danger">*</span></label>
                            <select name="payment_type" required class="input-base !py-3.5 !text-[11px] !font-bold appearance-none cursor-pointer">
                                @foreach($paymentTypes as $pt)
                                    <option value="{{ $pt }}" {{ old('payment_type', $expense->payment_type ?? 'Cash') === $pt ? 'selected' : '' }}>{{ $pt }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Category <span class="text-danger">*</span></label>
                            <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="Select Category" emptyValue="" placeholder="Select Category" :value="old('category_id', $expense->category_id)" required />
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Expense For <span class="text-danger">*</span></label>
                            <input type="text" name="expense_for" value="{{ old('expense_for', $expense->expense_for) }}" required placeholder="e.g. Office Supplies" class="input-base !py-3.5 !text-[11px] !font-bold">
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Reference No.</label>
                            <input type="text" name="reference_no" value="{{ old('reference_no', $expense->reference_no) }}" placeholder="Optional reference" class="input-base !py-3.5 !text-[11px] !font-bold">
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Amount <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[11px]">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" name="expense_amt" value="{{ old('expense_amt', $expense->expense_amt) }}" required class="input-base !pl-9 !pr-5 !py-3.5 !text-[12px] !font-black tabular-nums text-danger">
                            </div>
                        </div>

                        <div class="group relative md:col-span-2">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Account</label>
                            @if($accounts->isNotEmpty())
                                <x-searchable-select name="account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="No Account (Standalone Expense)" emptyValue="" placeholder="No Account (Standalone Expense)" :value="old('account_id', $expense->account_id)" />
                            @else
                                <input type="hidden" name="account_id" value="">
                            @endif
                        </div>

                        <div class="group relative md:col-span-2">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[8px] font-black uppercase text-text-muted tracking-widest z-10">Note</label>
                            <textarea name="note" rows="2" placeholder="Add a note..." class="input-base !py-3.5 !text-[11px] !font-bold resize-none">{{ old('note', $expense->note) }}</textarea>
                        </div>

                    </div>

                    <div class="mt-8 p-4 rounded-card bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/30 flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="text-[10px] text-amber-800 dark:text-amber-300">
                            <span class="font-bold">Ledger Integrity Notice:</span> Modifying the amount or account reverses the previous ledger impact with auditable <span class="font-mono font-bold">EXPENSE REVERSAL</span> entries and writes new forward <span class="font-mono font-bold">EXPENSE</span> entries. Editing is blocked inside closed/adjusted cash reconciliation periods.
                        </div>
                    </div>

                    <div class="mt-10 flex flex-col md:flex-row gap-4">
                        <button type="submit" :disabled="isSubmitting" class="btn-primary flex-1 !bg-amber-600 hover:!bg-amber-700 !py-4 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                            <span x-show="!isSubmitting" class="flex items-center justify-center gap-3">Update Expense</span>
                            <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Updating Expense...
                            </span>
                        </button>
                        <a href="{{ route('expenses.list') }}" class="btn-secondary px-10 !py-4 !text-[11px] uppercase tracking-widest">Cancel</a>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
