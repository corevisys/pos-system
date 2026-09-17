<x-app-layout title="SMS History">
    <div class="space-y-3" x-data="{
        isDetailModalOpen: false,
        selectedLog: {
            phone: '',
            customer_name: '',
            message: '',
            status: '',
            error_code: '',
            api_response: {},
            created_at: '',
            type: ''
        },
        openDetailModal(log) {
            this.selectedLog = {
                phone: log.phone,
                customer_name: log.customer ? log.customer.customer_name : 'Walk-in',
                message: log.message,
                status: log.status,
                error_code: log.error_code || 'None',
                api_response: log.api_response || {},
                created_at: new Date(log.created_at).toLocaleString(),
                type: log.rule_id ? 'Auto-Rule' : 'Direct Message'
            };
            this.isDetailModalOpen = true;
        }
    }"
        x-effect="document.body.classList.toggle('overflow-y-hidden', isDetailModalOpen)"
        @keydown.escape.window="isDetailModalOpen = false">
        <!-- HEADER -->
        <div class="bg-white dark:bg-dark-card p-3 md:p-4 rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-white uppercase"><span class="w-1.5 h-1.5 inline-block rounded-full bg-primary-500 mr-1 align-middle mb-1"></span>Messaging <span class="text-primary-600">Intelligence</span></h1>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Real-time SMS analytics & communication oversight.</p>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-100 transition-all flex items-center gap-1 shadow-sm">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Refresh
                </button>
                <a href="{{ route('sms.send') }}" class="px-3 py-1.5 bg-primary-600 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all flex items-center gap-1 shadow-md shadow-primary-200/50 dark:shadow-none">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Compose SMS
                </a>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Balance -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-3 md:p-4 border border-slate-100 dark:border-dark-border shadow-sm group hover:border-primary-500 transition-all flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Total Balance</p>
                    <div class="w-7 h-7 bg-slate-50 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 group-hover:bg-primary-50 group-hover:text-primary-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white">{{ format_currency($balance) }}</h3>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-[8px] text-slate-400 font-bold uppercase tracking-widest italic">Live from Provider</span>
                </div>
            </div>

            <!-- Sent Today -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-3 md:p-4 border border-slate-100 dark:border-dark-border shadow-sm group hover:border-blue-500 transition-all flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Sent Today</p>
                    <div class="w-7 h-7 bg-slate-50 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </div>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white">{{ number_format($stats['sent_today']) }}</h3>
                <div class="mt-1 flex items-center gap-2 text-[8px] text-slate-400 font-bold uppercase tracking-widest italic">Total Success: {{ number_format($stats['total_sent']) }}</div>
            </div>

            <!-- Delivery Rate -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-3 md:p-4 border border-slate-100 dark:border-dark-border shadow-sm group hover:border-violet-500 transition-all flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Gateway Health</p>
                    <div class="w-7 h-7 bg-slate-50 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 group-hover:bg-violet-50 group-hover:text-violet-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="flex items-center gap-3 mb-1">
                    <h3 class="text-xl font-black {{ str_contains(strtolower($healthStatus), 'critical') ? 'text-rose-500' : (str_contains(strtolower($healthStatus), 'warning') ? 'text-amber-500' : 'text-slate-800 dark:text-white') }}">{{ $healthStatus }}</h3>
                    @php
                        $rate = ($stats['total_sent'] + $stats['total_failed']) > 0 
                                ? ($stats['total_sent'] / ($stats['total_sent'] + $stats['total_failed'])) * 100 
                                : 100;
                    @endphp
                    <div class="w-16 h-1 flex-1 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mt-1">
                        <div class="h-full bg-violet-500" style="width: {{ $rate }}%"></div>
                    </div>
                </div>
                <div class="mt-1 flex items-center gap-2 text-[8px] text-slate-400 font-bold uppercase tracking-widest italic">Last monitored: {{ now()->format('h:i A') }}</div>
            </div>

            <!-- Failed/Scheduled -->
            <div class="bg-white dark:bg-dark-card rounded-2xl p-3 md:p-4 border border-slate-100 dark:border-dark-border shadow-sm group hover:border-rose-500 transition-all flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Scheduled / Failed</p>
                    <div class="w-7 h-7 bg-slate-50 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 group-hover:bg-rose-50 group-hover:text-rose-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <h3 class="text-xl font-black text-slate-800 dark:text-white">{{ $stats['scheduled'] }} <span class="text-slate-300">/</span> <span class="text-rose-500">{{ $stats['total_failed'] }}</span></h3>
                <div class="mt-1 flex items-center gap-2 text-[8px] text-slate-400 font-bold uppercase tracking-widest italic">Critical Alerts Active</div>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden flex-1">
            <div class="p-3 border-b border-slate-50 dark:border-dark-border flex items-center justify-between">
                <h2 class="text-[11px] font-black uppercase text-slate-800 dark:text-white tracking-widest flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                    Recent Global Activity
                </h2>
                <a href="{{ route('sms.logs') }}" class="px-3 py-1.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-primary-100 dark:hover:bg-primary-500/20 transition-colors flex items-center gap-1 group">
                    Full Logs
                    <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Timestamp</th>
                            <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Recipient</th>
                            <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Status</th>
                            <th class="px-4 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        @foreach($recentActivity as $log)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-4 py-2">
                                <p class="text-[11px] font-black text-slate-700 dark:text-slate-300">{{ $log->created_at->format('d M, h:i A') }}</p>
                                <p class="text-[8px] font-bold text-slate-400 uppercase mt-0.5">{{ $log->rule_id ? 'Auto-Rule' : 'Direct Message' }}</p>
                            </td>
                            <td class="px-4 py-2">
                                <p class="text-[11px] font-black text-primary-600 dark:text-primary-400">{{ $log->customer->customer_name ?? 'Walk-in' }}</p>
                                <div class="flex gap-2 mt-0.5">
                                    <span class="text-[8px] font-bold text-slate-400 uppercase">Ph: <span class="text-slate-500 font-black italic">{{ substr($log->phone, 0, 5) }}****{{ substr($log->phone, -4) }}</span></span>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1.5 {{ $log->status == 'Sent' ? 'text-emerald-500' : 'text-rose-500' }}">
                                    <div class="w-1.5 h-1.5 {{ $log->status == 'Sent' ? 'bg-emerald-500' : 'bg-rose-500' }} rounded-full {{ $log->status == 'Sent' ? 'animate-pulse' : '' }}"></div>
                                    <span class="text-[9px] font-black uppercase tracking-widest">{{ $log->status }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <button @click="openDetailModal({{ $log }})" class="p-1.5 text-slate-300 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition-all opacity-0 group-hover:opacity-100 float-right">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                        
                        @if($recentActivity->isEmpty())
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3 text-slate-200 dark:text-slate-700">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                No Activity Found
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        <!-- SMS DETAIL MODAL -->
        <div x-show="isDetailModalOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop must sit at z-0 (explicit). Left at `z-index: auto` this
                     positioned blurred layer is painted in CSS 2.1 Appendix E step 6,
                     above the static `inline-block` panel in step 5 — blurring the
                     dialog itself and swallowing every click inside it. --}}
                <div @click="isDetailModalOpen = false" class="fixed inset-0 z-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="relative z-10 inline-block align-bottom bg-white dark:bg-dark-card rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100 dark:border-dark-border">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-slate-50 dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                        <div>
                            <h3 class="text-sm font-black uppercase text-slate-800 dark:text-white tracking-widest flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                                SMS Transmission Detail
                            </h3>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1" x-text="'Trace ID: ' + selectedLog.type"></p>
                        </div>
                        <button @click="isDetailModalOpen = false" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6">
                        <!-- Recipient Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Recipient</p>
                                <p class="text-xs font-black text-slate-800 dark:text-white uppercase" x-text="selectedLog.customer_name"></p>
                                <p class="text-[10px] font-mono text-primary-600 dark:text-primary-400" x-text="selectedLog.phone"></p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Timestamp</p>
                                <p class="text-xs font-black text-slate-800 dark:text-white uppercase" x-text="selectedLog.created_at"></p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="selectedLog.status === 'Sent' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                    <span class="text-[9px] font-black uppercase tracking-widest" :class="selectedLog.status === 'Sent' ? 'text-emerald-500' : 'text-rose-500'" x-text="selectedLog.status"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Message Content -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border border-slate-100 dark:border-dark-border">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Message Content</p>
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 leading-relaxed" x-text="selectedLog.message"></p>
                        </div>

                        <!-- Technical Details / Failure Reason -->
                        <div class="space-y-4">
                            <div x-show="selectedLog.status === 'Failed'" class="space-y-2">
                                <p class="text-[9px] font-black text-rose-500 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Failure Diagnostic
                                </p>
                                <div class="bg-rose-50 dark:bg-rose-500/10 rounded-xl p-3 border border-rose-100 dark:border-rose-500/20">
                                    <p class="text-[10px] font-black text-rose-700 dark:text-rose-400 uppercase mb-1" x-text="'Error Code: ' + selectedLog.error_code"></p>
                                    <p class="text-[11px] font-bold text-rose-600 dark:text-rose-300" x-text="selectedLog.api_response.error_message || (typeof selectedLog.api_response === 'string' ? selectedLog.api_response : 'Unknown provider error')"></p>
                                </div>
                            </div>

                            <div x-show="selectedLog.status === 'Sent'" class="space-y-2">
                                <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Provider Handshake
                                </p>
                                <div class="bg-emerald-50 dark:bg-emerald-500/10 rounded-xl p-3 border border-emerald-100 dark:border-emerald-500/20">
                                    <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-300 capitalize" x-text="selectedLog.api_response.success_message || 'Message accepted by gateway successfully.'"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-dark-border flex items-center justify-end">
                        <button @click="isDetailModalOpen = false" class="px-5 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm">
                            Close Intelligence View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
