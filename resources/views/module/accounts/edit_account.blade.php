<x-app-layout title="Edit Account">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Accounts</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Accounts List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Account</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Update Account Information</p>
            </div>
        </div>

        <div class="max-w-4xl">
            <x-card class="overflow-hidden p-0">
                <!-- Form Header -->
                <div class="px-5 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-black text-text-primary dark:text-white tracking-tight uppercase tracking-widest">Edit Account Details</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-mono font-black uppercase tracking-wider bg-background dark:bg-slate-800 text-text-muted dark:text-dark-text">
                        {{ $account->account_code }}
                    </span>
                </div>

                <div class="p-5 md:p-6">
                    <form action="{{ route('accounts.update', $account->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
                        @csrf
                        @method('PUT')

                        <!-- LEFT COLUMN -->
                        <div class="space-y-6">
                            <!-- Parent Account -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Parent Account Head
                                </label>
                                <x-searchable-select name="parent_account" :options="$parentAccounts" labelKey="account_name" valueKey="id" emptyOption="-CREATE ACCOUNT HEAD-" emptyValue="" placeholder="Select Parent Account" :value="old('parent_account', $account->parent_id)" />
                            </div>

                            <!-- Account Number (Read-only) -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">
                                    Account Number <span class="text-text-muted">(Read-Only)</span>
                                </label>
                                <input type="text" value="{{ $account->account_code }}" readonly disabled class="input-base !py-2.5 !px-5 !text-[11px] !font-black !text-text-muted !tracking-wider cursor-not-allowed uppercase !font-mono !bg-background/70 dark:!bg-slate-800/40">
                            </div>

                            <!-- Account Name -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Account Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" placeholder="E.g. Business Savings" class="input-base !py-2.5 !px-5 !text-[11px] !font-bold" required>
                            </div>

                            <!-- Current Balance (Read-only) -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">
                                    Opening / Current Balance <span class="text-text-muted">(Read-Only)</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                    <input type="text" value="{{ format_currency($account->balance) }}" readonly disabled class="input-base !pl-10 !pr-5 !py-2.5 !text-[12px] !font-black tabular-nums cursor-not-allowed !bg-background/70 dark:!bg-slate-800/40">
                                </div>
                                <p class="text-[10px] font-bold italic text-text-muted mt-1">Opening balance cannot be edited after creation — use Cash Transactions to record adjustments.</p>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN -->
                        <div class="space-y-6 flex flex-col">
                            <!-- Note -->
                            <div class="group relative flex-grow">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                    Additional Note
                                </label>
                                <textarea name="note" class="input-base min-h-[160px] !rounded-card !py-3 !px-5 !text-[11px] !font-medium resize-none" placeholder="Enter any additional account details...">{{ old('note', $account->note) }}</textarea>
                            </div>
                        </div>

                        <!-- BUTTONS -->
                        <div class="md:col-span-2 flex flex-col md:flex-row justify-end items-center gap-3 mt-4 pt-4 border-t border-border dark:border-dark-border">
                            <a href="{{ route('accounts.list') }}" class="btn-secondary w-full md:w-auto px-6 !text-[10px] uppercase tracking-widest">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Close
                            </a>
                            <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-48 !py-2.5 !text-[10px] uppercase tracking-widest disabled:opacity-75 disabled:cursor-not-allowed">
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin -ml-1 mr-2 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </template>
                                <template x-if="!isSubmitting">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </template>
                                <span x-text="isSubmitting ? 'Saving...' : 'Update Account'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
