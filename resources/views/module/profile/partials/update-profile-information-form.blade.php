<section>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <!-- Username -->
            <div class="md:col-span-2 group">
                <label for="username" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Username</label>
                <input id="username" name="username" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('username', $user->username) }}" required autocomplete="username" placeholder="Choose a unique username">
                <x-input-error class="mt-1" :messages="$errors->get('username')" />
            </div>

            <!-- First Name -->
            <div class="group">
                <label for="first_name" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">First Name</label>
                <input id="first_name" name="first_name" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('first_name', $user->first_name) }}" required autocomplete="given-name" placeholder="First name">
                <x-input-error class="mt-1" :messages="$errors->get('first_name')" />
            </div>

            <!-- Last Name -->
            <div class="group">
                <label for="last_name" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Last Name</label>
                <input id="last_name" name="last_name" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('last_name', $user->last_name) }}" required autocomplete="family-name" placeholder="Last name">
                <x-input-error class="mt-1" :messages="$errors->get('last_name')" />
            </div>

            <!-- Email -->
            <div class="md:col-span-2 group">
                <label for="email" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Email Address</label>
                <input id="email" name="email" type="email" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('email', $user->email) }}" required autocomplete="email" placeholder="email@example.com">
                <x-input-error class="mt-1" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2 p-3 bg-orange-50 dark:bg-orange-500/10 rounded-xl border border-orange-100 dark:border-orange-500/20">
                        <p class="text-[10px] font-bold text-orange-600 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Your email address is unverified.
                        </p>
                        <button form="send-verification" class="mt-1 text-[9px] font-black uppercase tracking-widest text-orange-700 hover:text-orange-800 underline decoration-2 underline-offset-4">
                            Re-send verification email
                        </button>
                    </div>
                @endif
            </div>

            <!-- Mobile -->
            <div class="group">
                <label for="mobile" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Mobile Number</label>
                <input id="mobile" name="mobile" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('mobile', $user->mobile) }}" autocomplete="tel" placeholder="+880123456789">
                <x-input-error class="mt-1" :messages="$errors->get('mobile')" />
            </div>

            <!-- Gender -->
            <div class="group">
                <label for="gender" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Gender</label>
                <select id="gender" name="gender" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]">
                    <option value="">Select Gender</option>
                    <option value="Male" {{ old('gender', $user->gender) === 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $user->gender) === 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('gender', $user->gender) === 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                <x-input-error class="mt-1" :messages="$errors->get('gender')" />
            </div>

            <!-- Date of Birth -->
            <div class="group">
                <label for="dob" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Date of Birth</label>
                <input id="dob" name="dob" type="date" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('dob', $user->dob) }}">
                <x-input-error class="mt-1" :messages="$errors->get('dob')" />
            </div>

            <!-- Country -->
            <div class="group">
                <label for="country" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Country</label>
                <input id="country" name="country" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('country', $user->country) }}" autocomplete="country-name" placeholder="e.g. Bangladesh">
                <x-input-error class="mt-1" :messages="$errors->get('country')" />
            </div>

            <!-- State -->
            <div class="group">
                <label for="state" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">State / Division</label>
                <input id="state" name="state" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('state', $user->state) }}" placeholder="e.g. Dhaka">
                <x-input-error class="mt-1" :messages="$errors->get('state')" />
            </div>

            <!-- City -->
            <div class="group">
                <label for="city" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">City</label>
                <input id="city" name="city" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('city', $user->city) }}" autocomplete="address-level2" placeholder="e.g. Dhaka City">
                <x-input-error class="mt-1" :messages="$errors->get('city')" />
            </div>

            <!-- Postcode -->
            <div class="group">
                <label for="postcode" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Postcode</label>
                <input id="postcode" name="postcode" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" value="{{ old('postcode', $user->postcode) }}" autocomplete="postal-code" placeholder="e.g. 1200">
                <x-input-error class="mt-1" :messages="$errors->get('postcode')" />
            </div>

            <!-- Address -->
            <div class="md:col-span-2 group">
                <label for="address" class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1.5 group-focus-within:text-primary-500 transition-colors">Full Address</label>
                <textarea id="address" name="address" rows="2" class="w-full bg-slate-50 dark:bg-slate-800 border-none rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-primary-500 font-semibold transition-all dark:text-white text-[11px]" autocomplete="street-address" placeholder="Enter your full street address">{{ old('address', $user->address) }}</textarea>
                <x-input-error class="mt-1" :messages="$errors->get('address')" />
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-700 transition-all shadow-lg shadow-primary-200 dark:shadow-none flex items-center gap-2 group">
                Save Changes
                <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[11px] font-black text-emerald-500 uppercase tracking-widest"
                >Saved Successfully!</p>
            @endif
        </div>
    </form>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>
</section>
