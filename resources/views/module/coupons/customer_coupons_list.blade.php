<x-app-layout title="Customer Coupons List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Customer Coupons Ledger</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Customer Coupons</span>
                </div>
            </div>

            <a href="{{ route('coupons.customer.create') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                Create Customer Coupon
            </a>
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form action="{{ route('coupons.customer.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Show</label>
                        <select name="per_page" onchange="this.form.submit()" class="input-base !w-auto !py-1.5 !px-2.5 !text-[11px] !rounded-lg">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Entries</label>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Status</label>
                        <select name="status" class="input-base !w-auto !py-1.5 !px-2.5 !text-[11px] !rounded-lg">
                            <option value="">All Statuses</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive / Used</option>
                        </select>
                    </div>

                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search code, occasion, or customer..." class="input-base !w-64 !py-1.5 !pl-8 !pr-3 !text-[11px] !rounded-lg">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary !py-1.5 !px-3 !text-[10px]">
                        Filter
                    </button>
                    <a href="{{ route('coupons.customer.list') }}" class="btn-secondary !py-1.5 !px-3 !text-[10px]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Customer</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Occasion</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Code</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Expiry</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-right">Value</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Type</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                    <th class="px-5 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center w-28">Action</th>
                </tr>
            </x-slot>

            @forelse($coupons as $coupon)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $coupon->customer ? $coupon->customer->customer_name : 'N/A' }}</span>
                            @if($coupon->customer && ($coupon->customer->code || $coupon->customer->customer_code))
                                <span class="text-[9px] font-medium text-text-muted">({{ $coupon->customer->code ?? $coupon->customer->customer_code }})</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="text-[11px] font-black text-text-secondary">{{ $coupon->name }}</span>
                        @if($coupon->description)
                            <div class="text-[9px] text-text-muted line-clamp-1 mt-0.5">{{ $coupon->description }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="text-[11px] font-black text-primary dark:text-primary-400 font-mono tracking-wider">{{ $coupon->code }}</span>
                    </td>
                    <td class="px-5 py-3 text-[11px] font-bold text-text-secondary text-center">
                        {{ $coupon->expire_date ? \Carbon\Carbon::parse($coupon->expire_date)->format('d-m-Y') : 'No Expiry' }}
                    </td>
                    <td class="px-5 py-3 text-[11px] font-black text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                        @if($coupon->type == 'Fixed')<x-money value="{{ $coupon->value }}" />@else{{ number_format($coupon->value, 2) }}%@endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <x-badge color="neutral">{{ $coupon->type }}</x-badge>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($coupon->status == 1)
                            <x-badge color="emerald">Active</x-badge>
                        @else
                            <x-badge color="rose">Used / Inactive</x-badge>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <x-dropdown align="right" width="48" contentClasses="py-1 bg-white dark:bg-dark-card">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary !py-1 !px-2.5 !text-[9px] uppercase tracking-widest flex items-center gap-1.5">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('coupons.customer.edit', $coupon->id)">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        <span>Edit</span>
                                    </div>
                                </x-dropdown-link>

                                <form action="{{ route('coupons.customer.delete', $coupon->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this customer coupon voucher?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 focus:outline-none transition duration-150 ease-in-out border-t border-border-light dark:border-dark-border">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            <span>Delete</span>
                                        </div>
                                    </button>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-5 py-8 text-center text-text-muted text-xs font-bold">No customer coupons found.</td>
                </tr>
            @endforelse
        </x-table>

        <!-- PAGINATION -->
        @if($coupons->hasPages())
            <div class="mt-4">
                {{ $coupons->appends(request()->all())->links() }}
            </div>
        @endif
    </div>
</x-app-layout>