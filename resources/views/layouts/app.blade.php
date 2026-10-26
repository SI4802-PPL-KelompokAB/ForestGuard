<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#166534">
    <meta name="mobile-web-app-capable" content="yes">
    <title>@yield('title', 'ForestGuard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    @php
        $user = auth()->user();
        $navItems = [];
        if ($user?->isCitizen()) {
            $navItems[] = ['label' => 'Laporan Saya', 'href' => route('citizen.home'), 'active' => 'citizen.home'];
        }
        if ($user?->isRanger()) {
            $navItems[] = ['label' => 'Beranda Ranger', 'href' => route('ranger.home'), 'active' => 'ranger.home'];
        }
        if ($user?->isAdmin()) {
            $navItems[] = ['label' => 'Dasbor', 'href' => route('admin.dashboard'), 'active' => 'admin.dashboard'];
            $navItems[] = ['label' => 'Pengguna', 'href' => route('admin.users.index'), 'active' => 'admin.users.*'];
        }
    @endphp

    <div class="min-h-dvh md:flex">
        <aside class="hidden w-64 shrink-0 bg-forest-900 text-white md:flex md:flex-col">
            <div class="px-5 py-6">
                <p class="text-lg font-semibold">ForestGuard</p>
                <p class="mt-1 text-sm text-forest-100">{{ $user?->name }}</p>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-3">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}"
                       class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs($item['active']) ? 'bg-forest-700' : 'hover:bg-forest-800' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="p-3">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-forest-800 px-3 py-2 text-sm hover:bg-forest-700">Keluar</button>
            </form>
        </aside>

        <div class="flex min-h-dvh flex-1 flex-col pb-20 md:pb-0">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
                <div>
                    <p class="text-sm font-semibold text-forest-800">ForestGuard</p>
                    <p class="text-xs text-slate-500">{{ $user?->name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-forest-700 px-3 py-1.5 text-sm text-white">Keluar</button>
                </form>
            </header>

            <main class="flex-1 px-4 py-5 md:px-8">
                @if (session('status'))
                    <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
                @endif
                @if (session('warning'))
                    <x-alert type="warning" class="mb-4">{{ session('warning') }}</x-alert>
                @endif
                @if ($errors->any())
                    <x-alert type="error" class="mb-4">{{ $errors->first() }}</x-alert>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white md:hidden">
        <div class="mx-auto flex max-w-lg">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="flex-1 py-3 text-center text-sm {{ request()->routeIs($item['active']) ? 'font-semibold text-forest-700' : 'text-slate-500' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</body>
</html>
