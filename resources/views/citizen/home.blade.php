@extends('layouts.app')

@section('title', 'Laporan Saya')

@section('content')
    <x-card>
        <h1 class="text-xl font-semibold text-forest-900">Laporan Saya</h1>
        <p class="mt-2 text-sm text-slate-600">Halo, {{ auth()->user()->name }}. Daftar laporan akan tersedia pada PBI berikutnya.</p>
    </x-card>
@endsection
