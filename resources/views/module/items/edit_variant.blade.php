<x-app-layout title="Edit Variant">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Variants</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.variants') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold text-slate-400 uppercase tracking-widest">Variant List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Edit Variant</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
            <div class="p-4 md:p-6">


                <form action="{{ route('items.variants.update', $variant->id) }}" method="POST" class="space-y-8">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Variant Name -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                Variant Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="variant_name" value="{{ old('variant_name', $variant->variant_name) }}" placeholder="E.g. Color, Size" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-1 focus:ring-primary-500">
                        </div>

                        <!-- Variant Code -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                Variant Code
                            </label>
                            <input type="text" name="variant_code" value="{{ old('variant_code', $variant->variant_code) }}" placeholder="E.g. VAR-001" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-1 focus:ring-primary-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Description -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                Description
                            </label>
                            <textarea name="description" rows="4" placeholder="Briefly describe this variant..." class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-medium text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500">{{ old('description', $variant->description) }}</textarea>
                        </div>

                         <!-- Status -->
                         <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500">
                                Status
                            </label>
                            <div class="relative">
                                <select name="status" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border-none rounded-xl py-3 px-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none focus:ring-1 focus:ring-primary-500 appearance-none leading-tight cursor-pointer">
                                    <option value="1" {{ old('status', $variant->status) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $variant->status) == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FORM FOOTER -->
                    <div class="flex flex-col md:flex-row justify-center items-center gap-3 mt-8 pt-8 border-t border-slate-50 dark:border-dark-border">
                        <button type="submit" class="w-full md:w-48 py-2.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-primary-200 dark:shadow-none hover:bg-primary-700 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Update Variant
                        </button>
                        <a href="{{ route('items.variants') }}" class="w-full md:w-48 py-2.5 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-rose-200 dark:shadow-none hover:bg-rose-600 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Close
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500"></div>
        </div>
    </div>
</x-app-layout>
