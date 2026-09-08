<x-app-layout title="SMS Campaigns">
    <div class="space-y-10">
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white uppercase"><span class="text-primary-600">Growth</span> Campaigns</h1>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-bold mt-0.5">Manage bulk broadcast operations and measure outreach impact.</p>
            </div>
            <a href="{{ route('sms.send') }}" class="px-3 py-1.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all flex items-center gap-1.5 shadow-md shadow-primary-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Launch Campaign
            </a>
        </div>

        <!-- CAMPAIGN LIST -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-200 dark:border-dark-border shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-dark-border flex items-center justify-between">
                <h2 class="text-[10px] font-black uppercase text-slate-800 dark:text-white tracking-widest">Active & Historical Campaigns</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Campaign Identity</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Reach / Delivered</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Status Lifecycle</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest">Timeline</th>
                            <th class="px-3 py-2 text-[9px] font-black uppercase text-slate-400 tracking-widest text-right">Insight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-dark-border text-[10px] font-bold">
                        @forelse(\App\Models\SmsCampaign::latest()->get() as $campaign)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="px-3 py-2">
                                <p class="text-slate-800 dark:text-white uppercase">{{ $campaign->name }}</p>
                                <p class="text-[8px] text-slate-400 font-black uppercase tracking-widest">{{ $campaign->target_type }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <p class="text-slate-700 dark:text-slate-300">{{ $campaign->total_sent }} / {{ $campaign->total_recipients }}</p>
                                <div class="w-20 h-1 bg-slate-100 dark:bg-slate-800 rounded-full mt-1 overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: {{ $campaign->total_recipients > 0 ? ($campaign->total_sent / $campaign->total_recipients * 100) : 0 }}%"></div>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $statusColors = [
                                        'Draft' => 'bg-slate-100 text-slate-500',
                                        'Processing' => 'bg-blue-50 text-blue-600',
                                        'Completed' => 'bg-emerald-50 text-emerald-600',
                                        'Failed' => 'bg-rose-50 text-rose-600',
                                    ];
                                    $color = $statusColors[$campaign->status] ?? 'bg-slate-100 text-slate-500';
                                @endphp
                                <span class="{{ $color }} text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-widest">{{ $campaign->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-slate-500 font-medium">
                                {{ $campaign->created_at->format('d M, h:i A') }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if($campaign->status !== 'Completed')
                                        <a href="{{ route('sms.campaigns.edit', $campaign->id) }}" class="p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition-all" title="Edit Campaign">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                    @endif
                                    
                                    <form action="{{ route('sms.campaigns.delete', $campaign->id) }}" method="POST" onsubmit="return confirm('Archive this campaign intelligence?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-all" title="Delete Campaign">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-slate-400 font-medium italic uppercase tracking-widest">No active campaigns discovered.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
