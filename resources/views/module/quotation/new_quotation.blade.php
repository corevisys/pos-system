<x-app-layout title="New Quotation">

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('quotationPage', () => ({
            quotationInfo: {
                warehouse_id: '',
                customer_id: '',
                quotationDate: '{{ date('Y-m-d') }}',
                expireDate: '{{ date('Y-m-d', strtotime('+7 days')) }}',
                referenceNo: '',
                note: '',
                other_charges_tax_id: ''
            },
            cart: [],
            searchResults: [],
            searchQuery: '',
            otherCharges: 0,
            otherChargesTaxAmount: 0,
            discountOnAll: 0,
            discountType: 'Fixed',
            roundOff: 0,
            isSubmitting: false,
            showQuickAdd: false,
            // Save-in-flight flag for the Quick Add Item modal. Kept separate from
            // isSubmitting (which drives the main Save Quotation button) so approving
            // a quick-added item never shows "Saving Quotation..." on the wrong button.
            // Mirrors the verified new_purchase.blade.php Quick Add fix.
            quickItemSaving: false,
            successMessage: '',
            errorMessage: '',
            errorDetails: [],
            showSuccessModal: false,
            showErrorModal: false,
            quickItem: {
                item_name: '',
                barcode: '',
                category_id: '',
                unit_id: '',
                tax_id: '',
                tax_type: 'Inclusive',
                purchase_price: 0,
                sales_price: 0,
                brand_id: '',
                sku: '',
                alert_qty: 0,
                description: '',
                custom_barcode: ''
            },
            taxRates: {
                @foreach($taxes as $tax)
                    '{{ $tax->id }}': {{ $tax->tax }},
                @endforeach
            },

            async searchItems() {
                if (this.searchQuery.length < 1) {
                    this.searchResults = [];
                    return;
                }
                try {
                    const response = await fetch(`{{ route('quotation.search.items', [], false) }}?q=${encodeURIComponent(this.searchQuery)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) throw new Error('Search failed');
                    this.searchResults = await response.json();
                    
                    if (this.searchResults.length === 1) {
                        this.addItem(this.searchResults[0]);
                    }
                } catch (e) {
                    console.error(e);
                    this.searchResults = [];
                }
            },
            
            addItem(item) {
                let existing = this.cart.find(i => i.item_id === item.id);
                if (existing) {
                    existing.qty++;
                } else {
                    this.cart.push({
                        item_id: item.id,
                        name: item.item_name,
                        qty: 1,
                        price: parseFloat(item.sales_price || item.price || 0),
                        discount: 0,
                        tax: item.tax ? parseFloat(item.tax.tax || 0) : 0,
                        tax_type: item.tax_type || 'Inclusive',
                        taxAmount: 0,
                        unitCost: parseFloat(item.sales_price || item.price || 0),
                        total: parseFloat(item.sales_price || item.price || 0)
                    });
                }
                this.searchQuery = '';
                this.searchResults = [];
                this.calculateTotals();
                this.$nextTick(() => {
                    if (this.$refs.searchInput) this.$refs.searchInput.focus();
                });
            },

            async handleEnterKey() {
                if (this.searchResults.length === 1) {
                    this.addItem(this.searchResults[0]);
                    return;
                }
                
                if (this.searchResults.length === 0 && this.searchQuery.length > 0) {
                    await this.searchItems();
                    if (this.searchResults.length === 1) {
                        this.addItem(this.searchResults[0]);
                    }
                }
                else if (this.searchResults.length > 0) {
                    this.addItem(this.searchResults[0]);
                }
            },
            
            removeItem(item_id) {
                this.cart = this.cart.filter(item => item.item_id !== item_id);
                this.calculateTotals();
            },
            
            calculateTotals() {
                this.cart.forEach(item => {
                    let price = parseFloat(item.price || 0);
                    let qty = parseFloat(item.qty || 0);
                    let sub = price * qty;
                    let disc = Math.min(parseFloat(item.discount || 0), sub);
                    let taxable = Math.max(0, sub - disc);
                    let taxRate = parseFloat(item.tax || 0);
                    let taxAmt = 0;
                    let total = 0;

                    if (item.tax_type === 'Inclusive') {
                        taxAmt = taxRate > 0 ? (taxable * taxRate) / (100 + taxRate) : 0;
                        total = taxable;
                    } else {
                        taxAmt = taxRate > 0 ? (taxable * taxRate) / 100 : 0;
                        total = taxable + taxAmt;
                    }

                    item.taxAmount = taxAmt;
                    item.total = total;
                    item.unitCost = qty > 0 ? (total / qty) : price;
                });

                if (this.quotationInfo.other_charges_tax_id && this.taxRates[this.quotationInfo.other_charges_tax_id]) {
                    let per = parseFloat(this.taxRates[this.quotationInfo.other_charges_tax_id]);
                    this.otherChargesTaxAmount = (parseFloat(this.otherCharges || 0) * per) / 100;
                } else {
                    this.otherChargesTaxAmount = 0;
                }
            },
            
            get subtotal() {
                return this.cart.reduce((sum, item) => sum + (parseFloat(item.qty || 0) * parseFloat(item.price || 0)), 0);
            },
            
            get totalDiscount() {
                let itemDisco = this.cart.reduce((sum, item) => sum + Math.min(parseFloat(item.discount || 0), parseFloat(item.qty || 0) * parseFloat(item.price || 0)), 0);
                let globalDisco = 0;
                let discInput = parseFloat(this.discountOnAll || 0);
                if (this.discountType === 'Fixed') {
                    globalDisco = discInput;
                } else {
                    globalDisco = (this.subtotal * discInput) / 100;
                }
                return itemDisco + globalDisco;
            },

            get totalTax() {
                 return this.cart.reduce((sum, item) => sum + (item.taxAmount || 0), 0) + (this.otherChargesTaxAmount || 0);
            },
            
            get grandTotal() {
                let itemTotals = this.cart.reduce((sum, item) => sum + (item.total || 0), 0);
                let globalDisco = 0;
                let discInput = parseFloat(this.discountOnAll || 0);
                if (this.discountType === 'Fixed') {
                    globalDisco = discInput;
                } else {
                    globalDisco = (this.subtotal * discInput) / 100;
                }
                globalDisco = Math.min(globalDisco, itemTotals);

                let total = itemTotals - globalDisco + parseFloat(this.otherCharges || 0) + parseFloat(this.otherChargesTaxAmount || 0) + parseFloat(this.roundOff || 0);
                return Math.max(0, total);
            },

            async saveQuotation() {
                if (this.isSubmitting) return;

                let errors = [];
                if (!this.quotationInfo.warehouse_id) errors.push('Warehouse selection is required');
                if (!this.quotationInfo.customer_id) errors.push('Customer selection is required');
                if (this.cart.length === 0) errors.push('Cart is empty. Please add at least one item');

                if (errors.length > 0) {
                    this.errorMessage = 'Required Fields Missing';
                    this.errorDetails = errors;
                    this.showErrorModal = true;
                    return;
                }

                this.isSubmitting = true;
                try {
                    const data = {
                        warehouse_id: this.quotationInfo.warehouse_id,
                        customer_id: this.quotationInfo.customer_id,
                        quotation_date: this.quotationInfo.quotationDate,
                        expire_date: this.quotationInfo.expireDate,
                        reference_no: this.quotationInfo.referenceNo,
                        note: this.quotationInfo.note,
                        other_charges_tax_id: this.quotationInfo.other_charges_tax_id,
                        cart: this.cart,
                        subtotal: this.subtotal,
                        other_charges_input: this.otherCharges,
                        other_charges_amt: this.otherChargesTaxAmount,
                        discount_type: this.discountType,
                        discount_on_all: this.discountOnAll,
                        round_off: this.roundOff,
                        grand_total: this.grandTotal,
                        _token: '{{ csrf_token() }}'
                    };

                    const response = await fetch('{{ route('quotation.store', [], false) }}', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (response.ok && result.success) {
                        this.successMessage = result.message || 'Quotation saved successfully!';
                        this.showSuccessModal = true;
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1000);
                    } else {
                        this.errorMessage = result.message || 'Submission Failed';
                        this.errorDetails = result.errors ? Object.values(result.errors).flat() : [result.message || 'Validation failed'];
                        this.showErrorModal = true;
                    }
                } catch (err) {
                    console.error(err);
                    this.errorMessage = 'Network Error';
                    this.errorDetails = ['Failed to communicate with server. Please try again.'];
                    this.showErrorModal = true;
                } finally {
                    this.isSubmitting = false;
                }
            },

            async submitQuickItem() {
                // Re-entry guard: never fire two quick-add requests from a double-click
                // (the Save button is also :disabled while saving).
                if (this.quickItemSaving) return;

                let errors = [];
                if (!this.quickItem.item_name) errors.push('Item Name is required');
                if (!this.quickItem.category_id) errors.push('Category is required');
                if (!this.quickItem.unit_id) errors.push('Unit is required');
                if (!this.quickItem.tax_id) errors.push('Tax is required');
                if (!this.quickItem.sales_price || parseFloat(this.quickItem.sales_price) < 0) errors.push('Valid Sales Price is required');

                if (errors.length > 0) {
                    this.errorMessage = 'Quick Add Failed';
                    this.errorDetails = errors;
                    this.showErrorModal = true;
                    return;
                }

                this.quickItemSaving = true;

                try {
                    const response = await fetch('{{ route('purchase.quick.item.store', [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ...this.quickItem,
                            purchase_price: parseFloat(this.quickItem.purchase_price || 0),
                            sales_price: parseFloat(this.quickItem.sales_price || 0),
                            _token: '{{ csrf_token() }}'
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        this.addItem(result.item);
                        this.showQuickAdd = false;

                        this.successMessage = result.message || 'Item added successfully!';
                        this.showSuccessModal = true;
                        setTimeout(() => this.showSuccessModal = false, 3000);

                        this.quickItem = {
                            item_name: '',
                            barcode: '',
                            category_id: '',
                            unit_id: '',
                            tax_id: '',
                            tax_type: 'Inclusive',
                            purchase_price: 0,
                            sales_price: 0,
                            brand_id: '',
                            sku: '',
                            alert_qty: 0,
                            description: '',
                            custom_barcode: ''
                        };
                    } else {
                        this.errorMessage = result.message || 'Validation Error';
                        this.errorDetails = result.errors ? Object.values(result.errors).flat() : [result.message || 'Error saving item.'];
                        this.showErrorModal = true;
                    }
                } catch (error) {
                    console.error('Quick Add Error:', error);
                    this.errorMessage = 'System Error';
                    this.errorDetails = ['An error occurred while saving the item.'];
                    this.showErrorModal = true;
                } finally {
                    // Reset on BOTH success and failure paths so the button
                    // re-enables and the spinner clears.
                    this.quickItemSaving = false;
                }
            }
        }));
    });
</script>

    <div x-data="quotationPage" class="space-y-4">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">New Quotation</h1>
                <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('quotation.list') }}" class="hover:text-primary transition-colors font-bold">Quotations</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-bold">New</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('quotation.list') }}" class="btn-secondary px-3.5 py-1.5 text-xs font-bold flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button 
                    type="button"
                    @click="saveQuotation()" 
                    :disabled="isSubmitting" 
                    class="btn-primary px-4 py-1.5 text-xs font-bold flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="!isSubmitting">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            <span>Save Quotation</span>
                        </div>
                    </template>
                    <template x-if="isSubmitting">
                        <div class="flex items-center gap-1.5">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Saving...</span>
                        </div>
                    </template>
                </button>
            </div>
        </div>

        <!-- SECTION 1: QUOTATION INFO (Compact Header Strip) -->
        <div class="card p-3 md:p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Warehouse -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Warehouse <span class="text-danger">*</span></label>
                    <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="quotationInfo.warehouse_id" />
                </div>

                <!-- Customer -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Customer <span class="text-danger">*</span></label>
                    <x-searchable-select :options="$customers" labelKey="customer_name" valueKey="id" placeholder="Select Customer" model="quotationInfo.customer_id" />
                </div>

                <!-- Quotation Date -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Quotation Date <span class="text-danger">*</span></label>
                    <input type="date" x-model="quotationInfo.quotationDate" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Expire Date</label>
                    <input type="date" x-model="quotationInfo.expireDate" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                </div>

                <!-- Reference No -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Reference No</label>
                    <input type="text" x-model="quotationInfo.referenceNo" placeholder="Ref: #REF-001" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                </div>
            </div>
        </div>

        <!-- SECTION 2: ITEM SEARCH & CART TABLE -->
        <div class="card p-0 overflow-hidden">
            <!-- Search & Quick Add Bar -->
            <div class="p-3 border-b border-border-light dark:border-dark-border bg-background dark:bg-dark-bg/50">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 group">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary dark:text-dark-text/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input 
                            type="text" 
                            x-model="searchQuery" 
                            x-ref="searchInput"
                            @input.debounce.150ms="searchItems()"
                            @keydown.enter.prevent="handleEnterKey()"
                            placeholder="Scan barcode or search by item name / code / SKU..." 
                            class="input-base !py-2 !pl-9 !pr-4 !text-xs !rounded-xl w-full">
                        
                        <!-- Search Results Dropdown -->
                        <div 
                            x-show="searchResults.length > 0" 
                            class="absolute z-50 w-full mt-1.5 bg-white dark:bg-dark-card border border-border-light dark:border-dark-border rounded-xl shadow-xl max-h-60 overflow-y-auto"
                            @click.away="searchResults = []"
                            x-cloak>
                            <template x-for="item in searchResults" :key="item.id">
                                <div @click="addItem(item)" class="px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors border-b border-border-light dark:border-dark-border last:border-none flex items-center justify-between gap-3">
                                    <div class="flex-1">
                                        <p class="text-xs font-black text-text-primary dark:text-dark-text">
                                            <span x-text="item.item_code" class="text-primary font-mono mr-1"></span>
                                            <span x-text="item.item_name"></span>
                                        </p>
                                    </div>
                                    <p class="text-xs font-black text-primary tabular-nums">
                                        {{ $currencySymbol }}<span x-text="parseFloat(item.sales_price || item.price || 0).toFixed(2)"></span>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        @click="showQuickAdd = true" 
                        class="btn-secondary !py-2 !px-3 text-xs font-bold flex items-center gap-1.5 shrink-0" 
                        title="Quick Add Product">
                        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Add Item</span>
                    </button>
                </div>
            </div>

            <!-- Cart Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg/50 border-b border-border-light dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest">Item Name</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center w-24">Qty</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right w-32">Price ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center w-24">Disc ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Tax Amt ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Unit Cost ({{ $currencySymbol }})</th>
                            <th class="px-4 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-right">Subtotal ({{ $currencySymbol }})</th>
                            <th class="px-3 py-2 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center w-12">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        <template x-if="cart.length === 0">
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-xs text-text-secondary italic">
                                    No items in quotation cart. Search above or click "+ Add Item" to add items.
                                </td>
                            </tr>
                        </template>
                        <template x-for="item in cart" :key="item.item_id">
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-dark-bg/40 transition-colors">
                                <td class="px-4 py-2">
                                    <p class="text-xs font-bold text-text-primary dark:text-dark-text" x-text="item.name"></p>
                                    <span class="text-[9px] text-text-secondary font-mono" x-text="item.tax_type + ' Tax (' + item.tax + '%)'"></span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input 
                                        type="number" 
                                        x-model="item.qty" 
                                        @input="calculateTotals()" 
                                        min="0.01" 
                                        step="any"
                                        class="input-base !w-16 !py-1 !px-2 !text-xs !text-center font-bold">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input 
                                        type="number" 
                                        x-model="item.price" 
                                        @input="calculateTotals()" 
                                        min="0" 
                                        step="any"
                                        class="input-base !w-24 !py-1 !px-2 !text-xs !text-right font-bold text-primary">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input 
                                        type="number" 
                                        x-model="item.discount" 
                                        @input="calculateTotals()" 
                                        min="0" 
                                        step="any"
                                        class="input-base !w-16 !py-1 !px-2 !text-xs !text-center font-bold text-danger">
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-xs text-text-secondary" x-text="item.taxAmount.toFixed(2)"></td>
                                <td class="px-3 py-2 text-center tabular-nums text-xs font-bold text-text-primary dark:text-dark-text" x-text="item.unitCost.toFixed(2)"></td>
                                <td class="px-4 py-2 text-right tabular-nums text-xs font-black text-emerald-600 dark:text-emerald-400" x-text="item.total.toFixed(2)"></td>
                                <td class="px-3 py-2 text-center">
                                    <button 
                                        type="button"
                                        @click="removeItem(item.item_id)" 
                                        class="p-1 text-danger hover:bg-danger-light dark:hover:bg-danger/10 rounded-lg transition-colors" 
                                        title="Remove Line">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: BOTTOM GRID (NOTES & SUMMARY) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Left: Notes & Adjustments (2 columns) -->
            <div class="lg:col-span-2 card p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Other Charges & Tax -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Other Charges</label>
                        <div class="flex gap-2">
                            <input 
                                type="number" 
                                x-model="otherCharges" 
                                @input="calculateTotals()" 
                                min="0" 
                                step="any"
                                class="input-base !py-1.5 !px-3 !text-xs !rounded-xl flex-1">
                            <select 
                                x-model="quotationInfo.other_charges_tax_id" 
                                @change="calculateTotals()" 
                                class="input-base !py-1.5 !px-2 !text-xs !rounded-xl w-36">
                                <option value="">No Tax</option>
                                @foreach($taxes as $tax)
                                    <option value="{{ $tax->id }}">{{ $tax->tax_name }} ({{ $tax->tax }}%)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Discount on All -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Discount on All</label>
                        <div class="flex gap-2">
                            <input 
                                type="number" 
                                x-model="discountOnAll" 
                                min="0" 
                                step="any"
                                class="input-base !py-1.5 !px-3 !text-xs !rounded-xl flex-1">
                            <select 
                                x-model="discountType" 
                                class="input-base !py-1.5 !px-2 !text-xs !rounded-xl w-24 font-bold">
                                <option value="Fixed">{{ $currencySymbol }}</option>
                                <option value="Percentage">%</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Quotation Note -->
                <div>
                    <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Quotation Notes / Terms</label>
                    <textarea 
                        x-model="quotationInfo.note" 
                        rows="2" 
                        placeholder="Additional remarks, payment terms, or instructions for the customer..." 
                        class="input-base !py-2 !px-3 !text-xs !rounded-xl w-full"></textarea>
                </div>
            </div>

            <!-- Right: Calculation Summary Sidebar (1 column) -->
            <div class="card p-4 space-y-3 bg-slate-900 text-white dark:bg-dark-card border-none shadow-md">
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-white/10 dark:border-dark-border">
                        <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold">Subtotal</span>
                        <span class="font-bold tabular-nums">{{ $currencySymbol }}<span x-text="subtotal.toFixed(2)"></span></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-white/10 dark:border-dark-border">
                        <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold">Other Charges</span>
                        <span class="font-bold tabular-nums">{{ $currencySymbol }}<span x-text="(parseFloat(otherCharges || 0) + parseFloat(otherChargesTaxAmount || 0)).toFixed(2)"></span></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-white/10 dark:border-dark-border text-rose-400">
                        <span class="uppercase tracking-wider text-[10px] font-bold">Total Discount</span>
                        <span class="font-bold tabular-nums">- {{ $currencySymbol }}<span x-text="totalDiscount.toFixed(2)"></span></span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-b border-white/10 dark:border-dark-border">
                        <span class="text-slate-400 uppercase tracking-wider text-[10px] font-bold">Round Off</span>
                        <input 
                            type="number" 
                            x-model="roundOff" 
                            step="any"
                            class="w-16 bg-white/10 border-none rounded py-0.5 px-1.5 text-xs text-right font-bold text-white outline-none focus:ring-1 focus:ring-primary-400">
                    </div>

                    <div class="pt-2 flex justify-between items-baseline">
                        <span class="text-primary-400 uppercase tracking-widest font-black text-xs">Grand Total</span>
                        <span class="text-2xl font-black text-white tabular-nums tracking-tight">{{ $currencySymbol }}<span x-text="grandTotal.toFixed(2)"></span></span>
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="button"
                        @click="saveQuotation()" 
                        :disabled="isSubmitting" 
                        class="btn-primary w-full !py-2.5 text-xs font-black uppercase tracking-widest shadow-sm disabled:opacity-50">
                        <span x-text="isSubmitting ? 'Saving Quotation...' : 'Save & View Invoice'"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- QUICK ADD ITEM MODAL (Compact 2-Column Grid) -->
        <div 
            x-show="showQuickAdd" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            style="display: none;"
            x-cloak>
            <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5 space-y-4 shadow-2xl" @click.away="if(!quickItemSaving) showQuickAdd = false">
                <div class="flex justify-between items-center pb-3 border-b border-border-light dark:border-dark-border">
                    <div>
                        <h3 class="text-base font-black text-text-primary dark:text-dark-text">Quick Add Item</h3>
                        <p class="text-[10px] text-text-secondary uppercase tracking-wider">Fast catalog item registration</p>
                    </div>
                    <button type="button" @click="showQuickAdd = false" class="text-text-secondary hover:text-danger">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                    <!-- Item Name (Full Width) -->
                    <div class="sm:col-span-2">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Item Name <span class="text-danger">*</span></label>
                        <input type="text" x-model="quickItem.item_name" placeholder="Item Name" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Category <span class="text-danger">*</span></label>
                        <x-searchable-select :options="$categories" labelKey="category_name" valueKey="id" placeholder="Select Category" model="quickItem.category_id" />
                    </div>

                    <!-- Unit -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Unit <span class="text-danger">*</span></label>
                        <x-searchable-select :options="$units" labelKey="unit_name" valueKey="id" placeholder="Select Unit" model="quickItem.unit_id" />
                    </div>

                    <!-- Tax -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Tax <span class="text-danger">*</span></label>
                        <x-searchable-select :options="$taxes" labelKey="tax_name" valueKey="id" placeholder="Select Tax" model="quickItem.tax_id" />
                    </div>

                    <!-- Tax Type -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Tax Type <span class="text-danger">*</span></label>
                        <select x-model="quickItem.tax_type" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full font-bold">
                            <option value="Inclusive">Inclusive</option>
                            <option value="Exclusive">Exclusive</option>
                        </select>
                    </div>

                    <!-- Purchase Price -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Purchase Price</label>
                        <input type="number" x-model="quickItem.purchase_price" min="0" step="any" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Sales Price -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Sales Price <span class="text-danger">*</span></label>
                        <input type="number" x-model="quickItem.sales_price" min="0" step="any" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full text-primary font-bold">
                    </div>

                    <!-- Brand -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Brand</label>
                        <x-searchable-select :options="$brands" labelKey="brand_name" valueKey="id" placeholder="Select Brand" model="quickItem.brand_id" />
                    </div>

                    <!-- SKU -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">SKU</label>
                        <input type="text" x-model="quickItem.sku" placeholder="SKU Code" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Barcode / Custom Barcode</label>
                        <input type="text" x-model="quickItem.custom_barcode" placeholder="Barcode digits" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Alert Quantity -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Alert Quantity</label>
                        <input type="number" x-model="quickItem.alert_qty" min="0" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                </div>

                <div class="flex justify-end items-center gap-2 pt-3 border-t border-border-light dark:border-dark-border">
                    <button type="button" @click="showQuickAdd = false" class="btn-secondary !py-1.5 !px-3 text-xs font-bold">
                        Cancel
                    </button>
                    <button type="button" @click="submitQuickItem()" :disabled="quickItemSaving" class="btn-primary !py-1.5 !px-4 text-xs font-bold flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="quickItemSaving">
                            <div class="flex items-center gap-1.5">
                                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>Saving...</span>
                            </div>
                        </template>
                        <template x-if="!quickItemSaving">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>Save Item</span>
                            </div>
                        </template>
                    </button>
                </div>
            </div>
        </div>

        <!-- ERROR FEEDBACK MODAL -->
        <div 
            x-show="showErrorModal" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            style="display: none;"
            x-cloak>
            <div class="card w-full max-w-sm p-5 space-y-3 text-center shadow-2xl" @click.away="showErrorModal = false">
                <div class="w-10 h-10 rounded-full bg-danger-light text-danger flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-sm font-black text-text-primary dark:text-dark-text" x-text="errorMessage"></h3>
                <ul class="text-xs text-danger text-left list-disc list-inside space-y-1 bg-danger-light/30 p-2.5 rounded-xl" x-show="errorDetails.length > 0">
                    <template x-for="err in errorDetails" :key="err">
                        <li x-text="err"></li>
                    </template>
                </ul>
                <button type="button" @click="showErrorModal = false" class="btn-secondary w-full !py-1.5 text-xs font-bold">
                    Dismiss
                </button>
            </div>
        </div>

        <!-- SUCCESS FEEDBACK MODAL -->
        <div 
            x-show="showSuccessModal" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            style="display: none;"
            x-cloak>
            <div class="card w-full max-w-sm p-5 space-y-3 text-center shadow-2xl">
                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="text-sm font-black text-text-primary dark:text-dark-text" x-text="successMessage"></h3>
            </div>
        </div>

    </div>
</x-app-layout>