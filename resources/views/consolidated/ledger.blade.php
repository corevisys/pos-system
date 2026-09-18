<x-app-layout title="Consolidated Ledger">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
        <div>
            <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                <span>Consolidated Ledger / P&L</span>
                <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                    Owner
                </span>
            </h1>
            <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">
                {{ now()->format('l, F j, Y') }} &mdash; across {{ $totals['store_count'] }} store{{ $totals['store_count'] !== 1 ? 's' : '' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="store_id" onchange="this.form.submit()" class="input text-sm">
                    <option value="all" @selected($storeFilter === null)>All stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeFilter === (int) $store->id)>{{ $store->store_name }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('multi-store-dashboard') }}" class="btn-secondary text-sm">Network Dashboard</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <div class="text-[10px] font-bold uppercase tracking-widest text-text-muted">Total Debit</div>
            <div class="text-lg font-black text-text-primary dark:text-dark-text"><x-money value="{{ $totals['total_debit'] }}" /></div>
        </div>
        <div class="card p-4">
            <div class="text-[10px] font-bold uppercase tracking-widest text-text-muted">Total Credit</div>
            <div class="text-lg font-black text-text-primary dark:text-dark-text"><x-money value="{{ $totals['total_credit'] }}" /></div>
        </div>
        <div class="card p-4">
            <div class="text-[10px] font-bold uppercase tracking-widest text-text-muted">Net (Debit − Credit)</div>
            <div class="text-lg font-black {{ $totals['net'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}"><x-money value="{{ $totals['net'] }}" /></div>
        </div>
    </div>

    <x-table>
        <thead>
            <tr>
                <th>Store</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['store']->store_name }}</td>
                    <td class="text-right"><x-money value="{{ $row['total_debit'] }}" /></td>
                    <td class="text-right"><x-money value="{{ $row['total_credit'] }}" /></td>
                    <td class="text-right font-semibold {{ $row['net'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}"><x-money value="{{ $row['net'] }}" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-text-muted">No ledger activity.</td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-app-layout>
