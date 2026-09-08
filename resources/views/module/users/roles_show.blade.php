<x-app-layout title="Role Details">
    <div>
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div class="flex items-center gap-4">
                <!-- Icon/Badge -->
                <div class="relative group">
                    <div class="w-16 h-16 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 flex items-center justify-center font-black relative z-10 border-2 border-white dark:border-dark-card shadow-md">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <div class="absolute -bottom-1 -right-1 z-20">
                        <span class="flex h-5 w-5 items-center justify-center rounded-lg bg-indigo-500 text-white shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </span>
                    </div>
                </div>

                <div>
                    <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white leading-tight mb-0.5">{{ $role->role_name }}</h1>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 rounded-lg text-[8px] font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20">
                            {{ count($role->permissions->permissions ?? []) }} Permissions
                        </span>
                        <span class="text-slate-400 text-[10px] font-bold flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            {{ count($role->users ?? []) }} Staff
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                <a href="{{ route('users.roles') }}" class="flex-1 md:flex-none px-4 py-2 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center justify-center gap-2 italic">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Roles
                </a>
                @can('update', $role)
                    <a href="{{ route('users.roles.edit', $role->id) }}" class="flex-1 md:flex-none px-4 py-2 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-md shadow-emerald-200/50 flex items-center justify-center gap-2 italic">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        Edit Role
                    </a>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <!-- SIDEBAR -->
            <div class="lg:col-span-3 space-y-4">
                <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm">
                    <h3 class="text-[9px] font-black uppercase text-slate-400 tracking-widest mb-4 italic">Role Metadata</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest mb-0.5 opacity-70">Internal Identifier</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $role->role_name }}</p>
                        </div>
                        <div>
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest mb-0.5 opacity-70">Description</p>
                            <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-medium">{{ $role->description ?? 'No detailed description.' }}</p>
                        </div>
                        <div>
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest mb-1 italic">Role Status</p>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-{{ $role->status == 1 ? 'emerald' : 'slate' }}-50 text-{{ $role->status == 1 ? 'emerald' : 'slate' }}-600 rounded-lg text-[8px] font-black uppercase tracking-widest">
                                <span class="w-1 h-1 rounded-full bg-{{ $role->status == 1 ? 'emerald' : 'slate' }}-500"></span>
                                {{ $role->status == 1 ? 'Active' : 'Draft' }}
                            </span>
                        </div>
                        <hr class="border-slate-50 dark:border-dark-border opacity-50">
                        <div>
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest mb-0.5 opacity-70">Created On</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $role->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-dark-card rounded-2xl p-5 border border-slate-100 dark:border-dark-border shadow-sm">
                    <h3 class="text-[9px] font-black uppercase text-slate-400 tracking-widest mb-4 italic">Staff Assigned ({{ count($role->users ?? []) }})</h3>
                    <div class="space-y-3 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                        @forelse($role->users as $u)
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-[8px] font-black text-primary-600">
                                    {{ substr($u->first_name, 0, 1) }}{{ substr($u->last_name, 0, 1) }}
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-[10px] font-bold text-slate-700 dark:text-slate-200 leading-tight truncate">{{ $u->full_name }}</p>
                                    <p class="text-[9px] text-slate-400 font-medium truncate">{{ $u->username }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-[9px] font-bold text-slate-400 italic">No users found.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- PERMISSIONS -->
            <div class="lg:col-span-9">
                <div class="bg-white dark:bg-dark-card rounded-2xl p-8 border border-slate-100 dark:border-dark-border shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                         <h3 class="text-sm font-black tracking-tight italic uppercase text-slate-800 dark:text-white">Authorized Permissions</h3>
                    </div>

                    @if($role->permissions && count($role->permissions->permissions ?? []) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($role->permissions->permissions as $perm)
                                <div class="flex items-center gap-3 p-3 bg-slate-50/50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-dark-border group hover:bg-white dark:hover:bg-dark-card transition-all duration-300">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-500 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-slate-700 dark:text-slate-200 uppercase tracking-widest leading-none">{{ $perm }}</p>
                                        <p class="text-[8px] font-bold text-slate-400 italic mt-0.5">Access Enabled</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-slate-50 dark:bg-slate-800/30 rounded-2xl p-12 text-center border-2 border-dashed border-slate-200 dark:border-dark-border">
                            <h4 class="text-xs font-black text-slate-400 mb-2 uppercase italic">No permissions defined</h4>
                            <p class="text-[10px] font-medium text-slate-400 mb-6">This role has no system permissions assigned yet.</p>
                            @can('update', $role)
                                <a href="{{ route('users.roles.edit', $role->id) }}" class="inline-flex px-6 py-2 bg-emerald-500 text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all italic shadow-md shadow-emerald-200/50">Setup Permissions</a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
