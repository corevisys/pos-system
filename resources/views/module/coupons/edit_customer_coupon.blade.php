<x-app-layout title="Edit Customer Coupon">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Edit Customer Coupon</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('coupons.customer.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Customer Coupons</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Customer Voucher</span>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto" x-data="{
            couponCode: '{{ old('code', $coupon->code) }}',
            couponType: '{{ old('type', $coupon->type) }}',
            generateCode() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                let result = 'CUST-';
                for (let i = 0; i < 8; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                this.couponCode = result;
            }
        }">
            <!-- MAIN FORM CARD -->
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-6 border-b border-border-light dark:border-dark-border pb-4">
                    <div class="p-2.5 bg-primary/10 text-primary dark:bg-primary-950/40 dark:text-primary-400 rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-text-primary dark:text-dark-text uppercase tracking-wider">Edit Customer Voucher</h2>
                        <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">Update voucher for code: <span class="font-mono text-primary font-black">{{ $coupon->code }}</span></p>
                    </div>
                </div>

                @if($errors->any())
                    <div class="mb-5 p-3.5 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/50 rounded-xl text-rose-600 text-xs font-bold">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('coupons.customer.update', $coupon->id) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <!-- Form Fields Grid -->
                    <div class="grid grid-cols-1 gap-y-4">
                        <!-- Customer Name -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Customer <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <x-searchable-select name="customer_id" :options="$customers" labelKey="customer_name" valueKey="id" emptyOption="Select Customer" emptyValue="" placeholder="Select Customer" :value="old('customer_id', $coupon->customer_id)" required />
                            </div>
                        </div>

                        <!-- Occasion Name -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Occasion <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <input type="text" name="name" value="{{ old('name', $coupon->name) }}" placeholder="e.g., Birthday Gift, VIP Reward" class="input-base" required>
                            </div>
                        </div>

                        <!-- Coupon Code -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Coupon Code <span class="text-rose-500">*</span></label>
                            <div class="flex-1 flex gap-2">
                                <input type="text" name="code" x-model="couponCode" placeholder="Enter code" class="input-base !font-mono !font-black tracking-widest uppercase" required>
                                <button type="button" @click="generateCode()" class="btn-primary !p-2.5 shrink-0 flex items-center justify-center" title="Generate New Code">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Coupon Type -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Type <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <select name="type" x-model="couponType" class="input-base cursor-pointer" required>
                                    <option value="Percentage" {{ old('type', $coupon->type) === 'Percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                    <option value="Fixed" {{ old('type', $coupon->type) === 'Fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                </select>
                            </div>
                        </div>

                        <!-- Coupon Value -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Value <span class="text-rose-500">*</span></label>
                            <div class="flex-1 relative">
                                <span x-show="couponType === 'Fixed'" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[11px]">{{ $currencySymbol }}</span>
                                <input type="number" name="value" value="{{ old('value', $coupon->value) }}" step="0.01" min="0" :max="couponType === 'Percentage' ? 100 : null" placeholder="0.00" :class="couponType === 'Fixed' ? '!pl-8 !pr-4' : (couponType === 'Percentage' ? '!pl-4 !pr-8' : '')" class="input-base !font-black tabular-nums text-emerald-600 dark:text-emerald-400" required>
                                <span x-show="couponType === 'Percentage'" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[11px]">%</span>
                            </div>
                        </div>

                        <!-- Expire Date -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Expiry Date</label>
                            <div class="flex-1 relative">
                                <input type="date" name="expire_date" value="{{ old('expire_date', $coupon->expire_date ? \Carbon\Carbon::parse($coupon->expire_date)->format('Y-m-d') : '') }}" class="input-base">
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right">Status <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <select name="status" class="input-base cursor-pointer" required>
                                    <option value="1" {{ old('status', $coupon->status) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $coupon->status) == 0 ? 'selected' : '' }}>Inactive / Used</option>
                                </select>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="flex flex-col md:flex-row items-start gap-2 md:gap-6">
                            <label class="w-full md:w-40 text-[10px] font-black uppercase text-text-muted tracking-widest md:text-right mt-2">Description</label>
                            <div class="flex-1">
                                <textarea name="description" rows="3" placeholder="Optional details about this customer voucher..." class="input-base">{{ old('description', $coupon->description) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-border-light dark:border-dark-border mt-6">
                        <a href="{{ route('coupons.customer.list') }}" class="btn-secondary">
                            Close
                        </a>
                        <button type="submit" class="btn-primary">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Update Customer Coupon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
