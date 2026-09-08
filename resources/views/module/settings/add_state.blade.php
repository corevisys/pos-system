<x-app-layout title="Add State">
    <div class="max-w-4xl mx-auto">
        
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-8">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Add State <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Create New Record</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('settings.states') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold uppercase tracking-wider">States List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Add State</span>
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <form action="{{ route('settings.states.store') }}" method="POST" class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-2xl shadow-slate-200/50 dark:shadow-none overflow-hidden transition-all duration-300">
            @csrf
            
            <div class="p-8 border-b border-slate-50 dark:border-dark-border">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- State Name -->
                    <div class="relative group">
                        <label for="state" class="absolute -top-2 left-4 px-2 bg-white dark:bg-dark-card text-[10px] font-black uppercase tracking-widest text-slate-400 group-focus-within:text-primary-600 transition-all z-10">State Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="state" id="state" value="{{ old('state') }}" required
                               class="w-full px-5 py-4 bg-white dark:bg-dark-card border-2 border-slate-100 dark:border-dark-border rounded-2xl text-sm font-bold focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none transition-all placeholder:text-slate-300" 
                               placeholder="e.g. Dhaka">
                        @error('state') <p class="text-[10px] text-rose-500 font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                    </div>

                    <!-- Country Selection -->
                    <div class="relative group">
                        <label for="country_id" class="absolute -top-2 left-4 px-2 bg-white dark:bg-dark-card text-[10px] font-black uppercase tracking-widest text-slate-400 group-focus-within:text-primary-600 transition-all z-10">Country <span class="text-rose-500">*</span></label>
                        <x-searchable-select name="country_id" :options="$countries" labelKey="country" valueKey="id" emptyOption="Select Country" emptyValue="" placeholder="Select Country" :value="old('country_id')" required />
                        @error('country_id') <p class="text-[10px] text-rose-500 font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                    </div>

                    <!-- Status -->
                    <div class="relative group">
                        <label for="status" class="absolute -top-2 left-4 px-2 bg-white dark:bg-dark-card text-[10px] font-black uppercase tracking-widest text-slate-400 group-focus-within:text-primary-600 transition-all z-10">Status <span class="text-rose-500">*</span></label>
                        <select name="status" id="status" required
                                class="w-full px-5 py-4 bg-white dark:bg-dark-card border-2 border-slate-100 dark:border-dark-border rounded-2xl text-sm font-bold focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 outline-none transition-all appearance-none cursor-pointer">
                            <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 group-focus-within:text-primary-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        @error('status') <p class="text-[10px] text-rose-500 font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="p-6 bg-slate-50/50 dark:bg-white/5 flex flex-col sm:flex-row justify-end items-center gap-3">
                <a href="{{ route('settings.states') }}" class="w-full sm:w-auto px-8 py-3 bg-white dark:bg-dark-card border-2 border-slate-100 dark:border-dark-border rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-600 hover:border-slate-200 transition-all text-center">
                    Cancel
                </a>
                <button type="submit" class="w-full sm:w-auto px-10 py-3 bg-primary-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] hover:bg-primary-700 shadow-xl shadow-primary-200 transition-all">
                    Save State
                </button>
            </div>

        </form>
    </div>
</x-app-layout>
