<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $guestKey = request()->routeIs('register') ? 'register' : 'login';
    @endphp
    <x-seo-meta :page-key="$pageKey ?? $guestKey" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- CSS / Tailwind -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Hind Siliguri', 'Inter', sans-serif; }
    </style>
</head>
<body class="text-slate-900 antialiased gradient-bg min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a href="/" class="flex justify-center items-center gap-3 hover:scale-105 transition-transform duration-300">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                <i class="fas fa-terminal text-white text-2xl"></i>
            </div>
            <span class="text-3xl font-extrabold tracking-tight text-slate-900">
                CoreVisys<span class="text-blue-600">POS</span>
            </span>
        </a>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[480px]">
        <div class="bg-white/80 backdrop-blur-xl py-10 px-6 sm:px-12 rounded-[2rem] shadow-premium-lg border border-slate-100/50">
            {{ $slot }}
        </div>
    </div>

</body>
</html>
