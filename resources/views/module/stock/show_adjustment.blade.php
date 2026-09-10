<x-app-layout title="Stock Adjustment Details">
    <div class="space-y-4">
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Stock Adjustment Details</h1>
                <div class="flex items-center gap-2 text-text-secondary dark:text-dark-text/60 font-medium mt-0.5 text-xs">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('stock.adjustment') }}" class="hover:text-primary transition-colors">Adjustment List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-bold">{{ $adjustment->reference_no }}</span>
                </div>
            </div>

            <a href="{{ route('stock.adjustment.edit', $adjustment->id) }}" class="btn-primary px-4 py-2 text-[11px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit
            </a>
        </div>

        <!-- SUMMARY -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <x-stat-card label="Reference No." :value="$adjustment->reference_no ?? '---'">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Warehouse" :value="$adjustment->warehouse->warehouse_name ?? '---'">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Date" :value="\Carbon\Carbon::parse($adjustment->adjustment_date)->format('d-m-Y')">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
            <x-stat-card label="Created By" :value="$adjustment->user->name ?? 'System'">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        @if($adjustment->adjustment_note)
            <div class="card p-4">
                <p class="text-[9px] font-black uppercase tracking-widest text-text-secondary mb-1">Reason / Note</p>
                <p class="text-sm text-text-primary dark:text-dark-text">{{ $adjustment->adjustment_note }}</p>
            </div>
        @endif

        <!-- ITEMS TABLE -->
        <x-table>
            <x-slot:thead>
                <tr>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Item</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest text-center">Adjusted Qty</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Description</th>
                    <th class="px-4 py-2.5 text-[9px] font-black text-text-secondary uppercase tracking-widest">Serials</th>
                </tr>
            </x-slot:thead>
            @forelse($adjustmentItems as $ai)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-dark-bg/60 transition-colors">
                    <td class="px-4 py-2.5">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-text-primary dark:text-dark-text">{{ $ai['item_name'] }}</span>
                            <span class="text-[9px] text-text-secondary dark:text-dark-text/60 font-mono">{{ $ai['item_code'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <x-badge :color="$ai['quantity'] >= 0 ? 'success' : 'danger'">
                            {{ ($ai['quantity'] >= 0 ? '+' : '') . format_quantity($ai['quantity']) }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-text-secondary dark:text-dark-text/70">{{ $ai['description'] ?: '---' }}</td>
                    <td class="px-4 py-2.5">
                        @if(empty($ai['serials']))
                            <span class="text-[10px] text-text-secondary">---</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach($ai['serials'] as $sn)
                                    <span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 rounded text-[9px] font-mono text-text-secondary">{{ $sn }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-text-secondary">No items on this adjustment.</td>
                </tr>
            @endforelse
        </x-table>

        <div class="flex justify-end">
            <a href="{{ route('stock.adjustment') }}" class="btn-secondary px-4 py-2 text-[11px] font-black uppercase tracking-widest">
                Back to List
            </a>
        </div>
    </div>
</x-app-layout>
