<x-app-layout title="SMS Automation Rules">
    <div class="space-y-10" x-data="{
        isModalOpen: false,
        isEditing: false,
        formAction: '{{ route('sms.auto-rules.store') }}',
        formData: {
            id: null,
            rule_name: '',
            event_type: '',
            event_source: '',
            template_id: '',
            trigger_time: 'immediate',
            days_offset: 0,
            cooldown_days: 0
        },
        openCreateModal() {
            this.isEditing = false;
            this.formAction = '{{ route('sms.auto-rules.store') }}';
            this.formData = {
                id: null,
                rule_name: '',
                event_type: '',
                event_source: '',
                template_id: '',
                trigger_time: 'immediate',
                days_offset: 0,
                cooldown_days: 0
            };
            this.isModalOpen = true;
        },
        openEditModal(rule) {
            this.isEditing = true;
            this.formAction = '{{ route('sms.auto-rules.update', [':id']) }}'.replace(':id', rule.id);
            this.formData = {
                id: rule.id,
                rule_name: rule.rule_name,
                event_type: rule.event_type,
                event_source: rule.event_source,
                template_id: rule.template_id,
                trigger_time: rule.trigger_time,
                days_offset: rule.days_offset,
                cooldown_days: rule.cooldown_days
            };
            this.isModalOpen = true;
        },
        closeModal() {
            this.isModalOpen = false;
        },
        updateEventSource() {
            const sourceMap = {
                // Core Transaction
                'InvoiceCreated': 'invoice',
                'PaymentReceived': 'payment',
                'SalesReturnConfirmation': 'sale_return',
                'PurchaseCreated': 'purchase',
                // EMI Control
                'EmiDue': 'emi',
                'EmiOverdue': 'emi',
                'EmiPaymentConfirmation': 'emi',
                'EmiCompletion': 'emi',
                'ServiceDueReminder': 'service',
                // Inventory
                'LowStock': 'inventory',
                'WarehouseLowStock': 'inventory',
                'StockAdjustmentAlert': 'inventory',
                // CRM
                'CustomerBirthday': 'birthday',
                'FestivalCampaign': 'marketing',
                'CouponExpiry': 'marketing',
                'WinbackMessage': 'marketing',
                'CustomerAdded': 'marketing',
                // Admin Intelligence
                'EodSummary': 'admin',
                'LargeTransactionAlert': 'admin',
                'BackupCompletedAlert': 'admin'
            };
            this.formData.event_source = sourceMap[this.formData.event_type] || 'manual';
        },
    }">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-800 dark:text-white uppercase">Auto <span class="text-primary-600">Triggers</span></h1>
                <p class="text-slate-500 dark:text-slate-400 font-medium mt-1">Autonomous messaging based on lifecycle events and intelligent offsets.</p>
            </div>
            <button @click="openCreateModal()" class="px-5 py-2.5 bg-primary-600 text-white rounded-2xl text-[11px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all flex items-center gap-2 shadow-lg shadow-primary-200 dark:shadow-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Deploy Rule
            </button>
        </div>



        <!-- AUTO RULES LIST -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @forelse($rules as $rule)
            <div class="bg-white dark:bg-dark-card rounded-3xl p-6 border border-slate-200 dark:border-dark-border shadow-sm hover:shadow-xl hover:scale-[1.02] transition-all group relative overflow-hidden flex flex-col h-full">
                <div class="absolute -top-10 -right-10 w-24 h-24 bg-primary-600/5 dark:bg-primary-600/10 rounded-full group-hover:scale-150 transition-transform duration-700"></div>
                
                <div class="flex items-center justify-between mb-6 relative z-10">
                    <div class="w-10 h-10 bg-slate-50 dark:bg-slate-800 rounded-2xl flex items-center justify-center text-primary-600 border border-slate-100 dark:border-dark-border">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black uppercase tracking-widest {{ $rule->is_active ? 'text-emerald-500' : 'text-slate-400' }}">
                            {{ $rule->is_active ? 'Active' : 'Paused' }}
                        </span>
                    </div>
                </div>

                <div class="flex-1 relative z-10">
                    <h3 class="text-base font-black text-slate-800 dark:text-white uppercase tracking-tight mb-1">{{ $rule->rule_name }}</h3>
                    <p class="text-[10px] font-bold text-primary-600 mb-4 line-clamp-1 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        {{ $rule->template ? $rule->template->template_name : 'No Template Attached' }}
                    </p>

                    <div class="space-y-2 mb-6 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-dark-border">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Event</p>
                            </div>
                            <span class="text-[10px] font-black text-slate-800 dark:text-white uppercase">{{ $rule->event_type }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>
                                <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Timing</p>
                            </div>
                            <span class="text-[10px] font-black text-slate-800 dark:text-white uppercase">
                                @if($rule->trigger_time == 'immediate')
                                    Immediate
                                @elseif($rule->trigger_time == 'before_due')
                                    {{ $rule->days_offset }} Days Before
                                @else
                                    {{ $rule->days_offset }} Days After
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Cooldown</p>
                            </div>
                            <span class="text-[10px] font-black text-slate-800 dark:text-white uppercase">{{ $rule->cooldown_days }} Days</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-dark-border flex items-center justify-between relative z-10 w-full">
                    <div>
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Last Executed</p>
                        <p class="text-[10px] font-bold text-slate-700 dark:text-slate-300 italic">{{ $rule->last_executed_at?->diffForHumans() ?? 'Never' }}</p>
                    </div>
                    
                    <div class="relative" x-data="{ menuOpen: false }">
                        <button @click="menuOpen = !menuOpen" @click.away="menuOpen = false" class="p-2 text-slate-400 hover:text-primary-600 transition-colors rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                        
                        <div x-show="menuOpen" x-transition x-cloak class="absolute right-0 bottom-full mb-2 w-32 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl shadow-xl overflow-hidden z-20">
                            <button @click="openEditModal({{ json_encode($rule) }}); menuOpen = false" class="w-full text-left px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Edit
                            </button>
                            <form action="{{ route('sms.auto-rules.delete', $rule->id) }}" method="POST" onsubmit="return confirm('Delete this automation rule?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="lg:col-span-4 py-12 bg-slate-50 dark:bg-slate-900/50 rounded-3xl border-2 border-dashed border-slate-200 dark:border-dark-border flex flex-col items-center justify-center space-y-4">
                <div class="w-14 h-14 bg-white dark:bg-dark-card rounded-2xl shadow-sm flex items-center justify-center text-slate-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                </div>
                <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Zero automated protocols detected</p>
                <button @click="openCreateModal()" class="px-6 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-bold text-primary-600 uppercase tracking-widest hover:border-primary-600 hover:shadow-lg hover:shadow-primary-100 dark:hover:shadow-none transition-all">Create First Rule</button>
            </div>
            @endforelse
        </div>

        <!-- MODAL -->
        <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 text-left">
            <div x-show="isModalOpen" x-transition.opacity @click="closeModal()" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
            
            <div x-show="isModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                 class="relative w-full max-w-2xl bg-white dark:bg-dark-bg rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <div class="p-6 border-b border-slate-100 dark:border-dark-border flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 flex-shrink-0">
                    <div>
                        <h3 class="text-xl font-black text-slate-800 dark:text-white uppercase tracking-tight flex items-center gap-2" x-text="isEditing ? 'Configure Protocol' : 'Deploy Protocol'"></h3>
                        <p class="text-xs font-bold text-slate-500 mt-1 uppercase tracking-widest" x-text="isEditing ? 'Update existing automation rule' : 'Create new automation rule'"></p>
                    </div>
                    <button @click="closeModal()" class="w-10 h-10 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl flex items-center justify-center text-slate-400 hover:text-rose-500 hover:border-rose-200 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <form :action="formAction" method="POST" id="ruleForm">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Rule Name -->
                            <div class="md:col-span-2 space-y-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Rule Name</label>
                                <input type="text" name="rule_name" x-model="formData.rule_name" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all placeholder:text-slate-400 placeholder:font-normal" placeholder="e.g. Birthday Wish, Payment Reminder">
                            </div>

                            <!-- Event Type -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Core Event</label>
                                @php
                                    $usedTypes = $rules->pluck('event_type')->toArray();
                                @endphp
                                <select name="event_type" 
                                        x-model="formData.event_type" 
                                        @change="updateEventSource()"
                                        required
                                        class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-dark-border rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-primary-500 focus:border-primary-500 transition-all p-3">
                                    <option value="">Select Event...</option>
                                    
                                    <optgroup label="🔹 Core Transaction">
                                        <option value="InvoiceCreated" :disabled="'{{ in_array('InvoiceCreated', $usedTypes) }}' === '1' && formData.event_type !== 'InvoiceCreated'">Invoice Created</option>
                                        <option value="PaymentReceived" :disabled="'{{ in_array('PaymentReceived', $usedTypes) }}' === '1' && formData.event_type !== 'PaymentReceived'">Payment Received</option>
                                        <option value="SalesReturnConfirmation" :disabled="'{{ in_array('SalesReturnConfirmation', $usedTypes) }}' === '1' && formData.event_type !== 'SalesReturnConfirmation'">Sales Return Confirmation</option>
                                        <option value="PurchaseCreated" :disabled="'{{ in_array('PurchaseCreated', $usedTypes) }}' === '1' && formData.event_type !== 'PurchaseCreated'">Purchase Created</option>
                                    </optgroup>

                                    <optgroup label="🔹 EMI Control">
                                        <option value="EmiDue" :disabled="'{{ in_array('EmiDue', $usedTypes) }}' === '1' && formData.event_type !== 'EmiDue'">EMI Due</option>
                                        <option value="EmiOverdue" :disabled="'{{ in_array('EmiOverdue', $usedTypes) }}' === '1' && formData.event_type !== 'EmiOverdue'">EMI Overdue</option>
                                        <option value="EmiPaymentConfirmation" :disabled="'{{ in_array('EmiPaymentConfirmation', $usedTypes) }}' === '1' && formData.event_type !== 'EmiPaymentConfirmation'">EMI Payment Confirmation</option>
                                        <option value="EmiCompletion" :disabled="'{{ in_array('EmiCompletion', $usedTypes) }}' === '1' && formData.event_type !== 'EmiCompletion'">EMI Completion</option>
                                        <option value="ServiceDueReminder" :disabled="'{{ in_array('ServiceDueReminder', $usedTypes) }}' === '1' && formData.event_type !== 'ServiceDueReminder'">Service Due Reminder</option>
                                    </optgroup>

                                    <optgroup label="🔹 Inventory">
                                        <option value="LowStock" :disabled="'{{ in_array('LowStock', $usedTypes) }}' === '1' && formData.event_type !== 'LowStock'">Low Stock</option>
                                        <option value="WarehouseLowStock" :disabled="'{{ in_array('WarehouseLowStock', $usedTypes) }}' === '1' && formData.event_type !== 'WarehouseLowStock'">Warehouse Low Stock</option>
                                        <option value="StockAdjustmentAlert" :disabled="'{{ in_array('StockAdjustmentAlert', $usedTypes) }}' === '1' && formData.event_type !== 'StockAdjustmentAlert'">Stock Adjustment Alert</option>
                                    </optgroup>

                                    <optgroup label="🔹 CRM">
                                        <option value="CustomerBirthday" :disabled="'{{ in_array('CustomerBirthday', $usedTypes) }}' === '1' && formData.event_type !== 'CustomerBirthday'">Birthday</option>
                                        <option value="FestivalCampaign" :disabled="'{{ in_array('FestivalCampaign', $usedTypes) }}' === '1' && formData.event_type !== 'FestivalCampaign'">Festival Campaign</option>
                                        <option value="CouponExpiry" :disabled="'{{ in_array('CouponExpiry', $usedTypes) }}' === '1' && formData.event_type !== 'CouponExpiry'">Coupon Expiry</option>
                                        <option value="WinbackMessage" :disabled="'{{ in_array('WinbackMessage', $usedTypes) }}' === '1' && formData.event_type !== 'WinbackMessage'">Win-back Message</option>
                                        <option value="CustomerAdded" :disabled="'{{ in_array('CustomerAdded', $usedTypes) }}' === '1' && formData.event_type !== 'CustomerAdded'">Customer Added</option>
                                    </optgroup>

                                    <optgroup label="🔹 Admin Intelligence">
                                        <option value="EodSummary" :disabled="'{{ in_array('EodSummary', $usedTypes) }}' === '1' && formData.event_type !== 'EodSummary'">EOD Summary</option>
                                        <option value="LargeTransactionAlert" :disabled="'{{ in_array('LargeTransactionAlert', $usedTypes) }}' === '1' && formData.event_type !== 'LargeTransactionAlert'">Large Transaction Alert</option>
                                        <option value="BackupCompletedAlert" :disabled="'{{ in_array('BackupCompletedAlert', $usedTypes) }}' === '1' && formData.event_type !== 'BackupCompletedAlert'">Backup Completed Alert</option>
                                    </optgroup>
                                </select>
                                <input type="hidden" name="event_source" x-model="formData.event_source">
                            </div>

                            <!-- SMS Template -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Message Template</label>
                                <x-searchable-select name="template_id" :options="$templates" labelKey="template_name" valueKey="id" emptyOption="Select Template..." emptyValue="" placeholder="Select Template..." model="formData.template_id" required />
                            </div>

                            <!-- Trigger Timing Section -->
                            <div class="col-span-1 md:col-span-2 pt-4 pb-2">
                                <h4 class="text-xs font-black text-slate-800 dark:text-slate-300 uppercase tracking-widest border-b border-slate-100 dark:border-dark-border pb-2">Execution Timing</h4>
                            </div>

                            <!-- Trigger Time -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Timing Mode</label>
                                <select name="trigger_time" x-model="formData.trigger_time" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all appearance-none cursor-pointer">
                                    <option value="immediate">Immediate (Real-time)</option>
                                    <option value="before_due">Before Event/Due Date</option>
                                    <option value="after_due">After Event/Due Date</option>
                                </select>
                            </div>

                            <!-- Days Offset -->
                            <div class="space-y-2" x-show="formData.trigger_time !== 'immediate'" x-cloak>
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Days Offset</label>
                                <div class="relative">
                                    <input type="number" name="days_offset" x-model="formData.days_offset" min="0" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                        <span class="text-xs font-bold text-slate-400 uppercase">Days</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cooldown -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Cooldown Period</label>
                                <div class="relative">
                                    <input type="number" name="cooldown_days" x-model="formData.cooldown_days" min="0" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-dark-border rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all" placeholder="0 for no cooldown">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                        <span class="text-xs font-bold text-slate-400 uppercase text-right leading-none">Days<br><span class="text-[8px] opacity-70">(Skip repeat)</span></span>
                                    </div>
                                </div>
                            </div>


                        </div>

                        <div class="mt-8 flex items-center justify-end gap-3 pt-6 border-t border-slate-100 dark:border-dark-border">
                            <button type="button" @click="closeModal()" class="px-5 py-2.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[11px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-all shadow-sm">Cancel</button>
                            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-[11px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200 dark:shadow-none flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span x-text="isEditing ? 'Save Changes' : 'Deploy Protocol'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Scripts removed from push since they are moved to local x-data -->
</x-app-layout>
