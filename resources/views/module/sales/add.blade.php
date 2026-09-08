<x-app-layout title="Add Sale">
    <div x-data="saleComponent()">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Add Sale</h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 italic">Create a new sales transaction.</p>
            </div>
            <!-- Header Actions Removed -->
        </div>

        <div class="grid grid-cols-1 gap-6">
            
            <!-- SECTION 1: SALE INFO -->
            <div class="card p-3 md:p-4 rounded-card shadow-card">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
                    <!-- Warehouse -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Warehouse <span class="text-rose-500">*</span></label>
                        <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="saleInfo.warehouse" />
                    </div>

                    <!-- Sales Code -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Sales Code</label>
                        <div class="flex">
                            <input type="text" x-model="saleInfo.salesCode" readonly class="flex-1 bg-slate-100 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-bold transition-all outline-none cursor-not-allowed">
                        </div>
                    </div>

                    <!-- Customer Name -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Customer Name <span class="text-rose-500">*</span></label>
                        <x-searchable-select :options="$customers" labelKey="customer_name" valueKey="id" subtextKey="mobile" emptyOption="Walk-in customer" emptyValue="Walk-in customer" placeholder="Select Customer" model="saleInfo.customer" />
                        <p class="text-[9px] font-black text-rose-500 px-1">Prev Due: <span class="tabular-nums" x-text="'{{ $currencySymbol }}' + previousDue.toFixed(2)"></span></p>
                    </div>

                    <!-- Sales Date -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Sales Date <span class="text-rose-500">*</span></label>
                        <input type="date" x-model="saleInfo.salesDate" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-bold transition-all focus:ring-1 focus:ring-primary-500 outline-none">
                    </div>

                    <!-- Reference No -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Reference No</label>
                        <input type="text" x-model="saleInfo.referenceNo" placeholder="Ref: #REF-001" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-bold transition-all focus:ring-1 focus:ring-primary-500 outline-none">
                    </div>

                    <!-- Due Date -->
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block px-0.5">Due Date</label>
                        <input type="date" x-model="saleInfo.dueDate" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-bold transition-all focus:ring-1 focus:ring-primary-500 outline-none">
                    </div>
                </div>
            </div>

            <!-- SECTION 2: ITEM SEARCH & TABLE -->
            <div class="card rounded-card overflow-hidden shadow-card">
                <!-- Search Bar -->
                <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5">
                    <div class="flex gap-2">
                        <div class="relative flex-1 group" @click.away="searchResults = []">
                            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary-500 transition-colors z-10">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <input type="text" x-model="searchQuery" x-ref="itemSearchInput"
                                   @input.debounce.300ms="fetchItems()"
                                   @keydown.enter.prevent="handleEnterKey()"
                                   placeholder="Item name / Barcode / Itemcode"
                                   class="w-full relative z-0 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 pl-10 pr-4 focus:ring-1 focus:ring-primary-500 font-bold text-[11px] transition-all shadow-inner outline-none">
                            
                            <!-- SEARCH RESULTS DROPDOWN -->
                            <div x-show="searchQuery.length > 0 && searchResults.length > 0" 
                                 class="absolute z-[100] w-full mt-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                <template x-for="p in searchResults" :key="p.id">
                                    <div @click="addToCart(p)" class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors border-b border-slate-50 dark:border-dark-border last:border-none flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 flex-1">
                                            <!-- Thumbnail -->
                                            <div class="w-8 h-8 rounded-lg overflow-hidden border border-slate-100 dark:border-dark-border bg-slate-50 dark:bg-slate-800 flex-shrink-0">
                                                <template x-if="p.item_image">
                                                    <img :src="'/' + p.item_image" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!p.item_image">
                                                    <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002-2z"></path></svg>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-[10px] font-black text-slate-700 dark:text-slate-300">
                                                    <span x-text="p.item_code" class="text-primary-600 mr-1"></span>
                                                    <span x-text="p.item_name"></span>
                                                </p>
                                                <p class="text-[8px] text-slate-400 font-bold uppercase tracking-widest">
                                                    Stock: <span x-text="p.stock || 0" :class="(p.stock || 0) > 0 ? 'text-emerald-500' : 'text-rose-500'"></span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[11px] font-black text-primary-600 block" x-text="'{{ $currencySymbol }}' + parseFloat(p.sales_price).toFixed(2)"></p>
                                            <p class="text-[8px] font-bold text-slate-400 block line-through" x-show="parseFloat(p.discount_amount) > 0" x-text="'{{ $currencySymbol }}' + parseFloat(p.regular_price).toFixed(2)"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Item</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Qty</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Rate ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Disc ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Tax Amt ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Tax(%)</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Total ({{ $currencySymbol }})</th>
                                <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            <template x-if="cart.length === 0">
                                <tr>
                                    <td colspan="8" class="px-6 py-10 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-2xl flex items-center justify-center text-slate-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                            </div>
                                            <div>
                                                <p class="text-slate-500 font-black uppercase tracking-widest text-[9px]">No Items Added</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-for="item in cart" :key="item.id">
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-4 py-2">
                                        <p class="text-[11px] font-black text-slate-700 dark:text-slate-200" x-text="item.name"></p>
                                        <template x-if="item.selectedSerials !== undefined">
                                            <div class="mt-1">
                                                <div class="flex gap-2">
                                                    <span class="text-[8px] font-bold text-slate-400 uppercase">SN: <span x-text="item.serial" class="text-slate-500 font-black italic"></span></span>
                                                </div>
                                                <button @click="openSerialModal(item)" class="mt-1.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 rounded-lg px-2 py-1 text-[9px] font-black uppercase tracking-widest border border-primary-200 dark:border-primary-500/20 hover:bg-primary-100 transition-all flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    Select SN
                                                </button>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" x-model="item.qty" @input="calculateTotals()" 
                                               :readonly="item.selectedSerials !== undefined"
                                               :class="item.selectedSerials !== undefined ? 'bg-slate-100 cursor-not-allowed opacity-75' : 'bg-slate-50'"
                                               class="w-14 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-lg py-1 px-2 text-[10px] font-black text-center focus:ring-1 focus:ring-primary-500 outline-none transition-all">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" x-model="item.price" @input="calculateTotals()" class="w-20 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-lg py-1 px-2 text-[10px] font-black focus:ring-1 focus:ring-primary-500 outline-none transition-all">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" x-model="item.discount" @input="calculateTotals()" class="w-14 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-lg py-1 px-2 text-[10px] font-black focus:ring-1 focus:ring-primary-500 outline-none transition-all">
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-[10px] font-bold text-slate-500 tabular-nums" x-text="item.taxAmount.toFixed(2)"></span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" x-model="item.tax" @input="calculateTotals()" class="w-14 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-lg py-1 px-2 text-[10px] font-black focus:ring-1 focus:ring-primary-500 outline-none transition-all">
                                    </td>
                                    <td class="px-4 py-2">
                                        <p class="text-[11px] font-black text-primary-600 tabular-nums" x-text="item.total.toFixed(2)"></p>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <button @click="removeItem(item.id)" class="p-1 px-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary Row -->
                <div class="p-3 bg-slate-100/30 dark:bg-slate-800/30 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 border-t border-slate-50 dark:border-dark-border">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 px-1">Total Qty</p>
                        <div class="bg-white dark:bg-dark-card rounded-xl p-2 border border-slate-100 dark:border-dark-border shadow-sm">
                            <p class="text-base font-black text-primary-600" x-text="cart.reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0)"></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 px-1">Other Charges</p>
                        <div class="relative">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]">{{ $currencySymbol }}</span>
                            <input type="number" x-model="otherCharges" class="w-full bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl py-1.5 pl-7 pr-3 shadow-sm text-[11px] font-bold focus:ring-1 focus:ring-primary-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 px-1">Coupon</p>
                        <div class="flex gap-1.5" x-show="!appliedCoupon">
                            <input type="text" x-model="couponCode" placeholder="Code" @keydown.enter.prevent="applyCoupon()" class="w-full bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl py-1.5 px-3 shadow-sm text-[11px] font-bold outline-none uppercase">
                            <button type="button" @click="applyCoupon()" :disabled="couponLoading" class="px-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-[9px] font-black uppercase tracking-wider transition-all disabled:opacity-50 flex items-center justify-center">
                                <span x-show="!couponLoading">Apply</span>
                                <span x-show="couponLoading" class="animate-pulse">...</span>
                            </button>
                        </div>
                        <div x-show="appliedCoupon" class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-xl py-1 px-2 text-[10px]" x-cloak>
                            <div class="flex flex-col truncate">
                                <span class="font-black text-emerald-700 dark:text-emerald-300" x-text="appliedCoupon ? appliedCoupon.code : ''"></span>
                                <span class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400" x-text="'Saved ' + '{{ $currencySymbol }}' + parseFloat(couponDiscount || 0).toFixed(2)"></span>
                            </div>
                            <button type="button" @click="removeCoupon()" class="text-rose-500 hover:text-rose-700 p-0.5 rounded-lg ml-1" title="Remove Coupon">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 px-1">Global Disc</p>
                        <div class="flex gap-1.5">
                            <div class="relative flex-1">
                                <span x-show="discountType === 'fixed'" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]" x-cloak>{{ $currencySymbol }}</span>
                                <input type="number" x-model="discountOnAll" :class="discountType === 'fixed' ? 'pl-7' : 'px-3'" class="w-full bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl py-1.5 pr-3 shadow-sm text-[11px] font-bold focus:ring-1 focus:ring-primary-500 outline-none">
                            </div>
                            <select x-model="discountType" class="w-14 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl py-1.5 px-1 shadow-sm text-[9px] font-black uppercase tracking-widest outline-none">
                                <option value="fixed">{{ $currencySymbol }}</option>
                                <option value="percent">%</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- NOTES & PREVIOUS PAYMENTS -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Note Section -->
                    <div class="card p-3 md:p-4 rounded-card shadow-card">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 px-0.5">Sale Note</label>
                        <textarea rows="2" x-model="saleNote" placeholder="Write any specific notes..." class="input-base resize-none"></textarea>
                    </div>

                    <!-- Previous Payments Information -->
                    <div class="card rounded-card overflow-hidden shadow-card">
                        <div class="px-4 py-2 border-b border-border-light dark:border-dark-border bg-background/50 dark:bg-white/5">
                            <h3 class="text-[11px] font-black tracking-tight">Previous Payments Information</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                                    <tr>
                                        <th class="px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest">#</th>
                                        <th class="px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                                        <th class="px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest">Type</th>
                                        <th class="px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest">Amount</th>
                                        <th class="px-4 py-2 text-[8px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-slate-400 text-[8px] font-black uppercase tracking-widest">No Payments history</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Invoice Terms -->
                    <div x-data="{ open: false }" class="card rounded-card shadow-card overflow-hidden">
                        <button @click="open = !open" class="w-full px-4 py-2 flex justify-between items-center hover:bg-slate-50/50 dark:hover:bg-white/5 transition-colors">
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Invoice Terms and Conditions</span>
                            <div class="w-6 h-6 rounded-full bg-slate-50 dark:bg-slate-800 flex items-center justify-center transition-transform" :class="open ? 'rotate-180' : ''">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </button>
                        <div x-show="open" x-collapse class="px-4 pb-4">
                            <div class="p-2 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-dark-border">
                                <p class="text-[9px] text-slate-500 font-medium leading-relaxed italic">Default terms will appear here...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR: CALCULATION & PAYMENT -->
                <div class="space-y-6">
                    <!-- Grand Totals Card -->
                    <div class="bg-navy rounded-2xl p-4 text-white relative overflow-hidden shadow-modal border border-white/5">
                        <div class="space-y-3 relative z-10">
                            <div class="flex justify-between items-center bg-white/5 p-2 rounded-xl border border-white/5">
                                <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Subtotal</span>
                                <span class="text-[11px] font-black tabular-nums" x-text="subtotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center px-2">
                                <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Other Charges</span>
                                <span class="text-[10px] font-bold tabular-nums" x-text="'{{ $currencySymbol }} ' + parseFloat(otherCharges || 0).toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between items-center px-2 text-rose-400">
                                <span class="text-[9px] font-black uppercase tracking-widest">Discount</span>
                                <span class="text-[10px] font-bold tabular-nums" x-text="'{{ $currencySymbol }} ' + totalDiscount.toFixed(2)"></span>
                            </div>
                            
                            <div class="pt-3 border-t border-white/10 mt-3">
                                <p class="text-[9px] font-black uppercase text-primary-400 tracking-widest mb-0.5">Grand Total</p>
                                <div class="flex items-baseline gap-1.5 text-primary-500">
                                    <span class="text-xl font-black tabular-nums" x-text="grandTotal.toFixed(2)"></span>
                                    <span class="text-[9px] font-bold opacity-60">{{ $currencySymbol }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Decorative glow -->
                        <div class="absolute -right-8 -bottom-8 w-16 h-16 bg-primary-600/20 rounded-full blur-2xl"></div>
                        <div class="absolute -left-6 -top-6 w-12 h-12 bg-emerald-600/10 rounded-full blur-xl"></div>
                    </div>

                    <!-- Payment Details Quick View -->
                    <!-- Payment Options -->
                    <div class="space-y-3">
                         <h3 class="text-[13px] font-black px-1">Payment Options</h3>
                         <div class="grid grid-cols-2 gap-2">
                             <button type="button" x-show="isEmiCustomer" @click="openEmiModal()" x-cloak class="py-3 bg-danger hover:bg-danger-hover text-white rounded-button text-[10px] font-black uppercase tracking-widest shadow-md shadow-danger/30 dark:shadow-none transition-all flex flex-col items-center justify-center gap-1">
                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                 EMI
                             </button>
                             <button type="button" @click="multipleModalOpen = true" class="py-3 bg-warning hover:bg-warning/90 text-white rounded-button text-[10px] font-black uppercase tracking-widest shadow-md shadow-warning/30 dark:shadow-none transition-all flex flex-col items-center justify-center gap-1">
                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                 Multiple
                             </button>
                             <button type="button" @click="openCashModal()" class="py-3 bg-success hover:bg-success/90 text-white rounded-button text-[10px] font-black uppercase tracking-widest shadow-md shadow-success/30 dark:shadow-none transition-all flex flex-col items-center justify-center gap-1">
                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                 Cash
                             </button>
                             <button type="button" @click="submitSale()" :disabled="submitting" class="py-3 bg-primary hover:bg-primary-hover text-white rounded-button text-[10px] font-black uppercase tracking-widest shadow-md shadow-primary/30 dark:shadow-none transition-all flex flex-col items-center justify-center gap-1 disabled:opacity-60 disabled:cursor-not-allowed">
                                 <svg x-show="!submitting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                 <svg x-show="submitting" class="w-5 h-5 animate-spin" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                 <span x-text="submitting ? 'Saving...' : 'Pay All'">Pay All</span>
                             </button>
                         </div>
                    </div>
                </div>
            </div>

        </div>

<!-- MODALS -->

<!-- MULTIPLE PAYMENT MODAL -->
<div x-show="multipleModalOpen" 
     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    
    <div class="bg-card dark:bg-dark-card w-full max-w-4xl rounded-[2rem] shadow-modal flex flex-col overflow-hidden border border-border dark:border-dark-border max-h-[95vh]"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-10">
         
        <!-- Modal Header -->
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Multiple Payments</h2>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Split payments across different methods</p>
            </div>
            <button @click="multipleModalOpen = false" class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors flex items-center justify-center shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto p-6 pt-0 custom-scrollbar">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Left Column: Details & Payments -->
                <div class="lg:col-span-8 space-y-6">
                    
                    <!-- Top Row: Advance & Coupon -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Advance Payment -->
                        <div class="bg-slate-50 dark:bg-slate-800/20 rounded-2xl p-4 border border-slate-100 dark:border-dark-border">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Advance Payment</span>
                                <span class="text-[12px] font-black tabular-nums text-slate-800 dark:text-white" x-text="'{{ $currencySymbol }}' + parseFloat(advanceAmount || 0).toFixed(2)"></span>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white dark:bg-dark-card rounded-xl border border-slate-200 dark:border-dark-border shadow-sm hover:border-primary-200 transition-all group">
                                <div class="w-6 h-6 rounded-lg border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center transition-all group-hover:border-primary-300" :class="{ 'bg-primary-500 border-primary-500 shadow-lg shadow-primary-200': advancePayment }">
                                    <input type="checkbox" x-model="advancePayment" class="hidden">
                                    <svg x-show="advancePayment" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-[11px] font-black uppercase tracking-widest text-slate-600 dark:text-gray-400">Adjust Advance Payment</span>
                            </label>
                            <!-- Advance amount input — shown only when the toggle is on -->
                            <div x-show="advancePayment" x-cloak class="mt-3 p-3 bg-primary-50 dark:bg-primary-500/10 rounded-xl border border-primary-100 dark:border-primary-500/20">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5">Advance Amount</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-black">{{ $currencySymbol }}</span>
                                    <input type="number" x-model="advanceAmount" min="0" :max="grandTotal" step="0.01"
                                           @input="advanceAmount = Math.min(Math.max(parseFloat(advanceAmount) || 0, 0), grandTotal)"
                                           class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-7 pr-3 text-[11px] font-black focus:ring-2 focus:ring-primary-500 outline-none transition-all">
                                </div>
                            </div>
                        </div>

                        <!-- Coupon Code -->
                        <div class="bg-slate-50 dark:bg-slate-800/20 rounded-2xl p-4 border border-slate-100 dark:border-dark-border">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1">Discount Coupon Code</label>
                            <div class="relative" x-show="!appliedCoupon">
                                <input type="text" x-model="couponCode" placeholder="Enter Code" @keydown.enter.prevent="applyCoupon()" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-3 pl-4 pr-12 text-[11px] font-bold focus:ring-2 focus:ring-primary-500 outline-none transition-all uppercase placeholder:normal-case shadow-sm">
                                <button type="button" @click="applyCoupon()" :disabled="couponLoading" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-all disabled:opacity-50 flex items-center justify-center">
                                    <svg x-show="!couponLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                    <svg x-show="couponLoading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </button>
                            </div>
                            <div x-show="appliedCoupon" class="flex items-center justify-between p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-xl" x-cloak>
                                <div>
                                    <span class="text-[11px] font-black text-emerald-700 dark:text-emerald-300 uppercase tracking-wide block" x-text="appliedCoupon ? appliedCoupon.code : ''"></span>
                                    <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400" x-text="appliedCoupon ? appliedCoupon.message : ''"></span>
                                </div>
                                <button type="button" @click="removeCoupon()" class="p-1.5 bg-rose-100 hover:bg-rose-200 text-rose-600 rounded-lg transition-colors" title="Remove Coupon">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="flex justify-between mt-2 px-1">
                                <span class="text-[10px] font-bold text-slate-400">Coupon Value</span>
                                <span class="text-[10px] font-black text-slate-800 dark:text-slate-300" x-text="'{{ $currencySymbol }}' + parseFloat(couponDiscount || 0).toFixed(2)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods Section Header -->
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary-500 animate-pulse"></span>
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 dark:text-white">Payment Methods</h3>
                        </div>
                        <button @click="addPaymentRow()" class="px-4 py-2 bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-100 transition-all flex items-center gap-2 border border-primary-100 dark:border-primary-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Row
                        </button>
                    </div>

                    <!-- Payment Rows -->
                    <div class="space-y-4">
                        <template x-for="(row, index) in paymentRows" :key="index">
                            <div class="relative p-5 bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm hover:shadow-md transition-shadow">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <!-- Amount -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Amount</label>
                                        <div class="relative group">
                                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-black transition-colors group-focus-within:text-primary-500">{{ $currencySymbol }}</span>
                                            <input type="number" x-model="row.amount" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2.5 pl-8 pr-3 text-sm font-black focus:ring-2 focus:ring-primary-500 outline-none transition-all">
                                        </div>
                                    </div>
                                    <!-- Method -->
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Method</label>
                                        <select x-model="row.type" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-black focus:ring-2 focus:ring-primary-500 outline-none transition-all appearance-none cursor-pointer uppercase">
                                            @foreach($paymentTypes as $ptype)
                                                <option value="{{ $ptype->payment_type }}">{{ $ptype->payment_type }}</option>
                                            @endforeach
                                         </select>
                                    </div>
                                    <!-- Account -->
                                    <div class="space-y-1.5">
                                         <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Account</label>
                                         <x-searchable-select :options="$accounts" labelKey="account_name" valueKey="id" placeholder="Select Account" model="row.account" />
                                    </div>
                                    <!-- Note -->
                                    <div class="space-y-1.5 relative">
                                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Note</label>
                                        <input type="text" x-model="row.note" placeholder="Optional" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2.5 px-4 text-[11px] font-bold focus:ring-2 focus:ring-primary-500 outline-none transition-all">
                                        
                                        <!-- Remove Button -->
                                        <button @click="removePaymentRow(index)" x-show="paymentRows.length > 1" class="absolute -right-2 -top-2 w-6 h-6 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Internal Note -->
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest ml-1">Internal Note</label>
                        <textarea x-model="paymentNote" rows="2" placeholder="Add a note for this transaction..." class="w-full bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-dark-border rounded-xl py-3 px-4 text-[11px] font-bold focus:ring-2 focus:ring-primary-500 outline-none transition-all resize-none shadow-sm"></textarea>
                    </div>
                </div>

                <!-- Right Column: Summary Sidebar -->
                <div class="lg:col-span-4">
                    <div class="bg-navy rounded-[2rem] overflow-hidden shadow-modal text-white h-full relative p-6 flex flex-col justify-between border border-white/5">
                        
                        <!-- Decorative Orbs -->
                        <div class="absolute top-0 right-0 w-48 h-48 bg-primary-600/10 rounded-full blur-[80px] -mr-24 -mt-24 pointer-events-none"></div>
                        <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-600/10 rounded-full blur-[80px] -ml-24 -mb-24 pointer-events-none"></div>

                        <div class="relative space-y-6">
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-6 border-b border-white/10 pb-5">Payment Summary</h3>
                            
                            <!-- Detailed Summary -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 italic">Total Items</span>
                                    <span class="text-[12px] font-black tabular-nums" x-text="totalItems.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Subtotal</span>
                                    <span class="font-black tabular-nums text-slate-300" x-text="'{{ $currencySymbol }}' + subtotal.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-rose-400">
                                    <span class="text-[10px] font-black uppercase tracking-widest opacity-80">Discount (-)</span>
                                    <span class="text-[12px] font-black tabular-nums" x-text="'{{ $currencySymbol }}' + (totalDiscount - (parseFloat(couponDiscount) || 0)).toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-emerald-400">
                                    <span class="text-[10px] font-black uppercase tracking-widest opacity-80">Coupon (-)</span>
                                    <span class="text-[12px] font-black tabular-nums" x-text="'{{ $currencySymbol }}' + parseFloat(couponDiscount || 0).toFixed(2)"></span>
                                </div>
                                
                                <div class="my-4 border-t border-dashed border-white/10"></div>
                                
                                <div class="flex justify-between items-end">
                                    <span class="text-[11px] font-black uppercase tracking-tight text-slate-300 mb-0.5">Net Payable</span>
                                    <div class="text-right">
                                        <span class="text-2xl font-black text-white tabular-nums tracking-tighter" x-text="'{{ $currencySymbol }}' + totalPayable.toFixed(2)"></span>
                                    </div>
                                </div>
                                
                                <!-- Floating Result Card -->
                                <div class="bg-white/5 backdrop-blur-md rounded-xl p-4 border border-white/10 space-y-3 mt-4 shadow-Inner">
                                    <div class="flex justify-between items-center text-blue-300">
                                        <span class="text-[9px] font-black uppercase tracking-widest">Total Paying</span>
                                        <span class="text-lg font-black tabular-nums" x-text="'{{ $currencySymbol }}' + totalPaying.toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-orange-300">
                                         <span class="text-[9px] font-black uppercase tracking-widest">Balance Due</span>
                                        <span class="text-base font-black tabular-nums" x-text="'{{ $currencySymbol }}' + balance.toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-emerald-400 border-t border-white/10 pt-3">
                                        <span class="text-[9px] font-black uppercase tracking-widest">Change Return</span>
                                        <span class="text-lg font-black tabular-nums" x-text="'{{ $currencySymbol }}' + changeReturn.toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative pt-8 flex items-center justify-center gap-3 opacity-30">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <span class="text-[9px] text-slate-400 font-black uppercase tracking-[0.2em]">Secure Payment Gateway</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer Buttons -->
        <div class="px-6 py-4 border-t border-slate-50 dark:border-dark-border flex items-center justify-between bg-slate-50/30 dark:bg-slate-800/20">
            <button @click="multipleModalOpen = false" class="px-6 py-3 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all shadow-sm">
                Close
            </button>
            <div class="flex gap-3">
                <button @click="submitSale(false)" :disabled="submitting" class="px-6 py-3 bg-pink-500 hover:bg-pink-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-pink-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent" x-cloak></span>
                    <span x-text="submitting ? 'Saving...' : 'Save'">Save</span>
                </button>
                <button @click="submitSale(true)" :disabled="submitting" class="px-8 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent" x-cloak></span>
                    <span x-text="submitting ? 'Saving...' : 'Save & Print'">Save & Print</span>
                </button>
            </div>
        </div>
    </div>
</div>


        <!-- CASH PAYMENT MODAL -->
        <div x-show="cashModalOpen" 
             class="fixed inset-0 z-50 flex items-center justify-center bg-dark-bg/60 backdrop-blur-md p-4"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <div class="bg-card dark:bg-dark-card w-full max-w-4xl rounded-[2.5rem] shadow-modal flex flex-col overflow-hidden border border-border dark:border-dark-border max-h-[90vh]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">
                 
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Cash Payment</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Single transaction cash payment</p>
                    </div>
                    <button @click="cashModalOpen = false" class="w-8 h-8 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- Left Column: Payment Inputs -->
                        <div class="lg:col-span-8 space-y-5">
                            
                            <!-- Top Card: Advance & Coupon -->
                            <div class="bg-slate-50 dark:bg-slate-800/30 rounded-2xl p-4 border border-slate-100 dark:border-dark-border">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Advance Payment</span>
                                            <span class="text-[11px] font-black tabular-nums text-slate-700 dark:text-white" x-text="'{{ $currencySymbol }}' + parseFloat(advanceAmount || 0).toFixed(2)">{{ $currencySymbol }}0.00</span>
                                        </div>
                                        <label class="flex items-center gap-3 cursor-pointer p-3 bg-white dark:bg-dark-card rounded-xl border border-slate-200 dark:border-dark-border shadow-sm hover:border-primary-200 transition-colors">
                                            <div class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 flex items-center justify-center text-white" :class="{ 'bg-primary-500 border-primary-500': advancePayment }">
                                                <input type="checkbox" x-model="advancePayment" class="hidden">
                                                <svg x-show="advancePayment" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:text-gray-400">Adjust Advance Payment</span>
                                        </label>
                                        <!-- Advance amount input — shown only when the toggle is on -->
                                        <div x-show="advancePayment" x-cloak class="mt-3 p-3 bg-primary-50 dark:bg-primary-500/10 rounded-xl border border-primary-100 dark:border-primary-500/20">
                                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5">Advance Amount</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-black">{{ $currencySymbol }}</span>
                                                <input type="number" x-model="advanceAmount" min="0" :max="grandTotal" step="0.01"
                                                       @input="advanceAmount = Math.min(Math.max(parseFloat(advanceAmount) || 0, 0), grandTotal)"
                                                       class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-7 pr-3 text-[11px] font-black focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-2">Applied Coupon</label>
                                        <!-- Read-only summary of the coupon applied in the footer (single entry point). -->
                                        <div x-show="!appliedCoupon" class="flex items-center justify-between px-1 py-2.5 bg-slate-50 dark:bg-slate-800/30 rounded-xl border border-slate-100 dark:border-dark-border">
                                            <span class="text-[9px] font-bold text-slate-400">None</span>
                                            <span class="text-[9px] font-black text-slate-500">{{ $currencySymbol }}0.00</span>
                                        </div>
                                        <div x-show="appliedCoupon" class="flex items-center justify-between px-1 py-2.5 bg-emerald-50 dark:bg-emerald-950/30 rounded-xl border border-emerald-200 dark:border-emerald-800/50">
                                            <span class="text-[9px] font-black text-emerald-700 dark:text-emerald-300 uppercase" x-text="appliedCoupon.code"></span>
                                            <span class="text-[9px] font-black text-emerald-600" x-text="'{{ $currencySymbol }}' + parseFloat(couponDiscount || 0).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Cash Payment Input -->
                            <div class="bg-white dark:bg-dark-card p-5 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm">
                                <h3 class="text-[11px] font-black uppercase text-slate-800 dark:text-white tracking-widest flex items-center gap-2 mb-4">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Payment Details
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5">Cash Amount</label>
                                        <div class="relative">
                                             <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-black">{{ $currencySymbol }}</span>
                                            <input type="number" x-model="cashAmount" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2.5 pl-7 pr-4 text-sm font-black focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                                        </div>
                                    </div>
                                    <div>
                                         <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5">Account</label>
                                         <x-searchable-select :options="$accounts" labelKey="account_name" valueKey="id" placeholder="Select Account" model="cashAccount" />
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-2">Payment Note</label>
                                    <textarea x-model="cashNote" rows="2" placeholder="Add a note for this transaction..." class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-2xl py-3 px-4 text-[11px] font-bold focus:ring-2 focus:ring-emerald-500 outline-none transition-all resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Summary -->
                        <div class="lg:col-span-4">
                            <div class="bg-navy rounded-[2rem] overflow-hidden shadow-modal text-white h-full relative p-6 flex flex-col justify-between">
                                
                                <!-- Decorative Elements -->
                                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/20 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 w-32 h-32 bg-indigo-500/20 rounded-full blur-3xl -ml-16 -mb-16 pointer-events-none"></div>

                                <div class="relative space-y-4">
                                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-6 border-b border-white/10 pb-4">Payment Summary</h3>
                                    
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Subtotal</span>
                                            <span class="text-sm font-black" x-text="'{{ $currencySymbol }}' + subtotal.toFixed(2)"></span>
                                        </div>
                                        
                                        <div class="my-4 border-t border-dashed border-white/20"></div>
                                        
                                        <div class="flex justify-between items-end">
                                            <span class="text-[11px] font-black uppercase tracking-widest text-slate-300">Net Payable</span>
                                            <span class="text-2xl font-black text-white" x-text="'{{ $currencySymbol }}' + totalPayable.toFixed(2)"></span>
                                        </div>
                                        
                                        <div class="bg-white/5 rounded-xl p-3 border border-white/10 space-y-2 mt-2">
                                            <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-300">Cash Paying</span>
                                                <span class="text-sm font-black text-primary-300" x-text="'{{ $currencySymbol }}' + (parseFloat(cashAmount) || 0).toFixed(2)"></span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                 <span class="text-[10px] font-bold uppercase tracking-wider text-orange-300">Balance Due</span>
                                                <span class="text-sm font-black text-orange-300" x-text="'{{ $currencySymbol }}' + cashBalance.toFixed(2)"></span>
                                            </div>
                                            <div class="flex justify-between items-center border-t border-white/10 pt-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Change Return</span>
                                                <span class="text-lg font-black text-emerald-300" x-text="'{{ $currencySymbol }}' + cashChangeReturn.toFixed(2)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="relative mt-8">
                                    <div class="text-[9px] text-slate-500 font-medium text-center mb-2">Secure Cash Transaction</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-5 border-t border-slate-50 dark:border-dark-border flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/10">
                    <button @click="cashModalOpen = false" class="px-6 py-3 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm">
                        Close
                    </button>
                    <button @click="submitSale(false)" :disabled="submitting" class="px-8 py-3 bg-pink-500 hover:bg-pink-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-pink-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent" x-cloak></span>
                        <span x-text="submitting ? 'Saving...' : 'Save'">Save</span>
                    </button>
                    <button @click="submitSale(true)" :disabled="submitting" class="px-8 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent" x-cloak></span>
                        <span x-text="submitting ? 'Saving...' : 'Save & Print'">Save & Print</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- EMI PAYMENT MODAL -->
        <div x-show="emiModalOpen" 
             class="fixed inset-0 z-50 flex items-center justify-center bg-dark-bg/60 backdrop-blur-md p-4"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <div class="bg-slate-50 dark:bg-dark-bg w-full max-w-3xl rounded-[2rem] shadow-2xl flex flex-col overflow-hidden border border-slate-100 dark:border-dark-border max-h-[90vh]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">
                 
                <!-- Modal Header -->
                <div class="px-6 py-4 flex items-center justify-between bg-white dark:bg-dark-card border-b border-slate-100 dark:border-dark-border">
                    <h2 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">EMI Details</h2>
                    <button @click="emiModalOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Left Column: Inputs (EMI DETAILS) -->
                        <div class="space-y-4">
                            
                            <!-- Customer Display (Readonly) -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Customer</label>
                                <div class="relative">
                                    <input type="text" :value="emiCustomerName || emiCustomer" readonly class="w-full bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-9 pr-4 text-xs font-black text-slate-600 dark:text-slate-300 cursor-not-allowed">
                                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Total Amount & Initial -->
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Total Amount</label>
                                     <div class="relative">
                                         <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold">{{ $currencySymbol }}</span>
                                         <input type="text" :value="emiTotalAmount.toFixed(2)" readonly class="w-full bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-6 pr-3 text-xs font-black text-slate-500 cursor-not-allowed">
                                     </div>
                                </div>
                                <div class="space-y-1.5">
                                     <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Initial Pay</label>
                                     <div class="relative">
                                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold">{{ $currencySymbol }}</span>
                                        <input type="number" x-model="emiInitialPay" placeholder="Amount..." class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-8 pr-8 text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all placeholder:text-slate-300">
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 text-teal-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        </div>
                                     </div>
                                </div>

                                <!-- Duration & Start Date -->
                                <div class="space-y-1.5">
                                     <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Duration</label>
                                     <div class="relative">
                                        <select x-model="emiDuration" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-3 text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-teal-500 outline-none transition-all appearance-none cursor-pointer">
                                            <option value="3">3 Months</option>
                                            <option value="6">6 Months</option>
                                            <option value="9">9 Months</option>
                                            <option value="12">12 Months</option>
                                            <option value="18">18 Months</option>
                                            <option value="24">24 Months</option>
                                        </select>
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                     <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Start Date</label>
                                     <div class="relative">
                                        <input type="date" x-model="emiStartDate" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-3 text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-teal-500 outline-none transition-all">
                                     </div>
                                </div>
                            </div>
                            
                            <!-- Processing Fee -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Processing Fee</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold">{{ $currencySymbol }}</span>
                                    <input type="number" x-model="emiProcessingFee" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-6 pr-10 text-xs font-black focus:ring-2 focus:ring-teal-500 outline-none">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-800 dark:text-white font-black text-xs">{{ $currencySymbol }}500</span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest pl-1">Notes</label>
                                <div class="relative">
                                    <textarea x-model="emiNotes" rows="2" placeholder="Enter any notes..." class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 pl-4 pr-9 text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all resize-none placeholder:text-slate-300"></textarea>
                                    <div class="absolute right-3 bottom-3 text-teal-500">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </div>
                                </div>
                            </div>

                             <!-- Footer Actions -->
                             <div class="flex items-center gap-3 pt-2">
                                <button @click="emiModalOpen = false" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-colors shadow-sm w-28">
                                    Cancel
                                </button>
                                <button @click="submitEmi()" :disabled="submitting" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-teal-200/50 flex-1 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent inline-block align-middle mr-1" x-cloak></span>
                                    <span x-text="submitting ? 'Saving...' : 'Create EMI'">Create EMI</span>
                                </button>
                             </div>

                        </div>

                        <!-- Right Column: EMI Summary (Dark Card) -->
                        <div class="bg-navy rounded-[1.5rem] p-5 text-white flex flex-col h-full relative overflow-hidden">
                            <h3 class="text-sm font-bold mb-4 border-b border-white/10 pb-3">EMI Summary</h3>
                            
                            <div class="space-y-3 mb-4">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Total Amount:</span>
                                    <span class="font-bold" x-text="'{{ $currencySymbol }}' + emiTotalAmount.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Initial Payment:</span>
                                    <span class="font-bold" x-text="'{{ $currencySymbol }}' + (parseFloat(emiInitialPay) || 0).toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Remaining:</span>
                                    <span class="font-bold" x-text="'{{ $currencySymbol }}' + emiRemaining.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Duration:</span>
                                    <span class="font-bold" x-text="emiDuration + ' Months'"></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Processing Fee:</span>
                                    <span class="font-bold" x-text="'{{ $currencySymbol }}' + (parseFloat(emiProcessingFee) || 0).toFixed(0)"></span>
                                </div>
                            </div>
                            
                            <div class="my-3 border-t border-dashed border-white/20"></div>

                            <div class="flex items-baseline justify-between mb-4">
                                <span class="text-sm font-bold text-white">Monthly EMI</span>
                                <div class="text-right">
                                    <span class="text-lg font-black text-teal-400" x-text="'{{ $currencySymbol }}' + emiMonthly.toFixed(0)"></span>
                                    <span class="text-[10px] text-slate-400 block">per month</span>
                                </div>
                            </div>

                            <!-- Schedule Table -->
                            <div class="bg-slate-800/50 rounded-xl overflow-hidden mb-4 flex-1 max-h-48 overflow-y-auto custom-scrollbar">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-800 text-slate-400 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">Date</th>
                                            <th class="px-3 py-2 font-medium text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 text-slate-300">
                                        <template x-for="row in emiSchedule" :key="row.month">
                                            <tr>
                                                <td class="px-3 py-2" x-text="new Date(row.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })"></td>
                                                <td class="px-3 py-2 text-right font-bold" x-text="'{{ $currencySymbol }}' + row.amount.toFixed(2)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="flex items-start gap-2 text-[9px] text-slate-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-teal-500 mt-1 shrink-0"></span>
                                <p>EMI charge is fixed per month and fetched from system settings.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    
        <!-- SERIAL SELECTION MODAL -->
        <div x-show="serialModalOpen" 
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <div class="bg-card dark:bg-dark-card rounded-3xl shadow-modal w-full max-w-xl flex flex-col overflow-hidden border border-border dark:border-dark-border max-h-[80vh]"
                 @click.away="serialModalOpen = false"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                 
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-50 dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/20">
                    <div>
                        <h2 class="text-lg font-black text-slate-700 dark:text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                            Select Serial Numbers
                        </h2>
                        <template x-if="currentSerialItem">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5" x-text="currentSerialItem.item_name"></p>
                        </template>
                    </div>
                    <button @click="serialModalOpen = false" class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                    <!-- Loading State -->
                    <div x-show="loading" class="flex flex-col items-center justify-center py-12 text-slate-400">
                        <svg class="animate-spin h-8 w-8 mb-3" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest">Loading Serials...</span>
                    </div>

                    <!-- No Results -->
                    <div x-show="!loading && availableSerials.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400 text-center">
                        <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center mb-4 border border-dashed border-slate-200 dark:border-dark-border">
                            <svg class="w-8 h-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"></path></svg>
                        </div>
                        <h3 class="text-[11px] font-black text-slate-500 uppercase tracking-widest">No serials available</h3>
                        <p class="text-[10px] text-slate-400 mt-1 font-medium">Add stock with serial numbers first.</p>
                    </div>

                    <!-- Serial Grid -->
                    <div x-show="!loading && availableSerials.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="s in availableSerials" :key="s.id">
                            <button @click="toggleSerial(s.id)" 
                                    :class="tempSelectedSerials.includes(s.id) ? 'bg-primary-600 text-white border-primary-600 shadow-md shadow-primary-200' : 'bg-slate-50 dark:bg-slate-800 border-slate-100 dark:border-dark-border text-slate-600 dark:text-slate-300 hover:border-primary-300'"
                                    class="p-3 rounded-2xl border text-left transition-all group overflow-hidden relative">
                                <div class="relative z-10">
                                    <p class="text-[10px] font-black uppercase tracking-wider truncate" x-text="s.serial_number"></p>
                                    <div class="flex items-center gap-1 mt-1 opacity-60">
                                        <div class="w-1.5 h-1.5 rounded-full" :class="tempSelectedSerials.includes(s.id) ? 'bg-white' : 'bg-emerald-500'"></div>
                                        <span class="text-[8px] font-bold uppercase tracking-widest" x-text="tempSelectedSerials.includes(s.id) ? 'Selected' : 'Available'"></span>
                                    </div>
                                </div>
                                <div x-show="tempSelectedSerials.includes(s.id)" class="absolute -right-2 -bottom-2 opacity-20">
                                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-50 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/20 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <div class="bg-primary-50 dark:bg-primary-500/10 px-3 py-1.5 rounded-xl border border-primary-100 dark:border-primary-500/20">
                             <span class="text-[10px] font-black text-primary-600 dark:text-primary-400 uppercase tracking-widest">Selected: <span x-text="tempSelectedSerials.length"></span></span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="serialModalOpen = false" class="px-5 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="confirmSerials()" class="px-8 py-2.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 shadow-lg shadow-primary-200/50 dark:shadow-none transition-all transform active:scale-95">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('saleComponent', () => ({
                saleInfo: {
                    warehouse: '{{ $warehouses->first()->id ?? '' }}',
                    customer: '',
                    salesCode: '{{ $nextSalesCode }}',
                    salesDate: new Date().toISOString().slice(0, 10),
                    referenceNo: '',
                    dueDate: ''
                },
                customersList: @json($customers),
                cart: [],
                searchQuery: '',
                searchResults: [],
                loading: false,
                otherCharges: 0,
                couponDiscount: 0,
                discountOnAll: 0,
                discountType: 'fixed',
                couponCode: '',
                appliedCoupon: null,
                couponLoading: false,
                couponId: null,
                customerCouponId: null,
                previousDue: 0,
                isEmiCustomer: false,
                // Double-submit guard — set true while a sale/EMI request is in flight.
                submitting: false,
                // Standalone sale note (distinct from payment-modal notes).
                saleNote: '',
                
                // Serial Modal State
                serialModalOpen: false,
                currentSerialItem: null,
                tempSelectedSerials: [],
                availableSerials: [],

                init() {
                    this.$watch('saleInfo.customer', value => {
                        this.fetchCustomerDue();
                        let customer = this.customersList.find(c => c.id == value);
                        this.isEmiCustomer = customer && customer.customer_type === 'emi';
                    });
                },

                fetchCustomerDue() {
                    if (!this.saleInfo.customer || this.saleInfo.customer === 'Walk-in customer') {
                        this.previousDue = 0;
                        return;
                    }
                    let url = '{{ route('sales.pos.customer.due', ['id' => ':id']) }}'.replace(':id', this.saleInfo.customer);
                    fetch(url)
                        .then(res => {
                            if (!res.ok) throw new Error('Server Error: ' + res.statusText);
                            return res.json();
                        })
                        .then(data => {
                            if (data.success) {
                                this.previousDue = data.due;
                            }
                        })
                        .catch(err => console.error('Error fetching due:', err));
                },

                fetchItems() {
                    if (this.searchQuery.length === 0) {
                        this.searchResults = [];
                        return;
                    }
                    this.loading = true;
                    // Using default categories and brands to fetch anything matching the search query for the selected warehouse
                    let url = '{{ route('sales.pos.search.items', [], false) }}?q=' + this.searchQuery + 
                              '&category_id=All Categories' + 
                              '&brand_id=All Brands' +
                              '&warehouse_id=' + this.saleInfo.warehouse;
                    fetch(url)
                        .then(res => {
                            if (!res.ok) throw new Error('Server Error: ' + res.statusText);
                            return res.json();
                        })
                        .then(data => {
                            this.searchResults = data;
                            this.loading = false;
                        })
                        .catch(err => {
                            console.error('Fetch error:', err);
                            this.loading = false;
                            this.searchResults = [];
                        });
                },

                handleEnterKey() {
                    if (this.searchResults.length === 1) {
                        this.addToCart(this.searchResults[0]);
                        this.searchQuery = '';
                        this.searchResults = [];
                    } else if (this.searchQuery.length > 0) {
                        this.fetchItems();
                    }
                },

                openSerialModal(item) {
                    this.currentSerialItem = item;
                    this.tempSelectedSerials = [];
                    this.loading = true;
                    this.serialModalOpen = true;

                    fetch('{{ route('sales.pos.search.serials', [], false) }}?item_id=' + item.id + '&warehouse_id=' + this.saleInfo.warehouse)
                        .then(res => {
                            if (!res.ok) throw new Error('Server Error: ' + res.statusText);
                            return res.json();
                        })
                        .then(data => {
                            this.availableSerials = data;
                            this.loading = false;
                            let existing = this.cart.find(i => i.id === item.id);
                            if (existing) {
                                this.tempSelectedSerials = [...existing.selectedSerials];
                            }
                        })
                        .catch(err => {
                            console.error('Failed to fetch serials:', err);
                            this.loading = false;
                        });
                },
                
                toggleSerial(serialId) {
                    let index = this.tempSelectedSerials.indexOf(serialId);
                    if (index > -1) {
                        this.tempSelectedSerials.splice(index, 1);
                    } else {
                        this.tempSelectedSerials.push(serialId);
                    }
                },
                
                confirmSerials() {
                    if (this.tempSelectedSerials.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Selection Required',
                            text: 'Please select at least one serial number!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }
                    let p = this.currentSerialItem;
                    let existing = this.cart.find(i => i.id === p.id);
                    if (existing) {
                        existing.qty = this.tempSelectedSerials.length;
                        existing.selectedSerials = [...this.tempSelectedSerials];
                        existing.serial = this.getSerialNames(this.tempSelectedSerials);
                    } else {
                        this.cart.push({
                            id: p.id,
                            name: p.item_name,
                            qty: this.tempSelectedSerials.length,
                            price: parseFloat(p.sales_price),
                            discount: 0,
                            tax: p.tax ? parseFloat(p.tax.tax) : 0,
                            taxAmount: 0,
                            total: parseFloat(p.sales_price) * this.tempSelectedSerials.length,
                            serial: this.getSerialNames(this.tempSelectedSerials),
                            selectedSerials: [...this.tempSelectedSerials]
                        });
                    }
                    this.calculateTotals();
                    this.serialModalOpen = false;
                },
                
                getSerialNames(ids) {
                    return this.availableSerials
                        .filter(s => ids.includes(s.id))
                        .map(s => s.serial_number)
                        .join(', ');
                },

                addToCart(p) {
                    this.searchQuery = '';
                    this.searchResults = [];
                    
                    if (p.is_serialized == 1) {
                        this.openSerialModal(p);
                        return;
                    }
                    let existing = this.cart.find(i => i.id === p.id);
                    if (existing) {
                        existing.qty++;
                    } else {
                        this.cart.push({
                            id: p.id,
                            name: p.item_name,
                            qty: 1,
                            price: parseFloat(p.sales_price),
                            discount: 0,
                            tax: p.tax ? parseFloat(p.tax.tax) : 0,
                            taxAmount: 0,
                            total: parseFloat(p.sales_price),
                            stock: p.stock
                        });
                    }
                    this.calculateTotals();
                    this.$nextTick(() => {
                        if (this.$refs.itemSearchInput) {
                            this.$refs.itemSearchInput.focus();
                        }
                    });
                },

                get totalItems() {
                    return this.cart.reduce((sum, item) => sum + item.qty, 0);
                },
                
                removeItem(id) {
                    this.cart = this.cart.filter(item => item.id !== id);
                    this.calculateTotals();
                },
                
                calculateTotals() {
                    this.cart.forEach(item => {
                        if (item.selectedSerials !== undefined) {
                            item.qty = item.selectedSerials.length;
                        }
                        let sub = item.qty * item.price;
                        let disc = (sub * item.discount) / 100;
                        let tax = ((sub - disc) * item.tax) / 100;
                        item.taxAmount = tax;
                        item.total = sub - disc + tax;
                    });

                    // Dynamically recalculate percentage coupon when subtotal changes
                    if (this.appliedCoupon) {
                        if (String(this.appliedCoupon.type).toLowerCase() === 'percentage') {
                            this.couponDiscount = Math.min(this.subtotal, Math.round((this.subtotal * (parseFloat(this.appliedCoupon.value) || 0)) / 100 * 100) / 100);
                        } else {
                            this.couponDiscount = Math.min(this.subtotal, parseFloat(this.appliedCoupon.value) || 0);
                        }
                    }
                },

                applyCoupon() {
                    if (!this.couponCode || this.couponCode.trim() === '') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Coupon Code Required',
                            text: 'Please enter a coupon code.',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }
                    if (this.subtotal <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cart Empty',
                            text: 'Please add items to the cart before applying a coupon.',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }

                    this.couponLoading = true;
                    let customerId = this.saleInfo.customer !== 'Walk-in customer' && this.saleInfo.customer !== '' ? this.saleInfo.customer : null;

                    fetch('{{ route('sales.coupon.validate', [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            code: this.couponCode,
                            subtotal: this.subtotal,
                            customer_id: customerId
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        this.couponLoading = false;
                        if (res.ok && data.success) {
                            this.appliedCoupon = data;
                            this.couponId = data.coupon_id;
                            this.customerCouponId = data.customer_coupon_id;
                            this.couponDiscount = data.discount_amount;
                            Swal.fire({
                                icon: 'success',
                                title: 'Coupon Applied!',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false,
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                        } else {
                            this.removeCoupon(false);
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid Coupon',
                                text: data.message || 'Coupon could not be applied.',
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                        }
                    })
                    .catch(err => {
                        this.couponLoading = false;
                        console.error('Coupon error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Network error or server error while validating coupon.',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                    });
                },

                removeCoupon(notify = true) {
                    this.appliedCoupon = null;
                    this.couponId = null;
                    this.customerCouponId = null;
                    this.couponDiscount = 0;
                    this.couponCode = '';
                    if (notify) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Coupon Removed',
                            text: 'Coupon discount has been removed.',
                            timer: 1200,
                            showConfirmButton: false,
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                    }
                },
                
                get subtotal() {
                    return this.cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
                },
                
                get totalDiscount() {
                    let itemDisco = this.cart.reduce((sum, item) => sum + ((item.qty * item.price) * item.discount / 100), 0);
                    let globalDisco = 0;
                    if (this.discountType === 'fixed') {
                        globalDisco = parseFloat(this.discountOnAll || 0);
                    } else {
                        globalDisco = (this.subtotal * parseFloat(this.discountOnAll || 0)) / 100;
                    }
                    let total = itemDisco + globalDisco + parseFloat(this.couponDiscount || 0);
                    return Math.min(this.subtotal, total);
                },
                
                get totalTax() {
                    return this.cart.reduce((sum, item) => sum + (parseFloat(item.taxAmount) || 0), 0);
                },
                
                get grandTotal() {
                    return Math.max(0, (this.subtotal + this.totalTax + parseFloat(this.otherCharges || 0)) - this.totalDiscount);
                },
                
                // PAYMENT STATE
                multipleModalOpen: false,
                paymentRows: [{ amount: '', type: 'CASH', account: '{{ $accounts->first()->id ?? '' }}', note: '' }],
                advancePayment: false,
                advanceAmount: 0,
                paymentNote: '',
                addPaymentRow() {
                    let remaining = this.balance;
                    if (remaining > 0) {
                        this.paymentRows.push({ amount: remaining.toFixed(2), type: 'CASH', account: '{{ $accounts->first()->id ?? '' }}', note: '' });
                    } else {
                        this.paymentRows.push({ amount: '', type: 'CASH', account: '{{ $accounts->first()->id ?? '' }}', note: '' });
                    }
                },
                removePaymentRow(index) {
                    this.paymentRows.splice(index, 1);
                },
                get totalPaying() {
                    return this.paymentRows.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
                },
                get totalPayable() {
                    return Math.max(0, this.grandTotal - (this.advancePayment ? this.advanceAmount : 0));
                },
                get balance() {
                    return Math.max(0, this.totalPayable - this.totalPaying);
                },
                get changeReturn() {
                    return Math.max(0, this.totalPaying - this.totalPayable);
                },
                openMultipleModal() {
                    this.multipleModalOpen = true;
                    this.paymentRows = [{ 
                        amount: this.totalPayable.toFixed(2), 
                        type: 'CASH', 
                        account: '{{ $accounts->first()->id ?? '' }}', 
                        note: '' 
                    }];
                },
                
                // CASH PAYMENT STATE
                cashModalOpen: false,
                cashAmount: 0,
                cashAccount: '{{ $accounts->first()->id ?? '' }}',
                cashNote: '',
                openCashModal() {
                    this.cashModalOpen = true;
                    this.cashAmount = this.totalPayable;
                    this.cashAccount = '{{ $accounts->first()->id ?? '' }}';
                    this.cashNote = '';
                },
                get cashChangeReturn() {
                    return Math.max(0, (parseFloat(this.cashAmount) || 0) - this.totalPayable);
                },
                get cashBalance() {
                     return Math.max(0, this.totalPayable - (parseFloat(this.cashAmount) || 0));
                },

                // EMI PAYMENT STATE
                emiModalOpen: false,
                emiTotalAmount: 0,
                emiInitialPay: 0,
                emiDuration: 6,
                emiProcessingFee: 500,
                get emiRemaining() {
                    return Math.max(0, this.emiTotalAmount - (parseFloat(this.emiInitialPay) || 0));
                },
                get emiMonthly() {
                    if (this.emiDuration <= 0) return 0;
                    return (this.emiRemaining + (parseFloat(this.emiProcessingFee) || 0)) / this.emiDuration;
                },
                emiCustomer: '',
                emiCustomerName: '',
                emiStartDate: new Date().toISOString().slice(0, 10),
                emiNotes: '',
                get emiSchedule() {
                    let schedule = [];
                    let monthly = this.emiMonthly;
                    let startDate = new Date(this.emiStartDate);
                    
                    for (let i = 1; i <= this.emiDuration; i++) { 
                        let date = new Date(startDate);
                        date.setMonth(startDate.getMonth() + i);
                        schedule.push({
                            month: i,
                            date: date.toISOString().slice(0, 10),
                            amount: monthly
                        });
                    }
                    return schedule;
                },
                openEmiModal() {
                    try {
                        if (!this.saleInfo.customer ||
                            this.saleInfo.customer === '' ||
                            this.saleInfo.customer === 'Walk-in customer') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Customer Required',
                                text: 'Please select a regular/EMI customer first!',
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                            return;
                        }

                        // EMI is only available to customers whose type is 'emi' — mirror the
                        // POS page's x-show="isEmiCustomer" pattern and guard on click too.
                        if (!this.isEmiCustomer) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'EMI Not Available',
                                text: 'EMI payment is only available for EMI-type customers.',
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                            return;
                        }

                        let customer = this.customersList.find(c => c.id == this.saleInfo.customer);

                        this.emiModalOpen = true;
                        this.emiCustomer = this.saleInfo.customer;
                        this.emiCustomerName = customer ? customer.customer_name : '';
                        this.emiTotalAmount = parseFloat(this.grandTotal) || 0;
                        this.emiInitialPay = 0;
                        this.emiDuration = 6;
                        this.emiProcessingFee = 500;
                        this.emiStartDate = new Date().toISOString().slice(0, 10);
                        this.emiNotes = '';
                    } catch (e) {
                        console.error('EMI Modal Error:', e);
                        showError('Could not open EMI modal. Please check the console.');
                    }
                },

                submitSale(shouldPrint = false, paymentType = null) {
                    if (this.submitting) {
                        return;
                    }
                    if (this.cart.length === 0) {
                        Swal.fire({
                            icon: 'warning', title: 'Cart Empty', text: 'Please add some items to the cart first!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }
                    
                    let payload = {
                        customer_id: this.saleInfo.customer !== 'Walk-in customer' && this.saleInfo.customer !== '' ? this.saleInfo.customer : null,
                        warehouse_id: this.saleInfo.warehouse,
                        cart: this.cart,
                        subtotal: this.subtotal,
                        grand_total: this.totalPayable,
                        account_id: '{{ $accounts->first()->id ?? '' }}',
                        sales_date: this.saleInfo.salesDate,
                        reference_no: this.saleInfo.referenceNo,
                        due_date: this.saleInfo.dueDate,
                        other_charges: parseFloat(this.otherCharges || 0),
                        discount_on_all: parseFloat(this.discountOnAll || 0),
                        discount_type: this.discountType,
                        coupon_id: this.couponId,
                        customer_coupon_id: this.customerCouponId,
                        coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                        coupon_amt: parseFloat(this.couponDiscount || 0),
                        sales_note: this.saleNote || this.paymentNote || this.cashNote,
                        advance_amount: this.advancePayment ? (parseFloat(this.advanceAmount) || 0) : 0,
                        is_pos: false,
                    };

                    if (paymentType === 'due') {
                        payload.paid_amount = 0;
                        payload.payment_type = 'Cash'; // Default to cash for the record even if unpaid
                    } else if (this.cashModalOpen) {
                        payload.paid_amount = parseFloat(this.cashAmount) || 0;
                        payload.payment_type = 'Cash';
                        payload.account_id = this.cashAccount || payload.account_id;
                        payload.payment_note = this.cashNote;
                    } else if (this.multipleModalOpen) {
                        payload.payments = this.paymentRows.map(r => ({
                            amount: parseFloat(r.amount) || 0,
                            type: r.type,
                            account: r.account,
                            note: r.note
                        }));
                        payload.paid_amount = this.totalPaying;
                    } else {
                        // Fallback
                        payload.paid_amount = this.totalPayable;
                        payload.payment_type = 'Cash';
                    }

                    this.submitting = true;
                    Swal.fire({
                        title: 'Confirm Sale', text: "Are you sure you want to complete this sale?", icon: 'question', showCancelButton: true, confirmButtonColor: '#2563eb', cancelButtonColor: '#94a3b8', confirmButtonText: 'Yes, Confirm!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('{{ route('sales.store', [], false) }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify(payload)
                            })
                            .then(res => res.json())
                            .then(data => {
                                this.submitting = false;
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success', title: 'Sale Completed!', text: 'Redirecting...', timer: 1500, showConfirmButton: false, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    }).then(() => {
                                        let url = '{{ route('sales.invoice', ['id' => ':id']) }}'.replace(':id', data.sale_id);
                                        if (shouldPrint) {
                                            url += '?print=true';
                                        }
                                        window.location.href = url;
                                    });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Sale Failed', text: data.message, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                                }
                            })
                            .catch(err => {
                                this.submitting = false;
                                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Something went wrong!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                            });
                        } else {
                            this.submitting = false;
                        }
                    });
                },

                submitEmi() {
                    if (this.submitting) {
                        return;
                    }
                    if (this.cart.length === 0) {
                        Swal.fire({ icon: 'warning', title: 'Cart Empty', text: 'Please add some items to the cart first!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                        return;
                    }
                    if (!this.emiCustomer) {
                        Swal.fire({ icon: 'warning', title: 'Customer Required', text: 'Please select a customer first!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                        return;
                    }

                    this.submitting = true;
                    Swal.fire({
                        title: 'Confirm EMI Sale', text: "Are you sure you want to complete this EMI sale?", icon: 'question', showCancelButton: true, confirmButtonColor: '#0d9488', cancelButtonColor: '#94a3b8', confirmButtonText: 'Yes, Confirm!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('{{ route('sales.emi', [], false) }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({
                                    customer_id: this.emiCustomer,
                                    warehouse_id: this.saleInfo.warehouse,
                                    cart: this.cart,
                                    subtotal: this.subtotal,
                                    grand_total: this.totalPayable,
                                    advance_amount: this.advancePayment ? (parseFloat(this.advanceAmount) || 0) : 0,
                                    initial_pay: this.emiInitialPay,
                                    duration: this.emiDuration,
                                    processing_fee: this.emiProcessingFee,
                                    start_date: this.emiStartDate,
                                    notes: this.emiNotes,
                                    sales_note: this.saleNote,
                                    is_pos: false
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                this.submitting = false;
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success', title: 'EMI Sale Completed!', text: 'Redirecting to EMI details...', timer: 1500, showConfirmButton: false, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    }).then(() => {
                                        window.location.href = '{{ route('sales.emi.show', ['id' => ':id']) }}'.replace(':id', data.emi_sale_id);
                                    });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'EMI Sale Failed', text: data.message, background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                                }
                            })
                            .catch(err => {
                                this.submitting = false;
                                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Something went wrong!', background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff', color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b' });
                            });
                        } else {
                            this.submitting = false;
                        }
                    });
                }
            }));
        });
    </script>
</x-app-layout>