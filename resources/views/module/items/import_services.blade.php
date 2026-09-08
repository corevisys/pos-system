<x-app-layout title="Import Services">
    <div>
        <!-- HEADER & BREADCRUMBS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Import Services</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('items.list') }}" class="hover:text-primary-600 transition-colors text-[10px] font-bold text-slate-400">Items List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Import Services</span>
                </div>
            </div>
            
            <div class="flex items-center gap-2 w-full md:w-auto">
                <button class="flex-grow md:flex-none px-4 py-1.5 bg-rose-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-rose-200/50 dark:shadow-none">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Sample Format
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <!-- IMPORT CONTROL CARD -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden p-4 md:p-6">
                <form action="#" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="hidden md:block"></div>

                        <!-- Import Services -->
                        <div class="group relative md:col-span-2">
                            <label class="absolute -top-2 left-3 bg-white dark:bg-dark-card px-1.5 text-[8px] font-black uppercase text-slate-400 tracking-widest z-10 transition-colors group-focus-within:text-emerald-500">
                                CSV File <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative w-full bg-slate-50 dark:bg-slate-800 rounded-xl flex items-center px-3 overflow-hidden h-[34px]">
                                <input type="file" name="import_file" class="absolute inset-0 opacity-0 cursor-pointer z-10">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    Choose File
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-2 h-[34px]">
                            <button type="submit" class="flex-1 bg-emerald-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-200/50 dark:shadow-none">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                Import
                            </button>
                            <a href="{{ route('items.list') }}" class="flex-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- INSTRUCTIONS SECTION -->
            <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border shadow-sm overflow-hidden" x-data="{
                instructions: [
                    { name: 'Service Name', status: 'Required', desc: '' },
                    { name: 'Category Name', status: 'Required', desc: '' },
                    { name: 'Price Before Tax', status: 'Required', desc: '' },
                    { name: 'Price After Tax', status: 'Required', desc: '' },
                    { name: 'Tax Name', status: 'Required', desc: '' },
                    { name: 'Tax Value', status: 'Required', desc: '' },
                    { name: 'Tax Type', status: 'Required', desc: '' },
                    { name: 'Sales Price', status: 'Required', desc: '' },
                    { name: 'HSN', status: 'Optional', desc: '' },
                    { name: 'Barcode', status: 'Optional', desc: '' },
                    { name: 'Seller Points', status: 'Optional', desc: '' },
                    { name: 'Description', status: 'Optional', desc: '' },
                    { name: 'Discount Type', status: 'Optional', desc: '“Percentage” or “Fixed”' },
                    { name: 'Discount', status: 'Optional', desc: '' }
                ]
            }">
                <div class="px-6 py-3 border-b border-slate-50 dark:border-dark-border">
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-800 dark:text-white">Import Instructions</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-dark-border">
                            <tr>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest w-12 text-center">#</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Column Name</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                            <template x-for="(inst, index) in instructions" :key="inst.name">
                                <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-6 py-2.5 text-[10px] font-black text-slate-300 text-center" x-text="index + 1"></td>
                                    <td class="px-6 py-2.5 text-[10px] font-bold text-slate-700 dark:text-slate-300" x-text="inst.name"></td>
                                    <td class="px-6 py-2.5 text-center">
                                        <span :class="inst.status === 'Required' ? 'bg-rose-500/10 text-rose-600 border-rose-500/20' : 'bg-slate-100 text-slate-400 border-slate-200'" class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border" x-text="inst.status"></span>
                                    </td>
                                    <td class="px-6 py-2.5 text-[9px] font-medium text-slate-400 text-right italic" x-text="inst.desc || '---'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- BOTTOM ACCENT -->
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-primary-500 to-rose-500 rounded-full"></div>
        </div>
    </div>
</x-app-layout>
