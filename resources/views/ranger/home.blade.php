@extends('layouts.app')

@section('title', 'Beranda Ranger')

@section('content')
    <x-card>
        <h1 class="text-xl font-semibold text-forest-900">Beranda Ranger</h1>
        <p class="mt-2 text-sm text-slate-600">Halo, {{ auth()->user()->name }}.</p>
        <a href="{{ route('ranger.patrols.index') }}" class="mt-3 inline-block text-forest-700 underline">Lihat tugas patroli</a>
    </x-card>
@endsection
