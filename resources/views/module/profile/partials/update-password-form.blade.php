<section>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        <!-- Current Password -->
        <div class="group">
            <label for="update_password_current_password" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-rose-500 transition-colors">Current Password</label>
            <div class="relative">
                <input id="update_password_current_password" name="current_password" type="password" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-rose-500 font-semibold transition-all dark:text-white text-[11px]" autocomplete="current-password" placeholder="••••••••">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
        </div>

        <!-- New Password -->
        <div class="group">
            <label for="update_password_password" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">New Password</label>
            <div class="relative">
                <input id="update_password_password" name="password" type="password" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" autocomplete="new-password" placeholder="Min. 8 characters">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                </div>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div class="group">
            <label for="update_password_password_confirmation" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-emerald-500 transition-colors">Confirm Password</label>
            <div class="relative">
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-emerald-500 font-semibold transition-all dark:text-white text-[11px]" autocomplete="new-password" placeholder="Repeat new password">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-300">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-slate-900 dark:bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all shadow-lg flex items-center gap-2">
                Update Security
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[11px] font-black text-emerald-500 uppercase tracking-widest"
                >Security Updated!</p>
            @endif
        </div>
    </form>
</section>
