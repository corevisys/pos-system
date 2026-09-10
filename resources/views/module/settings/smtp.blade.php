<x-app-layout title="SMTP Settings">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">SMTP Settings <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Email Configuration</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">SMTP Settings</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2">
                <!-- FORM CONTAINER -->
                <form action="{{ route('settings.smtp.update') }}" method="POST">
                    @csrf
                    <x-card padding="p-0" class="overflow-hidden">
                        <div class="p-6 md:p-8 space-y-6">

                            <!-- SMTP Status -->
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">SMTP Status</label>
                                <select name="smtp_status" class="input-base appearance-none cursor-pointer">
                                    <option value="1" {{ $store->smtp_status == 1 ? 'selected' : '' }}>Enable</option>
                                    <option value="0" {{ $store->smtp_status == 0 ? 'selected' : '' }}>Disable</option>
                                </select>
                            </div>

                            <!-- SMTP Host -->
                            <div class="space-y-1">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">SMTP Host</label>
                                <input type="text" name="smtp_host" value="{{ $store->smtp_host }}" placeholder="e.g. smtp.gmail.com" class="input-base">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- SMTP Port -->
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">SMTP Port</label>
                                    <input type="text" name="smtp_port" value="{{ $store->smtp_port }}" placeholder="e.g. 587" class="input-base">
                                </div>

                                <!-- SMTP User -->
                                <div class="space-y-1">
                                    <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">SMTP User</label>
                                    <input type="text" name="smtp_user" value="{{ $store->smtp_user }}" placeholder="your email" class="input-base">
                                </div>
                            </div>

                            <!-- SMTP Password -->
                            <div class="space-y-1" x-data="{ show: false }">
                                <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">SMTP Password</label>
                                <div class="relative">
                                    <input :type="show ? 'text' : 'password'" name="smtp_pass" value="{{ $store->smtp_pass }}" class="input-base !pr-10">
                                    <button @click="show = !show" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-primary transition-colors">
                                        <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.04m4.066-1.16a10.148 10.148 0 016.593-1.801m4.624 4.624A10.05 10.05 0 0121.542 12c-1.274 4.057-5.064 7-9.542 7-1.173 0-2.29-.21-3.32-.589M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9.9L21 3"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="reset" class="btn-secondary">Reset</button>
                                <button type="submit" class="btn-primary">Save Configuration</button>
                            </div>
                        </div>
                    </x-card>
                </form>
            </div>

            <!-- SIDEBAR: TEST CONNECTION -->
            <div class="xl:col-span-1">
                <x-card class="sticky top-4">
                    <h3 class="text-[10px] font-black uppercase text-text-muted tracking-widest flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Test SMTP Connection
                    </h3>
                    <p class="text-[9px] font-bold text-text-muted leading-relaxed uppercase tracking-wider mb-4">Send a test email to verify your SMTP settings are correct.</p>

                    <div class="space-y-1 mb-4">
                        <label class="text-[9px] font-black uppercase tracking-widest text-text-muted">Test Email</label>
                        <input type="email" id="test_email" placeholder="recipient@example.com" class="input-base">
                    </div>

                    <button type="button" onclick="sendTestEmail()" id="test_btn" class="btn-primary w-full justify-center">
                        <span>Send Test Email</span>
                    </button>
                </x-card>
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
