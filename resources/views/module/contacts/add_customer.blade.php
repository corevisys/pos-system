<x-app-layout title="Add Customer">
    <div x-data="customerForm({
        customer_name: @js(old('customer_name', $customer->customer_name ?? '')),
        customer_type: @js(old('customer_type', $customer->customer_type ?? '')),
        customer_id_card: @js(old('customer_id_card', $customer->customer_id_card ?? '')),
        father_name: @js(old('father_name', $customer->father_name ?? '')),
        mother_name: @js(old('mother_name', $customer->mother_name ?? '')),
        dob: @js(old('dob', $customer->dob ?? '')),
        mobile: @js(old('mobile', $customer->mobile ?? $customer->mobile_primary ?? '')),
        mobile_primary: @js(old('mobile_primary', $customer->mobile_primary ?? '')),
        mobile_secondary: @js(old('mobile_secondary', $customer->mobile_secondary ?? '')),
        email: @js(old('email', $customer->email ?? '')),
        phone: @js(old('phone', $customer->phone ?? '')),
        gstin: @js(old('gstin', $customer->gstin ?? '')),
        tax_number: @js(old('tax_number', $customer->tax_number ?? '')),
        vatin: @js(old('vatin', $customer->vatin ?? '')),
        credit_limit: @js(old('credit_limit', $customer->credit_limit ?? '0.00')),
        opening_balance: @js(old('opening_balance', $customer->opening_balance ?? '0.00')),
        price_level_type: @js(old('price_level_type', $customer->price_level_type ?? 'Increase')),
        price_level: @js(old('price_level', $customer->price_level ?? '0')),
        location_link: @js(old('location_link', $customer->location_link ?? '')),
        occupation: @js(old('occupation', $customer->occupation ?? '')),
        monthly_income: @js(old('monthly_income', $customer->monthly_income ?? '')),
        workplace_name: @js(old('workplace_name', $customer->workplace_name ?? '')),
        workplace_address: @js(old('workplace_address', $customer->workplace_address ?? '')),
        present_address: @js(old('present_address', $customer->present_address ?? '')),
        permanent_address: @js(old('permanent_address', $customer->permanent_address ?? '')),
        country_id: @js(old('country_id', $customer->country_id ?? '')),
        state_id: @js(old('state_id', $customer->state_id ?? '')),
        city: @js(old('city', $customer->city ?? '')),
        postcode: @js(old('postcode', $customer->postcode ?? '')),
        address: @js(old('address', $customer->address ?? '')),
        ship_country_id: @js(old('ship_country_id', $customer->ship_country_id ?? '')),
        ship_state_id: @js(old('ship_state_id', $customer->ship_state_id ?? '')),
        ship_city: @js(old('ship_city', $customer->ship_city ?? '')),
        ship_postcode: @js(old('ship_postcode', $customer->ship_postcode ?? '')),
        ship_address: @js(old('ship_address', $customer->ship_address ?? '')),
        g_name: @js(old('g_name', $customer->guardian->name ?? '')),
        g_relationship: @js(old('g_relationship', $customer->guardian->relationship ?? '')),
        g_mobile: @js(old('g_mobile', $customer->guardian->mobile ?? '')),
        gr_name: @js(old('gr_name', $customer->guarantor->name ?? '')),
        gr_father_name: @js(old('gr_father_name', $customer->guarantor->father_name ?? '')),
        gr_address: @js(old('gr_address', $customer->guarantor->address ?? '')),
        gr_mobile: @js(old('gr_mobile', $customer->guarantor->mobile ?? '')),
        gr_occupation: @js(old('gr_occupation', $customer->guarantor->occupation ?? '')),
        gr_monthly_income: @js(old('gr_monthly_income', $customer->guarantor->monthly_income ?? ''))
    }, @js($customer->id ?? null))" @submit.prevent="">
        
        <form action="{{ isset($customer) ? route('contacts.customers.update', $customer->id) : route('contacts.customers.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if(isset($customer))
                @method('PUT')
            @endif
            
            <!-- HEADER & ACTIONS -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
                <div>
                    <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Customers <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">{{ isset($customer) ? 'Edit Customer' : 'Add Customer' }}</span></h1>
                    <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                             <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                             Home
                        </a>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        <a href="{{ route('contacts.customers.list') }}" class="hover:text-primary transition-colors text-[10px] font-black uppercase tracking-wider text-text-muted">Customers List</a>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">{{ isset($customer) ? 'Edit Customer' : 'Add Customer' }}</span>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    {{-- Global actions removed for wizard flow --}}
                </div>
            </div>



            <!-- MAIN FORM -->
            <div class="space-y-4">
                
                <div class="flex flex-wrap gap-2 border-b border-border dark:border-dark-border pb-2">
                     <button type="button" @click="activeTab = 'basic'" :class="activeTab === 'basic' ? 'text-primary-600 bg-primary-50 dark:bg-primary-500/10 border-primary-100 dark:border-primary-500/20' : 'text-text-muted hover:text-text-secondary'" class="flex items-center gap-2 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border border-transparent transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Basic Info
                     </button>
                     <button type="button" @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'text-primary-600 bg-primary-50 dark:bg-primary-500/10 border-primary-100 dark:border-primary-500/20' : 'text-text-muted hover:text-text-secondary'" class="flex items-center gap-2 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border border-transparent transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Documents
                     </button>
                     <button type="button" @click="activeTab = 'guardian'" x-show="customer.customer_type === 'emi'" :class="activeTab === 'guardian' ? 'text-primary-600 bg-primary-50 dark:bg-primary-500/10 border-primary-100 dark:border-primary-500/20' : 'text-text-muted hover:text-text-secondary'" class="flex items-center gap-2 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border border-transparent transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Guardian Info
                     </button>
                     <button type="button" @click="activeTab = 'guarantor'" x-show="customer.customer_type === 'emi'" :class="activeTab === 'guarantor' ? 'text-primary-600 bg-primary-50 dark:bg-primary-500/10 border-primary-100 dark:border-primary-500/20' : 'text-text-muted hover:text-text-secondary'" class="flex items-center gap-2 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border border-transparent transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Guarantor Info
                     </button>
                     <button type="button" @click="activeTab = 'advanced'" :class="activeTab === 'advanced' ? 'text-primary-600 bg-primary-50 dark:bg-primary-500/10 border-primary-100 dark:border-primary-500/20' : 'text-text-muted hover:text-text-secondary'" class="flex items-center gap-2 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg border border-transparent transition-all">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c.94-1.543-.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Advanced
                     </button>
                </div>

                <div x-show="activeTab === 'basic'" x-transition class="space-y-4">

                <!-- SECTION 2: BASIC INFO -->
                <div class="card p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4">
                        <!-- Basic Info Fields -->
                        <div>
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Customer Type <span class="text-danger">*</span></label>
                            <select name="customer_type" x-model="customer.customer_type" @change="validateField('customer_type')" @blur="validateField('customer_type')" required class="input-base cursor-pointer">
                                <option value="">Select Type</option>
                                <option value="regular">Regular Customer</option>
                                <option value="emi">EMI (Installment)</option>
                            </select>
                            <span x-show="errors.customer_type" x-text="errors.customer_type" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" x-model="customer.customer_name" @blur="validateField('customer_name')" required placeholder="Full Name" class="input-base">
                            <span x-show="errors.customer_name" x-text="errors.customer_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Customer ID Card <span class="text-danger">*</span></label>
                            <input type="text" name="customer_id_card" x-model="customer.customer_id_card" @blur="validateField('customer_id_card')" :required="customer.customer_type === 'emi'" placeholder="ID Number" class="input-base">
                            <span x-show="errors.customer_id_card" x-text="errors.customer_id_card" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Father's Name <span class="text-danger">*</span></label>
                            <input type="text" name="father_name" x-model="customer.father_name" @blur="validateField('father_name')" :required="customer.customer_type === 'emi'" placeholder="Father's Name" class="input-base">
                            <span x-show="errors.father_name" x-text="errors.father_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mother's Name <span class="text-danger">*</span></label>
                            <input type="text" name="mother_name" x-model="customer.mother_name" @blur="validateField('mother_name')" :required="customer.customer_type === 'emi'" placeholder="Mother's Name" class="input-base">
                            <span x-show="errors.mother_name" x-text="errors.mother_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" x-model="customer.dob" @blur="validateField('dob')" :required="customer.customer_type === 'emi'" class="input-base">
                            <span x-show="errors.dob" x-text="errors.dob" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mobile (Primary) <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" x-model="customer.mobile" @blur="validateField('mobile')" required placeholder="Primary Mobile" class="input-base">
                            <span x-show="errors.mobile" x-text="errors.mobile" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Email</label>
                            <input type="email" name="email" x-model="customer.email" @blur="validateField('email')" placeholder="Email Address" class="input-base">
                            <span x-show="errors.email" x-text="errors.email" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mobile (Secondary) <span class="text-danger">*</span></label>
                            <input type="text" name="mobile_secondary" x-model="customer.mobile_secondary" @blur="validateField('mobile_secondary')" :required="customer.customer_type === 'emi'" placeholder="Secondary Mobile" class="input-base">
                            <span x-show="errors.mobile_secondary" x-text="errors.mobile_secondary" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Occupation <span class="text-danger">*</span></label>
                            <input type="text" name="occupation" x-model="customer.occupation" @blur="validateField('occupation')" :required="customer.customer_type === 'emi'" placeholder="Occupation" class="input-base">
                            <span x-show="errors.occupation" x-text="errors.occupation" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Monthly Income <span class="text-danger">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" name="monthly_income" x-model="customer.monthly_income" @blur="validateField('monthly_income')" :required="customer.customer_type === 'emi'" placeholder="0.00" class="input-base !pl-10">
                                <span x-show="errors.monthly_income" x-text="errors.monthly_income" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Workplace Name <span class="text-danger">*</span></label>
                            <input type="text" name="workplace_name" x-model="customer.workplace_name" @blur="validateField('workplace_name')" :required="customer.customer_type === 'emi'" placeholder="Workplace Name" class="input-base">
                            <span x-show="errors.workplace_name" x-text="errors.workplace_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div class="md:col-span-2" x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Workplace Address <span class="text-danger">*</span></label>
                            <input type="text" name="workplace_address" x-model="customer.workplace_address" @blur="validateField('workplace_address')" :required="customer.customer_type === 'emi'" placeholder="Workplace Address" class="input-base">
                            <span x-show="errors.workplace_address" x-text="errors.workplace_address" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Present Address <span class="text-danger">*</span></label>
                            <textarea name="present_address" x-model="customer.present_address" @blur="validateField('present_address')" :required="customer.customer_type === 'emi'" rows="2" placeholder="Present Address" class="input-base"></textarea>
                            <span x-show="errors.present_address" x-text="errors.present_address" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                        <div class="md:col-span-2" x-show="customer.customer_type === 'emi'">
                            <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Permanent Address <span class="text-danger">*</span></label>
                            <textarea name="permanent_address" x-model="customer.permanent_address" @blur="validateField('permanent_address')" :required="customer.customer_type === 'emi'" rows="2" placeholder="Permanent Address" class="input-base"></textarea>
                            <span x-show="errors.permanent_address" x-text="errors.permanent_address" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: ADDRESS DETAILS -->
                <div>
                    <div class="flex items-center gap-2 mb-3 px-1">
                        <div class="w-1 h-5 bg-primary rounded-full"></div>
                        <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-text-secondary flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Address Details
                        </h2>
                    </div>
                    <div class="card p-4">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-4">
                            <!-- Billing Left -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Country</label>
                                    <x-searchable-select name="country_id" :options="$countries" labelKey="country" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Country" model="customer.country_id" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">City</label>
                                    <input type="text" name="city" x-model="customer.city" class="input-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Address</label>
                                    <textarea rows="2" name="address" x-model="customer.address" class="input-base"></textarea>
                                </div>
                            </div>
                            <!-- Billing Right -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">State</label>
                                    <x-searchable-select name="state_id" :options="$states" labelKey="state" valueKey="id" emptyOption="Select State" emptyValue="" placeholder="Select State" model="customer.state_id" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Postcode</label>
                                    <input type="text" name="postcode" x-model="customer.postcode" class="input-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Location Link</label>
                                    <input type="text" name="location_link" x-model="customer.location_link" class="input-base">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: SHIPPING ADDRESS -->
                <div>
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-5 bg-primary rounded-full"></div>
                            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-text-secondary flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                Shipping Address
                            </h2>
                        </div>
                        <label class="flex items-center gap-2 px-3 py-1 bg-background dark:bg-dark-bg/40 rounded-lg border border-border dark:border-dark-border self-start md:self-auto transition-all group cursor-pointer">
                            <input type="checkbox" x-model="copyAddress" @change="syncAddress()" class="w-3.5 h-3.5 rounded-md border-primary text-primary focus:ring-primary cursor-pointer transition-all">
                            <span class="text-[9px] font-black uppercase text-text-muted cursor-pointer select-none tracking-widest group-hover:text-primary transition-colors">Copy Billing Address ?</span>
                        </label>
                    </div>
                    <div class="card p-4 transition-all" :class="copyAddress ? 'opacity-40 pointer-events-none grayscale-[0.5]' : ''">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-4">
                            <!-- Shipping Left -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Country</label>
                                    <x-searchable-select name="ship_country_id" :options="$countries" labelKey="country" valueKey="id" emptyOption="-Select-" emptyValue="" placeholder="Select Country" model="customer.ship_country_id" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">City</label>
                                    <input type="text" name="ship_city" x-model="customer.ship_city" class="input-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Address</label>
                                    <textarea rows="2" name="ship_address" x-model="customer.ship_address" class="input-base"></textarea>
                                </div>
                            </div>
                            <!-- Shipping Right -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">State</label>
                                    <x-searchable-select name="ship_state_id" :options="$states" labelKey="state" valueKey="id" emptyOption="Select State" emptyValue="" placeholder="Select State" model="customer.ship_state_id" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Postcode</label>
                                    <input type="text" name="ship_postcode" x-model="customer.ship_postcode" class="input-base">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER ACTIONS FOR BASIC INFO -->
                <div class="mt-8 card p-4 flex justify-end gap-3">
                    <button type="button" 
                            @click="saveStep('basic')"
                            :disabled="isSaving"
                            class="btn-primary disabled:opacity-50">
                         <template x-if="!isSaving">
                            <span class="flex items-center gap-2">
                                Next: Documents
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                            </span>
                         </template>
                         <template x-if="isSaving">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Saving...
                            </span>
                         </template>
                    </button>
                </div> <!-- End Basic Info Footer Container -->
                </div> <!-- End Basic Info Tab -->

            <!-- DOCUMENTS TAB -->
            <div x-show="activeTab === 'documents'" x-transition class="space-y-4">
                    <div class="card p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Customer Photo -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">Customer Photo <span class="text-danger" x-show="customer.customer_type === 'emi'">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="photo" @change="imagePreview($event, 'photo')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->photo ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.photo && !{{ isset($customer) && $customer->photo ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">Photo</span>
                                    </div>
                                    <template x-if="previews.photo">
                                        <img :src="previews.photo" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->photo)
                                        <img x-show="!previews.photo" src="{{ asset('storage/' . $customer->photo) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.photo" x-text="errors.photo" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- NID Front -->
                            <div class="space-y-2" x-show="customer.customer_type === 'emi'">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">NID Front <span class="text-danger" x-show="customer.customer_type === 'emi'">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="nid_front" @change="imagePreview($event, 'nid_front')" :required="customer.customer_type === 'emi' && !{{ (isset($customer) && $customer->nid_front) ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.nid_front && !{{ (isset($customer) && $customer->nid_front) ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Front</span>
                                    </div>
                                    <template x-if="previews.nid_front">
                                        <img :src="previews.nid_front" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->nid_front)
                                        <img x-show="!previews.nid_front" src="{{ asset('storage/' . $customer->nid_front) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.nid_front" x-text="errors.nid_front" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- NID Back -->
                            <div class="space-y-2" x-show="customer.customer_type === 'emi'">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">NID Back <span class="text-danger" x-show="customer.customer_type === 'emi'">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="nid_back" @change="imagePreview($event, 'nid_back')" :required="customer.customer_type === 'emi' && !{{ (isset($customer) && $customer->nid_back) ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.nid_back && !{{ (isset($customer) && $customer->nid_back) ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Back</span>
                                    </div>
                                    <template x-if="previews.nid_back">
                                        <img :src="previews.nid_back" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->nid_back)
                                        <img x-show="!previews.nid_back" src="{{ asset('storage/' . $customer->nid_back) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.nid_back" x-text="errors.nid_back" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Job ID -->
                            <div class="space-y-2" x-show="customer.customer_type === 'emi'">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">Job ID Card <span class="text-danger" x-show="customer.customer_type === 'emi'">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="job_id_card" @change="imagePreview($event, 'job_id_card')" :required="customer.customer_type === 'emi' && !{{ (isset($customer) && $customer->job_id_card) ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.job_id_card && !{{ (isset($customer) && $customer->job_id_card) ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">Job ID</span>
                                    </div>
                                    <template x-if="previews.job_id_card">
                                        <img :src="previews.job_id_card" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->job_id_card)
                                        <img x-show="!previews.job_id_card" src="{{ asset('storage/' . $customer->job_id_card) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.job_id_card" x-text="errors.job_id_card" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS FOR DOCUMENTS -->
                    <div class="mt-8 card p-4 flex justify-between gap-3">
                        <button type="button" 
                                @click="activeTab = 'basic'"
                                class="btn-secondary">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                             Back
                        </button>
                        <button type="button" 
                                @click="saveStep('documents')"
                                :disabled="isSaving"
                                class="btn-primary disabled:opacity-50">
                             <template x-if="!isSaving">
                                <span class="flex items-center gap-2">
                                    <span x-text="customer.customer_type === 'emi' ? 'Next: Guardian' : 'Next: Advanced'"></span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                </span>
                             </template>
                             <template x-if="isSaving">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Saving...
                                </span>
                             </template>
                        </button>
                    </div>
                </div> <!-- End Documents Tab -->

                <!-- GUARDIAN TAB (EMI ONLY) -->
                <div x-show="activeTab === 'guardian'" x-transition class="space-y-4">
                    <div class="card p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Guardian Name <span class="text-danger">*</span></label>
                                <input type="text" name="g_name" x-model="customer.g_name" @blur="validateField('g_name')" :required="customer.customer_type === 'emi'" placeholder="Full Name" class="input-base">
                                <span x-show="errors.g_name" x-text="errors.g_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Relationship <span class="text-danger">*</span></label>
                                <input type="text" name="g_relationship" x-model="customer.g_relationship" @blur="validateField('g_relationship')" :required="customer.customer_type === 'emi'" placeholder="Spouse, Parent, etc." class="input-base">
                                <span x-show="errors.g_relationship" x-text="errors.g_relationship" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="g_mobile" x-model="customer.g_mobile" @blur="validateField('g_mobile')" :required="customer.customer_type === 'emi'" placeholder="Guardian's Mobile" class="input-base">
                                <span x-show="errors.g_mobile" x-text="errors.g_mobile" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6 pt-6 border-t border-border-light dark:border-dark-border">
                            <!-- Guardian NID Front -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">G. NID Front <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="g_nid_front" @change="imagePreview($event, 'g_nid_front')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guardian?->nid_front ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.g_nid_front && !{{ isset($customer) && $customer->guardian?->nid_front ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Front</span>
                                    </div>
                                    <template x-if="previews.g_nid_front">
                                        <img :src="previews.g_nid_front" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guardian && $customer->guardian->nid_front)
                                        <img x-show="!previews.g_nid_front" src="{{ asset('storage/' . $customer->guardian->nid_front) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.g_nid_front" x-text="errors.g_nid_front" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Guardian NID Back -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">G. NID Back <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="g_nid_back" @change="imagePreview($event, 'g_nid_back')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guardian?->nid_back ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.g_nid_back && !{{ isset($customer) && $customer->guardian?->nid_back ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Back</span>
                                    </div>
                                    <template x-if="previews.g_nid_back">
                                        <img :src="previews.g_nid_back" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guardian && $customer->guardian->nid_back)
                                        <img x-show="!previews.g_nid_back" src="{{ asset('storage/' . $customer->guardian->nid_back) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.g_nid_back" x-text="errors.g_nid_back" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Guardian Photo -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">G. Photo <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="g_photo" @change="imagePreview($event, 'g_photo')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guardian?->photo ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.g_photo && !{{ isset($customer) && $customer->guardian?->photo ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">Photo</span>
                                    </div>
                                    <template x-if="previews.g_photo">
                                        <img :src="previews.g_photo" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guardian && $customer->guardian->photo)
                                        <img x-show="!previews.g_photo" src="{{ asset('storage/' . $customer->guardian->photo) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.g_photo" x-text="errors.g_photo" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS FOR GUARDIAN -->
                    <div class="mt-8 card p-4 flex justify-between gap-3">
                        <button type="button" 
                                @click="activeTab = 'documents'"
                                class="btn-secondary">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                             Back
                        </button>
                        <button type="button" 
                                @click="saveStep('guardian')"
                                :disabled="isSaving"
                                class="btn-primary disabled:opacity-50">
                             <template x-if="!isSaving">
                                <span class="flex items-center gap-2">
                                    Next: Guarantor
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                </span>
                             </template>
                             <template x-if="isSaving">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Saving...
                                </span>
                             </template>
                        </button>
                    </div>
                </div>

                <!-- GUARANTOR TAB -->
                <div x-show="activeTab === 'guarantor'" x-transition class="space-y-4">
                    <div class="card p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Guarantor Name <span class="text-danger">*</span></label>
                                <input type="text" name="gr_name" x-model="customer.gr_name" @blur="validateField('gr_name')" :required="customer.customer_type === 'emi'" placeholder="Full Name" class="input-base">
                                <span x-show="errors.gr_name" x-text="errors.gr_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Father's Name <span class="text-danger">*</span></label>
                                <input type="text" name="gr_father_name" x-model="customer.gr_father_name" @blur="validateField('gr_father_name')" :required="customer.customer_type === 'emi'" placeholder="Father's Name" class="input-base">
                                <span x-show="errors.gr_father_name" x-text="errors.gr_father_name" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="gr_mobile" x-model="customer.gr_mobile" @blur="validateField('gr_mobile')" :required="customer.customer_type === 'emi'" placeholder="Guarantor's Mobile" class="input-base">
                                <span x-show="errors.gr_mobile" x-text="errors.gr_mobile" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Occupation <span class="text-danger">*</span></label>
                                <input type="text" name="gr_occupation" x-model="customer.gr_occupation" @blur="validateField('gr_occupation')" :required="customer.customer_type === 'emi'" class="input-base">
                                <span x-show="errors.gr_occupation" x-text="errors.gr_occupation" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Monthly Income <span class="text-danger">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" name="gr_monthly_income" x-model="customer.gr_monthly_income" @blur="validateField('gr_monthly_income')" :required="customer.customer_type === 'emi'" class="input-base !pl-10">
                                    <span x-show="errors.gr_monthly_income" x-text="errors.gr_monthly_income" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Guarantor Address <span class="text-danger">*</span></label>
                                <textarea name="gr_address" x-model="customer.gr_address" @blur="validateField('gr_address')" :required="customer.customer_type === 'emi'" rows="1" class="input-base"></textarea>
                                <span x-show="errors.gr_address" x-text="errors.gr_address" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6 pt-6 border-t border-border-light dark:border-dark-border">
                            <!-- Guarantor Photo -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">GR. Photo <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="gr_photo" @change="imagePreview($event, 'gr_photo')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guarantor?->photo ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.gr_photo && !{{ isset($customer) && $customer->guarantor?->photo ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">Photo</span>
                                    </div>
                                    <template x-if="previews.gr_photo">
                                        <img :src="previews.gr_photo" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guarantor && $customer->guarantor->photo)
                                        <img x-show="!previews.gr_photo" src="{{ asset('storage/' . $customer->guarantor->photo) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.gr_photo" x-text="errors.gr_photo" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Guarantor NID Front -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">GR. NID Front <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="gr_nid_front" @change="imagePreview($event, 'gr_nid_front')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guarantor?->nid_front ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.gr_nid_front && !{{ isset($customer) && $customer->guarantor?->nid_front ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Front</span>
                                    </div>
                                    <template x-if="previews.gr_nid_front">
                                        <img :src="previews.gr_nid_front" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guarantor && $customer->guarantor->nid_front)
                                        <img x-show="!previews.gr_nid_front" src="{{ asset('storage/' . $customer->guarantor->nid_front) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.gr_nid_front" x-text="errors.gr_nid_front" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Guarantor NID Back -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">GR. NID Back <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="gr_nid_back" @change="imagePreview($event, 'gr_nid_back')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guarantor?->nid_back ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.gr_nid_back && !{{ isset($customer) && $customer->guarantor?->nid_back ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h4m10-11V4a2 2 0 00-2-2H4a2 2 0 00-2 2v16a2 2 0 002 2h12m4-7a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">NID Back</span>
                                    </div>
                                    <template x-if="previews.gr_nid_back">
                                        <img :src="previews.gr_nid_back" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guarantor && $customer->guarantor->nid_back)
                                        <img x-show="!previews.gr_nid_back" src="{{ asset('storage/' . $customer->guarantor->nid_back) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.gr_nid_back" x-text="errors.gr_nid_back" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                            <!-- Guarantor Job ID -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-text-primary dark:text-dark-text ml-1">GR. Job ID <span class="text-danger">*</span></label>
                                <div class="relative group h-48 bg-background/60 dark:bg-dark-bg/40 rounded-2xl border-2 border-dashed border-border dark:border-dark-border overflow-hidden flex items-center justify-center hover:bg-background transition-all cursor-pointer">
                                    <input type="file" name="gr_job_id" @change="imagePreview($event, 'gr_job_id')" :required="customer.customer_type === 'emi' && !{{ isset($customer) && $customer->guarantor?->job_id ? 'true' : 'false' }}" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                    <div class="text-center" x-show="!previews.gr_job_id && !{{ isset($customer) && $customer->guarantor?->job_id ? 'true' : 'false' }}">
                                        <svg class="w-10 h-10 text-text-muted/60 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        <span class="text-[9px] font-black text-text-muted uppercase tracking-widest">Job ID</span>
                                    </div>
                                    <template x-if="previews.gr_job_id">
                                        <img :src="previews.gr_job_id" class="absolute inset-0 w-full h-full object-cover">
                                    </template>
                                    @if(isset($customer) && $customer->guarantor && $customer->guarantor->job_id)
                                        <img x-show="!previews.gr_job_id" src="{{ asset('storage/' . $customer->guarantor->job_id) }}" class="absolute inset-0 w-full h-full object-cover">
                                    @endif
                                </div>
                                <span x-show="errors.gr_job_id" x-text="errors.gr_job_id" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                            </div>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS FOR GUARANTOR -->
                    <div class="mt-8 card p-4 flex justify-between gap-3">
                        <button type="button" 
                                @click="activeTab = 'guardian'"
                                class="btn-secondary">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                             Back
                        </button>
                        <button type="button" 
                                @click="saveStep('guarantor')"
                                :disabled="isSaving"
                                class="btn-primary disabled:opacity-50">
                             <template x-if="!isSaving">
                                <span class="flex items-center gap-2">
                                    Next: Advanced
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                </span>
                             </template>
                             <template x-if="isSaving">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Saving...
                                </span>
                             </template>
                        </button>
                    </div>
                </div>

                <div x-show="activeTab === 'advanced'" x-transition class="space-y-6">
                    <!-- Pricing & Tax Details -->
                    <div class="card p-6">
                        <div class="flex items-center gap-2 mb-6 px-1 text-text-muted">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                             <h3 class="text-[10px] font-black uppercase tracking-widest">Pricing & Tax</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Opening Balance</label>
                                    <div class="md:col-span-2 relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                        <input type="number" step="0.01" name="opening_balance" x-model="customer.opening_balance" @blur="validateField('opening_balance')" class="input-base !pl-10">
                                        <span x-show="errors.opening_balance" x-text="errors.opening_balance" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Credit Limit</label>
                                    <div class="md:col-span-2 relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold text-[10px]">{{ $currencySymbol }}</span>
                                        <input type="number" step="0.01" name="credit_limit" x-model="customer.credit_limit" @blur="validateField('credit_limit')" class="input-base !pl-10">
                                        <span x-show="errors.credit_limit" x-text="errors.credit_limit" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Price Level Type</label>
                                    <div class="md:col-span-2">
                                        <select name="price_level_type" x-model="customer.price_level_type" class="input-base cursor-pointer">
                                            <option value="Increase">Increase</option>
                                            <option value="Decrease">Decrease</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Price Level</label>
                                    <div class="md:col-span-2 relative">
                                        <input type="number" step="0.01" name="price_level" x-model="customer.price_level" @blur="validateField('price_level')" class="input-base !pr-10">
                                        <span x-show="errors.price_level" x-text="errors.price_level" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 px-2 py-0.5 bg-background dark:bg-dark-bg/40 rounded text-[10px] font-black text-text-muted">%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">VAT Number</label>
                                    <div class="md:col-span-2">
                                        <input type="text" name="vatin" x-model="customer.vatin" placeholder="Enter VAT Number" class="input-base">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">GST Number</label>
                                    <div class="md:col-span-2">
                                        <input type="text" name="gstin" x-model="customer.gstin" placeholder="Enter GST Number" class="input-base">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Tax Number</label>
                                    <div class="md:col-span-2">
                                        <input type="text" name="tax_number" x-model="customer.tax_number" placeholder="Enter Tax Number" class="input-base">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Phone</label>
                                    <div class="md:col-span-2">
                                        <input type="text" name="phone" x-model="customer.phone" @blur="validateField('phone')" placeholder="Landline / Alternative Phone" class="input-base">
                                        <span x-show="errors.phone" x-text="errors.phone" class="text-[10px] font-bold text-danger mt-1 block uppercase tracking-wider"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 pt-8 border-t border-border dark:border-dark-border">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 items-start gap-4">
                                        <label class="block text-sm font-medium text-text-primary dark:text-dark-text mt-2">Location Map</label>
                                        <div class="md:col-span-2">
                                            <textarea name="location_link" x-model="customer.location_link" rows="2" placeholder="Google Maps Iframe / Link" class="input-base"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
                                        <label class="block text-sm font-medium text-text-primary dark:text-dark-text">Attachment</label>
                                        <div class="md:col-span-2">
                                            <label class="relative flex flex-col items-center justify-center p-3 border-2 border-dashed border-border dark:border-dark-border rounded-2xl bg-background/50 dark:bg-dark-bg/40 hover:bg-background dark:hover:bg-dark-bg/60 transition-all cursor-pointer group">
                                                 <input type="file" name="attachment_1" class="absolute inset-0 opacity-0 cursor-pointer">
                                                 <svg class="w-5 h-5 text-text-muted group-hover:text-primary mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                 <span class="text-[8px] font-black uppercase tracking-widest text-text-muted group-hover:text-primary">Upload File</span>
                                            </label>
                                            @if(isset($customer) && $customer->attachment_1)
                                                <div class="mt-2 text-[9px] text-text-muted flex items-center gap-2">
                                                    <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Existing: <a href="{{ asset('storage/' . $customer->attachment_1) }}" target="_blank" class="text-primary hover:underline">View File</a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons replaced by Wizard Footer -->
                    </div>

                    <!-- FOOTER ACTIONS FOR ADVANCED -->
                    <div class="mt-8 card p-4 flex justify-between gap-3">
                        <button type="button" 
                                @click="activeTab = customer.customer_type === 'emi' ? 'guarantor' : 'documents'"
                                class="btn-secondary">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                             Back
                        </button>
                        <button type="button" 
                                @click="saveStep('advanced')"
                                :disabled="isSaving"
                                class="btn-primary disabled:opacity-50">
                             <template x-if="!isSaving">
                                <span class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    Finish & Save
                                </span>
                             </template>
                             <template x-if="isSaving">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Finalizing...
                                </span>
                             </template>
                        </button>
                    </div>
                </div>
            </form>
    </div>
</x-app-layout>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customerForm', (initialData, customerId) => ({
        activeTab: 'basic',
        customer: initialData,
        customerId: customerId,
        copyAddress: false,
        previews: {},
        errors: {},
        isSubmitted: false,
        isSaving: false,
        
        syncAddress() {
            if (this.copyAddress) {
                this.customer.ship_country_id = this.customer.country_id;
                this.customer.ship_state_id = this.customer.state_id;
                this.customer.ship_city = this.customer.city;
                this.customer.ship_postcode = this.customer.postcode;
                this.customer.ship_address = this.customer.address;
            }
        },
        
        validateField(field) {
            const val = this.customer[field] || '';
            const type = this.customer.customer_type;
            delete this.errors[field];

            const requiredFields = ['customer_name', 'customer_type', 'mobile'];
            if (requiredFields.includes(field) && !val) {
                this.errors[field] = 'Required field';
            }

            if (field === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                this.errors[field] = 'Invalid email';
            }

            const elevenDigitFields = ['mobile', 'mobile_primary', 'mobile_secondary', 'phone', 'g_mobile', 'gr_mobile'];
            if (elevenDigitFields.includes(field) && val) {
                if (!/^\d{11}$/.test(val)) this.errors[field] = 'Exactly 11 digits';
            }

            const numericFields = ['monthly_income', 'gr_monthly_income', 'opening_balance', 'credit_limit', 'price_level'];
            if (numericFields.includes(field) && val !== '') {
                const numVal = parseFloat(val);
                if (isNaN(numVal) || numVal < 0) this.errors[field] = 'Non-negative number';
            }

            const emiRequired = ['customer_id_card', 'father_name', 'mother_name', 'dob', 'mobile_secondary', 'present_address', 'permanent_address', 'occupation', 'monthly_income', 'workplace_name', 'workplace_address', 'g_name', 'g_relationship', 'g_mobile', 'gr_name', 'gr_father_name', 'gr_address', 'gr_mobile', 'gr_occupation', 'gr_monthly_income'];
            if (type === 'emi' && emiRequired.includes(field) && !val) {
                this.errors[field] = 'Required for EMI';
            }
        },

        validateStep(step) {
            this.errors = {};
            let relevantFields = [];
            
            if (step === 'basic') {
                relevantFields = ['customer_name', 'customer_type', 'mobile', 'email'];
                if (this.customer.customer_type === 'emi') {
                    relevantFields = [...relevantFields, 'customer_id_card', 'father_name', 'mother_name', 'dob', 'mobile_secondary', 'present_address', 'permanent_address', 'occupation', 'monthly_income', 'workplace_name', 'workplace_address'];
                }
            } else if (step === 'documents') {
                if (this.customer.customer_type === 'emi') {
                    const emiFiles = ['photo', 'nid_front', 'nid_back', 'job_id_card'];
                    emiFiles.forEach(f => {
                        const imgEl = document.querySelector(`img[x-show='!previews.${f}']`);
                        if (!this.previews[f] && (!imgEl || imgEl.style.display === 'none')) {
                            this.errors[f] = 'File required';
                        }
                    });
                }
            } else if (step === 'guardian') {
                relevantFields = ['g_name', 'g_relationship', 'g_mobile'];
                if (this.customer.customer_type === 'emi') {
                    const emiFiles = ['g_photo', 'g_nid_front', 'g_nid_back'];
                    emiFiles.forEach(f => {
                        const imgEl = document.querySelector(`img[x-show='!previews.${f}']`);
                        if (!this.previews[f] && (!imgEl || imgEl.style.display === 'none')) {
                            this.errors[f] = 'File required';
                        }
                    });
                }
            } else if (step === 'guarantor') {
                relevantFields = ['gr_name', 'gr_father_name', 'gr_address', 'gr_mobile', 'gr_occupation', 'gr_monthly_income'];
                if (this.customer.customer_type === 'emi') {
                    const emiFiles = ['gr_photo', 'gr_nid_front', 'gr_nid_back', 'gr_job_id'];
                    emiFiles.forEach(f => {
                        const imgEl = document.querySelector(`img[x-show='!previews.${f}']`);
                        if (!this.previews[f] && (!imgEl || imgEl.style.display === 'none')) {
                            this.errors[f] = 'File required';
                        }
                    });
                }
            } else if (step === 'advanced') {
                relevantFields = ['opening_balance', 'credit_limit', 'price_level', 'phone'];
            }

            relevantFields.forEach(f => this.validateField(f));

            if (Object.keys(this.errors).length > 0) {
                this.$nextTick(() => {
                    const first = document.querySelector("[x-show^='errors.']:not([style*='display: none'])");
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                return false;
            }
            return true;
        },

        async saveStep(step) {
            if (!this.validateStep(step)) return;

            this.isSaving = true;
            const formData = new FormData();
            formData.append('current_step', step);
            if (this.customerId) formData.append('id', this.customerId);
            
            // Add customer data
            Object.keys(this.customer).forEach(key => {
                formData.append(key, this.customer[key] ?? '');
            });

            // Append files from inputs
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                if (input.files[0]) {
                    formData.append(input.name, input.files[0]);
                }
            });

            try {
                const response = await fetch("{{ route('contacts.customers.save-step') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();
                
                if (response.ok && data.success) {
                    this.customerId = data.id;
                    this.isSubmitted = false; // Reset submission flag
                    this.goToNext(step);
                } else if (response.status === 422 && data.errors) {
                    // Inject backend validation errors into frontend UI
                    for (const field in data.errors) {
                        this.errors[field] = data.errors[field][0];
                    }
                    // Scroll to first error
                    this.$nextTick(() => {
                        const first = document.querySelector("[x-show^='errors.']:not([style*='display: none'])");
                        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                } else {
                    showError(data.message || 'Error saving data');
                }
            } catch (error) {
                console.error('Save failed:', error);
                showError('An error occurred while saving.');
            } finally {
                this.isSaving = false;
            }
        },

        goToNext(currentStep) {
            const isEmi = this.customer.customer_type === 'emi';
            if (currentStep === 'basic') {
                this.activeTab = 'documents';
            } else if (currentStep === 'documents') {
                this.activeTab = isEmi ? 'guardian' : 'advanced';
            } else if (currentStep === 'guardian') {
                this.activeTab = 'guarantor';
            } else if (currentStep === 'guarantor') {
                this.activeTab = 'advanced';
            } else if (currentStep === 'advanced') {
                window.location.href = "{{ route('contacts.customers.list') }}";
            }
        },

        imagePreview(e, field) {
            const file = e.target.files[0];
            if (!file) return;
            delete this.errors[field];
            if (!file.type.startsWith('image/')) {
                this.errors[field] = 'Images only';
                e.target.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                this.errors[field] = 'Max 2MB';
                e.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => this.previews[field] = e.target.result;
            reader.readAsDataURL(file);
        },

        init() {
            this.$watch('customer.customer_type', value => {
                if (value === 'regular' && (this.activeTab === 'guardian' || this.activeTab === 'guarantor')) {
                    this.activeTab = 'basic';
                }
            });
        }
    }));
});
</script>
