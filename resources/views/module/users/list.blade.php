<x-app-layout title="Users List">
    <div x-data="{
        deleteAction: '',
        searchTerm: '{{ request('search') }}',

        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">User Management</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">User List</span>
                </div>
            </div>

            <a href="{{ route('users.create') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New User
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <x-stat-card label="Total Users" :value="$stats['total']"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>' />
            <x-stat-card label="Active" :value="$stats['active']"
                iconBg="bg-success-light text-success"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>' />
            <x-stat-card label="Inactive" :value="$stats['inactive']"
                iconBg="bg-warning-light text-warning"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form x-ref="filterForm" action="{{ route('users.list') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Show</label>
                        <select name="per_page" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Role</label>
                        <div class="w-40">
                            <x-searchable-select name="role" :options="$roles" labelKey="role_name" valueKey="id" emptyOption="All Roles" emptyValue="" placeholder="All Roles" :value="request('role')" change="submitFilters()" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Status</label>
                        <select name="status" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Copy</button>
                        <button type="button" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">Excel</button>
                        <button type="button" class="btn-ghost px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-lg">PDF</button>
                    </div>
                    <div class="relative group">
                        <input type="text" name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()" placeholder="Search..." class="input-base !w-40 !py-1.5 !pl-8 !pr-3 !text-[10px] !rounded-xl shadow-sm">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <x-table>
            <x-slot name="thead">
                <tr>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'first_name', 'order' => request('order') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1">
                            User Info
                            @if(request('sort') === 'first_name')
                                <svg class="w-2 h-2 {{ request('order') === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'role_name', 'order' => request('order') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1">
                            Role
                            @if(request('sort') === 'role_name')
                                <svg class="w-2 h-2 {{ request('order') === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest cursor-pointer hover:text-primary transition-colors">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => request('order') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1">
                            Date Joined
                            @if(request('sort') === 'created_at' || !request('sort'))
                                <svg class="w-2 h-2 {{ request('order') === 'asc' || !request('order') ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot>

            @forelse($users as $user)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-6 py-2.5">
                        <div class="flex items-center gap-3">
                            @if($user->profile_picture)
                                <img src="{{ asset('storage/' . $user->profile_picture) }}" class="w-7 h-7 rounded-lg object-cover">
                            @else
                                <div class="w-7 h-7 rounded-lg bg-primary-light text-primary flex items-center justify-center font-black text-[9px]">
                                    {{ collect(explode(' ', $user->first_name . ' ' . $user->last_name))->map(fn($n)=>substr($n,0,1))->join('') }}
                                </div>
                            @endif
                            <div>
                                <span class="text-[11px] font-bold text-text-primary dark:text-dark-text block leading-tight">{{ $user->full_name }}</span>
                                <span class="text-[9px] font-bold text-text-muted">{{ $user->email }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-2.5">
                        <x-badge color="primary">{{ $user->role_name ?? 'N/A' }}</x-badge>
                    </td>
                    <td class="px-6 py-2.5 text-center">
                        <form action="{{ route('users.toggle.status', $user->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest transition-all {{ $user->status == 1 ? 'bg-success-light text-success hover:bg-emerald-100 dark:bg-success/10 dark:text-success dark:hover:bg-success/20' : 'bg-background text-text-muted hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700' }}">
                                {{ $user->status == 1 ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-2.5">
                        <span class="text-[10px] font-bold text-text-muted italic">{{ $user->created_at->format('M d, Y') }}</span>
                    </td>
                    <td class="px-6 py-2.5 text-center">
                        <x-dropdown align="right" width="48" contentClasses="py-1">
                            <x-slot name="trigger">
                                <button type="button" class="btn-primary px-3 py-1.5 text-[10px] font-black uppercase tracking-widest">
                                    Action
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                @can('view', $user)
                                    <x-dropdown-link :href="route('users.show', $user->id)">View Details</x-dropdown-link>
                                @endcan
                                @can('update', $user)
                                    <x-dropdown-link :href="route('users.edit', $user->id)">Edit User</x-dropdown-link>
                                @endcan
                                @can('delete', $user)
                                    <button type="button"
                                        @click='deleteAction = "{{ route('users.delete', $user->id) }}"; $dispatch("open-modal", "confirm-delete-user")'
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-danger dark:text-danger hover:bg-danger-light dark:hover:bg-danger/10 focus:outline-none focus:bg-danger-light dark:focus:bg-danger/10 transition duration-150 ease-in-out">
                                        Delete
                                    </button>
                                @endcan
                            </x-slot>
                        </x-dropdown>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-[10px] font-bold text-text-muted italic uppercase tracking-widest">No users found</td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $users->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-user" maxWidth="sm">
            <form :action="deleteAction" method="POST">
                @csrf
                @method('DELETE')
                <div class="p-6 sm:p-8 text-center">
                    <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Revoke Access?</h2>
                    <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">This will permanently delete the user account. <br> This cannot be undone.</p>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-danger w-full">Yes, Delete User</button>
                        <button type="button" @click="$dispatch('close')" class="btn-secondary w-full">Cancel</button>
                    </div>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
