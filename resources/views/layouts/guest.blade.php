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
<body class="bg-forest-50 text-slate-800 antialiased">
    <div class="mx-auto flex min-h-dvh w-full max-w-lg flex-col justify-center px-4 py-8">
        <header class="mb-6 text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-forest-700">ForestGuard</p>
            <h1 class="mt-1 text-2xl font-semibold text-forest-900">@yield('heading', 'Masuk ke akun')</h1>
        </header>
        @yield('content')
    </div>
</body>
</html>
