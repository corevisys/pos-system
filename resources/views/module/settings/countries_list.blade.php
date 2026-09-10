<x-app-layout title="Countries List">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Countries List <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">View/Search Countries</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Countries List</span>
                </div>
            </div>

            <a href="{{ route('settings.countries.add') }}" class="btn-primary w-full md:w-auto !bg-danger hover:!bg-danger-hover">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                Add Country
            </a>
        </div>

        <!-- STATISTICS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="card p-4">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest mb-1">Total Countries</p>
                <h3 class="text-2xl font-black text-text-primary dark:text-white tabular-nums">{{ $totalCountries }}</h3>
            </div>
            <div class="card p-4">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest mb-1 text-success">Active</p>
                <h3 class="text-2xl font-black text-success tabular-nums">{{ $activeCountries }}</h3>
            </div>
            <div class="card p-4">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest mb-1 text-danger">Inactive</p>
                <h3 class="text-2xl font-black text-danger tabular-nums">{{ $inactiveCountries }}</h3>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <form action="{{ route('settings.countries') }}" method="GET" class="card p-3 mb-4 flex flex-wrap justify-between items-center gap-3">
            <div class="relative group">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search countries..." class="input-base !w-56 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary !py-1.5 !px-3 text-[10px] font-black uppercase tracking-widest">Search</button>
                @if(request('search'))
                    <a href="{{ route('settings.countries') }}" class="text-[9px] font-black uppercase tracking-wider text-danger hover:underline">Clear</a>
                @endif
            </div>
        </form>

        <!-- TABLE SECTION -->
        <x-card padding="p-0" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2.5 w-12 text-center text-[9px] font-black text-text-muted uppercase tracking-widest">#</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Country Name</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse($countries as $index => $country)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-4 py-1.5 text-center text-[10px] font-bold text-text-muted tabular-nums">
                                    {{ $countries->firstItem() + $index }}
                                </td>
                                <td class="px-4 py-1.5">
                                    <span class="text-[11px] font-bold text-text-primary dark:text-dark-text tracking-tight transition-transform group-hover:translate-x-1 inline-block">{{ $country->country }}</span>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    <span class="{{ $country->status == 1 ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20' }} px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border">
                                        {{ $country->status == 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    <x-dropdown align="right" width="40">
                                        <x-slot name="trigger">
                                            <button type="button" class="btn-primary px-3 py-1 !text-[9px] uppercase tracking-widest flex items-center gap-1.5 leading-none">
                                                Action
                                                <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <a href="{{ route('settings.countries.edit', $country->id) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-text-primary dark:text-dark-text hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors uppercase tracking-widest">
                                                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                Edit
                                            </a>
                                            <form action="{{ route('settings.countries.delete', $country->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this country?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors uppercase tracking-widest border-t border-border-light dark:border-dark-border mt-1 pt-1">
                                                    <svg class="w-3 h-3 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No countries found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $countries->firstItem() ?? 0 }} to {{ $countries->lastItem() ?? 0 }} of {{ $countries->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $countries->links() }}
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
