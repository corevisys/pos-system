<x-app-layout title="Create Stock Adjustment">
    <div x-data="adjustmentForm()">
        
        <!-- HEADER -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 px-1 text-black">
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-white">Create Stock Adjustment</h1>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Adjust inventory levels manually across warehouses</p>
            </div>
            <a href="{{ route('stock.adjustment') }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all flex items-center gap-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            
            <!-- LEFT SECTION: ITEM LIST -->
            <div class="lg:col-span-8 flex flex-col gap-3">

                <!-- ITEM SEARCH -->
                <div class="bg-white dark:bg-dark-card p-2 md:p-3 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm">
                    <div class="relative group" @click.away="searchResults = []">
                        <input type="text" x-model="searchQuery" 
                               @input.debounce.300ms="searchItems($event.target.value)"
                               placeholder="Search items by name, code or barcode..." 
                               class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 pl-10 pr-4 text-[11px] font-bold focus:ring-1 focus:ring-primary-500 outline-none transition-all shadow-inner placeholder:text-slate-400">
                        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        
                        <!-- SEARCH RESULTS DROPDOWN -->
                        <div x-show="searchResults.length > 0" 
                             class="absolute z-50 w-full mt-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <template x-for="item in searchResults" :key="item.id">
                                <div @click="addItem(item)" class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors border-b border-slate-50 dark:border-dark-border last:border-none flex items-center justify-between gap-3 text-black">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden border border-slate-100 dark:border-dark-border bg-slate-50 dark:bg-slate-800 flex-shrink-0">
                                            <template x-if="item.item_image">
                                                <img :src="'/' + item.item_image" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!item.item_image">
                                                <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002-2z"></path></svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black text-slate-700 dark:text-white" x-text="item.item_name"></p>
                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest" x-text="item.item_code"></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] font-black uppercase tracking-widest" :class="item.stock > 0 ? 'text-emerald-500' : 'text-rose-500'">Stock: <span x-text="item.stock"></span></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ITEM TABLE -->
                <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden flex-1 shadow-sm">
                    <div class="overflow-x-auto custom-scrollbar min-h-[400px]">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 sticky top-0 z-10 shadow-sm border-b border-slate-100 dark:border-dark-border">
                                <tr>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Item Information</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap">In Stock</th>
                                    <th class="px-4 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center whitespace-nowrap">Adjust Qty</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-dark-border" x-show="form.items.length > 0" x-cloak>
                                <template x-for="(item, index) in form.items" :key="index">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                                        <td class="px-4 py-2">
                                            <div class="flex flex-col">
                                                <p class="text-[11px] font-black text-primary-600 dark:text-primary-400" x-text="item.item_name"></p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest" x-text="item.item_code"></span>
                                                    <template x-if="item.is_serialized">
                                                        <button @click="openSerialModal(index)" class="px-1.5 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded text-[8px] font-black uppercase tracking-widest border border-emerald-100 dark:border-emerald-500/20 hover:bg-emerald-100 transition-all flex items-center gap-1">
                                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                            <span x-text="item.serials.length"></span> SN
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="text-[11px] font-black text-slate-600 dark:text-slate-300" x-text="item.stock || '0'"></span>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="updateQty(index, -1)" class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"></path></svg>
                                                </button>
                                                <input type="number" x-model.number="item.quantity" @input="onQtyChange(index)" 
                                                       class="w-12 bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-1 px-1 text-center text-[11px] font-black text-slate-800 dark:text-white focus:ring-1 focus:ring-primary-500 outline-none transition-all placeholder:text-slate-300">
                                                <button @click="updateQty(index, 1)" class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-right pr-6">
                                            <button @click="removeItem(index)" class="p-1.5 text-slate-300 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- EMPTY STATE -->
                        <div x-show="form.items.length === 0" class="flex flex-col items-center justify-center py-20 px-6 text-center">
                            <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800/50 rounded-full flex items-center justify-center mb-4 text-slate-200 dark:text-slate-700">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            </div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest">No Items Added</h3>
                            <p class="text-[10px] text-slate-400 mt-1 font-medium">Search items to adjust stock</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT SECTION: SIDEBAR -->
            <div class="lg:col-span-4 flex flex-col gap-3">
                
                <!-- SETTINGS CARD -->
                <div class="bg-white dark:bg-dark-card p-4 rounded-2xl shadow-sm border border-slate-100 dark:border-dark-border flex flex-col gap-4">
                    <div>
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 pl-1">Warehouse *</label>
                        <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="form.warehouse_id" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-3">
                        <div>
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1 pl-1">Reference No.</label>
                            <input type="text" x-model="form.reference_no" placeholder="Auto-generated" 
                                   class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-black text-slate-900 dark:text-white focus:ring-1 focus:ring-primary-500 outline-none transition-all placeholder:text-slate-300 shadow-inner">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1 pl-1">Date *</label>
                            <input type="date" x-model="form.adjustment_date" 
                                   class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-black text-slate-900 dark:text-white focus:ring-1 focus:ring-primary-500 outline-none transition-all uppercase shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1 pl-1">Reason / Note</label>
                        <textarea x-model="form.adjustment_note" rows="2" placeholder="Explanation..." 
                                  class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 focus:ring-1 focus:ring-primary-500 outline-none transition-all resize-none placeholder:text-slate-300 shadow-inner"></textarea>
                    </div>
                </div>

                <!-- SUMMARY CARD -->
                <div class="bg-slate-900 rounded-[2rem] p-6 shadow-2xl text-white relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary-500/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none group-hover:bg-primary-500/20 transition-all duration-700"></div>
                    
                    <div class="relative space-y-4">
                        <div class="flex justify-between items-center border-b border-white/10 pb-3">
                            <h3 class="text-[9px] font-black uppercase tracking-widest text-slate-400">Inventory Summary</h3>
                            <span class="px-2 py-0.5 bg-white/10 rounded text-[9px] font-black" x-text="form.items.length + ' ITEMS'"></span>
                        </div>

                        <div class="space-y-1 text-center py-2">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-primary-400">Total Adjustment Quantity</p>
                            <p class="text-5xl font-black tracking-tighter" x-text="totalQuantities"></p>
                        </div>

                        <div class="pt-1">
                            <button @click="submitForm" class="w-full py-4 bg-primary-500 hover:bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all transform active:scale-[0.98] shadow-lg shadow-primary-500/40 border border-primary-400/10 flex items-center justify-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                Confirm Adjustment
                            </button>
                        </div>
                    </div>
                </div>

                <p class="text-[9px] text-center font-bold text-slate-400 uppercase tracking-widest px-8">
                    Note: Adjustments will immediately update the selected warehouse's stock levels.
                </p>
            </div>
        </div>

        <!-- SEARCH OVERLAY -->
        <div class="fixed inset-0 z-20 bg-dark-bg/20 backdrop-blur-sm transition-all" x-show="searchResults.length > 0" @click="searchResults = []" x-cloak></div>

        <!-- SERIAL MODAL -->
        <div x-show="serialModalOpen" 
             class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            
            <div class="bg-white dark:bg-dark-card w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 transform transition-all border border-slate-100 dark:border-dark-border"
                 @click.away="serialModalOpen = false"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">
                 
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-800 dark:text-white">Serial Numbers</h2>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mt-1">Manage serials for: <span class="text-primary-500" x-text="currentSerialItem ? currentSerialItem.item_name : ''"></span></p>
                    </div>
                    <button @click="serialModalOpen = false" class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-800 text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar space-y-3">
                     <template x-if="currentSerialItem">
                        <template x-for="i in parseInt(currentSerialItem.quantity)" :key="i">
                            <div class="flex items-center gap-3 group">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-[10px] font-black text-slate-300 group-focus-within:bg-primary-50 group-focus-within:text-primary-500 transition-colors" x-text="i"></div>
                                <input type="text" 
                                       class="flex-1 bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-3 px-4 text-xs font-bold text-slate-700 dark:text-white focus:ring-2 focus:ring-primary-500 outline-none transition-all placeholder:text-slate-300 shadow-inner" 
                                       :placeholder="'Enter Serial Number ' + i"
                                       x-model="tempSerials[i-1]">
                            </div>
                        </template>
                    </template>
                </div>

                <div class="mt-8 grid grid-cols-2 gap-4">
                    <button @click="serialModalOpen = false" class="py-4 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-[11px] font-black uppercase tracking-widest transition-all">Cancel</button>
                    <button @click="saveSerials" class="py-4 bg-primary-500 hover:bg-primary-600 text-white rounded-2xl text-[11px] font-black uppercase tracking-widest transition-all shadow-lg shadow-primary-500/40">Apply Serials</button>
                </div>
            </div>
        </div>

    </div>

    <!-- SCRIPT -->
    <script>
        document.addEventListener('alpine:init', () => {
             Alpine.data('adjustmentForm', () => ({
                form: {
                    warehouse_id: '',
                    reference_no: '',
                    adjustment_date: '{{ date("Y-m-d") }}',
                    adjustment_note: '',
                    items: []
                },
                searchQuery: '',
                searchResults: [],
                
                get totalQuantities() {
                    return this.form.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
                },

                serialModalOpen: false,
                currentSerialItemIndex: null,
                tempSerials: [],

                init() {
                    this.$watch('form.warehouse_id', (value) => {
                        if (this.form.items.length > 0) {
                            this.form.items = [];
                        }
                    });
                },

                get currentSerialItem() {
                    return this.currentSerialItemIndex !== null ? this.form.items[this.currentSerialItemIndex] : null;
                },

                async searchItems(query) {
                    if (query.length < 2) {
                        this.searchResults = [];
                        return;
                    }

                    if (!this.form.warehouse_id) {
                        showError('Please select a warehouse first.');
                        this.searchQuery = '';
                        return;
                    }
                    
                    fetch(`/stock/adjustment/search-items?query=${query}&warehouse_id=${this.form.warehouse_id}`)
                        .then(response => response.json())
                        .then(data => {
                            this.searchResults = data;
                        });
                },

                addItem(item) {
                    let existingItem = this.form.items.find(i => i.item_id === item.id);
                    
                    if (existingItem) {
                        existingItem.quantity++;
                        this.onQtyChange(this.form.items.indexOf(existingItem));
                    } else {
                        const newIndex = this.form.items.push({
                            item_id: item.id,
                            item_name: item.item_name,
                            item_code: item.item_code,
                            stock: item.stock,
                            quantity: 1,
                            is_serialized: item.is_serialized,
                            serials: [],
                            description: ''
                        }) - 1;
                        
                        this.onQtyChange(newIndex);
                    }
                    
                    this.searchQuery = '';
                    this.searchResults = [];
                },

                updateQty(index, change) {
                    const item = this.form.items[index];
                    const newQty = (parseFloat(item.quantity) || 0) + change;
                    if (newQty >= 0) {
                        item.quantity = newQty;
                        this.onQtyChange(index);
                    }
                },

                onQtyChange(index) {
                    const item = this.form.items[index];
                    if (item.quantity < 0) item.quantity = 0;
                    
                    if (item.is_serialized) {
                        const qty = parseInt(item.quantity) || 0;
                        const currentCount = item.serials.length;
                        
                        if (qty > currentCount) {
                            const diff = qty - currentCount;
                            for (let i = 0; i < diff; i++) {
                                item.serials.push('');
                            }
                            // Auto open modal on increase
                            this.openSerialModal(index);
                        } else if (qty < currentCount) {
                            item.serials = item.serials.slice(0, qty);
                        }
                    }
                },

                removeItem(index) {
                    this.form.items.splice(index, 1);
                },
                
                openSerialModal(index) {
                    this.currentSerialItemIndex = index;
                    const item = this.form.items[index];
                    this.tempSerials = [...item.serials];
                    
                    // Ensure tempSerials matches current qty
                    const qty = parseInt(item.quantity) || 0;
                    if (this.tempSerials.length < qty) {
                        for (let i = this.tempSerials.length; i < qty; i++) {
                            this.tempSerials.push('');
                        }
                    } else {
                        this.tempSerials = this.tempSerials.slice(0, qty);
                    }
                    
                    this.serialModalOpen = true;
                },

                saveSerials() {
                    if (this.currentSerialItemIndex !== null) {
                        const item = this.form.items[this.currentSerialItemIndex];
                        this.form.items[this.currentSerialItemIndex].serials = this.tempSerials.slice(0, item.quantity);
                        this.serialModalOpen = false;
                        this.currentSerialItemIndex = null;
                        this.tempSerials = [];
                    }
                },

                async submitForm() {
                    // Validation
                    if (!this.form.warehouse_id) {
                        showError('Please select a warehouse.');
                        return;
                    }
                    if (this.form.items.length === 0) {
                        showError('Please add at least one item.');
                        return;
                    }

                    for (const item of this.form.items) {
                        if (item.is_serialized) {
                            const filledSerials = item.serials.filter(s => s.trim() !== '');
                            if (filledSerials.length < item.quantity) {
                                showError(`Please fill all serial numbers for "${item.item_name}"`);
                                this.openSerialModal(this.form.items.indexOf(item));
                                return;
                            }
                            const unique = new Set(filledSerials);
                            if (unique.size < filledSerials.length) {
                                showError(`Duplicate serial numbers found for "${item.item_name}"`);
                                this.openSerialModal(this.form.items.indexOf(item));
                                return;
                            }
                        }
                    }

                    try {
                        const response = await fetch(`{{ route('stock.adjustment.store', [], false) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.form)
                        });
                        
                        const responseText = await response.text();
                        let result;
                        try {
                            result = JSON.parse(responseText);
                        } catch (e) {
                            console.error('Non-JSON response:', responseText);
                            showError('Server returned an unexpected error. Please check the console.');
                            return;
                        }
                        
                        if (result.success) {
                            showSuccess(result.message || 'Stock Adjustment created successfully!');
                            setTimeout(() => { window.location.href = "{{ route('stock.adjustment') }}"; }, 800);
                        } else {
                            showError(result.message || result.error || 'Failed to create adjustment.');
                        }
                    } catch (error) {
                        console.error('Submit error:', error);
                        showError('An unexpected error occurred: ' + error.message);
                    }
                }
             }));
        });
    </script>
</x-app-layout>
