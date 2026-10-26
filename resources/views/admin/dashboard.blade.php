@extends('layouts.app')

@section('title', 'Dasbor')

@section('content')
    <x-card>
        <h1 class="text-xl font-semibold text-forest-900">Dasbor</h1>
        <p class="mt-2 text-sm text-slate-600">Halo, {{ auth()->user()->name }}. Ringkasan GIS dan verifikasi akan tersedia pada PBI berikutnya.</p>
    </x-card>
@endsection
