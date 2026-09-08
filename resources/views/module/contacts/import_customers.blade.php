<x-app-layout title="Import Customers">
    <div class="space-y-6">

        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-text-primary dark:text-dark-text">Import Customers</h1>
                <nav class="flex items-center gap-2 text-xs text-text-muted mt-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-3 h-3 text-text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('contacts.customers.list') }}" class="hover:text-primary transition-colors font-medium">Customers List</a>
                    <svg class="w-3 h-3 text-text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-primary dark:text-dark-text font-semibold">Import</span>
                </nav>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <a href="{{ route('contacts.customers.import.template') }}" class="btn-secondary w-full md:w-auto text-xs py-2">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Download CSV Template
                </a>
            </div>
        </div>

        <!-- POST-IMPORT RESULT SUMMARY -->
        @if(session('import_summary'))
            @php $summary = session('import_summary'); @endphp
            <x-card class="border-l-4 border-l-primary">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-border dark:border-dark-border">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg {{ $summary['skipped'] > 0 ? 'bg-amber-500/10 text-amber-600' : 'bg-emerald-500/10 text-emerald-600' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-text-primary dark:text-dark-text">Import Completed</h3>
                            <p class="text-xs text-text-muted">Summary of rows processed from the uploaded CSV file.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge color="success">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ $summary['imported'] }} Imported
                        </x-badge>
                        @if($summary['skipped'] > 0)
                            <x-badge color="warning">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                {{ $summary['skipped'] }} Skipped
                            </x-badge>
                        @endif
                    </div>
                </div>

                @if(!empty($summary['errors']))
                    <div class="mt-3">
                        <h4 class="text-xs font-semibold text-text-secondary dark:text-dark-text mb-1.5">Skipped Rows / Reasons:</h4>
                        <div class="bg-background dark:bg-slate-900 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1 text-xs custom-scrollbar">
                            @foreach($summary['errors'] as $error)
                                <div class="text-amber-700 dark:text-amber-400 flex items-start gap-1.5">
                                    <span class="text-amber-500">•</span>
                                    <span>{{ $error }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-danger/10 border border-danger/20 text-danger text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- UPLOAD FORM SECTION (1 col on lg) -->
            <div class="lg:col-span-1">
                <x-card class="h-full flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="p-2 bg-primary/10 rounded-lg text-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-text-primary dark:text-dark-text">Upload CSV File</h2>
                                <p class="text-xs text-text-muted">Select or drop a valid customer CSV</p>
                            </div>
                        </div>

                        <form action="{{ route('contacts.customers.import.store') }}" 
                              method="POST" 
                              enctype="multipart/form-data" 
                              x-data="{ 
                                  fileName: '', 
                                  fileSize: '', 
                                  isDragging: false, 
                                  isSubmitting: false,
                                  handleFile(files) {
                                      if (files && files[0]) {
                                          this.fileName = files[0].name;
                                          const bytes = files[0].size;
                                          this.fileSize = bytes < 1048576 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / 1048576).toFixed(2) + ' MB';
                                      }
                                  }
                              }" 
                              @submit="isSubmitting = true" 
                              class="space-y-4">
                            @csrf

                            <!-- DROPZONE -->
                            <div class="relative">
                                <input type="file" 
                                       name="import_file" 
                                       id="customer_import_file" 
                                       accept=".csv,text/csv" 
                                       required
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                       @change="handleFile($event.target.files)"
                                       @dragenter="isDragging = true"
                                       @dragleave="isDragging = false"
                                       @drop="isDragging = false; handleFile($event.dataTransfer.files)">
                                
                                <div class="w-full border-2 border-dashed rounded-xl p-6 text-center transition-all"
                                     :class="isDragging ? 'border-primary bg-primary/5' : (fileName ? 'border-emerald-400 bg-emerald-50/30 dark:bg-emerald-500/5' : 'border-border dark:border-dark-border hover:border-primary/50 bg-background dark:bg-dark-card')">
                                    
                                    <template x-if="!fileName">
                                        <div class="space-y-2">
                                            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                            </div>
                                            <p class="text-xs font-semibold text-text-primary dark:text-dark-text">Click to choose or drag CSV here</p>
                                            <p class="text-[11px] text-text-muted">Maximum file size: 5MB</p>
                                        </div>
                                    </template>

                                    <template x-if="fileName">
                                        <div class="space-y-2">
                                            <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 mx-auto flex items-center justify-center">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                            <p class="text-xs font-bold text-text-primary dark:text-dark-text truncate max-w-[220px] mx-auto" x-text="fileName"></p>
                                            <p class="text-[11px] text-emerald-600 font-medium" x-text="fileSize"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            @error('import_file')
                                <p class="text-xs font-semibold text-danger">{{ $message }}</p>
                            @enderror

                            <!-- SUBMIT ACTIONS -->
                            <div class="flex items-center gap-2 pt-2">
                                <button type="submit" 
                                        class="btn-primary flex-1" 
                                        :disabled="isSubmitting || !fileName">
                                    <template x-if="isSubmitting">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                    </template>
                                    <template x-if="!isSubmitting">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    </template>
                                    <span x-text="isSubmitting ? 'Importing...' : 'Import Customers'"></span>
                                </button>
                                <a href="{{ route('contacts.customers.list') }}" class="btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="mt-6 pt-4 border-t border-border dark:border-dark-border text-xs text-text-muted space-y-1.5">
                        <p class="font-medium text-text-secondary dark:text-dark-text flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Import Guidelines
                        </p>
                        <p>• Mobile numbers must be unique to prevent duplicate customers.</p>
                        <p>• Customer codes will be automatically generated.</p>
                        <p>• Previous Due field will initialize the customer opening balance.</p>
                    </div>
                </x-card>
            </div>

            <!-- COLUMN SPECIFICATION & INSTRUCTIONS TABLE (2 cols on lg) -->
            <div class="lg:col-span-2">
                <x-card padding="p-0" class="overflow-hidden">
                    <div class="p-4 border-b border-border dark:border-dark-border flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 bg-rose-500/10 rounded-lg text-rose-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <h2 class="text-sm font-bold text-text-primary dark:text-dark-text">CSV Columns Specification</h2>
                        </div>
                        <span class="text-xs text-text-muted">18 Columns</span>
                    </div>

                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse text-xs" x-data="{
                            instructions: [
                                { id: 1, column: 'Customer Name', value: 'Required', details: 'Full name of the customer' },
                                { id: 2, column: 'Mobile', value: 'Optional', details: 'Unique mobile phone number' },
                                { id: 3, column: 'Email', value: 'Optional', details: 'Unique email address' },
                                { id: 4, column: 'Phone', value: 'Optional', details: 'Alternative phone / telephone' },
                                { id: 5, column: 'GST Number', value: 'Optional', details: 'GSTIN identification number' },
                                { id: 6, column: 'TAX Number', value: 'Optional', details: 'Tax / VAT registration number' },
                                { id: 7, column: 'Previous Due', value: 'Optional', details: 'Opening balance (default: 0.00)' },
                                { id: 8, column: 'Credit Limit', value: 'Optional', details: 'Max credit allowance (-1 for no limit, default: 0.00)' },
                                { id: 9, column: 'Country Name', value: 'Optional', details: 'Country name (e.g. Bangladesh)' },
                                { id: 10, column: 'State Name', value: 'Optional', details: 'State / Division name (e.g. Dhaka)' },
                                { id: 11, column: 'Postcode', value: 'Optional', details: 'Postal / ZIP code' },
                                { id: 12, column: 'Address', value: 'Optional', details: 'Billing street address' },
                                { id: 13, column: 'Location Link', value: 'Optional', details: 'Google Maps URL or web link' },
                                { id: 14, column: 'Shipping Country Name', value: 'Optional', details: 'Shipping destination country' },
                                { id: 15, column: 'Shipping State Name', value: 'Optional', details: 'Shipping destination state' },
                                { id: 16, column: 'Shipping Postcode', value: 'Optional', details: 'Shipping destination postcode' },
                                { id: 17, column: 'Shipping Address', value: 'Optional', details: 'Shipping delivery address' },
                                { id: 18, column: 'Shipping Location Link', value: 'Optional', details: 'Shipping destination map link' }
                            ]
                        }">
                            <thead class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-border dark:border-dark-border text-text-secondary dark:text-dark-text font-semibold">
                                <tr>
                                    <th class="px-3.5 py-2.5 w-12 text-center">#</th>
                                    <th class="px-3.5 py-2.5">Column Name</th>
                                    <th class="px-3.5 py-2.5 text-center w-24">Required?</th>
                                    <th class="px-3.5 py-2.5">Description & Guidelines</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border dark:divide-dark-border">
                                <template x-for="item in instructions" :key="item.id">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-3.5 py-2 text-center text-text-muted font-medium" x-text="item.id"></td>
                                        <td class="px-3.5 py-2 font-semibold text-text-primary dark:text-dark-text" x-text="item.column"></td>
                                        <td class="px-3.5 py-2 text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                                  :class="item.value === 'Required' ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                                                  x-text="item.value"></span>
                                        </td>
                                        <td class="px-3.5 py-2 text-text-secondary dark:text-text-muted" x-text="item.details"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>