<x-app-layout title="Edit Warehouse">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Edit Warehouse</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('warehouse.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-widest">Warehouse List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Edit Warehouse</span>
                </div>
            </div>
            <a href="{{ route('warehouse.list') }}" class="btn-secondary w-full md:w-auto">Back to List</a>
        </div>

        <form action="{{ route('warehouse.update', $warehouse->id) }}" method="POST" class="max-w-3xl mx-auto" x-data="{ isSubmitting: false }" @submit="if(isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
            @csrf
            @method('PUT')
            <x-card class="overflow-hidden p-0">
                <div class="px-6 py-6">
                    <div class="grid grid-cols-1 gap-y-8">

                        <!-- Warehouse Name -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-primary">Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse_name" value="{{ old('warehouse_name', $warehouse->warehouse_name) }}" required placeholder="e.g. Main Branch" class="input-base !py-3 !text-[11px] !font-bold">
                            @error('warehouse_name')
                                <p class="text-danger text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Mobile -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-success">Mobile Number</label>
                                <input type="text" name="mobile" value="{{ old('mobile', $warehouse->mobile) }}" placeholder="Mobile..." class="input-base !py-3 !text-[11px] !font-bold">
                                @error('mobile')
                                    <p class="text-danger text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="group relative">
                                <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 transition-colors group-focus-within:text-success">Email Address</label>
                                <input type="email" name="email" value="{{ old('email', $warehouse->email) }}" placeholder="Email..." class="input-base !py-3 !text-[11px] !font-bold">
                                @error('email')
                                    <p class="text-danger text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Status Toggle (soft-disable semantics preserved) -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Status</label>
                            <div class="flex items-center gap-4 bg-background dark:bg-dark-card border border-border dark:border-dark-border rounded-input p-3">
                                <label class="flex items-center cursor-pointer gap-3">
                                    <div class="relative">
                                        <input type="hidden" name="status" value="0">
                                        <input type="checkbox" name="status" value="1" {{ old('status', $warehouse->status) == 1 ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-10 h-5 bg-slate-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary-600"></div>
                                    </div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-text-muted peer-checked:text-primary-600">Active</span>
                                </label>
                            </div>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex flex-col md:flex-row justify-center items-center gap-4 pt-4 border-t border-border dark:border-dark-border mt-2">
                            <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-56 !bg-success hover:!bg-success/90 !py-3 !text-[11px] uppercase tracking-widest disabled:opacity-50 disabled:pointer-events-none">
                                <span x-show="!isSubmitting" class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Update Data
                                </span>
                                <span x-show="isSubmitting" class="flex items-center justify-center gap-2" x-cloak>
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Updating...
                                </span>
                            </button>
                            <a href="{{ route('warehouse.list') }}" class="btn-secondary w-full md:w-56 !bg-amber-500 !border-amber-500 !text-white hover:!bg-amber-600 !py-3 !text-[11px] uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
