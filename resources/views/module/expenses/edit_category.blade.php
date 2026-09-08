<x-app-layout title="Edit Expense Category">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight">Edit Expense Category</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('expenses.categories') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold text-slate-400">Categories</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold">Edit Category</span>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto">


            <!-- MAIN FORM CARD -->
            <div class="bg-white dark:bg-dark-card p-4 md:p-6 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm">
                
                <div class="flex items-center gap-3 mb-6 border-b border-slate-50 dark:border-dark-border pb-4">
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl">
                         <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">Edit Category</h2>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Update expense category details</p>
                    </div>
                </div>

                <form action="{{ route('expenses.categories.update', $category->id) }}" method="POST" class="space-y-4">
                    @csrf
                    
                    <!-- Form Fields Grid -->
                    <div class="grid grid-cols-1 gap-y-4">
                        
                        <!-- Category Name -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Category Name <span class="text-rose-500">*</span></label>
                            <div class="flex-1">
                                <input type="text" name="category_name" value="{{ $category->category_name }}" placeholder="e.g. Office Supplies" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">
                            </div>
                        </div>

                        <!-- Category Code -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Category Code</label>
                            <div class="flex-1">
                                <input type="text" name="category_code" value="{{ $category->category_code }}" placeholder="Optional" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="flex flex-col md:flex-row items-start gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right mt-2">Description</label>
                            <div class="flex-1">
                                <textarea name="description" rows="3" placeholder="Category details..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500">{{ $category->description }}</textarea>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
                            <label class="w-full md:w-40 text-[9px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-widest md:text-right">Status</label>
                            <div class="flex-1">
                                <select name="status" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2 px-4 text-[10px] font-bold transition-all focus:ring-1 focus:ring-primary-500 appearance-none cursor-pointer">
                                    <option value="1" {{ $category->status == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ $category->status == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center gap-3 pt-4 border-t border-slate-50 dark:border-dark-border mt-6">
                        <button type="submit" class="px-8 py-2 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            Update Category
                        </button>
                        <a href="{{ route('expenses.categories') }}" class="px-8 py-2 bg-amber-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 transition-all shadow-lg shadow-amber-200/50 dark:shadow-none flex items-center justify-center">
                            Close
                        </a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-app-layout>
