<x-app-layout title="Languages List">
    <div x-data="{
        showConfirmModal: false,
        currentActiveName: '{{ $activeLanguage ? addslashes($activeLanguage->language) : '' }}',
        targetLanguageId: null,
        targetLanguageName: '',

        openActivateConfirm(id, name) {
            this.targetLanguageId = id;
            this.targetLanguageName = name;
            this.showConfirmModal = true;
        }
    }">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Language Management <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Single Active Language</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Settings</span>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Languages</span>
                </div>
            </div>
            <a href="{{ route('settings.languages.create') }}" class="px-5 py-2.5 bg-primary-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shadow-lg shadow-primary-500/20 hover:bg-primary-700 transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                Add Language
            </a>
        </div>

        <!-- ACTIVE LANGUAGE BANNER -->
        @if ($activeLanguage)
            <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/20 dark:border-emerald-500/10 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-sm uppercase italic shadow-sm">
                        {{ substr($activeLanguage->language, 0, 2) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Current System Language</span>
                            <span class="px-2 py-0.5 bg-emerald-500 text-white rounded-full text-[8px] font-black uppercase tracking-wider">Active</span>
                        </div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">
                            {{ $activeLanguage->language }}
                        </h2>
                    </div>
                </div>
                <div class="text-[10px] font-bold text-slate-400">
                    Only 1 language is active system-wide. Activating another language automatically deactivates this one.
                </div>
            </div>
        @endif

        <!-- TABLE SECTION -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/50">
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Language Name</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-center">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        @forelse($languages as $language)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors group {{ $language->status ? 'bg-emerald-50/15 dark:bg-emerald-950/10' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl {{ $language->status ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }} flex items-center justify-center font-black text-xs uppercase italic shadow-sm">
                                            {{ substr($language->language, 0, 2) }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest">{{ $language->language }}</span>
                                            @if ($language->status)
                                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Active Language"></span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center">
                                        @if($language->status == 1)
                                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 rounded-full text-[8px] font-black uppercase tracking-wider shadow-xs">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                Active
                                            </span>
                                        @else
                                            <button 
                                                @click="openActivateConfirm({{ $language->id }}, '{{ addslashes($language->language) }}')" 
                                                type="button" 
                                                class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 dark:bg-slate-800 dark:hover:bg-emerald-950/40 dark:text-slate-400 dark:hover:text-emerald-400 border border-slate-200 dark:border-slate-700 hover:border-emerald-300 rounded-full text-[8px] font-black uppercase tracking-wider transition-all cursor-pointer group/btn"
                                                title="Click to activate {{ $language->language }}"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400 group-hover/btn:bg-emerald-500"></span>
                                                Set Active
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end items-center gap-2">
                                        @if (!$language->status)
                                            <button 
                                                @click="openActivateConfirm({{ $language->id }}, '{{ addslashes($language->language) }}')" 
                                                type="button" 
                                                class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-xl transition-all shadow-sm active:scale-90"
                                                title="Activate Language"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('settings.languages.edit', $language->id) }}" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-xl transition-all shadow-sm active:scale-90" title="Edit Language">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        @if (!$language->status)
                                            <form action="{{ route('settings.languages.destroy', $language->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this language?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-xl transition-all shadow-sm active:scale-90" title="Delete Language">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        @else
                                            <span class="px-2 py-1 text-[8px] font-bold text-slate-400 italic bg-slate-100 dark:bg-slate-800 rounded-lg">Active (Locked)</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-slate-400 font-bold text-[10px] uppercase tracking-[0.2em] italic">No languages found. Add your first language!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ACTIVATE CONFIRMATION MODAL -->
        <div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white dark:bg-dark-card rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-100 dark:border-dark-border">
                    <div class="px-6 py-5 border-b border-slate-50 dark:border-dark-border flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-[0.15em] text-slate-800 dark:text-white">Switch Active Language</h3>
                            <p class="text-[10px] font-bold text-slate-400 mt-0.5">Confirmation required</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed">
                            This will deactivate <strong class="text-slate-900 dark:text-white font-bold" x-text="currentActiveName ? currentActiveName : 'the currently active language'"></strong> and set <strong class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="targetLanguageName"></strong> as the active language across the entire system.
                        </p>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            Continue?
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/5 border-t border-slate-50 dark:border-dark-border flex justify-end items-center gap-3">
                        <button @click="showConfirmModal = false; targetLanguageId = null;" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            Cancel
                        </button>
                        <form :action="'/settings/languages/' + targetLanguageId + '/activate'" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Confirm & Activate
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
