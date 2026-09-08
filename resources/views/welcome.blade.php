<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-seo-meta page-key="home" />
    @vite('resources/css/app.css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <!-- Swiper JS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <style>
        body { font-family: 'Hind Siliguri', 'Inter', sans-serif; }
        /* Swiper Customizations */
        .swiper-pagination-bullet-active {
            background: #2563eb !important;
        }
    </style>
</head>
<body class="bg-white text-slate-900 overflow-x-hidden">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 md:h-20">

                <!-- Logo -->
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 md:w-10 md:h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-terminal text-white text-lg md:text-xl"></i>
                    </div>
                    <span class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">
                        CoreVisys<span class="text-blue-600">POS</span>
                    </span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8 font-medium text-slate-600">
                    <a href="#features" class="hover:text-blue-600 transition">ফিচারসমূহ</a>
                    <a href="#comparison" class="hover:text-blue-600 transition">তুলনা</a>
                    <a href="#pricing" class="hover:text-blue-600 transition">মূল্য পরিকল্পনা</a>

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" 
                            class="bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-700 transition shadow-lg">
                                ড্যাশবোর্ড
                            </a>
                        @else
                            <a href="{{ route('login') }}" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-full hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                                লগইন
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" 
                                class="text-blue-600 hover:text-blue-800 transition">
                                    নিবন্ধন
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>

                <!-- Mobile -->
                <div class="md:hidden">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-blue-600 text-xl">
                            <i class="fas fa-user"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-blue-600 text-xl">
                            <i class="fas fa-sign-in-alt"></i>
                        </a>
                    @endauth
                </div>

            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-24 pb-16 gradient-bg hero-gradient px-4">
        <div class="max-w-7xl mx-auto flex flex-col items-center text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/80 border border-blue-100 text-blue-700 rounded-full text-sm font-semibold mb-8 shadow-sm">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span>
                </span>
                বাংলাদেশে ৫0+ জেলায় ৫০০+ ব্যবসায়ী আমাদের সাথে
            </div>
            
            <h1 class="text-3xl md:text-6xl font-extrabold leading-tight text-slate-900 max-w-4xl">
                প্রতিদিন কত লাভ করছেন জানেন?
                <br>
                <span class="text-blue-600">CoreVisys POS ছাড়া ব্যবসা মানে অন্ধভাবে দোকান চালানো।</span>
            </h1>
            
            <p class="mt-6 text-lg md:text-xl text-slate-700 max-w-2xl leading-relaxed">
                স্টক মিলছে না? বাকি টাকা আদায় হচ্ছে খন? কর্মচারীর হিসাব ঠিক আছে কিনা বুঝতে পারছেন না? এখন সব নিয়ন্ত্রণ থাকবে আপনার হাতে।
            </p>

            <div class="mt-10 flex flex-col items-center">
                <button class="bg-orange-500 hover:bg-orange-600 text-white px-10 py-5 rounded-2xl text-xl font-bold shadow-xl transition-all pulse-orange w-full sm:w-auto">
                    🔥 এখনই ফ্রি ডেমো নিন
                </button>
                <p class="text-sm text-slate-500 mt-4 flex items-center gap-3">
                    <span><i class="fas fa-check text-green-500"></i> কোনো অ্যাডভান্স লাগবে না</span>
                    <span><i class="fas fa-check text-green-500"></i> ১০ মিনিটে সেটআপ</span>
                </p>
            </div>

            <div class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <p class="text-2xl font-bold text-blue-600">৪.৯/৫ ⭐</p>
                    <p class="text-slate-600 text-sm">গুগল রেটিং</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-blue-600">১০,০০০+</p>
                    <p class="text-slate-600 text-sm">প্রোডাক্ট ম্যানেজড</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-blue-600">৫০+ জেলা</p>
                    <p class="text-slate-600 text-sm">কভারেজ</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-blue-600">২৪/৭</p>
                    <p class="text-slate-600 text-sm">কাস্টমার সাপোর্ট</p>
                </div>
            </div>
            
            <!-- SaaS Dashboard Carousel Component -->
            <div class="mt-16 w-full max-w-6xl relative z-10">
                
                <!-- Mode 2: Vertical Effect (Announcements / Alerts) -->
                <div class="w-full max-w-3xl mx-auto mb-10 h-[100px] sm:h-[120px] rounded-[16px] bg-white dark:bg-slate-900 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-slate-100 dark:border-slate-800 overflow-hidden relative">
                    <div class="swiper vertical-swiper h-full">
                        <div class="swiper-wrapper">
                            
                            <!-- Slide 1 -->
                            <div class="swiper-slide flex items-center justify-between px-6 md:px-8 bg-white dark:bg-slate-900 w-full h-full">
                                <div class="flex items-center gap-5">
                                    <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shrink-0">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    <div class="text-left">
                                        <h4 class="text-base md:text-lg font-bold text-slate-800 dark:text-white">
                                            নতুন ফিচার যুক্ত হয়েছে
                                        </h4>
                                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 truncate max-w-[180px] sm:max-w-xs md:max-w-md">
                                            এখন উন্নত রিপোর্ট ও অ্যানালিটিক্স ড্যাশবোর্ড দিয়ে প্রতিদিনের লাভ-ক্ষতি সহজে ট্র্যাক করুন।
                                        </p>
                                    </div>
                                </div>
                                <button class="hidden md:block px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition font-medium text-sm shrink-0">
                                    বিস্তারিত দেখুন
                                </button>
                            </div>

                            <!-- Slide 2 -->
                            <div class="swiper-slide flex items-center justify-between px-6 md:px-8 bg-white dark:bg-slate-900 w-full h-full">
                                <div class="flex items-center gap-5">
                                    <div class="w-12 h-12 rounded-full bg-orange-50 dark:bg-orange-900/30 text-orange-500 dark:text-orange-400 flex items-center justify-center text-xl shrink-0">
                                        <i class="fas fa-crown"></i>
                                    </div>
                                    <div class="text-left">
                                        <h4 class="text-base md:text-lg font-bold text-slate-800 dark:text-white">
                                            এন্টারপ্রাইজ প্ল্যানে আপগ্রেড করুন
                                        </h4>
                                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 truncate max-w-[180px] sm:max-w-xs md:max-w-md">
                                            আনলিমিটেড ব্রাঞ্চ, উন্নত কন্ট্রোল এবং ডেডিকেটেড সাপোর্ট ম্যানেজার সুবিধা নিন।
                                        </p>
                                    </div>
                                </div>
                                <button class="hidden md:block px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition font-medium text-sm shrink-0">
                                    এখনই আপগ্রেড করুন
                                </button>
                            </div>

                            <!-- Slide 3 -->
                            <div class="swiper-slide flex items-center justify-between px-6 md:px-8 bg-white dark:bg-slate-900 w-full h-full">
                                <div class="flex items-center gap-5">
                                    <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="text-left">
                                        <h4 class="text-base md:text-lg font-bold text-slate-800 dark:text-white">
                                            সিস্টেম রক্ষণাবেক্ষণ নোটিশ
                                        </h4>
                                        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 truncate max-w-[180px] sm:max-w-xs md:max-w-md">
                                            শনিবার রাত ২:০০ টায় সার্ভার আপগ্রেডের জন্য সাময়িক সিস্টেম আপডেট হবে।
                                        </p>
                                    </div>
                                </div>
                                <button class="hidden md:block px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition font-medium text-sm shrink-0">
                                    বিস্তারিত জানুন
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Mode 1: Coverflow Effect (Dashboard Mockups) -->
                <!-- Dashboard Mockup -->
                <div class="swiper coverflow-swiper w-full pt-4 pb-16 px-4">
                    <div class="swiper-wrapper">
                        <!-- Slide 1 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/dashboard.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=CoreVisys+Dashboard'" alt="CorevisysPOS Executive Analytics and Sales Dashboard Interface" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 2 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/pos.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Inventory+Management'" alt="CorevisysPOS Point of Sale Checkout and Real-Time Barcode Scanner" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 3 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/profit-loss.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS Profit and Loss Financial Accounting and COGS Reports" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 4 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/sms-api.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS Multi-Provider SMS Gateway and Automated Marketing Engine" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 5 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/sale.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS Multi-Warehouse Stock Sales Invoice Creation" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 6 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/massage-send.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS Targeted SMS Broadcast Campaign Dispatcher" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 7 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/massage.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS Customer Due Payment and EMI SMS Auto Rules" class="w-full h-auto object-cover">
                            </div>
                        </div>
                        <!-- Slide 8 -->
                        <div class="swiper-slide w-full max-w-3xl md:max-w-5xl transition-transform duration-500">
                            <div class="rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                                <img src="{{ asset('storage/frontend/emi.png') }}" onerror="this.src='https://placehold.co/1200x675/f8fafc/475569?text=Sales+Reporting'" alt="CorevisysPOS EMI Installment Schedule and Repayment Tracking" class="w-full h-auto object-cover">
                            </div>
                        </div>
                    </div>
                    <!-- Pagination -->
                    <div class="swiper-pagination !bottom-0"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Before vs After Section -->
    <section id="comparison" class="py-20 bg-slate-50 text-center px-4">
        <div class="max-w-7xl mx-auto">
            <h3 class="text-3xl md:text-4xl font-bold mb-4 text-slate-900">CoreVisys ব্যবহারের আগে ও পরে</h3>
            <p class="text-slate-600 mb-12 max-w-xl mx-auto text-lg">পার্থক্যটা নিজেই দেখুন এবং সিদ্ধান্ত নিন।</p>

            <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <!-- Before -->
                <div class="bg-red-50 p-10 rounded-3xl border border-red-100 text-left shadow-sm">
                    <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center text-red-600 mb-6 text-2xl">
                        <i class="fas fa-times"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-red-700 mb-6">❌ আগে</h4>
                    <ul class="space-y-4 text-slate-700 text-lg">
                        <li class="flex items-center gap-3"><i class="fas fa-minus-circle text-red-400"></i> জরাজীর্ণ খাতায় জটিল হিসাব</li>
                        <li class="flex items-center gap-3"><i class="fas fa-minus-circle text-red-400"></i> প্রতিদিনের লাভ অজানা থাকতো</li>
                        <li class="flex items-center gap-3"><i class="fas fa-minus-circle text-red-400"></i> স্টক মিলতো না, মাল চুরি হতো</li>
                        <li class="flex items-center gap-3"><i class="fas fa-minus-circle text-red-400"></i> বাকি টাকা আদায় ছিল যুদ্ধের মতো</li>
                    </ul>
                </div>

                <!-- After -->
                <div class="bg-green-50 p-10 rounded-3xl border border-green-100 text-left shadow-md ring-4 ring-green-100/50">
                    <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-6 text-2xl">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-green-700 mb-6">✅ পরে</h4>
                    <ul class="space-y-4 text-slate-700 text-lg">
                        <li class="flex items-center gap-3 font-semibold"><i class="fas fa-check-circle text-green-500"></i> ১ ক্লিকে ডিজিটাল সব হিসাব</li>
                        <li class="flex items-center gap-3 font-semibold"><i class="fas fa-check-circle text-green-500"></i> প্রতিদিনের লাভ রিপোর্ট মোবাইলে</li>
                        <li class="flex items-center gap-3 font-semibold"><i class="fas fa-check-circle text-green-500"></i> স্টক অটোমেটিক আপডেট থাকে</li>
                        <li class="flex items-center gap-3 font-semibold"><i class="fas fa-check-circle text-green-500"></i> কাস্টমারকে SMS রিমাইন্ডার</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Detailed Features Section -->
    <section id="features" class="py-24 bg-white px-4">
        <div class="max-w-7xl mx-auto text-center mb-16">
            <h2 class="text-blue-600 font-bold tracking-wider uppercase text-sm mb-4">আপনার ব্যবসার স্মার্ট সমাধান</h2>
            <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">ব্যবসার সব টেনশন এবার আমাদের ওপর ছেড়ে দিন</h3>
        </div>
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1: Inventory -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-boxes-stacked text-xl"></i>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">স্মার্ট ইনভেন্টরি ম্যানেজমেন্ট</h4>
                <p class="text-slate-600 leading-relaxed">মাল ফুরিয়ে যাওয়ার আগেই আপনাকে মোবাইলে জানাবে। বারকোড স্ক্যানিং এবং অটোমেটিক স্টক আপডেটের সুবিধা।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> লো-স্টক অ্যালার্ট</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> বারকোড জেনারেটর</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> মাল্টি-ক্যাটাগরি সাপোর্ট</li>
                </ul>
            </div>
            
            <!-- Feature 2: Due/EMI -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-hand-holding-dollar text-xl"></i>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">বাকি টাকা ও EMI ট্র্যাকিং</h4>
                <p class="text-slate-600 leading-relaxed">কার কাছে কত বাকি আছে তা অটোমেটিক হিসাব হবে। কাস্টমারকে ফ্রিতে SMS পাঠিয়ে টাকা আদায় করুন।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> কাস্টমার লেজার</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> অটোমেটিক SMS রিমাইন্ডার</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> EMI কিস্তি ম্যানেজমেন্ট</li>
                </ul>
            </div>

            <!-- Feature 3: Reports -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">অ্যাডভান্সড লাভ-ক্ষতি রিপোর্ট</h4>
                <p class="text-slate-600 leading-relaxed">দিনশেষে কত বিক্রি হলো আর কত টাকা লাভ থাকলো, তা এখন আপনার পকেটে থাকা মোবাইলে।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> ডেইলি/মান্থলি রিপোর্ট</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> এক্সপেন্স ট্র্যাকিং</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> ভ্যাট ও ডিসকাউন্ট ক্যালকুলেশন</li>
                </ul>
            </div>

            <!-- Feature 4: Staff/Security -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-user-lock text-xl"></i>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">কর্মচারী ও সিকিউরিটি</h4>
                <p class="text-slate-600 leading-relaxed">কর্মচারীরা কী করছে সব দেখতে পাবেন। ক্যাশ থেকে টাকা সরালে বা হিসাবে ভুল করলে সাথে সাথেই ধরা পড়বে।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> রোল বেসড এক্সেস</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> অ্যাক্টিভিটি লগ</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> ওটিপি সিকিউরিটি</li>
                </ul>
            </div>

            <!-- Feature 5: Multi-Branch -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-store text-xl"></i>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">মাল্টি-ব্রাঞ্চ কন্ট্রোল</h4>
                <p class="text-slate-600 leading-relaxed">আপনার যদি একাধিক দোকান থাকে, তবে এক জায়গা থেকেই সব নিয়ন্ত্রণ করতে পারবেন অনায়াসে।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> সেন্ট্রালাইজড ড্যাশবোর্ড</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> ইন্টার-ব্রাঞ্চ স্টক ট্রান্সফার</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> কম্বাইনড প্রফিট রিপোর্ট</li>
                </ul>
            </div>

            <!-- Feature 6: Offline & Mobile Support (Icon Updated) -->
            <div class="p-8 rounded-2xl border border-slate-100 hover:border-blue-200 hover:shadow-xl hover:scale-105 transition-all duration-300 group bg-slate-50">
                <div class="w-12 h-12 bg-white rounded-xl shadow-md flex items-center justify-center text-blue-600 mb-6 group-hover:bg-blue-600 group-hover:text-white transition relative">
                    <i class="fas fa-mobile-screen-button text-xl"></i>
                    <span class="absolute -top-1 -right-1 flex h-4 w-4">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-4 w-4 bg-blue-600 flex items-center justify-center">
                            <i class="fas fa-cloud-arrow-up text-[8px] text-white"></i>
                        </span>
                    </span>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-800">অফলাইন ও মোবাইল সাপোর্ট</h4>
                <p class="text-slate-600 leading-relaxed">ইন্টারনেট না থাকলেও বিক্রি থামবে না। পিসি, ল্যাপটপ বা অ্যান্ড্রয়েড মোবাইল দিয়েও চালানো যাবে।</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-500">
                    <li><i class="fas fa-caret-right text-blue-500"></i> অফলাইন সেলস সিঙ্ক</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> মোবাইল অ্যাপ সুবিধা</li>
                    <li><i class="fas fa-caret-right text-blue-500"></i> ক্লাউড ব্যাকআপ</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto text-center mb-16">
            <h2 class="text-blue-600 font-bold uppercase tracking-widest text-sm mb-4">প্রাইসিং প্ল্যান</h2>
            <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900">ব্যবসার বড় করার বিনিয়োগ</h3>
            <p class="text-slate-500 mt-4 text-lg">কোনো লুকানো চার্জ নেই • ফ্রি অনলাইন ট্রেনিং</p>
        </div>
        <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Startup -->
            <div class="p-8 rounded-3xl border border-slate-200 bg-white flex flex-col hover:border-blue-600 transition-all">
                <h4 class="text-xl font-bold text-slate-800 mb-2">Startup</h4>
                <div class="mb-6">
                    <span class="text-4xl font-black">৳১,৪৯৯</span>
                    <span class="text-slate-500">/মাসিক</span>
                </div>
                <ul class="space-y-4 mb-10 flex-grow text-slate-700">
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ১টি ব্রাঞ্চের জন্য</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ইনভেন্টরি ও সেলস</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ডেইলি লাভ-ক্ষতি রিপোর্ট</li>
                </ul>
                <button class="w-full py-4 border border-blue-600 text-blue-600 font-bold rounded-xl hover:bg-blue-50 transition">শুরু করুন</button>
            </div>
            
            <!-- Business (Featured) -->
            <div class="p-8 rounded-3xl border-2 border-blue-600 bg-white flex flex-col shadow-2xl relative scale-105 z-10">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-4 py-1 rounded-full text-xs font-bold uppercase">জনপ্রিয়</div>
                <h4 class="text-xl font-bold text-slate-800 mb-2">Business</h4>
                <div class="mb-6">
                    <span class="text-4xl font-black text-blue-600">৳২,৯৯৯</span>
                    <span class="text-slate-500">/মাসিক</span>
                </div>
                <ul class="space-y-4 mb-10 flex-grow text-slate-700 font-medium">
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> আনলিমিটেড প্রোডাক্ট</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ৩টি ব্রাঞ্চ লিংক</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> কাস্টমার SMS রিমাইন্ডার</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ক্লাউড ব্যাকআপ সুবিধা</li>
                </ul>
                <button class="w-full py-4 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg">১৪ দিনের ফ্রি ব্যবহার</button>
                <p class="text-red-500 font-bold mt-4 animate-bounce text-center text-sm">
                    ⚡ এই মাসে সাইনআপ করলে ফ্রি সেটআপ
                </p>
                <p class="text-xs text-slate-400 text-center mt-1">এই অফার সীমিত সময়ের জন্য।</p>
            </div>

            <!-- Enterprise -->
            <div class="p-8 rounded-3xl border border-slate-200 bg-white flex flex-col hover:border-blue-600 transition-all">
                <h4 class="text-xl font-bold text-slate-800 mb-2">Enterprise</h4>
                <div class="mb-6 text-2xl font-bold">আলোচনা সাপেক্ষে</div>
                <ul class="space-y-4 mb-10 flex-grow text-slate-700">
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> আনলিমিটেড ব্রাঞ্চ</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> নিজস্ব সার্ভার সেটআপ</li>
                    <li class="flex items-center gap-3"><i class="fas fa-circle-check text-blue-600"></i> ২৪/৭ ডেডিকেটেড ম্যানেজার</li>
                </ul>
                <button class="w-full py-4 border border-slate-300 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition">কাস্টম প্ল্যান নিন</button>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="py-24 px-4 bg-slate-900 text-white relative overflow-hidden">
        <!-- SVG Divider -->
        <div class="absolute top-0 left-0 w-full overflow-hidden leading-none">
            <svg class="relative block w-full h-[50px] fill-slate-50" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"></path>
            </svg>
        </div>

        <div class="max-w-5xl mx-auto text-center mt-10">
            <h3 class="text-3xl md:text-5xl font-black mb-6 leading-tight">
                আজ হিসাব ডিজিটাল না করলে,
                <br>
                কাল আফসোস করতে হতে পারে।
            </h3>
            <p class="text-xl text-slate-400 mb-10 max-w-2xl mx-auto">ব্যবসার ছোট বড় প্রতিটি তথ্য আপনার হাতের মুঠোয় রাখতে আজই শুরু করুন।</p>
            
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <button class="bg-orange-500 hover:bg-orange-600 text-white px-12 py-5 rounded-2xl font-bold text-2xl transition shadow-xl w-full sm:w-auto hover:scale-105 active:scale-95">
                    🔥 এখনই ফ্রি ডেমো নিন
                </button>
                <a href="https://wa.me/8801234567890" class="flex items-center gap-3 text-green-400 text-xl font-bold hover:text-green-300 transition">
                    <i class="fab fa-whatsapp text-3xl"></i> WhatsApp এ চ্যাট করুন
                </a>
            </div>
            
            <p class="mt-8 text-slate-500">আপনার ব্যবসা কি স্মার্ট করার সময় হয়নি?</p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 border-t border-slate-800 py-12 px-4">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white">
                    <i class="fas fa-terminal"></i>
                </div>
                <span class="text-xl font-bold text-white">CoreVisys</span>
            </div>
            <div class="flex flex-wrap gap-8 text-slate-400 text-sm">
                <a href="#features" class="hover:text-white transition">ফিচারসমূহ</a>
                <a href="#pricing" class="hover:text-white transition">প্রাইসিং</a>
                <a href="#comparison" class="hover:text-white transition">তুলনা</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white transition">প্রাইভেসি</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-white transition">শর্তাবলী</a>
            </div>
            <p class="text-slate-500 text-sm">© 2026 CoreVisys Bangladesh. ব্যবসার জন্য সেরা সমাধান।</p>
        </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/8801234567890" class="fixed bottom-6 right-6 z-50 bg-green-500 text-white w-14 h-14 rounded-full flex items-center justify-center text-3xl shadow-2xl hover:bg-green-600 transition-all hover:scale-110 pulse-orange">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Swiper Initialization scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Vertical Announcement Swiper
            const verticalSwiper = new Swiper('.vertical-swiper', {
                direction: 'vertical',
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                },
                speed: 600,
                mousewheel: true,
                grabCursor: true,
            });

            // Coverflow Dashboard Swiper
            const coverflowSwiper = new Swiper('.coverflow-swiper', {
                effect: 'coverflow',
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: 'auto',
                loop: true,
                coverflowEffect: {
                    rotate: 15,
                    stretch: 0,
                    depth: 100,
                    modifier: 1,
                    slideShadows: true,
                },
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false,
                },
                speed: 800,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
            });
        });
    </script>

</body>
</html>






