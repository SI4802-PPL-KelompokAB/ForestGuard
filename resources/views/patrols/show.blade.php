@extends('layouts.app')

@section('title', 'Detail patroli '.$patrol->code)

@section('content')
    <a href="{{ route(auth()->user()->isAdmin() ? 'admin.patrols.index' : 'ranger.patrols.index') }}" class="text-forest-700 underline">Kembali ke daftar patroli</a>
    <x-card class="mt-4 max-w-3xl space-y-3">
        <h1 class="text-xl font-semibold text-forest-900">{{ $patrol->code }}</h1>
        <p>Area: {{ $patrol->forestArea->name }}</p>
        <p>Ranger: {{ $patrol->ranger->name }}</p>
        <p>Pembuat: {{ $patrol->creator->name }}</p>
        <p>Status: <strong>{{ $patrol->status->value }}</strong></p>
        <p>Jadwal: {{ $patrol->scheduled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} {{ config('app.timezone') }}</p>
        <p>Jarak: {{ $patrol->total_distance_km === null ? '—' : $patrol->total_distance_km.' km' }}</p>
        <h2 class="font-semibold">Rencana checkpoint</h2>
        <ol class="space-y-2">
            @foreach ($patrol->patrolCheckpoints as $planned)
                <li>{{ $planned->sequence }}. {{ $planned->checkpoint->code }} — {{ $planned->checkpoint->name }} · {{ $planned->visited_at ? 'Sudah dikunjungi' : 'Belum dikunjungi' }}</li>
            @endforeach
        </ol>
    </x-card>
@endsection
