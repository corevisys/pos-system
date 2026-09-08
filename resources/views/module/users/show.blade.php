<x-app-layout title="User Details">
    <div>
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div class="flex items-center gap-4">
                <!-- Profile Image -->
                <div class="relative group">
                    @if($user->profile_picture)
                        <img src="{{ asset('storage/' . $user->profile_picture) }}" class="w-16 h-16 rounded-xl object-cover border-2 border-white dark:border-dark-card shadow-md">
                    @else
                        <div class="w-16 h-16 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-400 flex items-center justify-center font-black text-xl uppercase border-2 border-white dark:border-dark-card shadow-md">
                            {{ collect(explode(' ', $user->first_name . ' ' . $user->last_name))->map(fn($n)=>substr($n,0,1))->join('') }}
                        </div>
                    @endif
                    <div class="absolute -bottom-1 -right-1">
                        <span class="flex h-5 w-5 items-center justify-center rounded-lg {{ $user->status == 1 ? 'bg-emerald-500' : 'bg-slate-400' }} text-white shadow-sm">
                            @if($user->status == 1)
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            @endif
                        </span>
                    </div>
                </div>

                <div>
                    <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-white leading-tight mb-0.5">{{ $user->full_name }}</h1>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 rounded-lg text-[8px] font-black uppercase tracking-widest border border-primary-100 dark:border-primary-500/20">
                            {{ $user->role_name }}
                        </span>
                        <span class="text-slate-400 text-[10px] font-bold flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            {{ $user->email }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                <a href="{{ route('users.list') }}" class="flex-1 md:flex-none px-4 py-2 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center justify-center gap-2 italic">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to List
                </a>
                @can('update', $user)
                    <a href="{{ route('users.edit', $user->id) }}" class="flex-1 md:flex-none px-4 py-2 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-md shadow-emerald-200/50 flex items-center justify-center gap-2 italic">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        Edit User
                    </a>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 relative z-10">
            <!-- SIDEBAR: MEDIA & STATUS -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Media Card -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm p-6 text-center relative overflow-hidden group">
                    <div class="absolute -top-10 -left-10 w-32 h-32 bg-primary-500/5 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>
                    
                    <div class="relative shrink-0 mb-4 inline-block">
                        @if($user->profile_picture)
                            <img src="{{ asset('storage/' . $user->profile_picture) }}" class="w-24 h-24 rounded-3xl object-cover shadow-2xl border-4 border-white dark:border-slate-800 mx-auto transition-transform group-hover:scale-105">
                        @else
                            <div class="w-24 h-24 rounded-3xl bg-slate-50 dark:bg-slate-800 text-slate-400 flex items-center justify-center font-black text-3xl uppercase border-4 border-white dark:border-slate-800 mx-auto shadow-inner">
                                {{ collect(explode(' ', $user->first_name . ' ' . $user->last_name))->map(fn($n)=>substr($n,0,1))->join('') }}
                            </div>
                        @endif
                        <span class="absolute -bottom-2 -right-2 px-3 py-1 rounded-xl text-[8px] font-black uppercase tracking-widest shadow-lg {{ $user->status == 1 ? 'bg-emerald-500 text-white' : 'bg-slate-400 text-white' }}">
                            {{ $user->status == 1 ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <h2 class="text-xl font-black tracking-tight text-slate-800 dark:text-white leading-tight mt-2">{{ $user->full_name }}</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1 italic">{{ $user->role_name }}</p>

                    <div class="mt-8 pt-6 border-t border-slate-50 dark:border-dark-border space-y-4">
                        <div class="flex items-center gap-3 text-left bg-slate-50 dark:bg-white/5 p-3 rounded-2xl group/item hover:bg-primary-50 transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-white dark:bg-dark-card text-primary-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="truncate">
                                <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest leading-none mb-1">Email ID</p>
                                <p class="text-[10px] font-bold text-slate-600 dark:text-slate-300 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-left bg-slate-50 dark:bg-white/5 p-3 rounded-2xl group/item hover:bg-emerald-50 transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-white dark:bg-dark-card text-emerald-500 flex items-center justify-center shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <div>
                                <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest leading-none mb-1">Mobile No</p>
                                <p class="text-[10px] font-bold text-slate-600 dark:text-slate-300">{{ $user->mobile ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Media -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm p-6 overflow-hidden relative group">
                    <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-4 flex items-center gap-2 italic">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Official Photo
                    </h3>
                    <div class="relative bg-slate-50 dark:bg-white/5 rounded-2xl overflow-hidden aspect-square flex items-center justify-center group-hover:bg-slate-100 transition-colors border-2 border-dashed border-slate-100 dark:border-dark-border">
                        @if($user->photo)
                            <img src="{{ asset('storage/' . $user->photo) }}" class="w-full h-full object-cover">
                        @else
                            <div class="text-slate-300 dark:text-slate-600 text-[10px] font-black uppercase tracking-widest p-4 text-center leading-relaxed">No Official Photo Uploaded</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT: SECTIONS -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- SECTION 1: PERSONAL & IDENTITY -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm p-8 relative overflow-hidden group">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 blur-3xl rounded-full -mr-16 -mt-16 transition-transform group-hover:translate-y-8"></div>
                    
                    <h3 class="text-[11px] font-black uppercase text-slate-800 dark:text-white tracking-[0.3em] mb-8 pb-3 border-b border-slate-50 dark:border-dark-border flex items-center justify-between italic">
                        Personal Information
                        <span class="text-[8px] font-bold text-indigo-500 opacity-60">Identity Profile v1.0</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-8 gap-x-12">
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Username</p>
                            <p class="text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest">{{ $user->username }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Legacy Name</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->name }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">First Name</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->first_name ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Last Name</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->last_name ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Gender</p>
                            <div class="flex items-center gap-1.5">
                                <span class="w-1 h-1 rounded-full bg-indigo-500"></span>
                                <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 uppercase tracking-tighter">{{ $user->gender ?? 'Unspecified' }}</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Date of Birth</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 font-mono italic">{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M, Y') : 'N/A' }}</p>
                        </div>

                        <div class="md:col-span-2 space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Department / Member Of</p>
                            <p class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-tighter">{{ $user->member_of ?? 'General Staff' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Email Verified</p>
                            @if($user->email_verified_at)
                                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest">Verified <br> <small class="text-[7px] font-bold opacity-60 text-slate-400 italic">{{\Carbon\Carbon::parse($user->email_verified_at)->format('Y-m-d')}}</small></span>
                            @else
                                <span class="text-[9px] font-black text-rose-500 uppercase tracking-widest italic">Pending Verification</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: GEOGRAPHIC DATA -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm p-8 group">
                    <h3 class="text-[11px] font-black uppercase text-slate-800 dark:text-white tracking-[0.3em] mb-8 pb-3 border-b border-slate-50 dark:border-dark-border flex items-center justify-between italic">
                        Geographic Data
                         <svg class="w-4 h-4 text-emerald-500 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Country</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->country ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">State / Division</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->state ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">City</p>
                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ $user->city ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70">Postcode</p>
                            <p class="text-[11px] font-black text-slate-700 dark:text-slate-200 font-mono">{{ $user->postcode ?? 'N/A' }}</p>
                        </div>
                        <div class="md:col-span-2 lg:col-span-4 space-y-1 bg-slate-50 dark:bg-white/5 p-4 rounded-2xl border border-slate-100 dark:border-dark-border italic">
                            <p class="text-[8px] font-black uppercase text-slate-400 tracking-widest opacity-70 mb-1">Full Billing/Shipping Address</p>
                            <p class="text-[10px] font-bold text-slate-600 dark:text-slate-300 leading-relaxed">{{ $user->address ?? 'No physical address records found.' }}</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: SYSTEM AUDIT & METADATA -->
                <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white shadow-2xl relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>
                    <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-primary-500/10 rounded-full blur-3xl"></div>

                    <h3 class="text-[10px] font-black uppercase tracking-[0.4em] mb-10 opacity-60 text-center flex items-center justify-center gap-4 italic">
                        <span class="w-8 h-px bg-white/20"></span>
                        Security Audit Trail
                        <span class="w-8 h-px bg-white/20"></span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12 relative z-10">
                        <div class="space-y-2 group/item">
                            <p class="text-[8px] font-black uppercase tracking-widest opacity-40 group-hover/item:opacity-70 transition-opacity">Registration Trace</p>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-emerald-400 font-mono italic">{{ $user->created_date ?? 'N/A' }}</p>
                                <p class="text-[9px] font-bold opacity-60 uppercase">{{ $user->created_time ?? '' }}</p>
                            </div>
                        </div>
                        <div class="space-y-2 group/item">
                            <p class="text-[8px] font-black uppercase tracking-widest opacity-40">System Core IDs</p>
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px] font-bold">
                                    <span class="opacity-50">Store:</span>
                                    <span class="text-primary-400 font-mono italic">{{ $user->store->store_name ?? ('#' . ($user->store_id ?? '0')) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[10px] font-bold">
                                    <span class="opacity-50">Role:</span>
                                    <span class="text-primary-400 font-mono italic">{{ $user->role->role_name ?? ('#' . ($user->role_id ?? '0')) }}</span>
                                </div>
                            </div>
                        </div>
                         <div class="space-y-2 group/item">
                            <p class="text-[8px] font-black uppercase tracking-widest opacity-40">Network Signature</p>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-amber-400 font-mono italic tracking-widest">{{ $user->system_ip ?? '0.0.0.0' }}</p>
                                <p class="text-[8px] font-black uppercase opacity-60 truncate">{{ $user->system_name ?? 'UNKNOWN HOST' }}</p>
                            </div>
                        </div>
                        <div class="space-y-2 group/item">
                            <p class="text-[8px] font-black uppercase tracking-widest opacity-40">Account Ancestry</p>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase">Created By:</span>
                                    <span class="text-[10px] font-black text-rose-500 font-mono italic">UID:{{ $user->created_by ?? 'SYS' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase">Creator ID:</span>
                                    <span class="text-[10px] font-black text-amber-500 font-mono italic">UID:{{ $user->creater_id ?? 'SYS' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2 group/item">
                            <p class="text-[8px] font-black uppercase tracking-widest opacity-40">Last State Change</p>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-indigo-400 italic">{{ $user->updated_at->format('d M, Y H:i') }}</p>
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-bold uppercase opacity-50">Modifier:</span>
                                    <span class="text-[10px] font-black text-emerald-400 font-mono">UID:{{ $user->updater_id ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center p-4 bg-white/5 rounded-3xl border border-white/10 italic">
                             <div class="text-center">
                                <p class="text-[8px] font-black uppercase tracking-widest opacity-40 mb-1">System Status</p>
                                <p class="text-[10px] font-black text-emerald-500 tracking-tighter">SECURED & SYNCED</p>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
        </div>
    </div>
</x-app-layout>
