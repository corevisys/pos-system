@php
    // Graceful logo fallback: if no logo is set OR the stored path no longer
    // resolves to a real file, fall back to the neutral inline SVG placeholder
    // (matches the invoice view's guarded @if($store && $store->store_logo)
    // pattern) so a broken path never renders a raw broken-image icon.
    //
    // The placeholder is echoed with {{ }} (NOT @js): @js() JSON-escapes forward
    // slashes, which would change 'data:image/svg+xml;base64,' into
    // 'data:image\/svg+xml;base64,' and break the pinned test that asserts the
    // literal marker. base64 output contains no < > & " chars, so {{ }} is safe.
    $logoPreviewUrl = ($store->store_logo && file_exists(public_path('storage/' . $store->store_logo)))
        ? Storage::url($store->store_logo)
        : "data:image/svg+xml;base64," . base64_encode("<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"96\" height=\"48\" viewBox=\"0 0 96 48\"><rect width=\"96\" height=\"48\" fill=\"#f1f5f9\"/><path d=\"M30 16h12v16H30z\" fill=\"#cbd5e1\"/><circle cx=\"54\" cy=\"20\" r=\"6\" fill=\"#cbd5e1\"/><rect x=\"54\" y=\"26\" width=\"12\" height=\"6\" fill=\"#cbd5e1\"/></svg>");
