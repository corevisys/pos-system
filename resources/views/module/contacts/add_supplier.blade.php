<x-app-layout :title="isset($supplier) ? 'Edit Supplier' : 'Add Supplier'">
    <div x-data="supplierValidation()">
        
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Suppliers <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">{{ isset($supplier) ? 'Edit Supplier' : 'Add Supplier' }}</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('contacts.suppliers.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Suppliers List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">{{ isset($supplier) ? 'Edit Supplier' : 'Add Supplier' }}</span>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="{{ route('contacts.suppliers.list') }}" class="btn-secondary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button form="supplierForm" type="submit" class="btn-primary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    {{ isset($supplier) ? 'Update Supplier' : 'Save Supplier' }}
                </button>
            </div>
        </div>

        <!-- Server-side Error Alert -->
        @if ($errors->any())
            <div class="mb-4 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl flex items-center gap-3">
                <div class="w-8 h-8 bg-rose-500 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <p class="text-[11px] font-black text-danger uppercase tracking-wider">Please correct the following errors:</p>
                    <ul class="text-[10px] text-danger font-medium mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <!-- Client-side Validation Error Alert -->
        <template x-if="Object.keys(errors).length > 0 && isSubmitted">
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="mb-4 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl flex items-center gap-3">
                <div class="w-8 h-8 bg-rose-500 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <p class="text-[11px] font-black text-danger uppercase tracking-wider">Validation Error: Please correct the highlighted fields before saving.</p>
            </div>
        </template>

        <!-- MAIN FORM -->
        <div class="card p-4 md:p-6">
            <form id="supplierForm" 
                  action="{{ isset($supplier) ? route('contacts.suppliers.update', $supplier->id) : route('contacts.suppliers.store') }}" 
                  method="POST" 
                  enctype="multipart/form-data"
                  @submit.prevent="isSubmitted = true; if(validateForm()) $el.submit();">
                @csrf
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-4">
                    
                    <!-- Left Column: Contact Details -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1 px-1">
                            <div class="w-1 h-3.5 bg-primary rounded-full"></div>
                            <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Supplier Overview</h2>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" value="{{ old('supplier_name', $supplier->supplier_name ?? '') }}" 
                                   x-on:blur="validateField('supplier_name')"
                                   required placeholder="Legal or trade name" class="input-base">
                            <span x-show="errors.supplier_name" x-text="errors.supplier_name" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Mobile Phone (11 digits)</label>
                            <input type="text" name="mobile" value="{{ old('mobile', $supplier->mobile ?? '') }}" 
                                   x-on:blur="validateField('mobile')"
                                   placeholder="01XXXXXXXXX" class="input-base">
                            <span x-show="errors.mobile" x-text="errors.mobile" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Email Address</label>
                            <input type="email" name="email" value="{{ old('email', $supplier->email ?? '') }}" 
                                   x-on:blur="validateField('email')"
                                   placeholder="supplier@example.com" class="input-base">
                            <span x-show="errors.email" x-text="errors.email" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Phone (Landline / Secondary)</label>
                            <input type="text" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}" 
                                   x-on:blur="validateField('phone')"
                                   placeholder="01XXXXXXXXX" class="input-base">
                            <span x-show="errors.phone" x-text="errors.phone" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">GSTIN</label>
                                <input type="text" name="gstin" value="{{ old('gstin', $supplier->gstin ?? '') }}" placeholder="GST Number" class="input-base">
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">TAX Number</label>
                                <input type="text" name="tax_number" value="{{ old('tax_number', $supplier->tax_number ?? '') }}" placeholder="Tax ID" class="input-base">
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">VAT Number</label>
                                <input type="text" name="vatin" value="{{ old('vatin', $supplier->vatin ?? '') }}" placeholder="VATIN" class="input-base">
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Financial & Location Details -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-1 px-1">
                            <div class="w-1 h-3.5 bg-emerald-500 rounded-full"></div>
                            <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Financial & Location</h2>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Opening Balance</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $supplier->opening_balance ?? '0.00') }}" 
                                       x-on:blur="validateField('opening_balance')"
                                       class="input-base !pl-8 text-emerald-600 font-black">
                            </div>
                            <span x-show="errors.opening_balance" x-text="errors.opening_balance" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Country</label>
                            <x-searchable-select name="country_id" :options="$countries" labelKey="country" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Country" :value="old('country_id', $supplier->country_id ?? '')" />
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">State</label>
                            <x-searchable-select name="state_id" :options="$states" labelKey="state" valueKey="id" emptyOption="Select State" emptyValue="" placeholder="Select State" :value="old('state_id', $supplier->state_id ?? '')" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">City</label>
                                <input type="text" name="city" value="{{ old('city', $supplier->city ?? '') }}" placeholder="City Name" class="input-base">
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Postcode</label>
                                <input type="text" name="postcode" value="{{ old('postcode', $supplier->postcode ?? '') }}" placeholder="Postal / Zip Code" class="input-base">
                            </div>
                        </div>

                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Address</label>
                            <textarea name="address" rows="2" placeholder="Full Business Address" class="input-base">{{ old('address', $supplier->address ?? '') }}</textarea>
                        </div>
                    </div>
                    
                    <div class="lg:col-span-2 space-y-4 pt-4 border-t border-border dark:border-dark-border mt-2">
                         <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-4">
                             <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Location Link</label>
                                <input type="text" name="location_link" value="{{ old('location_link', $supplier->location_link ?? '') }}" placeholder="Google Maps URL" class="input-base">
                             </div>
                             <div>
                                <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Attachment (Document / Contract)</label>
                                @if(isset($supplier) && $supplier->attachment_1)
                                    <div class="mb-2 text-[9px] text-text-muted flex items-center gap-2">
                                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Existing: <a href="{{ asset('storage/' . $supplier->attachment_1) }}" target="_blank" class="text-primary hover:underline font-bold">View File</a>
                                    </div>
                                @endif
                                <input type="file" name="attachment_1" 
                                       x-on:change="validateField('attachment_1')"
                                       class="input-base file:mr-4 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[9px] file:font-black file:uppercase file:bg-primary-50 file:text-primary hover:file:bg-primary-100">
                                <span x-show="errors.attachment_1" x-text="errors.attachment_1" class="text-[9px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                             </div>
                         </div>
                    </div>

                </div>
            </form>
        </div>

    </div>

    @push('scripts')
    <script>
        function supplierValidation() {
            return {
                errors: {},
                isSubmitted: false,
                
                validateField(field) {
                    const form = document.getElementById('supplierForm');
                    const formData = new FormData(form);
                    const value = formData.get(field);
                    
                    delete this.errors[field];

                    if (field === 'supplier_name') {
                        if (!value || value.trim() === '') {
                            this.errors.supplier_name = 'Supplier name is required';
                        }
                    }

                    if (field === 'mobile' && value) {
                        const regex = /^\d{11}$/;
                        if (!regex.test(value)) {
                            this.errors.mobile = 'Mobile must be exactly 11 digits';
                        }
                    }

                    if (field === 'phone' && value) {
                        const regex = /^\d{11}$/;
                        if (!regex.test(value)) {
                            this.errors.phone = 'Phone must be exactly 11 digits';
                        }
                    }

                    if (field === 'email' && value) {
                        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!regex.test(value)) {
                            this.errors.email = 'Please enter a valid email';
                        }
                    }

                    if (field === 'opening_balance') {
                        const numValue = parseFloat(value);
                        if (isNaN(numValue) || numValue < 0) {
                            this.errors.opening_balance = 'Opening balance must be zero or positive';
                        }
                    }

                    if (field === 'attachment_1') {
                        const fileInput = form.querySelector('input[name="attachment_1"]');
                        if (fileInput && fileInput.files.length > 0) {
                            const file = fileInput.files[0];
                            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                            const maxSize = 2048 * 1024; // 2MB

                            if (!allowedTypes.includes(file.type)) {
                                this.errors.attachment_1 = 'Only images and PDFs are allowed';
                            } else if (file.size > maxSize) {
                                this.errors.attachment_1 = 'File size must be under 2MB';
                            }
                        }
                    }
                },

                validateForm() {
                    this.errors = {};
                    const fields = ['supplier_name', 'mobile', 'phone', 'email', 'opening_balance', 'attachment_1'];
                    fields.forEach(field => this.validateField(field));
                    
                    if (Object.keys(this.errors).length > 0) {
                        this.scrollToFirstError();
                        return false;
                    }
                    return true;
                },

                scrollToFirstError() {
                    this.$nextTick(() => {
                        const firstError = document.querySelector('[x-show*="errors"]');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }
            }
        }
    </script>
    @endpush
</x-app-layout>