<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if(isset($holdData))
<script>window.holdData = @json($holdData);</script>
@endif
@if(isset($saleData))
<script>window.saleData = @json($saleData);</script>
@endif

<x-app-layout title="POS">
    <div id="pos-screen" x-ref="posScreen" class="pos-screen p-1.5 md:p-2 pb-10 lg:flex lg:flex-col lg:overflow-y-auto lg:h-[calc(100dvh-6.5rem)] lg:pb-0" x-data="posComponent()">
        
        @if(!($hasOpenCashDrawer ?? false))
            <div x-data="{ dismissed: false }" x-show="!dismissed" class="mb-2 p-3 px-4 bg-warning-light dark:bg-warning/10 border border-warning/20 rounded-xl flex items-center justify-between gap-3 text-xs text-warning shadow-sm shrink-0">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-warning dark:text-warning shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>
                        <strong>Cash Drawer Notice:</strong> No cash drawer has been opened for today. You can still process sales normally, but remember to <a href="{{ route('accounts.cash-reconciliation.open-form') }}" class="font-bold underline hover:opacity-80">Open Cash Drawer</a> to track starting float & closing balances.
                    </span>
                </div>
                <button type="button" @click="dismissed = true" class="text-warning hover:text-danger dark:text-warning dark:hover:text-danger-light p-1.5 rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 flex-1 min-h-0">
            
            <!-- LEFT SECTION: CART & CHECKOUT -->
            <div class="lg:col-span-9 flex flex-col gap-2 min-h-0">
                
                <!-- TOP SELECTION BAR -->
                <div class="card p-2.5 md:p-3 rounded-xl shadow-card shrink-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest pl-2">Warehouse</label>
                            <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="selectedWarehouse" change="fetchItems()" inputClass="!py-1.5" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest pl-2">Customer</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="text" 
                                           x-model="customerSearch" 
                                           @click="if(customerSearch === 'Walk-in Customer') customerSearch = ''; customerDropdownOpen = true"
                                           @input="customerDropdownOpen = true; this.selectedCustomer = ''"
                                           @click.away="customerDropdownOpen = false; if(!selectedCustomer && customerSearch === '') customerSearch = 'Walk-in Customer'"
                                           @keydown.enter.prevent="if(customerDropdownOpen && filteredCustomers().length > 0) selectCustomer(filteredCustomers()[0])"
                                           placeholder="Search customer..."
                                           class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1.5 px-4 text-[11px] font-bold focus:ring-1 focus:ring-primary-500 transition-all outline-none">
                                    
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>

                                    <!-- Dropdown -->
                                    <div x-show="customerDropdownOpen" 
                                         x-cloak
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         class="absolute z-50 w-full mt-1 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl shadow-xl max-h-40 overflow-y-auto custom-scrollbar">
                                        <div class="p-1">
                                            <div @click="selectCustomer(null)" 
                                                 class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer rounded-lg text-[10px] font-bold text-slate-400 italic transition-colors border-b border-slate-50 dark:border-dark-border mb-1">
                                                Walk-in Customer
                                            </div>
                                            <template x-for="customer in filteredCustomers()" :key="customer.id">
                                                <div @click="selectCustomer(customer)" 
                                                     class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer rounded-lg text-[11px] font-bold text-slate-600 dark:text-slate-300 transition-colors">
                                                    <div class="flex flex-col">
                                                        <span x-text="customer.customer_name" class="truncate"></span>
                                                        <span class="text-[9px] text-slate-400" x-text="customer.mobile"></span>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="filteredCustomers().length === 0" class="px-4 py-2 text-[10px] text-slate-400 italic">No customers found</div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" @click="customerModalOpen = true" class="p-2 bg-primary-50 dark:bg-primary-500/10 text-primary-600 rounded-xl hover:bg-primary-100 transition-colors border border-primary-100 dark:border-primary-500/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ITEM SEARCH & TABLE -->
                <div class="card rounded-xl overflow-visible flex-1 min-h-0 flex flex-col shadow-card">
                    <div class="p-2.5 border-b border-border-light dark:border-dark-border shrink-0">
                        <div class="flex gap-2">
                            <div class="relative flex-1 group" @click.away="searchResults = []">
                                <input type="text" x-model="itemSearchQuery" x-ref="itemSearchInput"
                                       @input.debounce.300ms="fetchItems()" 
                                       @keydown.enter.prevent="handleEnterKey()"
                                       placeholder="Item name/Barcode/Itemcode" 
                                       class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1.5 pl-10 pr-4 text-[11px] font-bold focus:ring-1 focus:ring-primary-500 transition-all shadow-inner">
                                <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                
                                <!-- SEARCH RESULTS DROPDOWN -->
                                <div x-show="itemSearchQuery.length > 0 && searchResults.length > 0 && !serialModalOpen" 
                                     class="absolute z-40 w-full mt-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">
                                    <template x-for="p in searchResults" :key="p.id">
                                        <div @click="addToCart(p)" class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors border-b border-slate-50 dark:border-dark-border last:border-none flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 flex-1">
                                                <!-- Thumbnail in Search -->
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
                                                <p class="text-[11px] font-black text-primary-600 tabular-nums">{{ $currencySymbol }}<span x-text="(parseFloat(p.sales_price) || 0).toFixed(2)"></span></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <button type="button" @click="toggleFullscreen()" :title="isFullscreen ? 'Exit Fullscreen (Esc)' : 'Enter Fullscreen'" class="p-3.5 bg-slate-50 dark:bg-slate-800 border border-border dark:border-dark-border rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-all flex items-center justify-center shrink-0 text-text-muted" x-cloak>
                                <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V6a2 2 0 012-2h2M16 4h2a2 2 0 012 2v2M20 16v2a2 2 0 01-2 2h-2M8 20H6a2 2 0 01-2-2v-2"></path></svg>
                                <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3v3a2 2 0 01-2 2H3m18 0h-3a2 2 0 01-2-2V3m0 18v-3a2 2 0 012-2h3M3 16h3a2 2 0 012 2v3"></path></svg>
                            </button>
                            <a href="{{ route('items.add') }}" class="p-3.5 bg-primary text-white dark:bg-primary/15 dark:text-primary-300 rounded-xl hover:bg-primary-hover dark:hover:bg-primary/25 transition-all border border-primary/20 shadow-sm flex items-center justify-center shrink-0" title="Add New Item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                            </a>
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto flex-1 min-h-[150px] custom-scrollbar border-b border-border-light dark:border-dark-border relative">
                        <table class="w-full text-left" x-show="cart.length > 0">
                            <thead class="bg-background dark:bg-slate-800/50 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Item</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Price ({{ $currencySymbol }})</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Qty</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Disc ({{ $currencySymbol }})</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Total ({{ $currencySymbol }})</th>
                                    <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                <template x-for="item in cart" :key="item.id">
                                    <tr class="hover:bg-background/50 dark:hover:bg-slate-800/50 transition-colors group">
                                        <td class="px-4 py-2">
                                            <p class="text-[11px] font-black text-primary-600 dark:text-primary-400" x-text="item.name"></p>
                                            <!-- A2: visible flag on serialized lines that must be re-selected -->
                                            <span x-show="item.needsSerialSelection" x-cloak class="inline-flex items-center gap-1 mt-0.5 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest">
                                                Select Serial Numbers
                                            </span>
                                            <div class="flex gap-2 mt-0.5">
                                                <span class="text-[8px] font-bold text-slate-400 uppercase">SN: <span x-text="item.serial" class="text-slate-500 font-black italic"></span></span>
                                                <span class="text-[8px] font-bold text-slate-400 uppercase">Stock: <span x-text="item.stock" class="text-emerald-500 font-black"></span></span>
                                            </div>
                                        </td>
                                         <td class="px-4 py-2">
                                            <p class="text-[11px] font-black text-slate-700 dark:text-white" x-text="'{{ $currencySymbol }}' + item.price"></p>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                <template x-if="item.selectedSerials && item.selectedSerials.length > 0">
                                                    <button @click="openSerialModal(item)" class="min-h-11 px-3 py-2.5 bg-primary-50 dark:bg-primary/15 text-primary dark:text-primary-300 rounded-lg text-[10px] font-black uppercase tracking-widest border border-primary/20 dark:border-primary/30 hover:bg-primary-100 transition-all flex items-center gap-1">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                        <span x-text="item.qty"></span> SN
                                                    </button>
                                                </template>
                                                <template x-if="!item.selectedSerials || item.selectedSerials.length === 0">
                                                    <div class="flex items-center gap-2">
                                                        <!-- A2: serialized lines awaiting re-selection show a dedicated
                                                             "Select Serials" button (qty is locked to serial count) -->
                                                        <button x-show="item.needsSerialSelection" @click="openSerialModal(item)" x-cloak class="min-h-11 px-3 py-2.5 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 rounded-lg text-[10px] font-black uppercase tracking-widest border border-amber-200 dark:border-amber-500/30 hover:bg-amber-100 transition-all">
                                                            Select Serials
                                                        </button>
                                                        <button x-show="!item.needsSerialSelection" @click="item.qty > 1 ? item.qty-- : null" class="w-11 h-11 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-text-secondary hover:bg-primary hover:text-white transition-all text-sm">-</button>
                                                        <span x-show="!item.needsSerialSelection" class="text-[11px] font-black w-7 text-center" x-text="item.qty"></span>
                                                        <button x-show="!item.needsSerialSelection" @click="item.qty++" class="w-11 h-11 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-text-secondary hover:bg-primary hover:text-white transition-all text-sm">+</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-1">
                                                <span class="text-[9px] font-black text-slate-400 shrink-0">{{ $currencySymbol }}</span>
                                                <input type="number" x-model="item.discount" min="0" step="0.01"
                                                       @input="item.discount = Math.min(Math.max(parseFloat(item.discount) || 0, 0), item.price * item.qty)"
                                                       placeholder="0"
                                                       class="w-16 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-lg py-1 px-1.5 text-[10px] font-black text-center focus:ring-1 focus:ring-primary-500 outline-none transition-all">
                                            </div>
                                        </td>
                                         <td class="px-4 py-2">
                                            <p class="text-[11px] font-black text-slate-700 dark:text-white" x-text="'{{ $currencySymbol }}' + (item.price * item.qty - Math.min(parseFloat(item.discount || 0), item.price * item.qty)).toFixed(2)"></p>
                                            <template x-if="parseFloat(item.discount || 0) > 0">
                                                <p class="text-[8px] font-bold text-danger" x-text="'-' + '{{ $currencySymbol }}' + Math.min(parseFloat(item.discount || 0), item.price * item.qty).toFixed(2)"></p>
                                            </template>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button @click="removeItem(item.id)" class="w-11 h-11 flex items-center justify-center text-text-muted hover:text-danger hover:bg-danger-light dark:hover:bg-danger/10 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        
                        <!-- EMPTY STATE (Centered in fixed height) -->
                        <div x-show="cart.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-center p-6">
                            <div class="w-16 h-16 bg-background dark:bg-slate-800 rounded-full flex items-center justify-center mb-4 text-slate-200 dark:text-slate-700">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                            <h3 class="text-[11px] font-black text-text-muted uppercase tracking-widest">Cart is empty</h3>
                            <p class="text-[10px] text-text-muted mt-1 font-medium">Select products to start selling</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT SECTION: SIDE PANEL -->
            <div class="lg:col-span-3 flex flex-col gap-2 min-h-0 lg:overflow-y-auto custom-scrollbar">

                <!-- CUSTOMER SNAPSHOT (compact bar) -->
                <div class="card px-2.5 py-2 rounded-xl shadow-card shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-primary-light dark:bg-primary/15 text-primary dark:text-primary-300 flex items-center justify-center text-[9px] font-black shrink-0 uppercase">
                            <template x-if="selectedCustomer && customerSnapshotName">
                                <span x-text="customerInitials"></span>
                            </template>
                            <template x-if="!selectedCustomer || !customerSnapshotName">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </template>
                        </div>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="text-[10px] font-black text-text-primary dark:text-dark-text truncate" x-text="customerSnapshotName || 'Walk-in Customer'"></span>
                            <span class="text-[8px] font-bold text-text-muted truncate" x-text="customerSnapshotMobile || 'No mobile'"></span>
                        </div>
                        <template x-if="selectedCustomer && customerSnapshotDue > 0">
                            <span class="bg-danger-light text-danger px-2 py-0.5 rounded-full text-[9px] font-black tabular-nums shrink-0 inline-flex items-center gap-0.5">Due <x-money value="customerSnapshotDue" /></span>
                        </template>
                        <template x-if="!selectedCustomer || customerSnapshotDue <= 0">
                            <span class="text-[9px] font-black text-emerald-500 uppercase tracking-wider shrink-0">No due</span>
                        </template>
                    </div>
                </div>

                <!-- DISCOUNT / COUPON (Warehouse-field styling) -->
                <div class="card px-2.5 py-2 rounded-xl shadow-card shrink-0 space-y-1">
                    <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest pl-2">Discount & Coupon</label>
                    <div x-show="!appliedCoupon" class="flex gap-1.5">
                        <div class="relative flex-1 min-w-0">
                            <span x-show="discountType === 'fixed'" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-[10px]" x-cloak>{{ $currencySymbol }}</span>
                            <input type="number" x-model="discountOnAll" :class="discountType === 'fixed' ? 'pl-7' : 'px-3'" placeholder="0"
                                   :disabled="hasItemDiscount"
                                   class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1.5 pr-3 text-[11px] font-bold focus:ring-1 focus:ring-primary-500 transition-all outline-none disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-800/30">
                        </div>
                        <select x-model="discountType" :disabled="hasItemDiscount" class="bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1.5 px-2.5 text-[11px] font-black uppercase tracking-widest outline-none text-slate-500 dark:text-slate-400 cursor-pointer shrink-0 min-w-[52px] disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-slate-100 dark:disabled:bg-slate-800/30">
                            <option value="fixed">{{ $currencySymbol }}</option>
                            <option value="percent">%</option>
                        </select>
                    </div>
                    <div x-show="!appliedCoupon" class="flex gap-1.5">
                        <input type="text" x-model="couponCode" placeholder="Enter Code" @keydown.enter.prevent="applyCoupon()"
                               class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-1.5 px-3 text-[11px] font-bold outline-none uppercase flex-1 min-w-0 transition-all">
                        <button type="button" @click="applyCoupon()" :disabled="couponLoading" class="px-3 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-[9px] font-black uppercase tracking-wider transition-all disabled:opacity-50 flex items-center justify-center shrink-0">
                            <span x-show="!couponLoading">Apply</span>
                            <span x-show="couponLoading" class="animate-pulse">...</span>
                        </button>
                    </div>
                    <div x-show="appliedCoupon" class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-xl py-1.5 px-3 text-[10px]" x-cloak>
                        <div class="flex flex-col truncate">
                            <span class="font-black text-emerald-700 dark:text-emerald-300" x-text="appliedCoupon ? appliedCoupon.code : ''"></span>
                            <span class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-0.5">Saved <x-money value="parseFloat(couponAmount) || 0" /></span>
                        </div>
                        <button type="button" @click="removeCoupon()" class="text-rose-500 hover:text-rose-700 p-0.5 rounded-lg ml-1 shrink-0" title="Remove Coupon">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- TOTALS (compact two-line block) -->
                <div class="card px-2.5 py-2 rounded-xl shadow-card shrink-0">
                    <div class="flex items-center justify-between text-[9px] font-bold text-text-muted">
                        <span>
                            <span x-text="'Qty ' + totalQty"></span>
                            <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                            <span class="inline-flex items-center gap-0.5">Subtotal <x-money value="subtotal" /></span>
                            <template x-if="totalDiscount > 0">
                                <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                            </template>
                            <span x-show="totalDiscount > 0" class="text-danger inline-flex items-center gap-0.5">Disc −<x-money value="totalDiscount" /></span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-border-light dark:border-dark-border">
                        <span class="text-[9px] font-black uppercase text-primary tracking-widest">Grand total</span>
                        <x-money value="totalPayable" class="text-lg font-black tabular-nums text-text-primary dark:text-dark-text" />
                    </div>
                </div>

                <!-- ACTION BUTTONS (compact stack) -->
                <div class="space-y-1.5 shrink-0">
                    <button @click="submitSale()" :disabled="submitting" class="w-full h-[34px] btn-primary text-[10px] font-black uppercase tracking-widest shadow-md shadow-primary/30 dark:shadow-none inline-flex items-center justify-center gap-1.5 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="!submitting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <svg x-show="submitting" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="submitting ? 'Saving...' : 'Pay All'">Pay All</span>
                    </button>
                    <div class="grid gap-1.5" :class="isEmiCustomer ? 'grid-cols-3' : 'grid-cols-2'">
                        <button @click="openCashModal()" class="h-[30px] bg-success text-white rounded-lg text-[9px] font-black uppercase tracking-widest shadow-md shadow-success/30 dark:shadow-none transition-all flex items-center justify-center gap-1 hover:bg-success/90">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Cash
                        </button>
                        <button @click="openMultipleModal()" class="h-[30px] bg-warning text-white rounded-lg text-[9px] font-black uppercase tracking-widest shadow-md shadow-warning/30 dark:shadow-none transition-all flex items-center justify-center gap-1 hover:bg-warning/90">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Multiple
                        </button>
                        <button x-show="isEmiCustomer" @click="openEmiModal()" class="h-[30px] bg-danger text-white rounded-lg text-[9px] font-black uppercase tracking-widest shadow-md shadow-danger/30 dark:shadow-none transition-all flex items-center justify-center gap-1 hover:bg-danger-hover" x-cloak>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            EMI
                        </button>
                    </div>
                    <button @click="holdModalOpen = true" class="w-full h-[30px] bg-card dark:bg-dark-card text-text-primary dark:text-dark-text border border-border dark:border-dark-border rounded-lg text-[9px] font-black uppercase tracking-widest shadow-sm dark:shadow-none transition-all flex items-center justify-center gap-1 hover:bg-background dark:hover:bg-slate-800">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9l5 5-5 5"></path></svg>
                        Hold
                    </button>
                </div>

                <!-- HELD ORDERS (5 + pagination) -->
                <div class="card px-2.5 py-2 rounded-xl shadow-card shrink-0">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9l5 5-5 5"></path></svg>
                            <span class="text-[8px] font-black uppercase text-text-muted tracking-widest">Held orders</span>
                        </div>
                        <span class="bg-warning-light text-warning px-1.5 py-0.5 rounded-full text-[8px] font-black tabular-nums">{{ $heldOrdersCount }}</span>
                    </div>

                    <template x-if="heldOrders.length > 0">
                        <div>
                            <div class="space-y-1">
                                <template x-for="hold in paginatedHeldOrders" :key="hold.id">
                                    <a :href="hold.resume_url" class="w-full flex items-center justify-between gap-2 bg-background dark:bg-slate-800/50 hover:bg-primary-light dark:hover:bg-primary/10 border border-border-light dark:border-dark-border rounded-lg px-2 py-1.5 transition-all group">
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-[9px] font-black text-text-primary dark:text-dark-text truncate" x-text="hold.reference_no"></span>
                                            <span class="text-[7px] font-bold text-text-muted uppercase tracking-wider truncate" x-text="hold.customer_name + ' · ' + hold.item_count + ' items'"></span>
                                        </div>
                                        <x-money value="parseFloat(hold.grand_total) || 0" intercept class="text-[10px] font-black tabular-nums text-primary dark:text-primary-300 shrink-0" />
                                    </a>
                                </template>
                            </div>
                            <div x-show="heldOrdersTotalPages > 1" class="flex items-center justify-center gap-2 mt-2">
                                <button type="button" @click="heldOrdersPage = Math.max(1, heldOrdersPage - 1)" :disabled="heldOrdersPage <= 1" class="w-6 h-6 rounded-md bg-background dark:bg-slate-800 border border-border-light dark:border-dark-border flex items-center justify-center text-text-muted hover:text-primary transition-colors disabled:opacity-40">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                                </button>
                                <span class="text-[9px] font-black text-text-muted tabular-nums" x-text="heldOrdersPage + ' / ' + heldOrdersTotalPages"></span>
                                <button type="button" @click="heldOrdersPage = Math.min(heldOrdersTotalPages, heldOrdersPage + 1)" :disabled="heldOrdersPage >= heldOrdersTotalPages" class="w-6 h-6 rounded-md bg-background dark:bg-slate-800 border border-border-light dark:border-dark-border flex items-center justify-center text-text-muted hover:text-primary transition-colors disabled:opacity-40">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                            <a href="{{ route('sales.hold.list') }}" class="mt-1.5 block text-center text-[8px] font-black uppercase tracking-widest text-primary dark:text-primary-300 hover:text-primary-hover transition-colors">
                                View all held orders
                            </a>
                        </div>
                    </template>
                    <template x-if="heldOrders.length === 0">
                        <div class="py-3 text-center">
                            <p class="text-[9px] font-bold text-text-muted">No held orders</p>
                        </div>
                    </template>
                </div>
            </div>
            
        </div>
        

        <!-- HOLD INVOICE MODAL -->
        <x-modal name="pos-hold-invoice" state="holdModalOpen" maxWidth="md" focusable>
            
            <div class="p-6">
                 
                <div class="text-center">
                    <div class="w-14 h-14 bg-warning-light dark:bg-warning/15 rounded-full flex items-center justify-center mx-auto mb-3 border-4 border-warning/20">
                        <span class="text-2xl font-black text-warning">!</span>
                    </div>
                    
                    <h2 class="text-lg font-black text-text-primary dark:text-dark-text mb-2">Hold Invoice ?</h2>
                    <!-- A7: holding is now explicit — a duplicate reference is rejected,
                         not a silent replace. The server returns a clear error if the
                         reference already exists. -->
                    <p class="text-[11px] font-bold text-text-secondary dark:text-slate-400 mb-5 uppercase tracking-wide">Use a unique reference number. A duplicate reference will be rejected.</p>
                    
                    <input type="text" x-model="holdReference" placeholder="Please Enter Reference Number!"
                           class="input-base text-center placeholder:text-xs mb-5">
                    
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="holdModalOpen = false" class="btn-ghost">
                            Cancel
                        </button>
                        <button type="button" @click="submitHold()" :disabled="submitting" class="btn-danger">
                            <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent inline-block" x-cloak></span>
                            <span x-text="submitting ? 'Holding...' : 'OK'">OK</span>
                        </button>
                    </div>
                </div>
            </div>
        </x-modal>


        <!-- MULTIPLE PAYMENT MODAL -->
        <x-modal name="pos-multiple-payment" state="multipleModalOpen" maxWidth="4xl" focusable>
            <div class="flex flex-col max-h-[85vh]">

                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-border dark:border-dark-border flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Multiple Payments</h2>
                        <p class="text-[10px] font-bold text-text-muted uppercase tracking-widest mt-0.5">Split payments across different methods</p>
                    </div>
                    <button type="button" @click="multipleModalOpen = false" aria-label="Close"
                            class="w-9 h-9 rounded-button bg-background dark:bg-slate-800 text-text-muted hover:bg-danger-light hover:text-danger transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                        
                        <!-- Left Column: Payment Inputs -->
                        <div class="lg:col-span-7 space-y-5">
                            
                            <!-- Top Card: Advance & Coupon -->
                            <div class="bg-background dark:bg-slate-800/30 rounded-card p-4 border border-border dark:border-dark-border">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Advance Payment</span>
                                            <span class="text-[11px] font-black tabular-nums text-slate-700 dark:text-white" x-text="'{{ $currencySymbol }}' + (parseFloat(advanceAmount) || 0).toFixed(2)">0.00</span>
                                        </div>
                                        <label class="flex items-center gap-3 cursor-pointer p-3 bg-card dark:bg-dark-card rounded-input border border-border dark:border-dark-border shadow-sm hover:border-primary/40 transition-colors">
                                            <div class="w-5 h-5 rounded-md border-2 border-border dark:border-dark-border flex items-center justify-center text-white" :class="{ 'bg-primary-500 border-primary-500': advancePayment }">
                                                <input type="checkbox" x-model="advancePayment" class="hidden">
                                                <svg x-show="advancePayment" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-text-secondary dark:text-slate-400">Adjust Advance Payment</span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-2">Discount Coupon Code</label>
                                        <div class="relative" x-show="!appliedCoupon">
                                            <input type="text" x-model="couponCode" placeholder="Enter Code" @keydown.enter.prevent="applyCoupon()" class="input-base pr-10 uppercase placeholder:normal-case">
                                            <button type="button" @click="applyCoupon()" :disabled="couponLoading" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center">
                                                <svg x-show="!couponLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                <svg x-show="couponLoading" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            </button>
                                        </div>
                                        <div x-show="appliedCoupon" class="flex items-center justify-between p-2.5 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-xl" x-cloak>
                                            <div class="truncate mr-2">
                                                <span class="text-[10px] font-black text-emerald-700 dark:text-emerald-300 uppercase tracking-wide block truncate" x-text="appliedCoupon ? appliedCoupon.code : ''"></span>
                                                <span class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400 block truncate" x-text="appliedCoupon ? appliedCoupon.message : ''"></span>
                                            </div>
                                            <button type="button" @click="removeCoupon()" class="p-1 bg-rose-100 hover:bg-rose-200 text-rose-600 rounded-lg transition-colors shrink-0" title="Remove Coupon">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                        <div class="flex justify-between mt-1.5 px-1">
                                            <span class="text-[9px] font-bold text-text-muted">Coupon Value</span>
                                            <x-money value="couponAmount" class="text-[9px] font-black text-text-secondary dark:text-slate-300" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Rows Header -->
                            <div class="flex items-center justify-between">
                                <h3 class="text-[11px] font-black uppercase text-text-primary dark:text-dark-text tracking-widest flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                                    Payment Methods
                                </h3>
                                <button @click="addPaymentRow()" class="px-3 py-1.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-primary-100 dark:hover:bg-primary-500/20 transition-colors flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                    Add Row
                                </button>
                            </div>

                            <!-- Payment Rows -->
                            <div class="space-y-3">
                                <template x-for="(row, index) in paymentRows" :key="index">
                                    <div class="card p-4 relative group hover:border-primary/40 transition-all">
                                        <button @click="removePaymentRow(index)" x-show="paymentRows.length > 1" class="absolute -top-2 -right-2 bg-danger text-white rounded-full p-1.5 shadow-md opacity-0 group-hover:opacity-100 transition-all hover:scale-110 z-10">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                            <div class="md:col-span-3">
                                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Amount</label>
                                                <div class="relative">
                                                     <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-[10px] font-black">{{ $currencySymbol }}</span>
                                                    <input type="number" x-model="row.amount" class="input-base pl-7 pr-3 text-[11px] font-black">
                                                </div>
                                            </div>
                                            <div class="md:col-span-3">
                                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Method</label>
                                                <select x-model="row.type" class="input-base px-3 text-[11px] font-bold appearance-none">
                                                    <option value="CASH">CASH</option>
                                                    <option value="CARD">CARD</option>
                                                    <option value="BKASH">BKASH</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-3">
                                                 <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Account</label>
                                                 <x-searchable-select :options="$accounts" labelKey="account_name" valueKey="id" placeholder="Select Account" model="row.account" />
                                            </div>
                                            <div class="md:col-span-3">
                                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Note</label>
                                                <input type="text" x-model="row.note" placeholder="Optional" class="input-base px-3 text-[11px] font-bold">
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-2">Internal Note</label>
                                <textarea x-model="paymentNote" rows="2" placeholder="Add a note for this transaction..." class="input-base resize-none"></textarea>
                            </div>

                        </div>

                        <!-- Right Column: Summary -->
                        <div class="lg:col-span-5">
                            <div class="bg-navy rounded-card overflow-hidden shadow-card text-white h-full p-5 flex flex-col justify-between">
                                <div class="space-y-4">
                                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-5 border-b border-white/10 pb-3">Payment Summary</h3>
                                    
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Items</span>
                                            <span class="text-sm font-black" x-text="totalQty.toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Subtotal</span>
                                            <x-money value="subtotal" class="text-sm font-black" />
                                        </div>
                                        <div class="flex justify-between items-center text-rose-400">
                                            <span class="text-[10px] font-bold uppercase tracking-wider">Discount (-)</span>
                                            <x-money value="totalDiscount - couponAmount" class="text-sm font-black" />
                                        </div>
                                        <div class="flex justify-between items-center text-emerald-400">
                                            <span class="text-[10px] font-bold uppercase tracking-wider">Coupon (-)</span>
                                            <x-money value="couponAmount" class="text-sm font-black" />
                                        </div>
                                        
                                        <div class="my-4 border-t border-dashed border-white/20"></div>
                                        
                                        <div class="flex flex-wrap justify-between items-end gap-y-1">
                                            <span class="text-[11px] font-black uppercase tracking-widest text-slate-300">Net Payable</span>
                                            <x-money value="totalPayable" class="text-2xl font-black text-white" />
                                        </div>
                                        
                                        <div class="bg-white/5 rounded-xl p-3 border border-white/10 space-y-2 mt-2">
                                             <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-300">Total Paying</span>
                                                <x-money value="totalPaying" class="text-sm font-black text-primary-300" />
                                            </div>
                                             <div class="flex justify-between items-center">
                                                 <span class="text-[10px] font-bold uppercase tracking-wider text-orange-300">Balance Due</span>
                                                <x-money value="balance" class="text-sm font-black text-orange-300" />
                                            </div>
                                             <div x-show="changeReturnEnabled" class="flex flex-wrap justify-between items-center gap-y-1 border-t border-white/10 pt-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Change Return</span>
                                                <x-money value="changeReturn" class="text-lg font-black text-emerald-300" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-4 border-t border-border dark:border-dark-border flex justify-end gap-3 bg-background/50 dark:bg-slate-800/10 shrink-0">
                    <button type="button" @click="multipleModalOpen = false" class="btn-ghost">
                        Close
                    </button>
                    <button type="button" @click="submitSale()" :disabled="submitting" class="btn-secondary">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-text="submitting ? 'Saving...' : 'Save & Print'">Save & Print</span>
                    </button>
                    <button type="button" @click="submitSale()" :disabled="submitting" class="btn-primary">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="submitting ? 'Saving...' : 'Save'">Save</span>
                    </button>
                </div>
            </div>
        </x-modal>

        <!-- QUICK ADD CUSTOMER MODAL -->
        <x-modal name="pos-quick-customer" state="customerModalOpen" maxWidth="2xl" focusable>
            <div class="p-6">

                <div class="mb-5 text-center">
                    <h2 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Create New Customer</h2>
                    <p class="text-text-muted text-xs font-medium mt-1">Quickly add a customer without leaving the POS.</p>
                </div>

                <form @submit.prevent="submitQuickCustomer()" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-1">Full Name *</label>
                            <input type="text" x-model="newCustomer.customer_name" required placeholder="e.g. John Doe"
                                   class="input-base">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-1">Mobile Number *</label>
                            <input type="text" x-model="newCustomer.mobile" required placeholder="017xxxxxxxx"
                                   class="input-base">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-1">Category</label>
                            <div class="relative">
                                <select x-model="newCustomer.customer_type" class="input-base appearance-none cursor-pointer pr-9">
                                    <option value="regular">Regular</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-1">Email (Optional)</label>
                            <input type="email" x-model="newCustomer.email" placeholder="customer@example.com"
                                   class="input-base">
                        </div>
                    </div>

                    <p class="text-[8px] font-bold uppercase tracking-widest text-text-muted px-1">EMI customers must be created via Customers → New Customer (full KYC wizard).</p>

                    <div class="pt-2 flex gap-3 justify-end">
                        <button type="button" @click="customerModalOpen = false" class="btn-ghost">
                            Cancel
                        </button>
                        <button type="submit" :disabled="customerSubmitting" class="btn-primary">
                            <span x-show="!customerSubmitting">Register</span>
                            <svg x-show="customerSubmitting" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" x-cloak>
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </x-modal>

        <!-- CASH PAYMENT MODAL -->
        <x-modal name="pos-cash-payment" state="cashModalOpen" maxWidth="4xl" focusable>
            <div class="flex flex-col max-h-[85vh]">

                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-border dark:border-dark-border flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Cash Payment</h2>
                        <p class="text-[10px] font-bold text-text-muted uppercase tracking-widest mt-0.5">Single transaction cash payment</p>
                    </div>
                    <button type="button" @click="cashModalOpen = false" aria-label="Close"
                            class="w-9 h-9 rounded-button bg-background dark:bg-slate-800 text-text-muted hover:bg-danger-light hover:text-danger transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                        
                        <!-- Left Column: Payment Inputs -->
                        <div class="lg:col-span-7 space-y-5">
                            
                            <!-- Top Card: Advance & Coupon -->
                            <div class="bg-background dark:bg-slate-800/30 rounded-card p-4 border border-border dark:border-dark-border">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                         <div class="flex items-center justify-between mb-2">
                                            <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Advance Payment</span>
                                            <span class="text-[11px] font-black tabular-nums text-slate-700 dark:text-white" x-text="'{{ $currencySymbol }}' + (parseFloat(advanceAmount) || 0).toFixed(2)">0.00</span>
                                        </div>
                                        <label class="flex items-center gap-3 cursor-pointer p-3 bg-card dark:bg-dark-card rounded-input border border-border dark:border-dark-border shadow-sm hover:border-primary/40 transition-colors">
                                            <div class="w-5 h-5 rounded-md border-2 border-border dark:border-dark-border flex items-center justify-center text-white" :class="{ 'bg-primary-500 border-primary-500': advancePayment }">
                                                <input type="checkbox" x-model="advancePayment" class="hidden">
                                                <svg x-show="advancePayment" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            </div>
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-text-secondary dark:text-slate-400">Adjust Advance Payment</span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-2">Discount Coupon Code</label>
                                        <div class="relative" x-show="!appliedCoupon">
                                            <input type="text" x-model="couponCode" placeholder="Enter Code" @keydown.enter.prevent="applyCoupon()" class="input-base pr-10 uppercase placeholder:normal-case">
                                            <button type="button" @click="applyCoupon()" :disabled="couponLoading" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center">
                                                <svg x-show="!couponLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                <svg x-show="couponLoading" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            </button>
                                        </div>
                                        <div x-show="appliedCoupon" class="flex items-center justify-between p-2.5 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 rounded-xl" x-cloak>
                                            <div class="truncate mr-2">
                                                <span class="text-[10px] font-black text-emerald-700 dark:text-emerald-300 uppercase tracking-wide block truncate" x-text="appliedCoupon ? appliedCoupon.code : ''"></span>
                                                <span class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400 block truncate" x-text="appliedCoupon ? appliedCoupon.message : ''"></span>
                                            </div>
                                            <button type="button" @click="removeCoupon()" class="p-1 bg-rose-100 hover:bg-rose-200 text-rose-600 rounded-lg transition-colors shrink-0" title="Remove Coupon">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                        <div class="flex justify-between mt-1.5 px-1">
                                            <span class="text-[9px] font-bold text-text-muted">Coupon Value</span>
                                            <x-money value="couponAmount" class="text-[9px] font-black text-text-secondary dark:text-slate-300" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Cash Payment Input -->
                            <div class="card p-4">
                                <h3 class="text-[11px] font-black uppercase text-text-primary dark:text-dark-text tracking-widest flex items-center gap-2 mb-4">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                    Payment Details
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Cash Amount</label>
                                         <div class="relative">
                                             <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-[10px] font-black">{{ $currencySymbol }}</span>
                                            <input type="number" x-model="cashAmount" class="input-base pl-7 pr-4 text-sm font-black">
                                        </div>
                                    </div>
                                    <div>
                                         <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5">Account</label>
                                         <x-searchable-select :options="$accounts" labelKey="account_name" valueKey="id" placeholder="Select Account" model="cashAccount" />
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-2">Payment Note</label>
                                    <textarea x-model="cashNote" rows="2" placeholder="Add a note for this transaction..." class="input-base resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Summary -->
                        <div class="lg:col-span-5">
                            <div class="bg-navy rounded-card overflow-hidden shadow-card text-white h-full p-5 flex flex-col justify-between">
                                <div class="space-y-4">
                                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-5 border-b border-white/10 pb-3">Payment Summary</h3>
                                    
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Items</span>
                                            <span class="text-sm font-black" x-text="totalQty.toFixed(2)"></span>
                                        </div>
                                         <div class="flex justify-between items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Subtotal</span>
                                            <x-money value="subtotal" class="text-sm font-black" />
                                        </div>
                                         <div class="flex justify-between items-center text-rose-400">
                                            <span class="text-[10px] font-bold uppercase tracking-wider">Discount (-)</span>
                                            <x-money value="totalDiscount - couponAmount" class="text-sm font-black" />
                                        </div>
                                         <div class="flex justify-between items-center text-emerald-400">
                                            <span class="text-[10px] font-bold uppercase tracking-wider">Coupon (-)</span>
                                            <x-money value="couponAmount" class="text-sm font-black" />
                                        </div>
                                        
                                        <div class="my-4 border-t border-dashed border-white/20"></div>
                                        
                                         <div class="flex flex-wrap justify-between items-end gap-y-1">
                                            <span class="text-[11px] font-black uppercase tracking-widest text-slate-300">Net Payable</span>
                                            <x-money value="totalPayable" class="text-2xl font-black text-white" />
                                        </div>
                                        
                                        <div class="bg-white/5 rounded-xl p-3 border border-white/10 space-y-2 mt-2">
                                             <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-300">Cash Paying</span>
                                                <x-money value="parseFloat(cashAmount) || 0" class="text-sm font-black text-primary-300" />
                                            </div>
                                             <div class="flex justify-between items-center">
                                                  <span class="text-[10px] font-bold uppercase tracking-wider text-orange-300">Balance Due</span>
                                                 <x-money value="cashBalance" class="text-sm font-black text-orange-300" />
                                             </div>
                                             <div x-show="changeReturnEnabled" class="flex flex-wrap justify-between items-center gap-y-1 border-t border-white/10 pt-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Change Return</span>
                                                <x-money value="cashChangeReturn" class="text-lg font-black text-emerald-300" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-4 border-t border-border dark:border-dark-border flex justify-end gap-3 bg-background/50 dark:bg-slate-800/10 shrink-0">
                    <button type="button" @click="cashModalOpen = false" class="btn-ghost">
                        Close
                    </button>
                    <button type="button" @click="submitSale()" :disabled="submitting" class="btn-secondary">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <span x-text="submitting ? 'Saving...' : 'Save & Print'">Save & Print</span>
                    </button>
                    <button type="button" @click="submitSale()" :disabled="submitting" class="btn-primary">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="submitting ? 'Saving...' : 'Save'">Save</span>
                    </button>
                </div>
            </div>
        </x-modal>

        <!-- EMI PAYMENT MODAL -->
        <x-modal name="pos-emi-details" state="emiModalOpen" maxWidth="2xl" focusable>
            <div class="flex flex-col max-h-[85vh]">

                <!-- Modal Header -->
                <div class="px-4 py-3.5 flex items-center justify-between border-b border-border dark:border-dark-border shrink-0">
                    <h2 class="text-lg font-black tracking-tight text-text-primary dark:text-dark-text">EMI Details</h2>
                    <button type="button" @click="emiModalOpen = false" aria-label="Close"
                            class="w-9 h-9 rounded-button bg-background dark:bg-slate-800 text-text-muted hover:bg-danger-light hover:text-danger transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        
                        <!-- Left Column: Inputs (EMI DETAILS) -->
                        <div class="space-y-4">
                            
                            <!-- Customer Display (Readonly) -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Customer</label>
                                <div class="relative">
                                    <input type="text" :value="emiCustomerName" readonly class="input-base pl-9 pr-4 text-xs font-black text-text-secondary dark:text-slate-300 cursor-not-allowed">
                                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Total Amount & Initial -->
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Total Amount</label>
                                     <div class="relative">
                                         <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-[10px] font-bold">{{ $currencySymbol }}</span>
                                         <input type="text" :value="emiTotalAmount.toFixed(2)" readonly class="input-base pl-6 pr-3 text-xs font-black text-text-muted cursor-not-allowed">
                                     </div>
                                </div>
                                <div class="space-y-1.5">
                                     <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Initial Pay</label>
                                     <div class="relative">
                                        <input type="number" x-model="emiInitialPay" placeholder="Amount..." class="input-base pl-4 pr-8 text-xs font-bold placeholder:text-text-muted">
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 text-teal-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        </div>
                                     </div>
                                </div>

                                <!-- Duration & Start Date -->
                                <div class="space-y-1.5">
                                     <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Duration</label>
                                     <div class="relative">
                                        <select x-model="emiDuration" class="input-base px-3 text-xs font-bold appearance-none cursor-pointer">
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
                                     <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Start Date</label>
                                     <div class="relative">
                                        <input type="date" x-model="emiStartDate" class="input-base px-3 text-xs font-bold">
                                     </div>
                                </div>
                            </div>
                            
                            <!-- Processing Fee -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Processing Fee</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-[10px] font-bold">{{ $currencySymbol }}</span>
                                    <input type="number" x-model="emiProcessingFee" class="input-base pl-6 pr-10 text-xs font-black">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-primary dark:text-dark-text font-black text-xs">{{ $currencySymbol }}500</span>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-black uppercase text-text-secondary dark:text-slate-400 tracking-widest pl-1">Notes</label>
                                <div class="relative">
                                    <textarea x-model="emiNotes" rows="2" placeholder="Enter any notes..." class="input-base pl-4 pr-9 text-xs font-bold resize-none placeholder:text-text-muted"></textarea>
                                    <div class="absolute right-3 bottom-3 text-teal-500">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="flex items-center gap-3 pt-1">
                                <button type="button" @click="emiModalOpen = false" class="btn-ghost">
                                    Cancel
                                </button>
                                <button type="button" @click="submitEmi()" :disabled="submitting" class="btn-primary flex-1">
                                    <span x-show="submitting" class="w-4 h-4 animate-spin rounded-full border-2 border-white border-t-transparent inline-block" x-cloak></span>
                                    <span x-text="submitting ? 'Saving...' : 'Create EMI'">Create EMI</span>
                                </button>
                            </div>

                        </div>

                        <!-- Right Column: EMI Summary (Dark Card) -->
                        <div class="bg-navy rounded-card p-4 text-white flex flex-col h-full overflow-hidden">
                            <h3 class="text-sm font-bold mb-3 border-b border-white/10 pb-2.5">EMI Summary</h3>
                            
                            <div class="space-y-3 mb-4">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Total Amount:</span>
                                    <x-money value="emiTotalAmount" class="font-bold" />
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Initial Payment:</span>
                                    <x-money value="parseFloat(emiInitialPay) || 0" class="font-bold" />
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Remaining:</span>
                                    <x-money value="emiRemaining" class="font-bold" />
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Duration:</span>
                                    <span class="font-bold" x-text="emiDuration + ' Months'"></span>
                                </div>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-400 font-medium">Processing Fee:</span>
                                    <x-money value="parseFloat(emiProcessingFee) || 0" class="font-bold" />
                                </div>
                            </div>
                            
                            <div class="my-3 border-t border-dashed border-white/20"></div>

                            <div class="flex flex-wrap items-baseline justify-between gap-y-1 mb-4">
                                <span class="text-sm font-bold text-white">Monthly EMI</span>
                                <div class="text-right">
                                    <x-money value="emiMonthly" class="text-lg font-black text-teal-400" />
                                    <span class="text-[10px] text-slate-400 block">per month</span>
                                </div>
                            </div>

                            <!-- Schedule Table -->
                            <div class="bg-slate-800/50 rounded-card overflow-hidden mb-4 flex-1 max-h-48 overflow-y-auto custom-scrollbar">
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
                                                <td class="px-3 py-2 text-right font-bold"><x-money value="row.amount" /></td>
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
        </x-modal>

        <!-- SERIAL SELECTION MODAL -->
        <x-modal name="pos-serial-selection" state="serialModalOpen" maxWidth="xl" focusable>
            <div class="flex flex-col max-h-[80vh]">

                <!-- Modal Header -->
                <div class="px-5 py-3.5 border-b border-border dark:border-dark-border flex items-center justify-between bg-background/50 dark:bg-slate-800/20 shrink-0">
                    <div>
                        <h2 class="text-base font-black text-text-primary dark:text-dark-text flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                            Select Serial Numbers
                        </h2>
                        <template x-if="currentSerialItem">
                            <p class="text-[10px] font-bold text-text-muted uppercase tracking-widest mt-0.5" x-text="currentSerialItem.item_name"></p>
                        </template>
                    </div>
                    <button type="button" @click="serialModalOpen = false" aria-label="Close" class="w-9 h-9 rounded-button bg-background dark:bg-slate-800 text-text-muted hover:bg-danger-light hover:text-danger transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                    <!-- Loading State -->
                    <div x-show="loading" class="flex flex-col items-center justify-center py-12 text-text-muted">
                        <svg class="animate-spin h-8 w-8 mb-3" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest">Loading Serials...</span>
                    </div>

                    <!-- No Results -->
                    <div x-show="!loading && availableSerials.length === 0" class="flex flex-col items-center justify-center py-12 text-text-muted text-center">
                        <div class="w-16 h-16 bg-background dark:bg-slate-800/50 rounded-full flex items-center justify-center mb-4 border border-dashed border-border dark:border-dark-border">
                            <svg class="w-8 h-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"></path></svg>
                        </div>
                        <h3 class="text-[11px] font-black text-text-secondary uppercase tracking-widest">No serials available</h3>
                        <p class="text-[10px] text-text-muted mt-1 font-medium">Add stock with serial numbers first.</p>
                    </div>

                    <!-- Serial Grid -->
                    <div x-show="!loading && availableSerials.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="s in availableSerials" :key="s.id">
                            <button @click="toggleSerial(s.id)"
                                    :class="tempSelectedSerials.includes(s.id) ? 'bg-primary text-white border-primary shadow-md shadow-primary/30' : 'bg-background dark:bg-slate-800 border-border dark:border-dark-border text-text-secondary dark:text-slate-300 hover:border-primary/40'"
                                    class="p-3 rounded-xl border text-left transition-all group overflow-hidden relative min-h-11">
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
                <div class="px-5 py-3.5 border-t border-border dark:border-dark-border bg-background/50 dark:bg-slate-800/20 flex flex-wrap items-center justify-between gap-4 shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="bg-primary-light dark:bg-primary/15 px-3 py-2 rounded-input border border-primary/20 dark:border-primary/30">
                             <span class="text-[10px] font-black text-primary dark:text-primary-300 uppercase tracking-widest">Selected: <span x-text="tempSelectedSerials.length"></span></span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="serialModalOpen = false" class="btn-ghost">
                            Cancel
                        </button>
                        <button type="button" @click="confirmSerials()" class="btn-primary">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </x-modal>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('posComponent', () => ({
                cart: [],
                // Double-submit guard — set true while a sale/hold/EMI request is in flight.
                submitting: false,
                loading: false,
                itemSearchQuery: '',
                searchResults: [],
                selectedCustomer: '',
                previousDue: 0,
                // Customer snapshot side-panel state (refreshed by fetchCustomerDue)
                customerSnapshotName: '',
                customerSnapshotMobile: '',
                customerSnapshotDue: 0,
                get customerInitials() {
                    if (!this.customerSnapshotName) return '';
                    return this.customerSnapshotName.trim().split(/\s+/).map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
                },
                selectedWarehouse: '{{ $warehouses->first()->id ?? '' }}',
                selectedAccount: '{{ $accounts->first()->id ?? '' }}',
                serialModalOpen: false,
                availableSerials: [],
                currentSerialItem: null,
                tempSelectedSerials: [],
                roundOffEnabled: {{ !empty(store_settings()->round_off) ? 'true' : 'false' }},
                changeReturnEnabled: {{ !empty(store_settings()->change_return) ? 'true' : 'false' }},
                isFullscreen: false,
                // Held-orders side-panel state (client-side pagination)
                heldOrders: @js($heldOrders),
                heldOrdersPage: 1,
                heldOrdersPerPage: 5,
                get paginatedHeldOrders() {
                    const start = (this.heldOrdersPage - 1) * this.heldOrdersPerPage;
                    return this.heldOrders.slice(start, start + this.heldOrdersPerPage);
                },
                get heldOrdersTotalPages() {
                    return Math.max(1, Math.ceil(this.heldOrders.length / this.heldOrdersPerPage));
                },
                
                fetchCustomerDue() {
                    if (!this.selectedCustomer) {
                        this.previousDue = 0;
                        this.customerSnapshotDue = 0;
                        return;
                    }
                    let url = '{{ route('sales.pos.customer.due', ['id' => ':id']) }}'.replace(':id', this.selectedCustomer);
                    fetch(url)
                        .then(res => {
                            if (!res.ok) throw new Error('Server Error: ' + res.statusText);
                            return res.json();
                        })
                        .then(data => {
                            if (data.success) {
                                this.previousDue = data.due;
                                this.advanceAmount = data.advance || 0;
                                this.customerSnapshotDue = data.due;
                            }
                        })
                        .catch(err => console.error('Error fetching due:', err));
                },
                get subtotal() { return this.cart.reduce((sum, i) => sum + (i.price * i.qty), 0) },
                get totalQty() { return this.cart.reduce((sum, i) => sum + i.qty, 0) },
                get isEmiCustomer() {
                    if (!this.selectedCustomer) return false;
                    let customer = this.customers.find(c => c.id == this.selectedCustomer);
                    return customer && customer.customer_type === 'emi';
                },
                
                fetchItems() {
                    if (this.itemSearchQuery.length === 0) {
                        this.searchResults = [];
                    }
                    this.loading = true;
                    let url = '{{ route('sales.pos.search.items', [], false) }}?q=' + this.itemSearchQuery +
                              '&warehouse_id=' + this.selectedWarehouse;
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
                // Scanner queue: rapid consecutive barcode scans are captured on Enter,
                // the input is cleared immediately, and each scan is resolved sequentially
                // so scans never concatenate or race the async product search.
                scanQueue: [],
                scanProcessing: false,
                // Codes currently queued/in-flight — guards against a scanner's duplicate
                // Enter re-processing the same scan, while allowing a genuine re-scan later.
                pendingScans: new Set(),
                handleEnterKey() {
                    const raw = (this.itemSearchQuery || '').trim();
                    if (!raw) {
                        // Nothing typed: just close any dropdown
                        this.searchResults = [];
                        return;
                    }
                    // Manual single-result dropdown path: keep existing behavior
                    // (adds the one dropdown result — preserves partial-name typing)
                    if (this.searchResults.length === 1 && this.searchResults[0]) {
                        this.addToCart(this.searchResults[0]);
                        this.itemSearchQuery = '';
                        this.searchResults = [];
                        return;
                    }
                    // Treat as a scanner scan: capture + clear immediately, then queue
                    const code = raw.toUpperCase();
                    if (this.pendingScans.has(code)) return;
                    this.pendingScans.add(code);
                    this.itemSearchQuery = '';
                    this.searchResults = [];
                    this.scanQueue.push(code);
                    this.processScanQueue();
                },
                processScanQueue() {
                    if (this.scanProcessing) return;
                    if (this.scanQueue.length === 0) return;
                    this.scanProcessing = true;
                    const code = this.scanQueue.shift();
                    const url = '{{ route('sales.pos.search.items', [], false) }}?q=' + encodeURIComponent(code) +
                                '&warehouse_id=' + this.selectedWarehouse;
                    fetch(url)
                        .then(res => res.ok ? res.json() : [])
                        .then(data => {
                            const found = (Array.isArray(data) ? data : []).find(p =>
                                String(p.custom_barcode || '').toUpperCase() === code ||
                                String(p.item_code || '').toUpperCase() === code ||
                                // A13: serial-fallback hit — the scan term resolved to a serial,
                                // so the returned item carries matched_serial_id/matched_serial
                                // (its barcode/code never equals the serial value).
                                !!p.matched_serial_id
                            );
                            if (found) {
                                // A13: scan-by-serial path — pre-attach the exact matched serial
                                // and skip the modal. ONLY for this fallback hit.
                                if (found.matched_serial_id) {
                                    this.addToCartBySerial(found, found.matched_serial_id, found.matched_serial || code);
                                } else if (found.is_serialized == 1) {
                                    // Serialized items keep their existing dropdown -> serial-modal
                                    // flow (the cashier picks serials); don't auto-open the modal
                                    // from the scan queue.
                                    this.itemSearchQuery = code;
                                    this.searchResults = [found];
                                } else {
                                    this.addToCart(found);
                                }
                            }
                            // If not found, silently drop (the input is already cleared, ready for next scan)
                        })
                        .catch(() => {})
                        .finally(() => {
                            this.pendingScans.delete(code);
                            this.scanProcessing = false;
                            this.processScanQueue();
                            this.$nextTick(() => {
                                if (this.$refs.itemSearchInput) this.$refs.itemSearchInput.focus();
                            });
                        });
                },
                // A13 (Phase 2): scan-by-serial entry path. Adds the resolved item to
                // the cart with the EXACT matched serial pre-attached (selectedSerials
                // populated + serial display set + qty=1), bypassing the serial modal
                // for THIS specific add only. The regular typed / non-serial scan path
                // (addToCart / openSerialModal) is untouched.
                addToCartBySerial(p, serialId, serialValue) {
                    // Phase 3 client guard: reject if this exact serial is already in
                    // the cart on ANY line (selectedSerials across all lines).
                    const alreadyInCart = this.cart.some(line =>
                        Array.isArray(line.selectedSerials) && line.selectedSerials.includes(serialId)
                    );
                    if (alreadyInCart) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Serial Already in Cart',
                            text: 'This serial is already in your cart.',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }
                    let existing = this.cart.find(i => i.id === p.id);
                    if (existing && Array.isArray(existing.selectedSerials) && existing.selectedSerials.includes(serialId)) {
                        return;
                    }
                    const display = String(serialValue || p.sku || 'N/A');
                    if (existing) {
                        // Same item line already in cart — append this serial to it.
                        existing.selectedSerials = [...(existing.selectedSerials || []), serialId];
                        existing.qty = existing.selectedSerials.length;
                        existing.serial = existing.selectedSerials
                            .map(id => this.serialNameById(id) || display)
                            .join(', ');
                        // A resumed-hold line waiting on serial re-selection is now satisfied.
                        if (existing.needsSerialSelection !== undefined) {
                            existing.needsSerialSelection = false;
                        }
                    } else {
                        this.cart.push({
                            id: p.id,
                            name: p.item_name,
                            price: parseFloat(p.sales_price),
                            qty: 1,
                            serial: display,
                            selectedSerials: [serialId],
                            stock: p.stock,
                            tax: 0,
                            taxAmount: 0,
                            discount: 0,
                            isSerialized: p.is_serialized || 0
                        });
                    }
                },
                // Resolve a serial display name from the last-fetched availableSerials
                // list; falls back to '' when unknown so the caller supplies a default.
                serialNameById(id) {
                    const s = (this.availableSerials || []).find(x => x.id == id);
                    return s ? s.serial_number : '';
                },
                addToCart(p) {
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
                            price: parseFloat(p.sales_price),
                            qty: 1,
                            serial: p.sku || 'N/A',
                            selectedSerials: [],
                            stock: p.stock,
                            tax: 0,
                            taxAmount: 0,
                            discount: 0,
                            isSerialized: p.is_serialized || 0
                        });
                    }
                    if (this.itemSearchQuery.length > 0) {
                        this.itemSearchQuery = '';
                        this.searchResults = [];
                        this.fetchItems();
                        this.$nextTick(() => {
                            if (this.$refs.itemSearchInput) {
                                this.$refs.itemSearchInput.focus();
                            }
                        });
                    }
                },
                submitSale() {
                    if (this.submitting) {
                        return;
                    }
                    if (this.cart.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cart Empty',
                            text: 'Please add some items to the cart first!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return;
                    }

                    // --- A2: block completion while a serialized line still needs
                    // serial re-selection (resumed holds restore serialized items
                    // with an empty serial set and require the cashier to re-pick
                    // before the sale can be completed — prevents completing with
                    // serials left at status=0 / never marked sold). ---
                    const pendingSerial = this.cart.find(i => i.needsSerialSelection);
                    if (pendingSerial) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Serial Selection Required',
                            text: 'Please re-select serial numbers for "' + (pendingSerial.name || 'item') + '" before completing this sale.',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        this.$nextTick(() => this.openSerialModal(pendingSerial));
                        return;
                    }
                    
                    let payload = {
                        customer_id: this.selectedCustomer,
                        warehouse_id: this.selectedWarehouse,
                        cart: this.cart,
                        subtotal: this.subtotal,
                        discount_on_all: parseFloat(this.discountOnAll || 0),
                        discount_type: this.discountType,
                        coupon_id: this.couponId,
                        customer_coupon_id: this.customerCouponId,
                        coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                        coupon_amt: parseFloat(this.couponAmount || 0),
                        round_off: this.roundOffAmount,
                        grand_total: this.totalPayable,
                        advance_amount: this.advancePayment ? parseFloat(this.advanceAmount || 0) : 0,
                        account_id: this.selectedAccount,
                        hold_id: this.holdId,
                        sale_id: this.saleId,
                    };

                    if (this.cashModalOpen) {
                        payload.paid_amount = parseFloat(this.cashAmount) || 0;
                        payload.payment_type = 'Cash';
                        payload.account_id = this.cashAccount || this.selectedAccount;
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
                        payload.paid_amount = this.totalPayable;
                        payload.payment_type = 'Cash';
                    }

                    this.submitting = true;
                    Swal.fire({
                        title: 'Confirm Sale',
                        text: "Are you sure you want to complete this sale?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#2563eb',
                        cancelButtonColor: '#94a3b8',
                        confirmButtonText: 'Yes, Confirm!',
                        cancelButtonText: 'Cancel',
                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('{{ route('sales.pos.store', [], false) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify(payload)
                            })
                            .then(async res => {
                                const text = await res.text();
                                if (!res.ok) {
                                    // Try to parse as JSON for the message, if it fails, use the status text
                                    try {
                                        const errorData = JSON.parse(text);
                                        throw new Error(errorData.message || 'Server Error');
                                    } catch (e) {
                                        throw new Error('Server Error (' + res.status + '): ' + (text.substring(0, 100) || res.statusText));
                                    }
                                }
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    throw new Error('Invalid response format from server');
                                }
                            })
                            .then(data => {
                                this.submitting = false;
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Sale Completed!',
                                        text: 'Opening invoice in new tab...',
                                        timer: 1500,
                                        showConfirmButton: false,
                                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    }).then(() => {
                                        let invoiceUrl = '{{ route('sales.invoice', ['id' => ':id']) }}'.replace(':id', data.sale_id);
                                        window.open(invoiceUrl, '_blank');
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Sale Failed',
                                        text: data.message,
                                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    });
                                }
                            })
                            .catch(err => {
                                this.submitting = false;
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: err.message || err,
                                    background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                    color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                });
                            });
                        } else {
                            this.submitting = false;
                        }
                    });
                },

                openSerialModal(item) {
                    this.currentSerialItem = item;
                    this.tempSelectedSerials = [];
                    this.loading = true;
                    this.serialModalOpen = true;
                    this.clearSearch();

                    fetch('{{ route('sales.pos.search.serials', [], false) }}?item_id=' + item.id + '&warehouse_id=' + this.selectedWarehouse)
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
                        // A2: serials now selected → line no longer blocks completion.
                        if (existing.needsSerialSelection !== undefined) {
                            existing.needsSerialSelection = false;
                        }
                    } else {
                        this.cart.push({
                            id: p.id,
                            name: p.item_name,
                            price: parseFloat(p.sales_price),
                            qty: this.tempSelectedSerials.length,
                            serial: this.getSerialNames(this.tempSelectedSerials),
                            selectedSerials: [...this.tempSelectedSerials],
                            stock: p.stock,
                            tax: 0,
                            taxAmount: 0,
                            discount: 0,
                            isSerialized: p.is_serialized || 0
                        });
                    }
                    this.serialModalOpen = false;
                    this.clearSearch();
                },
                getSerialNames(ids) {
                    return this.availableSerials
                        .filter(s => ids.includes(s.id))
                        .map(s => s.serial_number)
                        .join(', ');
                },
                clearSearch() {
                    this.itemSearchQuery = '';
                    this.searchResults = [];
                    this.fetchItems();
                    this.$nextTick(() => {
                        if (this.$refs.itemSearchInput) {
                            this.$refs.itemSearchInput.focus();
                        }
                    });
                },

                holdModalOpen: false,
                holdReference: '',
                holdId: null,
                saleId: null,
                submitHold() {
                    if (this.submitting) {
                        return;
                    }
                    if (!this.holdReference) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Reference Required',
                            text: 'Please enter a reference number!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return; 
                    }
                    if (this.cart.length === 0) { 
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cart Empty',
                            text: 'Please add some items to the cart first!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return; 
                    }
                    this.submitting = true;
                    fetch('{{ route('sales.pos.hold', [], false) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            customer_id: this.selectedCustomer,
                            warehouse_id: this.selectedWarehouse,
                            cart: this.cart,
                            subtotal: this.subtotal,
                            grand_total: this.totalPayable,
                            reference_no: this.holdReference,
                            // --- A3: persist cart-level discount + coupon + note
                            // so resume restores the exact held cart ---
                            discount_on_all: parseFloat(this.discountOnAll || 0),
                            discount_type: this.discountType,
                            coupon_id: this.couponId,
                            customer_coupon_id: this.customerCouponId,
                            coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                            coupon_type: this.appliedCoupon ? this.appliedCoupon.type : null,
                            coupon_value: this.appliedCoupon ? parseFloat(this.appliedCoupon.value || 0) : 0,
                            coupon_amt: parseFloat(this.couponAmount || 0),
                            notes: this.paymentNote
                        })
                    })
                    .then(async res => {
                        const text = await res.text();
                        if (!res.ok) {
                            try {
                                const errorData = JSON.parse(text);
                                throw new Error(errorData.message || 'Server Error');
                            } catch (e) {
                                throw new Error('Server Error (' + res.status + '): ' + (text.substring(0, 100) || res.statusText));
                            }
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid response format from server');
                        }
                    })
                    .then(data => {
                        this.submitting = false;
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Held Successfully',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false,
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                            this.holdModalOpen = false;
                            this.holdReference = '';
                            this.cart = [];
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hold Error',
                                text: data.message,
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                        }
                    })
                    .catch(err => {
                        this.submitting = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Hold Failed',
                            text: err.message || err,
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                    });
                },

                multipleModalOpen: false,
                paymentRows: [{ amount: '', type: 'CASH', account: '{{ $accounts->first()->id ?? '' }}', note: '' }],
                discountOnAll: 0,
                discountType: 'fixed',
                couponCode: '',
                appliedCoupon: null,
                couponLoading: false,
                couponId: null,
                customerCouponId: null,
                couponAmount: 0,
                offerAmount: 0,
                advancePayment: false,
                advanceAmount: 0,
                paymentNote: '',
                
                customers: @js($customers),
                customerDropdownOpen: false,
                customerSearch: 'Walk-in Customer',
                filteredCustomers() {
                    if (!this.customers || !Array.isArray(this.customers)) return [];
                    if (this.customerSearch === '' || this.customerSearch === 'Walk-in Customer') return this.customers;
                    const q = this.customerSearch.toLowerCase();
                    return this.customers.filter(c => 
                        (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
                        (c.mobile && String(c.mobile).includes(q)) ||
                        (c.customer_code && c.customer_code.toLowerCase().includes(q))
                    );
                },
                selectCustomer(customer) {
                    if (customer) {
                        this.selectedCustomer = customer.id;
                        this.customerSearch = customer.customer_name;
                        this.advanceAmount = parseFloat(customer.tot_advance || 0);
                        this.customerSnapshotName = customer.customer_name || '';
                        this.customerSnapshotMobile = customer.mobile || '';
                        this.fetchCustomerDue();
                    } else {
                        this.selectedCustomer = '';
                        this.customerSearch = 'Walk-in Customer';
                        this.previousDue = 0;
                        this.advanceAmount = 0;
                        this.customerSnapshotName = '';
                        this.customerSnapshotMobile = '';
                        this.customerSnapshotDue = 0;
                    }
                    this.customerDropdownOpen = false;
                },

                customerModalOpen: false,
                customerSubmitting: false,
                newCustomer: {
                    customer_name: '',
                    mobile: '',
                    email: '',
                    customer_type: 'regular'
                },
                submitQuickCustomer() {
                    this.customerSubmitting = true;
                    fetch('{{ route('contacts.customers.quick-store') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(this.newCustomer)
                    })
                    .then(async res => {
                        const isJson = res.headers.get('content-type')?.includes('application/json');
                        const data = isJson ? await res.json() : null;
                        
                        this.customerSubmitting = false;
                        if (res.ok && data?.success) {
                            // Add to local customers list
                            this.customers.push(data.customer);
                            this.selectCustomer(data.customer);
                            
                            this.customerModalOpen = false;
                            this.newCustomer = { customer_name: '', mobile: '', email: '', customer_type: 'regular' };
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Customer added and selected!',
                                timer: 1500,
                                showConfirmButton: false,
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                        } else {
                            const errorMsg = data?.message || (isJson ? 'Validation Error' : 'Server Error (500)');
                            console.error('Quick Add Error:', data || res);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMsg,
                                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                            });
                        }
                    })
                    .catch(err => {
                        this.customerSubmitting = false;
                        console.error('Fetch Error:', err);
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network Error or Invalid Response!' });
                    });
                },

                openMultipleModal() {
                    this.multipleModalOpen = true;
                    // Auto-fill first row with total payable
                    this.paymentRows = [{ 
                        amount: this.totalPayable.toFixed(2), 
                        type: 'CASH', 
                        account: '{{ $accounts->first()->id ?? '' }}', 
                        note: '' 
                    }];
                },
                addPaymentRow() { 
                    // Auto-fill new row with remaining balance
                    let remaining = this.balance;
                    if (remaining > 0) {
                        this.paymentRows.push({ 
                            amount: remaining.toFixed(2), 
                            type: 'CASH', 
                            account: '{{ $accounts->first()->id ?? '' }}', 
                            note: '' 
                        });
                    } else {
                        this.paymentRows.push({ amount: '', type: 'CASH', account: '{{ $accounts->first()->id ?? '' }}', note: '' }); 
                    }
                },
                removePaymentRow(index) { this.paymentRows.splice(index, 1); },
                removeItem(id) {
                    this.cart = this.cart.filter(item => item.id !== id);
                },
                get totalPaying() { return this.paymentRows.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0); },
                get hasItemDiscount() {
                    return this.cart.some(i => parseFloat(i.discount || 0) > 0);
                },
                get invoiceDiscount() {
                    if (this.discountType === 'fixed') {
                        return parseFloat(this.discountOnAll || 0);
                    } else {
                        return (this.subtotal * (parseFloat(this.discountOnAll || 0))) / 100;
                    }
                },
                get totalDiscount() {
                    // Item-level discount is a fixed amount (৳), capped at each item's subtotal.
                    let itemDisco = this.cart.reduce((sum, i) => sum + Math.min(parseFloat(i.discount || 0), i.price * i.qty), 0);
                    let total = itemDisco + this.invoiceDiscount + parseFloat(this.couponAmount || 0);
                    return Math.min(this.subtotal, total);
                },
                get rawPayable() { 
                    return Math.max(0, (this.subtotal - this.totalDiscount) - (this.advancePayment ? this.advanceAmount : 0)); 
                },
                get roundOffAmount() { return this.roundOffEnabled ? (Math.round(this.rawPayable) - this.rawPayable) : 0; },
                get totalPayable() { return this.roundOffEnabled ? Math.round(this.rawPayable) : this.rawPayable; },
                get balance() { return Math.max(0, this.totalPayable - this.totalPaying); },
                get changeReturn() { return Math.max(0, this.totalPaying - this.totalPayable); },

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
                    let customerId = this.selectedCustomer || null;

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
                            this.couponAmount = data.discount_amount;
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
                    this.couponAmount = 0;
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

                recalculateCoupon() {
                    if (this.appliedCoupon) {
                        if (String(this.appliedCoupon.type).toLowerCase() === 'percentage') {
                            this.couponAmount = Math.min(this.subtotal, Math.round((this.subtotal * (parseFloat(this.appliedCoupon.value) || 0)) / 100 * 100) / 100);
                        } else {
                            this.couponAmount = Math.min(this.subtotal, parseFloat(this.appliedCoupon.value) || 0);
                        }
                    }
                },
                
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
                get cashChangeReturn() { return Math.max(0, (parseFloat(this.cashAmount) || 0) - this.totalPayable); },
                get cashBalance() { return Math.max(0, this.totalPayable - (parseFloat(this.cashAmount) || 0)); },
                
                emiModalOpen: false,
                emiTotalAmount: 0,
                emiInitialPay: 0,
                emiDuration: 6,
                emiProcessingFee: 500,
                get emiRemaining() { return Math.max(0, this.emiTotalAmount - (parseFloat(this.emiInitialPay) || 0)); },
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
                        schedule.push({ month: i, date: date.toISOString().slice(0, 10), amount: monthly });
                    }
                    return schedule;
                },
                submitEmi() {
                    if (this.submitting) {
                        return;
                    }
                    if (this.cart.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cart Empty',
                            text: 'Please add some items to the cart first!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return; 
                    }
                    if (!this.emiCustomer) { 
                        Swal.fire({
                            icon: 'warning',
                            title: 'Customer Required',
                            text: 'Please select a customer first!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return; 
                    }

                    this.submitting = true;
                    Swal.fire({
                        title: 'Confirm EMI Sale',
                        text: "Are you sure you want to complete this EMI sale?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0d9488', // teal-600
                        cancelButtonColor: '#94a3b8',
                        confirmButtonText: 'Yes, Confirm!',
                        cancelButtonText: 'Cancel',
                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('{{ route('sales.pos.emi', [], false) }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                body: JSON.stringify({
                                    customer_id: this.emiCustomer,
                                    warehouse_id: this.selectedWarehouse,
                                    cart: this.cart,
                                    subtotal: this.subtotal,
                                    discount_on_all: parseFloat(this.discountOnAll || 0),
                                    discount_type: this.discountType,
                                    coupon_id: this.couponId,
                                    customer_coupon_id: this.customerCouponId,
                                    coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                                    coupon_amt: parseFloat(this.couponAmount || 0),
                                    grand_total: this.emiTotalAmount,
                                    advance_amount: this.advancePayment ? parseFloat(this.advanceAmount || 0) : 0,
                                    initial_pay: parseFloat(this.emiInitialPay) || 0,
                                    duration: this.emiDuration,
                                    monthly_installment: this.emiMonthly,
                                    processing_fee: parseFloat(this.emiProcessingFee) || 0,
                                    start_date: this.emiStartDate,
                                    notes: this.emiNotes,
                                    schedule: this.emiSchedule,
                                    account_id: this.selectedAccount,
                                    hold_id: this.holdId
                                })
                            })
                            .then(async res => {
                                const text = await res.text();
                                if (!res.ok) {
                                    try {
                                        const errorData = JSON.parse(text);
                                        throw new Error(errorData.message || 'Server Error');
                                    } catch (e) {
                                        throw new Error('Server Error (' + res.status + '): ' + (text.substring(0, 100) || res.statusText));
                                    }
                                }
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    throw new Error('Invalid response format from server');
                                }
                            })
                            .then(data => {
                                this.submitting = false;
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'EMI Sale Completed!',
                                        text: 'Opening EMI details in new tab...',
                                        timer: 1500,
                                        showConfirmButton: false,
                                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    }).then(() => {
                                        let emiUrl = '{{ route('sales.emi.show', ['id' => ':id']) }}'.replace(':id', data.emi_sale_id);
                                        window.open(emiUrl, '_blank');
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message,
                                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    });
                                }
                            })
                            .catch(err => {
                                this.submitting = false;
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: err.message || err,
                                    background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                    color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                });
                            });
                        } else {
                            this.submitting = false;
                        }
                    });
                },
                openEmiModal() {
                    if (!this.selectedCustomer) { 
                        Swal.fire({
                            icon: 'warning',
                            title: 'Customer Required',
                            text: 'Please select a customer first!',
                            background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                        });
                        return; 
                    }
                    this.emiModalOpen = true;
                    let customerObj = this.customers.find(c => c.id == this.selectedCustomer);
                    this.emiCustomerName = customerObj ? customerObj.customer_name : 'Selected Customer';
                    this.emiCustomer = this.selectedCustomer;
                    this.emiTotalAmount = this.totalPayable;
                    this.emiInitialPay = 0;
                    this.emiDuration = 6;
                    this.emiProcessingFee = 500; 
                    this.emiStartDate = new Date().toISOString().slice(0, 10);
                    this.emiNotes = '';
                },
                toggleFullscreen() {
                    const el = this.$refs.posScreen;
                    if (!document.fullscreenElement) {
                        if (el && el.requestFullscreen) el.requestFullscreen();
                    } else {
                        if (document.exitFullscreen) document.exitFullscreen();
                    }
                },
                init() {
                    console.log('POS Alpine Initialized');
                    
                    // Initialize Customer Search
                    if (this.selectedCustomer) {
                        let c = this.customers.find(i => i.id == this.selectedCustomer);
                        if (c) this.customerSearch = c.customer_name;
                    } else {
                        this.customerSearch = 'Walk-in Customer';
                    }

                    this.$watch('selectedCustomer', value => {
                        this.fetchCustomerDue();
                        // Update Customer Search Input + snapshot identity
                        if (!value) {
                            this.customerSearch = 'Walk-in Customer';
                            this.customerSnapshotName = '';
                            this.customerSnapshotMobile = '';
                        } else {
                            let c = this.customers.find(i => i.id == value);
                            if (c) this.customerSearch = c.customer_name;
                            this.customerSnapshotName = c ? (c.customer_name || '') : '';
                            this.customerSnapshotMobile = c ? (c.mobile || '') : '';
                        }
                    });
                    this.$watch('selectedWarehouse', () => {
                        this.fetchItems();
                    });
                    this.$watch('cart', () => {
                        this.recalculateCoupon();
                        // Global discount and item-level discounts are mutually exclusive.
                        // If any cart item carries a discount, clear the global discount.
                        if (this.hasItemDiscount && parseFloat(this.discountOnAll || 0) > 0) {
                            this.discountOnAll = 0;
                        }
                    }, { deep: true });
                    this.fetchCustomerDue();
                    if (window.holdData) {
                        this.holdId = window.holdData.id;
                        this.selectedCustomer = window.holdData.customer_id || '';
                        this.selectedWarehouse = window.holdData.warehouse_id || '';
                        this.holdReference = window.holdData.reference_no || '';

                        // --- A3: restore the held cart-level state exactly ---
                        if (window.holdData.discount_on_all !== undefined && window.holdData.discount_on_all !== null) {
                            this.discountOnAll = parseFloat(window.holdData.discount_on_all || 0);
                        }
                        if (window.holdData.discount_type) {
                            this.discountType = window.holdData.discount_type;
                        }
                        if (window.holdData.sales_note) {
                            this.paymentNote = window.holdData.sales_note;
                        }
                        if (window.holdData.coupon_code && window.holdData.coupon_amount > 0) {
                            // Rebuild the appliedCoupon shape the rest of the POS
                            // component expects (matches CouponController response).
                            this.appliedCoupon = {
                                code: window.holdData.coupon_code,
                                type: window.holdData.coupon_type || 'fixed',
                                value: parseFloat(window.holdData.coupon_value || 0),
                                discount_amount: parseFloat(window.holdData.coupon_amount || 0),
                                message: 'Restored from held invoice',
                            };
                            this.couponId = window.holdData.coupon_id || null;
                            this.customerCouponId = window.holdData.customer_coupon_id || null;
                            this.couponCode = window.holdData.coupon_code;
                            this.couponAmount = parseFloat(window.holdData.coupon_amount || 0);
                        }

                        try {
                            let heldItems = window.holdData.items || [];
                            this.cart = heldItems.map(i => {
                                if (!i.item) return null;
                                const isSerialized = !!(i.is_serialized || (i.item && i.item.is_serialized));
                                return {
                                    id: i.item_id, name: i.item.item_name, price: parseFloat(i.price_per_unit), qty: parseFloat(i.sales_qty),
                                    serial: i.item.sku || 'N/A',
                                    // A2: serialized lines restore with NO serials selected and a
                                    // hard requirement to re-pick them before completing the sale.
                                    selectedSerials: [],
                                    needsSerialSelection: isSerialized,
                                    stock: i.item.stock,
                                    tax: parseFloat(i.tax_percent || 0),
                                    taxAmount: parseFloat(i.tax_amt || 0),
                                    discount: parseFloat(i.discount_input || 0),
                                    isSerialized: isSerialized ? 1 : 0
                                };
                            }).filter(item => item !== null);

                            // A2: auto-open the serial picker for the first serialized line
                            // so the cashier re-selects serials before completion.
                            const firstSerialized = this.cart.find(i => i.needsSerialSelection);
                            if (firstSerialized) {
                                this.$nextTick(() => {
                                    if (this.serialModalOpen === false) {
                                        this.openSerialModal(firstSerialized);
                                    }
                                });
                            }

                            // A10: warehouse-less hold → clear prompt before submit
                            if (window.holdData.hold_missing_warehouse) {
                                this.$nextTick(() => {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'Select a Warehouse',
                                        text: 'This held sale has no warehouse saved. Please select a warehouse before completing the sale.',
                                        background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#1e293b',
                                    });
                                });
                            }
                        } catch (e) { console.error('Error populating cart from hold:', e); }
                    } else if (window.saleData) {
                        this.saleId = window.saleData.id;
                        this.selectedCustomer = window.saleData.customer_id || '';
                        this.selectedWarehouse = window.saleData.warehouse_id || '';
                        try {
                            let saleItems = window.saleData.items || [];
                            this.cart = saleItems.map(i => {
                                if (!i.item) return null;
                                // Fetch serials for this item in this sale
                                let ser = i.item.serials ? i.item.serials.filter(s => s.sale_id == this.saleId).map(s => s.id) : [];
                                let serNames = i.item.serials ? i.item.serials.filter(s => s.sale_id == this.saleId).map(s => s.serial_number).join(', ') : '';
                                
                                return {
                                    id: i.item_id,
                                    name: i.item.item_name,
                                    price: parseFloat(i.price_per_unit),
                                    qty: parseFloat(i.sales_qty),
                                    serial: serNames || i.item.sku || 'N/A',
                                    selectedSerials: ser,
                                    stock: i.item.stock,
                                    tax: 0,
                                    discount: parseFloat(i.discount_input || 0)
                                };
                            }).filter(item => item !== null);

                            // Load payments if needed, but usually we just want to update the total
                            // For now, we focus on cart and customer/warehouse.
                        } catch (e) { console.error('Error populating cart from sale:', e); }
                    }
                    this.fetchItems();

                    // Keep fullscreen button state in sync with native fullscreen changes (incl. Escape exit)
                    document.addEventListener('fullscreenchange', () => {
                        this.isFullscreen = !!document.fullscreenElement;
                    });
                }
            }));
        });
    </script>
</x-app-layout>