@endphp
<x-app-layout title="Store Settings">
    <div x-data="{
        activeTab: 'store',
        isSubmitting: false,
        country: '{{ $store->country }}', 
        state: '{{ $store->state }}', 
        countries: {{ $countries->map(fn($c) => ['id' => $c->id, 'country' => $c->country])->toJson() }},
        states: {{ $states->map(fn($s) => ['id' => $s->id, 'state' => $s->state])->toJson() }},
        originalCurrencyId: '{{ (string)$store->currency_id }}',
        selectedCurrencyId: '{{ (string)$store->currency_id }}',
        currencies: {{ $currencies->map(fn($c) => ['id' => (string)$c->id, 'currency_name' => $c->currency_name, 'currency_code' => $c->currency_code, 'symbol' => $c->symbol])->toJson() }},
        showCurrencyConfirmModal: false,
        confirmedCurrencyChange: false,
        get currentActiveCurrency() {
            return this.currencies.find(c => String(c.id) === String(this.originalCurrencyId));
        },
        get targetActiveCurrency() {
            return this.currencies.find(c => String(c.id) === String(this.selectedCurrencyId));
        },

        originalLanguageId: '{{ (string)$store->language_id }}',
        selectedLanguageId: '{{ (string)$store->language_id }}',
        languages: {{ $languages->map(fn($l) => ['id' => (string)$l->id, 'language' => $l->language])->toJson() }},
        showLanguageConfirmModal: false,
        confirmedLanguageChange: false,
        get currentActiveLanguage() {
            return this.languages.find(l => String(l.id) === String(this.originalLanguageId));
        },
        get targetActiveLanguage() {
            return this.languages.find(l => String(l.id) === String(this.selectedLanguageId));
        },

        handleFormSubmit(e) {
            // Double-submit guard: once a real submission is in flight, block
            // any further submit attempts (mirrors the isSubmitting baseline).
            if (this.isSubmitting) {
                e.preventDefault();
                return false;
            }
            if (!this.confirmedCurrencyChange && this.selectedCurrencyId && String(this.selectedCurrencyId) !== String(this.originalCurrencyId)) {
                e.preventDefault();
                this.showCurrencyConfirmModal = true;
                return false;
            }
            if (!this.confirmedLanguageChange && this.selectedLanguageId && String(this.selectedLanguageId) !== String(this.originalLanguageId)) {
                e.preventDefault();
                this.showLanguageConfirmModal = true;
                return false;
            }
            this.isSubmitting = true;
        },
        confirmAndSubmit() {
            this.confirmedCurrencyChange = true;
            this.showCurrencyConfirmModal = false;
            this.isSubmitting = true;
            this.$nextTick(() => {
                this.$refs.storeForm.submit();
            });
        },
        confirmLanguageAndSubmit() {
            this.confirmedLanguageChange = true;
            this.showLanguageConfirmModal = false;
            this.isSubmitting = true;
            this.$nextTick(() => {
                this.$refs.storeForm.submit();
            });
        },
        async fetchStates() {
            const foundCountry = this.countries.find(c => c.country === this.country);
            if (!foundCountry) {
                this.states = [];
                this.state = '';
                return;
            }
            try {
                const response = await fetch('/settings/store/states/' + foundCountry.id);
                const data = await response.json();
                this.states = data;
                if (this.state && !data.some(s => s.state === this.state)) {
                    this.state = '';
                }
            } catch (error) { console.error(error); }
        },
        logoPreview: '{{ $logoPreviewUrl }}',
        handleLogoPreview(event) {
            const file = event.target.files[0];
            if (file) {
                this.logoPreview = URL.createObjectURL(file);
            }
        }
    }">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Store Settings <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest" x-text="activeTab + ' Configuration'"></span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">Store Settings</span>
                </div>
            </div>
        </div>


        <!-- TABS NAVIGATION -->
        <div class="flex flex-wrap items-center gap-2 mb-4 p-1 bg-background/60 dark:bg-slate-800/50 rounded-2xl w-fit border border-border dark:border-dark-border">
            <button @click="activeTab = 'store'" :class="activeTab === 'store' ? 'bg-card dark:bg-dark-card text-primary shadow-sm' : 'text-text-secondary hover:text-text-primary'" class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Store</button>
            <button @click="activeTab = 'system'" :class="activeTab === 'system' ? 'bg-card dark:bg-dark-card text-primary shadow-sm' : 'text-text-secondary hover:text-text-primary'" class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">System</button>
            <button @click="activeTab = 'sales'" :class="activeTab === 'sales' ? 'bg-card dark:bg-dark-card text-primary shadow-sm' : 'text-text-secondary hover:text-text-primary'" class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Sales</button>
            <button @click="activeTab = 'prefixes'" :class="activeTab === 'prefixes' ? 'bg-card dark:bg-dark-card text-primary shadow-sm' : 'text-text-secondary hover:text-text-primary'" class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Prefixes</button>
        </div>

        <!-- MAIN CONTAINER -->
        <x-card class="overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex items-center gap-2">
                <div class="p-1.5 bg-primary/10 rounded-lg">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <h2 class="text-sm font-black text-text-primary dark:text-white uppercase tracking-widest">Store Configuration</h2>
            </div>

            <form x-ref="storeForm" @submit="handleFormSubmit($event)" action="{{ route('settings.store.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6 p-5 md:p-6">
                @csrf

                @if ($errors->any())
                    <div class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-widest text-danger mb-1">Please fix the following errors</p>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li class="text-[11px] font-semibold text-danger">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- TAB 1: STORE -->
                <div x-show="activeTab === 'store'" x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                        <!-- Left Column -->
                        <div class="space-y-6">
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 opacity-70">Store Code <span class="text-danger">*</span></label>
                                <input type="text" value="{{ $store->store_code }}" class="input-base !py-3 !text-[11px] !font-bold !text-text-muted !cursor-not-allowed" readonly>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Store Name <span class="text-danger">*</span></label>
                                <input type="text" name="store_name" value="{{ old('store_name', $store->store_name) }}" class="input-base !py-3 !text-[11px] !font-bold" required>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Mobile <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile" value="{{ old('mobile', $store->mobile) }}" class="input-base !py-3 !text-[11px] !font-bold" required>
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', $store->email) }}" class="input-base !py-3 !text-[11px] !font-bold" required>
                                </div>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone', $store->phone) }}" class="input-base !py-3 !text-[11px] !font-bold">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">GST Number</label>
                                    <input type="text" name="gst_no" value="{{ old('gst_no', $store->gst_no) }}" class="input-base !py-3 !text-[11px] !font-bold">
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">VAT Number</label>
                                    <input type="text" name="vat_no" value="{{ old('vat_no', $store->vat_no) }}" class="input-base !py-3 !text-[11px] !font-bold">
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">PAN Number</label>
                                    <input type="text" name="pan_no" value="{{ old('pan_no', $store->pan_no) }}" class="input-base !py-3 !text-[11px] !font-bold">
                                </div>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Store Website</label>
                                <input type="url" name="store_website" value="{{ old('store_website', $store->store_website) }}" class="input-base !py-3 !text-[11px] !font-bold">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-6">
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-success">Bank Details</label>
                                <textarea name="bank_details" rows="3" class="input-base !py-3 !text-[11px] !font-bold resize-none">{{ old('bank_details', $store->bank_details) }}</textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Country</label>
                                    <x-searchable-select name="country" :options="$countries" labelKey="country" valueKey="country" emptyOption="Select Country" emptyValue="" placeholder="Select Country" model="country" change="fetchStates()" />
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">State</label>
                                    <x-searchable-select name="state" :options="[]" labelKey="state" valueKey="state" emptyOption="Select State" emptyValue="" placeholder="Select State" model="state" optionsExpression="states" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">City <span class="text-danger">*</span></label>
                                    <input type="text" name="city" value="{{ old('city', $store->city) }}" class="input-base !py-3 !text-[11px] !font-bold" required>
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Postcode</label>
                                    <input type="text" name="postcode" value="{{ old('postcode', $store->postcode) }}" class="input-base !py-3 !text-[11px] !font-bold">
                                </div>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Address</label>
                                <textarea name="address" rows="2" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm resize-none">{{ old('address', $store->address) }}</textarea>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest px-1">Store Logo</label>
                                <div class="flex items-start gap-4">
                                    <div class="relative group cursor-pointer" @click="$refs.logoInput.click()">
                                        <div class="w-24 h-12 bg-slate-50/50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-dark-border rounded-lg overflow-hidden flex items-center justify-center transition-all group-hover:border-primary-500">
                                            <img :src="logoPreview" class="max-w-[70%] max-h-[70%] opacity-80" alt="Logo">
                                            <div class="absolute inset-0 bg-primary-600/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-1">
                                        <input type="file" name="logo" x-ref="logoInput" class="hidden" @change="handleLogoPreview($event)">
                                        <label @click="$refs.logoInput.click()" class="text-[9px] font-black text-primary-600 underline cursor-pointer uppercase tracking-widest">Choose File</label>
                                        <p class="text-[8px] font-bold text-slate-400 mt-1">Max 1000x1000px</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: SYSTEM -->
                <div x-show="activeTab === 'system'" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                        <div class="space-y-6">
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Timezone <span class="text-danger">*</span></label>
                                <x-searchable-select name="timezone" :options="$timezones" labelKey="name" valueKey="id" placeholder="Select Timezone" :value="old('timezone', $store->timezone ?? 'Asia/Dhaka')" required />
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Date Format <span class="text-danger">*</span></label>
                                <select name="date_format" class="input-base !py-3 !text-[11px] !font-bold appearance-none">
                                    <option value="d-m-Y" {{ old('date_format', $store->date_format) == 'd-m-Y' ? 'selected' : '' }}>dd-mm-yyyy</option>
                                    <option value="m-d-Y" {{ old('date_format', $store->date_format) == 'm-d-Y' ? 'selected' : '' }}>mm-dd-yyyy</option>
                                    <option value="Y-m-d" {{ old('date_format', $store->date_format) == 'Y-m-d' ? 'selected' : '' }}>yyyy-mm-dd</option>
                                </select>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Time Format <span class="text-danger">*</span></label>
                                <select name="time_format" class="input-base !py-3 !text-[11px] !font-bold appearance-none">
                                    <option value="h:i a" {{ old('time_format', $store->time_format) == 'h:i a' ? 'selected' : '' }}>12 Hours</option>
                                    <option value="H:i" {{ old('time_format', $store->time_format) == 'H:i' ? 'selected' : '' }}>24 Hours</option>
                                </select>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Currency <span class="text-danger">*</span></label>
                                <x-searchable-select name="currency_id" :options="$currencies" labelKey="currency_name" valueKey="id" placeholder="Select Currency" :value="old('currency_id', $store->currency_id)" model="selectedCurrencyId" required />
                                <p class="text-[9px] font-medium text-text-muted mt-1 pl-1">
                                    Changing currency here sets the active currency across POS, invoices, and reports.
                                    <a href="{{ route('settings.currency') }}" class="text-primary hover:underline font-bold">Manage Currencies &rarr;</a>
                                </p>
                            </div>
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Currency Symbol Placement <span class="text-danger">*</span></label>
                                <select name="currency_placement" class="input-base !py-3 !text-[11px] !font-bold appearance-none">
                                    <option value="before" {{ old('currency_placement', $store->currency_placement) == 'before' ? 'selected' : '' }}>Before Amount</option>
                                    <option value="after" {{ old('currency_placement', $store->currency_placement) == 'after' ? 'selected' : '' }}>After Amount</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Language <span class="text-danger">*</span></label>
                                <x-searchable-select name="language_id" :options="$languages" labelKey="language" valueKey="id" placeholder="Select Language" :value="old('language_id', $store->language_id)" model="selectedLanguageId" required />
                                <p class="text-[9px] font-medium text-text-muted mt-1 italic">
                                    Changing store language sets the system-wide active language. <a href="{{ route('settings.languages.index') }}" class="text-primary-500 hover:underline font-bold">Manage languages &rarr;</a>
                                </p>
                            </div>
                            <div class="flex items-center gap-4 px-2 pt-1" x-data="{ toggled: {{ $store->round_off ? 'true' : 'false' }} }">
                                <span class="text-[10px] font-black uppercase text-text-muted tracking-widest">Enable Round Off?</span>
                                <input type="hidden" name="round_off" :value="toggled ? 1 : 0">
                                <div class="relative inline-block w-9 h-5 cursor-pointer" @click="toggled = !toggled">
                                    <div :class="toggled ? 'bg-primary-500' : 'bg-slate-200'" class="w-full h-full rounded-full transition-colors duration-200"></div>
                                    <div :class="toggled ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200 ease-in-out shadow-sm"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Decimals <span class="text-danger">*</span></label>
                                    <select name="decimals" class="input-base !py-3 !text-[11px] !font-bold appearance-none">
                                        <option value="0" {{ old('decimals', $store->decimals) == 0 ? 'selected' : '' }}>0</option>
                                        <option value="1" {{ old('decimals', $store->decimals) == 1 ? 'selected' : '' }}>1</option>
                                        <option value="2" {{ old('decimals', $store->decimals) == 2 ? 'selected' : '' }}>2</option>
                                        <option value="3" {{ old('decimals', $store->decimals) == 3 ? 'selected' : '' }}>3</option>
                                    </select>
                                </div>
                                <div class="group relative">
                                    <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Decimals for Quantity <span class="text-danger">*</span></label>
                                    <select name="qty_decimals" class="input-base !py-3 !text-[11px] !font-bold appearance-none">
                                        <option value="0" {{ old('qty_decimals', $store->qty_decimals) == 0 ? 'selected' : '' }}>0</option>
                                        <option value="1" {{ old('qty_decimals', $store->qty_decimals) == 1 ? 'selected' : '' }}>1</option>
                                        <option value="2" {{ old('qty_decimals', $store->qty_decimals) == 2 ? 'selected' : '' }}>2</option>
                                        <option value="3" {{ old('qty_decimals', $store->qty_decimals) == 3 ? 'selected' : '' }}>3</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: SALES -->
                <div x-show="activeTab === 'sales'" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95">
                    <div class="grid grid-cols-1 gap-8">
                         <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-4 pt-1">
                                <div class="flex items-center gap-4 px-2" x-data="{ toggled: {{ $store->number_to_words ? 'true' : 'false' }} }">
                                    <input type="hidden" name="number_to_words" :value="toggled ? 1 : 0">
                                    <div class="relative inline-block w-9 h-5 cursor-pointer" @click="toggled = !toggled">
                                        <div :class="toggled ? 'bg-primary-500' : 'bg-slate-200'" class="w-full h-full rounded-full transition-colors duration-200"></div>
                                        <div :class="toggled ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200 shadow-sm"></div>
                                    </div>
                                    <span class="text-[10px] font-black uppercase text-text-muted tracking-widest">Show Amount in Words on Invoice</span>
                                </div>
                                <div class="flex items-center gap-4 px-2" x-data="{ toggled: {{ $store->change_return ? 'true' : 'false' }} }">
                                    <input type="hidden" name="change_return" :value="toggled ? 1 : 0">
                                    <div class="relative inline-block w-9 h-5 cursor-pointer" @click="toggled = !toggled">
                                        <div :class="toggled ? 'bg-primary-500' : 'bg-slate-200'" class="w-full h-full rounded-full transition-colors duration-200"></div>
                                        <div :class="toggled ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200 shadow-sm"></div>
                                    </div>
                                    <span class="text-[10px] font-black uppercase text-text-muted tracking-widest">Show Paid Amount and Change Return?</span>
                                </div>
                            </div>
                            <div class="space-y-4 pt-1">
                                <div class="flex items-center gap-4 px-2" x-data="{ toggled: {{ $store->previous_balance_bit ? 'true' : 'false' }} }">
                                    <input type="hidden" name="previous_balance_bit" :value="toggled ? 1 : 0">
                                    <div class="relative inline-block w-9 h-5 cursor-pointer" @click="toggled = !toggled">
                                        <div :class="toggled ? 'bg-primary-500' : 'bg-slate-200'" class="w-full h-full rounded-full transition-colors duration-200"></div>
                                        <div :class="toggled ? 'translate-x-4' : 'translate-x-0.5'" class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200 shadow-sm"></div>
                                    </div>
                                    <span class="text-[10px] font-black uppercase text-text-muted tracking-widest">Show Previous Balance on Invoice</span>
                                </div>
                            </div>
                         </div>
                         <div class="space-y-6 border-t border-slate-50 dark:border-dark-border pt-6">
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary">Sales Invoice Footer Text</label>
                                <textarea name="sales_invoice_footer_text" rows="2" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 outline-none transition-all focus:border-primary-500 shadow-inner-sm resize-none">{{ old('sales_invoice_footer_text', $store->sales_invoice_footer_text) }}</textarea>
                            </div>
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-6 px-1" x-data="{ visibility: {{ $store->t_and_c_status ? '1' : '0' }} }">
                                    <span class="text-[10px] font-black uppercase text-text-muted tracking-widest">Invoice Terms and Conditions</span>
                                    <input type="hidden" name="t_and_c_status" :value="visibility">
                                    <div class="flex gap-3">
                                        <label class="flex items-center gap-2 cursor-pointer group" @click="visibility = 1">
                                            <div class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center p-0.5" :class="visibility == 1 ? 'border-primary-500' : 'border-slate-200'">
                                                <div class="w-1.5 h-1.5 bg-primary-500 rounded-full" x-show="visibility == 1"></div>
                                            </div>
                                            <span class="text-[9px] font-black uppercase text-text-secondary tracking-widest group-hover:text-primary transition-colors" :class="visibility == 1 ? 'text-primary-500' : ''">Show on Invoice</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer group" @click="visibility = 0">
                                            <div class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center p-0.5" :class="visibility == 0 ? 'border-primary-500' : 'border-slate-200'">
                                                <div class="w-1.5 h-1.5 bg-primary-500 rounded-full" x-show="visibility == 0"></div>
                                            </div>
                                            <span class="text-[9px] font-black uppercase text-text-muted tracking-widest group-hover:text-primary transition-colors" :class="visibility == 0 ? 'text-primary-500' : ''">Hide on Invoice</span>
                                        </label>
                                    </div>
                                </div>
                                <textarea name="invoice_terms" rows="4" class="input-base !py-3 !text-[11px] !font-bold resize-none italic">{{ old('invoice_terms', $store->invoice_terms) }}</textarea>
                            </div>
                         </div>
                    </div>
                </div>

                <!-- TAB 4: PREFIXES -->
                <div x-show="activeTab === 'prefixes'" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-95">
                    <!-- Compact responsive grid: same 19 fields, same order, same
                         name= attributes — presentation/grouping only. -->
                    <!-- Spacing matches the page convention used by the Store
                         (gap-x-8 gap-y-6) / System (gap-x-8 gap-y-6) tabs. -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-8 gap-y-6">
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Category <span class="text-danger">*</span></label>
                                <input type="text" name="category_init" value="{{ old('category_init', $store->category_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Supplier <span class="text-danger">*</span></label>
                                <input type="text" name="supplier_init" value="{{ old('supplier_init', $store->supplier_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Purchase Return <span class="text-danger">*</span></label>
                                <input type="text" name="purchase_return_init" value="{{ old('purchase_return_init', $store->purchase_return_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Sales <span class="text-danger">*</span></label>
                                <input type="text" name="sales_init" value="{{ old('sales_init', $store->sales_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Expense <span class="text-danger">*</span></label>
                                <input type="text" name="expense_init" value="{{ old('expense_init', $store->expense_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Quotation <span class="text-danger">*</span></label>
                                <input type="text" name="quotation_init" value="{{ old('quotation_init', $store->quotation_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Sales Payment <span class="text-danger">*</span></label>
                                <input type="text" name="sales_payment_init" value="{{ old('sales_payment_init', $store->sales_payment_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Purchase Payment <span class="text-danger">*</span></label>
                                <input type="text" name="purchase_payment_init" value="{{ old('purchase_payment_init', $store->purchase_payment_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Expense Payment <span class="text-danger">*</span></label>
                                <input type="text" name="expense_payment_init" value="{{ old('expense_payment_init', $store->expense_payment_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Item <span class="text-danger">*</span></label>
                                <input type="text" name="item_init" value="{{ old('item_init', $store->item_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Purchase <span class="text-danger">*</span></label>
                                <input type="text" name="purchase_init" value="{{ old('purchase_init', $store->purchase_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Customer <span class="text-danger">*</span></label>
                                <input type="text" name="customer_init" value="{{ old('customer_init', $store->customer_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Sales Return <span class="text-danger">*</span></label>
                                <input type="text" name="sales_return_init" value="{{ old('sales_return_init', $store->sales_return_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Accounts <span class="text-danger">*</span></label>
                                <input type="text" name="accounts_init" value="{{ old('accounts_init', $store->accounts_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Journal <span class="text-danger">*</span></label>
                                <input type="text" name="journal_init" value="{{ old('journal_init', $store->journal_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Money Transfer <span class="text-danger">*</span></label>
                                <input type="text" name="money_transfer_init" value="{{ old('money_transfer_init', $store->money_transfer_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Sales Return Payment <span class="text-danger">*</span></label>
                                <input type="text" name="sales_return_payment_init" value="{{ old('sales_return_payment_init', $store->sales_return_payment_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[120px]">Purchase Return Payment <span class="text-danger">*</span></label>
                                <input type="text" name="purchase_return_payment_init" value="{{ old('purchase_return_payment_init', $store->purchase_return_payment_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                            <div class="flex items-center justify-between gap-3 group">
                                <label class="text-[10px] font-black uppercase text-text-muted tracking-widest min-w-[130px]">Customers Advance Payments <span class="text-danger">*</span></label>
                                <input type="text" name="cust_advance_init" value="{{ old('cust_advance_init', $store->cust_advance_init) }}" class="input-base !py-2 !text-[11px] !font-black">
                            </div>
                    </div>
                </div>

                <!-- PERSISTENT ACTION BUTTONS -->
                <div class="flex flex-col md:flex-row justify-end items-center gap-3 pt-4 border-t border-slate-50 dark:border-dark-border">
                    <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-48 !bg-emerald-500 hover:!bg-emerald-600 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                         <svg x-show="!isSubmitting" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                         <svg x-show="isSubmitting" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="isSubmitting ? 'Saving...' : 'Save Settings'"></span>
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn-secondary w-full md:w-32 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Close
                    </a>
                </div>
            </form>
        </x-card>

        <!-- RECENT ACTIVITY (read-only) -->
        <div class="mt-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-3">
                <h2 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em]">Recent Activity</h2>

                <form action="{{ route('settings.store') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <select name="action" class="input-base !py-1.5 !text-[10px] !font-bold !w-auto">
                        <option value="">All Actions</option>
                        @foreach ($activityActions as $actionOption)
                            <option value="{{ $actionOption }}" {{ request('action') === $actionOption ? 'selected' : '' }}>{{ $actionOption }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" class="input-base !py-1.5 !text-[10px] !font-bold !w-auto">
                    <input type="date" name="to_date" value="{{ request('to_date') }}" class="input-base !py-1.5 !text-[10px] !font-bold !w-auto">
                    <button type="submit" class="btn-primary !py-1.5 !px-3 !text-[10px] tracking-widest">Filter</button>
                    @if (request('action') || request('from_date') || request('to_date'))
                        <a href="{{ route('settings.store') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline">Clear</a>
                    @endif
                </form>
            </div>

            <x-card padding="p-0" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                            <tr>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">When</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">User</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Action</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Details</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-dark-border">
                            @forelse ($activities as $activity)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-4 py-2">
                                        <span class="text-[10px] font-bold text-text-secondary dark:text-text-muted tabular-nums">{{ $activity->created_at?->format('Y-m-d H:i:s') }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-[10px] font-bold text-text-primary dark:text-dark-text">{{ $activity->user?->name ?? 'System' }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest">{{ $activity->action }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if (!empty($activity->new_values))
                                            <div class="space-y-0.5">
                                                @foreach ($activity->new_values as $field => $newValue)
                                                    <p class="text-[9px] font-bold text-text-secondary dark:text-text-muted">
                                                        <span class="text-text-muted uppercase tracking-wider">{{ $field }}</span>:
                                                        <span class="line-through opacity-60">{{ \Illuminate\Support\Str::limit((string) ($activity->old_values[$field] ?? '—'), 40) }}</span>
                                                        <span class="text-primary">&rarr;</span>
                                                        <span class="text-text-primary dark:text-dark-text">{{ \Illuminate\Support\Str::limit(is_scalar($newValue) ? (string) $newValue : json_encode($newValue), 40) }}</span>
                                                    </p>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[9px] font-bold text-text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="text-[10px] font-bold text-text-muted tabular-nums">{{ $activity->ip_address ?? '—' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center">
                                        <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No activity recorded yet</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                    <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                        Showing {{ $activities->firstItem() ?? 0 }} to {{ $activities->lastItem() ?? 0 }} of {{ $activities->total() }} entries
                    </p>
                    <div class="flex gap-1">
                        {{ $activities->links() }}
                    </div>
                </div>
            </x-card>
        </div>

        <!-- CURRENCY SWITCH CONFIRMATION MODAL -->
        <div x-show="showCurrencyConfirmModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showCurrencyConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showCurrencyConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white dark:bg-dark-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-100 dark:border-dark-border">
                    <div class="px-6 py-5 border-b border-slate-50 dark:border-dark-border flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.15em] text-slate-800 dark:text-white">Switch System Currency</h3>
                            <p class="text-[10px] font-bold text-slate-400 mt-0.5">Confirmation required</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
                            You are changing the system currency from <strong class="text-slate-900 dark:text-white font-bold" x-text="currentActiveCurrency ? currentActiveCurrency.currency_name : 'current currency'"></strong> to <strong class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="targetActiveCurrency ? targetActiveCurrency.currency_name : 'new currency'"></strong> (<span x-text="targetActiveCurrency ? targetActiveCurrency.currency_code : ''"></span> — <span class="font-bold" x-text="targetActiveCurrency ? targetActiveCurrency.symbol : ''"></span>).
                        </p>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            This will update prices, POS checkouts, invoices, and reports across the entire system. Continue?
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-50 dark:border-dark-border flex justify-end items-center gap-3">
                        <button @click="showCurrencyConfirmModal = false;" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            Cancel
                        </button>
                        <button @click="confirmAndSubmit()" type="button" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Confirm & Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- LANGUAGE SWITCH CONFIRMATION MODAL -->
        <div x-show="showLanguageConfirmModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showLanguageConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showLanguageConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white dark:bg-dark-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-100 dark:border-dark-border">
                    <div class="px-6 py-5 border-b border-slate-50 dark:border-dark-border flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.15em] text-slate-800 dark:text-white">Switch System Language</h3>
                            <p class="text-[10px] font-bold text-slate-400 mt-0.5">Confirmation required</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
                            You are changing the system language from <strong class="text-slate-900 dark:text-white font-bold" x-text="currentActiveLanguage ? currentActiveLanguage.language : 'current language'"></strong> to <strong class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="targetActiveLanguage ? targetActiveLanguage.language : 'new language'"></strong>.
                        </p>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            This will set this language as the system-wide active language. Continue?
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-50 dark:border-dark-border flex justify-end items-center gap-3">
                        <button @click="showLanguageConfirmModal = false;" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            Cancel
                        </button>
                        <button @click="confirmLanguageAndSubmit()" type="button" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Confirm & Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>