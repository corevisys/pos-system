<x-app-layout title="Print Labels">
    <div class="print:p-0 print:m-0 print:pb-0" x-data="printLabelsApp()">
        
        <!-- HEADER & ACTIONS (Hidden in Print) -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6 print:hidden">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-2">
                    Print Barcode Labels
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400 uppercase tracking-widest border border-primary-100 dark:border-primary-500/20">
                        Code 128
                    </span>
                </h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold uppercase tracking-wider">
                        Items
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 dark:text-slate-300 text-[10px] font-black uppercase tracking-wider">Print Labels</span>
                </div>
            </div>

            <!-- View Mode Switcher & Global Print Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-dark-border">
                    <button type="button" @click="activeTab = 'designer'" :class="activeTab === 'designer' ? 'bg-white dark:bg-dark-card text-primary-600 font-black shadow-xs' : 'text-slate-500 hover:text-slate-700 font-bold'" class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        Queue & Setup
                    </button>
                    <button type="button" @click="activeTab = 'sheet_preview'" :class="activeTab === 'sheet_preview' ? 'bg-white dark:bg-dark-card text-primary-600 font-black shadow-xs' : 'text-slate-500 hover:text-slate-700 font-bold'" class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        Sheet Preview (<span x-text="totalLabelCount"></span>)
                    </button>
                </div>

                <a href="{{ route('items.list') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Items List
                </a>
                
                <!-- Print Now: opens PDF inline in new browser tab via DomPDF stream() -->
                <button type="button" @click="submitForPrint()" :disabled="totalLabelCount === 0" :class="totalLabelCount === 0 ? 'opacity-50 cursor-not-allowed bg-slate-300 text-slate-500' : 'bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-200/50 dark:shadow-none text-white'" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Now (<span x-text="totalLabelCount"></span>)</span>
                </button>

                <!-- Download PDF: triggers browser save dialog via DomPDF download() -->
                <button type="button" @click="submitForPdf()" :disabled="totalLabelCount === 0" :class="totalLabelCount === 0 ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-200/50 dark:shadow-none'" class="px-5 py-2.5 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Download PDF</span>
                </button>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: DESIGNER & QUEUE SETUP (Hidden in Print)                           -->
        <!-- ========================================================================= -->
        <div x-show="activeTab === 'designer'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 print:hidden">
            
            <!-- LEFT / CENTER: SEARCH, BATCH FILTERS & QUEUE -->
            <div class="lg:col-span-8 space-y-5">
                
                <!-- SEARCH CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-4 md:p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-primary-500 rounded-full"></span>
                            Search & Add Items
                        </h3>
                        <button type="button" @click="toggleBatchFilter = !toggleBatchFilter" class="text-[10px] font-bold text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 cursor-pointer">
                            <span x-text="toggleBatchFilter ? 'Hide Batch Filters' : 'Bulk / Category / Warehouse Add'"></span>
                            <svg class="w-3 h-3 transition-transform" :class="toggleBatchFilter ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>

                    <!-- Search Input & Live Results Dropdown -->
                    <div class="relative">
                        <div class="relative group">
                            <input type="text" 
                                   x-model="searchQuery" 
                                   @input.debounce.250ms="performSearch()" 
                                   @keydown.enter.prevent="selectFirstResult()"
                                   @keydown.escape="showDropdown = false"
                                   @click="if (searchResults.length > 0) showDropdown = true"
                                   placeholder="Scan Barcode or type Product Name / Code / SKU..." 
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-2xl py-3 pl-11 pr-10 text-xs font-bold text-slate-800 dark:text-slate-200 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all shadow-inner-sm">
                            
                            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                                <template x-if="isSearching">
                                    <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </template>
                                <template x-if="searchQuery.length > 0 && !isSearching">
                                    <button type="button" @click="searchQuery = ''; showDropdown = false" class="text-slate-400 hover:text-slate-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Search Autocomplete Dropdown -->
                        <div x-show="showDropdown && searchResults.length > 0" 
                             @click.away="showDropdown = false" 
                             class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-dark-card rounded-2xl shadow-2xl border border-slate-100 dark:border-dark-border z-30 max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-dark-border" 
                             x-cloak>
                            <template x-for="item in searchResults" :key="item.id">
                                <div @click="addItem(item)" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 flex items-center justify-center font-bold text-xs">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-primary-600 transition-colors" x-text="item.item_name"></div>
                                            <div class="text-[10px] font-mono text-slate-400 flex items-center gap-2 mt-0.5">
                                                <span>Code: <strong x-text="item.item_code"></strong></span>
                                                <span x-show="item.custom_barcode">| Barcode: <strong x-text="item.custom_barcode"></strong></span>
                                                <span x-show="item.brand_name">| <span x-text="item.brand_name"></span></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-black text-emerald-600 dark:text-emerald-400" x-text="formatCurrency(item.sales_price)"></div>
                                        <div class="text-[9px] font-bold text-slate-400">Stock: <span x-text="item.stock"></span></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- BATCH FILTERS ACCORDION -->
                    <div x-show="toggleBatchFilter" x-transition class="mt-4 pt-4 border-t border-slate-100 dark:border-dark-border" x-cloak>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-1">Category</label>
                                <select x-model="batchCategory" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-2.5 text-xs font-bold outline-none focus:border-primary-500">
                                    <option value="">-- All Categories --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-1">Brand</label>
                                <select x-model="batchBrand" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-2.5 text-xs font-bold outline-none focus:border-primary-500">
                                    <option value="">-- All Brands --</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->brand_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-1">Warehouse Stock</label>
                                <select x-model="batchWarehouse" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-2.5 text-xs font-bold outline-none focus:border-primary-500">
                                    <option value="">-- Any Location --</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end items-center gap-2 mt-3">
                            <button type="button" @click="loadBatchItems()" :disabled="isLoadingBatch" class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                                <span x-show="!isLoadingBatch">Add Filtered Items to Queue</span>
                                <span x-show="isLoadingBatch">Loading Items...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- QUEUE CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden flex flex-col">
                    
                    <!-- Table Header Controls -->
                    <div class="p-4 border-b border-slate-100 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/40 flex flex-wrap justify-between items-center gap-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">
                                Label Print Queue
                            </h3>
                            <span class="px-2 py-0.5 bg-primary-500/10 text-primary-600 rounded text-[10px] font-black" x-text="queue.length + ' item(s)'"></span>
                        </div>

                        <!-- Quick Quantity Presets -->
                        <div class="flex flex-wrap items-center gap-1.5" x-show="queue.length > 0">
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider mr-1">Set All Qty:</span>
                            <button type="button" @click="setAllQuantities(1)" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded text-[9px] font-bold cursor-pointer">1</button>
                            <button type="button" @click="setAllQuantities(5)" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded text-[9px] font-bold cursor-pointer">5</button>
                            <button type="button" @click="setAllQuantities(10)" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 rounded text-[9px] font-bold cursor-pointer">10</button>
                            <button type="button" @click="matchAllToStock()" class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 text-emerald-600 rounded text-[9px] font-bold border border-emerald-200/50 cursor-pointer">Match Stock</button>
                            <button type="button" @click="clearQueue()" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 text-rose-600 rounded text-[9px] font-bold border border-rose-200/50 ml-1 cursor-pointer">Clear All</button>
                        </div>
                    </div>

                    <!-- Queue Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/30 dark:bg-slate-800/20 border-b border-slate-100 dark:border-dark-border text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                <tr>
                                    <th class="px-5 py-3">#</th>
                                    <th class="px-5 py-3">Item Details</th>
                                    <th class="px-5 py-3 text-right">Unit Price</th>
                                    <th class="px-5 py-3 text-center">Print Qty</th>
                                    <th class="px-5 py-3 text-center">Barcode Preview</th>
                                    <th class="px-5 py-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                                <template x-for="(item, index) in queue" :key="item.id">
                                    <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-800/30 transition-colors group">
                                        <td class="px-5 py-3 text-[10px] font-bold text-slate-400" x-text="index + 1"></td>
                                        <td class="px-5 py-3">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="item.item_name"></span>
                                                <div class="flex items-center gap-2 mt-0.5 text-[9px] font-mono text-slate-400">
                                                    <span>Code: <strong x-text="item.item_code"></strong></span>
                                                    <template x-if="item.custom_barcode">
                                                        <span>| Barcode: <strong x-text="item.custom_barcode"></strong></span>
                                                    </template>
                                                    <template x-if="item.stock">
                                                        <span>| In Stock: <strong x-text="item.stock"></strong></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono text-xs font-black text-slate-700 dark:text-slate-300" x-text="formatCurrency(item.sales_price)"></td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center justify-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-1 w-28 mx-auto border border-slate-200 dark:border-dark-border">
                                                <button type="button" @click="item.qty > 1 ? item.qty-- : null" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-rose-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all cursor-pointer">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"></path></svg>
                                                </button>
                                                <input type="number" min="1" x-model.number="item.qty" class="w-10 text-center bg-transparent border-none text-xs font-black text-slate-800 dark:text-white focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                <button type="button" @click="item.qty++" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-emerald-600 hover:bg-white dark:hover:bg-dark-card rounded-lg transition-all cursor-pointer">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            <div class="flex flex-col items-center justify-center w-28 mx-auto">
                                                <div class="w-full flex items-center justify-center overflow-hidden h-7" x-html="item.barcode_svg"></div>
                                                <span class="text-[8px] font-mono text-slate-400 tracking-wider mt-0.5" x-text="item.barcode_value"></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            <button type="button" @click="removeItem(index)" class="w-7 h-7 flex items-center justify-center bg-rose-500/10 text-rose-600 rounded-lg hover:bg-rose-500 hover:text-white transition-all mx-auto border border-rose-500/20 cursor-pointer" title="Remove from queue">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="queue.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center text-slate-400 font-medium">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <svg class="w-8 h-8 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                <p class="text-xs font-bold text-slate-500">Your print queue is empty.</p>
                                                <p class="text-[10px] text-slate-400">Search products above or use the bulk filters to add items for label printing.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Queue Summary Footer -->
                    <div class="p-4 border-t border-slate-100 dark:border-dark-border bg-slate-50/50 dark:bg-slate-800/40 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Labels to Print:</span>
                            <span class="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-600 rounded text-xs font-black tabular-nums border border-emerald-500/20" x-text="totalLabelCount"></span>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="button" @click="activeTab = 'sheet_preview'" :disabled="totalLabelCount === 0" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer">
                                Preview Sheet
                            </button>
                            <button type="button" @click="submitForPrint()" :disabled="totalLabelCount === 0" :class="totalLabelCount === 0 ? 'opacity-50 cursor-not-allowed bg-slate-300 text-slate-500' : 'bg-emerald-600 hover:bg-emerald-700 text-white'" class="px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                Print Now
                            </button>
                            <button type="button" @click="submitForPdf()" :disabled="totalLabelCount === 0" :class="totalLabelCount === 0 ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-primary-600 hover:bg-primary-700'" class="px-5 py-2 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: CONFIGURATION & LIVE VIRTUAL PREVIEW -->
            <div class="lg:col-span-4 space-y-5">
                
                <!-- LABEL CUSTOMIZATION CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-5 shadow-sm space-y-4">
                    <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 flex items-center gap-2">
                        <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                        Label Sheet Configuration
                    </h3>

                    <!-- Paper Sheet Preset -->
                    <div>
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-1.5">Paper / Sheet Preset</label>
                        <select x-model="preset" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-2 px-3 text-xs font-bold outline-none focus:border-primary-500 transition-all cursor-pointer">
                            <option value="sheet_24">24 Labels / Sheet (3 Columns × 8 Rows - A4 63.5×33.9mm)</option>
                            <option value="sheet_30">30 Labels / Sheet (3 Columns × 10 Rows - A4 70×29.7mm)</option>
                            <option value="sheet_12">12 Labels / Sheet (2 Columns × 6 Rows - A4 105×48mm)</option>
                            <option value="sheet_40">40 Labels / Sheet (4 Columns × 10 Rows - A4 48.5×25.4mm)</option>
                            <option value="thermal_roll">Continuous Thermal Roll (1 Label / Row - 50×25mm)</option>
                        </select>
                    </div>

                    <!-- Store Name Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Store Header</label>
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" x-model="showStoreName" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-3 h-3">
                                <span class="text-[9px] font-bold text-slate-500">Show</span>
                            </label>
                        </div>
                        <input type="text" x-model="customStoreName" :disabled="!showStoreName" placeholder="Store Name..." class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-3 text-xs font-bold outline-none focus:border-primary-500 transition-all disabled:opacity-50">
                    </div>

                    <!-- Toggle Options Grid -->
                    <div class="pt-2 border-t border-slate-100 dark:border-dark-border">
                        <label class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-2">Display Elements</label>
                        <div class="grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="checkbox" x-model="showItemName" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5">
                                <span>Product Name</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="checkbox" x-model="showPrice" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5">
                                <span>Sales Price</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="checkbox" x-model="showBarcode" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5">
                                <span>Barcode SVG</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 cursor-pointer hover:bg-slate-100 transition-colors">
                                <input type="checkbox" x-model="showItemCode" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5">
                                <span>Code / Text</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- LIVE VIRTUAL LABEL PREVIEW CARD -->
                <div class="bg-white dark:bg-dark-card rounded-3xl border border-primary-500/20 shadow-sm relative overflow-hidden flex flex-col items-center justify-center p-6 bg-slate-50/30 dark:bg-white/5 min-h-[320px]">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-3 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        Live Single Sticker Preview
                    </div>

                    <!-- Virtual Sticker Simulation Box -->
                    <div class="w-full max-w-[220px] p-3 bg-white border-2 border-dashed border-slate-300 rounded-lg flex flex-col items-center text-center shadow-lg text-black transition-all">
                        
                        <!-- Store Name -->
                        <template x-if="showStoreName">
                            <h4 class="text-[11px] font-black uppercase tracking-tight leading-tight w-full mb-0.5 truncate font-[sans-serif]" x-text="customStoreName"></h4>
                        </template>

                        <!-- Item Name -->
                        <template x-if="showItemName">
                            <p class="text-[10px] font-bold leading-tight line-clamp-2 w-full mb-0.5 font-[sans-serif]" x-text="previewItem.item_name"></p>
                        </template>

                        <!-- Price -->
                        <template x-if="showPrice">
                            <div class="text-[11px] font-black mb-0.5 font-[sans-serif]">
                                Price: <span x-text="formatCurrency(previewItem.sales_price)"></span>
                            </div>
                        </template>

                        <!-- Barcode SVG -->
                        <template x-if="showBarcode">
                            <div class="w-full flex items-center justify-center overflow-hidden my-0.5 h-8" x-html="previewItem.barcode_svg"></div>
                        </template>

                        <!-- Barcode String -->
                        <template x-if="showItemCode">
                            <p class="text-[9px] font-mono font-bold tracking-[0.2em] mt-0.5" x-text="previewItem.barcode_value"></p>
                        </template>
                    </div>

                    <div class="mt-4 text-[8px] font-black text-slate-400 uppercase tracking-widest">
                        Preset: <span class="text-primary-600 font-bold" x-text="presetTitle"></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: ON-SCREEN FULL SHEET PREVIEW (Visible in browser tab & print)       -->
        <!-- ========================================================================= -->
        <div x-show="activeTab === 'sheet_preview'" class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border p-6 shadow-sm mb-6 print:hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-slate-100 dark:border-dark-border">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-4 bg-primary-600 rounded-full"></span>
                        Full Sheet Print Layout Preview
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Showing <strong x-text="totalLabelCount"></strong> sticker(s) organized under <span class="text-primary-600 font-bold" x-text="presetTitle"></span>.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click="activeTab = 'designer'" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-black uppercase tracking-wider hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back to Queue
                    </button>
                </div>
            </div>

            <!-- Virtual Sheet Canvas Container -->
            <div class="bg-slate-100 dark:bg-slate-900/60 p-4 md:p-8 rounded-2xl flex justify-center overflow-x-auto">
                <div class="bg-white text-black p-4 shadow-xl border border-slate-200" :style="sheetContainerStyle">
                    <div :class="'preset-' + preset.replace('_', '-')" class="labels-grid">
                        <template x-for="sticker in expandedStickersList" :key="sticker.uid">
                            <div class="label-item">
                                <!-- Store Header -->
                                <template x-if="showStoreName">
                                    <div class="label-store-name" x-text="customStoreName"></div>
                                </template>

                                <!-- Item Name -->
                                <template x-if="showItemName">
                                    <div class="label-item-name" x-text="sticker.item_name"></div>
                                </template>

                                <!-- Price -->
                                <template x-if="showPrice">
                                    <div class="label-price">
                                        Price: <span x-text="formatCurrency(sticker.sales_price)"></span>
                                    </div>
                                </template>

                                <!-- Barcode SVG -->
                                <template x-if="showBarcode">
                                    <div class="label-barcode-svg" x-html="sticker.barcode_svg"></div>
                                </template>

                                <!-- Barcode Human Text -->
                                <template x-if="showItemCode">
                                    <div class="label-barcode-code" x-text="sticker.barcode_value"></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- PRINT AREA (Targeted exclusively during browser window.print())           -->
        <!-- ========================================================================= -->
        <div id="print-area" class="print-only-container">
            <div :class="'preset-' + preset.replace('_', '-')" class="labels-grid">
                <template x-for="sticker in expandedStickersList" :key="'p-' + sticker.uid">
                    <div class="label-item">
                        <!-- Store Header -->
                        <template x-if="showStoreName">
                            <div class="label-store-name" x-text="customStoreName"></div>
                        </template>

                        <!-- Item Name -->
                        <template x-if="showItemName">
                            <div class="label-item-name" x-text="sticker.item_name"></div>
                        </template>

                        <!-- Price -->
                        <template x-if="showPrice">
                            <div class="label-price">
                                Price: <span x-text="formatCurrency(sticker.sales_price)"></span>
                            </div>
                        </template>

                        <!-- Barcode SVG -->
                        <template x-if="showBarcode">
                            <div class="label-barcode-svg" x-html="sticker.barcode_svg"></div>
                        </template>

                        <!-- Barcode Human Text -->
                        <template x-if="showItemCode">
                            <div class="label-barcode-code" x-text="sticker.barcode_value"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- PRESET DIMENSIONS & PRINT STYLES                                          -->
    <!-- ========================================================================= -->
    <style>
        /* Base Labels Grid & Label Item Styling */
        .labels-grid {
            display: grid;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .label-item {
            box-sizing: border-box;
            background: #ffffff;
            color: #000000;
            border: 1px dashed #cbd5e1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .label-store-name {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: -0.01em;
            line-height: 1.1;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-item-name {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 600;
            line-height: 1.15;
            width: 100%;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .label-price {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-weight: 800;
            line-height: 1.1;
        }

        .label-barcode-svg {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .label-barcode-svg svg {
            max-width: 96%;
            height: 100% !important;
            display: block;
        }

        .label-barcode-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            letter-spacing: 0.15em;
            line-height: 1;
        }

        /* ── PRESET 1: 24 Labels / Sheet (3 cols × 8 rows - standard 63.5mm × 33.9mm) ── */
        .preset-sheet-24 {
            grid-template-columns: repeat(3, 63.5mm);
            gap: 2mm 2.5mm;
            width: 200mm;
            justify-content: center;
        }
        .preset-sheet-24 .label-item {
            width: 63.5mm;
            height: 33.9mm;
            padding: 1.5mm 2mm;
        }
        .preset-sheet-24 .label-store-name { font-size: 8pt; margin-bottom: 0.5mm; }
        .preset-sheet-24 .label-item-name { font-size: 7.5pt; margin-bottom: 0.5mm; }
        .preset-sheet-24 .label-price { font-size: 8pt; margin-bottom: 0.5mm; }
        .preset-sheet-24 .label-barcode-svg { height: 9mm; margin-bottom: 0.5mm; }
        .preset-sheet-24 .label-barcode-code { font-size: 6.5pt; }

        /* ── PRESET 2: 30 Labels / Sheet (3 cols × 10 rows - standard 70mm × 29.7mm) ── */
        .preset-sheet-30 {
            grid-template-columns: repeat(3, 66.5mm);
            gap: 1.5mm 2mm;
            width: 204mm;
            justify-content: center;
        }
        .preset-sheet-30 .label-item {
            width: 66.5mm;
            height: 27.5mm;
            padding: 1mm 1.5mm;
        }
        .preset-sheet-30 .label-store-name { font-size: 7.5pt; margin-bottom: 0.3mm; }
        .preset-sheet-30 .label-item-name { font-size: 7pt; margin-bottom: 0.3mm; }
        .preset-sheet-30 .label-price { font-size: 7.5pt; margin-bottom: 0.3mm; }
        .preset-sheet-30 .label-barcode-svg { height: 8mm; margin-bottom: 0.3mm; }
        .preset-sheet-30 .label-barcode-code { font-size: 6pt; }

        /* ── PRESET 3: 12 Labels / Sheet (2 cols × 6 rows - large 105mm × 48mm) ── */
        .preset-sheet-12 {
            grid-template-columns: repeat(2, 98mm);
            gap: 2mm 3mm;
            width: 200mm;
            justify-content: center;
        }
        .preset-sheet-12 .label-item {
            width: 98mm;
            height: 46mm;
            padding: 2.5mm 3mm;
        }
        .preset-sheet-12 .label-store-name { font-size: 11pt; margin-bottom: 1mm; }
        .preset-sheet-12 .label-item-name { font-size: 9.5pt; margin-bottom: 1mm; }
        .preset-sheet-12 .label-price { font-size: 11pt; margin-bottom: 1mm; }
        .preset-sheet-12 .label-barcode-svg { height: 14mm; margin-bottom: 1mm; }
        .preset-sheet-12 .label-barcode-code { font-size: 8.5pt; }

        /* ── PRESET 4: 40 Labels / Sheet (4 cols × 10 rows - compact 48.5mm × 25.4mm) ── */
        .preset-sheet-40 {
            grid-template-columns: repeat(4, 48mm);
            gap: 1.5mm 1.5mm;
            width: 198mm;
            justify-content: center;
        }
        .preset-sheet-40 .label-item {
            width: 48mm;
            height: 25mm;
            padding: 0.8mm 1mm;
        }
        .preset-sheet-40 .label-store-name { font-size: 6.5pt; margin-bottom: 0.2mm; }
        .preset-sheet-40 .label-item-name { font-size: 6pt; margin-bottom: 0.2mm; }
        .preset-sheet-40 .label-price { font-size: 6.5pt; margin-bottom: 0.2mm; }
        .preset-sheet-40 .label-barcode-svg { height: 7mm; margin-bottom: 0.2mm; }
        .preset-sheet-40 .label-barcode-code { font-size: 5.5pt; }

        /* ── PRESET 5: Continuous Thermal Roll (Single Column - 50mm × 25mm) ── */
        .preset-thermal-roll {
            grid-template-columns: 50mm;
            gap: 2mm;
            width: 50mm;
            margin: 0 auto;
            justify-content: center;
        }
        .preset-thermal-roll .label-item {
            width: 50mm;
            height: 25mm;
            padding: 1mm;
            border: 1px dashed #cbd5e1;
        }
        .preset-thermal-roll .label-store-name { font-size: 7.5pt; margin-bottom: 0.3mm; }
        .preset-thermal-roll .label-item-name { font-size: 6.5pt; margin-bottom: 0.3mm; }
        .preset-thermal-roll .label-price { font-size: 7.5pt; margin-bottom: 0.3mm; }
        .preset-thermal-roll .label-barcode-svg { height: 7.5mm; margin-bottom: 0.3mm; }
        .preset-thermal-roll .label-barcode-code { font-size: 6pt; }

        /* Screen only hidden container for print area */
        @media screen {
            .print-only-container {
                display: none !important;
            }
        }

        /* ═══════════════════════════════════════════════════════════ */
        /* ROBUST PRINT CSS OVERRIDES                                  */
        /* ═══════════════════════════════════════════════════════════ */
        @media print {
            @page {
                size: auto;
                margin: 4mm;
            }

            /* Unblock parents from clipping multi-page output */
            html, body {
                width: 100% !important;
                min-height: 100% !important;
                height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Reset master layout wrapper */
            div[class*="min-h-screen"],
            div[class*="overflow-hidden"],
            main {
                overflow: visible !important;
                display: block !important;
                min-height: auto !important;
                height: auto !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                position: static !important;
            }

            /* Hide everything except the print-only container */
            header,
            footer,
            aside,
            nav,
            #sidebar,
            #top-navbar,
            .print\:hidden,
            button,
            a,
            [x-cloak],
            div[role="alert"] {
                display: none !important;
                visibility: hidden !important;
            }

            /* Render only print-only container */
            .print-only-container {
                display: block !important;
                visibility: visible !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
            }

            .label-item {
                border: 1px dashed #cbd5e1 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                background: #ffffff !important;
                color: #000000 !important;
            }
        }
    </style>

    @push('scripts')
    <script>
        function printLabelsApp() {
            return {
                queue: @json($initialItems ?? []),
                searchQuery: '',
                searchResults: [],
                isSearching: false,
                showDropdown: false,
                activeTab: 'designer', // 'designer' or 'sheet_preview'
                
                // Batch Filters
                toggleBatchFilter: false,
                batchCategory: '',
                batchBrand: '',
                batchWarehouse: '',
                isLoadingBatch: false,

                // Customization Options
                preset: 'sheet_24',
                showStoreName: true,
                showItemName: true,
                showPrice: true,
                showBarcode: true,
                showItemCode: true,
                customStoreName: @json($storeName ?? 'COREVISYS POS'),
                currencySymbol: @json($currencySymbol ?? '$'),

                init() {
                    // Automatically switch to preview if preloaded with items
                    if (this.queue.length > 0 && new URLSearchParams(window.location.search).has('items')) {
                        this.activeTab = 'sheet_preview';
                    }
                },

                get totalLabelCount() {
                    return this.queue.reduce((acc, item) => acc + (parseInt(item.qty) || 0), 0);
                },

                get presetTitle() {
                    switch (this.preset) {
                        case 'sheet_24': return '24 Labels / Sheet (3×8 A4)';
                        case 'sheet_30': return '30 Labels / Sheet (3×10 A4)';
                        case 'sheet_12': return '12 Labels / Sheet (2×6 A4)';
                        case 'sheet_40': return '40 Labels / Sheet (4×10 A4)';
                        case 'thermal_roll': return 'Continuous Thermal Roll (50×25mm)';
                        default: return 'Label Sheet';
                    }
                },

                get sheetContainerStyle() {
                    switch (this.preset) {
                        case 'sheet_24':
                        case 'sheet_30':
                        case 'sheet_12':
                        case 'sheet_40':
                            return 'width: 210mm; min-height: 297mm;';
                        case 'thermal_roll':
                            return 'width: 70mm; min-height: auto;';
                        default:
                            return 'width: 210mm; min-height: 297mm;';
                    }
                },

                get previewItem() {
                    if (this.queue.length > 0) {
                        return this.queue[0];
                    }
                    return {
                        item_name: 'Sample Product Label',
                        item_code: 'IT-00001',
                        custom_barcode: '123456789012',
                        barcode_value: '123456789012',
                        sales_price: 150.00,
                        barcode_svg: '<svg width="150" height="30" viewBox="0 0 150 30" xmlns="http://www.w3.org/2000/svg"><rect width="150" height="30" fill="#fff"/><g fill="#000"><rect x="10" y="0" width="2" height="30"/><rect x="14" y="0" width="1" height="30"/><rect x="18" y="0" width="3" height="30"/><rect x="24" y="0" width="1" height="30"/><rect x="28" y="0" width="2" height="30"/><rect x="33" y="0" width="2" height="30"/><rect x="38" y="0" width="1" height="30"/><rect x="42" y="0" width="3" height="30"/><rect x="48" y="0" width="2" height="30"/><rect x="53" y="0" width="1" height="30"/><rect x="58" y="0" width="3" height="30"/><rect x="64" y="0" width="2" height="30"/><rect x="70" y="0" width="1" height="30"/><rect x="75" y="0" width="3" height="30"/><rect x="82" y="0" width="1" height="30"/><rect x="86" y="0" width="2" height="30"/><rect x="91" y="0" width="3" height="30"/><rect x="98" y="0" width="1" height="30"/><rect x="102" y="0" width="2" height="30"/><rect x="108" y="0" width="1" height="30"/><rect x="112" y="0" width="3" height="30"/><rect x="118" y="0" width="1" height="30"/><rect x="122" y="0" width="2" height="30"/><rect x="128" y="0" width="3" height="30"/><rect x="134" y="0" width="2" height="30"/></g></svg>'
                    };
                },

                get expandedStickersList() {
                    const list = [];
                    let uid = 1;
                    this.queue.forEach(item => {
                        const count = parseInt(item.qty) || 0;
                        for (let i = 0; i < count; i++) {
                            list.push({
                                ...item,
                                uid: uid++
                            });
                        }
                    });
                    return list;
                },

                formatCurrency(amount) {
                    const num = parseFloat(amount) || 0;
                    return this.currencySymbol + ' ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                async performSearch() {
                    const q = this.searchQuery.trim();
                    if (!q) {
                        this.searchResults = [];
                        this.showDropdown = false;
                        return;
                    }

                    this.isSearching = true;
                    try {
                        const response = await fetch(`/items/search-items?query=${encodeURIComponent(q)}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (response.ok) {
                            this.searchResults = await response.json();
                            this.showDropdown = this.searchResults.length > 0;
                        }
                    } catch (e) {
                        console.error('Failed to search items:', e);
                    } finally {
                        this.isSearching = false;
                    }
                },

                selectFirstResult() {
                    if (this.searchResults.length > 0) {
                        this.addItem(this.searchResults[0]);
                    }
                },

                addItem(item) {
                    const existing = this.queue.find(q => q.id === item.id);
                    if (existing) {
                        existing.qty = (parseInt(existing.qty) || 0) + 1;
                    } else {
                        this.queue.push({
                            ...item,
                            qty: 1
                        });
                    }
                    this.searchQuery = '';
                    this.searchResults = [];
                    this.showDropdown = false;
                },

                removeItem(index) {
                    this.queue.splice(index, 1);
                },

                clearQueue() {
                    this.queue = [];
                },

                setAllQuantities(qty) {
                    this.queue.forEach(item => item.qty = qty);
                },

                matchAllToStock() {
                    this.queue.forEach(item => {
                        const s = parseInt(item.stock);
                        item.qty = s > 0 ? s : 1;
                    });
                },

                async loadBatchItems() {
                    this.isLoadingBatch = true;
                    try {
                        const params = new URLSearchParams();
                        if (this.batchCategory) params.append('category_id', this.batchCategory);
                        if (this.batchBrand) params.append('brand_id', this.batchBrand);
                        if (this.batchWarehouse) params.append('warehouse_id', this.batchWarehouse);

                        const response = await fetch(`/items/labels/batch-items?${params.toString()}`, {
                            headers: { 'Accept': 'application/json' }
                        });

                        if (response.ok) {
                            const items = await response.json();
                            items.forEach(item => {
                                const existing = this.queue.find(q => q.id === item.id);
                                if (existing) {
                                    existing.qty = (parseInt(existing.qty) || 0) + 1;
                                } else {
                                    this.queue.push({
                                        ...item,
                                        qty: 1
                                    });
                                }
                            });
                            this.toggleBatchFilter = false;
                            this.activeTab = 'sheet_preview';
                        }
                    } catch (e) {
                        console.error('Batch load failed:', e);
                    } finally {
                        this.isLoadingBatch = false;
                    }
                },

                submitForPdf() {
                    if (this.totalLabelCount === 0) {
                        alert('Please add at least one item to the print queue.');
                        return;
                    }
                    // mode=download → attachment disposition, browser save dialog
                    const payload = this.queue.map(item => ({ id: item.id, qty: parseInt(item.qty) || 1 }));
                    document.getElementById('pdf-form-items').value = JSON.stringify(payload);
                    document.getElementById('pdf-form-preset').value = this.preset;
                    document.getElementById('pdf-form-store').value = this.customStoreName;
                    document.getElementById('pdf-form-show-store').value = this.showStoreName ? '1' : '0';
                    document.getElementById('pdf-form-show-name').value = this.showItemName ? '1' : '0';
                    document.getElementById('pdf-form-show-price').value = this.showPrice ? '1' : '0';
                    document.getElementById('pdf-form-show-barcode').value = this.showBarcode ? '1' : '0';
                    document.getElementById('pdf-form-show-code').value = this.showItemCode ? '1' : '0';
                    document.getElementById('pdf-submit-form').submit();
                },

                submitForPrint() {
                    if (this.totalLabelCount === 0) {
                        alert('Please add at least one item to the print queue.');
                        return;
                    }
                    // mode=print → inline disposition, new tab → native PDF viewer → user clicks Print
                    const payload = this.queue.map(item => ({ id: item.id, qty: parseInt(item.qty) || 1 }));
                    document.getElementById('pdf-print-items').value = JSON.stringify(payload);
                    document.getElementById('pdf-print-preset').value = this.preset;
                    document.getElementById('pdf-print-store').value = this.customStoreName;
                    document.getElementById('pdf-print-show-store').value = this.showStoreName ? '1' : '0';
                    document.getElementById('pdf-print-show-name').value = this.showItemName ? '1' : '0';
                    document.getElementById('pdf-print-show-price').value = this.showPrice ? '1' : '0';
                    document.getElementById('pdf-print-show-barcode').value = this.showBarcode ? '1' : '0';
                    document.getElementById('pdf-print-show-code').value = this.showItemCode ? '1' : '0';
                    document.getElementById('pdf-print-form').submit();
                }
            };
        }
    </script>
    @endpush

    {{-- Form 1: Download PDF (attachment disposition — browser save dialog) --}}
    <form id="pdf-submit-form" action="{{ route('items.labels.pdf') }}" method="POST" style="display:none;">
        @csrf
        <input type="hidden" name="mode"        value="download">
        <input type="hidden" id="pdf-form-items"        name="items">
        <input type="hidden" id="pdf-form-preset"       name="preset"        value="sheet_24">
        <input type="hidden" id="pdf-form-store"        name="store_name"    value="{{ $storeName ?? 'COREVISYS POS' }}">
        <input type="hidden" id="pdf-form-show-store"   name="show_store"    value="1">
        <input type="hidden" id="pdf-form-show-name"    name="show_name"     value="1">
        <input type="hidden" id="pdf-form-show-price"   name="show_price"    value="1">
        <input type="hidden" id="pdf-form-show-barcode" name="show_barcode"  value="1">
        <input type="hidden" id="pdf-form-show-code"    name="show_code"     value="1">
    </form>

    {{-- Form 2: Print Now (opens clean printable sheet in new tab and triggers browser print dialog immediately) --}}
    <form id="pdf-print-form" action="{{ route('items.labels.print') }}" method="POST" target="_blank" style="display:none;">
        @csrf
        <input type="hidden" id="pdf-print-items"        name="items">
        <input type="hidden" id="pdf-print-preset"       name="preset"        value="sheet_24">
        <input type="hidden" id="pdf-print-store"        name="store_name"    value="{{ $storeName ?? 'COREVISYS POS' }}">
        <input type="hidden" id="pdf-print-show-store"   name="show_store"    value="1">
        <input type="hidden" id="pdf-print-show-name"    name="show_name"     value="1">
        <input type="hidden" id="pdf-print-show-price"   name="show_price"    value="1">
        <input type="hidden" id="pdf-print-show-barcode" name="show_barcode"  value="1">
        <input type="hidden" id="pdf-print-show-code"    name="show_code"     value="1">
    </form>
</x-app-layout>
