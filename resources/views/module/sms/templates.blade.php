<x-app-layout title="SMS Templates">
    <div class="space-y-10" x-data="{ showModal: false, editMode: false }">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white uppercase"><span class="text-primary-600">Message</span> Templates</h1>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold mt-0.5">Standardize your communication with reusable narrative blocks.</p>
            </div>
            <a href="{{ route('sms.templates.create') }}" class="px-3 py-1.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all flex items-center gap-1.5 shadow-md shadow-primary-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Template
            </a>
        </div>


        <!-- TEMPLATE GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($templates as $tpl)
            <div class="bg-white dark:bg-dark-card rounded-2xl p-4 border border-slate-200 dark:border-dark-border shadow-sm group hover:border-primary-500 transition-all flex flex-col h-full hover:shadow-md">
                <div class="flex items-center justify-between mb-3">
                    <span class="bg-slate-100 dark:bg-slate-800 text-slate-500 text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-widest">
                        Transactional
                    </span>
                    <div class="flex items-center gap-0.5">
                        <a href="{{ route('sms.templates.edit', $tpl->id) }}" class="p-1 text-slate-400 hover:text-primary-600 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        <form action="{{ route('sms.templates.delete', $tpl->id) }}" method="POST" onsubmit="return confirm('Terminate this template?')">
                            @csrf @method('DELETE')
                            <button class="p-1 text-slate-400 hover:text-rose-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <h3 class="text-[11px] font-black text-slate-800 dark:text-white mb-1.5 uppercase tracking-tight">{{ $tpl->template_name }}</h3>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium line-clamp-3 mb-4 leading-relaxed bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl italic">
                    "{{ $tpl->content }}"
                </p>
                <div class="mt-auto pt-3 border-t border-slate-100 dark:border-dark-border flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                        <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Active</span>
                    </div>
                    <span class="text-[8px] font-black text-primary-600 uppercase tracking-tighter">English</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
