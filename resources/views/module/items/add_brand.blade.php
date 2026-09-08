<x-app-layout title="Add Brand">
    <div x-data="{ isSubmitting: false }">
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Brands <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Add Brand</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.brands') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Brands List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-wider">Add Brand</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Brand Catalog Management</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <a href="{{ route('items.brands') }}" class="btn-secondary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Cancel
                </a>
                <button type="submit" form="brandForm" :disabled="isSubmitting" x-ref="submitBtn" class="btn-primary">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    Save Brand
                </button>
            </div>
        </div>

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

        <!-- MAIN FORM -->
        <div class="card p-4 md:p-6">
            <form id="brandForm" action="{{ route('items.brands.store') }}" method="POST" class="space-y-8"
                  @submit="isSubmitting = true">
                @csrf

                <!-- ═════════ SECTION: BRAND INFORMATION ═════════ -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 px-1">
                        <div class="w-1 h-3.5 bg-primary rounded-full"></div>
                        <h2 class="text-[9px] font-black uppercase tracking-widest text-text-primary dark:text-dark-text">Brand Information</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Brand Name -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Brand Name <span class="text-danger">*</span></label>
                            <input type="text" name="brand_name" value="{{ old('brand_name') }}"
                                   placeholder="E.g. American Eagle" class="input-base">
                            @error('brand_name')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Brand Code -->
                        <div>
                            <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Brand Code</label>
                            <input type="text" name="brand_code" value="{{ old('brand_code') }}"
                                   placeholder="E.g. BR-001" class="input-base font-mono tracking-wider">
                            @error('brand_code')
                                <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mt-4">
                        <label class="text-[9px] font-black uppercase text-text-muted tracking-widest block mb-1.5 px-0.5">Description</label>
                        <textarea name="description" rows="4" placeholder="Briefly describe this brand..." class="input-base resize-y">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-[9px] font-bold text-danger mt-1 ml-0.5">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
