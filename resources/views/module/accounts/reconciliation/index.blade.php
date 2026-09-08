<x-app-layout title="Cash Drawer Reconciliation">
    <div x-data="{
        selectedIds: [],
        selectAll: false,
        isSubmitting: false,
        copySuccess: false,
        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedIds = Array.from(document.querySelectorAll('.row-checkbox')).map(el => el.value);
            } else {
                this.selectedIds = [];
            }
        },
        copyTable() {
            let rows = Array.from(document.querySelectorAll('table tbody tr')).filter(r => r.querySelectorAll('td').length > 1);
            if (rows.length === 0) return;
            let text = 'Code\tDate\tAccount\tWarehouse\tOpener\tStarting Float\tExpected\tCounted\tVariance\tStatus\n';
            rows.forEach(r => {
                let cells = r.querySelectorAll('td');
                if (cells.length >= 10) {
                    let code = cells[1].innerText.trim().replace(/\n/g, ' ');
                    let acc = cells[2].innerText.trim().replace(/\n/g, ' ');
                    let opener = cells[3].innerText.trim().replace(/\n/g, ' ');
                    let sFloat = cells[4].innerText.trim().replace(/\n/g, ' ');
                    let exp = cells[5].innerText.trim().replace(/\n/g, ' ');
                    let counted = cells[6].innerText.trim().replace(/\n/g, ' ');
                    let variance = cells[7].innerText.trim().replace(/\n/g, ' ');
                    let status = cells[8].innerText.trim().replace(/\n/g, ' ');
                    text += `${code}\t${acc}\t${opener}\t${sFloat}\t${exp}\t${counted}\t${variance}\t${status}\n`;
                }
            });
            navigator.clipboard.writeText(text).then(() => {
                this.copySuccess = true;
                setTimeout(() => this.copySuccess = false, 2000);
            });
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Daily Cash Drawer Reconciliation</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('accounts.list') }}" class="hover:text-primary transition-colors text-[10px]">Accounts</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary dark:text-dark-text text-[10px] font-bold">Cash Drawer Reconciliation</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">Two-Step Morning Open & Evening Close Reconciliation Flow</p>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto">
                @if(auth()->user()->hasPermission('cash_reconciliation_report'))
                <a href="{{ route('reports.cash_reconciliation') }}" class="btn-secondary px-3.5 !py-2 !text-[10px] uppercase tracking-widest flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Reconciliation Report
                </a>
                @endif

                @if(auth()->user()->hasPermission('cash_reconciliation_add'))
                <a href="{{ route('accounts.cash-reconciliation.open-form') }}" class="btn-primary w-full md:w-auto !bg-emerald-600 hover:!bg-emerald-700 !px-4 !py-2 !text-[10px] uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    Open Cash Drawer
                </a>
                @endif
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="card p-3 mb-4">
            <form method="GET" action="{{ route('accounts.cash-reconciliation.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2.5 items-end">
                @if(request('per_page'))
                    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                @endif
                <div>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">Reconciliation Date</label>
                    <input type="date" name="reconciliation_date" value="{{ request('reconciliation_date') }}" class="input-base !py-1.5 !px-2.5 !text-[11px] !font-bold">
                </div>
                <div>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">Warehouse</label>
                    <select name="warehouse_id" class="input-base !py-1.5 !px-2.5 !text-[11px] !font-bold">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">Cash Account</label>
                    <select name="account_id" class="input-base !py-1.5 !px-2.5 !text-[11px] !font-bold">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>{{ $acc->account_name }} ({{ $acc->account_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-text-muted uppercase tracking-widest block mb-1">Status</label>
                    <select name="status" class="input-base !py-1.5 !px-2.5 !text-[11px] !font-bold">
                        <option value="">All Statuses</option>
                        <option value="Open" {{ request('status') == 'Open' ? 'selected' : '' }}>Open (Awaiting Close)</option>
                        <option value="Reconciled" {{ request('status') == 'Reconciled' ? 'selected' : '' }}>Reconciled (Log Only)</option>
                        <option value="Adjusted" {{ request('status') == 'Adjusted' ? 'selected' : '' }}>Adjusted (Ledger Posted)</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1 !py-2 !text-[10px] uppercase tracking-widest">
                        Filter
                    </button>
                    <a href="{{ route('accounts.cash-reconciliation.index') }}" class="btn-secondary px-3 !py-2 !text-[10px] uppercase tracking-widest text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="card overflow-hidden p-0">

            <!-- Table Controls & Export Buttons -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-[8px] font-black text-text-muted uppercase tracking-widest">Show</label>
                    <select onchange="const url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.set('page', '1'); window.location.href = url.toString();" class="input-base !w-auto !py-0.5 !px-1.5 !text-[10px] !rounded-lg !cursor-pointer">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                    <label class="text-[8px] font-black text-text-muted uppercase tracking-widest leading-none">Entries</label>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex bg-background dark:bg-slate-800 rounded-xl p-1 border border-border dark:border-dark-border">
                        <button @click="copyTable()" type="button" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all" x-text="copySuccess ? 'Copied!' : 'Copy'">Copy</button>
                        <a href="{{ route('accounts.cash-reconciliation.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">Excel</a>
                        <a href="{{ route('accounts.cash-reconciliation.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest text-primary hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all flex items-center">PDF</a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-3 py-3 w-10 text-center">
                                <input type="checkbox" x-model="selectAll" @change="toggleSelectAll()" class="w-3 h-3 rounded border-border text-primary focus:ring-primary">
                            </th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted">Code / Date</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted">Warehouse & Account</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted">Opener / Closer</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-right">Starting Float</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-right">Expected</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-right">Counted</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-right">Variance</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-center">Status</th>
                            <th class="px-4 py-3 text-[9px] font-black uppercase tracking-widest text-text-muted text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border text-[11px] font-medium">
                        @forelse($reconciliations as $recon)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors {{ $recon->status === 'Open' ? 'bg-amber-50/30 dark:bg-amber-950/10' : '' }}">
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" value="{{ $recon->id }}" x-model="selectedIds" class="row-checkbox w-3 h-3 rounded border-border text-primary focus:ring-primary">
                            </td>
                            <td class="px-4 py-3 font-bold text-text-primary dark:text-dark-text">
                                <div>{{ $recon->reconciliation_code }}</div>
                                <div class="text-[9px] font-medium text-text-muted">{{ \Carbon\Carbon::parse($recon->reconciliation_date)->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-text-primary dark:text-dark-text">{{ $recon->account->account_name ?? 'N/A' }}</div>
                                <div class="text-[9px] text-text-muted">{{ $recon->warehouse->warehouse_name ?? 'Store Wide' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-text-primary dark:text-dark-text">{{ $recon->opener->name ?? $recon->user->name ?? 'User' }}</div>
                                <div class="text-[9px] text-text-muted">
                                    {{ $recon->opened_at ? $recon->opened_at->format('h:i A') : ($recon->created_at ? $recon->created_at->format('h:i A') : '') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-text-secondary dark:text-dark-text">
                                <div>{{ format_currency($recon->opening_balance) }}</div>
                                @if($recon->is_initial)
                                    <div class="text-[9px] font-bold text-blue-500">Initial Float</div>
                                @elseif($recon->opening_variance != 0)
                                    <div class="text-[9px] font-bold text-amber-600">
                                        {{ $recon->opening_variance > 0 ? '+' : '' }}{{ format_currency($recon->opening_variance) }} Float Adj
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-text-primary dark:text-dark-text">
                                @if($recon->status === 'Open')
                                    <span class="text-text-muted font-normal italic text-[10px]">Pending Close</span>
                                @else
                                    {{ format_currency($recon->expected_closing_balance) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-black text-text-primary dark:text-white">
                                @if($recon->status === 'Open')
                                    <span class="text-text-muted font-normal italic text-[10px]">&mdash;</span>
                                @else
                                    {{ format_currency($recon->counted_amount) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-black">
                                @if($recon->status === 'Open')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        In Progress
                                    </span>
                                @elseif($recon->variance == 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Balanced ({{ $currencySymbol ?? '' }}0.00)
                                    </span>
                                @elseif($recon->variance > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        +{{ format_currency($recon->variance) }} Over
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                        -{{ format_currency(abs($recon->variance)) }} Short
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($recon->status === 'Open')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Open
                                    </span>
                                @elseif($recon->status === 'Adjusted')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                        Adjusted
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        Reconciled
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if($recon->status === 'Open' && (auth()->id() === $recon->opened_by || auth()->user()->isSuperAdmin()))
                                        <a href="{{ route('accounts.cash-reconciliation.close-form', $recon->id) }}" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1 shadow-sm">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            Close
                                        </a>
                                    @endif

                                    @if($recon->status === 'Reconciled' && (auth()->id() === $recon->opened_by || auth()->id() === $recon->closed_by || auth()->user()->isSuperAdmin()) && auth()->user()->hasPermission('cash_reconciliation_add'))
                                        <a href="{{ route('accounts.cash-reconciliation.edit', $recon->id) }}" class="p-1.5 text-text-muted hover:text-primary dark:hover:text-primary transition-colors" title="Edit Count">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                    @endif

                                    <a href="{{ route('accounts.cash-reconciliation.show', $recon->id) }}" class="p-1.5 text-text-muted hover:text-primary dark:hover:text-primary transition-colors" title="View Details / Slip">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>

                                    @if(auth()->user()->hasPermission('cash_reconciliation_delete'))
                                    <form method="POST" action="{{ route('accounts.cash-reconciliation.delete', $recon->id) }}" onsubmit="return confirm('Are you sure you want to delete this reconciliation record? Any ledger adjustment will be reversed.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-danger hover:text-rose-700 transition-colors" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-text-muted font-bold">
                                No cash reconciliation records found for the selected criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($reconciliations->hasPages())
            <div class="px-4 py-3 border-t border-border dark:border-dark-border">
                {{ $reconciliations->links() }}
            </div>
            @endif
        </div>

        <!-- FLOATING BULK ACTION BAR -->
        <div x-show="selectedIds.length > 0"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700 text-xs">
            <span class="font-black">
                <span x-text="selectedIds.length"></span> item(s) selected
            </span>
            <div class="h-4 w-[1px] bg-slate-700"></div>

            @if(auth()->user()->hasPermission('cash_reconciliation_delete'))
            <form method="POST" action="{{ route('accounts.cash-reconciliation.bulk-delete') }}" @submit="if(isSubmitting) { $event.preventDefault(); return false; } if(!confirm('Are you sure you want to delete the selected reconciliations? Solvency checks will be applied.')) { $event.preventDefault(); return false; } isSubmitting = true;">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" :disabled="isSubmitting" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-[10px] font-black uppercase tracking-wider transition-all disabled:opacity-50 flex items-center gap-1.5">
                    <span x-show="!isSubmitting">Delete Selected</span>
                    <span x-show="isSubmitting">Processing...</span>
                </button>
            </form>
            @endif

            <button type="button" @click="selectedIds = []; selectAll = false;" class="text-slate-400 hover:text-white text-[10px] font-bold uppercase transition-colors">
                Cancel
            </button>
        </div>
    </div>
</x-app-layout>
