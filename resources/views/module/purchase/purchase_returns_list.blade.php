<x-app-layout title="Purchase Returns List">
    <div x-data="purchaseReturnsListPage()">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                    </div>
                    Purchase Returns
                </h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-xs flex items-center gap-1 font-bold uppercase tracking-wider">
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('purchase.list') }}" class="hover:text-primary-600 transition-colors text-xs font-bold uppercase tracking-wider">
                        Purchases
                    </a>
                    <svg class="w-2.5 h-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-400 text-xs font-bold uppercase tracking-wider">Returns</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('purchase.list') }}" class="px-4 py-2.5 bg-primary-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-primary-700 transition-all shadow-lg shadow-primary-200/50 dark:shadow-none flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Create Return from Purchase
                </a>
            </div>
        </div>

        <!-- STATISTICS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-stat-card 
                label="Return Invoices" 
                :value="number_format($stats['total_invoices'])" 
                icon-bg="bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Total Returned" 
                :value="format_currency($stats['total_amount'])" 
                icon-bg="bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Refunds Received" 
                :value="format_currency($stats['total_paid'])" 
                icon-bg="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card 
                label="Payable Adjusted" 
                :value="format_currency($stats['total_due'])" 
                icon-bg="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400">
                <x-slot:icon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <!-- FILTERS PANEL -->
        <form action="{{ route('purchase.returns') }}" method="GET" id="returnFilterForm" class="bg-white dark:bg-dark-card p-4 rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Filter -->
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Return Code / Purchase / Supplier..." class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 pl-9 pr-3 text-xs font-semibold text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-rose-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <select name="warehouse_id" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-rose-500 cursor-pointer">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <input type="date" name="from_date" value="{{ request('from_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-rose-500">
                </div>

                <!-- Date To & Reset -->
                <div class="flex items-center gap-2">
                    <input type="date" name="to_date" value="{{ request('to_date') }}" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-3 text-xs font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-rose-500">
                    @if(request()->hasAny(['search', 'warehouse_id', 'from_date', 'to_date']))
                        <a href="{{ route('purchase.returns') }}" class="p-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded-xl transition-colors shrink-0" title="Reset Filters">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    @endif
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="w-full px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md shadow-rose-200 dark:shadow-none">
                        Filter Returns
                    </button>
                </div>
            </div>
        </form>

        <!-- TABLE SECTION -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            <!-- Toolbar -->
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-dark-border flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Show</span>
                    <select name="per_page" form="returnFilterForm" onchange="document.getElementById('returnFilterForm').submit()" class="bg-slate-50 dark:bg-slate-800 border-none rounded-lg py-1 px-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-1 focus:ring-rose-500">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <span class="text-xs font-bold text-slate-400">entries</span>
                </div>

                <!-- Export Controls -->
                <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 p-1 rounded-xl border border-slate-100 dark:border-dark-border">
                    <button type="button" @click="copyTableData()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Copy
                    </button>
                    <button type="button" @click="exportCsv()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Excel
                    </button>
                    <button type="button" onclick="window.print()" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all shadow-sm">
                        Print
                    </button>
                </div>
            </div>

            <!-- Table Content -->
            <div class="overflow-x-auto">
                <table class="w-full text-left" id="returnTable">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/40 border-b border-slate-100 dark:border-dark-border text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <tr>
                            <th class="px-4 py-3.5">Date</th>
                            <th class="px-4 py-3.5">Return Code</th>
                            <th class="px-4 py-3.5">Purchase Code</th>
                            <th class="px-4 py-3.5">Reference</th>
                            <th class="px-4 py-3.5">Supplier</th>
                            <th class="px-4 py-3.5">Warehouse</th>
                            <th class="px-4 py-3.5 text-right">Grand Total</th>
                            <th class="px-4 py-3.5 text-right">Refund</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-4 py-3.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border text-xs">
                        @forelse($returns as $ret)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 font-bold whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($ret->return_date)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 font-mono font-black text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                    <a href="{{ route('purchase.return.invoice', $ret->id) }}" class="hover:underline">{{ $ret->return_code }}</a>
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                    @if($ret->purchase)
                                        <a href="{{ route('purchase.invoice', $ret->purchase->id) }}" class="hover:underline">{{ $ret->purchase->purchase_code }}</a>
                                    @else
                                        ---
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-semibold">
                                    {{ $ret->reference_no ?: '---' }}
                                </td>
                                <td class="px-4 py-3 font-black text-slate-800 dark:text-slate-200">
                                    {{ $ret->supplier->supplier_name ?? '---' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 font-semibold">
                                    {{ $ret->warehouse->warehouse_name ?? '---' }}
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-rose-600 whitespace-nowrap">
                                    {{ format_currency($ret->grand_total) }}
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums text-emerald-600 whitespace-nowrap">
                                    {{ format_currency($ret->paid_amount) }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-badge variant="danger">Returned</x-badge>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-dropdown align="right" width="44">
                                        <x-slot:trigger>
                                            <button type="button" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1.5 shadow-sm">
                                                Action
                                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <x-slot:content>
                                            <a href="{{ route('purchase.return.invoice', $ret->id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Debit Note View
                                            </a>

                                            <button type="button" @click="confirmDeleteReturn({{ $ret->id }}, '{{ $ret->return_code }}')" class="w-full text-left flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors border-t border-slate-100 dark:border-dark-border">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Delete Return
                                            </button>
                                        </x-slot:content>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                                        <p class="text-sm font-bold text-slate-600 dark:text-slate-400">No purchase returns recorded</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($returns->isNotEmpty())
                        <tfoot class="bg-slate-50/60 dark:bg-slate-800/60 border-t border-slate-100 dark:border-dark-border text-xs font-black">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-right uppercase tracking-wider text-slate-400">Totals:</td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-600">{{ format_currency($stats['total_amount']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ format_currency($stats['total_paid']) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Pagination -->
            @if($returns->hasPages())
                <div class="px-5 py-4 border-t border-slate-100 dark:border-dark-border flex flex-wrap justify-between items-center gap-3">
                    <p class="text-xs text-slate-400 font-semibold">
                        Showing {{ $returns->firstItem() }} to {{ $returns->lastItem() }} of {{ $returns->total() }} entries
                    </p>
                    <div>
                        {{ $returns->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>

    <script>
        function purchaseReturnsListPage() {
            return {
                async confirmDeleteReturn(id, code) {
                    if (!confirm('Are you sure you want to delete purchase return ' + code + '? This will restore inventory stock and reverse refund/ledger entries.')) {
                        return;
                    }

                    try {
                        const url = '{{ url('purchase/return-delete') }}/' + id;
                        const res = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to delete purchase return.');
                        }
                    } catch (e) {
                        alert('An unexpected error occurred while deleting.');
                    }
                },

                copyTableData() {
                    const table = document.getElementById('returnTable');
                    let text = '';
                    for (let row of table.rows) {
                        let rowData = [];
                        for (let cell of row.cells) {
                            rowData.push(cell.innerText.trim().replace(/\s+/g, ' '));
                        }
                        text += rowData.join('\t') + '\n';
                    }
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Purchase returns copied to clipboard!');
                    });
                },

                exportCsv() {
                    const table = document.getElementById('returnTable');
                    let csv = [];
                    for (let row of table.rows) {
                        let rowData = [];
                        for (let cell of row.cells) {
                            rowData.push('"' + cell.innerText.trim().replace(/"/g, '""').replace(/\s+/g, ' ') + '"');
                        }
                        csv.push(rowData.join(','));
                    }
                    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'purchase_returns_' + new Date().toISOString().slice(0, 10) + '.csv';
                    a.click();
                }
            };
        }
    </script>
</x-app-layout>