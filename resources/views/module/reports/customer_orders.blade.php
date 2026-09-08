<x-app-layout title="Customer Orders Report">
    <div x-data="customerOrdersReport()" x-cloak>
                
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                 <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Customer Orders <span class="text-[10px] font-bold text-slate-400 ml-2 italic uppercase tracking-widest">Order History</span></h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Customer Orders</span>
                </div>
            </div>
        </div>

        <!-- FILTER CARD -->
        <div class="bg-white dark:bg-dark-card rounded-2xl border border-slate-100 dark:border-dark-border p-5 mb-6 shadow-sm">
            <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-4 flex items-center gap-2">
                <span class="w-1.5 h-4 bg-primary-500 rounded-full"></span>
                Filter Criteria
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="relative group">
                    <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Customer Name</label>
                    <x-searchable-select :options="$customers" labelKey="customer_name" valueKey="id" subtextKey="mobile" emptyOption="Select Customer" emptyValue="" placeholder="Select Customer" model="customerId" />
                </div>

                <div class="relative group">
                    <label class="absolute -top-1.5 left-3 px-1 bg-white dark:bg-dark-card text-[9px] font-black uppercase text-slate-400 tracking-widest z-10">Till Date</label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3 text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <input type="date" x-model="tillDate" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 pl-9 pr-3 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-[11px] font-bold transition-all outline-none">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                 <button @click="resetFilters()" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                    Reset
                </button>
                <button @click="fetchData()" :disabled="isLoading" class="px-6 py-2 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none flex items-center gap-2 disabled:opacity-50">
                    <svg x-show="!isLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <svg x-show="isLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isLoading ? 'Loading...' : 'Show Orders'"></span>
                </button>
            </div>
        </div>

        <!-- ORDERS TABLE CARD -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm">
            
            <!-- Table Controls -->
            <div class="px-4 py-3 border-b border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Show</label>
                    <select class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg py-1 px-1 text-[10px] font-bold outline-none focus:ring-1 focus:ring-primary-500 transition-all">
                        <option>10</option>
                        <option>25</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 gap-1">
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Copy</button>
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">Excel</button>
                        <button class="px-3 py-1 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-primary-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all">PDF</button>
                    </div>
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
                            <th class="px-6 py-3 w-10 text-center">
                                <input type="checkbox" x-model="selectedAll" @change="toggleAll()" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                            </th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Customer Info</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Order ID</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Last Order</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Days Since</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        <template x-for="order in filteredOrders" :key="order.id">
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-6 py-2.5 text-center">
                                    <input type="checkbox" :value="order.id" x-model="selectedOrders" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </td>
                                <td class="px-6 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center font-black text-[9px]" x-text="order.name ? order.name.split(' ').map(n=>n?n[0]:'').join('').substring(0,2) : ''"></div>
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200" x-text="order.name"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-2.5 text-center">
                                    <span class="px-2 py-0.5 bg-primary-50 dark:bg-primary-900/10 text-primary-600 rounded text-[9px] font-black tracking-wide" x-text="order.orderId"></span>
                                </td>
                                <td class="px-6 py-2.5 text-[10px] font-bold text-slate-500 text-center" x-text="order.date"></td>
                                <td class="px-6 py-2.5 text-right font-mono">
                                     <span class="text-[10px] font-black text-slate-600 dark:text-slate-300" x-text="order.inDays + ' Days'"></span>
                                </td>
                                <td class="px-6 py-2.5 text-center">
                                     <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                            Action
                                            <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                        </button>
                                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden text-left" x-cloak>
                                            <div class="py-1">
                                                <a href="#" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">View Details</a>
                                                <a href="#" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border">Order History</a>
                                            </div>
                                        </div>
                                     </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Footer / Pagination -->
            <div class="px-6 py-3 border-t border-slate-50 dark:border-dark-border bg-slate-50/20 dark:bg-white/5 flex flex-wrap justify-between items-center gap-4">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic" x-text="'Showing 1 to ' + filteredOrders.length + ' of ' + orders.length + ' entries'"></p>
                <div class="flex gap-1.5">
                    <button class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[10px] font-bold text-slate-400 hover:border-slate-300 transition-all">Prev</button>
                    <button class="px-3 py-1 bg-primary-600 text-white rounded-lg text-[10px] font-bold shadow-md shadow-primary-200/50">1</button>
                    <button class="px-3 py-1 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[10px] font-bold text-slate-600 dark:text-slate-300 hover:border-primary-500 hover:text-primary-600 transition-all">Next</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        function customerOrdersReport() {
            return {
                customerId: '',
                tillDate: new Date().toISOString().split('T')[0],
                isLoading: false,
                orders: [],
                searchTerm: '',
                selectedAll: false,
                selectedOrders: [],
                
                get filteredOrders() {
                    if (this.searchTerm === '') {
                        return this.orders;
                    }
                    return this.orders.filter(o => 
                        (o.name && o.name.toLowerCase().includes(this.searchTerm.toLowerCase())) || 
                        (o.orderId && o.orderId.toLowerCase().includes(this.searchTerm.toLowerCase()))
                    );
                },
                
                toggleAll() {
                    if (this.selectedAll) {
                        this.selectedOrders = this.filteredOrders.map(o => o.id);
                    } else {
                        this.selectedOrders = [];
                    }
                },

                resetFilters() {
                    this.customerId = '';
                    this.tillDate = new Date().toISOString().split('T')[0];
                    this.orders = [];
                    this.searchTerm = '';
                },

                fetchData() {
                    if (!this.customerId) {
                        showError('Please select a customer first.');
                        return;
                    }
                    
                    this.isLoading = true;
                    
                    fetch(`{{ route('reports.customer_orders_data') }}?till_date=${this.tillDate}&customer_id=${this.customerId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.orders = data.orders || [];
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