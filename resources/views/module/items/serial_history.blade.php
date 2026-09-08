<x-app-layout title="Serial History">
    <div>
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                        Serial History
                    </h1>
                </div>
                <p class="text-[10px] text-text-muted font-medium uppercase tracking-widest flex items-center gap-2">
                    Serial Number Lifecycle Lookup
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    Read-only
                </p>
            </div>
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1 text-text-muted hover:text-primary transition-colors">Home</a>
                <span class="text-slate-300 mx-1">></span>
                <span class="text-primary">Serial History</span>
            </div>
        </div>

        <!-- SEARCH CARD -->
        <div class="card rounded-xl p-5 mb-5">
            <form method="GET" action="{{ route('items.serial-history') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input type="text" name="q" value="{{ $query }}"
                           placeholder="Enter a serial number (e.g. MS26A8dg) to see its full lifecycle"
                           class="w-full input-base !pl-10" autofocus>
                </div>
                <button type="submit" class="btn-primary !px-8 !py-3 !text-[11px] font-black uppercase tracking-widest shrink-0">
                    Look Up Serial
                </button>
            </form>
        </div>

        @if($query !== '')
            @if($history)
                <div class="space-y-4">
                    <!-- HEADER CARD -->
                    <div class="card rounded-xl p-6">
                        <div class="flex flex-col md:flex-row justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black text-text-muted uppercase tracking-widest">Serial Number</p>
                                <h2 class="text-2xl font-black tracking-tight font-mono uppercase text-text-primary dark:text-dark-text">{{ $history['serial_number'] }}</h2>
                            </div>
                            @if($history['item'])
                                <div class="text-left md:text-right">
                                    <p class="text-[10px] font-black text-text-muted uppercase tracking-widest">Item</p>
                                    <p class="text-base font-black text-text-primary dark:text-dark-text">{{ $history['item']['name'] }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 font-mono">{{ $history['item']['code'] }}</p>
                                </div>
                            @endif
                            <div>
                                <p class="text-[10px] font-black text-text-muted uppercase tracking-widest">Current Status</p>
                                @if($history['status'] === 1)
                                    <span class="inline-flex items-center gap-1 mt-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-100 dark:bg-rose-900/20 dark:text-rose-400">Sold</span>
                                @else
                                    <span class="inline-flex items-center gap-1 mt-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400">Available</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- TIMELINE -->
                    <div class="card rounded-xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-border-light dark:border-dark-border bg-background/50 dark:bg-slate-800/20">
                            <h3 class="text-[11px] font-black text-text-primary dark:text-dark-text uppercase tracking-widest">Lifecycle Timeline</h3>
                        </div>
                        <div class="divide-y divide-border-light dark:divide-dark-border">
                            <!-- ENTRY -->
                            <div class="p-5 flex gap-4">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-1">In</div>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-black text-text-primary uppercase tracking-widest">Entered Stock</span>
                                        <span class="text-[8px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded">{{ $history['entry']['source'] }}</span>
                                    </div>
                                    <p class="text-[10px] text-text-muted mt-1">
                                        @if($history['entry']['label'])
                                            @if($history['entry']['url'])
                                                <a href="{{ $history['entry']['url'] }}" class="text-primary hover:underline font-bold">{{ $history['entry']['label'] }}</a>
                                            @else
                                                <span class="font-bold">{{ $history['entry']['label'] }}</span>
                                            @endif
                                        @else
                                            Registered to stock
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <!-- SALE -->
                            @if($history['sale'])
                                <div class="p-5 flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 mt-1">Out</div>
                                    <div class="flex-1">
                                        <span class="text-[10px] font-black text-text-primary uppercase tracking-widest">Sold</span>
                                        <p class="text-[10px] text-text-muted mt-1">
                                            <a href="{{ $history['sale']['url'] }}" class="text-primary hover:underline font-bold">{{ $history['sale']['sales_code'] }}</a>
                                            <span class="mx-1">•</span>{{ $history['sale']['sales_date'] }}
                                            @if($history['sale']['customer'])
                                                <span class="mx-1">•</span>{{ $history['sale']['customer'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <!-- RETURNS -->
                            @forelse($history['returns'] as $ret)
                                <div class="p-5 flex gap-4">
                                    <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 mt-1">Rt</div>
                                    <div class="flex-1">
                                        <span class="text-[10px] font-black text-text-primary uppercase tracking-widest">Returned</span>
                                        <p class="text-[10px] text-text-muted mt-1">
                                            <a href="{{ $ret['url'] }}" class="text-amber-600 dark:text-amber-400 hover:underline font-bold">{{ $ret['return_code'] }}</a>
                                            <span class="mx-1">•</span>{{ $ret['return_date'] }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if(!$history['sale'])
                                    <div class="p-5 flex gap-4">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center shrink-0 mt-1">OK</div>
                                        <div class="flex-1">
                                            <span class="text-[10px] font-black text-text-primary uppercase tracking-widest">Currently In Stock</span>
                                            <p class="text-[10px] text-text-muted mt-1">No sale or return history for this serial — it is available at the warehouse.</p>
                                        </div>
                                    </div>
                                @endif
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <!-- NOT FOUND -->
                <div class="card rounded-xl p-10 text-center">
                    <h3 class="text-[12px] font-black text-text-secondary uppercase tracking-widest">No serial found</h3>
                    <p class="text-[10px] text-text-muted mt-1 font-medium">
                        "{{ $query }}" does not match any serial in the current store.
                    </p>
                </div>
            @endif
        @else
            <!-- EMPTY STATE -->
            <div class="card rounded-xl p-10 text-center">
                <h3 class="text-[12px] font-black text-text-secondary uppercase tracking-widest">Enter a Serial Number</h3>
                <p class="text-[10px] text-text-muted mt-1 font-medium">
                    Search a serial to see which purchase brought it in, when it sold, which invoice it was on, and whether it was returned.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
