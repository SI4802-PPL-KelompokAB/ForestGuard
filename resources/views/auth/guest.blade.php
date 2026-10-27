@php
    $isRegister = $mode === 'register';
@endphp

@extends('layouts.guest')

@section('title', $isRegister ? 'Daftar ForestGuard' : 'Masuk ForestGuard')
@section('heading', $isRegister ? 'Daftar sebagai warga' : 'Masuk ke akun')

@section('content')
    <x-card>
        <div class="mb-5 grid grid-cols-2 rounded-lg bg-slate-100 p-1 text-sm">
            <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-center {{ $isRegister ? 'text-slate-600' : 'bg-white font-semibold text-forest-800 shadow-sm' }}">Masuk</a>
            <a href="{{ route('register') }}" class="rounded-md px-3 py-2 text-center {{ $isRegister ? 'bg-white font-semibold text-forest-800 shadow-sm' : 'text-slate-600' }}">Daftar sebagai warga</a>
        </div>

        @if ($errors->any() && ! $isRegister)
            <x-alert type="error" class="mb-4">{{ $errors->first() }}</x-alert>
        @endif

        @if ($isRegister)
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf
                <x-input name="name" label="Nama lengkap" required autocomplete="name" />
                <x-input name="email" type="email" label="Email" required autocomplete="email" />
                <x-input name="password" type="password" label="Kata sandi" required autocomplete="new-password" />
                <x-input name="password_confirmation" type="password" label="Konfirmasi kata sandi" required autocomplete="new-password" />
                <x-button>Daftar</x-button>
            </form>
        @else
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-input name="email" type="email" label="Email" placeholder="user@gmail.com" required autocomplete="username" />
                <x-input name="password" type="password" label="Kata sandi" required autocomplete="current-password" />
                <x-button>Masuk</x-button>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-4">
                <p class="mb-2 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Akun Uji Coba (Klik untuk isi otomatis):</p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" onclick="fillLogin('admin@gmail.com')" class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center text-xs transition hover:border-forest-500 hover:bg-forest-50">
                        <span class="block font-semibold text-forest-800">Admin</span>
                        <span class="block text-[11px] text-slate-500">admin@gmail.com</span>
                    </button>
                    <button type="button" onclick="fillLogin('ranger@gmail.com')" class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center text-xs transition hover:border-forest-500 hover:bg-forest-50">
                        <span class="block font-semibold text-forest-800">Ranger</span>
                        <span class="block text-[11px] text-slate-500">ranger@gmail.com</span>
                    </button>
                    <button type="button" onclick="fillLogin('user@gmail.com')" class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center text-xs transition hover:border-forest-500 hover:bg-forest-50">
                        <span class="block font-semibold text-forest-800">Warga</span>
                        <span class="block text-[11px] text-slate-500">user@gmail.com</span>
                    </button>
                </div>
                <p class="mt-2 text-center text-xs text-slate-500">Kata sandi default: <code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-slate-700">Password123!</code></p>
            </div>

            <script>
                function fillLogin(email) {
                    const emailInput = document.getElementById('email');
                    const passwordInput = document.getElementById('password');
                    if (emailInput) {
                        emailInput.value = email;
                    }
                    if (passwordInput) {
                        passwordInput.value = 'Password123!';
                    }
                }
            </script>
        @endif
    </x-card>
@endsection
