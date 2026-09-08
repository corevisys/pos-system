<x-app-layout title="Edit Service">
    <div x-data="{
        discountType: '{{ old('discount_type', $service->discount_type ?? 'Percentage(%)') }}',
        taxType: '{{ old('tax_type', $service->tax_type ?? 'Inclusive') }}'
    }">
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Edit Service <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Management</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.service.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-black uppercase tracking-wider">Services List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Edit Service</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden relative">
            <div class="p-6 md:p-8 relative z-10">
                <form action="{{ route('items.service.update', $service->id) }}" method="POST" class="space-y-8" enctype="multipart/form-data">
                    @csrf
                    
                    <!-- SECTION 1: SERVICE INFORMATION -->
                    <div>
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-primary-500 rounded-full"></span>
                            Service Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Item Name -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                    Item Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="item_name" value="{{ old('item_name', $service->item_name) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                                @error('item_name') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>

                            <!-- Category -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                    Category <span class="text-rose-500">*</span>
                                </label>
                                <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Category" :value="old('category_id', $service->category_id)" required />
                                @error('category_id') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>

                            <!-- Item Code -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                    Item Code <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="item_code" value="{{ old('item_code', $service->item_code) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all font-mono tracking-wider" readonly>
                                @error('item_code') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>

                            <!-- HSN -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">HSN</label>
                                <input type="text" name="hsn" value="{{ old('hsn', $service->hsn) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </div>

                            <!-- Seller Points -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Seller Points</label>
                                <input type="number" name="seller_points" value="{{ old('seller_points', $service->seller_points) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-black tabular-nums text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </div>

                            <!-- Description -->
                            <div class="group relative col-span-1 md:col-span-2 lg:col-span-2">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Description</label>
                                <input type="text" name="description" value="{{ old('description', $service->description) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-medium text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </div>

                            <!-- Image -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500 text-nowrap">Image</label>
                                <div class="relative w-full h-[38px] bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl flex items-center px-4 overflow-hidden group/file hover:border-primary-500 transition-all cursor-pointer">
                                    <input type="file" name="item_image" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        Change File
                                    </span>
                                </div>
                                @if($service->item_image)
                                    <p class="text-[8px] text-slate-400 mt-1 uppercase tracking-widest">Current: {{ basename($service->item_image) }}</p>
                                @endif
                                @error('item_image') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-slate-50 dark:bg-dark-border"></div>

                    <!-- SECTION 2: DISCOUNT & PRICING -->
                    <div>
                         <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-6 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                            Pricing & Tax
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Discount Type -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Discount Type</label>
                                <div class="relative">
                                    <select name="discount_type" x-model="discountType" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer transition-all">
                                        <option>Percentage(%)</option>
                                        <option>Fixed Amount</option>
                                    </select>
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Discount</label>
                                <div class="relative">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]" x-text="discountType === 'Percentage(%)' ? '%' : '{{ $currencySymbol }}'"></span>
                                    <input type="number" name="discount" value="{{ old('discount', $service->discount) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-black tabular-nums text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 pr-8 transition-all">
                                </div>
                            </div>

                            <!-- Price -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500 text-nowrap">Price <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" name="price" value="{{ old('price', $service->price) }}" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-9 pr-4 text-[11px] font-black tabular-nums text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition-all">
                                </div>
                                @error('price') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>

                            <!-- Tax -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Tax</label>
                                <x-searchable-select name="tax_id" :options="$taxes" labelKey="tax_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Tax" :value="old('tax_id', $service->tax_id)" />
                            </div>

                            <!-- Sales Tax Type -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Sales Tax Type</label>
                                <div class="relative">
                                    <select name="tax_type" x-model="taxType" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer transition-all">
                                        <option>Inclusive</option>
                                        <option>Exclusive</option>
                                    </select>
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Price -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">Sales Price <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 font-bold text-[10px]">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" name="sales_price" value="{{ old('sales_price', $service->sales_price) }}" class="w-full bg-primary-50 dark:bg-slate-800 border border-primary-200 dark:border-primary-900 rounded-xl py-2.5 pl-9 pr-4 text-[11px] font-black text-primary-600 dark:text-primary-400 outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 tabular-nums shadow-sm transition-all">
                                </div>
                                @error('sales_price') <p class="text-[9px] text-rose-500 mt-1 font-bold uppercase tracking-wider">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- FORM FOOTER -->
                    <div class="flex flex-col md:flex-row justify-center items-center gap-4 mt-8 pt-8 border-t border-slate-50 dark:border-dark-border">
                        <button type="submit" class="w-full md:w-auto px-8 py-3 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-200/50">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Update Service
                        </button>
                        <a href="{{ route('items.service.list') }}" class="w-full md:w-auto px-8 py-3 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
