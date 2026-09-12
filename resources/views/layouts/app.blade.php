<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" :class="{ 'dark': darkMode }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $pageName = $title ?? $attributes->get('title') ?? View::yieldContent('title', 'Dashboard');
        $storeName = store_settings()?->store_name ?? auth()->user()?->store?->store_name ?? config('app.name', 'CorevisysPOS');
        $tabTitle = $pageName ? "{$pageName} - {$storeName}" : $storeName;
    @endphp
    <title>{{ $tabTitle }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

</head>

<body class="bg-slate-50 dark:bg-dark-bg font-sans text-slate-900 dark:text-dark-text transition-colors duration-300">

    <div class="flex min-h-screen overflow-hidden" x-data="{ 
            notificationsOpen: false, 
            profileOpen: false,
            sidebarOpen: true,
            handleMenuClick(menu, url, event) {
                if (!this.sidebarOpen) {
                    if (event && (event.ctrlKey || event.metaKey || event.button === 1)) {
                        window.open(url, '_blank');
                    } else {
                        window.location.href = url;
                    }
                } else {
                    this.toggleMenu(menu);
                }
            },
            triggerUpgradeAction() {
                if (window.showSuccess) {
                    window.showSuccess('Corevisys POS Pro: Advanced insights & multi-store support are coming soon!');
                } else {
                    alert('Corevisys POS Pro: Advanced insights & multi-store support are coming soon!');
                }
            },
            expandedMenus: {
                'users': {{ request()->routeIs('users.*') ? 'true' : 'false' }},
                'sales': {{ request()->routeIs('sales.*') ? 'true' : 'false' }},
                'contacts': {{ request()->routeIs('contacts.*') ? 'true' : 'false' }},
                'advance': {{ request()->routeIs('advance.*') ? 'true' : 'false' }},
                'coupons': {{ request()->routeIs('coupons.*') ? 'true' : 'false' }},
                'quotation': {{ request()->routeIs('quotation.*') ? 'true' : 'false' }},
                'purchase': {{ request()->routeIs('purchase.*') ? 'true' : 'false' }},
                'accounts': {{ request()->routeIs('accounts.*') ? 'true' : 'false' }},
                'items': {{ request()->routeIs('items.*') ? 'true' : 'false' }},
                'stock': {{ request()->routeIs('stock.*') ? 'true' : 'false' }},
                'expenses': {{ request()->routeIs('expenses.*') ? 'true' : 'false' }},
                'messaging': {{ (request()->routeIs('messaging.*') || request()->routeIs('sms.*')) ? 'true' : 'false' }},
                'reports': {{ request()->routeIs('reports.*') ? 'true' : 'false' }},
                'warehouse': {{ request()->routeIs('warehouse.*') ? 'true' : 'false' }},
                'settings': {{ request()->routeIs('settings.*') ? 'true' : 'false' }},
            },
            searchQuery: '',
            toggleMenu(menu) {
                this.expandedMenus[menu] = !this.expandedMenus[menu];
            }
         }">

        <!-- MOBILE OVERLAY -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
            x-cloak>
        </div>

        <!-- SIDEBAR -->
        <aside
            class="app-sidebar fixed inset-y-0 left-0 z-50 w-72 transition-all duration-300 transform"
            :class="sidebarOpen ? 'translate-x-0 lg:w-72' : '-translate-x-full lg:translate-x-0 lg:w-[72px] sidebar-collapsed'">

            <div class="flex flex-col h-full">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}"
                    title="Go to Dashboard"
                    class="sidebar-logo p-4 flex items-center gap-3 group rounded-2xl hover:bg-slate-100 dark:hover:bg-white/5 transition-all duration-200 cursor-pointer">
                    <div
                        class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-sm shrink-0 group-hover:scale-105 group-hover:bg-primary-hover transition-all duration-200">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white uppercase transition-all duration-200"
                        :class="sidebarOpen ? 'opacity-100' : 'opacity-0'">Corevisys<span
                            class="text-primary-600 group-hover:text-primary-700 dark:text-primary-400 dark:group-hover:text-primary-300">POS</span></span>
                </a>

                <!-- Store Info Section -->
                @if(auth()->user()->store)
                    <div class="px-4 pb-4 transition-all duration-300"
                        :class="sidebarOpen ? 'opacity-100 h-auto' : 'opacity-0 h-0 overflow-hidden'">
                        <div
                            class="p-2.5 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-100 dark:border-white/10 flex gap-2.5 items-start">
                            <div
                                class="w-8 h-8 rounded-lg bg-white dark:bg-white/10 border border-slate-200 dark:border-white/10 flex items-center justify-center shrink-0 overflow-hidden">
                                @if(auth()->user()->store->store_logo)
                                    <img src="{{ asset('storage/' . auth()->user()->store->store_logo) }}"
                                        alt="{{ auth()->user()->store->store_name }}"
                                        class="w-full h-full object-cover">
                                @else
                                    <svg class="w-4 h-4 text-primary-500 dark:text-primary-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                @endif
                            </div>
                            <div class="overflow-hidden min-w-0 flex-1">
                                <p class="text-[11px] font-black text-slate-700 dark:text-white truncate">
                                    {{ auth()->user()->store->store_name }}</p>
                                <p class="text-[9px] font-bold text-slate-400 mt-0.5 line-clamp-2 leading-tight">
                                    {{ auth()->user()->store->address }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Navigation -->
                <nav class="flex-1 px-4 space-y-1 overflow-y-auto scrollbar-hide pb-20">

                    @if(auth()->user()->hasPermission('dashboard_view_dashboard_data'))
                        <a href="{{ route('dashboard') }}"
                            class="relative flex items-center w-full gap-2 px-3 py-2 rounded-lg transition-all duration-200 group text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-primary text-white' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                </path>
                            </svg>
                            <span>Dashboard</span>
                            <x-sidebar-tooltip text="Dashboard" />
                        </a>
                    @endif

                    {{-- Multi-Store Dashboard (Super Admin only) --}}
                    @if(auth()->user()->isSuperAdmin() && auth()->user()->hasPermission('multi_store_dashboard_view'))
                        <a href="{{ route('multi-store-dashboard') }}"
                            class="relative flex items-center w-full gap-2 px-3 py-2 rounded-lg transition-all duration-200 group text-sm font-medium {{ request()->routeIs('multi-store-dashboard') ? 'bg-primary text-white' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                </path>
                            </svg>
                            <span>Multi-Store</span>
                            <x-sidebar-tooltip text="Multi-Store Dashboard" />
                        </a>
                    @endif

                    <!-- User Management -->

                    @if(auth()->user()->hasPermission('users_view') || auth()->user()->hasPermission('roles_view'))
                        @php
                            $usersDefaultUrl = auth()->user()->hasPermission('users_view') ? route('users.list') : route('users.roles');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('users', '{{ $usersDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                        </path>
                                    </svg>
                                    <span>Users</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['users'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Users" />
                            </button>
                            <div x-show="expandedMenus['users']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('users_view'))
                                    <a href="{{ route('users.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('users.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Users
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('roles_view'))
                                    <a href="{{ route('users.roles') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('users.roles') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Roles
                                        List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Sales -->
                    @if(auth()->user()->hasPermission('sales_view') || auth()->user()->hasPermission('sales_add') || auth()->user()->hasPermission('sales_return_view'))
                        @php
                            $salesDefaultUrl = auth()->user()->hasPermission('sales_add') ? route('sales.pos') : (auth()->user()->hasPermission('sales_view') ? route('sales.list') : route('sales.returns'));
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('sales', '{{ $salesDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                    <span>Sales</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['sales'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Sales" />
                            </button>
                            <div x-show="expandedMenus['sales']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('sales_add'))
                                    <a href="{{ route('sales.pos') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.pos') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">POS</a>
                                    <a href="{{ route('sales.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Sale</a>
                                @endif
                                @if(auth()->user()->hasPermission('sales_view'))
                                    <a href="{{ route('sales.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('sales_payment_view'))
                                    <a href="{{ route('sales.payments') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.payments') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                        Payments</a>
                                @endif
                                @if(auth()->user()->hasPermission('sales_return_view'))
                                    <a href="{{ route('sales.returns') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.returns') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                        Returns List</a>
                                @endif
                                @if(auth()->user()->hasPermission('sales_view'))
                                    <a href="{{ route('sales.emi.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.emi.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">EMI
                                        Sale List</a>
                                    <a href="{{ route('sales.hold.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sales.hold.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Hold
                                        Sales List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Contacts -->
                    @if(auth()->user()->hasPermission('customers_view') || auth()->user()->hasPermission('suppliers_view'))
                        @php
                            $contactsDefaultUrl = auth()->user()->hasPermission('customers_view') ? route('contacts.customers.list') : (auth()->user()->hasPermission('suppliers_view') ? route('contacts.suppliers.list') : route('contacts.customers.add'));
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('contacts', '{{ $contactsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                        </path>
                                    </svg>
                                    <span>Contacts</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['contacts'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Contacts" />
                            </button>
                            <div x-show="expandedMenus['contacts']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('customers_add'))
                                    <a href="{{ route('contacts.customers.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.customers.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Customer</a>
                                @endif
                                @if(auth()->user()->hasPermission('customers_view'))
                                    <a href="{{ route('contacts.customers.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.customers.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Customers
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('suppliers_add'))
                                    <a href="{{ route('contacts.suppliers.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.suppliers.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Supplier</a>
                                @endif
                                @if(auth()->user()->hasPermission('suppliers_view'))
                                    <a href="{{ route('contacts.suppliers.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.suppliers.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Suppliers
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('customers_import_customers'))
                                    <a href="{{ route('contacts.customers.import') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.customers.import') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Import
                                        Customers</a>
                                @endif
                                @if(auth()->user()->hasPermission('suppliers_import_suppliers'))
                                    <a href="{{ route('contacts.suppliers.import') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('contacts.suppliers.import') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Import
                                        Suppliers</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Advance -->
                    @if(auth()->user()->hasPermission('customers_advance_payments_view'))
                        @php
                            $advanceDefaultUrl = route('advance.list');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('advance', '{{ $advanceDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                        </path>
                                    </svg>
                                    <span>Advance</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['advance'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Advance" />
                            </button>
                            <div x-show="expandedMenus['advance']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('customers_advance_payments_add'))
                                    <a href="{{ route('advance.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('advance.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Advance</a>
                                @endif
                                <a href="{{ route('advance.list') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('advance.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Advance
                                    List</a>
                            </div>
                        </div>
                    @endif

                    <!-- Coupons -->
                    @if(auth()->user()->hasPermission('discount_coupon_view') || auth()->user()->hasPermission('customer_coupon_view'))
                        @php
                            $couponsDefaultUrl = auth()->user()->hasPermission('customer_coupon_view') ? route('coupons.customer.list') : (auth()->user()->hasPermission('discount_coupon_view') ? route('coupons.master') : route('coupons.customer.create'));
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('coupons', '{{ $couponsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z">
                                        </path>
                                    </svg>
                                    <span>Coupons</span>
                                    <span
                                        class="bg-rose-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded ml-1 animate-pulse">NEW</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['coupons'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Coupons" />
                            </button>
                            <div x-show="expandedMenus['coupons']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('customer_coupon_add'))
                                    <a href="{{ route('coupons.customer.create') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('coupons.customer.create') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Create
                                        Customer Coupon</a>
                                @endif
                                @if(auth()->user()->hasPermission('customer_coupon_view'))
                                    <a href="{{ route('coupons.customer.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('coupons.customer.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Customer
                                        Coupons List</a>
                                @endif
                                @if(auth()->user()->hasPermission('discount_coupon_add'))
                                    <a href="{{ route('coupons.create') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('coupons.create') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Create
                                        Coupon</a>
                                @endif
                                @if(auth()->user()->hasPermission('discount_coupon_view'))
                                    <a href="{{ route('coupons.master') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('coupons.master') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Coupons
                                        Master</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Quotation -->
                    @if(auth()->user()->hasPermission('quotation_view'))
                        @php
                            $quotationDefaultUrl = route('quotation.list');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('quotation', '{{ $quotationDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    <span>Quotation</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['quotation'] ? 'rotate-180' : ''" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Quotation" />
                            </button>
                            <div x-show="expandedMenus['quotation']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('quotation_add'))
                                    <a href="{{ route('quotation.new') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('quotation.new') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">New
                                        Quotation</a>
                                @endif
                                <a href="{{ route('quotation.list') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('quotation.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Quotation
                                    List</a>
                            </div>
                        </div>
                    @endif

                    <!-- Purchase -->
                    @if(auth()->user()->hasPermission('purchase_view') || auth()->user()->hasPermission('purchase_add') || auth()->user()->hasPermission('purchase_return_view'))
                        @php
                            $purchaseDefaultUrl = auth()->user()->hasPermission('purchase_view') ? route('purchase.list') : (auth()->user()->hasPermission('purchase_add') ? route('purchase.new') : route('purchase.returns'));
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('purchase', '{{ $purchaseDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                    <span>Purchase</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['purchase'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Purchase" />
                            </button>
                            <div x-show="expandedMenus['purchase']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('purchase_add'))
                                    <a href="{{ route('purchase.new') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('purchase.new') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">New
                                        Purchase</a>
                                @endif
                                @if(auth()->user()->hasPermission('purchase_view'))
                                    <a href="{{ route('purchase.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('purchase.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('purchase_return_view'))
                                    <a href="{{ route('purchase.returns') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('purchase.returns') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                        Returns List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Accounts -->
                    @if(auth()->user()->hasPermission('accounts_view'))
                        @php
                            $accountsDefaultUrl = route('accounts.list');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('accounts', '{{ $accountsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                    <span>Accounts</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['accounts'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Accounts" />
                            </button>
                            <div x-show="expandedMenus['accounts']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('accounts_add'))
                                    <a href="{{ route('accounts.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('accounts.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Account</a>
                                @endif
                                <a href="{{ route('accounts.list') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('accounts.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Accounts
                                    List</a>
                                @if(auth()->user()->hasPermission('money_transfer_view'))
                                    <a href="{{ route('accounts.transfer') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('accounts.transfer') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Money
                                        Transfer List</a>
                                @endif
                                @if(auth()->user()->hasPermission('money_deposit_view'))
                                    <a href="{{ route('accounts.deposit') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('accounts.deposit') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Deposit
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('cash_transactions'))
                                    <a href="{{ route('accounts.transactions') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('accounts.transactions') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Cash
                                        Transactions</a>
                                @endif
                                @if(auth()->user()->hasPermission('cash_reconciliation_view'))
                                    <a href="{{ route('accounts.cash-reconciliation.index') }}"
                                        class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('accounts.cash-reconciliation.*') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                        <span>Cash Reconciliation</span>
                                        <span class="bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.2 rounded ml-1">NEW</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Items -->
                    @if(auth()->user()->hasPermission('items_view') || auth()->user()->hasPermission('services_view'))
                        @php
                            $itemsDefaultUrl = auth()->user()->hasPermission('items_view') ? route('items.list') : route('items.service.list');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('items', '{{ $itemsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 11m8 4V5M4 7v10l8 4"></path>
                                    </svg>
                                    <span>Items</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['items'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Items" />
                            </button>
                            <div x-show="expandedMenus['items']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('items_add'))
                                    <a href="{{ route('items.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Item</a>
                                @endif
                                @if(auth()->user()->hasPermission('services_add'))
                                    <a href="{{ route('items.service.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.service.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Service</a>
                                @endif
                                @if(auth()->user()->hasPermission('services_view'))
                                    <a href="{{ route('items.service.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.service.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Services
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('items_view'))
                                    <a href="{{ route('items.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Items
                                        List</a>
                                    <a href="{{ route('items.serial-history') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.serial-history') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Serial
                                        History</a>
                                @endif

                                @if(auth()->user()->hasPermission('items_category_view'))
                                    <a href="{{ route('items.categories') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.categories') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Categories
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('brand_view'))
                                    <a href="{{ route('items.brands') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.brands') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Brands
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('variant_view'))
                                    <a href="{{ route('items.variants') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.variants') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Variants
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('items_print_labels'))
                                    <a href="{{ route('items.labels') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.labels') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Print
                                        Labels</a>
                                @endif
                                @if(auth()->user()->hasPermission('items_import_items'))
                                    <a href="{{ route('items.import') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('items.import') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Import
                                        Items</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Stock -->
                    @if(auth()->user()->hasPermission('stock_adjustment_view') || auth()->user()->hasPermission('stock_transfer_view'))
                        @php
                            $stockDefaultUrl = auth()->user()->hasPermission('stock_adjustment_view') ? route('stock.adjustment') : route('stock.transfer');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('stock', '{{ $stockDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                        </path>
                                    </svg>
                                    <span>Stock</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['stock'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Stock" />
                            </button>
                            <div x-show="expandedMenus['stock']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('stock_adjustment_view'))
                                    <a href="{{ route('stock.adjustment') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('stock.adjustment') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Adjustment
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('stock_transfer_view'))
                                    <a href="{{ route('stock.transfer') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('stock.transfer') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Transfer
                                        List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Expenses -->
                    @if(auth()->user()->hasPermission('expense_view'))
                        @php
                            $expensesDefaultUrl = route('expenses.list');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('expenses', '{{ $expensesDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                        </path>
                                    </svg>
                                    <span>Expenses</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['expenses'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Expenses" />
                            </button>
                            <div x-show="expandedMenus['expenses']" x-collapse class="pl-12 space-y-1 mt-1">
                                <a href="{{ route('expenses.list') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('expenses.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Expenses
                                    List</a>
                                @if(auth()->user()->hasPermission('expense_category_view'))
                                    <a href="{{ route('expenses.categories') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('expenses.categories') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Categories
                                        List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Messaging -->
                    @if(auth()->user()->hasPermission('sms_whatsapp_send_message') || auth()->user()->hasPermission('sms_whatsapp_message_template_view') || auth()->user()->hasPermission('sms_whatsapp_message_api_view') || auth()->user()->hasPermission('sms_whatsapp_message_settings'))
                        @php
                            $messagingDefaultUrl = auth()->user()->hasPermission('sms_whatsapp_message_api_view') ? route('sms.history') : (auth()->user()->hasPermission('sms_whatsapp_send_message') ? route('sms.send') : (auth()->user()->hasPermission('sms_whatsapp_message_template_view') ? route('sms.templates') : route('messaging.settings')));
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('messaging', '{{ $messagingDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                        </path>
                                    </svg>
                                    <span>Messaging</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['messaging'] ? 'rotate-180' : ''" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Messaging" />
                            </button>
                            <div x-show="expandedMenus['messaging']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('sms_whatsapp_message_api_view'))
                                    <a href="{{ route('sms.history') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.history') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS
                                        History</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_whatsapp_send_message'))
                                    <a href="{{ route('sms.send') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.send') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Send
                                        SMS</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_whatsapp_message_template_view'))
                                    <a href="{{ route('sms.templates') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.templates') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS
                                        Templates</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_whatsapp_message_api_view'))
                                    <a href="{{ route('sms.campaigns') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.campaigns') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Campaigns</a>
                                    <a href="{{ route('sms.logs') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.logs') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS
                                        Logs</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_blacklist_view') || auth()->user()->isSuperAdmin())
                                    <a href="{{ route('sms.blacklist') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.blacklist') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS
                                        Blacklist</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_whatsapp_message_settings'))
                                    <a href="{{ route('sms.auto-rules') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.auto-rules') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Auto
                                        Rules</a>
                                    <a href="{{ route('messaging.settings') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('messaging.settings') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS
                                        Settings</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Reports -->
                    @if(auth()->user()->hasPermission('reports_view'))
                        @php
                            $reportsDefaultUrl = route('reports.sales_summary');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('reports', '{{ $reportsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                        </path>
                                    </svg>
                                    <span>Reports</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['reports'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Reports" />
                            </button>
                            <div x-show="expandedMenus['reports']" x-collapse class="pl-12 space-y-1 mt-1">
                                <a href="{{ route('reports.sales_summary') }}"
                                    class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_summary') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                    <span>Sales Summary</span> <span
                                        class="bg-emerald-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1 animate-pulse">NEW</span>
                                </a>
                                @if(auth()->user()->hasPermission('cash_reconciliation_report'))
                                    <a href="{{ route('reports.cash_reconciliation') }}"
                                        class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.cash_reconciliation*') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                        <span>Cash Reconciliation</span> <span
                                            class="bg-emerald-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1">NEW</span>
                                    </a>
                                @endif
                                @if(auth()->user()->hasPermission('reports_cash_flow_view') || auth()->user()->hasPermission('reports_view') || auth()->user()->isSuperAdmin())
                                    <a href="{{ route('reports.cash_flow') }}"
                                        class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.cash_flow*') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                        <span>Cash Flow Statement</span> <span
                                            class="bg-emerald-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1">NEW</span>
                                    </a>
                                @endif
                                <a href="{{ route('reports.profit_loss') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.profit_loss') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Profit
                                    & Loss Report</a>
                                <a href="{{ route('reports.sales_payment') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_payment') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    & Payment Report</a>
                                <a href="{{ route('reports.customer_orders') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.customer_orders') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Customer
                                    Orders</a>
                                <a href="{{ route('reports.gstr1') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.gstr1') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">GSTR-1
                                    Report</a>
                                <a href="{{ route('reports.gstr2') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.gstr2') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">GSTR-2
                                    Report</a>
                                <a href="{{ route('reports.sales_gst') }}"
                                    class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_gst') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                    <span>Sales GST Report</span> <span
                                        class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1 animate-pulse">NEW</span>
                                </a>
                                <a href="{{ route('reports.purchase_gst') }}"
                                    class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.purchase_gst') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                    <span>Purchase GST Report</span> <span
                                        class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1 animate-pulse">NEW</span>
                                </a>
                                <a href="{{ route('reports.sales_tax') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_tax') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    Tax Report</a>
                                <a href="{{ route('reports.purchase_tax') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.purchase_tax') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                    Tax Report</a>
                                <a href="{{ route('reports.supplier_items') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.supplier_items') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Supplier
                                    Items Report</a>
                                <a href="{{ route('reports.sales') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    Report</a>
                                <a href="{{ route('reports.sales_return') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_return') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    Return Report</a>
                                <a href="{{ route('reports.seller_points') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.seller_points') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Seller
                                    Points Report</a>
                                <a href="{{ route('reports.purchase') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.purchase') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                    Report</a>
                                <a href="{{ route('reports.purchase_return') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.purchase_return') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                    Return Report</a>
                                <a href="{{ route('reports.expense') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.expense') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Expense
                                    Report</a>
                                <a href="{{ route('reports.stock') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.stock') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Stock
                                    Report</a>
                                <a href="{{ route('reports.sales_item') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_item') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    Item Report</a>
                                <a href="{{ route('reports.return_items') }}"
                                    class="flex items-center justify-between w-full text-left text-sm py-2 {{ request()->routeIs('reports.return_items') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">
                                    <span>Return Items Report</span> <span
                                        class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1 animate-pulse">NEW</span>
                                </a>
                                <a href="{{ route('reports.purchase_payments') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.purchase_payments') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Purchase
                                    Payments Report</a>
                                <a href="{{ route('reports.sales_payments') }}"
                                    class="block w-full text-left text-sm py-2 {{ request()->routeIs('reports.sales_payments') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Sales
                                    Payments Report</a>
                            </div>
                        </div>
                    @endif

                    <!-- Warehouse -->
                    @if(auth()->user()->hasPermission('warehouse_view'))
                        @php
                            $warehouseDefaultUrl = auth()->user()->hasPermission('warehouse_view') ? route('warehouse.list') : route('warehouse.add');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('warehouse', '{{ $warehouseDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                    <span>Warehouse</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['warehouse'] ? 'rotate-180' : ''" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Warehouse" />
                            </button>
                            <div x-show="expandedMenus['warehouse']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('warehouse_add'))
                                    <a href="{{ route('warehouse.add') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('warehouse.add') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Add
                                        Warehouse</a>
                                @endif
                                @if(auth()->user()->hasPermission('warehouse_view'))
                                    <a href="{{ route('warehouse.list') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('warehouse.list') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Warehouse
                                        List</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Settings -->
                    @if(auth()->user()->hasPermission('store_settings_view') || auth()->user()->hasPermission('language_view') || auth()->user()->hasPermission('country_view') || auth()->user()->hasPermission('state_view') || auth()->user()->hasPermission('tax_view') || auth()->user()->hasPermission('unit_view') || auth()->user()->hasPermission('payment_types_view') || auth()->user()->hasPermission('site_settings_view') || auth()->user()->hasPermission('smtp_settings_view') || auth()->user()->hasPermission('currency_view') || auth()->user()->hasPermission('change_password') || auth()->user()->hasPermission('database_backup') || auth()->user()->hasPermission('sms_whatsapp_message_settings'))
                        @php
                            $settingsDefaultUrl = auth()->user()->hasPermission('store_settings_view') ? route('settings.store') : route('settings.languages.index');
                        @endphp
                        <div>
                            <button type="button" @click="handleMenuClick('settings', '{{ $settingsDefaultUrl }}', $event)"
                                class="relative flex items-center w-full gap-2 px-3 py-2 rounded-xl transition-all duration-200 group font-bold text-[13px] text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 justify-between cursor-pointer">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                        </path>
                                    </svg>
                                    <span>Settings</span>
                                </div>
                                <svg class="w-3 h-3 transition-transform duration-200"
                                    :class="expandedMenus['settings'] ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                                <x-sidebar-tooltip text="Settings" />
                            </button>
                            <div x-show="expandedMenus['settings']" x-collapse class="pl-12 space-y-1 mt-1">
                                @if(auth()->user()->hasPermission('store_settings_view'))
                                    <a href="{{ route('settings.store') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.store') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Store</a>
                                @endif
                                @if(auth()->user()->hasPermission('language_view'))
                                    <a href="{{ route('settings.languages.index') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.languages.*') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Languages
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('country_view'))
                                    <a href="{{ route('settings.countries') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.countries') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Countries
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('state_view'))
                                    <a href="{{ route('settings.states') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.states') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">States
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('sms_whatsapp_message_settings'))
                                    <a href="{{ route('sms.settings') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('sms.settings') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMS/WhatsApp
                                        API</a>
                                @endif
                                @if(auth()->user()->hasPermission('tax_view'))
                                    <a href="{{ route('settings.tax') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.tax') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Tax
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('unit_view'))
                                    <a href="{{ route('settings.units') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.units') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Units
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('payment_types_view'))
                                    <a href="{{ route('settings.payment_types') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.payment_types') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Payment
                                        Types</a>
                                @endif
                                @if(auth()->user()->hasPermission('site_settings_view'))
                                    <a href="{{ route('settings.site_settings') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.site_settings') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Site
                                        Settings</a>
                                @endif
                                @if(auth()->user()->hasPermission('smtp_settings_view'))
                                    <a href="{{ route('settings.smtp') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.smtp') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">SMTP</a>
                                @endif
                                @if(auth()->user()->hasPermission('currency_view'))
                                    <a href="{{ route('settings.currency') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.currency') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Currency
                                        List</a>
                                @endif
                                @if(auth()->user()->hasPermission('change_password'))
                                    <a href="{{ route('settings.password') }}"
                                        class="block w-full text-left text-[13px] py-1.5 {{ request()->routeIs('settings.password') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Change
                                        Password</a>
                                @endif
                                @if(auth()->user()->hasPermission('database_backup'))
                                    <a href="{{ route('settings.backup') }}"
                                        class="block w-full text-left text-sm py-2 {{ request()->routeIs('settings.backup') ? 'text-primary-600 font-bold' : 'text-slate-500 hover:text-primary-600 dark:text-slate-400 dark:hover:text-primary-400' }}">Database
                                        Backup</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </nav>

                <!-- Sidebar Footer/Premium -->
                <div class="sidebar-upgrade-wrapper mt-auto">
                    <!-- Expanded state: full Upgrade card -->
                    <div x-show="sidebarOpen" x-cloak class="sidebar-upgrade-expanded p-4">
                        <div
                            class="p-3 bg-slate-50 dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-dark-border">
                            <p class="text-[9px] font-black text-primary-600 uppercase tracking-widest mb-1">Upgrade Now</p>
                            <p class="text-[10px] text-slate-500 font-medium mb-2">Advanced insights & multi-store support.
                            </p>
                            <button
                                type="button"
                                @click="triggerUpgradeAction()"
                                class="w-full py-1.5 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-lg text-[10px] font-bold hover:bg-slate-50 transition-colors cursor-pointer">Go
                                Pro</button>
                        </div>
                    </div>

                    <!-- Collapsed state: compact icon only -->
                    <div x-show="!sidebarOpen" x-cloak class="sidebar-upgrade-collapsed py-4 px-2 flex justify-center items-center">
                        <div class="relative group group/upgrade flex justify-center">
                            <button type="button"
                                @click="triggerUpgradeAction()"
                                title="Upgrade to Pro"
                                aria-label="Upgrade to Pro"
                                class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800 hover:bg-primary-50 dark:hover:bg-primary-950/40 border border-slate-200 dark:border-dark-border hover:border-primary-300 dark:hover:border-primary-700/50 text-primary-600 dark:text-primary-400 flex items-center justify-center transition-all shadow-xs hover:scale-105 cursor-pointer">
                                <svg class="w-5 h-5 transition-transform group-hover/upgrade:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                </svg>
                            </button>
                            <x-sidebar-tooltip text="Upgrade to Pro" />
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 transition-all duration-300 min-h-screen h-screen overflow-y-auto scroll-pb-16 pb-16" :class="sidebarOpen ? 'lg:ml-72' : 'lg:ml-[72px]'">

            <!-- TOP NAVIGATION -->
            <header
                class="sticky top-0 z-40 h-16 bg-white/80 dark:bg-dark-bg/80 backdrop-blur-xl border-b border-border dark:border-dark-border px-4 lg:px-6 flex items-center justify-between">

                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h8m-8 6h16"></path>
                        </svg>
                    </button>

                    @if(auth()->user()->store)
                        <a href="{{ route('dashboard') }}"
                            class="flex items-center gap-2 px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded-xl hover:border-primary-400 dark:hover:border-primary-500 transition-all group max-w-[100px] xs:max-w-[150px] sm:max-w-none">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></div>
                            <span
                                class="text-[9px] md:text-[10px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-widest group-hover:text-primary-600 transition-colors truncate">{{ auth()->user()->store->store_name }}</span>
                        </a>
                    @endif

                    {{-- ══════════════════════════════════════════════════════════
                         GLOBAL SEARCH TRIGGER BUTTON (Desktop & Mobile)
                    ══════════════════════════════════════════════════════════ --}}
                    <!-- Desktop Search Trigger -->
                    <button
                        type="button"
                        @click="$dispatch('open-global-search')"
                        class="hidden md:flex items-center gap-2.5 bg-slate-100/90 hover:bg-slate-200/70 dark:bg-slate-800/90 dark:hover:bg-slate-700/80 px-3 py-1.5 rounded-xl w-64 lg:w-80 border border-slate-200/80 dark:border-dark-border text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-all duration-150 group text-left cursor-pointer">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-primary-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span class="text-xs text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300 flex-1 truncate">Search anything…</span>
                        <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-medium font-mono text-slate-400 bg-white dark:bg-slate-900 border border-slate-200 dark:border-dark-border rounded-md shadow-xs shrink-0">
                            <span class="text-[9px]">Ctrl</span> K
                        </kbd>
                    </button>

                    <!-- Mobile Search Trigger Icon -->
                    <button
                        type="button"
                        @click="$dispatch('open-global-search')"
                        class="flex md:hidden p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors cursor-pointer"
                        title="Search (Ctrl+K)">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Keyboard Shortcuts Help Trigger -->
                    <button type="button"
                        @click="$dispatch('open-shortcuts-help')"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        title="Keyboard Shortcuts (Ctrl+Alt+?)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>

                    <!-- Light/Dark Toggle -->
                    <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <svg x-show="!darkMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                            </path>
                        </svg>
                        <svg x-show="darkMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                            </path>
                        </svg>
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="relative">
                        <button @click="profileOpen = !profileOpen; notificationsOpen = false"
                            class="flex items-center gap-2 p-1 pl-3 rounded-xl border border-slate-200 dark:border-dark-border bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 transition-colors">
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] font-bold leading-none">{{ Auth::user()->name }}</p>
                            </div>
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=2563eb&color=fff"
                                class="w-8 h-8 rounded-lg object-cover" alt="Avatar">
                        </button>
                        <!-- Dropdown Panel -->
                        <div x-show="profileOpen" @click.away="profileOpen = false" x-transition x-cloak
                            class="absolute right-0 mt-3 w-56 bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border rounded-3xl shadow-2xl overflow-hidden py-2 z-50">
                            <a href="{{ route('profile.edit') }}"
                                class="flex items-center gap-3 px-6 py-3 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile
                            </a>
                            <hr class="my-2 border-slate-100 dark:border-dark-border">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex items-center gap-3 w-full px-6 py-3 text-sm font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-left">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                        </path>
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-padding">
                {{ $slot }}
            </div>

            <!-- FOOTER -->
            <footer
                class="pointer-events-none fixed bottom-0 right-0 h-10 flex items-center justify-between px-6 border-t border-border dark:border-dark-border text-[9px] font-bold text-slate-400 uppercase tracking-widest bg-white/80 dark:bg-dark-bg/80 backdrop-blur-xl z-30 transition-all duration-300"
                :class="sidebarOpen ? 'lg:left-72' : 'lg:left-[72px]'">
                <div>&copy; 2026 COREVISYS POS INTEL. ALL RIGHTS RESERVED.</div>
                <div class="pointer-events-auto flex gap-4">
                    <a href="{{ route('legal.privacy') }}" class="hover:text-primary-600 transition-colors">Privacy</a>
                    <a href="{{ route('legal.terms') }}" class="hover:text-primary-600 transition-colors">Terms</a>
                    <a href="{{ route('docs.index') }}"
                        class="hover:text-primary-600 pl-3 border-l border-slate-200 dark:border-dark-border transition-colors">Docs</a>
                </div>
            </footer>
        </main>
    </div>
    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- GLOBAL SPOTLIGHT SEARCH MODAL (Command Palette)             --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div
        x-data="{
            open: false,
            query: '',
            results: {},
            totalCount: 0,
            loading: false,
            activeIdx: -1,
            searchRequestId: 0,
            searchController: null,

            icons: {
                'package':            'M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z',
                'wrench':             'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z',
                'user':               'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
                'truck':              'M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11a2 2 0 012 2v3m0 0h4l3 3v4h-7V8zM16 17a2 2 0 11-4 0 2 2 0 014 0zM7 17a2 2 0 11-4 0 2 2 0 014 0z',
                'file-text':          'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
                'shopping-cart':      'M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0',
                'clipboard-list':     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h4',
                'rotate-ccw':         'M1 4v6h6M3.51 15a9 9 0 101.85-4.36L1 10',
                'undo-2':             'M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 010 11H11',
                'arrow-left-right':   'M17 8l4 4-4 4M7 16l-4-4 4-4M3 12h18',
                'sliders-horizontal': 'M21 4H8M3 4h1M17 9h1M3 9h10M21 14H11M3 14h4M7 20h1M3 20h0',
                'receipt':            'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2',
                'shield-check':       'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4',
                'compass':            'M12 2a10 10 0 100 20 10 10 0 000-20zm3.59 7.41l-2.18 5.82a1 1 0 01-.58.58l-5.82 2.18a.5.5 0 01-.64-.64l2.18-5.82a1 1 0 01.58-.58l5.82-2.18a.5.5 0 01.64.64z',
            },

            openModal() {
                this.open = true;
                this.activeIdx = -1;
                this.$nextTick(() => {
                    const el = document.getElementById('global-command-palette-input');
                    if (el) el.focus();
                });
            },

            closeModal() {
                this.open = false;
                this.query = '';
                this.results = {};
                this.totalCount = 0;
                this.activeIdx = -1;
            },

            get flatResults() {
                const flat = [];
                for (const key in this.results) {
                    if (this.results[key] && this.results[key].items) {
                        this.results[key].items.forEach(item => flat.push(item));
                    }
                }
                return flat;
            },

            async doSearch() {
                const q = this.query.trim();
                const requestId = ++this.searchRequestId;
                if (this.searchController) {
                    this.searchController.abort();
                }
                if (q.length < 2) {
                    this.results = {};
                    this.totalCount = 0;
                    this.activeIdx = -1;
                    this.loading = false;
                    return;
                }
                this.loading = true;
                const controller = new AbortController();
                this.searchController = controller;
                try {
                    const res = await fetch('{{ route('global.search') }}?q=' + encodeURIComponent(q), {
                        signal: controller.signal,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (requestId !== this.searchRequestId) return;
                    this.results = data.categories || {};
                    this.totalCount = data.total_count || 0;
                    this.activeIdx = -1;
                } catch (e) {
                    if (e.name === 'AbortError' || requestId !== this.searchRequestId) return;
                    this.results = {};
                    this.totalCount = 0;
                } finally {
                    if (requestId === this.searchRequestId) {
                        this.loading = false;
                    }
                }
            },

            navigate(dir) {
                const flat = this.flatResults;
                if (!flat.length) return;
                this.activeIdx = Math.max(-1, Math.min(flat.length - 1, this.activeIdx + dir));
            },

            selectActive() {
                const flat = this.flatResults;
                if (this.activeIdx >= 0 && flat[this.activeIdx]) {
                    window.location.href = flat[this.activeIdx].url;
                }
            },

            isActive(item) {
                const flat = this.flatResults;
                return this.activeIdx >= 0 && flat[this.activeIdx] && flat[this.activeIdx].url === item.url;
            },
        }"
        @open-global-search.window="openModal()"
        @keydown.window.cmd.k.prevent="openModal()"
        @keydown.window.ctrl.k.prevent="openModal()"
        @keydown.escape.window="closeModal()"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true">

        {{-- Backdrop --}}
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeModal()"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        {{-- Centered Dialog Box --}}
        <div class="min-h-full flex items-start justify-center p-3 sm:p-4 pt-12 sm:pt-20">
            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                @click.stop
                class="relative z-10 w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-dark-card border border-slate-200 dark:border-dark-border text-left shadow-2xl transition-all">

                {{-- Search Header Bar --}}
                <div class="relative flex items-center px-4 py-3.5 border-b border-slate-100 dark:border-dark-border">
                    <div class="shrink-0 mr-3">
                        <svg x-show="!loading" class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <svg x-show="loading" x-cloak class="w-5 h-5 text-primary-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>

                    <input
                        id="global-command-palette-input"
                        type="text"
                        x-model="query"
                        @input.debounce.250ms="doSearch()"
                        @keydown.arrow-down.prevent="navigate(1)"
                        @keydown.arrow-up.prevent="navigate(-1)"
                        @keydown.enter.prevent="selectActive()"
                        placeholder="Search items, sales, customers, suppliers, purchases, quotations, users..."
                        autocomplete="off"
                        class="w-full bg-transparent border-0 p-0 text-sm sm:text-base text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0">

                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <button
                            x-show="query.length > 0"
                            x-cloak
                            @click="query = ''; results = {}; totalCount = 0; $nextTick(() => document.getElementById('global-command-palette-input').focus())"
                            class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono text-slate-400 bg-slate-100 dark:bg-slate-800 rounded border border-slate-200 dark:border-dark-border cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" @click="closeModal()">
                            ESC
                        </kbd>
                    </div>
                </div>

                {{-- Body / Results List --}}
                <div class="max-h-[60vh] overflow-y-auto px-2 py-2 scrollbar-thin">

                    {{-- Empty initial state (prompting user) --}}
                    <div x-show="query.trim().length < 2 && !loading" class="py-10 text-center text-slate-400">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Quick Global Search</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Type at least 2 characters to search across 13 system modules</p>
                    </div>

                    {{-- Loading state skeleton --}}
                    <div x-show="loading" class="p-3 space-y-2">
                        <template x-for="i in 3" :key="i">
                            <div class="flex items-center gap-3 px-3 py-2 animate-pulse">
                                <div class="w-8 h-8 bg-slate-100 dark:bg-slate-800 rounded-xl shrink-0"></div>
                                <div class="flex-1 space-y-1.5">
                                    <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-2/3"></div>
                                    <div class="h-2 bg-slate-100 dark:bg-slate-800 rounded w-1/3"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- No results state --}}
                    <div x-show="!loading && query.trim().length >= 2 && totalCount === 0" class="py-10 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">No matches found</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">No results matching &ldquo;<span x-text="query" class="font-bold text-slate-700 dark:text-slate-200"></span>&rdquo;</p>
                    </div>

                    {{-- Result Categories --}}
                    <div x-show="!loading && totalCount > 0">
                        <template x-for="(category, key) in results" :key="key">
                            <div x-show="category.items.length > 0" class="mb-3">
                                {{-- Category Header --}}
                                <div class="flex items-center gap-2 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <span x-text="category.label"></span>
                                    <div class="flex-1 h-px bg-slate-100 dark:bg-slate-800"></div>
                                </div>

                                {{-- Category Items --}}
                                <template x-for="item in category.items" :key="item.url">
                                    <a :href="item.url"
                                       :class="isActive(item) ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-900 dark:text-primary-100' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60 text-slate-700 dark:text-slate-200'"
                                       class="flex items-center gap-3 px-3 py-2 rounded-xl cursor-pointer transition-colors duration-100 group my-0.5">

                                        {{-- Icon --}}
                                        <div :class="isActive(item) ? 'bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 group-hover:bg-primary-50 dark:group-hover:bg-primary-500/10 group-hover:text-primary-600'"
                                             class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                <path :d="icons[category.icon] || icons['file-text']"/>
                                            </svg>
                                        </div>

                                        {{-- Title & Subtitle --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold truncate leading-snug" x-text="item.title"></p>
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate leading-snug mt-0.5" x-text="item.subtitle" x-show="item.subtitle"></p>
                                        </div>

                                        {{-- Price meta chip (Items & Services only) --}}
                                        <span
                                            x-show="item.meta"
                                            x-text="item.meta"
                                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 shrink-0 tracking-wide border border-emerald-100 dark:border-emerald-500/20">
                                        </span>

                                        {{-- Badge --}}
                                        <span class="text-[8px] font-black px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 shrink-0 tracking-wider" x-text="item.badge"></span>

                                        {{-- Arrow Enter Icon on Hover/Active --}}
                                        <svg :class="isActive(item) ? 'opacity-100 text-primary-500' : 'opacity-0 group-hover:opacity-100 text-slate-400'"
                                             class="w-3.5 h-3.5 shrink-0 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                        </svg>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>

                </div>

                {{-- Footer Bar --}}
                <div class="px-4 py-2.5 bg-slate-50/80 dark:bg-slate-800/40 border-t border-slate-100 dark:border-dark-border flex items-center justify-between text-[11px] text-slate-400">
                    <p class="text-[10px]">
                        <span x-show="totalCount > 0"><span x-text="totalCount" class="font-bold text-slate-600 dark:text-slate-300"></span> result<span x-show="totalCount !== 1">s</span></span>
                        <span x-show="totalCount === 0 && query.trim().length >= 2">0 results</span>
                        <span x-show="query.trim().length < 2">Ready to search</span>
                    </p>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400">
                        <span class="hidden sm:inline-flex items-center gap-1">
                            <kbd class="px-1 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded text-[9px] font-mono">↑</kbd>
                            <kbd class="px-1 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded text-[9px] font-mono">↓</kbd>
                            Navigate
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded text-[9px] font-mono">↵</kbd>
                            Select
                        </span>
                        <span class="hidden sm:inline-flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-dark-border rounded text-[9px] font-mono">ESC</kbd>
                            Close
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- GLOBAL NOTIFICATION TOAST SYSTEM                           --}}
    {{-- Renders toast UI + defines Alpine.store('notify') once.    --}}
    {{-- Flash session is read by the component's own x-init.       --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <x-notification-toast />

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TWO-KEY SEQUENCE SHORTCUTS SYSTEM & HELP MODAL              --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <x-keyboard-shortcuts />
    <x-keyboard-shortcuts-modal />

    @stack('scripts')


</body>

</html>
