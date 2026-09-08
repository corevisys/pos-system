<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo-meta :page-key="$pageKey ?? 'home'" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    @vite('resources/css/app.css')
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex flex-col justify-between">

    <!-- Top Public Navbar -->
    <header class="bg-white/90 backdrop-blur-md border-b border-slate-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 md:h-20">
                <!-- Logo -->
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center shadow-md shadow-blue-500/20 text-white">
                        <i class="fas fa-terminal text-lg"></i>
                    </div>
                    <span class="text-xl md:text-2xl font-black tracking-tight text-slate-900">
                        CoreVisys<span class="text-blue-600">POS</span>
                    </span>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-8 font-bold text-xs uppercase tracking-wider text-slate-600">
                    <a href="{{ url('/#features') }}" class="hover:text-blue-600 transition">ফিচারসমূহ</a>
                    <a href="{{ url('/#comparison') }}" class="hover:text-blue-600 transition">তুলনা</a>
                    <a href="{{ url('/#pricing') }}" class="hover:text-blue-600 transition">মূল্য পরিকল্পনা</a>
                    <a href="{{ route('legal.privacy') }}" class="hover:text-blue-600 transition {{ request()->routeIs('legal.privacy') ? 'text-blue-600' : '' }}">Privacy</a>
                    <a href="{{ route('legal.terms') }}" class="hover:text-blue-600 transition {{ request()->routeIs('legal.terms') ? 'text-blue-600' : '' }}">Terms</a>
                </nav>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center gap-2">
                            <i class="fas fa-chart-pie"></i> ড্যাশবোর্ড
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 text-slate-700 hover:text-blue-600 text-xs font-black uppercase tracking-wider transition">
                            লগইন
                        </a>
                        <a href="{{ route('register') }}" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-blue-700 transition shadow-md shadow-blue-500/20">
                            ফ্রি ট্রায়াল
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        {{ $slot }}
    </main>

    <!-- Public Footer -->
    <footer class="bg-slate-900 text-slate-400 border-t border-slate-800 py-12 px-4 mt-16">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-terminal"></i>
                </div>
                <span class="text-xl font-black text-white">CoreVisys<span class="text-blue-400">POS</span></span>
            </div>
            <div class="flex flex-wrap justify-center gap-6 text-xs font-bold uppercase tracking-wider">
                <a href="{{ url('/') }}" class="hover:text-white transition">হোম</a>
                <a href="{{ url('/#features') }}" class="hover:text-white transition">ফিচারসমূহ</a>
                <a href="{{ url('/#pricing') }}" class="hover:text-white transition">প্রাইসিং</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white transition">প্রাইভেসি পলিসি</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-white transition">শর্তাবলী</a>
            </div>
            <p class="text-slate-500 text-xs">© {{ date('Y') }} CoreVisysPOS. সর্বস্বত্ব সংরক্ষিত।</p>
        </div>
    </footer>

</body>
</html>
