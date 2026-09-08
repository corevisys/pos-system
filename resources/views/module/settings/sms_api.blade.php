<x-app-layout title="SMS API Settings">
    <div x-data="{
        activeProvider: {{ (int) $store->sms_status }},
        sandboxMode: {{ config('sms.sandbox', false) ? 'true' : 'false' }},
        activeTab: '{{ match((int) $store->sms_status) { 2 => 'alpha', 3 => 'bulksms', 4 => 'fivemojo', 5 => 'ssl', default => 'http' } }}',
        params: {{ $httpParams->count() > 0 ? $httpParams->map(fn($p) => ['key' => $p->key, 'value' => $p->key_value])->toJson() : "[{ key: 'base_url', value: '' }, { key: 'mobile_key', value: 'mobiles' }, { key: 'message_key', value: 'message' }, { key: 'APIKey', value: '' }]" }},
        alphaParams: {{ $alphaParams->count() > 0 ? $alphaParams->map(fn($p) => ['key' => $p->key, 'value' => $p->key_value])->toJson() : "[{ key: 'api_key', value: '' }, { key: 'sender_id', value: '' }]" }},
        bulksmsParams: {{ $bulksmsParams->count() > 0 ? $bulksmsParams->map(fn($p) => ['key' => $p->key, 'value' => $p->key_value])->toJson() : "[{ key: 'api_key', value: '' }, { key: 'type', value: 'text' }, { key: 'senderid', value: '' }]" }},
        sslParams: {{ $sslParams->count() > 0 ? $sslParams->map(fn($p) => ['key' => $p->key, 'value' => $p->key_value])->toJson() : "[{ key: 'api_token', value: '' }, { key: 'sid', value: '' }]" }},
        addParam(list) { this[list].push({ key: '', value: '' }) },
        removeParam(index, list) { this[list].splice(index, 1) },
        setProvider(value, tab) { this.activeProvider = value; this.activeTab = tab; },
        activeProviderLabel() {
            if (this.sandboxMode) return '🟡 Sandbox (Testing Only)';
            const map = { 0: '⚫ Disabled', 1: '🟢 HTTP/URL API', 2: '🟢 Alpha SMS', 3: '🟢 BulkSMS BD', 4: '🟢 FiveMojo', 5: '🟢 SSL Wireless' };
            return map[this.activeProvider] ?? '⚫ Disabled';
        }
    }">

        {{-- SANDBOX ENV WARNING BANNER --}}
        @if(config('sms.sandbox', false))
        <div class="mb-5 flex items-start gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-2xl p-4">
            <div class="w-8 h-8 bg-amber-400 rounded-xl flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="text-[11px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-0.5">Sandbox Mode Active</p>
                <p class="text-[10px] font-medium text-amber-600 dark:text-amber-300 leading-relaxed">
                    <strong>No real SMS is being sent.</strong> The Sandbox provider is active via environment configuration and overrides all settings below.
                    To disable, set <code class="bg-amber-200 dark:bg-amber-800 px-1 rounded font-mono text-amber-800 dark:text-amber-200">SMS_SANDBOX=false</code> in your <code class="bg-amber-200 dark:bg-amber-800 px-1 rounded font-mono text-amber-800 dark:text-amber-200">.env</code> file.
                </p>
            </div>
        </div>
        @endif

        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">SMS / WhatsApp API <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Gateway Settings</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">SMS / WhatsApp API</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Sending via:</span>
                <span x-text="activeProviderLabel()" class="text-[10px] font-black px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"></span>
            </div>
        </div>

        <form action="{{ route('sms.settings.update') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="sms_status" :value="activeProvider" id="sms_status_input">

            {{-- ══════════════════════════════ STEP 1: PROVIDER CARDS ══════════════════════════════ --}}
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-4 border-b border-slate-50 dark:border-dark-border">
                    <h2 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">Step 1 — Select Active Provider</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Click a provider card to set it as active. Only one provider can send SMS at a time.</p>
                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

                    {{-- Disable SMS --}}
                    <button type="button" id="provider-card-0" @click="setProvider(0, 'http')"
                        :class="activeProvider === 0 && !sandboxMode ? 'border-slate-400 dark:border-slate-500 bg-slate-50 dark:bg-slate-800/60 ring-2 ring-slate-300 dark:ring-slate-600' : 'border-slate-100 dark:border-dark-border hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            </div>
                            <span x-show="activeProvider === 0 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-full">Selected</span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">Disable SMS</p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">No SMS will be sent from this store.</p>
                        </div>
                    </button>

                    {{-- HTTP/URL API --}}
                    <button type="button" id="provider-card-1" @click="setProvider(1, 'http')"
                        :class="activeProvider === 1 && !sandboxMode ? 'border-primary-400 dark:border-primary-600 bg-primary-50/50 dark:bg-primary-900/20 ring-2 ring-primary-200 dark:ring-primary-800' : 'border-slate-100 dark:border-dark-border hover:border-primary-200 dark:hover:border-primary-900 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </div>
                            <span x-show="activeProvider === 1 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Active
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">HTTP / URL API</p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">Generic HTTP GET/POST — works with any URL-based SMS gateway.</p>
                        </div>
                    </button>

                    {{-- Alpha SMS --}}
                    <button type="button" id="provider-card-2" @click="setProvider(2, 'alpha')"
                        :class="activeProvider === 2 && !sandboxMode ? 'border-indigo-400 dark:border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/20 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-100 dark:border-dark-border hover:border-indigo-200 dark:hover:border-indigo-900 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <span x-show="activeProvider === 2 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Active
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">Alpha SMS</p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">api.sms.net.bd — popular BD gateway. Requires API Key &amp; Sender ID.</p>
                        </div>
                    </button>

                    {{-- BulkSMS BD --}}
                    <button type="button" id="provider-card-3" @click="setProvider(3, 'bulksms')"
                        :class="activeProvider === 3 && !sandboxMode ? 'border-sky-400 dark:border-sky-600 bg-sky-50/50 dark:bg-sky-900/20 ring-2 ring-sky-200 dark:ring-sky-800' : 'border-slate-100 dark:border-dark-border hover:border-sky-200 dark:hover:border-sky-900 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-sky-100 dark:bg-sky-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <span x-show="activeProvider === 3 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Active
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">BulkSMS BD</p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">bulksmsbd.net — BD gateway. Requires API Key, Type &amp; Sender ID.</p>
                        </div>
                    </button>

                    {{-- SSL Wireless --}}
                    <button type="button" id="provider-card-5" @click="setProvider(5, 'ssl')"
                        :class="activeProvider === 5 && !sandboxMode ? 'border-emerald-400 dark:border-emerald-600 bg-emerald-50/50 dark:bg-emerald-900/20 ring-2 ring-emerald-200 dark:ring-emerald-800' : 'border-slate-100 dark:border-dark-border hover:border-emerald-200 dark:hover:border-emerald-900 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <span x-show="activeProvider === 5 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Active
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">SSL Wireless</p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">smsplus.sslwireless.com — enterprise gateway. Requires API Token &amp; SID.</p>
                        </div>
                    </button>

                    {{-- FiveMojo --}}
                    <button type="button" id="provider-card-4" @click="setProvider(4, 'fivemojo')"
                        :class="activeProvider === 4 && !sandboxMode ? 'border-violet-400 dark:border-violet-600 bg-violet-50/50 dark:bg-violet-900/20 ring-2 ring-violet-200 dark:ring-violet-800' : 'border-slate-100 dark:border-dark-border hover:border-violet-200 dark:hover:border-violet-900 bg-white dark:bg-dark-card'"
                        class="relative group flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all text-left cursor-pointer">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-violet-100 dark:bg-violet-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2v-3.28a4 4 0 00-7.15-2.48l-.24-.35A2 2 0 009 12H6a2 2 0 00-2 2v5a2 2 0 002 2z"/></svg>
                            </div>
                            <span x-show="activeProvider === 4 && !sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Active
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">FiveMojo <span class="text-[9px] font-bold text-violet-500">WhatsApp</span></p>
                            <p class="text-[9px] font-medium text-slate-400 leading-relaxed mt-0.5">WhatsApp Business API. Requires Instance ID &amp; Token.</p>
                        </div>
                    </button>

                    {{-- SANDBOX — READ-ONLY / INFORMATIONAL, NO "Set Active" button --}}
                    <div id="provider-card-sandbox"
                        :class="sandboxMode ? 'border-amber-400 dark:border-amber-600 bg-amber-50/60 dark:bg-amber-900/20 ring-2 ring-amber-200 dark:ring-amber-800' : 'border-amber-100 dark:border-amber-900/30 bg-amber-50/30 dark:bg-amber-950/10'"
                        class="relative flex flex-col items-start gap-3 p-4 rounded-2xl border-2 transition-all cursor-not-allowed select-none">
                        <div class="flex items-center justify-between w-full">
                            <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                <span x-show="sandboxMode" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200 rounded-full flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span> Override Active
                                </span>
                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 rounded-full">Testing Only</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-amber-700 dark:text-amber-300">Sandbox <span class="text-[9px] font-bold text-amber-500">(Dev Only)</span></p>
                            <p class="text-[9px] font-medium text-amber-600/80 dark:text-amber-400/80 leading-relaxed mt-0.5">No real SMS is sent. Simulates success responses for local testing.</p>
                        </div>
                        <div class="w-full pt-2 mt-auto border-t border-amber-200/60 dark:border-amber-800/40">
                            <p class="text-[9px] font-bold text-amber-500 dark:text-amber-400 leading-relaxed">
                                🔒 Env-controlled — not configurable here.<br>
                                Set <code class="font-mono bg-amber-100 dark:bg-amber-900/50 px-1 rounded">SMS_SANDBOX=true/false</code> in <code class="font-mono bg-amber-100 dark:bg-amber-900/50 px-1 rounded">.env</code>
                            </p>
                        </div>
                    </div>

                </div>{{-- end provider card grid --}}
            </div>

            {{-- ══════════════════════════════ STEP 2: CONFIG TABS ══════════════════════════════ --}}
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-4 border-b border-slate-50 dark:border-dark-border">
                    <h2 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">Step 2 — Configure API Keys</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Set credentials for each provider. You can pre-configure multiple providers before switching.</p>
                </div>

                {{-- Config nav tabs --}}
                <div class="flex flex-wrap items-center gap-2 px-5 pt-4 pb-0">
                    <button type="button" @click="activeTab = 'http'"
                        :class="activeTab === 'http' ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 border-primary-200 dark:border-primary-800' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border">
                        HTTP/URL <span x-show="activeProvider === 1 && !sandboxMode" class="ml-1 w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block align-middle"></span>
                    </button>
                    <button type="button" @click="activeTab = 'alpha'"
                        :class="activeTab === 'alpha' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border">
                        Alpha SMS <span x-show="activeProvider === 2 && !sandboxMode" class="ml-1 w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block align-middle"></span>
                    </button>
                    <button type="button" @click="activeTab = 'bulksms'"
                        :class="activeTab === 'bulksms' ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 border-sky-200 dark:border-sky-800' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border">
                        BulkSMS BD <span x-show="activeProvider === 3 && !sandboxMode" class="ml-1 w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block align-middle"></span>
                    </button>
                    <button type="button" @click="activeTab = 'ssl'"
                        :class="activeTab === 'ssl' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border">
                        SSL Wireless <span x-show="activeProvider === 5 && !sandboxMode" class="ml-1 w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block align-middle"></span>
                    </button>
                    <button type="button" @click="activeTab = 'fivemojo'"
                        :class="activeTab === 'fivemojo' ? 'bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 border-violet-200 dark:border-violet-800' : 'text-slate-500 border-transparent hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border">
                        FiveMojo <span x-show="activeProvider === 4 && !sandboxMode" class="ml-1 w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block align-middle"></span>
                    </button>
                </div>

                <div class="p-5 space-y-6">

                    {{-- HTTP/URL Config --}}
                    <div x-show="activeTab === 'http'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="space-y-5">
                            <div class="bg-primary-600 dark:bg-primary-900/40 rounded-2xl p-5 text-white">
                                <h3 class="text-xs font-black mb-3 uppercase tracking-widest">HTTP / URL API Reference</h3>
                                <code class="block bg-black/20 p-3 rounded-lg text-[9px] break-all border border-white/10 font-mono mb-3">https://example.com/api/SendSMS?APIKey=QWERTY&amp;senderid=ABCDEF&amp;mobiles=88019xxx&amp;message=test</code>
                                <div class="bg-white/10 p-3 rounded-xl text-[9px] space-y-1">
                                    <p class="font-black uppercase tracking-widest text-primary-200 mb-1">Required keys</p>
                                    <p>• <code class="font-mono">base_url</code> — Full endpoint URL (without ?)</p>
                                    <p>• <code class="font-mono">mobile_key</code> — Param name for phone (e.g. <em>mobiles</em>)</p>
                                    <p>• <code class="font-mono">message_key</code> — Param name for message (e.g. <em>message</em>)</p>
                                    <p class="text-amber-300 font-bold italic mt-1">NOTE: Do not add "?" inside any input box.</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-1">
                                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">API Parameters</h4>
                                <button type="button" @click="addParam('params')" class="w-8 h-8 bg-primary-500 text-white rounded-lg flex items-center justify-center hover:bg-primary-600 transition-all shadow-md shadow-primary-200 dark:shadow-none">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <template x-for="(param, index) in params" :key="index">
                                    <div class="flex items-center gap-3 group" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2">
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Key</label><input type="text" :name="'params['+index+'][key]'" x-model="param.key" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500"></div>
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Value</label><input type="text" :name="'params['+index+'][value]'" x-model="param.value" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500"></div>
                                        </div>
                                        <button type="button" @click="removeParam(index, 'params')" class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center hover:bg-rose-600 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Alpha SMS Config --}}
                    <div x-show="activeTab === 'alpha'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="space-y-5">
                            <div class="bg-indigo-600 dark:bg-indigo-900/40 rounded-2xl p-5 text-white">
                                <h3 class="text-xs font-black mb-3 uppercase tracking-widest">Alpha SMS Reference</h3>
                                <code class="block bg-black/20 p-3 rounded-lg text-[9px] break-all border border-white/10 font-mono mb-3">https://api.sms.net.bd/sendsms?api_key={KEY}&amp;msg={MSG}&amp;to=8801800000000&amp;sender_id={SID}</code>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[9px]">
                                    <div><p class="font-black uppercase mb-1 text-indigo-200">Parameters</p><p>• <span class="text-indigo-200">api_key:</span> Required</p><p>• <span class="text-indigo-200">sender_id:</span> Optional — approved sender ID</p></div>
                                    <div><p class="font-black uppercase mb-1 text-indigo-200">Endpoints</p><p>• Balance: <code class="font-mono text-[8px]">api.sms.net.bd/user/balance</code></p></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-1">
                                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Alpha API Parameters</h4>
                                <button type="button" @click="addParam('alphaParams')" class="w-8 h-8 bg-indigo-500 text-white rounded-lg flex items-center justify-center hover:bg-indigo-600 transition-all shadow-md shadow-indigo-200 dark:shadow-none"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg></button>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <template x-for="(param, index) in alphaParams" :key="index">
                                    <div class="flex items-center gap-3 group" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2">
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Key</label><input type="text" :name="'alphaParams['+index+'][key]'" x-model="param.key" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-indigo-500"></div>
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Value</label><input type="text" :name="'alphaParams['+index+'][value]'" x-model="param.value" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-indigo-500"></div>
                                        </div>
                                        <button type="button" @click="removeParam(index, 'alphaParams')" class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center hover:bg-rose-600 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- BulkSMS BD Config --}}
                    <div x-show="activeTab === 'bulksms'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="space-y-5">
                            <div class="bg-sky-600 dark:bg-sky-900/40 rounded-2xl p-5 text-white">
                                <h3 class="text-xs font-black mb-3 uppercase tracking-widest">BulkSMS BD Reference</h3>
                                <code class="block bg-black/20 p-3 rounded-lg text-[9px] break-all border border-white/10 font-mono mb-3">http://bulksmsbd.net/api/smsapi?api_key={KEY}&amp;type=text&amp;number={NUM}&amp;senderid={SID}&amp;message={MSG}</code>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[9px]">
                                    <div><p class="font-black uppercase mb-1 text-sky-200">Parameters</p><p>• <span class="text-sky-200">api_key:</span> Required</p><p>• <span class="text-sky-200">type:</span> text / unicode</p><p>• <span class="text-sky-200">senderid:</span> Approved sender ID</p></div>
                                    <div><p class="font-black uppercase mb-1 text-sky-200">Success</p><p>• Response code 202 = SMS Submitted</p></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-1">
                                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">BulkSMS BD Parameters</h4>
                                <button type="button" @click="addParam('bulksmsParams')" class="w-8 h-8 bg-sky-500 text-white rounded-lg flex items-center justify-center hover:bg-sky-600 transition-all shadow-md shadow-sky-200 dark:shadow-none"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg></button>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <template x-for="(param, index) in bulksmsParams" :key="index">
                                    <div class="flex items-center gap-3 group" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2">
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Key</label><input type="text" :name="'bulksmsParams['+index+'][key]'" x-model="param.key" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-sky-500"></div>
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Value</label><input type="text" :name="'bulksmsParams['+index+'][value]'" x-model="param.value" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-sky-500"></div>
                                        </div>
                                        <button type="button" @click="removeParam(index, 'bulksmsParams')" class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center hover:bg-rose-600 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- SSL Wireless Config --}}
                    <div x-show="activeTab === 'ssl'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="space-y-5">
                            <div class="bg-emerald-600 dark:bg-emerald-900/40 rounded-2xl p-5 text-white">
                                <h3 class="text-xs font-black mb-3 uppercase tracking-widest">SSL Wireless Reference</h3>
                                <code class="block bg-black/20 p-3 rounded-lg text-[9px] break-all border border-white/10 font-mono mb-3">POST https://smsplus.sslwireless.com/api/v3/send-sms — { api_token, sid, msisdn, sms, csms_id }</code>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[9px]">
                                    <div><p class="font-black uppercase mb-1 text-emerald-200">Parameters</p><p>• <span class="text-emerald-200">api_token:</span> Required</p><p>• <span class="text-emerald-200">sid:</span> Masking/Brand SID</p></div>
                                    <div><p class="font-black uppercase mb-1 text-emerald-200">Info</p><p>• Success: status = "SUCCESS"</p></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-1">
                                <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">SSL Wireless Parameters</h4>
                                <button type="button" @click="addParam('sslParams')" class="w-8 h-8 bg-emerald-500 text-white rounded-lg flex items-center justify-center hover:bg-emerald-600 transition-all shadow-md shadow-emerald-200 dark:shadow-none"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg></button>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <template x-for="(param, index) in sslParams" :key="index">
                                    <div class="flex items-center gap-3 group" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2">
                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Key</label><input type="text" :name="'sslParams['+index+'][key]'" x-model="param.key" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-emerald-500"></div>
                                            <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Value</label><input type="text" :name="'sslParams['+index+'][value]'" x-model="param.value" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-emerald-500"></div>
                                        </div>
                                        <button type="button" @click="removeParam(index, 'sslParams')" class="w-8 h-8 bg-rose-500 text-white rounded-lg flex items-center justify-center hover:bg-rose-600 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- FiveMojo Config --}}
                    <div x-show="activeTab === 'fivemojo'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="space-y-5">
                            <div class="bg-violet-600 dark:bg-violet-900/40 rounded-2xl p-5 text-white">
                                <h3 class="text-xs font-black mb-3 uppercase tracking-widest">FiveMojo WhatsApp Reference</h3>
                                <code class="block bg-black/20 p-3 rounded-lg text-[9px] break-all border border-white/10 font-mono mb-3">POST {base_url}/api/send — { token, instance_id, number, message }</code>
                                <div class="text-[9px]"><p>• <span class="text-violet-200">instance_id:</span> Your FiveMojo WhatsApp instance</p><p>• <span class="text-violet-200">token:</span> API authentication token</p></div>
                            </div>
                            <div class="max-w-2xl space-y-4">
                                <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Instance ID</label><input type="text" name="fivemojo_instance_id" value="{{ $fivemojo->instance_id ?? '' }}" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-violet-500"></div>
                                <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Token</label><input type="text" name="fivemojo_token" value="{{ $fivemojo->token ?? '' }}" class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-violet-500"></div>
                            </div>
                        </div>
                    </div>

                </div>{{-- end config panels --}}
            </div>

            {{-- ══════════════════════════════ STEP 3: TEST CONNECTION ══════════════════════════════ --}}
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-4 border-b border-slate-50 dark:border-dark-border">
                    <h2 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300">Step 3 — Test Connection</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Save settings first, then send a test message to verify the active provider is working correctly.</p>
                </div>
                <div class="p-5 max-w-2xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Test Mobile Number</label><input type="text" id="test_mobile" placeholder="e.g. 8801800000000" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500"></div>
                        <div class="relative"><label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Test Message</label><input type="text" id="test_message" value="Hello! This is a test message from CorevisysPOS." class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500"></div>
                    </div>
                    <button type="button" onclick="sendTestSms()" id="btn-send-test"
                        class="px-6 py-2.5 bg-slate-800 dark:bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 dark:hover:bg-primary-700 transition-all flex items-center gap-2 shadow-lg shadow-slate-200/50 dark:shadow-none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Send Test Message
                    </button>
                </div>
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="flex flex-col md:flex-row justify-end items-center gap-3">
                <a href="{{ route('dashboard') }}" class="w-full md:w-32 py-2.5 bg-amber-500 text-white rounded-xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-amber-200 dark:shadow-none hover:bg-amber-600 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                    Close
                </a>
                <button type="submit" id="btn-save-settings" class="w-full md:w-52 py-2.5 bg-emerald-500 text-white rounded-xl text-[11px] font-black uppercase tracking-[0.2em] shadow-lg shadow-emerald-200 dark:shadow-none hover:bg-emerald-600 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    Save Settings
                </button>
            </div>

        </form>

        <script>
            function sendTestSms() {
                const mobile = document.getElementById('test_mobile').value;
                const message = document.getElementById('test_message').value;
                if (!mobile || !message) { showError('Please enter both mobile number and test message.'); return; }
                const btn = document.getElementById('btn-send-test');
                btn.disabled = true;
                btn.innerHTML = 'Sending…';
                fetch("{{ route('sms.settings.test') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ mobile, message })
                })
                .then(r => r.json())
                .then(data => { data.status === 'success' || data.success ? showSuccess(data.message || 'Test SMS sent!') : showError(data.message || 'Failed to send test SMS.'); })
                .catch(() => showError('Something went wrong. Check your network.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Send Test Message';
                });
            }
        </script>

    </div>
</x-app-layout>