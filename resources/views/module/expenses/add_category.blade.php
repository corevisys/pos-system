<x-app-layout title="Add Expense Category">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Add Expense Category</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('expenses.categories') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted">Categories</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">New Category</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Create a new expense category</p>
            </div>
        </div>

        <div class="max-w-3xl mx-auto" x-data="{ isSubmitting: false }">
            <x-card class="overflow-hidden p-0">
                <!-- Form Header -->
                <div class="px-5 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex items-center gap-2">
                    <div class="p-1.5 bg-success/10 rounded-lg">
                        <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    </div>
                    <h2 class="text-sm font-black text-text-primary dark:text-white uppercase tracking-widest">New Category</h2>
                </div>

                <div class="p-5 md:p-6">
                    <form action="{{ route('expenses.categories.store') }}" method="POST" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;" class="space-y-5">
                        @csrf

                        <!-- Category Name -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                Category Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="category_name" value="{{ old('category_name') }}" placeholder="e.g. Office Supplies" required class="input-base !py-3 !text-[11px] !font-bold">
                        </div>

                        <!-- Category Code -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                Category Code
                            </label>
                            <input type="text" name="category_code" value="{{ old('category_code') }}" placeholder="Optional" class="input-base !py-3 !text-[11px] !font-bold">
                        </div>

                        <!-- Description -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                Description
                            </label>
                            <textarea name="description" rows="3" placeholder="Category details..." class="input-base min-h-[70px] !py-3 !text-[11px] !font-medium resize-none">{{ old('description') }}</textarea>
                        </div>

                        <!-- Status -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">
                                Status
                            </label>
                            <select name="status" class="input-base !py-3 !text-[11px] !font-bold appearance-none cursor-pointer">
                                <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status', 1) == 0 ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex flex-col md:flex-row justify-center items-center gap-4 pt-4 border-t border-border dark:border-dark-border mt-6">
                            <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-56 !bg-success hover:!bg-success/90 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                                <span x-show="!isSubmitting" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Save Category
                                </span>
                                <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Saving Category...
                                </span>
                            </button>
                            <a href="{{ route('expenses.categories') }}" class="btn-secondary w-full md:w-56 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Close
                            </a>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
