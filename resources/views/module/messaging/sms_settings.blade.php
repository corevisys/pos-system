<x-app-layout title="SMS Settings">
    <div class="space-y-3" x-data="{
        updating: null,
        statuses: {{ json_encode($eventStatuses) }},
        async toggleStatus(eventType) {
            const currentStatus = this.statuses[eventType];
            this.updating = eventType;
            try {
                const response = await fetch('{{ route('messaging.settings.status') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        event_type: eventType,
                        is_active: !currentStatus
                    })
                });
                const data = await response.json();
                if (data.status === 'success' || data.success) {
                    this.statuses[eventType] = !currentStatus;
                    showSuccess(data.message || 'Status updated successfully.');
                } else {
                    showError(data.message || 'Failed to update status.');
                }
            } catch (error) {
                console.error('Error toggling status:', error);
                showError('An error occurred. Please try again.');
            } finally {
                this.updating = null;
            }
        }
    }">
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-white uppercase flex items-center gap-2">
                    <span class="p-1.5 bg-primary-600 rounded-lg text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    </span>
                    SMS Settings
                    <span class="text-[8px] font-bold text-slate-400 ml-1 italic uppercase tracking-widest bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">Automation Hub</span>
                </h1>
                <div class="flex items-center gap-1.5 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[9px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-400 text-[9px] font-black uppercase tracking-wider">Messaging</span>
                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-primary-600 text-[9px] font-black uppercase tracking-wider">SMS Settings</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('sms.auto-rules') }}" class="px-3 py-1.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-all flex items-center gap-1 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Manage Detailed Rules
                </a>
            </div>
        </div>

        <!-- GLOBAL STATUS -->
        <div class="bg-gradient-to-r from-primary-600 to-indigo-600 rounded-2xl p-4 mb-4 text-white shadow-lg shadow-primary-200/50 dark:shadow-none relative overflow-hidden">
            <div class="absolute top-0 right-0 w-48 h-48 bg-white/10 rounded-full translate-x-15 -translate-y-15 blur-2xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black uppercase tracking-tight">System Global Gateway</h2>
                        <p class="text-white/80 text-[9px] font-bold uppercase tracking-widest mt-0.5">Current Active Service: 
                            <span class="text-amber-300">
                                @if($store->sms_status == 1) HTTP/URL API
                                @elseif($store->sms_status == 2) Alpha SMS API
                                @elseif($store->sms_status == 3) Bulksmsbd API
                                @elseif($store->sms_status == 5) SSL Wireless API
                                @elseif($store->sms_status == 4) FiveMojo WhatsApp
                                @else Disabled
                                @endif
                            </span>
                        </p>
                    </div>
                </div>
                <a href="{{ route('sms.settings') }}" class="px-5 py-2 bg-white text-primary-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all shadow-md">
                    Configure API
                </a>
            </div>
        </div>

        <!-- EVENT CATEGORIES -->
        @php
            $categories = [
                '🔹 Core Transaction' => ['InvoiceCreated', 'PaymentReceived', 'SalesReturnConfirmation', 'PurchaseCreated'],
                '🔹 EMI Control' => ['EmiDue', 'EmiOverdue', 'EmiPaymentConfirmation', 'EmiCompletion', 'ServiceDueReminder'],
                '🔹 Inventory' => ['LowStock', 'WarehouseLowStock', 'StockAdjustmentAlert'],
                '🔹 CRM' => ['CustomerBirthday', 'FestivalCampaign', 'CouponExpiry', 'WinbackMessage', 'CustomerAdded'],
                '🔹 Admin Intelligence' => ['EodSummary', 'LargeTransactionAlert', 'BackupCompletedAlert']
            ];
        @endphp

        <div class="space-y-8">
            @foreach($categories as $categoryName => $types)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-[10px] font-black text-slate-800 dark:text-white uppercase tracking-[0.2em] flex-shrink-0">{{ $categoryName }}</h2>
                    <div class="h-px bg-slate-100 dark:bg-dark-border flex-1"></div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach($types as $type)
                    @if(isset($eventTypes[$type]))
                    @php
                        $label = $eventTypes[$type];
                        $eventRules = $rules->get($type, collect());
                        $templateName = $eventRules->first()?->template?->template_name ?? 'Default Protocol';
                    @endphp
                    <div class="group bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-4 hover:shadow-xl hover:border-primary-100 dark:hover:border-primary-900 transition-all flex flex-col h-full overflow-hidden relative"
                         x-data="{ active: statuses['{{ $type }}'] }"
                         x-init="$watch('statuses[\'{{ $type }}\']', value => active = value)">
                        <!-- Hover Glow -->
                        <div class="absolute -top-6 -right-6 w-16 h-16 bg-primary-600/5 dark:bg-primary-600/10 rounded-full group-hover:scale-150 transition-transform duration-700"></div>
                        
                        <div class="flex items-start justify-between mb-4 relative z-10">
                            <div class="p-2 rounded-xl transition-colors"
                                 :class="active ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/20' : 'bg-slate-50 text-slate-400 dark:bg-slate-800'">
                                @if(Str::contains($type, 'Invoice') || Str::contains($type, 'Sale') || Str::contains($type, 'Purchase') || Str::contains($type, 'Payment'))
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                @elseif(Str::contains($type, 'Birthday') || Str::contains($type, 'Campaign') || Str::contains($type, 'Coupon') || Str::contains($type, 'Message') || Str::contains($type, 'Customer'))
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z"></path></svg>
                                @elseif(Str::contains($type, 'Emi') || Str::contains($type, 'Service'))
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @elseif(Str::contains($type, 'Stock') || Str::contains($type, 'Adjustment'))
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                @else
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                @endif
                            </div>
                            
                            <button @click="toggleStatus('{{ $type }}')" 
                                    :disabled="updating === '{{ $type }}'"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-50"
                                    :class="active ? 'bg-primary-600' : 'bg-slate-200 dark:bg-slate-700'">
                                <span class="sr-only">Toggle Status</span>
                                <span aria-hidden="true" 
                                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                      :class="active ? 'translate-x-5' : 'translate-x-0'">
                                    <template x-if="updating === '{{ $type }}'">
                                        <svg class="animate-spin h-3 w-3 text-primary-600 m-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </template>
                                </span>
                            </button>
                        </div>

                        <div class="flex-1 relative z-10">
                            <div class="flex items-center justify-between mb-0.5">
                                <h3 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-tight">{{ $label }}</h3>
                                @if($eventRules->count() > 1)
                                    <span class="flex h-2 w-2 relative" title="Duplicate Rules Detected">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 text-[8px] font-bold uppercase tracking-widest mb-3 transition-colors"
                                 :class="active ? 'text-primary-600' : 'text-slate-400'">
                                <span class="w-1 h-1 rounded-full" :class="active ? 'bg-primary-600 animate-pulse' : 'bg-slate-300'"></span>
                                <span x-text="active ? 'Live' : 'Paused'"></span>
                                @if($eventRules->count() > 1)
                                    <span class="text-rose-500 bg-rose-50 dark:bg-rose-900/20 px-1 rounded-[4px] ml-auto">Conflict: {{ $eventRules->count() }} rules</span>
                                @endif
                            </div>

                            @if($eventRules->count() > 0)
                            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-2.5 border border-slate-50 dark:border-dark-border mb-3">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Protocol</span>
                                    <span class="text-[9px] font-black text-slate-800 dark:text-white truncate max-w-[80px]">{{ $templateName }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Timing</span>
                                    <span class="text-[9px] font-black text-slate-800 dark:text-white">{{ $eventRules->first()->trigger_time == 'immediate' ? 'Real-time' : $eventRules->first()->days_offset . 'd' }}</span>
                                </div>
                            </div>
                            @else
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-4 border border-amber-100 dark:border-amber-900/30 mb-4 flex-1">
                                <p class="text-[10px] font-bold text-amber-600 dark:text-amber-400 leading-relaxed italic">
                                    No automation rule found for this event. 
                                    <a href="{{ route('sms.auto-rules') }}" class="underline font-black hover:text-amber-700 transition-colors">Setup now</a>
                                </p>
                            </div>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-slate-50 dark:border-dark-border mt-auto relative z-10">
                            <a href="{{ route('sms.auto-rules') }}" class="text-[10px] font-black text-primary-600 uppercase tracking-widest hover:translate-x-1 transition-transform inline-flex items-center gap-1.5">
                                Configure Event
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                            </a>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <!-- HELP / INFO FOOTER -->
        <div class="mt-8 p-4 bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border flex flex-col md:flex-row items-center gap-4 shadow-sm">
            <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-xl shadow-sm flex items-center justify-center text-primary-600 flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex-1 text-center md:text-left">
                <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight mb-1">Global Controls</h3>
                <p class="text-slate-500 dark:text-slate-400 text-[10px] font-medium leading-relaxed max-w-2xl">
                    Use these toggles for high-level control. For fine-grained rules, visit the <a href="{{ route('sms.auto-rules') }}" class="text-primary-600 font-black hover:underline underline-offset-4">Auto Triggers manager</a>.
                </p>
            </div>
            <div class="flex-shrink-0">
                <a href="{{ route('sms.templates') }}" class="px-5 py-2 bg-slate-800 dark:bg-primary-600 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-md">
                    Templates
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
