<x-app-layout title="Edit Service">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Services <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Edit Service</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.service.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Services List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">Edit Service</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Service Catalog Management</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('items.service.list') }}" class="btn-secondary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button form="serviceForm" type="submit" class="btn-primary" x-ref="submitBtn">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    Update Service
                </button>
            </div>
        </div>

        <!-- Informational edit-after-sale warning (warn, don't block) -->
        @if(isset($salesHistoryCount) && $salesHistoryCount > 0)
            <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400 leading-relaxed">
                    This service has been sold or quoted {{ $salesHistoryCount }} time(s). Changing its name or price will not affect past invoices, but may cause it to display differently in historical reports.
                </p>
            </div>
        @endif

        <!-- Server-side Error Alert -->
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

        <!-- Client-side Validation Error Alert -->
        <template x-if="Object.keys(clientErrors).length > 0 && isSubmitted">
            <div class="mb-4 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl flex items-center gap-3">
                <div class="w-8 h-8 bg-rose-500 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <p class="text-[11px] font-black text-danger uppercase tracking-wider">Validation Error: Please correct the highlighted fields before saving.</p>
            </div>
        </template>

        <!-- MAIN FORM -->
        <div class="card p-4 md:p-6" x-data="editServiceForm()">
            <form id="serviceForm"
                  action="{{ route('items.service.update', $service->id) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  @submit.prevent="isSubmitting = true; if(validateForm()) { setButtonLoading($refs.submitBtn, 'Saving...'); $el.submit(); } else { isSubmitting = false; resetButtonLoading($refs.submitBtn); }">
                @csrf

                <!-- ═════════════ SECTION 1: SERVICE INFORMATION ═════════════ -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-primary rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Service Information</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Service Name <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" value="{{ old('item_name', $service->item_name) }}"
                                   x-on:blur="validateField('item_name')"
                                   class="input-base">
                            @error('item_name')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.item_name" x-text="clientErrors.item_name" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Category <span class="text-danger">*</span></label>
                            <x-searchable-select name="category_id" :options="$categories" labelKey="category_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Category" :value="old('category_id', $service->category_id)" required />
                            @error('category_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Item Code</label>
                            <input type="text" name="item_code" value="{{ old('item_code', $service->item_code) }}" class="input-base font-mono tracking-wider" readonly>
                            @error('item_code')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">HSN</label>
                            <input type="text" name="hsn" value="{{ old('hsn', $service->hsn) }}" class="input-base">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Seller Points</label>
                            <input type="number" name="seller_points" value="{{ old('seller_points', $service->seller_points) }}" class="input-base tabular-nums">
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Description</label>
                            <input type="text" name="description" value="{{ old('description', $service->description) }}" class="input-base">
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Image</label>
                            <input type="file" name="item_image" accept="image/*" class="input-base !py-1.5 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-[10px] file:font-black file:text-primary">
                            @if($service->item_image)
                                <p class="text-[8px] text-text-muted mt-1 uppercase tracking-widest">Current: {{ basename($service->item_image) }}</p>
                            @endif
                            @error('item_image')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
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
                                    <option value="Percentage(%)">Percentage(%)</option>
                                    <option value="Fixed Amount">Fixed Amount</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Discount</label>
                            <div class="relative">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]" x-text="discountType === 'Percentage(%)' ? '%' : '{{ $currencySymbol }}'"></span>
                                <input type="number" step="0.01" name="discount" value="{{ old('discount', $service->discount) }}" class="input-base pr-8 tabular-nums">
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Price <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" name="price" value="{{ old('price', $service->price) }}" class="input-base pl-7 tabular-nums">
                            </div>
                            @error('price')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.price" x-text="clientErrors.price" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Tax</label>
                            <x-searchable-select name="tax_id" :options="$taxes" labelKey="tax_name" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Tax" :value="old('tax_id', $service->tax_id)" />
                            @error('tax_id')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Sales Tax Type <span class="text-danger">*</span></label>
                            <div class="relative">
                                <select name="tax_type" x-model="taxType" class="input-base appearance-none cursor-pointer pr-8">
                                    <option value="Inclusive">Inclusive</option>
                                    <option value="Exclusive">Exclusive</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Sales Price <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-primary font-bold text-[10px]">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" name="sales_price" value="{{ old('sales_price', $service->sales_price) }}" class="input-base pl-7 tabular-nums !border-primary/30 !bg-primary/5">
                            </div>
                            @error('sales_price')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                            <span x-show="clientErrors.sales_price" x-text="clientErrors.sales_price" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @php
        $oldDiscountType = old('discount_type', $service->discount_type ?? 'Percentage(%)');
        $oldTaxType = old('tax_type', $service->tax_type ?? 'Inclusive');
    @endphp
    @push('scripts')
    <script>
        function editServiceForm() {
            return {
                discountType: @json($oldDiscountType),
                taxType: @json($oldTaxType),
                clientErrors: {},
                isSubmitted: false,
                isSubmitting: false,

                validateField(field) {
                    const form = document.getElementById('serviceForm');
                    const formData = new FormData(form);
                    const value = formData.get(field);
                    delete this.clientErrors[field];

                    if (field === 'item_name') {
                        if (!value || value.trim() === '') {
                            this.clientErrors.item_name = 'Service name is required';
                        }
                    }

                    if (field === 'price' && value !== null && value !== '') {
                        const num = parseFloat(value);
                        if (isNaN(num) || num < 0) {
                            this.clientErrors.price = 'Price must be zero or positive';
                        }
                    }

                    if (field === 'sales_price' && (value === null || value === '' || isNaN(parseFloat(value)) || parseFloat(value) < 0)) {
                        this.clientErrors.sales_price = 'Sales price must be zero or positive';
                    }
                },

                validateForm() {
                    this.clientErrors = {};
                    const fields = ['item_name', 'category_id', 'price', 'sales_price', 'tax_type'];
                    fields.forEach(field => this.validateField(field));
                    if (Object.keys(this.clientErrors).length > 0) {
                        return false;
                    }
                    return true;
                }
            };
        }
        document.addEventListener('alpine:init', () => {
            Alpine.data('editServiceForm', editServiceForm);
        });
    </script>
    @endpush
</x-app-layout>
