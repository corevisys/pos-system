<x-app-layout title="Edit Role">
    <div>
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-black tracking-tight text-text-primary dark:text-dark-text">Configure Role <span class="text-[10px] font-bold text-text-muted ml-2 italic uppercase tracking-widest">{{ $role->role_name }}</span></h1>
                <div class="flex items-center gap-2 text-text-muted font-medium mt-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors text-[10px] flex items-center gap-1 font-bold uppercase tracking-wider">
                         <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                         Home
                    </a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <a href="{{ route('users.roles') }}" class="hover:text-primary transition-colors text-[10px] font-bold uppercase tracking-wider">Roles List</a>
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    <span class="text-text-secondary text-[10px] font-black uppercase tracking-wider">Edit Role</span>
                </div>
            </div>

            <a href="{{ route('users.roles') }}" class="btn-secondary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Cancel
            </a>
        </div>

        <x-card class="relative overflow-hidden"
             x-data="{ 
                selectedPermissions: {{ json_encode($role->permissions->permissions ?? []) }},
                allPermissions: [],
                init() {
                    const checkboxes = this.$root.querySelectorAll('input[type=checkbox][name=' + 'permissions[]' + ']');
                    this.allPermissions = Array.from(checkboxes).map(cb => cb.value);
                },
                toggleModule(moduleId, select) {
                    const moduleCheckboxes = this.$root.querySelectorAll(`[data-module='${moduleId}']`);
                    const modulePerms = Array.from(moduleCheckboxes).map(cb => cb.value);
                    if (select) {
                        this.selectedPermissions = [...new Set([...this.selectedPermissions, ...modulePerms])];
                    } else {
                        this.selectedPermissions = this.selectedPermissions.filter(p => !modulePerms.includes(p));
                    }
                },
                isModuleSelected(moduleId) {
                    const moduleCheckboxes = this.$root.querySelectorAll(`[data-module='${moduleId}']`);
                    if (moduleCheckboxes.length === 0) return false;
                    return Array.from(moduleCheckboxes).every(cb => this.selectedPermissions.includes(cb.value));
                }
             }">
             <div class="absolute top-0 right-0 w-64 h-64 bg-primary-light rounded-full blur-3xl -mr-20 -mt-20"></div>
            
            <form action="{{ route('users.roles.update', $role->id) }}" method="POST" class="space-y-6 relative z-10">
                @csrf
                @method('PUT')
                
                <!-- Basic Info Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" value="{{ old('role_name', $role->role_name) }}" required class="input-base">
                    </div>
                    
                    <div class="group relative">
                        <label class="absolute -top-1.5 left-3 bg-card dark:bg-dark-card px-1 text-[9px] font-black uppercase text-text-muted tracking-widest z-10">Description</label>
                        <textarea name="description" rows="1" class="input-base min-h-[38px]">{{ old('description', $role->description) }}</textarea>
                    </div>
                </div>

                <!-- Permissions Section -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-success-light text-success rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-text-secondary dark:text-dark-text italic">Security Privileges</h4>
                        </div>
                        <div class="flex items-center gap-3">
                             <button type="button" @click="selectedPermissions = [...allPermissions]" class="btn-ghost px-2 py-1 text-[8px] font-black uppercase tracking-widest">Select All</button>
                             <div class="w-1 h-1 rounded-full bg-border dark:bg-dark-border"></div>
                             <button type="button" @click="selectedPermissions = []" class="btn-ghost px-2 py-1 text-[8px] font-black uppercase tracking-widest">Clear</button>
                        </div>
                    </div>

                    <div class="card p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-background dark:bg-dark-bg">
                                    <th class="py-3 px-4 text-[9px] font-black text-text-muted uppercase tracking-widest border-b border-border dark:border-dark-border">#</th>
                                    <th class="py-3 px-4 text-[9px] font-black text-text-muted uppercase tracking-widest border-b border-border dark:border-dark-border">Modules</th>
                                    <th class="py-3 px-4 text-[9px] font-black text-text-muted uppercase tracking-widest border-b border-border dark:border-dark-border text-center">Select All</th>
                                    <th class="py-3 px-4 text-[9px] font-black text-text-muted uppercase tracking-widest border-b border-border dark:border-dark-border">Specific Permissions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-dark-border">
                                @php
                                    $modules = [
                                        ['id' => 1, 'name' => 'Users', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 2, 'name' => 'Roles', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 3, 'name' => 'Tax', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 4, 'name' => 'Units', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 5, 'name' => 'Payment Types', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 6, 'name' => 'Warehouse', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 7, 'name' => 'Store(Own Store)', 'perms' => ['Edit']],
                                        ['id' => 8, 'name' => 'Dashboard', 'perms' => ['View Dashboard Data', 'Information Box 1', 'Information Box 2', 'Purchase And Sales Chart', 'Recently Added Items List', 'Stock Alert List', 'Trending Items Chart', 'Recent Sales Invoice List']],
                                        ['id' => 9, 'name' => 'Cash Reconciliation', 'perms' => ['View', 'Add', 'Adjust', 'Delete']],
                                        ['id' => 10, 'name' => 'Accounts', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Add Money Deposit', 'Edit Money Deposit', 'Delete Money Deposit', 'View Money Deposit', 'Add Money Transfer', 'Edit Money Transfer', 'Delete Money Transfer', 'View Money Transfer', 'Cash Transactions'], 'perms_map' => ['Add Money Deposit' => 'money_deposit_add', 'Edit Money Deposit' => 'money_deposit_edit', 'Delete Money Deposit' => 'money_deposit_delete', 'View Money Deposit' => 'money_deposit_view', 'Add Money Transfer' => 'money_transfer_add', 'Edit Money Transfer' => 'money_transfer_edit', 'Delete Money Transfer' => 'money_transfer_delete', 'View Money Transfer' => 'money_transfer_view', 'Cash Transactions' => 'cash_transactions']],
                                        ['id' => 11, 'name' => 'Expense', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Category Add', 'Category Edit', 'Category Delete', 'Category View', 'Show all users Expenses']],
                                        ['id' => 12, 'name' => 'Items', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Category Add', 'Category Edit', 'Category Delete', 'Category View', 'Print Labels', 'Import Items', 'Import Services']],
                                        ['id' => 13, 'name' => 'Services', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 14, 'name' => 'Stock Transfer', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 15, 'name' => 'Stock Adjustment', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 16, 'name' => 'Brand', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 17, 'name' => 'Variant', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 18, 'name' => 'Suppliers', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Import Suppliers']],
                                        ['id' => 19, 'name' => 'Customers', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Import Customers']],
                                        ['id' => 20, 'name' => 'Customers Advance Payments', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 21, 'name' => 'Purchase', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Purchase Payments View', 'Purchase Payments Add', 'Purchase Payments Delete', 'Show all users Purchase Invoices']],
                                        ['id' => 22, 'name' => 'Purchase Return', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Purchase Return Payments View', 'Purchase Return Payments Add', 'Purchase Return Payments Delete', 'Show all users Purchase Return Invoices']],
                                        ['id' => 23, 'name' => 'Sales (Include POS)', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Sales Payments View', 'Sales Payments Add', 'Sales Payments Delete', 'Show all users Sales Invoices', 'Show Item Purchase Price(While making invoice)']],
                                        ['id' => 24, 'name' => 'Discount Coupon', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 25, 'name' => 'Customer Coupon', 'perms' => ['Add', 'Edit', 'Delete', 'View']],
                                        ['id' => 26, 'name' => 'Quotation', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Show all users Quotations']],
                                        ['id' => 27, 'name' => 'Sales Return', 'perms' => ['Add', 'Edit', 'Delete', 'View', 'Sales Return Payments View', 'Sales Return Payments Add', 'Sales Return Payments Delete', 'Show all users Sales Return Invoices']],
                                        ['id' => 28, 'name' => 'SMS/WhatsApp', 'perms' => ['Message Settings', 'Send Message', 'Message Template Edit', 'Message Template View', 'Message API View', 'Message API Edit']],
                                        ['id' => 30, 'name' => 'Reports', 'perms' => ['Delivery Sheet Report', 'Load Sheet Report', 'Customer Orders Report', 'Sales Tax Report', 'Purchase Tax Report', 'Supplier Items Report', 'Sales Report', 'Sales Return Report', 'Seller Points Report', 'Purchase Report', 'Purchase Return Report', 'Expense Report', 'Profit Report', 'Stock Report', 'Sales Item Report', 'Return Items Report', 'Purchase Payments Report', 'Sales Payments Report', 'GSTR-1 Report', 'GSTR-2 Report', 'Sales GST Report', 'Purchase GST Report']],
                                        ['id' => 31, 'name' => 'Help Documentation Link', 'perms' => ['Show Link (This link is always public)']],
                                    ];
                                @endphp

                                @foreach($modules as $m)
                                <tr class="hover:bg-background/50 dark:hover:bg-white/5 transition-colors">
                                    <td class="py-2.5 px-4 text-[10px] font-bold text-text-muted">{{ $m['id'] }}</td>
                                    <td class="py-2.5 px-4 text-[11px] font-semibold text-text-primary dark:text-dark-text">{{ $m['name'] }}</td>
                                    <td class="py-2.5 px-4 text-center">
                                        <input type="checkbox" 
                                               @change="toggleModule('{{ $m['name'] }}', $el.checked)"
                                               :checked="isModuleSelected('{{ $m['name'] }}')"
                                               class="w-4 h-4 rounded text-primary focus:ring-primary/30 transition-all cursor-pointer">
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                                            @foreach($m['perms'] as $p)
                                                @php $p_slug = ($m['perms_map'][$p] ?? \Illuminate\Support\Str::slug($m['name'] . ' ' . $p, '_')); @endphp
                                                <label class="flex items-center gap-1.5 cursor-pointer group">
                                                    <input type="checkbox" 
                                                           name="permissions[]" 
                                                           value="{{ $p_slug }}" 
                                                           x-model="selectedPermissions"
                                                           data-module="{{ $m['name'] }}"
                                                           class="w-3.5 h-3.5 rounded text-primary focus:ring-primary/30 transition-all">
                                                    <span class="text-[10px] font-bold text-text-secondary group-hover:text-text-primary dark:text-dark-text dark:group-hover:text-dark-text transition-colors">{{ $p }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 pt-6 border-t border-border dark:border-dark-border">
                    <div class="flex items-center gap-2 text-text-muted">
                         <svg class="w-4 h-4 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                         <p class="text-[9px] font-bold italic uppercase tracking-wider text-warning">Affects <span class="font-black underline decoration-warning/30">{{ count($role->users ?? []) }} Staff</span> immediately.</p>
                    </div>
                    <div class="flex gap-2 w-full md:w-auto">
                        <button type="submit" class="btn-primary flex-1 md:flex-none px-8">
                             <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                             Save Changes
                        </button>
                        <a href="{{ route('users.roles') }}" class="btn-secondary flex-1 md:flex-none px-8">Cancel</a>
                    </div>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
