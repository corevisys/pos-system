<x-app-layout title="Add Language">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Add New Language</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('settings.languages.index') }}" class="hover:text-primary-600 transition-colors text-[10px] font-black uppercase tracking-wider">Languages</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Add Language</span>
                </div>
            </div>
        </div>

        <!-- MAIN CONTAINER -->
        <div class="max-w-xl mx-auto">
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden p-8">
                <form action="{{ route('settings.languages.store') }}" method="POST" class="space-y-8">
                    @csrf
                    
                    <div class="space-y-6">
                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Language Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="language" value="{{ old('language') }}" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-2xl py-3 px-4 text-[11px] font-black text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 focus:bg-white dark:focus:bg-dark-card shadow-inner-sm" placeholder="e.g. English" required>
                            @error('language')
                                <p class="text-[9px] font-bold text-rose-500 mt-1 uppercase tracking-widest italic ml-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10 italic opacity-70">Status</label>
                            <select name="status" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-2xl py-3 px-4 text-[11px] font-black text-slate-700 dark:text-slate-300 outline-none appearance-none transition-all focus:border-primary-500">
                                <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-end items-center gap-3 pt-4">
                        <button type="submit" class="w-full md:w-32 py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 active:scale-95 transition-all flex items-center justify-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Save
                        </button>
                        <a href="{{ route('settings.languages.index') }}" class="w-full md:w-32 py-2.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 active:scale-95 transition-all text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
