<x-app-layout title="SMS Template Form">
    <div x-data="{
        templateContent: '{{ $template->content ?? '' }}',
        insertVar(v) {
            const el = document.getElementById('template_content');
            const start = el.selectionStart;
            const end = el.selectionEnd;
            this.templateContent = this.templateContent.substring(0, start) + v + this.templateContent.substring(end);
            this.$nextTick(() => {
                el.focus();
                el.setSelectionRange(start + v.length, start + v.length);
            });
        }
    }">
        <!-- HEADER -->
        <div class="flex items-center justify-between transition-all mb-4">
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center shadow-md shadow-primary-200">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <span>{{ isset($template) ? 'Sync' : 'Build' }} Template</span>
                </h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1 italic">Refining communication protocols</p>
            </div>
            
            <a href="{{ route('sms.templates') }}" class="px-4 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all flex items-center gap-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Return to Hub
            </a>
        </div>

        <!-- MAIN FORM CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
            <form action="{{ isset($template) ? route('sms.templates.update', $template->id) : route('sms.templates.store') }}" method="POST" class="p-5 md:p-6">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- LEFT SIDE: INPUTS -->
                    <div class="lg:col-span-8 space-y-6">
                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-[0.2em] z-10">Template Name<span class="text-rose-500 ml-0.5">*</span></label>
                            <input type="text" name="template_name" value="{{ $template->template_name ?? '' }}" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-200 outline-none transition-all focus:border-primary-500 focus:bg-white dark:focus:bg-dark-card shadow-inner-sm" placeholder="e.g GREETING TO CUSTOMER">
                        </div>

                        <div class="group relative">
                            <label class="absolute -top-1.5 left-3 bg-white dark:bg-dark-card px-1 text-[9px] font-black uppercase text-slate-400 tracking-[0.2em] z-10">Narrative Content<span class="text-rose-500 ml-0.5">*</span></label>
                            <textarea id="template_content" name="content" x-model="templateContent" rows="8" required class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-xl py-3 px-3 text-[11px] font-bold text-slate-700 dark:text-slate-200 outline-none transition-all focus:border-primary-500 focus:bg-white dark:focus:bg-dark-card shadow-inner-sm leading-relaxed" placeholder="Craft your message..."></textarea>
                        </div>
                    </div>

                    <!-- RIGHT SIDE: VARIABLES -->
                    <div class="lg:col-span-4">
                        <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-5 border border-slate-100 dark:border-dark-border h-full">
                            <h3 class="text-[9px] font-black uppercase text-primary-600 tracking-[0.2em] mb-4 flex items-center gap-2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                Variable Library
                            </h3>
                            
                            <div class="flex flex-wrap gap-1.5">
                                @php
                                    $vars = [
                                        '{{customer_name}}', '{{sales_id}}', '{{sales_date}}', 
                                        '{{sales_amount}}', '{{paid_amt}}', '{{due_amt}}',
                                        '{{emi_amount}}', '{{emi_date}}',
                                        '{{store_name}}', '{{store_mobile}}', '{{emi_amount}}', '{{emi_date}}'
                                    ];
                                @endphp
                                @foreach($vars as $v)
                                    <button type="button" @click="insertVar('{{ $v }}')" class="px-2 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[9px] font-bold text-slate-500 dark:text-slate-400 hover:border-primary-500 hover:text-primary-600 transition-all active:scale-95">
                                        {{ $v }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-5 p-3 bg-primary-600/5 rounded-xl border border-primary-600/10">
                                <p class="text-[8px] text-slate-500 dark:text-slate-400 leading-tight">Tap any label to inject it into the editor at your cursor position.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER ACTIONS -->
                <div class="flex items-center justify-end gap-3 mt-8 pt-5 border-t border-slate-50 dark:border-dark-border">
                    <button type="submit" class="px-8 py-2 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-200/50 dark:shadow-none hover:bg-emerald-600 active:scale-95 transition-all flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        {{ isset($template) ? 'Sync' : 'Deploy' }} Protocol
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
