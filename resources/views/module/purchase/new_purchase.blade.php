<x-app-layout title="New Purchase">

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('purchasePage', () => ({
            purchaseInfo: {
                warehouse_id: '',
                supplier_id: '',
                purchaseDate: '{{ date('Y-m-d') }}',
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
            amount_paid: 0,
            payment_type: 'Cash',
            account_id: '',
            isSubmitting: false,
            showQuickAdd: false,
            quickItem: {
                item_name: '',
                barcode: '',
                category_id: '',
                unit_id: '',
                tax_id: '',
                purchase_price: 0,
                sales_price: 0,
                brand_id: '',
                sku: '',
                alert_qty: 0,
                description: '',
                tax_type: 'Inclusive',
                custom_barcode: '',
                is_serialized: 0,
                opening_stock: 0,
                serial_numbers: [],
                warehouse_id: ''
            },
            
            // Notification State
            successMessage: '',
            errorMessage: '',
            errorDetails: [],
            showSuccessModal: false,
            showErrorModal: false,

            // Quick Modals
            showBrandModal: false,
            showCategoryModal: false,
            showUnitModal: false,
            showTaxModal: false,
            showSupplierModal: false,

            serialModal: {
                show: false,
                item_id: null,
                item_name: '',
                tempSerials: '',
                currentIndex: -1,
                warnings: []
            },

            taxRates: {
                @foreach($taxes as $tax)
                    '{{ $tax->id }}': {{ $tax->tax }},
                @endforeach
            },
            taxesList: @json($taxes),

            init() {
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                    this.calculateTotals();
                });
            },

            updateQuickSerials() {
                const qty = parseInt(this.quickItem.opening_stock) || 0;
                if (qty > 0) {
                    const currentCount = this.quickItem.serial_numbers.length;
                    if (qty > currentCount) {
                        for (let i = currentCount; i < qty; i++) {
                            this.quickItem.serial_numbers.push('');
                        }
                    } else if (qty < currentCount) {
                        this.quickItem.serial_numbers = this.quickItem.serial_numbers.slice(0, qty);
                    }
                } else {
                    this.quickItem.serial_numbers = [];
                }
            },

            async submitQuickAdd(event, selectName, modalFlag) {
                const form = event.target;

                if (!form.checkValidity()) {
                    let errors = [];
                    form.querySelectorAll(':invalid').forEach(field => {
                        let label = field.closest('.group')?.querySelector('label')?.innerText || 
                                    field.closest('.relative')?.querySelector('label')?.innerText || 
                                    field.placeholder || 
                                    field.name;
                        label = label.replace('*', '').trim();
                        errors.push(`[${label}] is required`);
                    });
                    
                    if (errors.length > 0) {
                        this.errorMessage = 'Missing Information';
                        this.errorDetails = errors;
                        this.showErrorModal = true;
                        return;
                    }
                }

                const formData = new FormData(form);
                const url = form.action;

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        this[modalFlag] = false;
                        
                        if (selectName === 'supplier_id') {
                            this.purchaseInfo.supplier_id = data.supplier.id;
                        } else if (selectName === 'brand_id') {
                            this.quickItem.brand_id = data.brand.id;
                        } else if (selectName === 'category_id') {
                            this.quickItem.category_id = data.category.id;
                        } else if (selectName === 'unit_id') {
                            this.quickItem.unit_id = data.unit.id;
                        } else if (selectName === 'tax_id') {
                            this.quickItem.tax_id = data.tax.id;
                            this.taxRates[data.tax.id] = data.tax.tax;
                        }

                        this.successMessage = data.message || 'Saved successfully!';
                        this.showSuccessModal = true;
                        setTimeout(() => this.showSuccessModal = false, 3000);
                        form.reset();
                    } else {
                        this.errorMessage = data.message || 'Save Failed';
                        this.errorDetails = data.errors ? Object.values(data.errors).flat() : [data.message || 'Unknown error'];
                        this.showErrorModal = true;
                    }
                } catch (error) {
                    this.errorMessage = 'Network Error';
                    this.errorDetails = ['Could not connect to server. Please try again.'];
                    this.showErrorModal = true;
                }
            },

            async submitQuickItem() {
                let errors = [];
                if (!this.quickItem.item_name) errors.push('Item Name is required');
                if (!this.quickItem.category_id) errors.push('Category is required');
                if (!this.quickItem.unit_id) errors.push('Unit is required');
                if (!this.quickItem.tax_id) errors.push('Tax is required');
                if (!this.quickItem.purchase_price || parseFloat(this.quickItem.purchase_price) < 0) errors.push('Valid Purchase Price is required');
                if (!this.quickItem.sales_price || parseFloat(this.quickItem.sales_price) < 0) errors.push('Valid Sales Price is required');

                if (this.quickItem.is_serialized && parseInt(this.quickItem.opening_stock) > 0) {
                    const filledSerials = this.quickItem.serial_numbers.filter(s => s.trim() !== '').length;
                    if (filledSerials !== parseInt(this.quickItem.opening_stock)) {
                        errors.push(`Please provide all ${this.quickItem.opening_stock} serial numbers (only ${filledSerials} entered).`);
                    }
                    if (!this.quickItem.warehouse_id) {
                        errors.push('Warehouse is required when entering opening stock.');
                    }
                }

                if (errors.length > 0) {
                    this.errorMessage = 'Validation Errors';
                    this.errorDetails = errors;
                    this.showErrorModal = true;
                    return;
                }

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
                            opening_stock: parseInt(this.quickItem.opening_stock || 0),
                            _token: '{{ csrf_token() }}'
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        this.addItem(result.item);
                        this.showQuickAdd = false;
                        
                        this.successMessage = result.message || 'Item created and added to cart!';
                        this.showSuccessModal = true;
                        setTimeout(() => this.showSuccessModal = false, 3000);

                        this.quickItem = {
                            item_name: '', barcode: '', category_id: '', unit_id: '', tax_id: '',
                            purchase_price: 0, sales_price: 0, brand_id: '', sku: '', alert_qty: 0,
                            description: '', tax_type: 'Inclusive', custom_barcode: '', is_serialized: 0,
                            opening_stock: 0, serial_numbers: [], warehouse_id: ''
                        };
                    } else {
                        this.errorMessage = result.message || 'Item Creation Failed';
                        this.errorDetails = result.errors ? Object.values(result.errors).flat() : [result.message || 'Error occurred.'];
                        this.showErrorModal = true;
                    }
                } catch (error) {
                    console.error('Quick Add Item Error:', error);
                    this.errorMessage = 'System Exception';
                    this.errorDetails = ['Failed to communicate with server. Check connectivity.'];
                    this.showErrorModal = true;
                }
            },

            async searchItems() {
                if (this.searchQuery.length < 1) {
                    this.searchResults = [];
                    return;
                }
                try {
                    const response = await fetch(`{{ route('purchase.search.items', [], false) }}?q=${encodeURIComponent(this.searchQuery)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) throw new Error('Search failed');
                    this.searchResults = await response.json();
                    
                    if (this.searchResults.length === 1 && this.searchQuery.trim() === this.searchResults[0].barcode) {
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
                    this.updateItemSerials(existing);
                } else {
                    const taxRate = item.tax ? parseFloat(item.tax.tax || 0) : 0;
                    this.cart.push({
                        item_id: item.id,
                        name: item.item_name,
                        item_code: item.item_code || '',
                        qty: 1,
                        price: parseFloat(item.purchase_price || item.price || 0),
                        discount: 0,
                        discount_type: 'Fixed',
                        tax: taxRate,
                        tax_id: item.tax_id || '',
                        tax_type: item.tax_type || 'Inclusive',
                        taxAmount: 0,
                        unitCost: parseFloat(item.purchase_price || item.price || 0),
                        total: parseFloat(item.purchase_price || item.price || 0),
                        is_serialized: item.is_serialized || 0,
                        serials: []
                    });
                    let added = this.cart[this.cart.length - 1];
                    this.updateItemSerials(added);
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
            },
            
            removeItem(item_id) {
                this.cart = this.cart.filter(item => item.item_id !== item_id);
                this.calculateTotals();
            },

            openSerialModal(item, index) {
                this.serialModal.item_id = item.item_id;
                this.serialModal.item_name = item.name;
                this.serialModal.currentIndex = index;
                
                this.updateItemSerials(item);
                this.serialModal.tempSerials = item.serials.join('\n');
                this.refreshSerialWarnings();
                this.serialModal.show = true;
            },

            updateItemSerials(item) {
                const qty = parseInt(item.qty) || 0;
                if (qty > 0) {
                    const currentCount = item.serials.length;
                    if (qty > currentCount) {
                        for (let i = currentCount; i < qty; i++) {
                            item.serials.push('');
                        }
                    } else if (qty < currentCount) {
                        item.serials = item.serials.slice(0, qty);
                    }
                } else {
                    item.serials = [];
                }
            },

            saveSerials() {
                const dups = this.duplicateSerialsInModal();
                if (dups.length > 0) {
                    showError('Duplicate serial numbers entered for this item. Please correct them before applying.');
                    this.refreshSerialWarnings();
                    return;
                }
                if (this.serialModal.currentIndex !== -1) {
                    const item = this.cart[this.serialModal.currentIndex];
                    const sns = this.serialModal.tempSerials.split(/[\n,]/).map(s => s.trim()).filter(s => s !== '');
                    
                    if (sns.length > 0) {
                        for(let i = 0; i < item.qty; i++) {
                            if (sns[i]) item.serials[i] = sns[i];
                        }
                    }
                    this.calculateTotals();
                }
                this.serialModal.show = false;
            },

            // Item 4 (client-side feedback): compute which serials in the current
            // serial-entry modal are duplicates WITHIN this submission (same value
            // typed into two slots). Wired into the submit guard + modal hints.
            duplicateSerialsInModal() {
                const sns = this.serialModal.tempSerials.split(/[\n,]/).map(s => s.trim()).filter(s => s !== '');
                const seen = new Map();
                const dups = new Set();
                sns.forEach(s => {
                    if (seen.has(s)) dups.add(s);
                    else seen.set(s, true);
                });
                return [...dups];
            },
            refreshSerialWarnings() {
                // Existing serials already registered for this item are surfaced by
                // the authoritative server-side check at submit; the modal shows only
                // the intra-submission duplicates live (no extra per-keystroke HTTP).
                this.serialModal.warnings = this.duplicateSerialsInModal();
            },
            
            calculateTotals() {
                this.cart.forEach(item => {
                    if (item.tax_id && this.taxRates[item.tax_id] !== undefined) {
                        item.tax = parseFloat(this.taxRates[item.tax_id]);
                    } else if (!item.tax_id) {
                        item.tax = 0;
                    }

                    const qty = parseFloat(item.qty || 0);
                    const price = parseFloat(item.price || 0);
                    const discount = parseFloat(item.discount || 0);
                    const taxRate = parseFloat(item.tax || 0);
                    
                    const subtotalBeforeTax = qty * price;
                    const amountAfterDiscount = subtotalBeforeTax - discount;

                    if (item.tax_type === 'Inclusive') {
                        item.taxAmount = amountAfterDiscount - (amountAfterDiscount / (1 + (taxRate / 100)));
                        item.total = amountAfterDiscount;
                    } else {
                        item.taxAmount = (amountAfterDiscount * taxRate) / 100;
                        item.total = amountAfterDiscount + item.taxAmount;
                    }
                    item.unitCost = (item.total / (qty || 1)) || 0;
                });

                if (this.purchaseInfo.other_charges_tax_id && this.taxRates[this.purchaseInfo.other_charges_tax_id]) {
                    const per = parseFloat(this.taxRates[this.purchaseInfo.other_charges_tax_id]);
                    this.otherChargesTaxAmount = (parseFloat(this.otherCharges || 0) * per) / 100;
                } else {
                    this.otherChargesTaxAmount = 0;
                }

                this.$nextTick(() => {
                    this.amount_paid = this.grandTotal;
                });
            },
            
            get subtotal() {
                return this.cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
            },

            get cartTotal() {
                return this.cart.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
            },
            
            get globalDiscount() {
                if (this.discountType === 'Percentage') {
                    return (this.subtotal * parseFloat(this.discountOnAll || 0)) / 100;
                }
                return parseFloat(this.discountOnAll || 0);
            },

            get totalDiscount() {
                let itemDisco = this.cart.reduce((sum, item) => sum + parseFloat(item.discount || 0), 0);
                return itemDisco + this.globalDiscount;
            },

            get totalTax() {
                return this.cart.reduce((sum, item) => sum + (parseFloat(item.taxAmount) || 0), 0) + this.otherChargesTaxAmount;
            },
            
            get grandTotal() {
                const total = (this.cartTotal - this.globalDiscount) + parseFloat(this.otherCharges || 0) + this.otherChargesTaxAmount + parseFloat(this.roundOff || 0);
                return total > 0 ? total : 0;
            },

            async savePurchase() {
                if (this.isSubmitting) return;

                let errors = [];
                if (!this.purchaseInfo.warehouse_id) errors.push('Destination Warehouse selection is required');
                if (!this.purchaseInfo.supplier_id) errors.push('Supplier selection is required');
                if (this.cart.length === 0) errors.push('Purchase Cart is empty. Please scan or select at least one item');
                if (parseFloat(this.amount_paid || 0) > 0 && !this.account_id) {
                    errors.push('Please select a Bank / Cash Account when entering a paid amount');
                }

                // Validate serialized items
                this.cart.forEach(item => {
                    if (item.is_serialized == 1) {
                        const filledSerials = item.serials.filter(s => s && s.trim() !== '').length;
                        if (filledSerials < item.qty) {
                            errors.push(`Item "${item.name}" requires ${item.qty} serials (only ${filledSerials} entered)`);
                        }
                    }
                });

                if (errors.length > 0) {
                    this.errorMessage = 'Please Complete Required Fields';
                    this.errorDetails = errors;
                    this.showErrorModal = true;
                    return;
                }

                this.isSubmitting = true;

                const data = {
                    warehouse_id: this.purchaseInfo.warehouse_id,
                    supplier_id: this.purchaseInfo.supplier_id,
                    purchase_date: this.purchaseInfo.purchaseDate,
                    reference_no: this.purchaseInfo.referenceNo,
                    note: this.purchaseInfo.note,
                    other_charges_tax_id: this.purchaseInfo.other_charges_tax_id,
                    cart: this.cart,
                    subtotal: this.subtotal,
                    other_charges_input: parseFloat(this.otherCharges || 0),
                    other_charges_amt: parseFloat(this.otherChargesTaxAmount || 0),
                    discount_type: this.discountType,
                    discount_on_all: parseFloat(this.discountOnAll || 0),
                    round_off: parseFloat(this.roundOff || 0),
                    grand_total: this.grandTotal,
                    amount_paid: parseFloat(this.amount_paid || 0),
                    payment_type: this.payment_type,
                    account_id: this.account_id,
                    _token: '{{ csrf_token() }}'
                };

                try {
                    const response = await fetch('{{ route('purchase.store', [], false) }}', {
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
                        this.successMessage = result.message || 'Purchase saved successfully!';
                        this.showSuccessModal = true;
                        setTimeout(() => {
                            window.location.href = result.redirect || '{{ route('purchase.list') }}';
                        }, 1000);
                    } else {
                        this.errorMessage = result.message || 'Submission Failed';
                        this.errorDetails = result.errors ? Object.values(result.errors).flat() : [result.message || 'Validation failed'];
                        this.showErrorModal = true;
                    }
                } catch (error) {
                    console.error('Save Purchase Error:', error);
                    this.errorMessage = 'System Error';
                    this.errorDetails = ['An unexpected error occurred while saving.'];
                    this.showErrorModal = true;
                } finally {
                    this.isSubmitting = false;
                }
            }
        }));
    });
</script>

<div class="space-y-3 pb-8" x-data="purchasePage">

    <!-- COMPACT HEADER BAR -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-primary-600"></span>
                New Purchase Entry
            </h1>
            <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                <a href="{{ route('purchase.list') }}" class="hover:text-primary transition-colors font-bold">Purchases</a>
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                <span class="text-text-primary dark:text-dark-text font-bold">Create</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('purchase.list') }}" class="btn-secondary !py-1.5 !px-3 text-xs font-bold flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                Cancel
            </a>
            <button 
                type="button" 
                @click="savePurchase()"
                :disabled="isSubmitting"
                class="btn-primary !py-1.5 !px-4 text-xs font-bold flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <template x-if="isSubmitting">
                    <div class="flex items-center gap-1.5">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Saving...</span>
                    </div>
                </template>
                <template x-if="!isSubmitting">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        <span>Save Purchase</span>
                    </div>
                </template>
            </button>
        </div>
    </div>

    <!-- MAIN FORM GRID -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

        <!-- LEFT COLUMN: INPUTS & CART -->
        <div class="xl:col-span-8 space-y-3">

            <!-- SECTION 1: SUPPLIER & LOCATION (Single Compact Bar on Desktop) -->
            <div class="card p-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    <!-- Warehouse -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Warehouse <span class="text-danger">*</span>
                        </label>
                        <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="purchaseInfo.warehouse_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Supplier Name + Quick Add Button -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">
                                Supplier <span class="text-danger">*</span>
                            </label>
                            <button type="button" @click="showSupplierModal = true" class="text-[9px] font-black text-primary hover:underline">+ New</button>
                        </div>
                        <x-searchable-select :options="$suppliers" labelKey="supplier_name" valueKey="id" subtextKey="mobile" placeholder="Select Supplier" model="purchaseInfo.supplier_id" quickAddClick="showSupplierModal = true" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Purchase Date -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Purchase Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" x-model="purchaseInfo.purchaseDate" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full font-bold">
                    </div>

                    <!-- Reference / Bill No -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Bill / Ref No
                        </label>
                        <input type="text" x-model="purchaseInfo.referenceNo" placeholder="Ref: #INV-001" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                </div>
            </div>

            <!-- SECTION 2: ITEM SEARCH & CART TABLE -->
            <div class="card p-0 overflow-hidden">
                <!-- Search & Quick Add Bar -->
                <div class="p-2.5 border-b border-border-light dark:border-dark-border bg-background dark:bg-dark-bg/50">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1 group" @click.away="searchResults = []">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary dark:text-dark-text/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                            </div>
                            <input 
                                type="text"
                                x-model="searchQuery"
                                x-ref="searchInput"
                                @input.debounce.120ms="searchItems()"
                                @keydown.enter.prevent="handleEnterKey()"
                                placeholder="Scan barcode or search by item name / code / SKU..."
                                class="input-base !py-2 !pl-9 !pr-8 !text-xs !rounded-xl w-full">

                            <template x-if="searchQuery.length > 0">
                                <button type="button" @click="searchQuery = ''; searchResults = []; $refs.searchInput.focus()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-danger">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </template>

                            <!-- Autocomplete Dropdown -->
                            <div 
                                x-show="searchResults.length > 0 || searchQuery.length >= 1"
                                class="absolute z-40 w-full mt-1.5 bg-white dark:bg-dark-card border border-border-light dark:border-dark-border rounded-xl shadow-xl max-h-60 overflow-y-auto divide-y divide-border-light dark:divide-dark-border"
                                x-cloak
                                style="display: none;">
                                
                                <template x-for="item in searchResults" :key="item.id">
                                    <div @click="addItem(item)" class="px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-black text-text-primary dark:text-dark-text truncate">
                                                <span class="text-primary font-mono mr-1" x-text="item.item_code"></span>
                                                <span x-text="item.item_name"></span>
                                            </p>
                                            <p class="text-[9px] text-text-secondary mt-0.5 font-bold uppercase tracking-wider">
                                                <span x-text="item.category ? item.category.category_name : 'General'"></span>
                                                <span class="mx-1">•</span>
                                                Stock: <span :class="(item.stock || 0) > 0 ? 'text-success' : 'text-danger'" x-text="item.stock || 0"></span>
                                            </p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-xs font-black text-primary tabular-nums">
                                                {{ $currencySymbol }}<span x-text="parseFloat(item.purchase_price || item.price || 0).toFixed(2)"></span>
                                            </p>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="searchResults.length === 0 && searchQuery.length >= 1">
                                    <div class="p-3 text-center text-xs text-text-secondary italic">
                                        No item matches "<span class="font-bold" x-text="searchQuery"></span>"
                                    </div>
                                </template>

                                <!-- Quick Create link -->
                                <div 
                                    x-show="searchQuery.length >= 1"
                                    @click="showQuickAdd = true; quickItem.item_name = searchQuery"
                                    class="p-2.5 bg-primary-light/50 dark:bg-primary/10 hover:bg-primary-light cursor-pointer transition-all flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded bg-primary text-white flex items-center justify-center text-xs font-bold">+</div>
                                        <p class="text-xs font-bold text-primary">Add New: "<span x-text="searchQuery"></span>"</p>
                                    </div>
                                    <span class="text-[9px] font-black uppercase text-primary tracking-wider">Quick Create →</span>
                                </div>
                            </div>
                        </div>

                        <!-- Add Item Button -->
                        <button 
                            type="button" 
                            @click="showQuickAdd = true" 
                            class="btn-secondary !py-2 !px-3 text-xs font-bold flex items-center gap-1.5 shrink-0" 
                            title="Quick Add Product">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            <span>Add Product</span>
                        </button>
                    </div>
                </div>

                <!-- Cart Table -->
                <div class="overflow-x-auto min-h-[160px]">
                    <table class="w-full text-left">
                        <thead class="bg-background dark:bg-dark-bg/50 border-b border-border-light dark:border-dark-border">
                            <tr>
                                <th class="py-2 px-2.5 text-center w-8 text-[9px] font-black text-text-secondary uppercase tracking-widest">#</th>
                                <th class="py-2 px-3 text-[9px] font-black text-text-secondary uppercase tracking-widest">Item Description</th>
                                <th class="py-2 px-2 text-center w-20 text-[9px] font-black text-text-secondary uppercase tracking-widest">Qty</th>
                                <th class="py-2 px-2 text-right w-24 text-[9px] font-black text-text-secondary uppercase tracking-widest">Price ({{ $currencySymbol }})</th>
                                <th class="py-2 px-2 text-center w-18 text-[9px] font-black text-text-secondary uppercase tracking-widest">Disc</th>
                                <th class="py-2 px-2 text-right text-[9px] font-black text-text-secondary uppercase tracking-widest">Tax</th>
                                <th class="py-2 px-3 text-right w-28 text-[9px] font-black text-text-secondary uppercase tracking-widest">Line Total</th>
                                <th class="py-2 px-2 text-center w-10 text-[9px] font-black text-text-secondary uppercase tracking-widest"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-dark-border text-xs">
                            <!-- Empty Cart State -->
                            <template x-if="cart.length === 0">
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-text-secondary italic">
                                        Purchase cart is empty. Use the search bar above or click "+ Add Product".
                                    </td>
                                </tr>
                            </template>

                            <!-- Cart Items -->
                            <template x-for="(item, index) in cart" :key="item.item_id">
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-dark-bg/40 transition-colors">
                                    <!-- Index -->
                                    <td class="py-1.5 px-2.5 text-center text-xs font-bold text-slate-400 tabular-nums" x-text="index + 1"></td>

                                    <!-- Name, Code, Serial Badge -->
                                    <td class="py-1.5 px-3 min-w-[170px]">
                                        <p class="text-xs font-bold text-text-primary dark:text-dark-text truncate" x-text="item.name" :title="item.name"></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <template x-if="item.item_code">
                                                <span class="text-[9px] font-mono text-primary font-semibold" x-text="item.item_code"></span>
                                            </template>
                                            <template x-if="item.is_serialized == 1">
                                                <button 
                                                    type="button"
                                                    @click="openSerialModal(item, cart.indexOf(item))"
                                                    :class="item.serials.filter(s => s && s.trim() !== '').length === parseInt(item.qty) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border-rose-200 dark:border-rose-500/20 animate-pulse'"
                                                    class="px-1.5 py-0.5 rounded text-[9px] font-black border transition-all flex items-center gap-1 whitespace-nowrap">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                                    <span x-text="item.serials.filter(s => s && s.trim() !== '').length + '/' + item.qty + ' SN'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </td>

                                    <!-- Qty -->
                                    <td class="py-1.5 px-2 text-center">
                                        <input 
                                            type="number"
                                            min="1"
                                            x-model="item.qty"
                                            @input="calculateTotals()"
                                            @change="if(item.is_serialized == 1) openSerialModal(item, cart.indexOf(item))"
                                            :class="item.is_serialized == 1 && item.qty != item.serials.filter(s => s && s.trim() !== '').length ? 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-900/20 text-rose-600' : ''"
                                            class="input-base !w-16 !py-1 !px-2 !text-xs !text-center font-bold">
                                    </td>

                                    <!-- Price -->
                                    <td class="py-1.5 px-2 text-right">
                                        <input 
                                            type="number"
                                            step="0.01"
                                            x-model="item.price"
                                            @input="calculateTotals()"
                                            class="input-base !w-20 !py-1 !px-2 !text-xs !text-right font-bold text-primary tabular-nums">
                                    </td>

                                    <!-- Discount -->
                                    <td class="py-1.5 px-2 text-center">
                                        <input 
                                            type="number"
                                            step="0.01"
                                            x-model="item.discount"
                                            @input="calculateTotals()"
                                            class="input-base !w-16 !py-1 !px-1.5 !text-xs !text-center font-bold text-rose-500 tabular-nums">
                                    </td>

                                    <!-- Tax: compact selects side-by-side -->
                                    <td class="py-1.5 px-2 text-right">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <div class="flex items-center gap-1">
                                                <select 
                                                    x-model="item.tax_id"
                                                    @change="calculateTotals()"
                                                    class="input-base !py-0.5 !px-1.5 !text-[9px] font-bold cursor-pointer">
                                                    <option value="">No Tax</option>
                                                    <template x-for="t in taxesList" :key="t.id">
                                                        <option :value="t.id" x-text="t.tax_name + ' (' + t.tax + '%)'"></option>
                                                    </template>
                                                </select>
                                                <select 
                                                    x-model="item.tax_type"
                                                    @change="calculateTotals()"
                                                    class="input-base !py-0.5 !px-1 !text-[8px] font-black uppercase text-slate-400 cursor-pointer">
                                                    <option value="Inclusive">Inc</option>
                                                    <option value="Exclusive">Exc</option>
                                                </select>
                                            </div>
                                            <span class="text-[9px] font-bold text-slate-400 tabular-nums">
                                                {{ $currencySymbol }}<span x-text="parseFloat(item.taxAmount || 0).toFixed(2)"></span>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Line Total -->
                                    <td class="py-1.5 px-3 text-right">
                                        <p class="text-xs font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                                            {{ $currencySymbol }}<span x-text="parseFloat(item.total || 0).toFixed(2)"></span>
                                        </p>
                                        <p class="text-[9px] text-slate-400 tabular-nums">
                                            Unit: {{ $currencySymbol }}<span x-text="parseFloat(item.unitCost || 0).toFixed(2)"></span>
                                        </p>
                                    </td>

                                    <!-- Delete Button -->
                                    <td class="py-1.5 px-2 text-center">
                                        <button 
                                            type="button" 
                                            @click="removeItem(item.item_id)" 
                                            title="Remove Item" 
                                            class="w-7 h-7 rounded-lg text-slate-400 hover:text-danger hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-all flex items-center justify-center mx-auto">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 3: CHARGES, DISCOUNT & NOTES -->
            <div class="card p-3 space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-border-light dark:border-dark-border">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    <h3 class="text-[10px] font-black uppercase tracking-wider text-text-secondary">2. Charges, Discount & Notes</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <!-- Other / Freight Charges & Tax -->
                    <div class="md:col-span-6 space-y-1">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block">
                            Other / Freight Charges
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">{{ $currencySymbol }}</span>
                                <input 
                                    type="number"
                                    step="0.01"
                                    x-model="otherCharges"
                                    @input="calculateTotals()"
                                    class="input-base !py-1.5 !pl-6 !pr-2 !text-xs !rounded-xl w-full font-bold">
                            </div>
                            <x-searchable-select :options="$taxes" labelKey="tax_name" valueKey="id" placeholder="Tax: 0%" emptyOption="Tax: 0%" emptyValue="" model="purchaseInfo.other_charges_tax_id" change="calculateTotals()" inputClass="!py-1.5 !text-xs !rounded-xl" />
                        </div>
                    </div>

                    <!-- Overall Discount -->
                    <div class="md:col-span-6 space-y-1">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block">
                            Overall Discount
                        </label>
                        <div class="flex gap-2">
                            <input 
                                type="number"
                                step="0.01"
                                x-model="discountOnAll"
                                @input="calculateTotals()"
                                class="input-base !py-1.5 !px-3 !text-xs !rounded-xl flex-1 font-bold text-rose-500">
                            <select 
                                x-model="discountType"
                                @change="calculateTotals()"
                                class="input-base !py-1.5 !px-2 !text-xs !rounded-xl w-20 font-bold cursor-pointer">
                                <option value="Fixed">{{ $currencySymbol }}</option>
                                <option value="Percentage">%</option>
                            </select>
                        </div>
                    </div>

                    <!-- Order Remarks / Notes -->
                    <div class="md:col-span-12 space-y-1">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block">
                            Order Remarks & Internal Notes
                        </label>
                        <textarea 
                            x-model="purchaseInfo.note"
                            rows="2"
                            placeholder="Type notes or special supplier instructions..."
                            class="input-base !py-2 !px-3 !text-xs !rounded-xl w-full resize-none"></textarea>
                    </div>
                </div>

                <!-- SECTION 4: PAYMENT STRIP (Distinct Visual Unit) -->
                <div class="pt-3 border-t border-border-light dark:border-dark-border">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <h3 class="text-[10px] font-black uppercase tracking-wider text-text-secondary">3. Initial Payment (Optional)</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <!-- Paid Amount -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                                Paid Amount
                            </label>
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">{{ $currencySymbol }}</span>
                                <input 
                                    type="number"
                                    step="0.01"
                                    x-model="amount_paid"
                                    class="input-base !py-1.5 !pl-6 !pr-2 !text-xs !rounded-xl w-full font-bold text-emerald-600">
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                                Payment Method
                            </label>
                            <x-searchable-select :options="$paymentTypes" labelKey="payment_type" valueKey="payment_type" placeholder="Select Method" model="payment_type" inputClass="!py-1.5 !text-xs !rounded-xl" />
                        </div>

                        <!-- Bank / Cash Account -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                                Bank / Cash Account
                            </label>
                            <x-searchable-select :options="$accounts" labelKey="account_name" valueKey="id" placeholder="Select Account" model="account_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- RIGHT COLUMN: FINANCIAL SUMMARY SIDEBAR -->
        <div class="xl:col-span-4">
            <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 rounded-2xl p-5 text-white shadow-xl border border-slate-800 space-y-3">
                
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Order Summary</span>
                    <span class="px-2 py-0.5 rounded-full bg-primary-500/20 text-primary-400 text-[9px] font-black uppercase tracking-wider">Live</span>
                </div>

                <!-- Subtotal -->
                <div class="flex justify-between items-center bg-white/5 p-2.5 rounded-xl border border-white/5">
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Items Subtotal</span>
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] font-bold text-slate-500">{{ $currencySymbol }}</span>
                        <span class="text-sm font-black tabular-nums" x-text="parseFloat(subtotal || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Other Charges -->
                <div class="flex justify-between items-center px-1 text-xs">
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Other / Freight</span>
                    <div class="flex items-center gap-1 font-bold text-slate-300">
                        <span class="text-[10px] text-slate-500">{{ $currencySymbol }}</span>
                        <span class="tabular-nums" x-text="parseFloat(otherCharges || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Tax Total -->
                <div class="flex justify-between items-center px-1 text-xs text-emerald-400">
                    <span class="text-[10px] font-black uppercase tracking-wider">Tax Total</span>
                    <div class="flex items-center gap-1 font-bold">
                        <span class="text-[10px] text-emerald-400/60">+ {{ $currencySymbol }}</span>
                        <span class="tabular-nums" x-text="parseFloat(totalTax || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Total Discount -->
                <div class="flex justify-between items-center px-1 text-xs text-rose-400">
                    <span class="text-[10px] font-black uppercase tracking-wider">Total Discount</span>
                    <div class="flex items-center gap-1 font-bold">
                        <span class="text-[10px] text-rose-400/60">- {{ $currencySymbol }}</span>
                        <span class="tabular-nums" x-text="parseFloat(totalDiscount || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Round Off -->
                <div class="flex justify-between items-center px-1 text-xs">
                    <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Round Off</span>
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] text-slate-500">{{ $currencySymbol }}</span>
                        <input 
                            type="number"
                            step="0.01"
                            x-model="roundOff"
                            class="w-16 bg-white/10 border-none rounded-lg py-1 px-1.5 text-xs font-black text-right text-white tabular-nums focus:bg-white/20 outline-none">
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="pt-3 border-t border-white/10">
                    <p class="text-[10px] font-black uppercase text-primary-400 tracking-widest mb-1">Net Grand Total</p>
                    <div class="flex items-baseline gap-1">
                        <span class="text-xs font-bold text-slate-400">{{ $currencySymbol }}</span>
                        <span class="text-2xl font-black text-white tabular-nums tracking-tight" x-text="parseFloat(grandTotal || 0).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Inbound Quantity Pill -->
                <div class="bg-white/5 p-2 rounded-xl border border-white/5 flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Inbound Qty</span>
                    <span class="text-xs font-black text-white tabular-nums" x-text="cart.reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0) + ' Units'"></span>
                </div>

                <!-- Sticky Big CTA -->
                <button 
                    type="button"
                    @click="savePurchase()"
                    :disabled="isSubmitting"
                    class="w-full mt-2 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-950 disabled:opacity-60 disabled:cursor-not-allowed">
                    <template x-if="isSubmitting">
                        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </template>
                    <template x-if="!isSubmitting">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </template>
                    <span x-text="isSubmitting ? 'Saving...' : 'Confirm & Save Purchase'"></span>
                </button>

            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODALS SECTION -->
    <!-- ========================================================================= -->

    <!-- QUICK ADD ITEM MODAL -->
    <div 
        x-show="showQuickAdd"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        x-cloak
        style="display: none;">
        
        <div class="bg-white dark:bg-dark-card w-full max-w-2xl rounded-2xl shadow-2xl border border-border dark:border-dark-border overflow-hidden" @click.away="showQuickAdd = false">
            <div class="px-5 py-3 border-b border-border dark:border-dark-border flex justify-between items-center bg-slate-50/50 dark:bg-dark-bg/50">
                <h3 class="text-sm font-black text-text-primary dark:text-dark-text">Quick Add Product</h3>
                <button type="button" @click="showQuickAdd = false" class="text-slate-400 hover:text-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5 max-h-[80vh] overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Item Name -->
                    <div class="sm:col-span-2">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Item Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" x-model="quickItem.item_name" placeholder="Enter product name" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Category -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">Category <span class="text-danger">*</span></label>
                            <button type="button" @click="showCategoryModal = true" class="text-[9px] font-black text-primary hover:underline">+ New</button>
                        </div>
                        <x-searchable-select :options="$categories" labelKey="category_name" valueKey="id" placeholder="Select Category" model="quickItem.category_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Brand -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">Brand</label>
                            <button type="button" @click="showBrandModal = true" class="text-[9px] font-black text-primary hover:underline">+ New</button>
                        </div>
                        <x-searchable-select :options="$brands" labelKey="brand_name" valueKey="id" placeholder="Select Brand" model="quickItem.brand_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Unit -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">Unit <span class="text-danger">*</span></label>
                            <button type="button" @click="showUnitModal = true" class="text-[9px] font-black text-primary hover:underline">+ New</button>
                        </div>
                        <x-searchable-select :options="$units" labelKey="unit_name" valueKey="id" placeholder="Select Unit" model="quickItem.unit_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Tax -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">Tax <span class="text-danger">*</span></label>
                            <button type="button" @click="showTaxModal = true" class="text-[9px] font-black text-primary hover:underline">+ New</button>
                        </div>
                        <x-searchable-select :options="$taxes" labelKey="tax_name" valueKey="id" placeholder="Select Tax" model="quickItem.tax_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                    </div>

                    <!-- Purchase Price -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Purchase Price <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" x-model="quickItem.purchase_price" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full font-bold text-primary tabular-nums">
                    </div>

                    <!-- Sales Price -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">
                            Sales Price <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" x-model="quickItem.sales_price" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full font-bold text-emerald-600 tabular-nums">
                    </div>

                    <!-- Tax Type -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Tax Type</label>
                        <select x-model="quickItem.tax_type" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full cursor-pointer">
                            <option value="Inclusive">Inclusive</option>
                            <option value="Exclusive">Exclusive</option>
                        </select>
                    </div>

                    <!-- SKU -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">SKU / Barcode</label>
                        <input type="text" x-model="quickItem.barcode" placeholder="Optional barcode" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>

                    <!-- Serial Tracking Switch -->
                    <div class="sm:col-span-2 flex items-center gap-2 pt-1">
                        <label class="flex items-center cursor-pointer gap-2 select-none">
                            <input type="checkbox" x-model="quickItem.is_serialized" :value="1" @change="if(!quickItem.is_serialized) { quickItem.opening_stock = 0; updateQuickSerials(); }" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                            <span class="text-xs font-bold text-text-primary dark:text-dark-text">Track Serial Numbers / IMEI for this product</span>
                        </label>
                    </div>
                </div>

                <!-- Opening Stock & Serials for Quick Add -->
                <div x-show="quickItem.is_serialized || quickItem.opening_stock > 0" x-cloak class="p-3 bg-slate-50 dark:bg-dark-bg/40 rounded-xl border border-border dark:border-dark-border space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Opening Stock</label>
                            <input type="number" x-model="quickItem.opening_stock" @input="updateQuickSerials()" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                        </div>
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Warehouse</label>
                            <x-searchable-select :options="$warehouses" labelKey="warehouse_name" valueKey="id" placeholder="Select Warehouse" model="quickItem.warehouse_id" inputClass="!py-1.5 !text-xs !rounded-xl" />
                        </div>
                    </div>

                    <div x-show="quickItem.opening_stock > 0" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black uppercase text-primary tracking-wider">Serial Numbers Entry</span>
                            <span class="text-[9px] font-bold text-slate-400" x-text="'Required: ' + quickItem.opening_stock"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[140px] overflow-y-auto pr-1">
                            <template x-for="(sn, idx) in quickItem.serial_numbers" :key="idx">
                                <input type="text" x-model="quickItem.serial_numbers[idx]" :placeholder="'SN #' + (idx + 1)" class="input-base !py-1 !px-2 !text-xs !rounded-lg w-full font-mono uppercase">
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 bg-slate-50/80 dark:bg-dark-bg/50 border-t border-border dark:border-dark-border flex justify-end gap-2">
                <button type="button" @click="showQuickAdd = false" class="btn-secondary !py-1.5 !px-3 text-xs font-bold">Cancel</button>
                <button type="button" @click="submitQuickItem()" class="btn-primary !py-1.5 !px-4 text-xs font-bold">Save Product</button>
            </div>
        </div>
    </div>

    <!-- SERIAL NUMBER MANAGEMENT MODAL -->
    <div 
        x-show="serialModal.show" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        style="display: none;"
        x-cloak>
        
        <div class="bg-white dark:bg-dark-card w-full max-w-xl rounded-2xl shadow-2xl border border-border dark:border-dark-border overflow-hidden" @click.away="serialModal.show = false">
            <div class="px-5 py-3 border-b border-border dark:border-dark-border flex justify-between items-center bg-slate-50/50 dark:bg-dark-bg/50">
                <div>
                    <h3 class="text-sm font-black text-text-primary dark:text-dark-text" x-text="'Serials for: ' + serialModal.item_name"></h3>
                    <p class="text-[9px] text-text-secondary uppercase tracking-wider">Paste bulk or enter individually below</p>
                </div>
                <button type="button" @click="serialModal.show = false" class="text-slate-400 hover:text-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <!-- Bulk Entry Area -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest">
                            Bulk Paste (Fast)
                        </label>
                        <span class="text-[9px] text-slate-400">Separated by line or comma</span>
                    </div>
                    <textarea
                        x-model="serialModal.tempSerials"
                        rows="3"
                        @input.debounce.300ms="
                            refreshSerialWarnings();
                            const sns = $event.target.value.split(/[\n,]/).map(s => s.trim()).filter(s => s !== '');
                            if (serialModal.currentIndex !== -1) {
                                const item = cart[serialModal.currentIndex];
                                sns.forEach((s, i) => { if(i < item.serials.length) item.serials[i] = s; });
                            }
                        "
                        placeholder="Paste serial numbers here..."
                        class="input-base !py-2 !px-3 !text-xs !rounded-xl w-full font-mono"></textarea>
                </div>

                <!-- Live inline warning for serials typed more than once in this modal -->
                <template x-if="serialModal.warnings.length > 0">
                    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 px-3 py-2 text-[10px] font-bold text-rose-600 dark:text-rose-400 space-y-1">
                        <div>⚠ Serial numbers repeated within this item must be removed:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="w in serialModal.warnings" :key="w">
                                <span class="bg-white dark:bg-dark-card px-2 py-0.5 rounded-md border border-rose-200 dark:border-rose-500/30 font-mono uppercase" x-text="w"></span>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Individual Inputs Grid -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] font-black uppercase text-text-secondary tracking-widest">Individual Slots</span>
                        <span class="text-xs font-bold text-primary tabular-nums" x-text="(serialModal.currentIndex !== -1 ? cart[serialModal.currentIndex].serials.filter(s => s && s.trim() !== '').length : 0) + ' / ' + (serialModal.currentIndex !== -1 ? cart[serialModal.currentIndex].qty : 0) + ' Filled'"></span>
                    </div>
                    
                    <div class="max-h-[180px] overflow-y-auto pr-1">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <template x-if="serialModal.currentIndex !== -1">
                                <template x-for="(sn, idx) in cart[serialModal.currentIndex].serials" :key="idx">
                                    <input 
                                        type="text" 
                                        x-model="cart[serialModal.currentIndex].serials[idx]" 
                                        :placeholder="'Serial #' + (idx + 1)" 
                                        class="input-base !py-1 !px-2 !text-xs !rounded-lg w-full font-mono uppercase font-bold">
                                </template>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-border dark:border-dark-border">
                    <button type="button" @click="saveSerials()" class="flex-1 btn-primary !py-2 text-xs font-bold">
                        Done & Apply Serials
                    </button>
                    <button type="button" @click="serialModal.show = false" class="btn-secondary !py-2 !px-4 text-xs font-bold">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ADD SUPPLIER MODAL -->
    <template x-teleport="body">
        <div x-show="showSupplierModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="showSupplierModal = false" class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-border dark:border-dark-border">
                <div class="px-4 py-3 border-b border-border dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-dark-bg/50">
                    <h3 class="text-xs font-black uppercase tracking-wider text-text-primary dark:text-dark-text">Quick Add Supplier</h3>
                    <button type="button" @click="showSupplierModal = false" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'supplier_id', 'showSupplierModal')" action="{{ route('contacts.suppliers.store') }}" method="POST" class="p-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" required placeholder="Supplier name" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Mobile Phone <span class="text-danger">*</span></label>
                        <input type="text" name="mobile" required placeholder="01XXXXXXXXX" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Email Address</label>
                        <input type="email" name="email" placeholder="supplier@example.com" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 btn-primary !py-2 text-xs font-bold">Save Supplier</button>
                        <button type="button" @click="showSupplierModal = false" class="btn-secondary !py-2 !px-3 text-xs font-bold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- QUICK ADD BRAND MODAL -->
    <template x-teleport="body">
        <div x-show="showBrandModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="showBrandModal = false" class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-border dark:border-dark-border">
                <div class="px-4 py-3 border-b border-border dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-dark-bg/50">
                    <h3 class="text-xs font-black uppercase tracking-wider text-text-primary dark:text-dark-text">Quick Add Brand</h3>
                    <button type="button" @click="showBrandModal = false" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'brand_id', 'showBrandModal')" action="{{ route('items.brands.store') }}" method="POST" class="p-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" name="brand_name" required placeholder="e.g. Samsung" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Brand Code</label>
                        <input type="text" name="brand_code" placeholder="Optional" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 btn-primary !py-2 text-xs font-bold">Save Brand</button>
                        <button type="button" @click="showBrandModal = false" class="btn-secondary !py-2 !px-3 text-xs font-bold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- QUICK ADD CATEGORY MODAL -->
    <template x-teleport="body">
        <div x-show="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="showCategoryModal = false" class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-border dark:border-dark-border">
                <div class="px-4 py-3 border-b border-border dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-dark-bg/50">
                    <h3 class="text-xs font-black uppercase tracking-wider text-text-primary dark:text-dark-text">Quick Add Category</h3>
                    <button type="button" @click="showCategoryModal = false" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'category_id', 'showCategoryModal')" action="{{ route('items.categories.store') }}" method="POST" class="p-4 space-y-3">
                    @csrf
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" required placeholder="e.g. Laptops" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Category Code</label>
                        <input type="text" name="category_code" placeholder="Optional" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 btn-primary !py-2 text-xs font-bold">Save Category</button>
                        <button type="button" @click="showCategoryModal = false" class="btn-secondary !py-2 !px-3 text-xs font-bold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- QUICK ADD UNIT MODAL -->
    <template x-teleport="body">
        <div x-show="showUnitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="showUnitModal = false" class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-border dark:border-dark-border">
                <div class="px-4 py-3 border-b border-border dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-dark-bg/50">
                    <h3 class="text-xs font-black uppercase tracking-wider text-text-primary dark:text-dark-text">Quick Add Unit</h3>
                    <button type="button" @click="showUnitModal = false" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'unit_id', 'showUnitModal')" action="{{ route('settings.units.store') }}" method="POST" class="p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="status" value="1">
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" name="unit_name" required placeholder="e.g. Pieces, Box" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Description</label>
                        <input type="text" name="description" placeholder="Optional description" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 btn-primary !py-2 text-xs font-bold">Save Unit</button>
                        <button type="button" @click="showUnitModal = false" class="btn-secondary !py-2 !px-3 text-xs font-bold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- QUICK ADD TAX MODAL -->
    <template x-teleport="body">
        <div x-show="showTaxModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="showTaxModal = false" class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden border border-border dark:border-dark-border">
                <div class="px-4 py-3 border-b border-border dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-dark-bg/50">
                    <h3 class="text-xs font-black uppercase tracking-wider text-text-primary dark:text-dark-text">Quick Add Tax</h3>
                    <button type="button" @click="showTaxModal = false" class="text-slate-400 hover:text-danger">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'tax_id', 'showTaxModal')" action="{{ route('settings.tax.store') }}" method="POST" class="p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="group_bit" value="0">
                    <input type="hidden" name="status" value="1">
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Tax Name <span class="text-danger">*</span></label>
                        <input type="text" name="tax_name" required placeholder="e.g. VAT 5%" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-text-secondary tracking-widest block mb-1">Tax Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="tax" required placeholder="5.00" class="input-base !py-1.5 !px-3 !text-xs !rounded-xl w-full">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 btn-primary !py-2 text-xs font-bold">Save Tax</button>
                        <button type="button" @click="showTaxModal = false" class="btn-secondary !py-2 !px-3 text-xs font-bold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- SUCCESS TOAST MODAL -->
    <template x-teleport="body">
        <div 
            x-show="showSuccessModal" 
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50" 
            x-cloak 
            style="display: none;">
            <div class="bg-slate-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-2.5 border border-white/10 text-xs font-bold">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                <span x-text="successMessage"></span>
            </div>
        </div>
    </template>

    <!-- ERROR DETAILS MODAL -->
    <template x-teleport="body">
        <div 
            x-show="showErrorModal" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" 
            x-cloak 
            style="display: none;">
            <div class="bg-white dark:bg-dark-card w-full max-w-sm rounded-2xl shadow-2xl border border-border dark:border-dark-border p-5 text-center" @click.away="showErrorModal = false">
                <div class="w-12 h-12 bg-rose-50 dark:bg-rose-500/10 rounded-xl flex items-center justify-center mx-auto mb-3 text-danger">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-sm font-black text-text-primary dark:text-dark-text mb-1" x-text="errorMessage"></h3>
                <div class="space-y-1.5 text-left max-h-40 overflow-y-auto pr-1 my-3">
                    <template x-for="err in errorDetails">
                        <div class="p-2 bg-rose-50/60 dark:bg-rose-500/10 rounded-lg text-xs font-semibold text-danger" x-text="err"></div>
                    </template>
                </div>
                <button type="button" @click="showErrorModal = false" class="w-full btn-secondary !py-2 text-xs font-bold">Review & Correct</button>
            </div>
        </div>
    </template>

</div>
</x-app-layout>