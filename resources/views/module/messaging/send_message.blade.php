<x-app-layout title="Send Message">
    <div class="flex flex-col items-center">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="w-full max-w-2xl mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-800 dark:text-white uppercase">Send <span class="text-primary-600">Message</span></h1>
                <p class="text-slate-500 dark:text-slate-400 font-medium mt-1">Compose and dispatch communications to your contacts.</p>
            </div>
            <div class="flex items-center gap-2 text-slate-400 font-medium mt-1 md:mt-0">
                <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-xs flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Home
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                <span class="text-slate-600 text-[10px] font-black uppercase tracking-widest">Send Message</span>
            </div>
        </div>

        <!-- CENTERED FORM CARD -->
        <div class="w-full max-w-2xl bg-white dark:bg-dark-card rounded-3xl border border-slate-200 dark:border-dark-border shadow-sm overflow-hidden p-6 md:p-10">
            <form action="#" method="POST" class="space-y-6">
                <div class="space-y-6">
                    <!-- Mobile Number Input -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-primary-500 italic">
                            Mobile Numbers <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-primary-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5.25a3 3 0 00-3 3v8.5a3 3 0 003 3h18a3 3 0 003-3v-8.5a3 3 0 00-3-3H3zm0 1.5h18a1.5 1.5 0 011.5 1.5v.191l-10.5 6.125L1.5 8.441V8.25A1.5 1.5 0 013 6.75zm10.5 7.61l10.5-6.125V16.75a1.5 1.5 0 01-1.5 1.5H3a1.5 1.5 0 01-1.5-1.5V8.235l10.5 6.125z"></path></svg>
                            </div>
                            <input type="text" name="mobile" placeholder="Mobile 1, Mobile 2, ..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-2xl py-3.5 pl-11 pr-4 text-sm font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-2 focus:ring-primary-500" required>
                        </div>
                        <p class="text-[9px] font-black text-slate-400 mt-2 px-2 uppercase tracking-widest italic opacity-60">Use commas to separate multiple mobile numbers.</p>
                    </div>

                    <!-- Message Textarea -->
                    <div class="group relative">
                        <label class="absolute -top-2 left-6 bg-white dark:bg-dark-card px-2 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-emerald-500 italic">
                            Your Message <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="message" rows="4" placeholder="Type your message here..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-3xl p-6 text-sm font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:ring-2 focus:ring-emerald-500 resize-none" required></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-3 pt-6 border-t border-slate-100 dark:border-dark-border">
                    <a href="{{ route('messaging.templates') }}" class="w-full sm:w-auto px-6 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-slate-600 dark:text-slate-400 rounded-2xl text-[11px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                        Cancel
                    </a>
                    <button type="submit" class="w-full sm:w-auto px-8 py-2.5 bg-emerald-500 text-white rounded-2xl text-[11px] font-black uppercase tracking-widest shadow-lg shadow-emerald-200/50 dark:shadow-none hover:bg-emerald-600 hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Send Message
                    </button>
                </div>
            </form>
        </div>

        <!-- BOTTOM ACCENT -->
        <div class="mt-8 h-1 w-full max-w-2xl bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full opacity-30"></div>
    </div>
</x-app-layout>
