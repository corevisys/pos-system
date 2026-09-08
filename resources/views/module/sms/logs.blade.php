<x-app-layout title="SMS Delivery Logs">
    <div class="space-y-10" x-data="{
        showFilters: {{ request()->anyFilled(['phone', 'status', 'date_from', 'date_to']) ? 'true' : 'false' }},
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
                customer_name: log.customer ? log.customer.customer_name : 'Unknown',
                message: log.message,
                status: log.status,
                error_code: log.error_code || 'None',
                api_response: log.api_response || {},
                created_at: new Date(log.created_at).toLocaleString(),
                type: log.rule_id ? 'Auto-Rule' : 'Direct Message'
            };
            this.isDetailModalOpen = true;
        }
    }">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white uppercase"><span class="text-primary-600">Audit</span> Logs</h1>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold mt-0.5">Full transparency of communication flow with enterprise-grade tracing.</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showFilters = !showFilters" class="px-3 py-1.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all flex items-center gap-1.5 shadow-sm" :class="{ 'ring-2 ring-primary-500': showFilters }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Apply Filters
                </button>
                <form action="{{ route('sms.logs') }}" method="GET" class="m-0 p-0">
                    <input type="hidden" name="export" value="csv">
                    @if(request('phone')) <input type="hidden" name="phone" value="{{ request('phone') }}"> @endif
                    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                    @if(request('date_from')) <input type="hidden" name="date_from" value="{{ request('date_from') }}"> @endif
                    @if(request('date_to')) <input type="hidden" name="date_to" value="{{ request('date_to') }}"> @endif
                    <button type="submit" class="px-3 py-1.5 bg-slate-800 dark:bg-slate-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 dark:hover:bg-slate-600 transition-all flex items-center gap-1.5 shadow-md shadow-slate-200 dark:shadow-none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Export CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div x-show="showFilters" x-collapse x-cloak>
            <form action="{{ route('sms.logs') }}" method="GET" class="bg-white dark:bg-dark-card rounded-2xl p-4 border border-slate-200 dark:border-dark-border shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1 block">Search Recipient</label>
                        <input type="text" name="phone" value="{{ request('phone') }}" placeholder="Phone or Name..." class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1 block">Delivery Status</label>
                        <select name="status" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all appearance-none cursor-pointer">
                            <option value="all">All Statuses</option>
                            <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Sent" {{ request('status') == 'Sent' ? 'selected' : '' }}>Sent</option>
                            <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="Failed" {{ request('status') == 'Failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1 block">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1 block">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" max="{{ now()->format('Y-m-d') }}" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-dark-border flex items-center justify-end gap-2">
                    <a href="{{ route('sms.logs') }}" class="px-4 py-1.5 text-[10px] font-black uppercase text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">Clear Filters</a>
                    <button type="submit" class="px-4 py-1.5 bg-primary-600 text-white rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-sm shadow-primary-200 dark:shadow-none">Search Logs</button>
                </div>
            </form>
        </div>

        <!-- LOG TABLE -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-200 dark:border-dark-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Trace ID</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Recipient</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Message Segment</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Provider Stat</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Cost/Parts</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest text-right">Status</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border text-[10px] font-bold">
                        @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors group">
                            <td class="px-3 py-2">
                                <p class="text-slate-400 font-mono tracking-tighter">#{{ $log->id }}</p>
                                <p class="text-[8px] text-slate-500 font-medium uppercase">{{ $log->created_at->format('H:i:s') }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="text-slate-800 dark:text-white uppercase">{{ $log->customer->customer_name ?? 'Unknown' }}</p>
                                <p class="text-[8px] text-slate-400 font-mono italic tracking-widest">{{ substr($log->phone, 0, 7) }}****{{ substr($log->phone, -4) }}</p>
                            </td>
                            <td class="px-3 py-2 max-w-xs">
                                <p class="text-slate-600 dark:text-slate-400 font-medium line-clamp-1 group-hover:line-clamp-none transition-all cursor-help">{{ $log->message }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span class="text-[8px] bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded text-slate-500 uppercase">{{ $log->provider }}</span>
                                <p class="text-[8px] text-slate-400 mt-0.5 font-mono tracking-tighter">{{ $log->provider_message_id }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="text-slate-700 dark:text-slate-300">{{ $currencySymbol }}{{ number_format($log->cost, 3) }}</p>
                                <p class="text-[8px] text-slate-400 font-black uppercase">{{ $log->sms_parts }} Part(s)</p>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @php
                                    $statusColors = [
                                        'Pending' => 'text-slate-400',
                                        'Sent' => 'text-blue-500',
                                        'Delivered' => 'text-emerald-500',
                                        'Failed' => 'text-rose-500',
                                    ];
                                    $textColor = $statusColors[$log->status] ?? 'text-slate-400';
                                @endphp
                                <div class="flex items-center justify-end gap-1 {{ $textColor }}">
                                    <span class="text-[9px] font-black uppercase tracking-widest">{{ $log->status }}</span>
                                    <div class="w-1.5 h-1.5 bg-current rounded-full"></div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center text-slate-300">
                                <button @click="openDetailModal({{ $log }})" class="p-1.5 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-400 font-medium italic uppercase tracking-widest">No communication traces located.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-dark-border">
                    {{ $logs->links() }}
                </div>
            @endif
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
                <div @click="isDetailModalOpen = false" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-dark-card rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-100 dark:border-dark-border">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-slate-50 dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                        <div>
                            <h3 class="text-sm font-black uppercase text-slate-800 dark:text-white tracking-widest flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                                Trace Diagnostic Report
                            </h3>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1" x-text="'Origin: ' + selectedLog.type"></p>
                        </div>
                        <button @click="isDetailModalOpen = false" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6 text-xs">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Target Node</p>
                                <p class="font-black text-slate-800 dark:text-white uppercase" x-text="selectedLog.customer_name"></p>
                                <p class="font-mono text-primary-600 dark:text-primary-400" x-text="selectedLog.phone"></p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Audit Timestamp</p>
                                <p class="font-black text-slate-800 dark:text-white uppercase" x-text="selectedLog.created_at"></p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <div class="w-1.5 h-1.5 rounded-full" :class="selectedLog.status === 'Sent' ? 'bg-emerald-500' : 'bg-rose-500'"></div>
                                    <span class="font-black uppercase tracking-widest" :class="selectedLog.status === 'Sent' ? 'text-emerald-500' : 'text-rose-500'" x-text="selectedLog.status"></span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border border-slate-100 dark:border-dark-border">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Communication Payload</p>
                            <p class="font-semibold text-slate-600 dark:text-slate-300 leading-relaxed" x-text="selectedLog.message"></p>
                        </div>

                        <div class="space-y-4">
                            <div x-show="selectedLog.status === 'Failed'" class="space-y-2">
                                <p class="text-[9px] font-black text-rose-500 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Failure Diagnostic
                                </p>
                                <div class="bg-rose-50 dark:bg-rose-500/10 rounded-xl p-3 border border-rose-100 dark:border-rose-500/20">
                                    <p class="text-[10px] font-black text-rose-700 dark:text-rose-400 uppercase mb-1" x-text="'Internal Error Code: ' + selectedLog.error_code"></p>
                                    <p class="font-bold text-rose-600 dark:text-rose-300" x-text="selectedLog.api_response.error_message || (typeof selectedLog.api_response === 'string' ? selectedLog.api_response : 'Unknown provider error')"></p>
                                </div>
                            </div>

                            <div x-show="selectedLog.status === 'Sent'" class="space-y-2">
                                <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Gateway Verification
                                </p>
                                <div class="bg-emerald-50 dark:bg-emerald-500/10 rounded-xl p-3 border border-emerald-100 dark:border-emerald-500/20">
                                    <p class="font-bold text-emerald-600 dark:text-emerald-300 capitalize" x-text="selectedLog.api_response.success_message || 'Handshake complete. Payload accepted by provider.'"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-dark-border flex items-center justify-end">
                        <button @click="isDetailModalOpen = false" class="px-5 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm">
                            Exit Diagnostic View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
