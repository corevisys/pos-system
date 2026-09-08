<x-app-layout title="Add Warehouse">
    <div class="flex flex-col items-center">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="w-full max-w-2xl mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Warehouse</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('warehouse.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold text-slate-400 uppercase tracking-widest">Warehouse List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Warehouse</span>
                </div>
            </div>
        </div>

        <!-- CENTERED FORM CARD -->
        <div class="w-full max-w-2xl bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden p-6 md:p-10">
            <form action="{{ route('warehouse.store') }}" method="POST" class="space-y-8">
                @csrf
                <div class="grid grid-cols-1 gap-6">
                    
                    <!-- Warehouse Name -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500 italic">
                            Warehouse Name <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-primary-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                            <input type="text" name="warehouse_name" value="{{ old('warehouse_name') }}" placeholder="Enter warehouse name..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-3 pl-10 pr-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-1 focus:ring-primary-500" required>
                        </div>
                        @error('warehouse_name')
                            <p class="text-rose-500 text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Mobile -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-emerald-500 italic">
                                Mobile Number
                            </label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h2.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                </div>
                                <input type="text" name="mobile" value="{{ old('mobile') }}" placeholder="Mobile..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-3 pl-10 pr-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-1 focus:ring-emerald-500">
                            </div>
                            @error('mobile')
                                <p class="text-rose-500 text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="group relative">
                            <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-emerald-500 italic">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"></path></svg>
                                </div>
                                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-3 pl-10 pr-4 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-1 focus:ring-emerald-500">
                            </div>
                            @error('email')
                                <p class="text-rose-500 text-[9px] font-bold mt-1 px-4 italic uppercase tracking-widest">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col md:flex-row justify-center items-center gap-3 pt-6 border-t border-slate-50 dark:border-dark-border">
                    <button type="submit" class="w-full md:w-40 py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-200/50 dark:shadow-none hover:bg-emerald-600 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                         <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        Save Data
                    </button>
                    <a href="{{ route('warehouse.list') }}" class="w-full md:w-40 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center justify-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- BOTTOM ACCENT -->
        <div class="mt-8 h-1 w-full max-w-2xl bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full opacity-30"></div>
    </div>
</x-app-layout>
