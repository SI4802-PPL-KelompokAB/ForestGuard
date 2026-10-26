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
                <x-input name="email" type="email" label="Email" required autocomplete="username" />
                <x-input name="password" type="password" label="Kata sandi" required autocomplete="current-password" />
                <x-button>Masuk</x-button>
            </form>
        @endif
    </x-card>
@endsection
