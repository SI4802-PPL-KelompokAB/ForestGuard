@extends('layouts.app')

@section('title', 'Beranda Ranger')

@section('content')
    <x-card>
        <h1 class="text-xl font-semibold text-forest-900">Beranda Ranger</h1>
        <p class="mt-2 text-sm text-slate-600">Halo, {{ auth()->user()->name }}. Modul patroli akan tersedia pada PBI berikutnya.</p>
    </x-card>
@endsection
