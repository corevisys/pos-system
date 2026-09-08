<x-app-layout title="Site Settings">
    <div x-data="{ activeTab: 'site' }">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Site Settings <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Add/Update Site Settings</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Site Settings</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    Save Changes
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8">
            <!-- TABS & FORM CONTAINER -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <!-- Tab Headers -->
                <div class="flex border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 p-1">
                    <button @click="activeTab = 'site'" 
                            :class="activeTab === 'site' ? 'text-primary-600 bg-white dark:bg-dark-card shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                            class="px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                        Site Configuration
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="p-6 md:p-8">
                    <div x-show="activeTab === 'site'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="max-w-4xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                                
                                <!-- Site Name -->
                                <div class="group relative">
                                    <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Site Name <span class="text-rose-500">*</span></label>
                                    <input type="text" value="Shop Keeper" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm">
                                    <p class="mt-1 text-[9px] text-slate-400 font-bold italic pl-1">The public name of your marketplace.</p>
                                </div>

                                <!-- Site Logo -->
                                <div class="space-y-3">
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest pl-1">Site Logo</label>
                                    <div class="flex items-start gap-6">
                                        <div class="relative group w-32 h-32 border-2 border-dashed border-slate-200 dark:border-dark-border rounded-2xl hover:border-primary-500 transition-all bg-slate-50/30 dark:bg-white/5 flex flex-col items-center justify-center text-center gap-2 cursor-pointer">
                                            <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                            <div class="w-10 h-10 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform duration-300">
                                                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <p class="text-[8px] text-slate-400 font-black uppercase">Max 300KB</p>
                                        </div>

                                        <!-- Logo Preview -->
                                        <div class="p-3 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-2xl shadow-sm">
                                            <div class="flex flex-col items-center gap-2">
                                                <img src="https://ui-avatars.com/api/?name=SK&background=2563eb&color=fff&size=200" class="w-24 h-24 object-contain rounded" alt="Logo Preview">
                                                <div class="text-[8px] font-black text-slate-300 uppercase tracking-widest">Preview</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex justify-end gap-2">
                    <button class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    <button class="px-4 py-2 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none">Update Settings</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
