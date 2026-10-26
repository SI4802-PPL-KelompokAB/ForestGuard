<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#166534">
    <title>Akses ditolak</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-4">
        <x-card>
            <p class="text-sm font-semibold text-forest-700">HTTP 403</p>
            <h1 class="mt-2 text-2xl font-semibold">Akses ditolak</h1>
            <p class="mt-2 text-sm text-slate-600">Anda tidak memiliki izin untuk membuka halaman ini.</p>
            <a href="{{ auth()->check() ? app(\App\Support\RoleHome::class)->url(auth()->user()) : route('login') }}" class="mt-4 inline-block text-sm font-semibold text-forest-700">Kembali</a>
        </x-card>
    </div>
</body>
</html>
