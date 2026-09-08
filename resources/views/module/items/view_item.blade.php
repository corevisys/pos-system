<x-app-layout title="Item Details">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Item Details <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">{{ $item->item_code }}</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-black uppercase tracking-wider">Items List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">View Details</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('items.edit', $item->id) }}" class="px-5 py-2 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-200 dark:shadow-none flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Item
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- LEFT COLUMN: Image & Basic Stats -->
            <div class="lg:col-span-4 space-y-6">
                <!-- IMAGE CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-6 shadow-sm overflow-hidden flex flex-col items-center text-center">
                    <div class="w-48 h-48 rounded-2xl border-4 border-slate-50 dark:border-slate-800 shadow-inner overflow-hidden mb-4 bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                        @if($item->item_image)
                            <img src="{{ asset($item->item_image) }}" alt="{{ $item->item_name }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-16 h-16 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        @endif
                    </div>
                    <h2 class="text-lg font-black text-slate-800 dark:text-white leading-tight uppercase tracking-tight">{{ $item->item_name }}</h2>
                    <span class="text-[10px] font-black text-primary-500 uppercase tracking-widest mt-1">{{ $item->brand->brand_name ?? 'No Brand' }}</span>
                    
                    <div class="grid grid-cols-2 gap-4 w-full mt-6 pt-6 border-t border-slate-50 dark:border-dark-border">
                        <div class="flex flex-col items-center">
                            <span class="text-[9px] font-black text-slate-400 underline decoration-primary-500 decoration-2 underline-offset-4 uppercase tracking-widest mb-1">Total Stock</span>
                            <span class="text-xl font-black text-slate-800 dark:text-white leading-none">{{ number_format($totalStock, 2) }}</span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter mt-1">{{ $item->unit->unit_name ?? 'Units' }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-[9px] font-black text-slate-400 underline decoration-rose-500 decoration-2 underline-offset-4 uppercase tracking-widest mb-1">Alert Qty</span>
                            <span class="text-xl font-black text-rose-500 leading-none">{{ $item->alert_qty }}</span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter mt-1">Minimum</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-5 shadow-sm">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 border-b border-slate-50 dark:border-dark-border pb-2">Warehouse Stock</h3>
                    <div class="space-y-2">
                        @forelse($item->warehouseItems as $warehouseItem)
                        <div class="flex items-center justify-between text-[11px] font-bold">
                            <span class="text-slate-500">{{ $warehouseItem->warehouse->warehouse_name ?? 'Unknown Warehouse' }}</span>
                            <span class="text-slate-800 dark:text-slate-200 tabular-nums">{{ number_format((float) $warehouseItem->available_qty, 2) }} {{ $item->unit->unit_name ?? 'Units' }}</span>
                        </div>
                        @empty
                        <p class="text-[10px] text-slate-400">No warehouse stock recorded.</p>
                        @endforelse
                    </div>
                </div>

                <!-- QUICK INFO -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-5 shadow-sm space-y-4">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 border-b border-slate-50 dark:border-dark-border pb-2 flex items-center gap-2">
                        <svg class="w-3 h-3 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Additional Stats
                    </h3>
                    <div class="flex items-center justify-between text-[11px] font-bold">
                        <span class="text-slate-500">Item Group:</span>
                        <span class="text-slate-800 dark:text-slate-200">{{ $item->item_group }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-bold">
                        <span class="text-slate-500">Category:</span>
                        <span class="text-slate-800 dark:text-slate-200 uppercase tracking-tighter text-[10px]">{{ $item->category->category_name ?? '---' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-bold">
                        <span class="text-slate-500">Tax Type:</span>
                        <span class="text-slate-800 dark:text-slate-200 px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded-lg text-[9px] uppercase tracking-widest">{{ $item->tax_type }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-bold">
                        <span class="text-slate-500">Created At:</span>
                        <span class="text-slate-800 dark:text-slate-200 italic text-[9px]">{{ date('d M, Y', strtotime($item->created_date)) }}</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Detailed Info & Serial Numbers -->
            <div class="lg:col-span-8 space-y-6">
                <!-- PRICING CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-6 shadow-sm overflow-hidden">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-6 border-b border-slate-50 dark:border-dark-border pb-2 flex items-center gap-2">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Pricing details
                    </h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="p-3 bg-amber-50 dark:bg-amber-500/5 rounded-2xl border border-amber-100/50 dark:border-amber-500/10">
                            <span class="text-[8px] font-black text-amber-600 uppercase tracking-widest mb-1 block">Purchase Price</span>
                            <span class="text-lg font-black text-slate-800 dark:text-white tabular-nums">{{ format_currency($item->purchase_price) }}</span>
                        </div>
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-500/5 rounded-2xl border border-indigo-100/50 dark:border-indigo-500/10">
                            <span class="text-[8px] font-black text-indigo-600 uppercase tracking-widest mb-1 block">Profit Margin</span>
                            <span class="text-lg font-black text-slate-800 dark:text-white tabular-nums">{{ $item->profit_margin }}%</span>
                        </div>
                        <div class="p-3 bg-emerald-50 dark:bg-emerald-500/5 rounded-2xl border border-emerald-100/50 dark:border-emerald-500/10">
                            <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mb-1 block">Sales Price</span>
                            <span class="text-lg font-black text-slate-800 dark:text-white tabular-nums">{{ format_currency($item->sales_price) }}</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-dark-border">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1 block">MRP</span>
                            <span class="text-lg font-black text-slate-800 dark:text-white tabular-nums">{{ format_currency($item->mrp) }}</span>
                        </div>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-6 shadow-sm overflow-hidden">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 flex items-center gap-2">
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                        Description
                    </h3>
                    <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400 font-medium">
                        {{ $item->item_description ?: 'No description provided.' }}
                    </p>
                </div>

                <!-- SERIAL NUMBERS SECTION -->
                @if($item->is_serialized)
                <div class="bg-white dark:bg-dark-card rounded-3xl border-2 border-primary-100 dark:border-primary-500/20 overflow-hidden shadow-sm animate-fade-in group">
                    <div class="bg-primary-500 px-6 py-3 flex justify-between items-center group-hover:bg-primary-600 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 17h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                            </div>
                            <h3 class="text-xs font-black text-white uppercase tracking-[0.2em]">Inventory Serial Numbers</h3>
                        </div>
                        <span class="bg-white/20 text-white text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest backdrop-blur-sm">Total: {{ $item->serials->count() }}</span>
                    </div>

                    <div class="p-6">
                        @if($item->serials->where('status', 0)->count() > 0)
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                            @foreach($item->serials->where('status', 0) as $index => $sn)
                            <div class="p-2.5 bg-slate-50 dark:bg-dark-border/20 border border-slate-100 dark:border-dark-border rounded-xl flex items-center justify-center text-center hover:border-primary-300 dark:hover:border-primary-500/50 transition-all cursor-default group/sn shadow-sm">
                                <span class="text-[10px] font-black text-slate-700 dark:text-slate-300 uppercase tracking-tighter">{{ $sn->serial_number }}</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                            <svg class="w-12 h-12 mb-2 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            <span class="text-[10px] font-black uppercase tracking-widest italic">No Available Serial Numbers</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- VARIATIONS SECTION -->
                @if($item->item_group === 'Box' && count($variants) > 0)
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
                    <div class="bg-slate-50/50 dark:bg-white/5 px-6 py-3 border-b border-slate-100 dark:border-dark-border">
                        <h3 class="text-xs font-black text-slate-600 dark:text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            Variations / Packaging
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/20 dark:bg-slate-800/20 border-b border-slate-50 dark:border-dark-border">
                                    <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Variant Name</th>
                                    <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">SKU/Barcode</th>
                                    <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Selling Price ({{ $currencySymbol }})</th>
                                    <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Curr. Stock</th>
                                    <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Serials</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                                @foreach($variants as $variant)
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-3">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 uppercase tracking-tight">{{ str_replace($item->item_name.'-', '', $variant->item_name) ?: $variant->item_name }}</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex flex-col">
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">{{ $variant->sku ?: '---' }}</span>
                                            <span class="text-[8px] font-bold text-slate-400 font-mono">{{ $variant->custom_barcode ?: '---' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400 tabular-nums">{{ format_currency($variant->sales_price) }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <span class="text-[11px] font-black text-slate-700 dark:text-slate-200 tabular-nums">{{ $variant->stock }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                         @if($item->is_serialized)
                                            <span class="text-[9px] font-black text-primary-500 bg-primary-50 dark:bg-primary-500/10 px-2 py-0.5 rounded-full border border-primary-100/50 uppercase italic tracking-widest">
                                               {{ $variant->serials_count }} SLN
                                            </span>
                                         @else
                                            <span class="text-slate-200">---</span>
                                         @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
