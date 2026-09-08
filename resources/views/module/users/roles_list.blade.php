<x-app-layout title="Roles List">
    <div x-data="{
        deleteId: null,
        searchTerm: '{{ request('search') }}',

        openDeleteModal(id) {
            this.deleteId = id;
            $dispatch('open-modal', 'confirm-delete-role');
        },

        submitFilters() {
            this.$refs.filterForm.submit();
        }
    }">

        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Role Management</h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-bold uppercase tracking-widest">Role List</span>
                </div>
            </div>

            <a href="{{ route('users.roles.create') }}" class="btn-primary w-full md:w-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                New Role
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <x-stat-card label="Total Roles" :value="$stats['total']"
                iconBg="bg-primary-light text-primary"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>' />
            <x-stat-card label="Active" :value="$stats['active']"
                iconBg="bg-success-light text-success"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>' />
            <x-stat-card label="Inactive" :value="$stats['inactive']"
                iconBg="bg-warning-light text-warning"
                icon='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' />
        </div>

        <!-- FILTER BAR -->
        <div class="card p-3 mb-4">
            <form x-ref="filterForm" action="{{ route('users.roles') }}" method="GET" class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">Show</label>
                        <select name="per_page" @change="submitFilters()" class="input-base !w-auto !py-1 !px-2 !text-[10px] !rounded-lg">
                            <option value="10" {{ request('per_page') == 10 || !request('per_page') ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
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
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'role_name', 'order' => request('order') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1">
                            Role Name
                            @if(request('sort') === 'role_name')
                                <svg class="w-2 h-2 {{ request('order') === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"></path></svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest">Description</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Users</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Permissions</th>
                    <th class="px-6 py-3 text-[9px] font-black text-text-muted uppercase tracking-widest text-center">Action</th>
                </tr>
            </x-slot>

            @forelse($roles as $role)
                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                    <td class="px-6 py-2.5">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-primary-light text-primary flex items-center justify-center font-bold">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <span class="text-[11px] font-bold text-text-primary dark:text-dark-text">{{ $role->role_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-2.5">
                        <span class="text-[10px] font-bold text-text-secondary dark:text-dark-text leading-tight">{{ $role->description ?? 'No description' }}</span>
                    </td>
                    <td class="px-6 py-2.5 text-center">
                        <span class="text-[10px] font-black text-text-muted uppercase tracking-tight">{{ $role->users_count }}</span>
                    </td>
                    <td class="px-6 py-2.5 text-center">
                        <x-badge color="neutral">
                            @if($role->permissions && $role->permissions->permissions)
                                {{ count($role->permissions->permissions) }} Perms
                            @else
                                0 Perms
                            @endif
                        </x-badge>
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
                                @can('view', $role)
                                    <x-dropdown-link :href="route('users.roles.show', $role->id)">View Details</x-dropdown-link>
                                @endcan
                                @can('update', $role)
                                    <x-dropdown-link :href="route('users.roles.edit', $role->id)">Edit Role</x-dropdown-link>
                                @endcan
                                @can('delete', $role)
                                    <button type="button"
                                        @click="openDeleteModal({{ $role->id }})"
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
                    <td colspan="5" class="px-6 py-10 text-center text-[10px] font-bold text-text-muted italic uppercase tracking-widest">No roles found</td>
                </tr>
            @endforelse
        </x-table>

        <!-- Footer / Pagination -->
        <div class="mt-4 flex flex-wrap justify-between items-center gap-4">
            <p class="text-[9px] font-black text-text-muted uppercase tracking-widest italic">
                Showing {{ $roles->firstItem() ?? 0 }} to {{ $roles->lastItem() ?? 0 }} of {{ $roles->total() }} entries
            </p>
            <div class="flex gap-1">
                {{ $roles->links() }}
            </div>
        </div>

        <!-- DELETE CONFIRMATION MODAL -->
        <x-modal name="confirm-delete-role" maxWidth="sm">
            <form :action="'{{ url('users/roles') }}/' + deleteId" method="POST">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="p-6 sm:p-8 text-center">
                    <div class="w-20 h-20 bg-danger-light text-danger rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <h2 class="text-xl font-black mb-2 text-text-primary dark:text-dark-text">Delete Role?</h2>
                    <p class="text-text-muted text-sm font-medium mb-8 leading-relaxed">This will remove the role from the system. <br> Users assigned to this role may need reassignment.</p>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-danger w-full">Yes, Delete Role</button>
                        <button type="button" @click="$dispatch('close')" class="btn-secondary w-full">Cancel</button>
                    </div>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
