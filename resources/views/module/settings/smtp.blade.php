<x-app-layout title="SMTP Settings">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">SMTP Settings <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Email Configuration</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">SMTP Settings</span>
                </div>
            </div>
        </div>


        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2">
                <!-- FORM CONTAINER -->
                <form action="{{ route('settings.smtp.update') }}" method="POST" class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    @csrf
                    <div class="p-6 md:p-8 space-y-6">
                        
                        <!-- SMTP Status -->
                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">SMTP Status</label>
                            <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all">
                                 <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <select name="smtp_status" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none appearance-none cursor-pointer">
                                    <option value="1" {{ $store->smtp_status == 1 ? 'selected' : '' }}>Enable</option>
                                    <option value="0" {{ $store->smtp_status == 0 ? 'selected' : '' }}>Disable</option>
                                </select>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                        <!-- SMTP Host -->
                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">SMTP Host</label>
                             <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                <input type="text" name="smtp_host" value="{{ $store->smtp_host }}" placeholder="e.g. smtp.gmail.com" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- SMTP Port -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">SMTP Port</label>
                                 <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    <input type="text" name="smtp_port" value="{{ $store->smtp_port }}" placeholder="e.g. 587" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                                </div>
                            </div>

                            <!-- SMTP User -->
                            <div class="group relative">
                                <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">SMTP User</label>
                                 <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    <input type="text" name="smtp_user" value="{{ $store->smtp_user }}" placeholder="your email" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                                </div>
                            </div>
                        </div>

                        <!-- SMTP Password -->
                        <div class="group relative" x-data="{ show: false }">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">SMTP Password</label>
                             <div class="flex items-center gap-3 bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 focus-within:border-primary-500 hover:border-primary-500 transition-all shadow-inner-sm">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <input :type="show ? 'text' : 'password'" name="smtp_pass" value="{{ $store->smtp_pass }}" class="w-full bg-transparent border-none text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none placeholder-slate-400">
                                <button @click="show = !show" type="button" class="text-slate-400 hover:text-primary-600 transition-colors">
                                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.04m4.066-1.16a10.148 10.148 0 016.593-1.801m4.624 4.624A10.05 10.05 0 0121.542 12c-1.274 4.057-5.064 7-9.542 7-1.173 0-2.29-.21-3.32-.589M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9.9L21 3"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="reset" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Reset</button>
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none">Save Configuration</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- SIDEBAR: TEST CONNECTION -->
            <div class="xl:col-span-1">
                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-6 border border-slate-100 dark:border-dark-border space-y-4 sticky top-4">
                    <h3 class="text-[10px] font-black uppercase text-primary-500 tracking-widest flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Test SMTP Connection
                    </h3>
                    <p class="text-[9px] font-bold text-slate-400 leading-relaxed uppercase tracking-wider">Send a test email to verify your SMTP settings are correct.</p>
                    
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 bg-slate-50 dark:bg-slate-800 px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors">Test Email</label>
                        <input type="email" id="test_email" placeholder="recipient@example.com" class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm">
                    </div>

                    <button type="button" onclick="sendTestEmail()" id="test_btn" class="w-full py-2.5 bg-slate-800 dark:bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-200/50">
                        <span>Send Test Email</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function sendTestEmail() {
            const email = document.getElementById('test_email').value;
            const btn = document.getElementById('test_btn');
            const originalText = btn.innerHTML;

            if(!email) {
                showError('Please enter a test email address.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Sending...';

            fetch("{{ route('settings.smtp.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ email })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success' || data.success) {
                    showSuccess(data.message || 'Test email sent successfully!');
                } else {
                    showError(data.message || 'Failed to send test email.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Something went wrong. Please check console.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    </script>
</x-app-layout>
