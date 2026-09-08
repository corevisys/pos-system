<x-app-layout title="Edit User">
    <div>
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Edit User Profile <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">{{ $user->full_name }}</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('users.list') }}" class="hover:text-primary transition-colors text-[10px] font-bold uppercase tracking-wider">Users List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">Edit User</span>
                </div>
            </div>

            <a href="{{ route('users.list') }}" class="btn-secondary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Cancel
            </a>
        </div>

        <x-card class="relative overflow-hidden">
            <form action="{{ route('users.update', $user->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6 relative z-10">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- LEFT COLUMN: MAIN FORMS -->
                    <div class="lg:col-span-8 space-y-6">

                        <!-- SECTION 1: IDENTITY & ROLE -->
                        <x-card class="relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-primary-light rounded-full blur-3xl -mr-16 -mt-16"></div>
                            <h3 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em] mb-6 italic border-b border-border dark:border-dark-border pb-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                Identity & Access
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Full Name (System) <span class="text-danger">*</span></label>
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Role <span class="text-danger">*</span></label>
                                    <x-searchable-select name="role_id" :options="$roles" labelKey="role_name" valueKey="id" emptyOption="Select Role" emptyValue="" placeholder="Select Role" :value="old('role_id', $user->role_id)" required />
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Status <span class="text-danger">*</span></label>
                                    <select name="status" required class="input-base">
                                        <option value="1" {{ $user->status == 1 ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ $user->status == 0 ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Store ID</label>
                                    <x-searchable-select name="store_id" :options="$stores" labelKey="store_name" valueKey="id" emptyOption="Select Store (Optional)" emptyValue="" placeholder="Select Store" :value="old('store_id', $user->store_id)" />
                                </div>
                            </div>
                        </x-card>

                        <!-- SECTION 2: PERSONAL DETAILS -->
                        <x-card>
                            <h3 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em] mb-6 italic border-b border-border dark:border-dark-border pb-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                Personal Details
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">First Name</label>
                                    <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Last Name</label>
                                    <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Gender</label>
                                    <select name="gender" class="input-base">
                                        <option value="">Select Gender</option>
                                        <option value="Male" {{ old('gender', $user->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ old('gender', $user->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                        <option value="Other" {{ old('gender', $user->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Date of Birth</label>
                                    <input type="date" name="dob" value="{{ old('dob', $user->dob) }}" class="input-base">
                                </div>
                                <div class="group lg:col-span-2">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Member Of / Department</label>
                                    <input type="text" name="member_of" value="{{ old('member_of', $user->member_of) }}" class="input-base">
                                </div>
                            </div>
                        </x-card>

                        <!-- SECTION 3: LOCATION & CONTACT -->
                        <x-card>
                            <h3 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em] mb-6 italic border-b border-border dark:border-dark-border pb-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Location & Contact
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Mobile Number</label>
                                    <input type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Country</label>
                                    <input type="text" name="country" value="{{ old('country', $user->country) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">State / Division</label>
                                    <input type="text" name="state" value="{{ old('state', $user->state) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">City</label>
                                    <input type="text" name="city" value="{{ old('city', $user->city) }}" class="input-base">
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Postcode</label>
                                    <input type="text" name="postcode" value="{{ old('postcode', $user->postcode) }}" class="input-base">
                                </div>
                                <div class="group md:col-span-2 lg:col-span-3">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-1.5 ml-1">Full Address</label>
                                    <textarea name="address" rows="2" class="input-base">{{ old('address', $user->address) }}</textarea>
                                </div>
                            </div>
                        </x-card>
                    </div>

                    <!-- RIGHT COLUMN: MEDIA & SECURITY -->
                    <div class="lg:col-span-4 space-y-6">

                        <!-- SECTION 4: MEDIA -->
                        <x-card>
                            <h3 class="text-[10px] font-black uppercase text-text-muted tracking-[0.2em] mb-6 italic border-b border-border dark:border-dark-border pb-2">User Media</h3>
                            <div class="space-y-6">
                                <!-- Profile Picture -->
                                <div class="group" x-data="{ profilePreview: '{{ $user->profile_picture ? asset('storage/' . $user->profile_picture) : '' }}' }">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-3">Profile Picture</label>
                                    <div class="flex items-center gap-4">
                                        <div class="relative shrink-0">
                                            <template x-if="profilePreview">
                                                <img :src="profilePreview" class="w-16 h-16 rounded-2xl object-cover shadow-md border-2 border-border dark:border-dark-border transition-all">
                                            </template>
                                            <template x-if="!profilePreview">
                                                <div class="w-16 h-16 rounded-2xl bg-card dark:bg-dark-card text-text-muted flex items-center justify-center font-black text-xl border-2 border-dashed border-border dark:border-dark-border">?</div>
                                            </template>
                                        </div>
                                        <input type="file" name="profile_picture" @change="profilePreview = URL.createObjectURL($event.target.files[0])" class="text-[10px] text-text-muted file:mr-4 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:text-[9px] file:font-black file:uppercase file:bg-primary-light file:text-primary hover:file:bg-primary-100 cursor-pointer">
                                    </div>
                                </div>
                                <!-- Photo -->
                                <div class="group" x-data="{ photoPreview: '{{ $user->photo ? asset('storage/' . $user->photo) : '' }}' }">
                                    <label class="block text-sm font-medium text-text-primary dark:text-dark-text mb-3">Official Photo</label>
                                    <div class="flex items-center gap-4">
                                        <div class="relative shrink-0">
                                            <template x-if="photoPreview">
                                                <img :src="photoPreview" class="w-16 h-16 rounded-2xl object-cover shadow-md border-2 border-border dark:border-dark-border transition-all">
                                            </template>
                                            <template x-if="!photoPreview">
                                                <div class="w-16 h-16 rounded-2xl bg-card dark:bg-dark-card text-text-muted flex items-center justify-center font-black text-xl border-2 border-dashed border-border dark:border-dark-border">?</div>
                                            </template>
                                        </div>
                                        <input type="file" name="photo" @change="photoPreview = URL.createObjectURL($event.target.files[0])" class="text-[10px] text-text-muted file:mr-4 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:text-[9px] file:font-black file:uppercase file:bg-primary-light file:text-primary hover:file:bg-primary-100 cursor-pointer">
                                    </div>
                                </div>
                            </div>
                        </x-card>

                        <!-- SECTION 5: SECURITY -->
                        <div class="card bg-warning-light/40 dark:bg-warning/10 border-warning/20 p-6">
                            <h3 class="text-[10px] font-black uppercase text-warning tracking-[0.2em] mb-6 italic border-b border-warning/20 pb-2 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Security Override
                            </h3>
                            <div class="group">
                                <label class="block text-sm font-medium text-warning mb-1.5 ml-1">Reset Password</label>
                                <input type="password" name="password" placeholder="••••••••" class="input-base">
                                <p class="text-xs font-medium text-text-muted mt-1.5 text-center">Leave blank to retain current password</p>
                            </div>
                        </div>

                        <!-- SECTION 6: SYSTEM AUDIT (Read Only) -->
                        <div class="bg-navy rounded-3xl p-6 text-white shadow-xl relative overflow-hidden group">
                            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-primary/20 rounded-full blur-3xl transition-transform group-hover:scale-125"></div>
                            <h3 class="text-[9px] font-black uppercase tracking-[0.2em] mb-6 opacity-60 flex items-center gap-2 italic">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                System Metadata
                            </h3>
                            <div class="space-y-3.5 relative z-10">
                                <div class="flex justify-between">
                                    <span class="text-[8px] font-black uppercase opacity-40">Created At</span>
                                    <span class="text-[9px] font-bold">{{ $user->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                <div class="flex justify-between font-mono">
                                    <span class="text-[8px] font-black uppercase opacity-40">System IP</span>
                                    <span class="text-[9px] font-bold text-primary-400">{{ $user->system_ip ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[8px] font-black uppercase opacity-40">Device Name</span>
                                    <span class="text-[9px] font-bold uppercase">{{ $user->system_name ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[8px] font-black uppercase opacity-40">Creator ID</span>
                                    <span class="text-[9px] font-bold text-amber-400 font-mono">{{ $user->creater_id ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[8px] font-black uppercase opacity-40">Updater ID</span>
                                    <span class="text-[9px] font-bold text-emerald-400 font-mono">{{ $user->updater_id ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GLOBAL ACTIONS -->
                <div class="flex flex-col md:flex-row justify-end items-center gap-4 pt-4">
                    <button type="submit" class="btn-primary w-full md:w-56 group">
                        Sync Changes
                        <svg class="w-3.5 h-3.5 group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                    <a href="{{ route('users.list') }}" class="btn-secondary w-full md:w-32">
                        Discard
                    </a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
