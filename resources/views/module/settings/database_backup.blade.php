<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<x-app-layout title="Database Backup">
    <div x-data="{
        backups: @js($backups),
        isGenerating: false,
        generateBackup() {
            this.isGenerating = true;
            fetch('{{ route('settings.backup.create') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                this.isGenerating = false;
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                this.isGenerating = false;
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong while generating backup.'
                });
            });
        },
        deleteBackup(name) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will not be able to recover this backup file!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`{{ url('settings/database-backup/delete') }}/${name}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message
                            });
                        }
                    });
                }
            });
        }
    }">
                
        <!-- HEADER & ACTIONS -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                 <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Database Backup</h1>
                <div class="flex items-center gap-2 text-slate-400 font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary-600 transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-slate-600 text-[10px] font-black uppercase tracking-wider">Database Backup</span>
                </div>
            </div>

            <button @click="generateBackup()" 
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-300 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-200/50 dark:shadow-none hover:scale-105 active:scale-95 flex items-center gap-2">
                <template x-if="!isGenerating">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </template>
                <template x-if="isGenerating">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </template>
                <span x-text="isGenerating ? 'Generating...' : 'Create New Backup'"></span>
            </button>
        </div>

        <!-- BACKUP LIST CARD -->
        <div class="bg-white dark:bg-dark-card rounded-3xl border border-slate-100 dark:border-dark-border overflow-hidden shadow-sm" x-data="{
            searchTerm: '',
            selectedAll: false,
            selectedRecords: [],
            get filteredBackups() {
                return $data.backups.filter(b => b.name.toLowerCase().includes(this.searchTerm.toLowerCase()));
            },
            toggleAll() {
                if (this.selectedAll) {
                    this.selectedRecords = this.filteredBackups.map(b => b.id);
                } else {
                    this.selectedRecords = [];
                }
            }
        }">
            
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
                     <div class="relative group">
                        <input type="text" x-model="searchTerm" placeholder="Search backups..." class="bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-xl py-1.5 px-8 text-[10px] font-bold focus:ring-1 focus:ring-primary-500 outline-none w-48 shadow-sm transition-all">
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
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest">Backup File Information</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Size</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Creation Date</th>
                            <th class="px-6 py-3 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-dark-border">
                        <tr x-show="filteredBackups.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                    <span class="text-[10px] font-black uppercase tracking-widest opacity-50 italic">No Backup Files Found</span>
                                </div>
                            </td>
                        </tr>
                        <template x-for="backup in filteredBackups" :key="backup.id">
                            <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/30 transition-colors group">
                                <td class="px-6 py-2.5 text-center">
                                    <input type="checkbox" :value="backup.id" x-model="selectedRecords" class="w-3.5 h-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </td>
                                <td class="px-6 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-xl bg-primary-50 dark:bg-primary-900/10 flex items-center justify-center text-primary-600 border border-primary-100 dark:border-primary-900/20 shadow-sm transition-transform group-hover:scale-110">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200" x-text="backup.name"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-2.5 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest italic" x-text="backup.size"></td>
                                <td class="px-6 py-2.5 text-center text-[10px] font-bold text-slate-500" x-text="backup.date"></td>
                                <td class="px-6 py-2.5 text-center">
                                     <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button @click="open = !open" class="px-3 py-1 bg-rose-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-2 hover:bg-rose-700 transition-all shadow-sm">
                                            Action
                                            <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                        </button>
                                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-36 origin-top-right rounded-xl bg-white dark:bg-dark-card shadow-2xl border border-slate-100 dark:border-dark-border z-20 overflow-hidden text-left" x-cloak>
                                            <div class="py-1">
                                                <a :href="`{{ url('settings/database-backup/download') }}/${backup.name}`" class="block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest">Download</a>
                                                <button @click="deleteBackup(backup.name)" class="w-full text-left block px-4 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-500/10 transition-colors uppercase tracking-widest border-t border-slate-50 dark:border-dark-border text-rose-600">Delete Permanently</button>
                                            </div>
                                        </div>
                                     </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- INFO SECTION -->
        <div class="mt-6 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-xl p-4 flex gap-3 items-start shadow-sm">
            <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg text-amber-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <h4 class="text-[10px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-400 mb-0.5">Safety First</h4>
                <p class="text-[10px] font-bold text-amber-700 dark:text-amber-500/80 leading-relaxed">
                    It is recommended to create a database backup before performing any major system updates or data imports. Always keep your backup files in a secure location.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
