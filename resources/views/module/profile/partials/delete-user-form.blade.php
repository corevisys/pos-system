<section class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex-1">
            <p class="text-[10px] font-bold text-slate-400 leading-relaxed">
                Once your account is deleted, all of its resources and data will be permanently deleted. Please download any data you wish to retain before proceeding.
            </p>
        </div>
        <button 
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="px-5 py-2.5 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-600 transition-all flex items-center gap-2 group shadow-lg shadow-rose-200 dark:shadow-none"
        >
            Delete Account
            <svg class="w-3.5 h-3.5 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 bg-white dark:bg-dark-card rounded-3xl overflow-hidden relative">
            @csrf
            @method('delete')

            <div class="absolute top-0 right-0 w-32 h-32 bg-rose-500/10 blur-3xl rounded-full -mr-16 -mt-16"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-rose-50 dark:bg-rose-500/10 rounded-2xl flex items-center justify-center text-rose-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-black tracking-tight text-slate-800 dark:text-white uppercase">Critical Action</h2>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5 italic">Account Deletion Protocol</p>
                    </div>
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-400 font-medium leading-relaxed mb-8">
                    Once your account is deleted, all of its data will be permanently cleared. Please enter your password to confirm you would like to permanently delete your account.
                </p>

                <div class="group mb-8">
                    <label for="password" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-2 group-focus-within:text-rose-500 transition-colors">Confirm Password</label>
                    <input id="password" name="password" type="password" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-rose-500 font-semibold transition-all dark:text-white text-[11px]" placeholder="••••••••">
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">
                        Cancel
                    </button>

                    <button type="submit" class="px-6 py-2.5 bg-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-600 transition-all shadow-lg shadow-rose-200 dark:shadow-none">
                        Confirm Deletion
                    </button>
                </div>
            </div>
        </form>
    </x-modal>
</section>
