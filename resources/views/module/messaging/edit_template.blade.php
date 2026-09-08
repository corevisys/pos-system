<x-app-layout title="Edit Messaging Template">
    <div>
        <form action="{{ route('messaging.templates.update', $template->id) }}" method="POST">
            @csrf
            
            <!-- HEADER & ACTIONS -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
                <div>
                    <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Message Template <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest font-black">Update Template</span></h1>
                    <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                             <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                             Home
                        </a>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        <a href="{{ route('messaging.templates') }}" class="hover:text-primary-600 transition-colors text-[10px] font-black uppercase tracking-wider text-slate-400">Templates List</a>
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                        <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Update Template</span>
                    </div>
                </div>
                
                <div class="flex gap-2 w-full md:w-auto">
                    <a href="{{ route('messaging.templates') }}" class="flex-1 md:flex-none px-4 py-2 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center justify-center gap-2 text-slate-600 dark:text-slate-400 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Cancel
                    </a>
                    <button type="submit" class="flex-1 md:flex-none px-6 py-2 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center justify-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Update Template
                    </button>
                </div>
            </div>



            <!-- MAIN FORM BODY -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- FORM SECTION -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white dark:bg-dark-card p-6 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm">
                        <div class="flex items-center gap-2 mb-6 px-1">
                            <div class="w-1.5 h-6 bg-emerald-500 rounded-full"></div>
                            <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-600">Update Template Details</h2>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 px-0.5">Template Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="template_name" value="{{ old('template_name', $template->template_name) }}" required placeholder="e.g. GREETING TO CUSTOMER ON SALES" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-dark-border rounded-2xl py-3 px-4 text-[11px] font-bold transition-all focus:ring-1 focus:ring-primary-500 text-slate-700 dark:text-white outline-none">
                            </div>

                            <div>
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 px-0.5">Message Content <span class="text-rose-500">*</span></label>
                                <textarea name="content" required rows="10" placeholder="Hi {@{{customer_name}}}, ..." class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-dark-border rounded-2xl py-3 px-4 text-[11px] font-bold transition-all focus:ring-1 focus:ring-primary-500 text-slate-700 dark:text-white outline-none resize-none leading-relaxed">{{ old('content', $template->content) }}</textarea>
                                <p class="mt-2 text-[9px] font-bold text-slate-400 italic">Use the variables on the right to personalize your message.</p>
                            </div>

                            <div class="pt-4 border-t border-slate-50 dark:border-dark-border">
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest block mb-3 px-0.5">Template Status</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer group">
                                        <input type="radio" name="status" value="1" {{ $template->status == 1 ? 'checked' : '' }} class="w-4 h-4 border-slate-200 text-emerald-500 focus:ring-emerald-500">
                                        <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">Active</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer group">
                                        <input type="radio" name="status" value="0" {{ $template->status == 0 ? 'checked' : '' }} class="w-4 h-4 border-slate-200 text-rose-500 focus:ring-rose-500">
                                        <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest">Inactive</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INFO SECTION (VARIABLES) -->
                <div class="space-y-4">
                    <div class="bg-slate-900 dark:bg-dark-card p-6 rounded-3xl shadow-2xl relative overflow-hidden group">
                        <!-- Background Glow Accent -->
                        <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/20 rounded-full blur-[80px] group-hover:bg-emerald-500/30 transition-all duration-700"></div>
                        
                        <div class="relative z-10">
                            <div class="flex items-center gap-2 mb-6">
                                <div class="w-8 h-8 bg-emerald-500/20 rounded-xl flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-white">SMS Content Variables</h3>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                @php
                                    $variables = [
                                        ['key' => '{{customer_name}}', 'label' => 'Customer Name'],
                                        ['key' => '{{sales_id}}', 'label' => 'Sales ID'],
                                        ['key' => '{{sales_date}}', 'label' => 'Sales Date'],
                                        ['key' => '{{sales_amount}}', 'label' => 'Total Amount'],
                                        ['key' => '{{paid_amt}}', 'label' => 'Paid Amount'],
                                        ['key' => '{{due_amt}}', 'label' => 'Due Amount'],
                                        ['key' => '{{emi_date}}', 'label' => 'EMI Due Date'],
                                        ['key' => '{{emi_amount}}', 'label' => 'EMI Amount'],
                                        ['key' => '{{store_name}}', 'label' => 'Store Name'],
                                        ['key' => '{{store_mobile}}', 'label' => 'Store Mobile'],
                                        ['key' => '{{store_address}}', 'label' => 'Store Address'],
                                        ['key' => '{{store_website}}', 'label' => 'Store Website'],
                                        ['key' => '{{store_email}}', 'label' => 'Store Email'],
                                    ];
                                @endphp

                                @foreach ($variables as $var)
                                    <div class="flex items-center justify-between p-2.5 bg-white/5 hover:bg-white/10 rounded-xl border border-white/5 transition-all group/var cursor-pointer" 
                                         onclick="navigator.clipboard.writeText('{{ $var['key'] }}')">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] font-black text-emerald-400 font-mono tracking-tighter">{{ $var['key'] }}</span>
                                            <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">{{ $var['label'] }}</span>
                                        </div>
                                        <svg class="w-3 h-3 text-slate-600 group-hover/var:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="mt-6 h-1 w-full bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full opacity-30"></div>
        </form>
    </div>
</x-app-layout>
