<x-app-layout title="Edit State">
    <div class="max-w-4xl mx-auto" x-data="{ isSubmitting: false }">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-8">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Edit State <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">Modify Details</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('settings.states') }}" class="hover:text-primary transition-colors text-[10px] font-bold text-text-muted uppercase tracking-wider">States List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">Edit State</span>
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <form action="{{ route('settings.states.update', $state->id) }}" method="POST" @submit="isSubmitting = true">
            @csrf
            <x-card padding="p-0" class="overflow-hidden">
                <div class="p-8 border-b border-border dark:border-dark-border">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                        <!-- State Name -->
                        <div class="group relative">
                            <label for="state" class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[10px] font-black uppercase tracking-widest text-text-muted z-10">State Name <span class="text-danger">*</span></label>
                            <input type="text" name="state" id="state" value="{{ old('state', $state->state) }}" required class="input-base !py-3 !text-sm !font-bold" placeholder="e.g. Dhaka">
                            @error('state') <p class="text-[10px] text-danger font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                        </div>

                        <!-- Country Selection -->
                        <div class="group relative">
                            <label for="country_id" class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[10px] font-black uppercase tracking-widest text-text-muted z-10">Country <span class="text-danger">*</span></label>
                            <x-searchable-select name="country_id" :options="$countries" labelKey="country" valueKey="id" emptyOption="Select Country" emptyValue="" placeholder="Select Country" :value="old('country_id', $state->country_id)" required />
                            @error('country_id') <p class="text-[10px] text-danger font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                        </div>

                        <!-- Status -->
                        <div class="group relative">
                            <label for="status" class="absolute -top-2 left-4 bg-card dark:bg-dark-card px-2 text-[10px] font-black uppercase tracking-widest text-text-muted z-10">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" required class="input-base appearance-none cursor-pointer !py-3 !text-sm !font-bold">
                                <option value="1" {{ old('status', $state->status) == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status', $state->status) == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-text-muted">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            @error('status') <p class="text-[10px] text-danger font-bold mt-1 ml-2 uppercase italic tracking-widest">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="p-6 bg-background/50 dark:bg-white/5 flex flex-col sm:flex-row justify-end items-center gap-3">
                    <a href="{{ route('settings.states') }}" class="btn-secondary w-full sm:w-auto">Cancel</a>
                    <button type="submit" :disabled="isSubmitting" class="btn-primary w-full sm:w-auto px-10 disabled:opacity-60 disabled:cursor-not-allowed">Update State</button>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
