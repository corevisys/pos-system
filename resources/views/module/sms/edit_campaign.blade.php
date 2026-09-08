<x-app-layout title="Edit SMS Campaign">
    <div class="max-w-4xl mx-auto space-y-10">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <a href="{{ route('sms.campaigns') }}" class="inline-flex items-center gap-2 text-[10px] font-black uppercase text-slate-400 hover:text-primary-600 transition-all mb-2">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Campaigns
                </a>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white uppercase">Edit <span class="text-primary-600">Campaign</span></h1>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold mt-0.5">Adjust campaign details and scheduling before transmission.</p>
            </div>
        </div>

        <form action="{{ route('sms.campaigns.update', $campaign->id) }}" method="POST" class="space-y-6">
            @csrf
            <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-200 dark:border-dark-border shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 dark:border-dark-border">
                    <h2 class="text-[10px] font-black uppercase text-slate-800 dark:text-white tracking-widest">Campaign Configuration</h2>
                </div>
                <div class="p-6 space-y-6">
                    {{-- Campaign Name --}}
                    <div class="group relative">
                        <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Campaign Label</label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $campaign->name) }}"
                            required
                            class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"
                        >
                    </div>

                    {{-- Message Content --}}
                    <div class="group relative">
                        <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Message Narrative</label>
                        <textarea
                            name="message"
                            rows="4"
                            required
                            class="w-full bg-slate-50/50 dark:bg-slate-800/50 border border-slate-200 dark:border-dark-border rounded-2xl py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm resize-none"
                        >{{ old('message', $campaign->template_id ? $campaign->template->content : ($campaign->target_filters['custom_message'] ?? '')) }}</textarea>
                        @if($campaign->template_id)
                            <p class="mt-2 text-[8px] font-black uppercase text-amber-500 tracking-tight italic">Note: This campaign is linked to template "{{ $campaign->template->name }}". Editing here will update the custom message sent for this campaign.</p>
                        @endif
                    </div>

                    {{-- Schedule --}}
                    <div class="group relative">
                        <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10">Delivery Schedule</label>
                        <input
                            type="datetime-local"
                            name="scheduled_at"
                            value="{{ old('scheduled_at', $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                            class="w-full bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-300 outline-none transition-all focus:border-primary-500 shadow-inner-sm"
                        >
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('sms.campaigns') }}" class="px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</a>
                <button type="submit" class="px-8 py-2.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200">Save Intelligence</button>
            </div>
        </form>
    </div>
</x-app-layout>
