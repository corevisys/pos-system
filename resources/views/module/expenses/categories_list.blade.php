<x-app-layout title="Expense Categories List">
    <div class="grid grid-cols-1 gap-3">
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-1">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Expense Categories</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('expenses.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-widest">Expenses</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Category List</span>
                </div>
                <p class="text-[10px] text-text-muted font-bold uppercase tracking-widest mt-1 px-1">View/Search Expense Categories</p>
            </div>

            <a href="{{ route('expenses.categories.add') }}" class="btn-primary w-full md:w-auto !bg-danger hover:!bg-danger-hover">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                New Category
            </a>
        </div>

        <!-- TABLE SECTION -->
        <div class="card overflow-hidden p-0">
            <!-- Table Controls (search only; no decorative export/per-page/bulk controls) -->
            <div class="px-4 py-3 border-b border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    {{ $categories->total() }} categor{{ $categories->total() == 1 ? 'y' : 'ies' }}
                </p>
                <form method="GET" action="{{ route('expenses.categories') }}" class="relative group">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                    <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-background dark:bg-dark-bg border-b border-border dark:border-dark-border">
                        <tr>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Category Name</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Code</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest">Description</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-dark-border">
                        @forelse($categories as $category)
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-4 py-1.5">
                                    <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $category->category_name }}</span>
                                </td>
                                <td class="px-4 py-1.5">
                                    <span class="text-[10px] font-black text-text-muted font-mono tracking-wider italic">{{ $category->category_code ?? '---' }}</span>
                                </td>
                                <td class="px-4 py-1.5 text-[10px] font-medium text-text-muted italic max-w-[220px] truncate">{{ \Illuminate\Support\Str::limit($category->description, 30) ?? '---' }}</td>
                                <td class="px-4 py-1.5 text-center">
                                    @if($category->status == 1)
                                        <span class="px-2 py-0.5 bg-success/10 text-success rounded text-[9px] font-bold uppercase tracking-widest border border-success/20">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 bg-danger/10 text-danger rounded text-[9px] font-bold uppercase tracking-widest border border-danger/20">Inactive</span>
                                    @endif
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
                                            <x-dropdown-link :href="route('expenses.categories.edit', $category->id)">Edit</x-dropdown-link>
                                            <form action="{{ route('expenses.categories.delete', $category->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-danger hover:bg-danger-light dark:hover:bg-danger/10 transition-colors uppercase tracking-widest">Delete</button>
                                            </form>
                                        </x-slot>
                                    </x-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center">
                                    <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic leading-none">No categories found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-4 py-3 border-t border-border dark:border-dark-border bg-background/40 dark:bg-white/5 flex flex-wrap justify-between items-center gap-3">
                <p class="text-[8px] font-black text-text-muted uppercase tracking-widest italic leading-none">
                    Showing {{ $categories->firstItem() ?? 0 }} to {{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }} entries
                </p>
                <div class="flex gap-1">
                    {{ $categories->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
