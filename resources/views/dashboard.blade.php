<x-app-layout title="Dashboard">
    <div x-data="{
        trendPeriod: 'last7',
        isChartLoading: false,
        trendChart: null,
        paymentChart: null,
        async switchTrend(period) {
            this.trendPeriod = period;
            this.isChartLoading = true;
            try {
                const res = await fetch(`{{ route('dashboard.data') }}?period=${period}`);
                const data = await res.json();
                if (this.trendChart) {
                    this.trendChart.data.labels = data.labels;
                    this.trendChart.data.datasets[0].data = data.values;
                    this.trendChart.update('active');
                }
            } catch (e) {
                window.showError && window.showError('Failed to load chart data.');
                console.error(e);
            } finally {
                this.isChartLoading = false;
            }
        }
    }">

        {{-- =====================================================================
             HEADER — Title + Quick Action Buttons
        ====================================================================== --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                    <span>Dashboard Overview</span>
                    <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                        Live
                    </span>
                </h1>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">
                    {{ now()->format('l, F j, Y') }}
                </p>
            </div>
            {{-- Quick Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                @if(auth()->user()->hasPermission('sales_add') || auth()->user()->isSuperAdmin())
                <a href="{{ route('sales.pos') }}" class="btn-primary"
                   @click="if (!($event.metaKey || $event.ctrlKey || $event.altKey || $event.shiftKey)) window.setButtonLoading($event.currentTarget, 'Loading...')">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    New Sale / POS
                </a>
                @endif
                @if(auth()->user()->hasPermission('purchase_add') || auth()->user()->isSuperAdmin())
                <a href="{{ route('purchase.new') }}" class="btn-secondary"
                   @click="if (!($event.metaKey || $event.ctrlKey || $event.altKey || $event.shiftKey)) window.setButtonLoading($event.currentTarget, 'Loading...')">
                    <svg class="w-3.5 h-3.5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    New Purchase
                </a>
                @endif
                @if(auth()->user()->hasPermission('items_add') || auth()->user()->isSuperAdmin())
                <a href="{{ route('items.add') }}" class="btn-secondary"
                   @click="if (!($event.metaKey || $event.ctrlKey || $event.altKey || $event.shiftKey)) window.setButtonLoading($event.currentTarget, 'Loading...')">
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Add Item
                </a>
                @endif
            </div>
        </div>

        {{-- =====================================================================
             KPI CARDS ROW (5 cards)
        ====================================================================== --}}
        @php
            $todaySalesFooter = '<p class="text-xs font-bold text-text-muted mt-2 flex items-center justify-between"><span>Orders today:</span><span class="text-text-primary dark:text-dark-text font-extrabold">' . e($stats['today_orders']) . '</span></p>';
            $todayOrdersFooter = '<p class="text-xs font-bold text-text-muted mt-2">Invoices issued today</p>';
            $todayProfitFooter = '<p class="text-xs font-bold text-text-muted mt-2">Net profit (after expenses)</p>';
            $outstandingFooter = '<p class="text-xs font-bold text-text-muted mt-2">Unpaid & partial invoices</p>';

            if ($stats['month_change_percent'] === null) {
                $monthFooter = '<p class="text-xs font-bold text-text-muted mt-2">vs last month: N/A</p>';
            } elseif ($stats['month_change_percent'] >= 0) {
                $monthFooter = '<p class="text-xs font-bold text-text-muted mt-2 flex items-center gap-1"><span class="text-emerald-500">▲ +' . e($stats['month_change_percent']) . '%</span><span>vs last month</span></p>';
            } else {
                $monthFooter = '<p class="text-xs font-bold text-text-muted mt-2 flex items-center gap-1"><span class="text-rose-500">▼ ' . e($stats['month_change_percent']) . '%</span><span>vs last month</span></p>';
            }
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">

            {{-- 1. Today's Sales --}}
            <x-stat-card
                label="Today's Sales"
                :value="format_currency($stats['today_sales'])"
                iconBg="bg-blue-50 dark:bg-blue-500/10 text-blue-600"
                icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                :footer="$todaySalesFooter"
            />

            {{-- 2. Today's Orders --}}
            <x-stat-card
                label="Today's Orders"
                :value="$stats['today_orders']"
                iconBg="bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600"
                icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'
                :footer="$todayOrdersFooter"
            />

            {{-- 3. Today's Profit --}}
            <x-stat-card
                label="Today's Profit"
                :value="format_currency($stats['today_net_profit'])"
                :iconBg="$stats['today_net_profit'] >= 0 ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-500'"
                icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>'
                :footer="$todayProfitFooter"
            />

            {{-- 4. Total Outstanding Due --}}
            <x-stat-card
                label="Outstanding Due"
                :value="format_currency($stats['total_outstanding_due'])"
                iconBg="bg-rose-50 dark:bg-rose-500/10 text-rose-600"
                icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
                :footer="$outstandingFooter"
            />

            {{-- 5. This Month's Sales --}}
            <x-stat-card
                label="This Month"
                :value="format_currency($stats['this_month_sales'])"
                iconBg="bg-purple-50 dark:bg-purple-500/10 text-purple-600"
                icon='<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
                :footer="$monthFooter"
            />
        </div>

        {{-- =====================================================================
             ROW 2: Sales Trend Chart (L) + Payment Method Chart (R)
        ====================================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

            {{-- Sales Trend Line Chart --}}
            <x-card :hover="true" class="lg:col-span-2">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                    <div>
                        <h3 class="text-sm font-black text-text-primary dark:text-dark-text flex items-center gap-2">
                            <span class="w-2 h-4 bg-primary-500 rounded-full"></span>
                            Sales Trend
                        </h3>
                        <p class="text-[9px] text-text-muted font-bold uppercase tracking-widest ml-4">Daily revenue over selected period</p>
                    </div>
                    {{-- Period Toggle --}}
                    <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-dark-border">
                        <button @click="switchTrend('last7')"
                                :class="trendPeriod === 'last7' ? 'bg-white dark:bg-dark-card shadow-sm text-slate-800 dark:text-white' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                                class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">
                            7 Days
                        </button>
                        <button @click="switchTrend('last30')"
                                :class="trendPeriod === 'last30' ? 'bg-white dark:bg-dark-card shadow-sm text-slate-800 dark:text-white' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                                class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all">
                            30 Days
                        </button>
                    </div>
                </div>
                <div class="h-64 relative">
                    <div x-show="isChartLoading" x-cloak
                         class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-dark-card/70 z-10 rounded-xl">
                        <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </x-card>

            {{-- Payment Method Doughnut --}}
            <x-card :hover="true" class="flex flex-col">
                <h3 class="text-sm font-black text-text-primary dark:text-dark-text flex items-center gap-2 mb-4">
                    <span class="w-2 h-4 bg-emerald-500 rounded-full"></span>
                    Payment Methods
                    <span class="text-[9px] font-bold text-text-muted normal-case">This month</span>
                </h3>
                @if(count($paymentMethods) > 0)
                    <div class="h-44 mb-4 relative">
                        <canvas id="paymentMethodChart"></canvas>
                    </div>
                    <div class="space-y-1.5 mt-auto">
                        @foreach($paymentMethods as $pm)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ ['#6366f1','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4'][($loop->index % 6)] }}"></span>
                                <span class="text-[10px] font-bold text-text-secondary dark:text-dark-text">{{ $pm['method'] }}</span>
                            </div>
                            <span class="text-[10px] font-black text-text-primary dark:text-dark-text tabular-nums">
                                {{ format_currency($pm['amount']) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center py-8">
                        <div class="w-12 h-12 bg-background dark:bg-dark-card rounded-2xl flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </div>
                        <p class="text-[11px] font-bold text-text-muted">No payments this month</p>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- =====================================================================
             ROW 3: Top Products (L) + Low Stock Alert (R)
        ====================================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

            {{-- Top Selling Products --}}
            <div class="lg:col-span-2">
                <x-table title="Top Selling Products">
                    <x-slot name="actions">
                        <a href="{{ route('items.list') }}" class="text-[10px] font-black uppercase tracking-widest text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-500/10 px-3 py-1.5 rounded-lg transition-colors">
                            View All
                        </a>
                    </x-slot>
                    @if(count($topProducts) > 0)
                        <x-slot name="thead">
                            <tr>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Product</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Qty Sold</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Revenue</th>
                            </tr>
                        </x-slot>
                        @foreach($topProducts as $i => $product)
                        <tr class="hover:bg-background dark:hover:bg-dark-card transition-colors">
                            <td class="px-4 py-3">
                                <span class="w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center
                                    {{ $i === 0 ? 'bg-yellow-100 text-yellow-600' : ($i === 1 ? 'bg-background text-text-secondary dark:bg-dark-border' : ($i === 2 ? 'bg-orange-100 text-orange-600' : 'bg-background text-text-muted dark:bg-dark-card')) }}">
                                    {{ $i + 1 }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-[11px] font-bold text-text-primary dark:text-dark-text leading-tight">{{ $product->item_name }}</p>
                                <p class="text-[9px] font-mono text-text-muted mt-0.5">{{ $product->item_code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-[11px] font-black tabular-nums text-indigo-600 dark:text-indigo-400">{{ format_quantity($product->total_qty) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-[11px] font-black tabular-nums text-text-primary dark:text-dark-text">{{ format_currency($product->total_revenue) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4">
                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                    <div class="w-14 h-14 bg-background dark:bg-dark-card rounded-2xl flex items-center justify-center mb-3">
                                        <svg class="w-7 h-7 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                    <p class="text-[11px] font-bold text-text-muted">No sales recorded this month yet</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </x-table>
            </div>

            {{-- Low Stock Alert --}}
            <x-card class="bg-slate-900 dark:bg-black text-white relative overflow-hidden border-slate-800">
                <h3 class="text-xs font-black uppercase tracking-widest mb-4 flex items-center gap-2.5 text-white">
                    @if($lowStockItems->count() > 0)
                        <span class="w-2.5 h-2.5 bg-rose-500 rounded-full animate-ping"></span>
                    @else
                        <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
                    @endif
                    Low Stock Alert
                    @if($lowStockItems->count() > 0)
                        <span class="ml-auto px-2 py-0.5 bg-rose-500/20 text-rose-400 text-[9px] rounded-lg border border-rose-500/30">
                            {{ $lowStockItems->count() }} items
                        </span>
                    @endif
                </h3>

                @if($lowStockItems->count() > 0)
                    <div class="space-y-2.5">
                        @foreach($lowStockItems as $item)
                        <div class="p-2.5 bg-white/5 rounded-xl border border-white/5 hover:bg-white/10 transition-all">
                            <div class="flex justify-between items-start mb-1.5">
                                <div class="flex-1 min-w-0 pr-2">
                                    <p class="text-[11px] font-bold truncate">{{ $item->item_name }}</p>
                                    <p class="text-[9px] text-text-muted font-mono">{{ $item->sku ?: $item->item_code }}</p>
                                </div>
                                <span class="text-[10px] font-black text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded-lg whitespace-nowrap flex-shrink-0">
                                    {{ format_quantity($item->stock) }} left
                                </span>
                            </div>
                            @php
                                $limit = max(1, (int)$item->alert_qty);
                                $pct   = min(100, ($item->stock / $limit) * 100);
                            @endphp
                            <div class="w-full h-1 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $item->stock <= 0 ? 'bg-rose-600' : 'bg-rose-500' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <a href="{{ route('purchase.new') }}" class="btn-primary w-full mt-4">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Restock Now
                    </a>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <div class="w-12 h-12 bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-3 border border-emerald-500/20">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-[11px] font-bold text-emerald-400">All stock levels are healthy 🎉</p>
                        <p class="text-[9px] text-text-muted mt-1">No items below alert threshold</p>
                    </div>
                @endif

                {{-- Decorative glow --}}
                <div class="absolute -right-16 -bottom-16 w-48 h-48 bg-primary-600/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-16 -top-16 w-48 h-48 bg-rose-600/5 rounded-full blur-3xl pointer-events-none"></div>
            </x-card>
        </div>

        {{-- =====================================================================
             ROW 4: Recent Transactions (L) + Customers with Due (R)
        ====================================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

            {{-- Recent Transactions --}}
            <div class="lg:col-span-2">
                <x-table title="Recent Transactions">
                    <x-slot name="actions">
                        <a href="{{ route('sales.list') }}" class="text-[10px] font-black uppercase tracking-widest text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-500/10 px-3 py-1.5 rounded-lg transition-colors">
                            View All
                        </a>
                    </x-slot>
                    @if($recentTransactions->count() > 0)
                        <x-slot name="thead">
                            <tr>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Invoice</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Amount</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                                <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                            </tr>
                        </x-slot>
                        @foreach($recentTransactions as $txn)
                        <tr class="hover:bg-background dark:hover:bg-dark-card transition-colors">
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black italic text-text-secondary dark:text-text-muted">{{ $txn->sales_code }}</span>
                                <p class="text-[9px] text-text-muted mt-0.5">{{ \Carbon\Carbon::parse($txn->sales_date)->diffForHumans() }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-background dark:bg-dark-border text-text-secondary dark:text-dark-text font-black flex items-center justify-center text-[10px]">
                                        {{ strtoupper(substr($txn->customer ? $txn->customer->customer_name : 'W', 0, 1)) }}
                                    </div>
                                    <span class="text-[11px] font-bold text-text-primary dark:text-dark-text leading-tight">
                                        {{ $txn->customer ? $txn->customer->customer_name : 'Walk-in Customer' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-[11px] font-black tabular-nums text-text-primary dark:text-dark-text">
                                    {{ format_currency($txn->grand_total) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $ps = $txn->payment_status ?? 'Unpaid';
                                    $badgeClass = match($ps) {
                                        'Paid'    => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                                        'Partial' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400',
                                        default   => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $badgeClass }}">
                                    {{ $ps }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('sales.show', $txn->id) }}"
                                   class="p-1.5 text-text-muted hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-xl transition-all inline-block"
                                   title="View Invoice">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5">
                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                    <p class="text-[11px] font-bold text-text-muted">No transactions found</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </x-table>
            </div>

            {{-- Customers with Outstanding Dues --}}
            <x-card class="p-0 overflow-hidden">
                <div class="px-5 py-4 border-b border-border dark:border-dark-border flex items-center justify-between gap-3">
                    <h3 class="text-xs font-black uppercase tracking-widest text-text-primary dark:text-dark-text flex items-center gap-2">
                        <span class="w-2 h-4 bg-rose-500 rounded-full"></span>
                        Customers with Due
                    </h3>
                    @if(count($customersWithDue) > 0)
                    <a href="{{ route('reports.sales_summary') }}" class="text-[10px] font-black uppercase tracking-widest text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-500/10 px-3 py-1.5 rounded-lg transition-colors">
                        Report
                    </a>
                    @endif
                </div>
                @if(count($customersWithDue) > 0)
                    <div class="divide-y divide-border-light dark:divide-dark-border">
                        @foreach($customersWithDue as $customer)
                        <div class="px-5 py-3 hover:bg-background dark:hover:bg-dark-card transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-500 font-black flex items-center justify-center text-[10px] flex-shrink-0">
                                        {{ strtoupper(substr($customer->customer_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-bold text-text-primary dark:text-dark-text truncate">{{ $customer->customer_name }}</p>
                                        <p class="text-[9px] text-text-muted font-mono">{{ $customer->mobile }}</p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-2">
                                    <p class="text-[11px] font-black text-rose-600 dark:text-rose-400 tabular-nums">
                                        {{ format_currency($customer->total_due) }}
                                    </p>
                                    <p class="text-[9px] text-text-muted">{{ $customer->orders_count }} order(s)</p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center px-4">
                        <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/10 rounded-2xl flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">All customers are settled!</p>
                        <p class="text-[9px] text-text-muted mt-1">No outstanding dues found</p>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- =====================================================================
             ROW 5: Purchases vs Sales — This Month
        ====================================================================== --}}
        <x-card>
            <h3 class="text-xs font-black uppercase tracking-widest text-text-primary dark:text-dark-text flex items-center gap-2 mb-5">
                <span class="w-2 h-4 bg-purple-500 rounded-full"></span>
                Purchases vs Sales
                <span class="text-text-muted font-bold normal-case">— {{ now()->format('F Y') }}</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Sales bar --}}
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded bg-primary-500"></span>
                            <span class="text-[11px] font-black text-text-primary dark:text-dark-text">Total Sales</span>
                        </div>
                        <span class="text-sm font-black text-primary-600 dark:text-primary-400 tabular-nums">
                            {{ format_currency($stats['this_month_sales']) }}
                        </span>
                    </div>
                    @php
                        $maxVal = max($stats['this_month_sales'], $stats['this_month_purchases'], 1);
                        $salesPct = round(($stats['this_month_sales'] / $maxVal) * 100);
                    @endphp
                    <div class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-primary-500 rounded-full transition-all duration-700" style="width: {{ $salesPct }}%"></div>
                    </div>
                </div>
                {{-- Purchases bar --}}
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded bg-orange-400"></span>
                            <span class="text-[11px] font-black text-text-primary dark:text-dark-text">Total Purchases</span>
                        </div>
                        <span class="text-sm font-black text-orange-500 tabular-nums">
                            {{ format_currency($stats['this_month_purchases']) }}
                        </span>
                    </div>
                    @php
                        $purchasePct = round(($stats['this_month_purchases'] / $maxVal) * 100);
                    @endphp
                    <div class="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-400 rounded-full transition-all duration-700" style="width: {{ $purchasePct }}%"></div>
                    </div>
                </div>
            </div>
            {{-- Summary pill --}}
            @php
                $margin = $stats['this_month_sales'] - $stats['this_month_purchases'];
            @endphp
            <div class="mt-5 pt-4 border-t border-border-light dark:border-dark-border flex items-center justify-between">
                <span class="text-[10px] font-bold text-text-muted uppercase tracking-widest">Net Margin (Sales − Purchases)</span>
                <span class="text-sm font-black tabular-nums {{ $margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                    {{ ($margin >= 0 ? '+' : '') }}{{ format_currency($margin) }}
                </span>
            </div>
        </x-card>

    </div>{{-- /main container --}}

    {{-- =====================================================================
         CHART INITIALIZATION
    ====================================================================== --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const isDark = () => document.documentElement.classList.contains('dark');
        const gridColor = () => isDark() ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        const tickColor = () => isDark() ? '#94a3b8' : '#94a3b8';

        const storeDecimals = {{ (int)(store_settings()->decimals ?? 2) }};
        const storeSymbol = @json($currencySymbol ?? '');
        const storePlacementAfter = @json((store_settings()->currency_placement ?? 'before') === 'after');

        function formatMoney(amount) {
            const formatted = Number(amount || 0).toLocaleString(undefined, {
                minimumFractionDigits: storeDecimals,
                maximumFractionDigits: storeDecimals
            });
            return storePlacementAfter ? `${formatted} ${storeSymbol}` : `${storeSymbol} ${formatted}`;
        }

        // ---- 1. SALES TREND LINE CHART ----
        const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const trendGrad = trendCtx.createLinearGradient(0, 0, 0, 260);
        trendGrad.addColorStop(0, 'rgba(99,102,241,0.18)');
        trendGrad.addColorStop(1, 'rgba(99,102,241,0)');

        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartData['labels']) !!},
                datasets: [{
                    label: 'Sales (' + storeSymbol + ')',
                    data: {!! json_encode($chartData['values']) !!},
                    borderColor: '#6366f1',
                    borderWidth: 3,
                    fill: true,
                    backgroundColor: trendGrad,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 10, weight: 'bold', family: 'Plus Jakarta Sans' },
                        bodyFont:  { size: 13, weight: 'bold', family: 'Plus Jakarta Sans' },
                        padding: 14,
                        borderRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => formatMoney(ctx.raw)
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { display: false }, ticks: { display: false } },
                    x: { grid: { display: false }, ticks: { color: tickColor(), font: { size: 10, weight: 'bold' } } }
                }
            }
        });

        // Expose to Alpine for chart period toggle
        document.addEventListener('alpine:initialized', () => {
            const comp = Alpine.$data(document.querySelector('[x-data]'));
            if (comp) comp.trendChart = trendChart;
        });

        // ---- 2. PAYMENT METHOD DOUGHNUT ----
        @if(count($paymentMethods) > 0)
        const payCtx = document.getElementById('paymentMethodChart').getContext('2d');
        const payColors = ['#6366f1','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4'];
        new Chart(payCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_column($paymentMethods, 'method')) !!},
                datasets: [{
                    data:  {!! json_encode(array_column($paymentMethods, 'amount')) !!},
                    backgroundColor: payColors.slice(0, {{ count($paymentMethods) }}),
                    borderWidth: 2,
                    borderColor: isDark() ? '#1e293b' : '#fff',
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 10, weight: 'bold', family: 'Plus Jakarta Sans' },
                        bodyFont:  { size: 12, weight: 'bold', family: 'Plus Jakarta Sans' },
                        padding: 12,
                        borderRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label: (ctx) => ' ' + formatMoney(ctx.raw)
                        }
                    }
                }
            }
        });
        @endif
    });
    </script>
</x-app-layout>
