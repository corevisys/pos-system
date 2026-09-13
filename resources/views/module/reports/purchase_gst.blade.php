<x-app-layout title="Purchase GST Report">
    <div x-data="purchaseGstReport()" x-cloak>
                
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                 <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Purchase GST Report <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Input Tax</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Purchase GST Report</span>
                </div>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 mb-6 shadow-sm">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-4 flex items-center gap-2">
                <span class="w-1.5 h-4 bg-primary-500 rounded-full"></span>
                Filter Criteria
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="relative group">
                    <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">From Date</label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3 text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <input type="date" x-model="fromDate" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-9 pr-3 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-[11px] font-bold transition-all outline-none">
                    </div>
                </div>

                <div class="relative group">
                    <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">To Date</label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3 text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <input type="date" x-model="toDate" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-9 pr-3 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-[11px] font-bold transition-all outline-none">
                    </div>
                </div>

                <div class="relative group">
                    <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Supplier Name</label>
                    <x-searchable-select :options="$suppliers" labelKey="supplier_name" valueKey="id" subtextKey="mobile" emptyOption="-All-" emptyValue="all" placeholder="Select Supplier" model="supplierId" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                 <button @click="resetFilters()" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                    Reset
                </button>
                <button @click="fetchData()" :disabled="isLoading" class="px-6 py-2 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-50">
                    <svg x-show="!isLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <svg x-show="isLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isLoading ? 'Loading...' : 'Show Report'"></span>
                </button>
            </div>
        </div>

        <!-- RECORDS TABLE CARD -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            
            <!-- Table Controls -->
            <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                

                <div class="flex items-center gap-2">
                    <x-report-export-buttons :route="route('reports.purchase_gst_data')" />
                    <div class="relative group">
                        <input type="text" x-model="searchTerm" placeholder="Search..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-40 shadow-sm transition-all">
                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                        <tr>
                            
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Purchase Details</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Supplier</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Taxable ({{ $currencySymbol }})</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Tax %</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Total ({{ $currencySymbol }})</th>
                            
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        <tr x-show="filteredRecords.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                    <span class="text-[10px] font-black uppercase tracking-widest opacity-50 italic">No Purchase Records Found</span>
                                </div>
                            </td>
                        </tr>
                        <template x-for="record in filteredRecords" :key="record.id">
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                
                                <td class="px-6 py-2.5">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200" x-text="record.date"></span>
                                        <span class="text-[9px] font-black text-primary-600 uppercase tracking-tight" x-text="record.invoice"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-2.5">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black text-slate-700 dark:text-slate-200" x-text="record.supplier"></span>
                                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest" x-text="record.gstNo || 'No GST'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-2.5 text-right font-mono text-[11px] font-black text-slate-700 dark:text-white" x-text="record.taxable"></td>
                                <td class="px-6 py-2.5 text-right font-mono text-[11px] font-black text-emerald-600" x-text="record.tax"></td>
                                <td class="px-6 py-2.5 text-right font-mono text-[11px] font-black text-slate-800 dark:text-white" x-text="record.total"></td>
                                
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="bg-slate-50/80 dark:bg-slate-800/80 border-t border-slate-200">
                        <tr class="font-black text-slate-700 dark:text-slate-200">
                            <td colspan="2" class="px-6 py-3 text-right uppercase text-[9px] tracking-widest text-slate-500 italic">Total Summary</td>
                            <td class="px-6 py-3 text-[11px] tabular-nums text-right font-mono" x-text="records.reduce((acc, r) => acc + parseFloat(r.taxable.replace(/,/g, '')), 0).toFixed(2)"></td>
                            <td class="px-6 py-3 text-[11px] tabular-nums text-right text-emerald-600 font-mono" x-text="records.reduce((acc, r) => acc + parseFloat(r.tax.replace(/,/g, '')), 0).toFixed(2)"></td>
                            <td class="px-6 py-3 text-[11px] tabular-nums text-right text-slate-800 dark:text-white font-mono" x-text="records.reduce((acc, r) => acc + parseFloat(r.total.replace(/,/g, '')), 0).toFixed(2)"></td>
                            </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

    <script>
        function purchaseGstReport() {
            return {
                fromDate: new Date(new Date().setDate(new Date().getDate() - 29)).toISOString().split('T')[0],
                toDate: new Date().toISOString().split('T')[0],
                supplierId: 'all',
                isLoading: false,
                records: [],
                searchTerm: '',
                get filteredRecords() {
                    if (this.searchTerm === '') {
                        return this.records;
                    }
                    return this.records.filter(r => 
                        (r.invoice && r.invoice.toLowerCase().includes(this.searchTerm.toLowerCase())) || 
                        (r.supplier && r.supplier.toLowerCase().includes(this.searchTerm.toLowerCase()))
                    );
                },
                
                resetFilters() {
                    this.fromDate = new Date(new Date().setDate(new Date().getDate() - 29)).toISOString().split('T')[0];
                    this.toDate = new Date().toISOString().split('T')[0];
                    this.supplierId = 'all';
                    this.records = [];
                    this.searchTerm = '';
                },

                fetchData() {
                    this.isLoading = true;
                    
                    fetch(`{{ route('reports.purchase_gst_data') }}?start_date=${this.fromDate}&end_date=${this.toDate}&supplier_id=${this.supplierId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.records = data.records || [];
                            } else {
                                showError(data.message || 'Error fetching data');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            showError('Failed to fetch data');
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                }
            }
        }
    </script>
</x-app-layout>