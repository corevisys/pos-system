<x-app-layout title="Multi-Store Dashboard">
    {{-- =========================================================================
         HEADER
    ========================================================================= --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
        <div>
            <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Multi-Store Network Dashboard</span>
                <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                    Super Admin
                </span>
            </h1>
            <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">
                {{ now()->format('l, F j, Y') }} &mdash; {{ $networkStats['store_count'] }} store{{ $networkStats['store_count'] !== 1 ? 's' : '' }} in network
            </p>
        </div>
        {{-- Back to single-store dashboard --}}
        <a href="{{ route('dashboard') }}" class="btn-secondary text-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            My Dashboard
        </a>
    </div>

    {{-- =========================================================================
         NETWORK-WIDE KPI SUMMARY ROW
    ========================================================================= --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">

        {{-- Store Count --}}
        <x-stat-card
            label="Total Stores"
            :value="$networkStats['store_count']"
            iconBg="bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Active in network</p>'
        />

        {{-- Network Today Sales --}}
        <x-stat-card
            label="Network Sales Today"
            :value="format_currency($networkStats['today_sales'])"
            iconBg="bg-blue-50 dark:bg-blue-500/10 text-blue-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Combined today</p>'
        />

        {{-- Network Today Orders --}}
        <x-stat-card
            label="Network Orders Today"
            :value="$networkStats['today_orders']"
            iconBg="bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Orders placed today</p>'
        />

        {{-- Network Month Sales --}}
        <x-stat-card
            label="Network Month Sales"
            :value="format_currency($networkStats['this_month_sales'])"
            iconBg="bg-purple-50 dark:bg-purple-500/10 text-purple-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Current month</p>'
        />

        {{-- Network Month Purchases --}}
        <x-stat-card
            label="Network Purchases"
            :value="format_currency($networkStats['this_month_purchases'])"
            iconBg="bg-amber-50 dark:bg-amber-500/10 text-amber-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Current month</p>'
        />

        {{-- Network Outstanding Due --}}
        <x-stat-card
            label="Network Due"
            :value="format_currency($networkStats['total_outstanding_due'])"
            iconBg="bg-rose-50 dark:bg-rose-500/10 text-rose-600"
            icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
            footer='<p class="text-xs font-bold text-text-muted mt-2">Total outstanding</p>'
        />
    </div>

    {{-- =========================================================================
         SECTION HEADING: Per-Store Breakdown
    ========================================================================= --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="h-0.5 flex-1 bg-gradient-to-r from-primary-500/30 to-transparent rounded-full"></div>
        <h2 class="text-[11px] font-black text-text-muted uppercase tracking-widest">Per-Store Breakdown</h2>
        <div class="h-0.5 flex-1 bg-gradient-to-l from-primary-500/30 to-transparent rounded-full"></div>
    </div>

    {{-- =========================================================================
         PER-STORE CARDS GRID
    ========================================================================= --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($storeStats as $idx => $entry)
            @php
                $store    = $entry['store'];
                $s        = $entry['stats']['stats'];
                $storeColors = [
                    'bg-blue-500',   'bg-indigo-500', 'bg-violet-500', 'bg-emerald-500',
                    'bg-amber-500',  'bg-rose-500',   'bg-cyan-500',   'bg-pink-500',
                ];
                $accentBg = $storeColors[$idx % count($storeColors)];

                // Month-over-month change indicator
                if ($s['month_change_percent'] === null) {
                    $changeLabel = 'N/A vs last mo.';
                    $changeClass = 'text-slate-400';
                } elseif ($s['month_change_percent'] >= 0) {
                    $changeLabel = '▲ +' . $s['month_change_percent'] . '% vs last mo.';
                    $changeClass = 'text-emerald-500';
                } else {
                    $changeLabel = '▼ ' . $s['month_change_percent'] . '% vs last mo.';
                    $changeClass = 'text-rose-500';
                }
            @endphp

            <div class="relative bg-white dark:bg-dark-card rounded-2xl border border-border dark:border-dark-border shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">

                {{-- Store header stripe --}}
                <div class="flex items-center gap-3 px-5 py-4 border-b border-border dark:border-dark-border">
                    {{-- Colour avatar --}}
                    <div class="w-8 h-8 rounded-xl {{ $accentBg }} flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-[11px] font-black uppercase">
                            {{ substr($store->store_name ?? 'S', 0, 2) }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-text-primary dark:text-dark-text truncate">
                            {{ $store->store_name ?: 'Store #' . $store->id }}
                        </p>
                        <p class="text-[10px] text-text-muted font-bold truncate">
                            {{ $store->store_code ? 'Code: ' . $store->store_code : ('ID: ' . $store->id) }}
                            @if($store->city) &bull; {{ $store->city }} @endif
                        </p>
                    </div>
                    {{-- Store settings shortcut --}}
                    @if(auth()->user()->hasPermission('store_settings_view'))
                    <a href="{{ route('settings.store') }}"
                       class="flex-shrink-0 w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-primary-50 dark:hover:bg-primary-500/10 flex items-center justify-center transition-colors"
                       title="Store Settings">
                        <svg class="w-3.5 h-3.5 text-slate-400 hover:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </a>
                    @endif
                </div>

                {{-- KPI grid (2×2) --}}
                <div class="grid grid-cols-2 divide-x divide-y divide-border dark:divide-dark-border">

                    {{-- Today Sales --}}
                    <div class="p-4">
                        <p class="text-[9px] font-bold text-text-muted uppercase tracking-widest mb-1">Today Sales</p>
                        <p class="text-base font-black text-text-primary dark:text-dark-text leading-none">
                            {{ format_currency($s['today_sales']) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">{{ $s['today_orders'] }} order{{ $s['today_orders'] !== 1 ? 's' : '' }}</p>
                    </div>

                    {{-- Outstanding Due --}}
                    <div class="p-4">
                        <p class="text-[9px] font-bold text-text-muted uppercase tracking-widest mb-1">Outstanding Due</p>
                        <p class="text-base font-black {{ $s['total_outstanding_due'] > 0 ? 'text-rose-500' : 'text-emerald-500' }} leading-none">
                            {{ format_currency($s['total_outstanding_due']) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">Unpaid invoices</p>
                    </div>

                    {{-- Month Sales --}}
                    <div class="p-4">
                        <p class="text-[9px] font-bold text-text-muted uppercase tracking-widest mb-1">Month Sales</p>
                        <p class="text-base font-black text-text-primary dark:text-dark-text leading-none">
                            {{ format_currency($s['this_month_sales']) }}
                        </p>
                        <p class="text-[10px] {{ $changeClass }} mt-1 font-semibold">{{ $changeLabel }}</p>
                    </div>

                    {{-- Month Purchases --}}
                    <div class="p-4">
                        <p class="text-[9px] font-bold text-text-muted uppercase tracking-widest mb-1">Month Purchases</p>
                        <p class="text-base font-black text-text-primary dark:text-dark-text leading-none">
                            {{ format_currency($s['this_month_purchases']) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">Current month</p>
                    </div>
                </div>

            </div>
        @endforeach
    </div>

    {{-- Empty state --}}
    @if($storeStats->isEmpty())
        <div class="text-center py-20">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <p class="text-sm text-text-muted font-bold">No stores found in the database.</p>
        </div>
    @endif

</x-app-layout>
