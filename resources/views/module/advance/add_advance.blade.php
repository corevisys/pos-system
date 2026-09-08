<x-app-layout title="{{ isset($advance) ? 'Edit Advance Payment' : 'Add Advance Payment' }}">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">{{ isset($advance) ? 'Edit Advance' : 'New Advance' }}</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('advance.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Advance List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">{{ isset($advance) ? 'Edit Advance' : 'New Advance' }}</span>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto">
            <!-- MAIN FORM CARD -->
            <div class="card p-5 md:p-7">
                <div class="flex items-center gap-3 mb-6 border-b border-border-light dark:border-dark-border pb-4">
                    <div class="p-2.5 bg-primary-light text-primary rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-wider">{{ isset($advance) ? 'Edit Advance Payment' : 'New Advance Payment' }}</h2>
                        <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">Capture customer advance deposit into ledger</p>
                    </div>
                </div>

                <form action="{{ isset($advance) ? route('advance.update', $advance->id) : route('advance.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Form Fields Grid -->
                    <div class="grid grid-cols-1 gap-y-4">
                        
                        <!-- Date -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">
                                Date <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex-1 relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                                <input type="date" name="payment_date" value="{{ old('payment_date', isset($advance) ? $advance->payment_date : date('Y-m-d')) }}" required class="input-base !pl-10">
                            </div>
                        </div>

                        <!-- Customer Name -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">
                                Customer <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex-1 flex items-center gap-2">
                                <div class="flex-1">
                                    <x-searchable-select name="customer_id" :options="$customers" labelKey="customer_name" valueKey="id" emptyOption="Select Customer" emptyValue="" placeholder="Select Customer" :value="old('customer_id', isset($advance) ? $advance->customer_id : '')" required />
                                </div>
                                <a href="{{ route('contacts.customers.add') }}" title="Add New Customer" class="p-2.5 bg-background dark:bg-dark-bg text-primary border border-border dark:border-dark-border rounded-xl hover:bg-primary-light transition-all flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">
                                Amount <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex-1 relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted font-bold text-xs pointer-events-none">
                                    {{ $currencySymbol }}
                                </span>
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', isset($advance) ? $advance->amount : '') }}" placeholder="0.00" required class="input-base !pl-10 tabular-nums">
                            </div>
                        </div>

                        <!-- Payment Type -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">
                                Payment Type <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex-1">
                                <select name="payment_type" required class="input-base cursor-pointer">
                                    <option value="">-Select Payment Type-</option>
                                    @php
                                        $selectedType = old('payment_type', isset($advance) ? $advance->payment_type : 'Cash');
                                    @endphp
                                    <option value="Cash" {{ $selectedType == 'Cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="Card" {{ $selectedType == 'Card' ? 'selected' : '' }}>Card</option>
                                    <option value="Cheque" {{ $selectedType == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                    <option value="Bank Transfer" {{ $selectedType == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="Other" {{ $selectedType == 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Deposit Account -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">
                                Account <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex-1">
                                <x-searchable-select name="account_id" :options="$accounts" labelKey="account_name" valueKey="id" emptyOption="Select Account" emptyValue="" placeholder="Select Account" :value="old('account_id', isset($advance) ? $advance->account_id : '')" required />
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="flex flex-col md:flex-row items-start gap-2 md:gap-6">
                            <label class="w-full md:w-36 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right mt-2">
                                Note
                            </label>
                            <div class="flex-1">
                                <textarea name="note" rows="3" placeholder="Additional details..." class="input-base">{{ old('note', isset($advance) ? $advance->note : '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end items-center gap-3 pt-4 border-t border-border-light dark:border-dark-border mt-6">
                        <a href="{{ route('advance.list') }}" class="btn-secondary">
                            Close
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            {{ isset($advance) ? 'Update Advance' : 'Save Advance' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>