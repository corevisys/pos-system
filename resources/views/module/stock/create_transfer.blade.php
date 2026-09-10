<x-app-layout title="Create Stock Transfer">
    <div x-data="transferForm()">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">
                    Stock Transfer
                    <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">New Transfer</span>
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('stock.transfer') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Transfer List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">New Transfer</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('stock.transfer') }}" class="btn-secondary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button type="button" @click="submitForm" :disabled="isSubmitting" x-ref="submitBtn" class="btn-primary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    Save Transfer
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- LEFT: DETAILS + ITEMS -->
            <div class="lg:col-span-8 flex flex-col gap-4">

                <!-- SECTION: TRANSFER DETAILS -->
                <div class="card p-4 md:p-5">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-rose-500 rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Transfer Details</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">From Warehouse (Source) <span class="text-danger">*</span></label>
                            <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Source" model="form.warehouse_from" />
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">To Warehouse (Destination) <span class="text-danger">*</span></label>
                            <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Destination" model="form.warehouse_to" />
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" x-model="form.transfer_date" class="input-base uppercase">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Reference No.</label>
                            <input type="text" x-model="form.reference_no" placeholder="Auto-generated (e.g. TR-2023...)" class="input-base">
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Transfer Note</label>
                            <textarea x-model="form.note" rows="2" placeholder="Reference, vehicle no, etc..." class="input-base resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION: ITEMS -->
                <div class="card p-4 md:p-5">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-emerald-500 rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Items</h2>
                        <span class="ml-auto text-[9px] font-black uppercase tracking-widest text-text-muted" x-text="form.items.length + ' item(s)'"></span>
                    </div>

                    <!-- ITEM SEARCH -->
                    <div class="relative group mb-4" @click.away="searchResults = []">
                        <input type="text" x-model="searchQuery"
                               @input.debounce.300ms="searchItems($event.target.value)"
                               placeholder="Search items in source warehouse..."
                               class="input-base !pl-10">
                        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>

                        <!-- SEARCH RESULTS DROPDOWN -->
                        <div x-show="searchResults.length > 0"
                             x-cloak
                             class="absolute z-50 w-full mt-1 card max-h-60 overflow-y-auto custom-scrollbar"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <template x-for="item in searchResults" :key="item.id">
                                <div @click="addItem(item)" class="px-4 py-2 hover:bg-background dark:hover:bg-dark-bg cursor-pointer transition-colors border-b border-border dark:border-dark-border last:border-none flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden border border-border dark:border-dark-border bg-background dark:bg-dark-bg flex-shrink-0">
                                            <template x-if="item.item_image">
                                                <img :src="'/' + item.item_image" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!item.item_image">
                                                <div class="w-full h-full flex items-center justify-center text-text-muted">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-black text-text-primary dark:text-dark-text" x-text="item.item_name"></p>
                                            <p class="text-[9px] text-text-muted font-bold uppercase tracking-widest" x-text="item.item_code"></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] font-black uppercase tracking-widest" :class="item.stock > 0 ? 'text-emerald-500' : 'text-rose-500'">Avail: <span x-text="item.stock"></span></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- ITEM TABLE -->
                    <div class="rounded-2xl border border-border dark:border-dark-border overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left">
                                <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                                    <tr>
                                        <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest whitespace-nowrap">Item Information</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Source Stock</th>
                                        <th class="px-4 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center whitespace-nowrap">Transfer Qty</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border dark:divide-dark-border" x-show="form.items.length > 0" x-cloak>
                                    <template x-for="(item, index) in form.items" :key="index">
                                        <tr class="hover:bg-background/60 dark:hover:bg-dark-bg/60 transition-colors group">
                                            <td class="px-4 py-2">
                                                <div class="flex flex-col">
                                                    <p class="text-[11px] font-black text-primary" x-text="item.item_name"></p>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span class="text-[8px] font-bold text-text-muted uppercase tracking-widest" x-text="item.item_code"></span>
                                                        <template x-if="item.is_serialized">
                                                            <button type="button" @click="openSerialModal(index)" class="px-1.5 py-0.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded text-[8px] font-black uppercase tracking-widest border border-emerald-500/20 hover:bg-emerald-500/20 transition-all flex items-center gap-1">
                                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                                <span x-text="item.serials.length"></span> SN
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="text-[11px] font-black text-text-secondary dark:text-dark-text/70 tabular-nums" x-text="item.stock || '0'"></span>
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button type="button" @click="updateQty(index, -1)" class="w-6 h-6 rounded-lg bg-background dark:bg-dark-bg border border-border dark:border-dark-border flex items-center justify-center text-text-muted hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"></path></svg>
                                                    </button>
                                                    <input type="number" x-model.number="item.quantity" @input="onQtyChange(index)"
                                                           class="input-base !w-16 !py-1 !px-1 text-center tabular-nums">
                                                    <button type="button" @click="updateQty(index, 1)" class="w-6 h-6 rounded-lg bg-background dark:bg-dark-bg border border-border dark:border-dark-border flex items-center justify-center text-text-muted hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-all">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-right pr-4">
                                                <button type="button" @click="removeItem(index)" class="p-1.5 text-text-muted hover:text-danger hover:bg-danger-light dark:hover:bg-danger/10 rounded-lg transition-all">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- EMPTY STATE -->
                        <div x-show="form.items.length === 0" x-cloak class="flex flex-col items-center justify-center py-16 px-6 text-center">
                            <div class="w-16 h-16 bg-background dark:bg-dark-bg rounded-full flex items-center justify-center mb-4 text-text-muted">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            </div>
                            <h3 class="text-[11px] font-black text-text-muted uppercase tracking-widest">No Items Added</h3>
                            <p class="text-[10px] text-text-muted mt-1 font-medium">Search items in the selected source warehouse</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: SUMMARY -->
            <div class="lg:col-span-4 flex flex-col gap-4">
                <div class="card p-5 sticky top-4">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-3.5 bg-sky-500 rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Transfer Summary</h2>
                        <span class="ml-auto px-2 py-0.5 bg-background dark:bg-dark-bg rounded text-[9px] font-black text-text-muted" x-text="form.items.length + ' ITEMS'"></span>
                    </div>

                    <div class="text-center py-3 border-y border-border dark:border-dark-border">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-text-muted">Total Transfer Quantity</p>
                        <p class="text-4xl font-black tracking-tighter text-text-primary dark:text-dark-text tabular-nums mt-1" x-text="totalQuantities"></p>
                    </div>

                    <div class="mt-4">
                        <button type="button" @click="submitForm" :disabled="isSubmitting" class="btn-primary w-full">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Confirm Transfer
                        </button>
                    </div>

                    <p class="text-[9px] text-center font-bold text-text-muted uppercase tracking-widest mt-4 leading-relaxed">
                        Stock is deducted from the source and added to the destination immediately.
                    </p>
                </div>
            </div>
        </div>

        <!-- SEARCH OVERLAY -->
        <div class="fixed inset-0 z-20 bg-navy/20 backdrop-blur-sm transition-all" x-show="searchResults.length > 0" @click="searchResults = []" x-cloak></div>

        <!-- SERIAL MODAL (design-system x-modal) -->
        <x-modal name="transfer-serial-modal" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-lg font-black text-text-primary dark:text-dark-text">Transferred Serials</h2>
                        <p class="text-[10px] text-text-muted font-black uppercase tracking-widest mt-1">Select serials for: <span class="text-primary" x-text="currentSerialItem ? currentSerialItem.item_name : ''"></span></p>
                    </div>
                    <button type="button" @click="$dispatch('close-modal', { name: 'transfer-serial-modal' })" class="w-9 h-9 rounded-full bg-background dark:bg-dark-bg text-text-muted hover:text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-all flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar space-y-3">
                    <template x-if="currentSerialItem">
                        <template x-for="i in parseInt(currentSerialItem.quantity)" :key="i">
                            <div class="relative">
                                <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[7px] font-black uppercase text-text-muted tracking-widest z-10 italic" x-text="'SLN ' + i"></label>
                                <input type="text"
                                       :placeholder="'Enter Serial ' + i"
                                       x-model="tempSerials[i-1]"
                                       :class="serialRowError(i - 1, tempSerials[i-1]) ? 'input-base !ring-2 !ring-rose-500/60 !border-rose-500 uppercase tracking-wider' : 'input-base uppercase tracking-wider'">
                                <template x-if="serialRowError(i - 1, tempSerials[i-1])">
                                    <p class="text-[9px] font-bold text-danger mt-1 ml-0.5" x-text="serialRowError(i - 1, tempSerials[i-1])"></p>
                                </template>
                            </div>
                        </template>
                    </template>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="$dispatch('close-modal', { name: 'transfer-serial-modal' })" class="btn-secondary w-full justify-center">Cancel</button>
                    <button type="button" @click="saveSerials" class="btn-primary w-full justify-center">Confirm Serials</button>
                </div>
            </div>
        </x-modal>

    </div>

    <!-- SCRIPT -->
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transferForm', () => ({
                form: {
                    warehouse_from: '',
                    warehouse_to: '',
                    reference_no: '',
                    transfer_date: '{{ date("Y-m-d") }}',
                    note: '',
                    items: []
                },
                searchQuery: '',
                searchResults: [],

                isSubmitting: false,

                get totalQuantities() {
                    return this.form.items.reduce((sum, item) => sum + (parseFloat(item.quantity) || 0), 0);
                },

                currentSerialItemIndex: null,
                tempSerials: [],

                init() {
                    this.$watch('form.warehouse_from', (value) => {
                        if (this.form.items.length > 0) {
                            if (confirm('Changing source warehouse will clear the item list. Continue?')) {
                                this.form.items = [];
                            }
                        }
                    });
                },

                get currentSerialItem() {
                    return this.currentSerialItemIndex !== null ? this.form.items[this.currentSerialItemIndex] : null;
                },

                // Inline duplicate error for a serial row (mirrors Add Item's serial grid
                // treatment). Returns the EXACT existing duplicate message text.
                serialRowError(idx, value) {
                    if (!value || value.trim() === '') return '';
                    const item = this.currentSerialItem;
                    if (!item) return '';
                    const v = value.trim().toUpperCase();
                    const dupCount = (this.tempSerials || []).filter(s => s && s.trim().toUpperCase() === v).length;
                    if (dupCount > 1) {
                        return 'Duplicate serial numbers found for "' + item.item_name + '"';
                    }
                    return '';
                },

                async searchItems(query) {
                    if (query.length < 2) {
                        this.searchResults = [];
                        return;
                    }

                    if (!this.form.warehouse_from) {
                        showError('Please select a SOURCE warehouse first.');
                        this.searchQuery = '';
                        return;
                    }

                    fetch(`/stock/transfer/search-items?query=${query}&warehouse_id=${this.form.warehouse_from}`)
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
                            is_serialized: parseInt(item.is_serialized) || 0,
                            serials: [],
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

                    const qty = parseInt(item.quantity) || 0;
                    if (this.tempSerials.length < qty) {
                        for (let i = this.tempSerials.length; i < qty; i++) {
                            this.tempSerials.push('');
                        }
                    } else {
                        this.tempSerials = this.tempSerials.slice(0, qty);
                    }

                    this.$dispatch('open-modal', { name: 'transfer-serial-modal' });
                },

                saveSerials() {
                    if (this.currentSerialItemIndex !== null) {
                        const item = this.form.items[this.currentSerialItemIndex];
                        this.form.items[this.currentSerialItemIndex].serials = this.tempSerials.slice(0, item.quantity);
                        this.$dispatch('close-modal', { name: 'transfer-serial-modal' });
                        this.currentSerialItemIndex = null;
                        this.tempSerials = [];
                    }
                },

                async submitForm() {
                    if (this.isSubmitting) return;

                    if (!this.form.warehouse_from || !this.form.warehouse_to) {
                        showError('Please select both source and destination warehouses.');
                        return;
                    }
                    if (this.form.warehouse_from === this.form.warehouse_to) {
                        showError('Source and destination warehouses cannot be the same.');
                        return;
                    }
                    if (this.form.items.length === 0) {
                        showError('Please add at least one item to the transfer.');
                        return;
                    }

                    for (const item of this.form.items) {
                        if (item.quantity > item.stock) {
                            showError(`Insufficient stock for "${item.item_name}" (Available: ${item.stock})`);
                            return;
                        }

                        if (item.is_serialized) {
                            const filledSerials = item.serials.filter(s => s.trim() !== '');
                            if (filledSerials.length < item.quantity) {
                                showError(`Please fill all serial numbers for "${item.item_name}"`);
                                this.openSerialModal(this.form.items.indexOf(item));
                                return;
                            }
                        }
                    }

                    this.isSubmitting = true;
                    setButtonLoading(this.$refs.submitBtn, 'Saving...');

                    try {
                        const response = await fetch(`{{ route('stock.transfer.store', [], false) }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const result = await response.json();

                        if (result.success) {
                            showSuccess(result.message || 'Stock Transfer created successfully!');
                            setTimeout(() => { window.location.href = "{{ route('stock.transfer') }}"; }, 800);
                        } else {
                            showError(result.message || 'Failed to create transfer.');
                        }
                    } catch (error) {
                        console.error('Submit error:', error);
                        showError('An unexpected error occurred. Please try again.');
                    } finally {
                        this.isSubmitting = false;
                        resetButtonLoading(this.$refs.submitBtn);
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
