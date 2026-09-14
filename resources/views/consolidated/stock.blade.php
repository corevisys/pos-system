<x-app-layout title="Consolidated Stock">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
        <div>
            <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text flex items-center gap-2">
                <span>Consolidated Stock</span>
                <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 text-[10px] font-black uppercase rounded-lg tracking-widest">
                    Owner
                </span>
            </h1>
            <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-0.5">
                Warehouse stock across {{ $totals['store_count'] }} store{{ $totals['store_count'] !== 1 ? 's' : '' }}
            </p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="store_id" onchange="this.form.submit()" class="input text-sm">
                <option value="all" @selected($storeFilter === null)>All stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected($storeFilter === (int) $store->id)>{{ $store->store_name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
            <div class="text-[10px] font-bold uppercase tracking-widest text-text-muted">Total Available Qty</div>
            <div class="text-lg font-black text-text-primary dark:text-dark-text">{{ format_quantity($totals['total_qty']) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-[10px] font-bold uppercase tracking-widest text-text-muted">Stores</div>
            <div class="text-lg font-black text-text-primary dark:text-dark-text">{{ $totals['store_count'] }}</div>
        </div>
    </div>

    <x-table>
        <thead>
            <tr>
                <th>Store</th>
                <th class="text-right">Distinct Items</th>
                <th class="text-right">Warehouses</th>
                <th class="text-right">Available Qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['store']->store_name }}</td>
                    <td class="text-right">{{ $row['item_count'] }}</td>
                    <td class="text-right">{{ $row['warehouse_count'] }}</td>
                    <td class="text-right font-semibold">{{ format_quantity($row['total_qty']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-text-muted">No stock records.</td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-app-layout>
