<x-app-layout title="Send SMS">
    {{-- TOAST NOTIFICATION --}}
    <div
        x-data="smsComposer()"
        x-init="init()"
        class="space-y-6 relative"
    >
        {{-- TOAST --}}
        <div
            x-show="toast.show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed top-6 right-6 z-50 flex items-start gap-3 px-5 py-4 rounded-2xl shadow-2xl border max-w-sm"
            :class="toast.type === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-700'
                : 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-700'"
        >
            <div class="flex-shrink-0 mt-0.5">
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </template>
            </div>
            <div class="flex-1">
                <p class="text-xs font-bold" :class="toast.type === 'success' ? 'text-emerald-800 dark:text-emerald-200' : 'text-red-800 dark:text-red-200'" x-text="toast.message"></p>
                <template x-if="toast.link">
                    <a :href="toast.link" class="text-[10px] font-black uppercase tracking-widest underline mt-1 inline-block" :class="toast.type === 'success' ? 'text-emerald-600' : 'text-red-600'">View Campaign →</a>
                </template>
            </div>
            <button @click="toast.show = false" class="ml-2 text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white uppercase"><span class="w-1.5 h-1.5 inline-block rounded-full bg-primary-600 mr-1.5 align-middle mb-1"></span>SMS <span class="text-primary-600">Composer</span></h1>
                <p class="text-slate-500 dark:text-slate-400 font-medium mt-0.5 text-[10px]">Multi-channel message orchestration with advanced targeting.</p>
            </div>
            <a href="{{ route('sms.campaigns') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                View Campaigns
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- LEFT: CONFIGURATION (8 columns) --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- SECTION 1: AUDIENCE TARGETING --}}
                <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-200 dark:border-dark-border shadow-sm">
                    <h2 class="text-[10px] font-black uppercase text-slate-800 dark:text-white tracking-widest mb-4 flex items-center gap-2">
                        <div class="w-1.5 h-1.5 bg-primary-600 rounded-full"></div>
                        1. Select Target Audience
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Segment --}}
                        <div class="group relative">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Audience Segment</label>
                            <select
                                x-model="target"
                                @change="fetchTargetCount()"
                                class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none appearance-none transition-all focus:border-primary-500 shadow-inner-sm"
                            >
                                <option value="all">All Customers</option>
                                <option value="single">Single Customer</option>
                                <option value="due">Due Customers</option>
                                <option value="emi">EMI Customers</option>
                                <option value="suppliers">Suppliers</option>
                            </select>
                        </div>

                        {{-- Template --}}
                        <div class="group relative">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Template Selector</label>
                            <select
                                x-model="selectedTemplate"
                                @change="onTemplateChange()"
                                class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none appearance-none transition-all focus:border-primary-500 shadow-inner-sm"
                            >
                                <option value="">Draft (No Template)</option>
                                <template x-for="tpl in templates" :key="tpl.id">
                                    <option :value="tpl.id" x-text="tpl.template_name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    {{-- SINGLE CUSTOMER SELECTOR --}}
                    <div
                        x-show="target === 'single'"
                        x-collapse
                        class="mt-6 pt-6 border-t border-slate-100 dark:border-dark-border"
                    >
                        <div class="group relative max-w-md">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Find Customer (Name/City/Code)</label>
                            <div class="relative" @click.away="showCustomerSearch = false">
                                <input
                                    type="text"
                                    x-model="customerSearchQuery"
                                    @input.debounce.500ms="searchCustomers()"
                                    @focus="showCustomerSearch = true"
                                    placeholder="Start typing to search..."
                                    class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"
                                >
                                {{-- Search Results Dropdown --}}
                                <div
                                    x-show="showCustomerSearch && customerSearchResults.length > 0"
                                    class="absolute left-0 right-0 mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl shadow-xl z-50 max-h-48 overflow-y-auto"
                                >
                                    <template x-for="c in customerSearchResults" :key="c.id">
                                        <button
                                            @click="selectCustomer(c)"
                                            class="w-full text-left px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex flex-col items-start transition-colors border-b border-slate-50 dark:border-slate-700/30 last:border-0"
                                        >
                                            <span class="text-xs font-black text-slate-700 dark:text-slate-200" x-text="c.customer_name"></span>
                                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter" x-text="`${c.customer_code} • ${c.mobile}`"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Selected Customer Badge --}}
                            <template x-if="selectedCustomer">
                                <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-primary-50 dark:bg-primary-500/10 border border-primary-100 dark:border-primary-500/30 rounded-lg">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black text-primary-600 uppercase tracking-tighter" x-text="selectedCustomer.customer_name"></span>
                                        <span class="text-[8px] text-primary-400 font-bold" x-text="selectedCustomer.mobile"></span>
                                    </div>
                                    <button @click="selectedCustomer = null; selectedCustomerIds = []; fetchTargetCount()" class="text-primary-300 hover:text-primary-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ADVANCED FILTERS --}}
                    <div
                        x-show="target === 'due' || target === 'emi' || target === 'suppliers'"
                        x-collapse
                        class="mt-6 pt-6 border-t border-slate-100 dark:border-dark-border grid grid-cols-1 md:grid-cols-3 gap-4"
                    >
                        {{-- Min Due --}}
                        <div class="group relative" x-show="target === 'due'">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Min Due Balance</label>
                            <input
                                type="number"
                                x-model.debounce.500ms="minDue"
                                @input="fetchTargetCount()"
                                min="0"
                                placeholder="0"
                                class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"
                            >
                        </div>

                        {{-- Area / City --}}
                        <div class="group relative">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Area / City Filter</label>
                            <input
                                type="text"
                                x-model.debounce.600ms="area"
                                @input="fetchTargetCount()"
                                placeholder="e.g. Dhaka"
                                class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-2.5 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"
                            >
                        </div>

                        {{-- Target Count Display --}}
                        <div class="flex items-center justify-center bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3 border border-dashed border-slate-200 dark:border-dark-border min-h-[46px]">
                            <template x-if="isCalculating">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3 h-3 text-primary-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Calculating...</span>
                                </div>
                            </template>
                            <template x-if="!isCalculating">
                                <p class="text-[10px] font-bold text-primary-600 uppercase tracking-widest">
                                    Filtered: <span class="text-slate-800 dark:text-white font-black" x-text="targetCount"></span>
                                </p>
                            </template>
                        </div>
                    </div>

                    {{-- All Customers Count Display --}}
                    <div x-show="target === 'all' || target === 'single'" class="mt-6 pt-6 border-t border-slate-100 dark:border-dark-border flex items-center gap-3">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                        <p class="text-xs font-bold text-slate-600 dark:text-slate-300">
                            <span class="text-emerald-500 font-black" x-text="targetCount"></span>
                            recipients will receive this broadcast
                        </p>
                        <template x-if="isCalculating">
                            <svg class="w-3 h-3 text-primary-500 animate-spin ml-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </template>
                    </div>
                </div>

                {{-- SECTION 2: MESSAGE COMPOSER --}}
                <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-200 dark:border-dark-border shadow-sm">
                    <h2 class="text-[10px] font-black uppercase text-slate-800 dark:text-white tracking-widest mb-4 flex items-center gap-2">
                        <div class="w-1.5 h-1.5 bg-violet-600 rounded-full"></div>
                        2. Compose Message
                    </h2>

                    <div class="space-y-5">
                        {{-- Textarea --}}
                        <div class="group relative">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Message Narrative</label>
                            <textarea
                                id="messageTextarea"
                                x-model="message"
                                rows="4"
                                placeholder="Type your message here..."
                                class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-2xl py-3 px-4 pb-12 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-violet-500 shadow-inner-sm resize-none scrollbar-hide"
                            ></textarea>
                            {{-- Encoding badges --}}
                            <div class="absolute bottom-4 right-4 flex items-center gap-2">
                                <div class="px-3 py-1 bg-white dark:bg-dark-card rounded-lg border border-slate-200 dark:border-dark-border text-[9px] font-black uppercase tracking-tighter shadow-sm flex items-center gap-2">
                                    <template x-if="encoding === 'Unicode'">
                                        <span class="w-2 h-2 bg-violet-500 rounded-full animate-pulse"></span>
                                    </template>
                                    <template x-if="encoding !== 'Unicode'">
                                        <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                                    </template>
                                    <span x-text="encoding" class="text-slate-600 dark:text-slate-300"></span>
                                    <span class="text-slate-300">|</span>
                                    <span x-text="message.length" class="text-slate-700 dark:text-slate-200"></span> Chars
                                </div>
                                <div class="px-3 py-1 bg-violet-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest shadow-lg shadow-violet-200 dark:shadow-none">
                                    Parts: <span x-text="segments"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Dynamic Variable Chips --}}
                        <div>
                            <p class="text-[9px] font-black uppercase text-slate-400 tracking-widest mb-2">Insert Variable</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="v in variables" :key="v">
                                    <button
                                        @click="insertVariable(v)"
                                        class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-primary-50 dark:hover:bg-primary-500/10 hover:text-primary-600 rounded-xl text-[10px] font-bold text-slate-500 dark:text-slate-400 transition-all border border-transparent hover:border-primary-100 dark:hover:border-primary-500/30"
                                    >
                                        {<span x-text="v"></span>}
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-dark-card rounded-2xl p-4 border border-slate-200 dark:border-dark-border shadow-lg space-y-4">
                    {{-- Stats & Schedule Toggle --}}
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-center gap-6">
                            <div class="text-left leading-none">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Recipients</p>
                                <p class="text-lg font-black text-slate-800 dark:text-white" x-text="targetCount"></p>
                            </div>
                            <div class="w-px h-8 bg-slate-100 dark:bg-slate-800"></div>
                            <div class="text-left leading-none">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">SMS Parts</p>
                                <p class="text-sm font-black text-slate-800 dark:text-white" x-text="segments"></p>
                            </div>
                            <div class="w-px h-8 bg-slate-100 dark:bg-slate-800"></div>
                            <div class="text-left leading-none">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Encoding</p>
                                <p class="text-sm font-black" :class="encoding === 'Unicode' ? 'text-violet-500' : 'text-emerald-500'" x-text="encoding"></p>
                            </div>
                        </div>

                        {{-- Scheduling Toggle --}}
                        <div class="flex items-center gap-4 bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-slate-100 dark:border-dark-border">
                            <div class="flex flex-col text-right">
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Schedule</span>
                                <span class="text-[10px] font-bold" :class="isScheduled ? 'text-primary-600' : 'text-slate-500'" x-text="isScheduled ? 'FOR LATER' : 'NOW'"></span>
                            </div>
                            <button 
                                @click="isScheduled = !isScheduled" 
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="isScheduled ? 'bg-primary-600' : 'bg-slate-200 dark:bg-slate-700'"
                            >
                                <span 
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="isScheduled ? 'translate-x-5' : 'translate-x-0'"
                                ></span>
                            </button>
                        </div>
                    </div>

                    {{-- Schedule Inputs & Final Action --}}
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100 dark:border-dark-border">
                        <div class="w-full md:w-auto flex-1 h-12 flex items-center">
                            <template x-if="isScheduled">
                                <div class="w-full max-w-xs group relative" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-x-4">
                                    <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Select Delivery Time</label>
                                    <input 
                                        type="datetime-local" 
                                        x-model="scheduledAt"
                                        class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500"
                                    >
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <button
                                @click="sendNow()"
                                :disabled="isSending || !message || targetCount == 0 || (isScheduled && !scheduledAt)"
                                class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-8 py-3 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-200 dark:shadow-none"
                            >
                                <template x-if="isSending">
                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </template>
                                <template x-if="!isSending">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                </template>
                                <span x-text="isSending ? 'Processing...' : (isScheduled ? 'Schedule Broadcast' : 'Broadcast Now')"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: PHONE PREVIEW (4 columns) --}}
            <div class="lg:col-span-4 flex flex-col items-center">
                <div class="sticky top-24 w-full">
                    {{-- Phone Frame --}}
                    <div class="relative mx-auto border-gray-800 dark:border-gray-800 bg-gray-800 border-[10px] rounded-[2rem] h-[520px] w-[260px] shadow-xl">
                        <div class="h-[32px] w-[3px] bg-gray-800 absolute -left-[17px] top-[72px] rounded-l-lg"></div>
                        <div class="h-[46px] w-[3px] bg-gray-800 absolute -left-[17px] top-[124px] rounded-l-lg"></div>
                        <div class="h-[46px] w-[3px] bg-gray-800 absolute -left-[17px] top-[178px] rounded-l-lg"></div>
                        <div class="h-[64px] w-[3px] bg-gray-800 absolute -right-[17px] top-[142px] rounded-r-lg"></div>
                        <div class="rounded-[2rem] overflow-hidden w-full h-full bg-white dark:bg-gray-950">
                            {{-- Status Bar --}}
                            <div class="h-8 w-full flex items-center justify-between px-6 pt-2 bg-white dark:bg-gray-950">
                                <span class="text-[9px] font-bold text-slate-800 dark:text-white">9:41</span>
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-700 dark:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M1.5 8.5a13 13 0 0121 0M5 12a10 10 0 0114 0M8.5 15.5a6 6 0 017 0M12 19h.01"/></svg>
                                    <div class="w-5 h-2.5 rounded-sm border border-slate-700 dark:border-white relative overflow-hidden"><div class="absolute inset-y-0 left-0 w-3/4 bg-slate-700 dark:bg-white rounded-sm"></div></div>
                                </div>
                            </div>

                            {{-- Messenger UI --}}
                            <div class="p-3 space-y-3 h-[calc(100%-40px)] flex flex-col bg-white dark:bg-gray-950">
                                {{-- Sender info --}}
                                <div class="flex flex-col items-center mb-1">
                                    <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center text-primary-600 dark:text-primary-300 font-bold text-base mb-1">C</div>
                                    <p class="text-[9px] font-black text-slate-800 dark:text-white uppercase tracking-tighter">Corevisys POS</p>
                                    <p class="text-[7px] text-slate-400 font-medium">Verified Sender · SMS</p>
                                </div>

                                {{-- Message Bubble --}}
                                <div class="flex-1 overflow-y-auto space-y-3 scrollbar-hide">
                                    <div class="text-[8px] text-slate-400 text-center font-bold uppercase tracking-widest">Today</div>
                                    <div class="max-w-[90%] bg-slate-100 dark:bg-gray-800 rounded-2xl p-3 rounded-bl-none ml-1">
                                        <p class="text-[10px] leading-relaxed text-slate-700 dark:text-slate-200 font-medium break-words" x-text="message || 'Your message preview will appear here...'"></p>
                                        <p class="text-[7px] text-slate-400 mt-2 text-right uppercase">Just Now</p>
                                    </div>
                                    {{-- Segments badge --}}
                                    <template x-if="message && segments > 1">
                                        <div class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-full">
                                            <svg class="w-2 h-2 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z"/></svg>
                                            <span class="text-[7px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest" x-text="`${segments} SMS parts`"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- Fake input bar --}}
                                <div class="h-10 bg-slate-50 dark:bg-gray-900 rounded-full flex items-center px-4 gap-2 border border-slate-100 dark:border-gray-800">
                                    <div class="flex-1 text-[9px] text-slate-300 dark:text-gray-600 font-bold italic tracking-widest">Message...</div>
                                    <div class="w-6 h-6 bg-primary-600 rounded-full flex items-center justify-center text-white">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest text-center mt-4">Live Mobile Preview</p>

                    {{-- Segment / Cost Summary Card (below phone) --}}
                    <div class="mt-4 bg-white dark:bg-dark-card rounded-2xl p-4 border border-slate-200 dark:border-dark-border shadow-sm space-y-2">
                        <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest">Delivery Summary</p>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">Segment</span>
                                <span class="text-[10px] font-black" :class="target === 'all' ? 'text-emerald-500' : target === 'due' ? 'text-amber-500' : target === 'emi' ? 'text-violet-500' : 'text-blue-500'" x-text="target.toUpperCase()"></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">Recipients</span>
                                <span class="text-[10px] font-black text-slate-800 dark:text-white" x-text="targetCount.toLocaleString()"></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">Parts / SMS</span>
                                <span class="text-[10px] font-black text-slate-800 dark:text-white" x-text="segments"></span>
                            </div>
                            <div class="pt-2 border-t border-slate-100 dark:border-dark-border flex justify-between items-center">
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">Total SMS</span>
                                <span class="text-[11px] font-black text-primary-600" x-text="(targetCount * segments).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function smsComposer() {
            return {
                target: 'all',
                selectedTemplate: '',
                message: '',
                segments: 1,
                encoding: 'GSM-7',
                targetCount: 0,
                minDue: '',
                area: '',
                customerSearchQuery: '',
                customerSearchResults: [],
                showCustomerSearch: false,
                selectedCustomer: null,
                selectedCustomerIds: [],
                isScheduled: false,
                scheduledAt: '',
                isCalculating: false,
                isSending: false,
                toast: { show: false, type: 'success', message: '', link: '' },
                templates: @json(\App\Models\DbSmsTemplate::all()),
                variables: ['customer_name', 'due_amount', 'invoice_no', 'due_date', 'company_name', 'emi_amount'],
                countTimer: null,

                init() {
                    this.$watch('message', () => this.calculateSegments());
                    this.$watch('target', () => {
                        this.selectedCustomer = null;
                        this.selectedCustomerIds = [];
                        this.fetchTargetCount();
                    });
                    this.fetchTargetCount();
                },

                calculateSegments() {
                    if (!this.message) { this.segments = 1; this.encoding = 'GSM-7'; return; }
                    const isUnicode = /[^\u0000-\u007F]+/.test(this.message);
                    this.encoding = isUnicode ? 'Unicode' : 'GSM-7';
                    const len = this.message.length;
                    const limit = isUnicode ? (len <= 70 ? 70 : 67) : (len <= 160 ? 160 : 153);
                    this.segments = Math.ceil(len / limit) || 1;
                },

                fetchTargetCount() {
                    clearTimeout(this.countTimer);
                    this.isCalculating = true;
                    this.countTimer = setTimeout(async () => {
                        try {
                            const params = new URLSearchParams({
                                target: this.target,
                                min_due: this.minDue,
                                area: this.area,
                            });
                            
                            // Add customer_ids for single selection
                            this.selectedCustomerIds.forEach(id => {
                                params.append('customer_ids[]', id);
                            });

                            const res = await fetch(`{{ route('sms.send.count') }}?${params}`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await res.json();
                            this.targetCount = data.count ?? 0;
                        } catch(e) {
                            this.targetCount = 0;
                        } finally {
                            this.isCalculating = false;
                        }
                    }, 300);
                },

                async searchCustomers() {
                    if (this.customerSearchQuery.length < 2) {
                        this.customerSearchResults = [];
                        return;
                    }
                    try {
                        const res = await fetch(`{{ route('sms.send.search.customers') }}?q=${this.customerSearchQuery}`);
                        this.customerSearchResults = await res.json();
                    } catch(e) {
                        this.customerSearchResults = [];
                    }
                },

                selectCustomer(c) {
                    this.selectedCustomer = c;
                    this.selectedCustomerIds = [c.id];
                    this.customerSearchQuery = '';
                    this.customerSearchResults = [];
                    this.showCustomerSearch = false;
                    this.fetchTargetCount();
                },

                insertVariable(v) {
                    const ta = document.getElementById('messageTextarea');
                    if (!ta) { this.message += `{${v}} `; return; }
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    this.message = this.message.substring(0, start) + `{${v}}` + this.message.substring(end);
                    this.$nextTick(() => { ta.selectionStart = ta.selectionEnd = start + v.length + 2; ta.focus(); });
                },

                onTemplateChange() {
                    const tpl = this.templates.find(t => t.id == this.selectedTemplate);
                    if (tpl) this.message = tpl.content ?? tpl.template_content ?? '';
                },

                async sendNow() {
                    if (!this.message) { this.showToast('error', 'Please compose a message before broadcasting.'); return; }
                    if (this.targetCount === 0) { this.showToast('error', 'No recipients found for the selected filters.'); return; }
                    if (!confirm(`Broadcast "${this.message.substring(0, 40)}..." to ${this.targetCount.toLocaleString()} recipient(s)?`)) return;

                    this.isSending = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                        const body = new FormData();
                        body.append('message', this.message);
                        body.append('target', this.target);
                        body.append('template_id', this.selectedTemplate);
                        body.append('min_due', this.minDue);
                        body.append('area', this.area);
                        body.append('is_scheduled', this.isScheduled ? 1 : 0);
                        if (this.isScheduled) body.append('scheduled_at', this.scheduledAt);
                        body.append('_token', token);

                        // Attach customer IDs
                        this.selectedCustomerIds.forEach(id => {
                            body.append('customer_ids[]', id);
                        });

                        const res = await fetch('{{ route('sms.send.process') }}', { method: 'POST', body });
                        const data = await res.json();

                        if (res.ok && data.status === 'success') {
                            this.showToast('success', data.message, data.campaign_link);
                            // Reset form
                            this.message = '';
                            this.selectedTemplate = '';
                            this.fetchTargetCount();
                        } else {
                            this.showToast('error', data.message ?? 'Broadcast failed. Please try again.');
                        }
                    } catch(e) {
                        this.showToast('error', 'Network error. Please check your connection.');
                    } finally {
                        this.isSending = false;
                    }
                },

                showToast(type, message, link = '') {
                    this.toast = { show: true, type, message, link };
                    setTimeout(() => { this.toast.show = false; }, 6000);
                }
            }
        }
    </script>
</x-app-layout>
