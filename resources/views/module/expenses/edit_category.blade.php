<x-app-layout title="Edit Expense Category">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight flex items-center gap-2 text-text-primary dark:text-dark-text">
                    <div class="w-8 h-8 rounded-xl bg-success/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </div>
                    Edit Expense Category
                </h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px]">Home</a>
                    <span class="text-[10px] text-text-muted">/</span>
                    <a href="{{ route('expenses.categories') }}" class="hover:text-primary transition-colors text-[10px]">Categories</a>
                    <span class="text-[10px] text-text-muted">/</span>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Category</span>
                </div>
            </div>
            <a href="{{ route('expenses.categories') }}" class="btn-secondary w-full md:w-auto">Back to List</a>
        </div>

        <form action="{{ route('expenses.categories.update', $category->id) }}" method="POST" class="max-w-3xl mx-auto" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            <x-card class="overflow-hidden p-0">
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-y-8">

                        <!-- Category Name -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" value="{{ old('category_name', $category->category_name) }}" required placeholder="e.g. Office Supplies" class="input-base !py-3 !text-[11px] !font-bold">
                        </div>

                        <!-- Category Code -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Category Code</label>
                            <input type="text" name="category_code" value="{{ old('category_code', $category->category_code) }}" placeholder="Optional" class="input-base !py-3 !text-[11px] !font-bold">
                        </div>

                        <!-- Description -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Description</label>
                            <textarea name="description" rows="3" placeholder="Category details..." class="input-base min-h-[70px] !py-3 !text-[11px] !font-medium resize-none">{{ old('description', $category->description) }}</textarea>
                        </div>

                        <!-- Status -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Status</label>
                            <select name="status" class="input-base !py-3 !text-[11px] !font-bold appearance-none cursor-pointer">
                                <option value="1" {{ old('status', $category->status) == 1 ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status', $category->status) == 0 ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex flex-col md:flex-row justify-center items-center gap-4 pt-4 border-t border-border dark:border-dark-border mt-2">
                            <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-56 !bg-success hover:!bg-success/90 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                                <span x-show="!isSubmitting" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Update Category
                                </span>
                                <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Updating Category...
                                </span>
                            </button>
                            <a href="{{ route('expenses.categories') }}" class="btn-secondary w-full md:w-56 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Close
                            </a>
                        </div>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
