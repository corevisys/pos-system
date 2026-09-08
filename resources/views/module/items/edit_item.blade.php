<x-app-layout title="Edit Item">
    <div>

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Items <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Edit Item</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Items List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">Edit Item</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Inventory Management</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('items.list') }}" class="btn-secondary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button form="itemForm" type="submit" class="btn-primary" x-ref="submitBtn">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    Save Item
                </button>
            </div>
        </div>

        <!-- Server-side Error Alert (validation + DuplicateSerialNumberException) -->
        @if ($errors->any() || session('error'))
            <div class="mb-4 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl flex items-center gap-3">
                <div class="w-8 h-8 bg-rose-500 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <p class="text-[11px] font-black text-danger uppercase tracking-wider">Please correct the following errors:</p>
                    <ul class="text-[10px] text-danger font-medium mt-1 list-disc list-inside">
                        @if (session('error'))
                            <li>{{ session('error') }}</li>
                        @endif
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @push('scripts')
        @php
            // Hoisted out of @json(...) — Blade's directive parser mis-balances closures
            // / nested arrays inside @json() arguments, which emitted invalid PHP.
            $editTaxesJson = $taxes->map(function($t){ return ['id' => $t->id, 'rate' => $t->tax]; })->values();
        @endphp
        <script>
            function editItemForm() {
                return {
            itemGroup: '{{ trim(old('item_group', $item->item_group)) }}',

            discountType: '{{ old('discount_type', $item->discount_type) ?? 'Percentage' }}',
            taxType: '{{ old('tax_type', $item->tax_type) ?? 'Inclusive' }}',
            price: {{ old('price', $item->price) ?? 0 }},
            taxId: '{{ old('tax_id', $item->tax_id) ?? '' }}',
            purchasePrice: {{ old('purchase_price', $item->purchase_price) ?? 0 }},
            profitMargin: {{ old('profit_margin', $item->profit_margin) ?? 0 }},
            salesPrice: {{ old('sales_price', $item->sales_price) ?? 0 }},
            discount: {{ old('discount', $item->discount) ?? 0 }},
            openingStock: Number('{{ old('opening_stock', $current_stock) ?? 0 }}'),
            warehouseId: '{{ old('warehouse_id', $warehouse_id) ?? '' }}',
            warehouseStocks: @json($warehouseStocks ?? []),
            warehouseNames: @json($warehouses->pluck('warehouse_name', 'id')->toArray()),
            hasWarehouseRows: {{ isset($hasWarehouseRows) ? ($hasWarehouseRows ? 'true' : 'false') : 'true' }},
            stockFromOld: {{ old('opening_stock') !== null ? 'true' : 'false' }},

            enableSerials: {{ (old('is_serialized', $item->is_serialized) == 1) ? 'true' : 'false' }},
            serialNumbers: @json(old('serial_numbers', $item->serials->pluck('serial_number') ?? [])),
            taxes: @json($editTaxesJson),
            
            showBrandModal: false,
            showCategoryModal: false,
            showUnitModal: false,
            showTaxModal: false,
            imagePreview: null,
            
            handleImagePreview(event) {
                const file = event.target.files[0];
                if (file) {
                    this.imagePreview = URL.createObjectURL(file);
                }
            },
            
            get isSingle() { return String(this.itemGroup).toLowerCase() === 'single'; },
            get isBox() { return String(this.itemGroup).toLowerCase() === 'box'; },

            get warehouseName() {
                return this.warehouseNames[this.warehouseId] || 'Selected Warehouse';
            },
            get totalAllWarehouseStock() {
                // Derived sum across ALL warehouse rows for this item — never the global
                // db_items.stock, which may be stale/legacy for items without warehouse rows.
                if (!this.hasWarehouseRows) {
                    return Number(this.openingStock || 0).toFixed(2);
                }
                return Object.values(this.warehouseStocks || {}).reduce((sum, qty) => sum + (parseFloat(qty) || 0), 0).toFixed(2);
            },

            init() {
                this.$watch('price', () => this.calculatePurchasePrice());
                this.$watch('taxId', () => this.calculatePurchasePrice());
                this.$watch('taxType', () => this.calculatePurchasePrice());
                this.$watch('purchasePrice', () => this.calculateSalesPrice());
                this.$watch('profitMargin', () => this.calculateSalesPrice());
                
                this.$watch('itemGroup', (val) => {
                    if (this.isBox && this.variants.length === 0) {
                        this.addVariant();
                    }
                    this.$nextTick(() => this.syncAllSerials());
                });

                this.$watch('openingStock', () => this.$nextTick(() => this.syncAllSerials()));
                this.$watch('enableSerials', () => this.$nextTick(() => this.syncAllSerials()));

                // When the selected warehouse changes, show THAT warehouse's stock
                // (not always the first warehouse's). Global stock stays a derived sum.
                this.$watch('warehouseId', () => this.$nextTick(() => this.syncStockForWarehouse()));

                // Initialize variants if needed
                if (this.isBox && this.variants.length === 0) {
                    this.addVariant();
                }

                // Initial serial update. On a validation redirect the user's typed
                // opening_stock must be preserved, so skip the first warehouse sync.
                this.$nextTick(() => {
                    if (!this.stockFromOld) {
                        this.syncStockForWarehouse();
                    }
                    this.syncAllSerials();
                    this.initSerialErrors();
                });
            },

            syncStockForWarehouse() {
                if (this.isSingle) {
                    // Legacy items (no warehouse rows) keep their existing db_items.stock —
                    // never overwrite it with 0 just because the map is empty.
                    if (this.hasWarehouseRows) {
                        this.openingStock = Number(this.warehouseStocks[this.warehouseId] ?? 0);
                    }
                } else if (this.isBox) {
                    this.variants.forEach(v => {
                        if (v.wh_stocks && Object.keys(v.wh_stocks).length > 0) {
                            v.stock = Number(v.wh_stocks[this.warehouseId] ?? 0);
                        }
                        // else: legacy variant with no warehouse rows keeps its existing stock
                    });
                    this.variants = JSON.parse(JSON.stringify(this.variants)); // Force reactivity
                    this.calculateTotalStock();
                }
                this.$nextTick(() => this.syncAllSerials());
            },

            getTaxRate(id) {
                const tax = this.taxes.find(t => t.id == id);
                return tax ? parseFloat(tax.rate) : 0;
            },

            calculatePurchasePrice() {
                let price = parseFloat(this.price) || 0;
                let taxRate = this.getTaxRate(this.taxId);
                
                if (this.taxType === 'Exclusive') {
                    this.purchasePrice = (price * (1 + taxRate / 100)).toFixed(2);
                } else {
                    this.purchasePrice = price.toFixed(2);
                }
                this.calculateSalesPrice();
            },

            calculateSalesPrice() {
                let purchase = parseFloat(this.purchasePrice) || 0;
                let profit = parseFloat(this.profitMargin) || 0;
                this.salesPrice = (purchase * (1 + profit / 100)).toFixed(2);
            },

            calculateProfitMargin() {
                let purchase = parseFloat(this.purchasePrice) || 0;
                let sales = parseFloat(this.salesPrice) || 0;
                if (purchase > 0) {
                    this.profitMargin = (((sales - purchase) / purchase) * 100).toFixed(2);
                }
            },

            syncAllSerials() {
                if (!this.enableSerials) return;
                if (this.isSingle) {
                    this.updateSerials();
                } else if (this.isBox) {
                    this.variants.forEach((_, i) => this.updateVariantSerials(i));
                }
            },

            updateSerials() {
                const targetCount = parseInt(this.openingStock) || 0;
                let currentSns = Array.isArray(this.serialNumbers) ? [...this.serialNumbers] : [];
                
                if (targetCount > 0) {
                    if (targetCount > currentSns.length) {
                        while (currentSns.length < targetCount) {
                            currentSns.push('');
                        }
                    } else if (targetCount < currentSns.length) {
                        currentSns = currentSns.slice(0, targetCount);
                    }
                } else {
                    currentSns = [];
                }
                this.serialNumbers = currentSns;
            },

            updateVariantSerials(index) {
                const variant = this.variants[index];
                if (!variant) return;
                
                const targetCount = parseInt(variant.stock) || 0;
                let currentSns = Array.isArray(variant.serials) ? variant.serials : [];
                
                if (targetCount > 0) {
                    if (targetCount > currentSns.length) {
                        for (let i = currentSns.length; i < targetCount; i++) {
                            currentSns.push('');
                        }
                    } else if (targetCount < currentSns.length) {
                        currentSns = currentSns.slice(0, targetCount);
                    }
                } else {
                    currentSns = [];
                }
                variant.serials = currentSns;
                this.variants = JSON.parse(JSON.stringify(this.variants)); // Force reactivity
            },



            variants: @json(old('variants', $variantsData) ?? []),

            errors: @json($errors->toArray()),
            getVariantError(index, field) {
                const key = `variants.${index}.${field}`;
                // Client-side first (Alpine validateForm), then server-side session errors.
                if (this.clientErrors && this.clientErrors[key]) {
                    return this.clientErrors[key];
                }
                return this.errors[key] ? this.errors[key][0] : null;
            },
            addVariant() {
                this.variants = [...this.variants, { name: '', sku: '', hsn: '', barcode: '', price: '', purchase_price: '', profit: '', sales_price: '', stock: 0, serials: [] }];
            },
            removeVariant(index) {
                if (this.variants.length > 1) {
                    this.variants = this.variants.filter((_, i) => i !== index);
                }
            },

            calculateTotalStock() {
                this.openingStock = this.variants.reduce((sum, v) => sum + (parseFloat(v.stock) || 0), 0);
            },
            calculateVariant(index, field) {
                let variant = this.variants[index];
                let price = parseFloat(variant.price) || 0;
                let purchase = parseFloat(variant.purchase_price) || 0;
                let profit = parseFloat(variant.profit) || 0;
                let sales = parseFloat(variant.sales_price) || 0;
                
                // Note: Variants currently use Global Tax settings for calculation as per design, 
                // or if no tax is applied, logic mimics single item without specific variant tax field.
                // Assuming variants inherit global tax settings for calculation simplicity or mimic single item logic.
                // However, variants grid usually implies direct entry. Let's make it standard.
                
                let taxRate = this.getTaxRate(this.taxId);
                
                if (field === 'price') {
                    if (this.taxType === 'Exclusive') {
                        variant.purchase_price = (price * (1 + taxRate / 100)).toFixed(2);
                    } else {
                        variant.purchase_price = price.toFixed(2);
                    }
                    // Trigger cascade
                    purchase = parseFloat(variant.purchase_price);
                    variant.sales_price = (purchase * (1 + profit / 100)).toFixed(2);
                }
                
                if (field === 'purchase_price' || field === 'profit') {
                    variant.sales_price = (purchase * (1 + profit / 100)).toFixed(2);
                }
                
                if (field === 'sales_price') {
                    if (purchase > 0) {
                        variant.profit = (((sales - purchase) / purchase) * 100).toFixed(2);
                    }
                }
                
                this.variants = JSON.parse(JSON.stringify(this.variants)); // Force deep reactivity
            },


            async submitQuickAdd(event, selectName, modalFlag) {
                const form = event.target;
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type=submit]');
                const originalText = submitBtn.innerText;
                
                submitBtn.disabled = true;
                submitBtn.innerText = 'Saving...';
                
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    if (response.ok && (data.success || data.id)) {
                        const select = document.querySelector(`select[name='${selectName}']`);
                        if (select) {
                            const optionName = data.name || data.brand_name || data.category_name || data.unit_name || data.tax_name;
                            const option = new Option(optionName, data.id, true, true);
                            select.add(option);
                            select.dispatchEvent(new Event('change'));
                            
                            if (selectName === 'tax_id') {
                                if (this.taxes) this.taxes.push({ id: data.id, rate: data.rate || data.tax_percent || 0 });
                                this.taxId = data.id;
                            }
                        }
                        showSuccess(data.message || 'Saved successfully!');
                        form.reset();
                        this[modalFlag] = false;
                    } else {
                        const errList = data.errors ? Object.values(data.errors).flat() : (data.message || 'Error occurred while saving.');
                        showError(errList);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showError('An unexpected error occurred. Please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerText = originalText;
                }
            },

                // ── Client-side validation (mirrors add_item's itemForm()) ──
                clientErrors: {},
                isSubmitted: false,
                isSubmitting: false,
                serialError: '{{ session('serial_error') }}',
                serialErrors: {},
                variantSerialErrorMap: {},

                initSerialErrors() {
                    if (this.serialError && this.serialError !== '') {
                        const idx = this.serialNumbers.findIndex(s => s && s.trim().toUpperCase() === this.serialError.toUpperCase());
                        if (idx >= 0) {
                            this.serialErrors = {};
                            this.serialErrors[idx] = 'Serial ' + this.serialError + ' is duplicated or already registered for this item.';
                        }
                    }
                },

                validateField(field) {
                    const form = document.getElementById('itemForm');
                    const formData = new FormData(form);
                    const value = formData.get(field);
                    delete this.clientErrors[field];

                    if (field === 'item_name' && (!value || value.trim() === '')) {
                        this.clientErrors.item_name = 'Item name is required';
                    }
                    if (field === 'category_id' && (!value || value === '')) {
                        this.clientErrors.category_id = 'Please select a category';
                    }
                    if (field === 'unit_id' && (!value || value === '')) {
                        this.clientErrors.unit_id = 'Please select a unit';
                    }
                    if (field === 'tax_id' && (!value || value === '')) {
                        this.clientErrors.tax_id = 'Please select a tax rate';
                    }
                    if (field === 'warehouse_id' && (!value || value === '')) {
                        this.clientErrors.warehouse_id = 'Please select a warehouse';
                    }
                    if (field === 'price' && value !== null && value !== '') {
                        const num = parseFloat(value);
                        if (isNaN(num) || num < 0) {
                            this.clientErrors.price = 'Price must be zero or positive';
                        }
                    }
                    if (field === 'purchase_price' && value !== null && value !== '') {
                        const num = parseFloat(value);
                        if (isNaN(num) || num < 0) {
                            this.clientErrors.purchase_price = 'Purchase price must be zero or positive';
                        }
                    }
                    if (field === 'sales_price' && value !== null && value !== '') {
                        const num = parseFloat(value);
                        if (isNaN(num) || num < 0) {
                            this.clientErrors.sales_price = 'Sales price must be zero or positive';
                        }
                    }
                    if (field === 'sku' && value) {
                        if (value.length > 100) {
                            this.clientErrors.sku = 'SKU must be 100 characters or fewer';
                        } else if (!/^[A-Za-z0-9\-_\.]+$/.test(value)) {
                            this.clientErrors.sku = 'SKU may only contain letters, numbers, dash, underscore or dot';
                        }
                    }
                    if (field === 'custom_barcode' && value) {
                        if (value.length > 100) {
                            this.clientErrors.custom_barcode = 'Barcode must be 100 characters or fewer';
                        } else if (!/^[A-Za-z0-9\-_\.]+$/.test(value)) {
                            this.clientErrors.custom_barcode = 'Barcode may only contain letters, numbers, dash, underscore or dot';
                        }
                    }
                    if (field === 'hsn' && value && value.length > 50) {
                        this.clientErrors.hsn = 'HSN must be 50 characters or fewer';
                    }
                },

                validateForm() {
                    this.clientErrors = {};
                    const fields = ['item_name', 'category_id', 'unit_id', 'tax_id', 'warehouse_id', 'price', 'purchase_price', 'sales_price', 'sku', 'custom_barcode', 'hsn'];
                    fields.forEach(field => {
                        if (this.isSingle || ['item_name', 'category_id', 'unit_id', 'tax_id', 'warehouse_id'].includes(field)) {
                            this.validateField(field);
                        }
                    });

                    if (this.isBox) {
                        this.variants.forEach((variant, index) => {
                            if (!variant.name || variant.name.trim() === '') {
                                this.clientErrors['variants.' + index + '.name'] = 'Variant name is required';
                            }
                            if (variant.price === '' || variant.price === null || isNaN(parseFloat(variant.price)) || parseFloat(variant.price) < 0) {
                                this.clientErrors['variants.' + index + '.price'] = 'Variant price must be zero or positive';
                            }
                            if (variant.purchase_price === '' || variant.purchase_price === null || isNaN(parseFloat(variant.purchase_price)) || parseFloat(variant.purchase_price) < 0) {
                                this.clientErrors['variants.' + index + '.purchase_price'] = 'Variant purchase price must be zero or positive';
                            }
                            if (variant.sales_price === '' || variant.sales_price === null || isNaN(parseFloat(variant.sales_price)) || parseFloat(variant.sales_price) < 0) {
                                this.clientErrors['variants.' + index + '.sales_price'] = 'Variant sales price must be zero or positive';
                            }
                        });
                    }

                    if (this.enableSerials && this.isSingle && this.openingStock > 0) {
                        const filled = this.serialNumbers.filter(s => s && s.trim() !== '').length;
                        if (filled !== parseInt(this.openingStock)) {
                            this.clientErrors.serial_count = 'Please provide all ' + this.openingStock + ' serial numbers (only ' + filled + ' entered).';
                        }
                    }

                    if (Object.keys(this.clientErrors).length > 0) {
                        this.$nextTick(() => {
                            const firstError = document.querySelector('[x-show*="clientErrors"]');
                            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        });
                        return false;
                    }
                    return true;
                },

                variantErrors(index, field) {
                    const key = 'variants.' + index + '.' + field;
                    return this.clientErrors[key] || null;
                },

                variantSerialErrors(vIdx, snIndex, value) {
                    if (value && value.trim() !== '') {
                        const variant = this.variants[vIdx];
                        if (variant && variant.serials) {
                            const dupCount = variant.serials.filter(s => s && s.trim().toUpperCase() === value.trim().toUpperCase()).length;
                            if (dupCount > 1) {
                                return 'Serial ' + value + ' is entered more than once for this variant.';
                            }
                        }
                    }
                    return null;
                }
                };
            }
            document.addEventListener('alpine:init', () => {
                Alpine.data('editItemForm', editItemForm);
            });
        </script>
        @endpush

        <div class="card p-4 md:p-6" x-data="editItemForm()">
            <div>
                <form id="itemForm" action="{{ route('items.update', $item->id) }}" method="POST" enctype="multipart/form-data" class="space-y-8"
                      @submit.prevent="isSubmitting = true; if(validateForm()) { setButtonLoading($refs.submitBtn, 'Saving...'); $el.submit(); } else { isSubmitting = false; resetButtonLoading($refs.submitBtn); }">
                    @csrf

                    <!-- ═════════════ SECTION 1: BASIC INFORMATION ═════════════ -->
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-4 px-1">
                            <div class="w-1 h-3.5 bg-primary rounded-full"></div>
                            <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Basic Information</h2>
                        </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" value="{{ old('item_name', $item->item_name) }}" x-on:blur="validateField('item_name')" placeholder="E.g. Wireless Mouse" class="input-base">
                            @error('item_name')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.item_name" x-text="clientErrors.item_name" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Serial Tracking</label>
                            <label class="flex items-center gap-2.5 input-base cursor-pointer select-none">
                                <input type="checkbox" name="is_serialized" value="1" x-model="enableSerials" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                                <span class="text-xs font-bold text-text-primary dark:text-dark-text uppercase tracking-widest">Track Serials</span>
                            </label>
                            @error('is_serialized')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Brand</label>
                            <x-searchable-select name="brand_id" :options="$brands" labelKey="brand_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Brand" :value="old('brand_id', $item->brand_id)" quickAddClick="showBrandModal = true" />
                            @error('brand_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Category <span class="text-danger">*</span></label>
                            <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Category" :value="old('category_id', $item->category_id)" quickAddClick="showCategoryModal = true" required />
                            @error('category_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.category_id" x-text="clientErrors.category_id" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Item Group <span class="text-danger">*</span></label>
                            <div class="relative">
                                <select name="item_group" x-model="itemGroup" class="input-base appearance-none cursor-pointer pr-8">
                                    <option value="">Select Item Group</option>
                                    <option value="Single" {{ old('item_group', $item->item_group) == 'Single' ? 'selected' : '' }}>Single</option>
                                    <option value="Box" {{ old('item_group', $item->item_group) == 'Box' ? 'selected' : '' }}>Box</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                            @error('item_group')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Unit <span class="text-danger">*</span></label>
                            <x-searchable-select name="unit_id" :options="$units" labelKey="unit_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Unit" :value="old('unit_id', $item->unit_id)" quickAddClick="showUnitModal = true" required />
                            @error('unit_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.unit_id" x-text="clientErrors.unit_id" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div x-show="isSingle">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">SKU</label>
                            <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" x-on:blur="validateField('sku')" placeholder="Optional" class="input-base">
                            @error('sku')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.sku" x-text="clientErrors.sku" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div x-show="isSingle">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">HSN</label>
                            <input type="text" name="hsn" value="{{ old('hsn', $item->hsn) }}" x-on:blur="validateField('hsn')" placeholder="GST Category" class="input-base">
                            @error('hsn')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.hsn" x-text="clientErrors.hsn" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Alert Qty</label>
                            <input type="number" name="alert_qty" value="{{ old('alert_qty', $item->alert_qty) }}" class="input-base tabular-nums">
                            @error('alert_qty')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Seller Points</label>
                            <input type="number" name="seller_points" value="{{ old('seller_points', $item->seller_points) }}" class="input-base tabular-nums">
                            @error('seller_points')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="isSingle">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Barcode</label>
                            <div class="relative">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                </span>
                                <input type="text" name="custom_barcode" value="{{ old('custom_barcode', $item->custom_barcode) }}" x-on:blur="validateField('custom_barcode')" placeholder="{{ $item->item_code }}" class="input-base pr-8 tracking-widest font-mono">
                            </div>
                            @error('custom_barcode')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.custom_barcode" x-text="clientErrors.custom_barcode" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Description</label>
                            <input type="text" name="description" value="{{ old('description', $item->description) }}" placeholder="Brief details..." class="input-base">
                            @error('description')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Select Image</label>
                            <div class="flex flex-col gap-1">
                                <div class="relative">
                                    <input type="file" name="item_image" @change="handleImagePreview($event)" class="input-base file:mr-4 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[9px] file:font-black file:uppercase file:bg-primary-50 file:text-primary hover:file:bg-primary-100">
                                </div>
                                <div x-show="imagePreview || '{{ $item->item_image }}'" class="mt-2 flex items-center gap-3 bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-border dark:border-dark-border shadow-sm w-fit" x-cloak>
                                    <div class="relative">
                                        <img :src="imagePreview || '{{ asset($item->item_image) }}'" class="w-12 h-12 object-cover rounded-lg ring-2 ring-white dark:ring-slate-700 shadow-sm" alt="Item Image">
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black uppercase tracking-widest text-slate-400" x-text="imagePreview ? 'New Preview' : 'Current Image'"></span>
                                        <span class="text-[7px] font-bold text-slate-500 max-w-[100px] truncate" x-text="imagePreview ? 'Selected File' : '{{ basename($item->item_image) }}'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                <!-- ═════════════ SECTION 2: PRICING & TAX ═════════════ -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-emerald-500 rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Pricing & Tax</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Discount Type</label>
                            <div class="relative">
                                <select name="discount_type" x-model="discountType" class="input-base appearance-none cursor-pointer pr-8">
                                    <option value="Percentage">Percentage(%)</option>
                                    <option value="Fixed">Fixed Amount</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                            @error('discount_type')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Discount</label>
                            <div class="relative">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]" x-text="discountType === 'Percentage' ? '%' : '{{ $currencySymbol }}'"></span>
                                <input type="number" name="discount" x-model.number="discount" placeholder="0.00" class="input-base pr-8 tabular-nums">
                            </div>
                            @error('discount')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Price <span class="text-danger">*</span>
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <input type="number" step="0.01" name="price" x-model.number="price"
                                   :readonly="isBox"
                                   :class="isBox ? 'input-base !bg-slate-100/50 dark:!bg-slate-700/30' : 'input-base'"
                                   @input="calculatePurchasePrice()" x-on:blur="validateField('price')" placeholder="Price w/o Tax" class="input-base tabular-nums">
                             @error('price')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                             @enderror
                             <span x-show="clientErrors.price" x-text="clientErrors.price" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                         </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Tax <span class="text-danger">*</span>
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <x-searchable-select name="tax_id" :options="$taxes" labelKey="tax_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Tax" :value="old('tax_id', $item->tax_id)" model="taxId" change="calculatePurchasePrice()" quickAddClick="showTaxModal = true" required />
                            @error('tax_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.tax_id" x-text="clientErrors.tax_id" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>


                        <!-- Purchase Price -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Purchase Price <span class="text-danger">*</span>
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <input type="number" step="0.01" name="purchase_price" x-model.number="purchasePrice"
                                   :readonly="isBox"
                                   :class="isBox ? 'input-base !bg-slate-100/50 dark:!bg-slate-700/30' : 'input-base text-emerald-600 dark:text-emerald-400 font-black'"
                                   @input="calculateSalesPrice()" x-on:blur="validateField('purchase_price')" placeholder="Total with Tax" class="input-base tabular-nums">
                            @error('purchase_price')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.purchase_price" x-text="clientErrors.purchase_price" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Tax Type <span class="text-danger">*</span></label>
                            <div class="relative">
                                <select name="tax_type" x-model="taxType" @change="calculatePurchasePrice()" class="input-base appearance-none cursor-pointer pr-8">
                                    <option value="Inclusive">Inclusive</option>
                                    <option value="Exclusive">Exclusive</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                            @error('tax_type')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Profit(%)
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <input type="number" name="profit_margin" x-model.number="profitMargin"
                                   :readonly="isBox"
                                   :class="isBox ? 'input-base !bg-slate-100/50 dark:!bg-slate-700/30' : 'input-base'"
                                   @input="calculateSalesPrice()" placeholder="Profit %" class="input-base tabular-nums">
                            @error('profit_margin')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Sales Price <span class="text-danger">*</span>
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <input type="number" step="0.01" name="sales_price" x-model.number="salesPrice"
                                   :readonly="isBox"
                                   :class="isBox ? 'input-base !bg-slate-100/50 dark:!bg-slate-700/30' : 'input-base text-primary-600 dark:text-primary-400 font-black'"
                                   @input="calculateProfitMargin()" x-on:blur="validateField('sales_price')" placeholder="Sales Price" class="input-base tabular-nums">
                             @error('sales_price')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                             @enderror
                             <span x-show="clientErrors.sales_price" x-text="clientErrors.sales_price" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">MRP
                                <template x-if="isBox">
                                    <span class="text-[7px] text-amber-500">(Master)</span>
                                </template>
                            </label>
                            <input type="number" step="0.01" name="mrp" value="{{ old('mrp', $item->mrp) }}"
                                   :readonly="isBox"
                                   :class="isBox ? 'input-base !bg-slate-100/50 dark:!bg-slate-700/30' : 'input-base'"
                                   placeholder="Max Retail Price" class="input-base tabular-nums">
                            @error('mrp')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                    </div>

                <!-- ═════════════ SECTION 3: STOCK & SERIALS ═════════════ -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-sky-500 rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Stock & Serials</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Warehouse <span class="text-danger">*</span></label>
                            <x-searchable-select name="warehouse_id" model="warehouseId" :options="$warehouses" labelKey="warehouse_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Warehouse" :value="old('warehouse_id', $warehouse_id)" required />
                            @error('warehouse_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.warehouse_id" x-text="clientErrors.warehouse_id" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            <p class="text-[8px] font-bold text-text-muted mt-1 ml-0.5 italic">Switching warehouse loads that warehouse's stock.</p>
                        </div>

                        <div class="md:col-span-2" x-show="isSingle">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Stock in <span x-text="warehouseName">Selected Warehouse</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </span>
                                <input type="number" name="opening_stock" x-model.number="openingStock"
                                       :readonly="isBox"
                                       :class="isBox ? 'input-base !pl-10 !bg-slate-100/50 dark:!bg-slate-700/50' : 'input-base !pl-10 cursor-text'"
                                       @input="$nextTick(() => updateSerials())"
                                       class="input-base tabular-nums">
                            </div>
                            @error('opening_stock')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="isSingle">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Total All Warehouses</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                                </span>
                                <input type="text" :value="totalAllWarehouseStock" readonly
                                       class="input-base !pl-10 tabular-nums text-text-secondary bg-transparent cursor-default">
                            </div>
                            <p class="text-[8px] font-bold text-text-muted mt-1 ml-0.5 italic">Read-only: sum of all warehouse stock.</p>
                        </div>
                    </div>
                    </div>

                    <!-- SLN INPUT GRID (Single, serialized) -->
                    <div x-show="enableSerials && isSingle && openingStock > 0" x-cloak class="mb-8">
                        <div class="rounded-2xl border border-border dark:border-dark-border overflow-hidden animate-fade-in">
                            <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-800/30 border-b border-border dark:border-dark-border flex items-center justify-between flex-wrap gap-2">
                                <h3 class="text-[10px] font-black uppercase tracking-widest text-text-primary flex items-center gap-2">
                                    <span class="w-1.5 h-4 bg-primary rounded-full"></span>
                                    Item Serials
                                    <span class="px-2 py-0.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-[8px] font-bold rounded-full" x-text="openingStock + ' Units'"></span>
                                </h3>
                                <input type="text" placeholder="Bulk Paste (comma/line separated)..."
                                       @input.debounce.500ms="
                                        const sns = $event.target.value.split(/[,|\n]/).map(s => s.trim()).filter(s => s !== '');
                                        sns.forEach((s, i) => { if(i < serialNumbers.length) serialNumbers[i] = s; });
                                       "
                                       class="w-48 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg py-1 px-3 text-[8px] font-bold outline-none">
                            </div>
                            <div class="p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-10 gap-3">
                                <template x-for="(sn, idx) in serialNumbers" :key="idx">
                                    <div class="relative">
                                        <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[7px] font-black uppercase text-text-muted tracking-widest z-10 italic" x-text="'SLN ' + (idx + 1)"></label>
                                        <input type="text" :name="'serial_numbers[]'" x-model="serialNumbers[idx]" required
                                               :class="serialErrors[idx] ? 'input-base !py-1.5 !px-2 !text-xs !ring-2 !ring-rose-500/60 !border-rose-500 uppercase tracking-wider' : 'input-base !py-1.5 !px-2 !text-xs uppercase tracking-wider'">
                                        <template x-if="serialErrors[idx]">
                                            <p class="text-[8px] text-danger font-bold mt-1 ml-0.5" x-text="serialErrors[idx]"></p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>





                    <!-- SECTION 4: VARIANTS (Visible only when 'Box' is selected) -->
                    <div x-show="isBox" x-cloak class="space-y-4">

                        <div class="h-px bg-slate-50 dark:bg-slate-800"></div>
                        
                        <div class="flex items-center justify-between px-1 mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-1 h-3.5 bg-amber-500 rounded-full"></div>
                                <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Variant Details</h2>
                            </div>
                            <button type="button" @click="addVariant()" class="btn-secondary !py-1.5 !px-3 text-xs font-bold flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                Add Variant
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-border dark:border-dark-border">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-primary">
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10">Variant Name <span class="text-rose-200">*</span></th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10">SKU</th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10">HSN</th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10">Barcode</th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10 text-center">Price({{ $currencySymbol }}) <span class="text-rose-200">*</span></th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10 text-center">Purchase Price({{ $currencySymbol }}) <span class="text-rose-200">*</span></th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10 text-center">Profit Margin</th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10 text-center">Selling Price({{ $currencySymbol }}) <span class="text-rose-200">*</span></th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white border-r border-white/10 text-center text-nowrap">Stock in Selected Warehouse</th>
                                        <th class="px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-dark-border">
                                    <template x-for="(variant, index) in variants" :key="index">
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors align-top">
                                            <td class="py-2.5 px-3">
                                                <input type="hidden" x-model="variant.id" :name="'variants[' + index + '][id]'">
                                                <input type="text" x-model="variant.name" :name="'variants[' + index + '][name]'" placeholder="E.g. Red / XL" class="input-base !py-1.5 !px-2.5 !text-xs" :class="getVariantError(index, 'name') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">

                                                <template x-if="getVariantError(index, 'name')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'name')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="text" x-model="variant.sku" :name="'variants[' + index + '][sku]'" placeholder="Optional" class="input-base !py-1.5 !px-2.5 !text-xs" :class="getVariantError(index, 'sku') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                <template x-if="getVariantError(index, 'sku')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'sku')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="text" x-model="variant.hsn" :name="'variants[' + index + '][hsn]'" placeholder="Optional" class="input-base !py-1.5 !px-2.5 !text-xs">
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="text" x-model="variant.barcode" :name="'variants[' + index + '][barcode]'" placeholder="Optional" class="input-base !py-1.5 !px-2.5 !text-xs" :class="getVariantError(index, 'barcode') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                <template x-if="getVariantError(index, 'barcode')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'barcode')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="number" step="0.01" x-model.number="variant.price" @input="calculateVariant(index, 'price')" :name="'variants[' + index + '][price]'" placeholder="0.00" class="input-base !py-1.5 !px-2.5 !text-xs tabular-nums" :class="getVariantError(index, 'price') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                <template x-if="getVariantError(index, 'price')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'price')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="number" step="0.01" x-model.number="variant.purchase_price" @input="calculateVariant(index, 'purchase_price')" :name="'variants[' + index + '][purchase_price]'" placeholder="0.00" class="input-base !py-1.5 !px-2.5 !text-xs tabular-nums" :class="getVariantError(index, 'purchase_price') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                <template x-if="getVariantError(index, 'purchase_price')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'purchase_price')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="number" x-model.number="variant.profit" @input="calculateVariant(index, 'profit')" :name="'variants[' + index + '][profit]'" placeholder="0" class="input-base !py-1.5 !px-2.5 !text-xs tabular-nums">
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <input type="number" step="0.01" x-model.number="variant.sales_price" @input="calculateVariant(index, 'sales_price')" :name="'variants[' + index + '][sales_price]'" placeholder="0.00" class="input-base !py-1.5 !px-2.5 !text-xs tabular-nums text-primary-600 dark:text-primary-400 font-black" :class="getVariantError(index, 'sales_price') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                <template x-if="getVariantError(index, 'sales_price')">
                                                    <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'sales_price')"></p>
                                                </template>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="relative">
                                                    <input type="number" x-model.number="variant.stock" :name="'variants[' + index + '][stock]'" @input="updateVariantSerials(index); calculateTotalStock()" class="input-base !py-1.5 !px-2.5 !text-xs tabular-nums" :class="getVariantError(index, 'stock') ? '!ring-2 !ring-rose-500/60 !border-rose-500' : ''">
                                                    <template x-if="getVariantError(index, 'stock')">
                                                        <p class="text-[8px] text-danger font-bold mt-1" x-text="getVariantError(index, 'stock')"></p>
                                                    </template>
                                                    <template x-if="enableSerials && variant.stock > 0">
                                                        <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="p-2 text-center">
                                                <button type="button" @click="removeVariant(index)" class="w-7 h-7 bg-rose-50 dark:bg-rose-500/10 text-rose-500 rounded-lg inline-flex items-center justify-center hover:bg-rose-100 dark:hover:bg-rose-500/20 transition-all border border-rose-100/50 dark:border-rose-500/20">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </td>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- VARIANT SERIALS SECTION (Box, serialized) -->
                    <div x-show="enableSerials && isBox && variants.some(v => v.stock > 0)" x-cloak class="mb-8 space-y-4 animate-fade-in">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-text-primary mb-4 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-primary rounded-full"></span>
                            Variant Serial Numbers Entry
                        </h3>

                            <template x-for="(variant, vIdx) in variants" :key="vIdx">
                                <div x-show="variant.stock > 0" class="mb-6 rounded-2xl border border-border dark:border-dark-border overflow-hidden animate-fade-in">

                                    <div class="bg-slate-50/50 dark:bg-slate-800/30 px-4 py-2 border-b border-border dark:border-dark-border flex items-center justify-between flex-wrap gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-text-primary" x-text="'Serials for: ' + (variant.name || 'Unnamed Variant')"></span>
                                            <span class="px-2 py-0.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-[8px] font-bold rounded-full" x-text="(variant.stock || 0) + ' Units'"></span>
                                        </div>
                                        <input type="text" placeholder="Bulk Paste (comma/line separated)..."
                                               @input.debounce.500ms="
                                                const sns = $event.target.value.split(/[,|\n]/).map(s => s.trim()).filter(s => s !== '');
                                                sns.forEach((s, i) => { if(i < variant.serials.length) variant.serials[i] = s; });
                                               "
                                               class="w-48 bg-card dark:bg-dark-card border border-border dark:border-dark-border rounded-lg py-1 px-3 text-[8px] font-bold outline-none">
                                    </div>
                                    <div class="p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-10 gap-3">
                                        <template x-for="(sn, snIndex) in variant.serials" :key="snIndex">
                                            <div class="relative">
                                                <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[7px] font-black uppercase text-text-muted tracking-widest z-10 italic" x-text="'SLN ' + (snIndex + 1)"></label>
                                                <input type="text" :name="'variant_serials['+vIdx+'][]'" x-model="variant.serials[snIndex]" required
                                                       :class="variantSerialErrors(vIdx, snIndex, variant.serials[snIndex]) ? 'input-base !py-1.5 !px-2 !text-xs !ring-2 !ring-rose-500/60 !border-rose-500 uppercase tracking-wider' : 'input-base !py-1.5 !px-2 !text-xs uppercase tracking-wider'">
                                                <template x-if="variantSerialErrors(vIdx, snIndex, variant.serials[snIndex])">
                                                    <p class="text-[8px] text-danger font-bold mt-1 ml-0.5" x-text="variantSerialErrors(vIdx, snIndex, variant.serials[snIndex])"></p>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>


                    <!-- FORM FOOTER -->
                    <div class="flex flex-col md:flex-row justify-end items-center gap-3 mt-8 pt-8 border-t border-border dark:border-dark-border">
                        <button type="submit" class="btn-primary w-full md:w-48" :disabled="isSubmitting">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Save Item
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500"></div>
    <!-- QUICK ADD MODALS -->
    
    <!-- Brand Modal -->
    <template x-teleport="body">
        <div x-show="showBrandModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" x-cloak>
            <div @click.away="showBrandModal = false" class="bg-white dark:bg-dark-card w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-100 dark:border-dark-border animate-fade-in">
                <div class="p-6 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-white">Quick Add Brand</h3>
                    <button @click="showBrandModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'brand_id', 'showBrandModal')" action="{{ route('items.brands.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Brand Name *</label>
                        <input type="text" name="brand_name" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Brand Code</label>
                        <input type="text" name="brand_code" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all">Save Brand</button>
                        <button type="button" @click="showBrandModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Category Modal -->
    <template x-teleport="body">
        <div x-show="showCategoryModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" x-cloak>
            <div @click.away="showCategoryModal = false" class="bg-white dark:bg-dark-card w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-100 dark:border-dark-border animate-fade-in">
                <div class="p-6 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-white">Quick Add Category</h3>
                    <button @click="showCategoryModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'category_id', 'showCategoryModal')" action="{{ route('items.categories.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Category Name *</label>
                        <input type="text" name="category_name" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Category Code</label>
                        <input type="text" name="category_code" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all">Save Category</button>
                        <button type="button" @click="showCategoryModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Unit Modal -->
    <template x-teleport="body">
        <div x-show="showUnitModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" x-cloak>
            <div @click.away="showUnitModal = false" class="bg-white dark:bg-dark-card w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-100 dark:border-dark-border animate-fade-in">
                <div class="p-6 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-white">Quick Add Unit</h3>
                    <button @click="showUnitModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'unit_id', 'showUnitModal')" action="{{ route('settings.units.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="status" value="1">
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Unit Name *</label>
                        <input type="text" name="unit_name" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Description</label>
                        <input type="text" name="description" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all">Save Unit</button>
                        <button type="button" @click="showUnitModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Tax Modal -->
    <template x-teleport="body">
        <div x-show="showTaxModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" x-cloak>
            <div @click.away="showTaxModal = false" class="bg-white dark:bg-dark-card w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-100 dark:border-dark-border animate-fade-in">
                <div class="p-6 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-white">Quick Add Tax</h3>
                    <button @click="showTaxModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitQuickAdd($event, 'tax_id', 'showTaxModal')" action="{{ route('settings.tax.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="group_bit" value="0">
                    <input type="hidden" name="status" value="1">
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Tax Name *</label>
                        <input type="text" name="tax_name" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="group relative">
                        <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Tax Percentage (%) *</label>
                        <input type="number" step="0.01" name="tax" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-grow py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all">Save Tax</button>
                        <button type="button" @click="showTaxModal = false" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
        </div>
    </div>
</x-app-layout>
