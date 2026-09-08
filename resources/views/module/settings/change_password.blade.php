<x-app-layout title="Change Password">
    <div>
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Change Password <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Update Security Credentials</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Change Password</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Update Security
                </button>
            </div>
        </div>

        <div class="max-w-xl mx-auto md:mt-8">
            <!-- FORM CONTAINER -->
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                
                <div class="p-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5">
                    <h2 class="text-[9px] font-black uppercase tracking-widest text-slate-400">Please Enter Valid Data</h2>
                </div>

                <div class="p-6 md:p-8 space-y-6">
                    
                    <!-- Current Password -->
                    <div class="group relative" x-data="{ show: false }">
                        <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Current Password <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                             <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <input :type="show ? 'text' : 'password'" placeholder="Enter current password" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                             <button @click="show = !show" type="button" class="text-slate-400 hover:text-primary-600 transition-colors">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.04m4.066-1.16a10.148 10.148 0 016.593-1.801m4.624 4.624A10.05 10.05 0 0121.542 12c-1.274 4.057-5.064 7-9.542 7-1.173 0-2.29-.21-3.32-.589M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9.9L21 3"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="group relative" x-data="{ show: false }">
                        <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">New Password <span class="text-rose-500">*</span></label>
                        <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                             <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3c1.268 0 2.39.234 3.468.657m1.62 3.861a10.033 10.033 0 014.15 4.408m-1.378 4.41l-.1-.054M4 17.5a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0zM7 9a2 2 0 114 0 2 2 0 01-4 0z"></path></svg>
                            <input :type="show ? 'text' : 'password'" placeholder="Enter new password" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                             <button @click="show = !show" type="button" class="text-slate-400 hover:text-primary-600 transition-colors">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.04m4.066-1.16a10.148 10.148 0 016.593-1.801m4.624 4.624A10.05 10.05 0 0121.542 12c-1.274 4.057-5.064 7-9.542 7-1.173 0-2.29-.21-3.32-.589M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9.9L21 3"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="group relative" x-data="{ show: false }">
                        <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Confirm Password <span class="text-rose-500">*</span></label>
                         <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <input :type="show ? 'text' : 'password'" placeholder="Confirm new password" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                             <button @click="show = !show" type="button" class="text-slate-400 hover:text-primary-600 transition-colors">
                                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.04m4.066-1.16a10.148 10.148 0 016.593-1.801m4.624 4.624A10.05 10.05 0 0121.542 12c-1.274 4.057-5.064 7-9.542 7-1.173 0-2.29-.21-3.32-.589M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9.9L21 3"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex flex-col gap-3 pt-2">
                        <button class="w-full py-3 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Update Credentials
                        </button>
                        <p class="text-center text-[9px] text-slate-400 font-bold uppercase tracking-widest">Forgot password? Contact Administrator</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>