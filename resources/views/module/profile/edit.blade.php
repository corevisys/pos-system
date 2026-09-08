<x-app-layout title="User Profile">
    <div>
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-lg font-black tracking-tight dark:text-white uppercase italic">Account Settings</h1>
                <p class="text-[9px] text-slate-400 font-medium -mt-0.5 whitespace-nowrap">Manage your profile information, security, and account preferences.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard') }}" class="px-3 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- PROFILE INFO (Spans 2 columns like dashboard main section) -->
            <div class="lg:col-span-2 bg-white dark:bg-dark-card rounded-2xl p-4 md:p-5 border border-slate-100 dark:border-dark-border shadow-sm relative overflow-hidden group hover:shadow-xl transition-all duration-300">
                <div class="absolute top-0 right-0 w-48 h-48 bg-primary-500/5 blur-3xl rounded-full -mr-20 -mt-20 group-hover:bg-primary-500/10 transition-colors"></div>
                <div class="relative z-10">
                    <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-6 italic border-b border-slate-50 dark:border-dark-border pb-2">Public Information</h3>
                    @include('module.profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="space-y-4">
                <!-- PASSWORD -->
                <div class="bg-white dark:bg-dark-card rounded-2xl p-4 md:p-5 border border-slate-100 dark:border-dark-border shadow-sm group hover:shadow-xl transition-all duration-300">
                    <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-6 italic border-b border-slate-50 dark:border-dark-border pb-2">Security Credentials</h3>
                    @include('module.profile.partials.update-password-form')
                </div>

                <!-- DANGER ZONE (Admin Only) -->
                @if(auth()->user()->isSuperAdmin())
                <div class="bg-rose-50/10 dark:bg-rose-500/5 rounded-2xl p-4 md:p-5 border border-rose-100 dark:border-rose-500/10 shadow-sm hover:shadow-xl hover:bg-rose-50/30 transition-all duration-300">
                    <h3 class="text-[10px] font-bold text-rose-500 uppercase tracking-[0.2em] mb-4 italic">Danger Zone</h3>
                    <p class="text-[10px] font-bold text-slate-400 mb-4">Once your account is deleted, all of its data will be permanently cleared.</p>
                    @include('module.profile.partials.delete-user-form')
                </div>
                @else
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 md:p-5 border border-slate-200 dark:border-dark-border opacity-60 shadow-inner">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] italic">Danger Zone</h3>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400">Account deletion is restricted to system administrators only.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
