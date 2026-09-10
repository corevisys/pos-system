<x-app-layout title="Edit Language">
    <div x-data="{ isSubmitting: false }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Edit Language</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('settings.languages.index') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">Languages</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">Edit Language</span>
                </div>
            </div>
        </div>

        <!-- MAIN CONTAINER -->
        <div class="max-w-xl mx-auto">
            <x-card padding="p-8">
                <form action="{{ route('settings.languages.update', $language->id) }}" method="POST" class="space-y-8" @submit="isSubmitting = true">
                    @csrf

                    <div class="space-y-6">
                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Language Name <span class="text-danger">*</span></label>
                            <input type="text" name="language" value="{{ old('language', $language->language) }}" class="input-base" placeholder="e.g. English" required>
                            @error('language')
                                <p class="text-[9px] font-bold text-danger mt-1 uppercase tracking-widest italic ml-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[9px] font-black uppercase text-text-muted tracking-widest z-10 italic opacity-70">Status</label>
                            @if($language->status == 1)
                                <input type="hidden" name="status" value="1">
                                <div class="w-full bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-input py-3 px-4 text-[11px] font-black text-emerald-700 dark:text-emerald-300 flex items-center justify-between">
                                    <span>Active (System Primary)</span>
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <p class="text-[9px] font-medium text-text-muted italic mt-1.5 ml-1">To change the active language, activate another language from the languages list.</p>
                            @else
                                <select name="status" class="input-base appearance-none cursor-pointer">
                                    <option value="0" {{ old('status', $language->status) == '0' ? 'selected' : '' }}>Inactive</option>
                                    <option value="1" {{ old('status', $language->status) == '1' ? 'selected' : '' }}>Active (Sets as primary active language)</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                                <p class="text-[9px] font-medium text-text-muted italic mt-1.5 ml-1">Changing to Active will automatically deactivate the currently active language.</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-end items-center gap-3 pt-4">
                        <button type="submit" :disabled="isSubmitting" class="btn-primary w-full md:w-32 !bg-success hover:!bg-success/90 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Update
                        </button>
                        <a href="{{ route('settings.languages.index') }}" class="btn-secondary w-full md:w-32">Cancel</a>
                    </div>
                </form>
            </x-card>
        </div>

    </div>
</x-app-layout>
